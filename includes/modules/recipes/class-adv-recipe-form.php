<?php
defined( 'ABSPATH' ) || exit;

class ADV_Recipe_Form {

    public function __construct() {
        add_shortcode( 'goss_add_recipe', [ $this, 'render' ] );
        add_action( 'init', [ $this, 'handle_submit' ] );
        add_action( 'init', [ $this, 'handle_payment_success' ] );
    }

    // ─── Shortcode render ────────────────────────────────────

    public function render( $atts ) {
        wp_enqueue_style(  'adv-recipe-form', ADV_URL . 'public/assets/recipe-form.css', [], ADV_VERSION );
        wp_enqueue_script( 'adv-recipe-form', ADV_URL . 'public/assets/recipe-form.js',  [], ADV_VERSION, true );
        wp_enqueue_style( 'adv-forms-theme', ADV_URL . 'public/assets/adv-forms-theme.css', [ 'adv-recipe-form' ], ADV_VERSION );

        if ( ! is_user_logged_in() ) {
            $login_url = add_query_arg( 'redirect_to', urlencode( home_url( '/submit-recipe/' ) ), home_url( '/login/' ) );
            return '
            <div class="adv-notice-card">
                <div class="adv-notice-icon-box purple">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div class="adv-notice-body">
                    <h3>Account Login Required</h3>
                    <p>Please log in to submit a featured guest recipe.</p>
                    <div class="adv-notice-actions">
                        <a href="' . esc_url( $login_url ) . '" class="adv-notice-btn primary">Log In Now</a>
                        <a href="' . esc_url( home_url( '/register/' ) ) . '" class="adv-notice-btn outline">Create Account</a>
                    </div>
                </div>
            </div>';
        }

        $price   = ADV_Recipe_Settings::get_price();
        $error   = adv_get_flash( 'recipe_error' );
        $success = adv_get_flash( 'recipe_success' );
        $old     = adv_get_flash( 'recipe_old' ) ?: [];

        ob_start();
        include ADV_PATH . 'public/views/recipes/add-recipe-form.php';
        return ob_get_clean();
    }

    // ─── Form submission ─────────────────────────────────────

