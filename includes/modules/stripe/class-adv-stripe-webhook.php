<?php
defined( 'ABSPATH' ) || exit;

class ADV_Stripe_Webhook {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_route' ] );
    }

    public function register_route() {
        register_rest_route( 'advertisement/v1', '/stripe-webhook', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle' ],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle( WP_REST_Request $request ) {
        $autoloader = ADV_PATH . 'vendor/autoload.php';
        if ( ! file_exists( $autoloader ) ) return new WP_REST_Response( 'SDK missing', 500 );
        require_once $autoloader;

        $payload    = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret     = ADV_Stripe_Settings::get( 'webhook_secret' );

        try {
            $event = \Stripe\Webhook::constructEvent( $payload, $sig_header, $secret );
        } catch ( \Exception $e ) {
            return new WP_REST_Response( 'Invalid signature', 400 );
        }

        switch ( $event->type ) {

            // ── Payment confirmed (first payment or trial start) ──
            case 'checkout.session.completed':
                $this->on_checkout_completed( $event->data->object );
                break;

            // ── Monthly renewal succeeded ─────────────────────────
            case 'invoice.payment_succeeded':
                $this->on_payment_succeeded( $event->data->object );
                break;

            // ── Monthly renewal failed ────────────────────────────
            case 'invoice.payment_failed':
                $this->on_payment_failed( $event->data->object );
                break;

            // ── Subscription cancelled ────────────────────────────
            case 'customer.subscription.deleted':
                $this->on_subscription_cancelled( $event->data->object );
                break;

            // ── Trial ending in 3 days ────────────────────────────
            case 'customer.subscription.trial_will_end':
                $this->on_trial_ending( $event->data->object );
                break;

            // ── Subscription updated (price/billing cycle change) ──
            case 'customer.subscription.updated':
                $this->on_subscription_updated( $event->data->object );
                break;
        }

        return new WP_REST_Response( 'OK', 200 );
    }

    // ─────────────────────────────────────────────────────────

    private function on_checkout_completed( $session ) {
        global $wpdb;

        $user_id     = intval( $session->metadata->user_id ?? 0 );
        $plan_id     = intval( $session->metadata->plan_id ?? 0 );
        $quantity    = max( 1, intval( $session->metadata->quantity ?? 1 ) );
        $coupon_code = $session->metadata->coupon_code ?? null;
        $coupon_id   = intval( $session->metadata->coupon_id ?? 0 );

        if ( ! $user_id || ! $plan_id ) return;

        ADV_Plans::init();
        $plan         = ADV_Plans::get( $plan_id );
        $unit_price   = $plan ? (float) $plan->price : 0.00;
        $total_amount = $unit_price * $quantity;

        // Save booking
        $wpdb->insert( $wpdb->prefix . 'adv_bookings', [
            'user_id'                => $user_id,
            'plan_id'                => $plan_id,
            'quantity'               => $quantity,
            'amount'                 => $total_amount,
            'status'                 => 'active',
            'stripe_subscription_id' => $session->subscription,
            'stripe_customer_id'     => $session->customer,
            'starts_at'              => current_time( 'mysql' ),
        ]);

        // Mark coupon as used
        if ( $coupon_code && $coupon_id ) {
            ADV_Coupons::init();
            ADV_Coupons::mark_used( $coupon_id, $user_id );
        }

        // Store session so listing form knows payment is done
        update_user_meta( $user_id, 'adv_payment_completed', 1 );
        update_user_meta( $user_id, 'adv_active_plan_id', $plan_id );

        // Sync user listings with new plan quota
        error_log( 'ADV Stripe Webhook - Calling adv_sync_user_listing_quota for user ' . $user_id );
        adv_sync_user_listing_quota( $user_id );

        // Fire hook for email module
        do_action( 'adv_payment_confirmed', $user_id, $plan_id );
    }

    private function on_payment_succeeded( $invoice ) {
        global $wpdb;

        $subscription_id = $invoice->subscription;

        $wpdb->update(
            $wpdb->prefix . 'adv_bookings',
            [ 'status' => 'active' ],
            [ 'stripe_subscription_id' => $subscription_id ]
        );

        // Sync listings with plan quota
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}adv_bookings WHERE stripe_subscription_id = %s",
            $subscription_id
        ));

        if ( $booking ) {
            error_log( 'ADV Stripe Webhook - Payment succeeded, calling adv_sync_user_listing_quota for user ' . $booking->user_id );
            adv_sync_user_listing_quota( $booking->user_id );
            do_action( 'adv_payment_renewal_success', $booking->user_id );
        }
    }

    private function on_payment_failed( $invoice ) {
        global $wpdb;

        $subscription_id = $invoice->subscription;

        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}adv_bookings WHERE stripe_subscription_id = %s",
            $subscription_id
        ));

        if ( ! $booking ) return;

        // Suspend listing
        $wpdb->update(
            $wpdb->prefix . 'adv_bookings',
            [ 'status' => 'suspended' ],
            [ 'stripe_subscription_id' => $subscription_id ]
        );

        adv_sync_user_listing_quota( $booking->user_id );

        do_action( 'adv_payment_failed', $booking->user_id );
    }

    private function on_subscription_cancelled( $subscription ) {
        global $wpdb;

        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}adv_bookings WHERE stripe_subscription_id = %s",
            $subscription->id
        ));

        if ( ! $booking ) return;

        $wpdb->update(
            $wpdb->prefix . 'adv_bookings',
            [ 'status' => 'cancelled' ],
            [ 'stripe_subscription_id' => $subscription->id ]
        );

        delete_user_meta( $booking->user_id, 'adv_payment_completed' );
        delete_user_meta( $booking->user_id, 'adv_active_plan_id' );

        adv_sync_user_listing_quota( $booking->user_id );

        do_action( 'adv_subscription_cancelled', $booking->user_id );
    }

    private function on_trial_ending( $subscription ) {
        global $wpdb;

        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}adv_bookings WHERE stripe_subscription_id = %s",
            $subscription->id
        ));

        if ( $booking ) {
            do_action( 'adv_trial_ending_soon', $booking->user_id );
        }
    }

    private function on_subscription_updated( $subscription ) {
        global $wpdb;

        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}adv_bookings WHERE stripe_subscription_id = %s",
            $subscription->id
        ));

        if ( ! $booking ) return;

        // Check if price or billing cycle changed
        $new_price_id = $subscription->items->data[0]->price->id ?? null;
        $new_interval = $subscription->items->data[0]->price->recurring->interval ?? null;
        
        // Update booking with new price ID if changed
        if ( $new_price_id ) {
            $wpdb->update(
                $wpdb->prefix . 'adv_bookings',
                [ 'stripe_price_id' => $new_price_id ],
                [ 'id' => $booking->id ]
            );
        }

        // Sync user quota in case billing cycle changed
        adv_sync_user_listing_quota( $booking->user_id );

        // Notify admin about subscription changes
        do_action( 'adv_subscription_updated', $booking->user_id, $subscription );
    }

    /**
     * Set the WordPress post status of the user's directory listings according to active quota
     */
    private function set_listing_status( $user_id, $status = '' ) {
        adv_sync_user_listing_quota( $user_id );
    }
}
