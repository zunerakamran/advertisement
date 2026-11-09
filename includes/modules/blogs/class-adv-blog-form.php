<?php
defined( 'ABSPATH' ) || exit;

class ADV_Blog_Form {

    public function __construct() {
        add_shortcode( 'goss_add_blog', [ $this, 'render' ] );
        add_action( 'init',            [ $this, 'handle_submit' ] );
        add_action( 'init',            [ $this, 'handle_payment_success' ] );
        // Required for media upload button to work in wp_editor on frontend
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_editor_assets' ] );
        // Temporary upload_files only — never edit_posts / publish_posts
        add_filter( 'user_has_cap', [ $this, 'grant_upload_cap' ], 10, 3 );
        add_filter( 'map_meta_cap', [ $this, 'map_upload_cap' ], 10, 4 );
        // Restrict media library to current user's uploads
        add_filter( 'ajax_query_attachments_args', [ $this, 'allow_media_library_access' ], 10, 1 );
    }

    // ─── Enqueue media/editor assets on the submit page ─────

    public function enqueue_editor_assets() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) return;
        if ( ! has_shortcode( $post->post_content, 'goss_add_blog' ) ) return;
        if ( ! is_user_logged_in() ) return;

        // Makes the "Add Media" button work in wp_editor on the frontend
        wp_enqueue_media();
    }

    // ─── Grant upload_files only in plugin media contexts ─────

    public function grant_upload_cap( $allcaps, $cap, $args ) {
        if ( ! in_array( 'upload_files', (array) $cap, true ) ) {
            return $allcaps;
        }

        if ( ! class_exists( 'ADV_Security' ) || ! ADV_Security::is_plugin_upload_context() ) {
            return $allcaps;
        }

        $allcaps['upload_files'] = true;
        return $allcaps;
    }

    // ─── Restrict media library to current user's uploads ─────

    public function allow_media_library_access( $query ) {
        if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
            $query['author'] = get_current_user_id();
        }
        return $query;
    }

    // ─── Map upload_files for plugin media contexts only ─────

    public function map_upload_cap( $caps, $cap, $user_id, $args ) {
        if ( $cap !== 'upload_files' ) {
            return $caps;
        }
        if ( ! $user_id || (int) $user_id !== get_current_user_id() ) {
            return $caps;
        }
        if ( ! class_exists( 'ADV_Security' ) || ! ADV_Security::is_plugin_upload_context() ) {
            return $caps;
        }
        return [];
    }

    // ─── Shortcode render ────────────────────────────────────

    public function render( $atts ) {
        wp_enqueue_style( 'adv-blog-form', ADV_URL . 'public/assets/blog-form.css', [], ADV_VERSION );
        wp_enqueue_script( 'adv-blog-form', ADV_URL . 'public/assets/blog-form.js',  [], ADV_VERSION, true );
        wp_enqueue_style( 'adv-forms-theme', ADV_URL . 'public/assets/adv-forms-theme.css', [ 'adv-blog-form' ], ADV_VERSION );

        if ( ! is_user_logged_in() ) {
            $login_url = add_query_arg( 'redirect_to', urlencode( home_url( '/submit-blog/' ) ), home_url( '/login/' ) );
            return '
            <div class="adv-notice-card">
                <div class="adv-notice-icon-box purple">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div class="adv-notice-body">
                    <h3>Account Login Required</h3>
                    <p>Please log in to submit a featured guest blog post.</p>
                    <div class="adv-notice-actions">
                        <a href="' . esc_url( $login_url ) . '" class="adv-notice-btn primary">Log In Now</a>
                        <a href="' . esc_url( home_url( '/register/' ) ) . '" class="adv-notice-btn outline">Create Account</a>
                    </div>
                </div>
            </div>';
        }

        $price   = ADV_Blog_Settings::get_price();
        $error   = adv_get_flash( 'blog_error' );
        $success = adv_get_flash( 'blog_success' );
        $old     = adv_get_flash( 'blog_old' ) ?: [];

        ob_start();
        include ADV_PATH . 'public/views/blogs/add-blog-form.php';
        return ob_get_clean();
    }

    // ─── Handle form submission ──────────────────────────────

    public function handle_submit() {
        if ( ! isset( $_POST['adv_blog_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['adv_blog_nonce'], 'adv_submit_blog' ) ) return;
        if ( ! is_user_logged_in() ) return;

        $user_id = get_current_user_id();

        $title   = sanitize_text_field( $_POST['blog_title'] ?? '' );
        // wp_kses_post preserves safe HTML from the WYSIWYG (headings, links, images, lists etc.)
        $content = wp_kses_post( $_POST['blog_content'] ?? '' );

        $errors = [];
        if ( empty( $title ) )   $errors[] = 'Blog title is required.';
        if ( empty( $content ) ) $errors[] = 'Blog content is required.';

        if ( $errors ) {
            adv_set_flash( 'blog_error', implode( ' ', $errors ) );
            adv_set_flash( 'blog_old', [ 'blog_title' => $title ] );
            // Note: we don't flash content back — wp_editor will be empty on reload
            // which is acceptable; content loss only happens on validation fail
            wp_safe_redirect( home_url( '/submit-blog/' ) );
            exit;
        }

        // Insert as pending_payment
        $post_id = wp_insert_post( [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'pending_payment',
            'post_type'    => 'featured_blog',
            'post_author'  => $user_id,
        ] );

        if ( is_wp_error( $post_id ) ) {
            adv_set_flash( 'blog_error', 'Could not save your blog post. Please try again.' );
            wp_safe_redirect( home_url( '/submit-blog/' ) );
            exit;
        }

        // Handle featured image upload
        if ( ! empty( $_FILES['blog_image']['tmp_name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $attachment_id = media_handle_upload( 'blog_image', $post_id );
            if ( ! is_wp_error( $attachment_id ) ) {
                set_post_thumbnail( $post_id, $attachment_id );
            }
        }

        $payment_method = sanitize_text_field( $_POST['payment_method'] ?? 'stripe' );
        update_post_meta( $post_id, '_adv_blog_payment_method', $payment_method );

        // ── Bank Transfer path (TEST MODE) ────────────────────────
        if ( $payment_method === 'bank_transfer' ) {
            // Immediately mark as paid and pending review for testing
            update_post_meta( $post_id, '_adv_blog_bank_reference', 'TEST-BANK-TRANSFER' );
            update_post_meta( $post_id, '_adv_blog_paid', 1 );
            update_post_meta( $post_id, '_adv_blog_paid_at', current_time( 'mysql' ) );
            
            wp_update_post( [
                'ID'          => $post_id,
                'post_status' => 'pending',
            ] );

            do_action( 'adv_blog_submitted', $post_id, $user_id );

            adv_set_flash( 'dash_success', 'Test Mode: Your blog post has been submitted successfully using Bank Transfer! It is now pending review by our team.' );
            wp_safe_redirect( get_preview_post_link( $post_id ) );
            exit;
        }

        // Redirect to Stripe
        $checkout = self::create_stripe_checkout( $post_id, $user_id );
        if ( isset( $checkout['error'] ) ) {
            adv_set_flash( 'blog_error', $checkout['error'] );
            wp_safe_redirect( home_url( '/submit-blog/' ) );
            exit;
        }

        wp_redirect( $checkout['url'] );
        exit;
    }

    // ─── Stripe one-time checkout ────────────────────────────

    public static function create_stripe_checkout( $post_id, $user_id ) {
        $autoloader = ADV_PATH . 'vendor/autoload.php';
        if ( ! file_exists( $autoloader ) ) {
            return [ 'error' => 'Payment system not available. Please contact us.' ];
        }
        require_once $autoloader;
        \Stripe\Stripe::setApiKey( ADV_Stripe_Settings::get( 'secret_key' ) );

        $user  = get_userdata( $user_id );
        $price = ADV_Blog_Settings::get_price();

        try {
            $session = \Stripe\Checkout\Session::create( [
                'mode'           => 'payment',
                'line_items'     => [ [
                    'price_data' => [
                        'currency'     => 'gbp',
                        'unit_amount'  => intval( $price * 100 ),
                        'product_data' => [
                            'name'        => 'Featured Guest Blog Submission',
                            'description' => get_the_title( $post_id ),
                        ],
                    ],
                    'quantity' => 1,
                ] ],
                'customer_email' => $user->user_email,
                'success_url'    => add_query_arg( [
                    'adv_blog_payment' => 'success',
                    'session_id'       => '{CHECKOUT_SESSION_ID}',
                    'blog_id'          => $post_id,
                ], home_url( '/submit-blog/' ) ),
                'cancel_url'     => add_query_arg( 'adv_blog_payment', 'cancelled', home_url( '/submit-blog/' ) ),
                'metadata'       => [
                    'user_id' => $user_id,
                    'blog_id' => $post_id,
                    'type'    => 'guest_blog',
                ],
            ] );

            return [ 'url' => $session->url ];
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            return [ 'error' => 'Payment error: ' . $e->getMessage() ];
        }
    }

    // ─── Payment success handler ─────────────────────────────

    public function handle_payment_success() {
        if ( ! isset( $_GET['adv_blog_payment'] ) || $_GET['adv_blog_payment'] !== 'success' ) return;
        if ( empty( $_GET['session_id'] ) || empty( $_GET['blog_id'] ) ) return;

        $blog_id    = intval( $_GET['blog_id'] );
        $session_id = sanitize_text_field( $_GET['session_id'] );

        $autoloader = ADV_PATH . 'vendor/autoload.php';
        if ( ! file_exists( $autoloader ) ) return;
        require_once $autoloader;
        \Stripe\Stripe::setApiKey( ADV_Stripe_Settings::get( 'secret_key' ) );

        try {
            $session = \Stripe\Checkout\Session::retrieve( $session_id );
        } catch ( \Exception $e ) {
            adv_set_flash( 'blog_error', 'Could not verify payment. Please contact us.' );
            wp_safe_redirect( home_url( '/submit-blog/' ) );
            exit;
        }

        if ( $session->payment_status !== 'paid' ) {
            adv_set_flash( 'blog_error', 'Payment was not completed. Please try again.' );
            wp_safe_redirect( home_url( '/submit-blog/' ) );
            exit;
        }

        $blog = get_post( $blog_id );
        if ( ! $blog || $blog->post_type !== 'featured_blog' ) return;

        // Move to pending (awaiting admin approval)
        wp_update_post( [
            'ID'          => $blog_id,
            'post_status' => 'pending',
        ] );

        update_post_meta( $blog_id, '_adv_blog_stripe_session', $session_id );
        update_post_meta( $blog_id, '_adv_blog_paid',    1 );
        update_post_meta( $blog_id, '_adv_blog_paid_at', current_time( 'mysql' ) );

        do_action( 'adv_blog_submitted', $blog_id, $blog->post_author );

        adv_set_flash( 'blog_success', 'Your blog post has been submitted successfully! It is now pending review and will be published by our team shortly.' );
        wp_safe_redirect( get_preview_post_link( $blog_id ) );
        exit;
    }
}