    public function handle_submit() {
        if ( ! isset( $_POST['adv_recipe_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['adv_recipe_nonce'], 'adv_submit_recipe' ) ) return;

        // Must be logged in — redirect to login if not
        if ( ! is_user_logged_in() ) {
            $login_url = add_query_arg( 'redirect_to', urlencode( home_url( '/submit-recipe/' ) ), home_url( '/login/' ) );
            wp_safe_redirect( $login_url );
            exit;
        }

        $user_id = get_current_user_id();

        // Collect & sanitize fields (mirrors ACF fields on the recipe post type)
        $title        = sanitize_text_field( $_POST['recipe_title'] ?? '' );
        $description = sanitize_textarea_field( $_POST['recipe_description'] ?? '' );
        $servings     = sanitize_text_field( $_POST['recipe_servings'] ?? '' );
        $prep_time    = sanitize_text_field( $_POST['recipe_prep_time'] ?? '' );
        $cook_time    = sanitize_text_field( $_POST['recipe_cook_time'] ?? '' );
        $difficulty   = sanitize_text_field( $_POST['recipe_difficulty'] ?? '' );
        $ingredients  = sanitize_textarea_field( $_POST['recipe_ingredients'] ?? '' );
        $total_time    = sanitize_text_field( $_POST['recipe_total_time'] ?? '' );
        $temperature   = sanitize_text_field( $_POST['recipe_temperature'] ?? '' );
        $instructions  = sanitize_textarea_field( $_POST['recipe_instructions'] ?? '' );
        $notes         = sanitize_textarea_field( $_POST['recipe_notes'] ?? '' );
        $author_bio   = sanitize_textarea_field( $_POST['recipe_author_bio'] ?? '' );
        $payment_method = sanitize_text_field( $_POST['payment_method'] ?? 'stripe' );

        // Validation
        $errors = [];
        if ( empty( $title ) )       $errors[] = 'Recipe title is required.';
        if ( empty( $ingredients ) ) $errors[] = 'Ingredients are required.';
        if ( empty( $instructions ) ) $errors[] = 'Instructions are required.';

        if ( $errors ) {
            adv_set_flash( 'recipe_error', implode( ' ', $errors ) );
            adv_set_flash( 'recipe_old', $_POST );
            wp_safe_redirect( home_url( '/submit-recipe/' ) );
            exit;
        }

        // Create post in pending_payment status
        $post_id = wp_insert_post( [
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => 'pending_payment',
            'post_type'    => 'featured_recipe',
            'post_author'  => $user_id,
        ] );

        if ( is_wp_error( $post_id ) ) {
            adv_set_flash( 'recipe_error', 'Could not save your recipe. Please try again.' );
            adv_set_flash( 'recipe_old', $_POST );
            wp_safe_redirect( home_url( '/submit-recipe/' ) );
            exit;
        }

        // Store ACF-compatible meta
        update_post_meta( $post_id, 'recipe_description', $description );
        update_post_meta( $post_id, 'recipe_servings',      $servings );
        update_post_meta( $post_id, 'recipe_prep_time',     $prep_time );
        update_post_meta( $post_id, 'recipe_cook_time',     $cook_time );
        update_post_meta( $post_id, 'recipe_difficulty',    $difficulty );
        update_post_meta( $post_id, 'recipe_ingredients',   $ingredients );
        update_post_meta( $post_id, 'recipe_total_time',   $total_time );
        update_post_meta( $post_id, 'recipe_temperature',  $temperature );
        update_post_meta( $post_id, 'recipe_instructions', $instructions );
        update_post_meta( $post_id, 'recipe_notes',        $notes );
        update_post_meta( $post_id, 'recipe_author_bio',    $author_bio );
        update_post_meta( $post_id, '_adv_recipe_payment_method', $payment_method );

        // Handle featured image upload
        if ( ! empty( $_FILES['recipe_image']['tmp_name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $attachment_id = media_handle_upload( 'recipe_image', $post_id );
            if ( ! is_wp_error( $attachment_id ) ) {
                set_post_thumbnail( $post_id, $attachment_id );
            }
        }

        // ── Bank Transfer path (TEST MODE) ────────────────────────
        if ( $payment_method === 'bank_transfer' ) {
            // Immediately mark as paid and pending review for testing
            update_post_meta( $post_id, '_adv_recipe_bank_reference', 'TEST-BANK-TRANSFER' );
            update_post_meta( $post_id, '_adv_recipe_paid', 1 );
            update_post_meta( $post_id, '_adv_recipe_paid_at', current_time( 'mysql' ) );
            
            wp_update_post( [
                'ID'          => $post_id,
                'post_status' => 'pending',
            ] );

            // Fire email hooks
            do_action( 'adv_recipe_submitted', $post_id, $user_id );

            adv_set_flash( 'dash_success', 'Test Mode: Your recipe has been submitted successfully using Bank Transfer! It is now pending review by our team.' );
            wp_safe_redirect( get_preview_post_link( $post_id ) );
            exit;
        }

        // ── Stripe path ─────────────────────────────────────
        $checkout_url = self::create_stripe_checkout( $post_id, $user_id );
        if ( isset( $checkout_url['error'] ) ) {
            adv_set_flash( 'recipe_error', $checkout_url['error'] );
            wp_safe_redirect( home_url( '/submit-recipe/' ) );
            exit;
        }

        wp_redirect( $checkout_url['url'] );
        exit;
    }

    // ─── Stripe one-time checkout ────────────────────────────

    public static function create_stripe_checkout( $recipe_id, $user_id ) {
        $autoloader = ADV_PATH . 'vendor/autoload.php';
        if ( ! file_exists( $autoloader ) ) {
            return [ 'error' => 'Payment system not available. Please contact us.' ];
        }
        require_once $autoloader;
        \Stripe\Stripe::setApiKey( ADV_Stripe_Settings::get( 'secret_key' ) );

        $user  = get_userdata( $user_id );
        $price = ADV_Recipe_Settings::get_price();

        try {
            $session = \Stripe\Checkout\Session::create( [
                'mode'           => 'payment',
                'line_items'     => [ [
                    'price_data' => [
                        'currency'     => 'gbp',
                        'unit_amount'  => intval( $price * 100 ),
                        'product_data' => [
                            'name'        => 'Featured Guest Recipe Submission',
                            'description' => get_the_title( $recipe_id ),
                        ],
                    ],
                    'quantity' => 1,
                ] ],
                'customer_email' => $user->user_email,
                'success_url'    => add_query_arg( [
                    'adv_recipe_payment' => 'success',
                    'session_id'         => '{CHECKOUT_SESSION_ID}',
                    'recipe_id'          => $recipe_id,
                ], home_url( '/submit-recipe/' ) ),
                'cancel_url'     => add_query_arg( 'adv_recipe_payment', 'cancelled', home_url( '/submit-recipe/' ) ),
                'metadata'       => [
                    'user_id'   => $user_id,
                    'recipe_id' => $recipe_id,
                    'type'      => 'guest_recipe',
                ],
            ] );

            return [ 'url' => $session->url ];
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            return [ 'error' => 'Payment error: ' . $e->getMessage() ];
        }
    }

    // ─── Payment success redirect handler ───────────────────

    public function handle_payment_success() {
        if ( ! isset( $_GET['adv_recipe_payment'] ) || $_GET['adv_recipe_payment'] !== 'success' ) return;
        if ( empty( $_GET['session_id'] ) || empty( $_GET['recipe_id'] ) ) return;

        $recipe_id  = intval( $_GET['recipe_id'] );
        $session_id = sanitize_text_field( $_GET['session_id'] );

        // Verify payment with Stripe
        $autoloader = ADV_PATH . 'vendor/autoload.php';
        if ( ! file_exists( $autoloader ) ) return;
        require_once $autoloader;
        \Stripe\Stripe::setApiKey( ADV_Stripe_Settings::get( 'secret_key' ) );

        try {
            $session = \Stripe\Checkout\Session::retrieve( $session_id );
        } catch ( \Exception $e ) {
            adv_set_flash( 'recipe_error', 'Could not verify payment. Please contact us.' );
            wp_safe_redirect( home_url( '/submit-recipe/' ) );
            exit;
        }

        if ( $session->payment_status !== 'paid' ) {
            adv_set_flash( 'recipe_error', 'Payment was not completed. Please try again.' );
            wp_safe_redirect( home_url( '/submit-recipe/' ) );
            exit;
        }

        $recipe = get_post( $recipe_id );
        if ( ! $recipe || $recipe->post_type !== 'featured_recipe' ) return;

        // Move to pending (awaiting admin approval)
        wp_update_post( [
            'ID'          => $recipe_id,
            'post_status' => 'pending',
        ] );

        // Store payment reference
        update_post_meta( $recipe_id, '_adv_recipe_stripe_session', $session_id );
        update_post_meta( $recipe_id, '_adv_recipe_paid', 1 );
        update_post_meta( $recipe_id, '_adv_recipe_paid_at', current_time( 'mysql' ) );

        // Fire email hooks
        do_action( 'adv_recipe_submitted', $recipe_id, $recipe->post_author );

        adv_set_flash( 'dash_success', 'Your recipe has been submitted successfully! It is now pending review by our team and will be published shortly.' );
        wp_safe_redirect( get_preview_post_link( $recipe_id ) );
        exit;
    }
}