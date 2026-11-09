<?php
defined( 'ABSPATH' ) || exit;

class ADV_Stripe {

    public function __construct() {
        add_action( 'init', [ $this, 'handle_checkout_redirect' ] );
        add_action( 'init', [ $this, 'handle_payment_success' ] );
    }

    /**
     * Load Stripe PHP SDK
     */
    private static function load() {
        $autoloader = ADV_PATH . 'vendor/autoload.php';
        if ( ! file_exists( $autoloader ) ) {
            wp_die( 'Stripe SDK not found. Please run <code>composer require stripe/stripe-php</code> inside the advertisement plugin folder.' );
        }
        require_once $autoloader;
        \Stripe\Stripe::setApiKey( ADV_Stripe_Settings::get( 'secret_key' ) );
    }

    /**
     * Create a Stripe hosted checkout session
     * Called when user clicks "Get Started" on a plan
     */
    /**
     * Create a Stripe hosted checkout session
     * Called when user clicks "Get Started" on a plan
     */
    public static function create_checkout_session( $plan_id, $coupon_code = null, $quantity = 1 ) {
        self::load();
        ADV_Plans::init();

        $quantity = max( 1, intval( $quantity ) );
        $plan     = ADV_Plans::get( $plan_id );
        if ( ! $plan ) {
            return [ 'error' => 'Invalid plan selected.' ];
        }

        // Auto-sync plan with Stripe on the fly if not synced yet
        if ( empty( $plan->stripe_price_id ) && ADV_Stripe_Settings::is_configured() ) {
            ADV_Plans::sync( $plan_id );
            $plan = ADV_Plans::get( $plan_id );
        }

        if ( empty( $plan->stripe_price_id ) ) {
            return [ 'error' => 'This plan is not connected to Stripe yet. Please ensure Stripe API keys are configured in admin settings.' ];
        }

        $user    = wp_get_current_user();
        $user_id = get_current_user_id();

        // Build line items with selected quantity
        $line_items = [[
            'price'    => $plan->stripe_price_id,
            'quantity' => $quantity,
        ]];

        // Base session params
        $params = [
            'mode'                => 'subscription',
            'line_items'          => $line_items,
            'customer_email'      => $user->user_email,
            'success_url'         => add_query_arg([
                'adv_payment' => 'success',
                'session_id'  => '{CHECKOUT_SESSION_ID}',
                'plan_id'     => $plan_id,
                'quantity'    => $quantity,
            ], home_url( '/dashboard/' )),
            'cancel_url'          => add_query_arg( 'adv_payment', 'cancelled', home_url( '/pricing/' ) ),
            'metadata'            => [
                'user_id'  => $user_id,
                'plan_id'  => $plan_id,
                'quantity' => $quantity,
            ],
            'subscription_data'   => [
                'metadata' => [
                    'user_id'  => $user_id,
                    'plan_id'  => $plan_id,
                    'quantity' => $quantity,
                ],
            ],
        ];

        // Apply coupon as free trial
        if ( $coupon_code ) {
            ADV_Coupons::init();
            $result = ADV_Coupons::validate( $coupon_code, $user_id );
            if ( $result['valid'] ) {
                $params['subscription_data']['trial_period_days'] = intval( $result['coupon']->trial_days );
                $params['metadata']['coupon_code']                = $coupon_code;
                $params['metadata']['coupon_id']                  = $result['coupon']->id;
            }
        }

        try {
            $session = \Stripe\Checkout\Session::create( $params );
            return [ 'url' => $session->url ];
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            return [ 'error' => $e->getMessage() ];
        }
    }

    /**
     * Immediately cancel a Stripe subscription
     *
     * @param  string $subscription_id  Stripe subscription ID (sub_xxx)
     * @return array  ['success' => true] or ['error' => '...']
     */
    public static function cancel_subscription( $subscription_id ) {
        if ( empty( $subscription_id ) ) {
            return [ 'error' => 'No subscription ID provided.' ];
        }

        try {
            self::load();
            $subscription = \Stripe\Subscription::retrieve( $subscription_id );
            $subscription->cancel(); // Immediate cancellation
            return [ 'success' => true ];
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            return [ 'error' => $e->getMessage() ];
        }
    }

    /**
     * Listen for ?adv_checkout=1&plan_id=X in URL
     * Redirects logged-in user to Stripe
     */
    public function handle_checkout_redirect() {
        if ( ! isset( $_GET['adv_checkout'] ) ) return;
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( add_query_arg( 'redirect_to', urlencode( $_SERVER['REQUEST_URI'] ), home_url( '/login/' ) ) );
            exit;
        }

        $plan_id     = intval( $_GET['plan_id'] ?? 0 );
        $quantity    = max( 1, intval( $_GET['quantity'] ?? 1 ) );
        $coupon_code = sanitize_text_field( $_GET['coupon'] ?? '' );

