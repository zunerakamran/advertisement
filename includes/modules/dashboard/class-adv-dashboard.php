<?php
defined( 'ABSPATH' ) || exit;

class ADV_Dashboard {

    public function __construct() {
        add_shortcode( 'goss_dashboard', [ $this, 'render' ] );
        add_action( 'init', [ $this, 'handle_delete_listing' ] );
        add_action( 'init', [ $this, 'handle_delete_recipe' ] );
        add_action( 'init', [ $this, 'handle_delete_blog' ] );
        add_action( 'init', [ $this, 'handle_update_profile' ] );
        add_action( 'init', [ $this, 'handle_unsubscribe' ] );
        add_action( 'init', [ $this, 'handle_toggle_listing' ] );
    }

    // ─── Render ──────────────────────────────────────────────

    public function render( $atts ) {
        if ( ! is_user_logged_in() ) {
            $redirect = urlencode( home_url( '/dashboard/' ) );
            return '<p style="text-align:center;padding:40px;">Please <a href="' . esc_url( home_url( '/login/?redirect_to=' . $redirect ) ) . '">login</a> to view your dashboard.</p>';
        }

        $user_id      = get_current_user_id();
        $user         = wp_get_current_user();

        $all_listings  = self::get_all_user_listings( $user_id );
        $listing       = $all_listings ? $all_listings[0] : null;
        $booking       = self::get_active_booking( $user_id );
        $plan          = $booking ? ADV_Plans::get( $booking->plan_id ) : null;
        $user_recipes  = self::get_user_recipes( $user_id );
        $user_blogs    = self::get_user_blogs( $user_id );
        $user_magazines = self::get_user_magazines( $user_id );
        $user_banners  = self::get_user_banners( $user_id );

        $submitted = isset( $_GET['submitted'] );
        $updated   = isset( $_GET['updated'] );
        $deleted   = isset( $_GET['deleted'] );

        $stats = [
            'total'     => count( $all_listings ),
            'published' => 0,
            'pending'   => 0,
            'suspended' => 0,
        ];

        foreach ( $all_listings as $item ) {
            if ( $item->post_status === 'publish' )   $stats['published']++;
            if ( $item->post_status === 'pending' )   $stats['pending']++;
            if ( $item->post_status === 'suspended' ) $stats['suspended']++;
        }

        wp_enqueue_style( 'adv-dashboard', ADV_URL . 'public/assets/dashboard.css', [], ADV_VERSION );
        wp_enqueue_script( 'adv-dashboard', ADV_URL . 'public/assets/dashboard.js', [], ADV_VERSION, true );
        wp_localize_script( 'adv-dashboard', 'advPublic', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ] );

