<?php
defined( 'ABSPATH' ) || exit;

class ADV_Pricing_Shortcode {

    public function __construct() {
        add_shortcode( 'goss_pricing',  [ $this, 'render_pricing' ] );
        add_shortcode( 'goss_checkout', [ $this, 'render_checkout' ] );
        add_action( 'wp_ajax_adv_validate_coupon',        [ $this, 'ajax_validate_coupon' ] );
        add_action( 'wp_ajax_nopriv_adv_validate_coupon', [ $this, 'ajax_validate_coupon' ] );
        add_action( 'init', [ $this, 'handle_bank_transfer' ] );
    }

    public function render_pricing( $atts ) {
        ADV_Plans::init();
        $plans = ADV_Plans::get_all();
        ob_start();
        include ADV_PATH . 'public/views/pricing/pricing-page.php';
        return ob_get_clean();
    }

    public function render_checkout( $atts ) {
        $plan_id  = intval( $_GET['plan_id'] ?? 0 );
        $quantity = max( 1, intval( $_GET['quantity'] ?? 1 ) );

        if ( ! $plan_id ) {
            return '<p style="text-align:center;padding:40px;">No plan selected. <a href="' . esc_url( home_url( '/pricing/' ) ) . '">Browse plans →</a></p>';
        }

        ADV_Plans::init();
        $plan = ADV_Plans::get( $plan_id );

        if ( ! $plan || ! $plan->is_active ) {
            return '<p style="text-align:center;padding:40px;">Plan not found. <a href="' . esc_url( home_url( '/pricing/' ) ) . '">Browse plans →</a></p>';
        }

        $features = ADV_Plans::get_features( $plan );

        // Check if a coupon was passed in URL or previously applied
        $applied_coupon = sanitize_text_field( $_GET['coupon'] ?? '' );
        $trial_days     = 0;

        if ( $applied_coupon ) {
            ADV_Coupons::init();
            $coupon_result = ADV_Coupons::validate( $applied_coupon, get_current_user_id() );
            if ( $coupon_result['valid'] ) {
                $trial_days = intval( $coupon_result['coupon']->trial_days );
            } else {
                $applied_coupon = ''; // invalid — clear it
            }
        }

        // Enqueue checkout assets
        wp_enqueue_style(  'adv-checkout', ADV_URL . 'public/assets/checkout.css', [], ADV_VERSION );
        wp_enqueue_script( 'adv-checkout', ADV_URL . 'public/assets/checkout.js',  [], ADV_VERSION, true );

        ob_start();
        include ADV_PATH . 'public/views/pricing/checkout-page.php';
        return ob_get_clean();
    }

    public function ajax_validate_coupon() {
        check_ajax_referer( 'adv_coupon_nonce', 'nonce' );

        $code    = sanitize_text_field( $_POST['code'] ?? '' );
        $user_id = get_current_user_id();

        if ( empty( $code ) ) {
            wp_send_json_error( [ 'message' => 'Please enter a coupon code.' ] );
        }

        ADV_Coupons::init();
        $result = ADV_Coupons::validate( $code, $user_id );

        if ( $result['valid'] ) {
            adv_set_flash( 'active_coupon', $code );
            wp_send_json_success( [ 'message' => $result['message'], 'trial_days' => $result['coupon']->trial_days ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }
    }

    /**
     * Handle Bank Transfer Form Submission (For Testing — can be removed anytime)
     */
    public function handle_bank_transfer() {
        if ( ! isset( $_REQUEST['adv_bank_transfer'] ) ) return;
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( add_query_arg( 'redirect_to', urlencode( $_SERVER['REQUEST_URI'] ), home_url( '/login/' ) ) );
            exit;
        }

        $user_id  = get_current_user_id();
        $plan_id  = intval( $_REQUEST['plan_id'] ?? 0 );
        $quantity = max( 1, intval( $_REQUEST['quantity'] ?? 1 ) );
        $coupon   = sanitize_text_field( $_REQUEST['coupon'] ?? '' );

        if ( ! $plan_id ) {
            adv_set_flash( 'checkout_error', 'Invalid plan selected.' );
            return;
        }

        ADV_Plans::init();
        $plan = ADV_Plans::get( $plan_id );
        $unit_price   = $plan ? (float) $plan->price : 0.00;
        $total_amount = $unit_price * $quantity;

        global $wpdb;

        // Cancel previous active bookings for this user so current purchase sets the active quota
        $wpdb->update(
            $wpdb->prefix . 'adv_bookings',
            [ 'status' => 'cancelled' ],
            [ 'user_id' => $user_id, 'status' => 'active' ]
        );

        // Insert booking record for Bank Transfer (Test Mode)
        $wpdb->insert( $wpdb->prefix . 'adv_bookings', [
            'user_id'                => $user_id,
            'plan_id'                => $plan_id,
            'quantity'               => $quantity,
            'amount'                 => $total_amount,
            'status'                 => 'active',
            'stripe_subscription_id' => 'bank_transfer_test',
            'stripe_customer_id'     => 'bank_transfer_test',
            'starts_at'              => current_time( 'mysql' ),
        ] );

        // If coupon was applied, mark used
        if ( $coupon ) {
            ADV_Coupons::init();
            $val = ADV_Coupons::validate( $coupon, $user_id );
            if ( $val['valid'] ) {
                ADV_Coupons::mark_used( $val['coupon']->id, $user_id );
            }
        }

        // Grant user active plan access
        update_user_meta( $user_id, 'adv_payment_completed', 1 );
        update_user_meta( $user_id, 'adv_active_plan_id', $plan_id );

        // Sync user listing statuses with new plan quota
        error_log( 'ADV Bank Transfer - Calling adv_sync_user_listing_quota for user ' . $user_id );
        adv_sync_user_listing_quota( $user_id );

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
            adv_set_flash( 'bank_transfer_success', sprintf( 'Bank transfer recorded (Test Mode) for %d listing(s). Plan activated!', $quantity ) );
            wp_safe_redirect( home_url( '/add-listing/' ) );
        } else {
            // Has listings, redirect to dashboard to show restoration message
            adv_set_flash( 'bank_transfer_success', sprintf( 'Bank transfer recorded (Test Mode) for %d listing(s). Plan activated!', $quantity ) );
            wp_safe_redirect( home_url( '/dashboard/' ) );
        }
        exit;
    }
}
