<?php
defined( 'ABSPATH' ) || exit;

class ADV_Listing_Form {

    public static $price_ranges = [
        'cheap'     => 'Cheap (£)',
        'economy'   => 'Economy (££)',
        'moderate'  => 'Moderate (£££)',
        'ultrahigh' => 'Ultra High (££££)',
    ];

    public function __construct() {
        add_shortcode( 'goss_add_listing', [ $this, 'render' ] );
        add_action( 'init', [ $this, 'handle_submit' ] );
        add_action( 'adv_async_listing_notification', [ $this, 'handle_async_notification' ], 10, 3 );
    }

    // ─── Render Form ─────────────────────────────────────────

    public function render( $atts ) {
        wp_enqueue_style( 'adv-listing-form', ADV_URL . 'public/assets/listing-form.css', [], ADV_VERSION );
        wp_enqueue_style( 'adv-forms-theme', ADV_URL . 'public/assets/adv-forms-theme.css', [ 'adv-listing-form' ], ADV_VERSION );

        if ( ! is_user_logged_in() ) {
            $login_url = add_query_arg( 'redirect_to', urlencode( home_url( '/add-listing/' ) ), home_url( '/login/' ) );
            return '
            <div class="adv-notice-card">
                <div class="adv-notice-icon-box purple">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div class="adv-notice-body">
                    <h3>Account Login Required</h3>
                    <p>Please log in to your account to create and manage your business directory listing.</p>
                    <div class="adv-notice-actions">
                        <a href="' . esc_url( $login_url ) . '" class="adv-notice-btn primary">Log In Now</a>
                        <a href="' . esc_url( home_url( '/register/' ) ) . '" class="adv-notice-btn outline">Create Account</a>
                    </div>
                </div>
            </div>';
        }

        $user_id = get_current_user_id();
        $listing = null;

        // ── Editing an existing listing? ──
        $edit_id = isset( $_GET['edit'] ) ? intval( $_GET['edit'] ) : 0;
        if ( $edit_id ) {
            $candidate = get_post( $edit_id );
            if ( $candidate
                && $candidate->post_type === 'adv_listing'
                && (int) $candidate->post_author === $user_id
                && $candidate->post_status !== 'trash'
            ) {
                $listing = $candidate;
            }
        }

        // ── Adding a new listing: enforce active plan + listing quota ──
        if ( ! $listing ) {
            $quota = self::get_user_listing_quota( $user_id );
            $used  = self::get_user_active_listings_count( $user_id );


            if ( $quota <= 0 ) {
                return '
                <div class="adv-notice-card warning">
                    <div class="adv-notice-icon-box yellow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                    </div>
                    <div class="adv-notice-body">
                        <h3>Membership Plan Required</h3>
                        <p>You need an active subscription plan before publishing your business listing.</p>
                        <div class="adv-notice-actions">
                            <a href="' . esc_url( home_url( '/pricing/' ) ) . '" class="adv-notice-btn primary">View Pricing Plans</a>
                        </div>
                    </div>
                </div>';
            }

            if ( $used >= $quota ) {
                return '
                <div class="adv-notice-card info">
                    <div class="adv-notice-icon-box blue">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>
                    </div>
                    <div class="adv-notice-body">
                        <h3>Listing Quota Reached</h3>
                        <p>You have reached your listing quota (' . intval( $used ) . ' of ' . intval( $quota ) . ' listings used). You can manage or edit your existing listings from your dashboard, or purchase additional listings.</p>
                        <div class="adv-notice-actions">
                            <a href="' . esc_url( home_url( '/dashboard/' ) ) . '" class="adv-notice-btn primary">Go to Dashboard</a>
                            <a href="' . esc_url( home_url( '/pricing/' ) ) . '" class="adv-notice-btn outline">Get Additional Listings</a>
                        </div>
                    </div>
                </div>';
            }
        }

        $error   = adv_get_flash( 'listing_error' );
        $success = adv_get_flash( 'listing_success' );
        $flashed_old = adv_get_flash( 'listing_old' );

        if ( $flashed_old ) {
            $old = $flashed_old;
        } elseif ( $listing ) {
            $old = self::listing_to_fields( $listing );
        } else {
            $old = [];
        }

        $categories = ADV_Categories::get_top_level();

        ob_start();
        include ADV_PATH . 'public/views/listings/add-listing-form.php';
        return ob_get_clean();
    }

