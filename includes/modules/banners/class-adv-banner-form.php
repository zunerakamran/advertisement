<?php
defined( 'ABSPATH' ) || exit;

class ADV_Banner_Form {

    public function __construct() {
        add_shortcode( 'goss_add_banner', [ $this, 'render' ] );
        add_action( 'init', [ $this, 'handle_submit' ] );
        add_action( 'init', [ $this, 'handle_payment_success' ] );
        add_action( 'wp_ajax_adv_toggle_banner_autorenew', [ $this, 'ajax_toggle_autorenew' ] );
        add_action( 'wp_ajax_adv_toggle_banner_autorenew',        [ $this, 'ajax_toggle_autorenew' ] );
        add_action( 'wp_ajax_nopriv_adv_toggle_banner_autorenew', function() {
            wp_send_json_error( [ 'message' => 'Please log in to manage your banner.' ] );
        } );
    }

    // ─── Shortcode ───────────────────────────────────────────

    public function render( $atts ) {
        wp_enqueue_style(  'adv-banner-form', ADV_URL . 'public/assets/banner-form.css', [], ADV_VERSION );
        wp_enqueue_script( 'adv-banner-form', ADV_URL . 'public/assets/banner-form.js',  [], ADV_VERSION, true );
        wp_enqueue_style( 'adv-forms-theme', ADV_URL . 'public/assets/adv-forms-theme.css', [ 'adv-magazine-form' ], ADV_VERSION );
        
        wp_localize_script( 'adv-banner-form', 'advBannerPrices', ADV_Banner_Settings::all_prices() );

        if ( ! is_user_logged_in() ) {
            $login_url = add_query_arg( 'redirect_to', urlencode( home_url( '/submit-banner-ad/' ) ), home_url( '/login/' ) );
            return '
            <div class="adv-notice-card">
                <div class="adv-notice-icon-box purple">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div class="adv-notice-body">
                    <h3>Account Login Required</h3>
                    <p>Please log in to submit a homepage banner advert.</p>
                    <div class="adv-notice-actions">
                        <a href="' . esc_url( $login_url ) . '" class="adv-notice-btn primary">Log In Now</a>
                        <a href="' . esc_url( home_url( '/register/' ) ) . '" class="adv-notice-btn outline">Create Account</a>
                    </div>
                </div>
            </div>';
        }

        $prices  = ADV_Banner_Settings::all_prices();
        $error   = adv_get_flash( 'banner_error' );
        $success = adv_get_flash( 'banner_success' );
        $old     = adv_get_flash( 'banner_old' ) ?: [];

        ob_start();
        include ADV_PATH . 'public/views/banners/add-banner-form.php';
        return ob_get_clean();
    }

    // ─── Availability — configurable slots per size ──

    public static function slot_taken( $size, $exclude_id = 0 ) {
        $max_slots = ADV_Banner_Settings::get_slots( $size );
        if ( $max_slots <= 0 ) return false; // Unlimited slots if set to 0 or negative

        $existing = get_posts([
            'post_type'   => 'adv_banner',
            'meta_key'    => '_adv_banner_size',
            'meta_value'  => $size,
            'post_status' => [ 'publish', 'pending' ],
            'numberposts' => $max_slots,
            'exclude'     => [ $exclude_id ],
            'fields'      => 'ids',
        ]);
        return count( $existing ) >= $max_slots;
    }

    // ─── Handle submission ───────────────────────────────────

