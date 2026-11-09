<?php
defined( 'ABSPATH' ) || exit;

class ADV_Magazine_Form {

    public function __construct() {
        add_shortcode( 'goss_magazine_advert', [ $this, 'render' ] );
        add_action( 'init', [ $this, 'handle_submit' ] );
        add_action( 'init', [ $this, 'handle_payment_success' ] );
        add_action( 'wp_enqueue_scripts', function() {
            wp_localize_script( 'adv-magazine-form', 'advMagPrices', ADV_Magazine_Settings::all_prices() );
        } );
    }

    // ─── Shortcode ───────────────────────────────────────────

    public function render( $atts ) {
        wp_enqueue_style(  'adv-magazine-form', ADV_URL . 'public/assets/magazine-form.css', [], ADV_VERSION );
        wp_enqueue_script( 'adv-magazine-form', ADV_URL . 'public/assets/magazine-form.js',  [], ADV_VERSION, true );
        wp_enqueue_style( 'adv-forms-theme', ADV_URL . 'public/assets/adv-forms-theme.css', [ 'adv-magazine-form' ], ADV_VERSION );

        if ( ! is_user_logged_in() ) {
            $login_url = add_query_arg( 'redirect_to', urlencode( home_url( '/magazine-advert/' ) ), home_url( '/login/' ) );
            return '
            <div class="adv-notice-card">
                <div class="adv-notice-icon-box purple">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div class="adv-notice-body">
                    <h3>Account Login Required</h3>
                    <p>Please log in to submit a magazine advert.</p>
                    <div class="adv-notice-actions">
                        <a href="' . esc_url( $login_url ) . '" class="adv-notice-btn primary">Log In Now</a>
                        <a href="' . esc_url( home_url( '/register/' ) ) . '" class="adv-notice-btn outline">Create Account</a>
                    </div>
                </div>
            </div>';
        }

        $prices  = ADV_Magazine_Settings::all_prices();
        $error   = adv_get_flash( 'mag_error' );
        $success = adv_get_flash( 'mag_success' );
        $old     = adv_get_flash( 'mag_old' ) ?: [];

        ob_start();
        include ADV_PATH . 'public/views/magazine/add-magazine-form.php';
        return ob_get_clean();
    }

    // ─── Handle submission ───────────────────────────────────