    // ─── Prefill helper (edit mode) ───────────────────────────

    private static function listing_to_fields( $listing ) {
        $terms   = wp_get_post_terms( $listing->ID, 'adv_category', [ 'fields' => 'ids' ] );

        return [
            'listing_name'         => $listing->post_title,
            'listing_desc'         => $listing->post_content,
            'listing_features'     => get_post_meta( $listing->ID, 'adv_features', true ),
            'listing_phone'        => get_post_meta( $listing->ID, 'adv_phone', true ),
            'listing_email'        => get_post_meta( $listing->ID, 'adv_email', true ),
            'listing_whatsapp'     => get_post_meta( $listing->ID, 'adv_whatsapp', true ),
            'listing_facebook'     => get_post_meta( $listing->ID, 'adv_facebook', true ),
            'listing_instagram'    => get_post_meta( $listing->ID, 'adv_instagram', true ),
            'listing_linkedin'     => get_post_meta( $listing->ID, 'adv_linkedin', true ),
            'listing_category'     => $terms[0] ?? '',
            'listing_pricing_type' => get_post_meta( $listing->ID, 'adv_pricing_type', true ) ?: 'custom',
            'listing_price_custom' => get_post_meta( $listing->ID, 'adv_price_custom', true ),
            'listing_price_range'  => get_post_meta( $listing->ID, 'adv_price_range', true ),
        ];
    }

    // ─── Handle Submit ────────────────────────────────────────