        if ( ! $plan_id ) {
            wp_safe_redirect( home_url( '/pricing/' ) );
            exit;
        }

        $result = self::create_checkout_session( $plan_id, $coupon_code, $quantity );

        if ( isset( $result['error'] ) ) {
            adv_set_flash( 'checkout_error', $result['error'] );
            wp_safe_redirect( home_url( '/pricing/' ) );
            exit;
        }

        wp_redirect( $result['url'] );
        exit;
    }

    /**
     * Listen for ?adv_payment=success&session_id=X in URL
     * Verifies Stripe checkout session synchronously using API key and saves booking
     */
    public function handle_payment_success() {
        if ( ! isset( $_GET['adv_payment'] ) || $_GET['adv_payment'] !== 'success' ) return;
        if ( empty( $_GET['session_id'] ) ) return;

        $session_id = sanitize_text_field( $_GET['session_id'] );
        $user_id    = get_current_user_id();

        if ( ! $user_id ) return;

        try {
            self::load();
            $session = \Stripe\Checkout\Session::retrieve( $session_id );

            if ( $session && ( $session->payment_status === 'paid' || $session->status === 'complete' ) ) {
                global $wpdb;

                $plan_id     = intval( $session->metadata->plan_id ?? $_GET['plan_id'] ?? 0 );
                $quantity    = max( 1, intval( $session->metadata->quantity ?? $_GET['quantity'] ?? 1 ) );
                $coupon_code = $session->metadata->coupon_code ?? null;
                $coupon_id   = intval( $session->metadata->coupon_id ?? 0 );

                $subscription_id = $session->subscription ?? '';
                $customer_id     = $session->customer ?? '';

                ADV_Plans::init();
                $plan = ADV_Plans::get( $plan_id );
                $unit_price   = $plan ? (float) $plan->price : 0.00;
                $total_amount = $unit_price * $quantity;

                // Prevent duplicate processing
                $existing = false;
                if ( $subscription_id ) {
                    $existing = $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}adv_bookings WHERE stripe_subscription_id = %s",
                        $subscription_id
                    ) );
                }

                if ( ! $existing && $plan_id ) {
                    // Cancel previous active bookings for this user so current purchase sets the active quota
                    $wpdb->update(
                        $wpdb->prefix . 'adv_bookings',
                        [ 'status' => 'cancelled' ],
                        [ 'user_id' => $user_id, 'status' => 'active' ]
                    );

                    // Save booking
                    $wpdb->insert( $wpdb->prefix . 'adv_bookings', [
                        'user_id'                => $user_id,
                        'plan_id'                => $plan_id,
                        'quantity'               => $quantity,
                        'amount'                 => $total_amount,
                        'status'                 => 'active',
                        'stripe_subscription_id' => $subscription_id,
                        'stripe_customer_id'     => $customer_id,
                        'starts_at'              => current_time( 'mysql' ),
                    ] );

                    // Mark coupon as used if present
                    if ( $coupon_code && $coupon_id ) {
                        ADV_Coupons::init();
                        ADV_Coupons::mark_used( $coupon_id, $user_id );
                    }

                    // Update user meta so listing form unlocks
                    update_user_meta( $user_id, 'adv_payment_completed', 1 );
                    update_user_meta( $user_id, 'adv_active_plan_id', $plan_id );

                    error_log( 'ADV Stripe Payment Success - Calling adv_sync_user_listing_quota for user ' . $user_id );
                    adv_sync_user_listing_quota( $user_id );

                    do_action( 'adv_payment_confirmed', $user_id, $plan_id );
                } else if ( $existing ) {
                    update_user_meta( $user_id, 'adv_payment_completed', 1 );
                    if ( $plan_id ) {
                        update_user_meta( $user_id, 'adv_active_plan_id', $plan_id );
                    }
                    error_log( 'ADV Stripe Payment Success (existing) - Calling adv_sync_user_listing_quota for user ' . $user_id );
                    adv_sync_user_listing_quota( $user_id );
                }

                // Check if user has any listings, redirect accordingly
                $user_listings = get_posts([
                    'post_type'   => 'adv_listing',
                    'author'      => $user_id,
                    'post_status' => 'any',
                    'numberposts' => 1,
                    'fields'      => 'ids',
                ]);

                if ( empty( $user_listings ) ) {
                    // No listings, redirect to add listing page
                    wp_safe_redirect( home_url( '/add-listing/' ) );
                } else {
                    // Has listings, redirect to dashboard to show restoration message
                    wp_safe_redirect( home_url( '/dashboard/' ) );
                }
                exit;

            }
        } catch ( \Exception $e ) {
            error_log( 'ADV Stripe session retrieval error: ' . $e->getMessage() );
        }
    }
}