    public function handle_submit() {
        if ( ! isset( $_POST['adv_banner_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['adv_banner_nonce'], 'adv_submit_banner' ) ) return;
        if ( ! is_user_logged_in() ) return;

        $user_id  = get_current_user_id();
        $size     = sanitize_key( $_POST['banner_size'] ?? '' );
        $business = sanitize_text_field( $_POST['business_name'] ?? '' );
        $contact  = sanitize_text_field( $_POST['contact_name'] ?? '' );
        $email    = sanitize_email( $_POST['contact_email'] ?? '' );
        $phone    = sanitize_text_field( $_POST['contact_phone'] ?? '' );
        $link_url = esc_url_raw( $_POST['banner_url'] ?? '' );
        $notes    = sanitize_textarea_field( $_POST['banner_notes'] ?? '' );

        $errors = [];
        if ( ! array_key_exists( $size, ADV_Banner_Settings::BANNERS ) ) $errors[] = 'Please select a valid banner size.';
        if ( empty( $business ) ) $errors[] = 'Business name is required.';
        if ( empty( $email ) )    $errors[] = 'Contact email is required.';
        if ( empty( $link_url ) ) $errors[] = 'Please provide the website / landing page link the banner should point to.';
        if ( empty( $_FILES['banner_image']['tmp_name'] ) ) {
            $errors[] = 'Please upload your banner image.';
        } else {
            // Strict image dimension validation
            $img_info = @getimagesize( $_FILES['banner_image']['tmp_name'] );
            if ( ! $img_info ) {
                $errors[] = 'The uploaded file is not a valid image.';
            } else {
                $upload_w = $img_info[0];
                $upload_h = $img_info[1];
                $req_w    = ADV_Banner_Settings::get_width( $size );
                $req_h    = ADV_Banner_Settings::get_height( $size );
                $label    = ADV_Banner_Settings::get_label( $size );

                if ( $upload_w !== $req_w || $upload_h !== $req_h ) {
                    $errors[] = sprintf(
                        'Uploaded banner image size (%dx%d px) does not match the required dimensions for %s (%dx%d px). Please upload an image with exact size %dx%d px.',
                        $upload_w, $upload_h, $label, $req_w, $req_h, $req_w, $req_h
                    );
                }
            }
        }

        if ( ! $errors && self::slot_taken( $size ) ) {
            $errors[] = 'Sorry, the ' . ADV_Banner_Settings::get_label( $size ) . ' slot is currently taken. Banner spaces are limited — please choose another size or contact us about availability.';
        }

        if ( $errors ) {
            adv_set_flash( 'banner_error', implode( ' ', $errors ) );
            adv_set_flash( 'banner_old', $_POST );
            wp_safe_redirect( home_url( '/submit-banner-ad/' ) );
            exit;
        }

        $price = ADV_Banner_Settings::get_price( $size );
        $label = ADV_Banner_Settings::get_label( $size );

        // Create post
        $post_id = wp_insert_post( [
            'post_title'  => $business,
            'post_status' => 'draft',
            'post_type'   => 'adv_banner',
            'post_author' => $user_id,
        ] );

        if ( is_wp_error( $post_id ) ) {
            adv_set_flash( 'banner_error', 'Could not save your banner submission. Please try again.' );
            wp_safe_redirect( home_url( '/submit-banner-ad/' ) );
            exit;
        }

        // Store meta (auto_renew default enabled = 1, charges monthly)
        update_post_meta( $post_id, '_adv_banner_size',          $size );
        update_post_meta( $post_id, '_adv_banner_price',         $price );
        update_post_meta( $post_id, '_adv_banner_business',      $business );
        update_post_meta( $post_id, '_adv_banner_contact_name',  $contact );
        update_post_meta( $post_id, '_adv_banner_contact_email', $email );
        update_post_meta( $post_id, '_adv_banner_contact_phone', $phone );
        update_post_meta( $post_id, '_adv_banner_url',           $link_url );
        update_post_meta( $post_id, '_adv_banner_notes',         $notes );
        update_post_meta( $post_id, '_adv_banner_auto_renew',    1 );
        update_post_meta( $post_id, '_adv_banner_expires_at',    date( 'Y-m-d H:i:s', strtotime( '+1 month' ) ) );

        // Upload banner image
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( 'banner_image', $post_id );
        if ( is_wp_error( $attachment_id ) ) {
            adv_set_flash( 'banner_error', 'Image upload failed: ' . $attachment_id->get_error_message() );
            wp_delete_post( $post_id, true );
            wp_safe_redirect( home_url( '/submit-banner-ad/' ) );
            exit;
        }
        update_post_meta( $post_id, '_adv_banner_image_id', $attachment_id );
        set_post_thumbnail( $post_id, $attachment_id );

        // Payment method
        $payment_method = sanitize_key( $_POST['payment_method'] ?? 'stripe' );
        update_post_meta( $post_id, '_adv_banner_payment_method', $payment_method );

        // ── Bank Transfer path (TEST MODE) ───────────────────
        if ( $payment_method === 'bank_transfer' ) {
            update_post_meta( $post_id, '_adv_banner_bank_reference', 'TEST-BANK-TRANSFER' );
            update_post_meta( $post_id, '_adv_banner_paid',           1 );
            update_post_meta( $post_id, '_adv_banner_paid_at',        current_time( 'mysql' ) );

            $updated = wp_update_post( [
                'ID'          => $post_id,
                'post_status' => 'pending',
            ] );

            if ( is_wp_error( $updated ) || ! $updated ) {
                adv_set_flash( 'dash_error', 'Banner saved but status update failed. Please contact support.' );
                wp_safe_redirect( home_url( '/dashboard/#tab-banners' ) );
                exit;
            }

            do_action( 'adv_banner_submitted', $post_id, $user_id );

            adv_set_flash( 'dash_success', 'Test Mode: Your homepage banner has been submitted using Bank Transfer! It is now pending review by our team.' );
            wp_safe_redirect( home_url( '/dashboard/#tab-banners' ) );
            exit;
        }

        // Stripe checkout (Subscription / Monthly billing)
        $checkout = self::create_stripe_checkout( $post_id, $user_id, $size, $price, $label );
        if ( isset( $checkout['error'] ) ) {
            adv_set_flash( 'banner_error', $checkout['error'] );
            wp_safe_redirect( home_url( '/submit-banner-ad/' ) );
            exit;
        }

        wp_redirect( $checkout['url'] );
        exit;
    }

    // ─── Stripe checkout (Monthly Subscription) ───────────────

    public static function create_stripe_checkout( $post_id, $user_id, $size, $price, $label ) {
        $autoloader = ADV_PATH . 'vendor/autoload.php';
        if ( ! file_exists( $autoloader ) ) {
            return [ 'error' => 'Payment system not available. Please contact us.' ];
        }
        require_once $autoloader;
        \Stripe\Stripe::setApiKey( ADV_Stripe_Settings::get( 'secret_key' ) );

        $user = get_userdata( $user_id );

        try {
            $session = \Stripe\Checkout\Session::create( [
                'mode'           => 'subscription',
                'line_items'     => [ [
                    'price_data' => [
                        'currency'     => 'gbp',
                        'unit_amount'  => intval( $price * 100 ),
                        'recurring'    => [
                            'interval' => 'month',
                        ],
                        'product_data' => [
                            'name'        => 'Homepage Banner — ' . $label . ' (Monthly Auto-Renew)',
                            'description' => get_post_meta( $post_id, '_adv_banner_business', true ),
                        ],
                    ],
                    'quantity' => 1,
                ] ],
                'customer_email' => $user->user_email,
                'success_url'    => add_query_arg( [
                    'adv_banner_payment' => 'success',
                    'session_id'         => '{CHECKOUT_SESSION_ID}',
                    'banner_id'          => $post_id,
                ], home_url( '/submit-banner-ad/' ) ),
                'cancel_url'     => add_query_arg( 'adv_banner_payment', 'cancelled', home_url( '/submit-banner-ad/' ) ),
                'metadata'       => [
                    'user_id'   => $user_id,
                    'banner_id' => $post_id,
                    'size'      => $size,
                    'type'      => 'homepage_banner',
                ],
            ] );

            return [ 'url' => $session->url ];
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            return [ 'error' => 'Payment error: ' . $e->getMessage() ];
        }
    }

    // ─── Payment success ─────────────────────────────────────

    public function handle_payment_success() {
        if ( ! isset( $_GET['adv_banner_payment'] ) || $_GET['adv_banner_payment'] !== 'success' ) return;
        if ( empty( $_GET['session_id'] ) || empty( $_GET['banner_id'] ) ) return;

        $banner_id  = intval( $_GET['banner_id'] );
        $session_id = sanitize_text_field( $_GET['session_id'] );

        $autoloader = ADV_PATH . 'vendor/autoload.php';
        if ( ! file_exists( $autoloader ) ) return;
        require_once $autoloader;
        \Stripe\Stripe::setApiKey( ADV_Stripe_Settings::get( 'secret_key' ) );

        try {
            $session = \Stripe\Checkout\Session::retrieve( $session_id );
        } catch ( \Exception $e ) {
            adv_set_flash( 'banner_error', 'Could not verify payment. Please contact us.' );
            wp_safe_redirect( home_url( '/submit-banner-ad/' ) );
            exit;
        }

        $post = get_post( $banner_id );
        if ( ! $post || $post->post_type !== 'adv_banner' ) return;

        // Move to pending (awaiting admin review before publication)
        wp_update_post( [
            'ID'          => $banner_id,
            'post_status' => 'pending',
        ] );

        update_post_meta( $banner_id, '_adv_banner_stripe_session',      $session_id );
        if ( ! empty( $session->subscription ) ) {
            update_post_meta( $banner_id, '_adv_banner_stripe_subscription', $session->subscription );
        }
        if ( ! empty( $session->customer ) ) {
            update_post_meta( $banner_id, '_adv_banner_stripe_customer',     $session->customer );
        }
        update_post_meta( $banner_id, '_adv_banner_paid',        1 );
        update_post_meta( $banner_id, '_adv_banner_paid_at',     current_time( 'mysql' ) );
        update_post_meta( $banner_id, '_adv_banner_auto_renew',  1 );
        update_post_meta( $banner_id, '_adv_banner_expires_at',  date( 'Y-m-d H:i:s', strtotime( '+1 month' ) ) );

        do_action( 'adv_banner_submitted', $banner_id, $post->post_author );

        adv_set_flash( 'banner_success', 'Your homepage banner has been submitted and monthly subscription set up! Our team will review it and publish it shortly.' );
        wp_safe_redirect( home_url( '/submit-banner-ad/' ) );
        exit;
    }

    // ─── AJAX Toggle Auto-Renew ───────────────────────────────

    public function ajax_toggle_autorenew() {
        check_ajax_referer( 'adv_dashboard_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $banner_id = intval( $_POST['banner_id'] ?? 0 );
        $user_id   = get_current_user_id();
        $banner    = get_post( $banner_id );

        if ( ! $banner || $banner->post_type !== 'adv_banner' || (int) $banner->post_author !== $user_id ) {
            wp_send_json_error( [ 'message' => 'Invalid banner advert.' ] );
        }

        $current_auto_renew = (int) get_post_meta( $banner_id, '_adv_banner_auto_renew', true );

        // Prevent turning auto-renew back on once it's been disabled
        if ( $current_auto_renew === 0 ) {
            wp_send_json_error( [ 'message' => 'Auto-renew cannot be re-enabled once disabled. Please contact support to create a new subscription.' ] );
        }

        $new_auto_renew = 0; // Only allow turning OFF

        update_post_meta( $banner_id, '_adv_banner_auto_renew', $new_auto_renew );

        // If turning OFF auto-renew: cancel Stripe subscription AND immediately expire the banner
        if ( $new_auto_renew === 0 ) {
            $stripe_sub_id = get_post_meta( $banner_id, '_adv_banner_stripe_subscription', true );
            if ( $stripe_sub_id ) {
                $autoloader = ADV_PATH . 'vendor/autoload.php';
                if ( file_exists( $autoloader ) ) {
                    require_once $autoloader;
                    try {
                        \Stripe\Stripe::setApiKey( ADV_Stripe_Settings::get( 'secret_key' ) );
                        // Cancel the Stripe subscription immediately
                        \Stripe\Subscription::cancel( $stripe_sub_id );
                    } catch ( \Exception $e ) {
                        // Log silently
                    }
                }
            }

            // Immediately expire the banner in WordPress
            wp_update_post( [
                'ID'          => $banner_id,
                'post_status' => 'adv_banner_expired',
            ] );

            do_action( 'adv_banner_expired', $banner_id, $user_id );

            wp_send_json_success( [
                'auto_renew' => 0,
                'message'    => 'Your banner has been cancelled and is now expired.',
            ] );

        } else {
            // Turning auto-renew back ON is not straightforward with Stripe (needs a new subscription)
            // Just update the meta — admin can handle reactivation manually
            wp_send_json_success( [
                'auto_renew' => 1,
                'message'    => 'Auto-renew enabled. Please contact us to reactivate your subscription.',
            ] );
        }
    }
}