    public function handle_submit() {
        if ( ! isset( $_POST['adv_listing_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['adv_listing_nonce'], 'adv_add_listing' ) ) return;
        if ( ! is_user_logged_in() ) return;

        $user_id    = get_current_user_id();
        $listing_id = intval( $_POST['listing_id'] ?? 0 );
        $is_edit    = false;

        if ( $listing_id ) {
            $existing_post = get_post( $listing_id );
            if ( ! $existing_post
                || $existing_post->post_type !== 'adv_listing'
                || (int) $existing_post->post_author !== $user_id
            ) {
                // Not their listing — silently bail
                return;
            }
            $is_edit = true;
        }

        // Collect fields
        $title        = sanitize_text_field( $_POST['listing_name']        ?? '' );
        $description  = sanitize_textarea_field( $_POST['listing_desc']    ?? '' );
        $features     = sanitize_textarea_field( $_POST['listing_features']?? '' );
        $phone        = sanitize_text_field( $_POST['listing_phone']       ?? '' );
        $email        = sanitize_email( $_POST['listing_email']            ?? '' );
        $whatsapp     = sanitize_text_field( $_POST['listing_whatsapp']    ?? '' );
        $facebook     = esc_url_raw( $_POST['listing_facebook']            ?? '' );
        $instagram    = esc_url_raw( $_POST['listing_instagram']           ?? '' );
        $linkedin     = esc_url_raw( $_POST['listing_linkedin']            ?? '' );
        $category_id  = intval( $_POST['listing_category']                 ?? 0 );
        $pricing_type = sanitize_text_field( $_POST['listing_pricing_type']?? 'custom' );
        $price_custom = sanitize_text_field( $_POST['listing_price_custom']?? '' );
        $price_range  = sanitize_text_field( $_POST['listing_price_range'] ?? '' );

        // Validation
        if ( empty( $title ) ) {
            adv_set_flash( 'listing_error', 'Business name is required.' );
            adv_set_flash( 'listing_old', $_POST );
            return;
        }

        if ( empty( $description ) ) {
            adv_set_flash( 'listing_error', 'Description is required.' );
            adv_set_flash( 'listing_old', $_POST );
            return;
        }

        if ( empty( $phone ) && empty( $email ) ) {
            adv_set_flash( 'listing_error', 'Please provide at least a phone number or email.' );
            adv_set_flash( 'listing_old', $_POST );
            return;
        }

        if ( ! $is_edit ) {
            $quota = self::get_user_listing_quota( $user_id );
            $used  = self::get_user_active_listings_count( $user_id );
            if ( $used >= $quota ) {
                adv_set_flash( 'listing_error', 'You have reached your maximum active listing quota (' . $quota . '). Please purchase additional listings or deactivate an existing listing.' );
                adv_set_flash( 'listing_old', $_POST );
                return;
            }
        }


        // Create or update the listing post
        if ( $is_edit ) {
            wp_update_post([
                'ID'           => $listing_id,
                'post_title'   => $title,
                'post_content' => $description,
                'post_status'  => 'pending', // re-review after any edit
            ]);
            $post_id = $listing_id;
        } else {
            $post_id = wp_insert_post([
                'post_title'   => $title,
                'post_content' => $description,
                'post_status'  => 'pending',
                'post_type'    => 'adv_listing',
                'post_author'  => $user_id,
            ]);

            if ( is_wp_error( $post_id ) ) {
                adv_set_flash( 'listing_error', 'Something went wrong. Please try again.' );
                return;
            }
        }

        // Assign category
        if ( $category_id ) {
            wp_set_post_terms( $post_id, [ $category_id ], 'adv_category' );
        }

        // Save all meta fields in a single batch for better performance
        $meta_fields = [
            'adv_features'     => $features,
            'adv_phone'        => $phone,
            'adv_email'        => $email,
            'adv_whatsapp'     => $whatsapp,
            'adv_facebook'     => $facebook,
            'adv_instagram'    => $instagram,
            'adv_linkedin'     => $linkedin,
            'adv_pricing_type' => $pricing_type,
            'adv_price_custom' => $price_custom,
            'adv_price_range'  => $price_range,
        ];

        foreach ( $meta_fields as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }

        // Handle logo upload (only replaces if a new file was chosen)
        if ( ! empty( $_FILES['listing_logo']['name'] ) ) {
            $logo_id = self::handle_upload( $_FILES['listing_logo'], $post_id );
            if ( $logo_id ) {
                set_post_thumbnail( $post_id, $logo_id );
            }
        }

        if ( ! $is_edit ) {
            // Link booking to this listing — only relevant on first submit
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'adv_bookings',
                [ 'listing_id' => $post_id ],
                [ 'user_id' => $user_id, 'status' => 'active' ]
            );
        }

        // Fire hook for email notification (schedule as async to avoid blocking)
        wp_schedule_single_event( time(), 'adv_async_listing_notification', [
            'action' => $is_edit ? 'adv_listing_updated' : 'adv_listing_submitted',
            'post_id' => $post_id,
            'user_id' => $user_id
        ]);

        // Redirect to preview page
        $preview_url = adv_get_listing_preview_url( $post_id );

        wp_safe_redirect( $preview_url );
        exit;
    }

    // ─── Async Notification Handler ───────────────────────────

    public function handle_async_notification( $action, $post_id, $user_id ) {
        do_action( $action, $post_id, $user_id );
    }

    // ─── Logo Upload Helper ───────────────────────────────────

    private static function handle_upload( $file, $post_id ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $allowed = [ 'image/jpeg', 'image/png', 'image/webp' ];
        if ( ! in_array( $file['type'], $allowed ) ) return false;

        $upload = media_handle_upload( 'listing_logo', $post_id );
        if ( is_wp_error( $upload ) ) return false;

        return $upload;
    }

    // ─── Quota Helpers ────────────────────────────────────────

    public static function get_user_listing_quota( $user_id ) {
        global $wpdb;
        $quota = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(IFNULL(NULLIF(quantity, 0), 1)) FROM {$wpdb->prefix}adv_bookings WHERE user_id = %d AND status = 'active'",
            $user_id
        ) );
        return max( 0, intval( $quota ) );
    }

    public static function get_user_active_listings_count( $user_id ) {
        global $wpdb;
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'adv_listing' 
             AND post_author = %d 
             AND post_status IN ('publish', 'pending')",
            $user_id
        ) );
        return max( 0, intval( $count ) );
    }

    public static function get_user_listings_count( $user_id ) {
        $posts = get_posts([
            'post_type'   => 'adv_listing',
            'author'      => $user_id,
            'post_status' => [ 'publish', 'pending', 'draft', 'suspended' ],
            'numberposts' => -1,
            'fields'      => 'ids',
        ]);
        return count( $posts );
    }
}