    public function handle_submit() {
        if ( ! isset( $_POST['adv_magazine_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['adv_magazine_nonce'], 'adv_submit_magazine' ) ) return;
        if ( ! is_user_logged_in() ) return;

        $user_id    = get_current_user_id();
        $ad_type    = sanitize_key( $_POST['ad_type'] ?? '' );
        $business   = sanitize_text_field( $_POST['business_name'] ?? '' );
        $contact    = sanitize_text_field( $_POST['contact_name'] ?? '' );
        $email      = sanitize_email( $_POST['contact_email'] ?? '' );
        $phone      = sanitize_text_field( $_POST['contact_phone'] ?? '' );
        $notes      = sanitize_textarea_field( $_POST['ad_notes'] ?? '' );

        $errors = [];
        if ( ! array_key_exists( $ad_type, ADV_Magazine_Settings::PRICES ) ) $errors[] = 'Please select a valid ad size.';
        if ( empty( $business ) ) $errors[] = 'Business name is required.';
        if ( empty( $email ) )    $errors[] = 'Contact email is required.';
        if ( empty( $_FILES['ad_image']['tmp_name'] ) ) $errors[] = 'Please upload your advert image.';

        if ( $errors ) {
            adv_set_flash( 'mag_error', implode( ' ', $errors ) );
            adv_set_flash( 'mag_old', $_POST );
            wp_safe_redirect( home_url( '/magazine-advert/' ) );
            exit;
        }

        $price = ADV_Magazine_Settings::get_price( $ad_type );
        $label = ADV_Magazine_Settings::get_label( $ad_type );

        // Create post
        $post_id = wp_insert_post( [
            'post_title'  => $business . ' — ' . $label,
            'post_status' => 'adv_pending_payment',
            'post_type'   => 'adv_magazine',
            'post_author' => $user_id,
        ] );

        if ( is_wp_error( $post_id ) ) {
            adv_set_flash( 'mag_error', 'Could not save your advert. Please try again.' );
            wp_safe_redirect( home_url( '/magazine-advert/' ) );
            exit;
        }

        // Store meta
        update_post_meta( $post_id, '_adv_mag_type',          $ad_type );
        update_post_meta( $post_id, '_adv_mag_price',         $price );
        update_post_meta( $post_id, '_adv_mag_business',      $business );
        update_post_meta( $post_id, '_adv_mag_contact_name',  $contact );
        update_post_meta( $post_id, '_adv_mag_contact_email', $email );
        update_post_meta( $post_id, '_adv_mag_contact_phone', $phone );
        update_post_meta( $post_id, '_adv_mag_notes',         $notes );

        // Upload advert image
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( 'ad_image', $post_id );
        if ( is_wp_error( $attachment_id ) ) {
            adv_set_flash( 'mag_error', 'Image upload failed: ' . $attachment_id->get_error_message() );
            wp_delete_post( $post_id, true );
            wp_safe_redirect( home_url( '/magazine-advert/' ) );
            exit;
        }
        update_post_meta( $post_id, '_adv_mag_image_id', $attachment_id );
        set_post_thumbnail( $post_id, $attachment_id );

        // Store payment method
        $payment_method = sanitize_key( $_POST['payment_method'] ?? 'stripe' );
        update_post_meta( $post_id, '_adv_mag_payment_method', $payment_method );

        // ── Bank Transfer path (TEST MODE) ───────────────────────────
        if ( $payment_method === 'bank_transfer' ) {
            update_post_meta( $post_id, '_adv_mag_bank_reference', 'TEST-BANK-TRANSFER' );
            update_post_meta( $post_id, '_adv_mag_paid',           1 );
            update_post_meta( $post_id, '_adv_mag_paid_at',        current_time( 'mysql' ) );

            wp_update_post( [
                'ID'          => $post_id,
                'post_status' => 'pending',
            ] );

            do_action( 'adv_magazine_submitted', $post_id, $user_id );

            adv_set_flash( 'dash_success', 'Test Mode: Your magazine advert has been submitted using Bank Transfer! It is now pending review by our team.' );
            wp_safe_redirect( home_url( '/dashboard/' ) );
            exit;
        }

        // Stripe checkout
        $checkout = self::create_stripe_checkout( $post_id, $user_id, $ad_type, $price, $label );
        if ( isset( $checkout['error'] ) ) {
            adv_set_flash( 'mag_error', $checkout['error'] );
            wp_safe_redirect( home_url( '/magazine-advert/' ) );
            exit;
        }

        wp_redirect( $checkout['url'] );
        exit;
    }

    // ─── Stripe checkout ─────────────────────────────────────

    public static function create_stripe_checkout( $post_id, $user_id, $ad_type, $price, $label ) {
        $autoloader = ADV_PATH . 'vendor/autoload.php';
        if ( ! file_exists( $autoloader ) ) {
            return [ 'error' => 'Payment system not available. Please contact us.' ];
        }
        require_once $autoloader;
        \Stripe\Stripe::setApiKey( ADV_Stripe_Settings::get( 'secret_key' ) );

        $user = get_userdata( $user_id );

        try {
            $session = \Stripe\Checkout\Session::create( [
                'mode'           => 'payment',
                'line_items'     => [ [
                    'price_data' => [
                        'currency'     => 'gbp',
                        'unit_amount'  => intval( $price * 100 ),
                        'product_data' => [
                            'name'        => 'Magazine Advert — ' . $label,
                            'description' => get_post_meta( $post_id, '_adv_mag_business', true ),
                        ],
                    ],
                    'quantity' => 1,
                ] ],
                'customer_email' => $user->user_email,
                'success_url'    => add_query_arg( [
                    'adv_mag_payment' => 'success',
                    'session_id'      => '{CHECKOUT_SESSION_ID}',
                    'mag_id'          => $post_id,
                ], home_url( '/magazine-advert/' ) ),
                'cancel_url'     => add_query_arg( 'adv_mag_payment', 'cancelled', home_url( '/magazine-advert/' ) ),
                'metadata'       => [
                    'user_id'  => $user_id,
                    'mag_id'   => $post_id,
                    'ad_type'  => $ad_type,
                    'type'     => 'magazine_advert',
                ],
            ] );

            return [ 'url' => $session->url ];
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            return [ 'error' => 'Payment error: ' . $e->getMessage() ];
        }
    }

    // ─── Payment success ─────────────────────────────────────

    public function handle_payment_success() {
        if ( ! isset( $_GET['adv_mag_payment'] ) || $_GET['adv_mag_payment'] !== 'success' ) return;
        if ( empty( $_GET['session_id'] ) || empty( $_GET['mag_id'] ) ) return;

        $mag_id     = intval( $_GET['mag_id'] );
        $session_id = sanitize_text_field( $_GET['session_id'] );

        $autoloader = ADV_PATH . 'vendor/autoload.php';
        if ( ! file_exists( $autoloader ) ) return;
        require_once $autoloader;
        \Stripe\Stripe::setApiKey( ADV_Stripe_Settings::get( 'secret_key' ) );

        try {
            $session = \Stripe\Checkout\Session::retrieve( $session_id );
        } catch ( \Exception $e ) {
            adv_set_flash( 'mag_error', 'Could not verify payment. Please contact us.' );
            wp_safe_redirect( home_url( '/magazine-advert/' ) );
            exit;
        }

        if ( $session->payment_status !== 'paid' ) {
            adv_set_flash( 'mag_error', 'Payment was not completed. Please try again.' );
            wp_safe_redirect( home_url( '/magazine-advert/' ) );
            exit;
        }

        $post = get_post( $mag_id );
        if ( ! $post || $post->post_type !== 'adv_magazine' ) return;

        // Move to pending (awaiting admin approval + magazine URL)
        wp_update_post( [
            'ID'          => $mag_id,
            'post_status' => 'pending',
        ] );

        update_post_meta( $mag_id, '_adv_mag_stripe_session', $session_id );
        update_post_meta( $mag_id, '_adv_mag_paid',           1 );
        update_post_meta( $mag_id, '_adv_mag_paid_at',        current_time( 'mysql' ) );

        do_action( 'adv_magazine_submitted', $mag_id, $post->post_author );

        adv_set_flash( 'mag_success', 'Your magazine advert has been submitted and payment confirmed! Our team will review it and get in touch once it is published in the magazine.' );
        wp_safe_redirect( home_url( '/magazine-advert/' ) );
        exit;
    }
}