        ob_start();
        include ADV_PATH . 'public/views/dashboard/dashboard.php';
        return ob_get_clean();
    }

    // ─── Data helpers ────────────────────────────────────────

    public static function get_all_user_listings( $user_id ) {
        return get_posts([
            'post_type'   => 'adv_listing',
            'author'      => $user_id,
            'post_status' => [ 'publish', 'pending', 'draft', 'suspended' ],
            'numberposts' => -1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);
    }

    public static function get_user_listing( $user_id ) {
        $listings = self::get_all_user_listings( $user_id );
        return $listings ? $listings[0] : null;
    }

    public static function get_user_recipes( $user_id ) {
        return get_posts([
            'post_type'   => 'featured_recipe',
            'author'      => $user_id,
            'post_status' => [ 'publish', 'pending', 'pending_payment', 'draft', 'suspended' ],
            'numberposts' => -1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);
    }

    public static function get_user_blogs( $user_id ) {
        return get_posts([
            'post_type'   => 'featured_blog',
            'author'      => $user_id,
            'post_status' => [ 'publish', 'pending', 'pending_payment', 'draft', 'suspended' ],
            'numberposts' => -1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);
    }

    public static function get_user_magazines( $user_id ) {
        return get_posts([
            'post_type'   => 'adv_magazine',
            'author'      => $user_id,
            'post_status' => [ 'publish', 'pending', 'adv_pending_payment', 'adv_approved', 'draft' ],
            'numberposts' => -1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);
    }

    public static function get_user_banners( $user_id ) {
        return get_posts([
            'post_type'   => 'adv_banner',
            'author'      => $user_id,
            'post_status' => [ 'publish', 'pending', 'draft', 'adv_banner_pending_payment', 'adv_banner_expired' ],
            'numberposts' => -1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);
    }

    public static function get_active_booking( $user_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}adv_bookings WHERE user_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1",
            $user_id
        ) );
    }

    public static function status_label( $status ) {
        $labels = [
            'publish'   => [ 'label' => 'Live',           'class' => 'adv-badge-success' ],
            'pending'   => [ 'label' => 'Pending Review',  'class' => 'adv-badge-warning' ],
            'draft'     => [ 'label' => 'Draft',           'class' => 'adv-badge-muted'   ],
            'suspended' => [ 'label' => 'Suspended',       'class' => 'adv-badge-danger'  ],
        ];
        return $labels[ $status ] ?? [ 'label' => ucfirst( $status ), 'class' => 'adv-badge-muted' ];
    }

    public static function recipe_status_label( $post ) {
        $post_status     = $post->post_status;
        $payment_method  = get_post_meta( $post->ID, '_adv_recipe_payment_method', true );
        $paid            = get_post_meta( $post->ID, '_adv_recipe_paid', true );

        if ( $post_status === 'publish' ) {
            return [ 'label' => 'Published', 'class' => 'adv-badge-success' ];
        }
        if ( $post_status === 'pending' ) {
            return [ 'label' => 'Under Review', 'class' => 'adv-badge-warning' ];
        }
        if ( $post_status === 'pending_payment' ) {
            /* if ( $payment_method === 'bank_transfer' ) {
                return [ 'label' => 'Pending Bank Transfer', 'class' => 'adv-badge-blue' ];
            } */
            return [ 'label' => 'Payment Pending', 'class' => 'adv-badge-muted' ];
        }
        return [ 'label' => ucfirst( $post_status ), 'class' => 'adv-badge-muted' ];
    }

    public static function recipe_payment_label( $post ) {
        $payment_method = get_post_meta( $post->ID, '_adv_recipe_payment_method', true );
        $paid           = (int) get_post_meta( $post->ID, '_adv_recipe_paid', true );

        if ( $paid ) {
            return [ 'label' => '✅ Paid', 'class' => 'adv-badge-success' ];
        }
        /* if ( $payment_method === 'bank_transfer' ) {
            return [ 'label' => '🏦 Bank Transfer', 'class' => 'adv-badge-blue' ];
        } */
        return [ 'label' => '⏳ Unpaid', 'class' => 'adv-badge-muted' ];
    }

    public static function blog_status_label( $post ) {
        $post_status     = $post->post_status;
        $payment_method  = get_post_meta( $post->ID, '_adv_blog_payment_method', true );
        $paid            = get_post_meta( $post->ID, '_adv_blog_paid', true );

        if ( $post_status === 'publish' ) {
            return [ 'label' => 'Published', 'class' => 'adv-badge-success' ];
        }
        if ( $post_status === 'pending' ) {
            return [ 'label' => 'Under Review', 'class' => 'adv-badge-warning' ];
        }
        if ( $post_status === 'pending_payment' ) {
            /* if ( $payment_method === 'bank_transfer' ) {
                return [ 'label' => 'Pending Bank Transfer', 'class' => 'adv-badge-blue' ];
            } */
            return [ 'label' => 'Payment Pending', 'class' => 'adv-badge-muted' ];
        }
        return [ 'label' => ucfirst( $post_status ), 'class' => 'adv-badge-muted' ];
    }

    public static function blog_payment_label( $post ) {
        $payment_method = get_post_meta( $post->ID, '_adv_blog_payment_method', true );
        $paid           = (int) get_post_meta( $post->ID, '_adv_blog_paid', true );

        if ( $paid ) {
            return [ 'label' => '✅ Paid', 'class' => 'adv-badge-success' ];
        }
        /* if ( $payment_method === 'bank_transfer' ) {
            return [ 'label' => '🏦 Bank Transfer', 'class' => 'adv-badge-blue' ];
        } */
        return [ 'label' => '⏳ Unpaid', 'class' => 'adv-badge-muted' ];
    }

    public static function magazine_status_label( $post ) {
        $post_status    = $post->post_status;
        $payment_method = get_post_meta( $post->ID, '_adv_mag_payment_method', true );

        if ( $post_status === 'publish' || $post_status === 'adv_approved' ) {
            return [ 'label' => 'Approved', 'class' => 'adv-badge-success' ];
        }
        if ( $post_status === 'pending' ) {
            return [ 'label' => 'Under Review', 'class' => 'adv-badge-warning' ];
        }
        if ( $post_status === 'adv_pending_payment' ) {
            /* if ( $payment_method === 'bank_transfer' ) {
                return [ 'label' => 'Pending Bank Transfer', 'class' => 'adv-badge-blue' ];
            } */
            return [ 'label' => 'Payment Pending', 'class' => 'adv-badge-muted' ];
        }
        return [ 'label' => ucfirst( str_replace( 'adv_', '', $post_status ) ), 'class' => 'adv-badge-muted' ];
    }

    public static function banner_status_label( $post ) {
        $post_status = $post->post_status;
        $payment_method = get_post_meta( $post->ID, '_adv_banner_payment_method', true );

        if ( $post_status === 'publish' ) {
            return [ 'label' => 'Live', 'class' => 'adv-badge-success' ];
        }
        if ( $post_status === 'pending' ) {
            return [ 'label' => 'Under Review', 'class' => 'adv-badge-warning' ];
        }
        if ( $post_status === 'adv_banner_pending_payment' ) {
            /* if ( $payment_method === 'bank_transfer' ) {
                return [ 'label' => 'Pending Bank Transfer', 'class' => 'adv-badge-blue' ];
            } */
            return [ 'label' => 'Payment Pending', 'class' => 'adv-badge-muted' ];
        }
        if ( $post_status === 'adv_banner_expired' ) {
            return [ 'label' => 'Expired', 'class' => 'adv-badge-danger' ];
        }
        return [ 'label' => ucfirst( str_replace( 'adv_banner_', '', $post_status ) ), 'class' => 'adv-badge-muted' ];
    }

    public static function banner_payment_label( $post ) {
        $payment_method = get_post_meta( $post->ID, '_adv_banner_payment_method', true );
        $paid           = (int) get_post_meta( $post->ID, '_adv_banner_paid', true );

        if ( $paid ) {
            return [ 'label' => '✅ Paid', 'class' => 'adv-badge-success' ];
        }
        /* if ( $payment_method === 'bank_transfer' ) {
            return [ 'label' => '🏦 Bank Transfer', 'class' => 'adv-badge-blue' ];
        } */
        return [ 'label' => '⏳ Unpaid', 'class' => 'adv-badge-muted' ];
    }

    public static function magazine_payment_label( $post ) {
        $payment_method = get_post_meta( $post->ID, '_adv_mag_payment_method', true );
        $paid           = (int) get_post_meta( $post->ID, '_adv_mag_paid', true );

        if ( $paid ) {
            return [ 'label' => '✅ Paid', 'class' => 'adv-badge-success' ];
        }
        /* if ( $payment_method === 'bank_transfer' ) {
            return [ 'label' => '🏦 Bank Transfer', 'class' => 'adv-badge-blue' ];
        } */
        return [ 'label' => '⏳ Unpaid', 'class' => 'adv-badge-muted' ];
    }

    // ─── Toggle Listing Active / Suspended Status ──────────────

    public function handle_toggle_listing() {
        if ( ! isset( $_GET['adv_toggle_listing'] ) ) return;
        if ( ! is_user_logged_in() ) return;
        if ( ! wp_verify_nonce( $_GET['adv_toggle_nonce'] ?? '', 'adv_toggle_listing' ) ) return;

        $user_id   = get_current_user_id();
        $target_id = intval( $_GET['adv_toggle_listing'] );
        if ( ! $target_id ) return;

        $listing = get_post( $target_id );

        if ( $listing && $listing->post_type === 'adv_listing' && (int) $listing->post_author === $user_id ) {
            $quota = ADV_Listing_Form::get_user_listing_quota( $user_id );

            if ( $listing->post_status === 'suspended' ) {
                $active_used = ADV_Listing_Form::get_user_active_listings_count( $user_id );
                if ( $active_used >= $quota ) {
                    adv_set_flash( 'dash_error', sprintf( 'Cannot activate listing. You have reached your active listing quota (%d of %d allowed). Please suspend another listing or upgrade your plan.', $active_used, $quota ) );
                } else {
                    // Restore to original pre-suspension status (pending or publish)
                    $restore_status = get_post_meta( $listing->ID, '_adv_pre_suspension_status', true );
                    if ( ! in_array( $restore_status, [ 'publish', 'pending' ], true ) ) {
                        $restore_status = 'publish';
                    }
                    delete_post_meta( $listing->ID, '_adv_pre_suspension_status' );
                    wp_update_post([
                        'ID'          => $listing->ID,
                        'post_status' => $restore_status,
                    ]);
                    $label = $restore_status === 'pending' ? 'Pending Review' : 'Live';
                    adv_set_flash( 'dash_success', 'Listing reactivated — status restored to ' . $label . '.' );
                }
            } elseif ( in_array( $listing->post_status, [ 'publish', 'pending' ], true ) ) {
                // Save current status so we can restore it on unsuspend
                update_post_meta( $listing->ID, '_adv_pre_suspension_status', $listing->post_status );
                wp_update_post([
                    'ID'          => $listing->ID,
                    'post_status' => 'suspended',
                ]);
                adv_set_flash( 'dash_info', 'Listing has been suspended.' );
            }
        }

        wp_safe_redirect( home_url( '/dashboard/' ) );
        exit;
    }

    // ─── Delete listing ──────────────────────────────────────

    public function handle_delete_listing() {
        if ( ! isset( $_GET['adv_delete_listing'] ) ) return;
        if ( ! is_user_logged_in() ) return;
        if ( ! wp_verify_nonce( $_GET['adv_delete_nonce'] ?? $_GET['adv_delete_listing'], 'adv_delete_listing' ) ) return;

        $user_id   = get_current_user_id();
        $target_id = intval( $_GET['adv_delete_listing_id'] ?? $_GET['adv_delete_listing'] ?? 0 );
        if ( ! $target_id ) return;

        $listing = get_post( $target_id );

        if ( $listing && $listing->post_type === 'adv_listing' && (int) $listing->post_author === $user_id ) {
            wp_trash_post( $listing->ID );

            // Fire hook for email notification
            do_action( 'adv_listing_deleted', $listing->ID, $user_id );
        }

        wp_safe_redirect( add_query_arg( 'deleted', '1', home_url( '/dashboard/' ) ) );
        exit;
    }

    // ─── Delete recipe ───────────────────────────────────────

    public function handle_delete_recipe() {
        if ( ! isset( $_GET['adv_delete_recipe'] ) ) return;
        if ( ! is_user_logged_in() ) return;
        if ( ! wp_verify_nonce( $_GET['adv_delete_recipe'], 'adv_delete_recipe' ) ) return;

        $user_id   = get_current_user_id();
        $target_id = intval( $_GET['adv_delete_recipe_id'] ?? 0 );
        if ( ! $target_id ) return;

        $recipe = get_post( $target_id );

        if ( $recipe && $recipe->post_type === 'featured_recipe' && (int) $recipe->post_author === $user_id ) {
            wp_delete_post( $recipe->ID, true );
            adv_set_flash( 'dash_success', 'Recipe deleted successfully.' );
        }

        wp_safe_redirect( home_url( '/dashboard/' ) );
        exit;
    }

    // ─── Delete blog ─────────────────────────────────────────

    public function handle_delete_blog() {
        if ( ! isset( $_GET['adv_delete_blog'] ) ) return;
        if ( ! is_user_logged_in() ) return;
        if ( ! wp_verify_nonce( $_GET['adv_delete_blog'], 'adv_delete_blog' ) ) return;

        $user_id   = get_current_user_id();
        $target_id = intval( $_GET['adv_delete_blog_id'] ?? 0 );
        if ( ! $target_id ) return;

        $blog = get_post( $target_id );

        if ( $blog && $blog->post_type === 'featured_blog' && (int) $blog->post_author === $user_id ) {
            wp_delete_post( $blog->ID, true );
            adv_set_flash( 'dash_success', 'Blog post deleted successfully.' );
        }

        wp_safe_redirect( home_url( '/dashboard/' ) );
        exit;
    }

    // ─── Profile Update Handler ──────────────────────────────

    public function handle_update_profile() {
        if ( ! isset( $_POST['adv_action'] ) || $_POST['adv_action'] !== 'update_profile' ) return;
        if ( ! is_user_logged_in() ) return;
        if ( ! wp_verify_nonce( $_POST['adv_profile_nonce'] ?? '', 'adv_update_profile' ) ) return;

        $user_id      = get_current_user_id();
        $first_name   = sanitize_text_field( $_POST['first_name']   ?? '' );
        $last_name    = sanitize_text_field( $_POST['last_name']    ?? '' );
        $display_name = sanitize_text_field( $_POST['display_name'] ?? '' );
        $email        = sanitize_email( $_POST['user_email']        ?? '' );

        $data = [
            'ID'           => $user_id,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $display_name,
        ];

        if ( $email && is_email( $email ) ) {
            $data['user_email'] = $email;
        }

        wp_update_user( $data );
        adv_set_flash( 'profile_saved', 'Profile details updated successfully.' );
        wp_safe_redirect( home_url( '/dashboard/' ) );
        exit;
    }

    // ─── Unsubscribe ─────────────────────────────────────────

    public function handle_unsubscribe() {
        if ( ! isset( $_POST['adv_action'] ) || $_POST['adv_action'] !== 'unsubscribe' ) return;
        if ( ! is_user_logged_in() ) return;
        if ( ! wp_verify_nonce( $_POST['adv_unsubscribe_nonce'] ?? '', 'adv_unsubscribe' ) ) return;

        global $wpdb;
        $user_id = get_current_user_id();

        // Get active booking
        $booking = self::get_active_booking( $user_id );
        if ( ! $booking ) {
            wp_safe_redirect( add_query_arg( 'unsub_error', '1', home_url( '/dashboard/' ) ) );
            exit;
        }

        $error = null;

        // Cancel in Stripe only if a real Stripe subscription ID exists (starts with 'sub_')
        if ( ! empty( $booking->stripe_subscription_id ) && str_starts_with( $booking->stripe_subscription_id, 'sub_' ) ) {
            $result = ADV_Stripe::cancel_subscription( $booking->stripe_subscription_id );
            if ( isset( $result['error'] ) ) {
                $error = $result['error'];
            }
        }

        if ( $error ) {
            adv_set_flash( 'unsub_error', $error );
            wp_safe_redirect( add_query_arg( 'tab', 'billing', home_url( '/dashboard/' ) ) );
            exit;
        }

        // Mark booking as cancelled in DB
        $wpdb->update(
            $wpdb->prefix . 'adv_bookings',
            [ 'status'  => 'cancelled' ],
            [ 'user_id' => $user_id, 'status' => 'active' ]
        );

        // Revoke access immediately
        delete_user_meta( $user_id, 'adv_payment_completed' );
        delete_user_meta( $user_id, 'adv_active_plan_id' );

        // Take all user listings offline immediately (suspended via quota sync where quota=0)
        adv_sync_user_listing_quota( $user_id );

        // Fire hook (e.g. for email notifications)
        do_action( 'adv_subscription_cancelled', $user_id );

        wp_safe_redirect( add_query_arg( 'unsubscribed', '1', home_url( '/dashboard/' ) ) );
        exit;
    }
}