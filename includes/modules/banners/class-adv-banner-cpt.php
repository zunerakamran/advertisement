<?php
defined( 'ABSPATH' ) || exit;

class ADV_Banner_CPT {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
        add_action( 'init', [ __CLASS__, 'register_statuses' ] );
        add_action( 'init', function() {
            if ( ! get_option( 'adv_banner_slug_flushed_v1' ) ) {
                flush_rewrite_rules();
                update_option( 'adv_banner_slug_flushed_v1', 1 );
            }
        }, 99 );

        // Ensure WP Admin list shows all banner post statuses (pending, pending_payment, published, expired, draft)
        add_action( 'pre_get_posts', [ __CLASS__, 'admin_list_all_statuses' ] );

        // Custom Admin Columns
        add_filter( 'manage_adv_banner_posts_columns',       [ __CLASS__, 'add_admin_columns' ] );
        add_action( 'manage_adv_banner_posts_custom_column', [ __CLASS__, 'render_admin_columns' ], 10, 2 );

        // Register daily cron for banner expirations & auto-renews
        if ( ! wp_next_scheduled( 'adv_banner_daily_cron' ) ) {
            wp_schedule_event( time(), 'daily', 'adv_banner_daily_cron' );
        }
        add_action( 'adv_banner_daily_cron', [ __CLASS__, 'check_expirations' ] );
    }

    public static function custom_row_actions( $actions, $post ) {
        if ( $post->post_type === 'adv_banner' ) {
            unset( $actions['inline hide-if-no-js'] );
            unset( $actions['clone'] );
            unset( $actions['delete_cache'] );
            unset( $actions['clear_cache'] );
            
            $keep_actions = [];
            if ( isset( $actions['edit'] ) ) {
                $keep_actions['edit'] = $actions['edit'];
            }
            if ( isset( $actions['trash'] ) ) {
                $keep_actions['trash'] = $actions['trash'];
            }
            
            return $keep_actions;
        }
        return $actions;
    }

    public static function register() {
        register_post_type( 'adv_banner', [
            'label'        => 'Homepage Banners',
            'labels'       => [
                'name'          => 'Homepage Banners',
                'singular_name' => 'Homepage Banner',
                'add_new_item'  => 'Add New Banner',
                'edit_item'     => 'Edit Banner',
                'view_item'     => 'View Banner',
                'search_items'  => 'Search Banners',
                'all_items'     => 'All Banners',
            ],
            'public'          => false, // not front-end browsable — shown only via [goss_homepage_banner]
            'show_ui'         => true,
            'show_in_menu'    => 'advertisement',
            'supports'        => [ 'title', 'author', 'thumbnail' ],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ]);
    }

    public static function register_statuses() {
        register_post_status( 'adv_banner_pending_payment', [
            'label'                     => _x( 'Pending Payment', 'post status', 'advertisement' ),
            'public'                    => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Pending Payment <span class="count">(%s)</span>',
                'Pending Payment <span class="count">(%s)</span>'
            ),
        ] );

        register_post_status( 'adv_banner_expired', [
            'label'                     => _x( 'Expired', 'post status', 'advertisement' ),
            'public'                    => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Expired <span class="count">(%s)</span>',
                'Expired <span class="count">(%s)</span>'
            ),
        ] );
    }

    /**
     * Include all custom and pending statuses in WP Admin main edit list
     */
    public static function admin_list_all_statuses( $query ) {
        if ( is_admin() && $query->is_main_query() && $query->get( 'post_type' ) === 'adv_banner' ) {
            if ( empty( $_GET['post_status'] ) ) {
                $query->set( 'post_status', [ 'publish', 'pending', 'adv_banner_pending_payment', 'adv_banner_expired', 'draft' ] );
            }
        }
    }

    /**
     * Custom Admin List Columns
     */
    public static function add_admin_columns( $columns ) {
        $new_cols = [];
        $new_cols['cb']         = $columns['cb'];
        $new_cols['thumbnail']  = 'Image';
        $new_cols['title']      = 'Business / Title';
        $new_cols['size']       = 'Size & Dimensions';
        $new_cols['price']      = 'Price';
        $new_cols['status']     = 'Status';
        $new_cols['expires_at'] = 'Expires / Renews';
        return $new_cols;
    }

    public static function render_admin_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'thumbnail':
                $img_id = get_post_meta( $post_id, '_adv_banner_image_id', true );
                if ( $img_id ) {
                    $url = wp_get_attachment_url( $img_id );
                    echo '<a href="' . esc_url( $url ) . '" target="_blank" class="button button-small">View Image</a>';
                } else {
                    echo '—';
                }
                break;

            case 'size':
                $size = get_post_meta( $post_id, '_adv_banner_size', true );
                $w    = ADV_Banner_Settings::get_width( $size );
                $h    = ADV_Banner_Settings::get_height( $size );
                echo '<strong>' . esc_html( ADV_Banner_Settings::get_label( $size ) ) . '</strong><br>';
                echo '<small style="color:#64748b;">' . esc_html( $w . ' × ' . $h . ' px' ) . '</small>';
                break;

            case 'price':
                $price = get_post_meta( $post_id, '_adv_banner_price', true );
                echo $price ? '£' . number_format( (float) $price, 2 ) . ' / mo' : '—';
                break;

            case 'status':
                $post        = get_post( $post_id );
                $post_status = $post->post_status;
                echo '<select class="adv-banner-status-dropdown" data-post-id="' . esc_attr( $post_id ) . '" style="width:100%;max-width:150px;">';
                echo '<option value="publish" ' . selected( $post_status, 'publish', false ) . '>Published</option>';
                echo '<option value="pending" ' . selected( $post_status, 'pending', false ) . '>Pending Review</option>';
                echo '<option value="adv_banner_pending_payment" ' . selected( $post_status, 'adv_banner_pending_payment', false ) . '>Pending Payment</option>';
                echo '<option value="adv_banner_expired" ' . selected( $post_status, 'adv_banner_expired', false ) . '>Expired</option>';
                echo '<option value="draft" ' . selected( $post_status, 'draft', false ) . '>Draft</option>';
                echo '</select>';
                echo '<span class="adv-banner-status-spinner" style="display:none; margin-left:10px;">Saving...</span>';
                break;

            case 'expires_at':
                $exp = get_post_meta( $post_id, '_adv_banner_expires_at', true );
                echo $exp ? esc_html( date( 'M j, Y', strtotime( $exp ) ) ) : '—';
                break;
        }
    }

    /**
     * Daily check for expired banners.
     * If auto_renew is 1 (and active subscription or bank auto-renew), extends period.
     * If auto_renew is 0, sets status to adv_banner_expired and notifies owner.
     */
    public static function check_expirations() {
        $now = current_time( 'mysql' );

        $published_banners = get_posts( [
            'post_type'   => 'adv_banner',
            'post_status' => 'publish',
            'numberposts' => -1,
        ] );

        foreach ( $published_banners as $banner ) {
            $expires_at = get_post_meta( $banner->ID, '_adv_banner_expires_at', true );
            if ( ! $expires_at ) continue;

            if ( $expires_at < $now ) {
                $auto_renew = (int) get_post_meta( $banner->ID, '_adv_banner_auto_renew', true );
                $pm_method  = get_post_meta( $banner->ID, '_adv_banner_payment_method', true );

                if ( $auto_renew === 1 && $pm_method === 'bank_transfer' ) {
                    // Simulate/process bank transfer monthly renewal in test/auto mode
                    $new_exp = date( 'Y-m-d H:i:s', strtotime( '+1 month', strtotime( $expires_at ) ) );
                    update_post_meta( $banner->ID, '_adv_banner_expires_at', $new_exp );
                    do_action( 'adv_banner_renewed', $banner->ID, $banner->post_author );
                } elseif ( $auto_renew === 0 ) {
                    // Expire the banner & free up slot
                    wp_update_post( [
                        'ID'          => $banner->ID,
                        'post_status' => 'adv_banner_expired',
                    ] );
                    do_action( 'adv_banner_expired', $banner->ID, $banner->post_author );
                }
            }
        }
    }

    public static function add_status_change_script() {
        $screen = get_current_screen();
        if ( $screen && $screen->post_type === 'adv_banner' && $screen->base === 'edit' ) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('.adv-banner-status-dropdown').on('change', function() {
                    var $select = $(this);
                    var postId = $select.data('post-id');
                    var newStatus = $select.val();
                    var $spinner = $select.siblings('.adv-banner-status-spinner');
                    
                    $spinner.show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'adv_change_banner_status',
                            post_id: postId,
                            status: newStatus,
                            nonce: '<?php echo wp_create_nonce( 'adv_change_banner_status_nonce' ); ?>'
                        },
                        success: function(response) {
                            $spinner.hide();
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + response.data);
                            }
                        },
                        error: function() {
                            $spinner.hide();
                            alert('Error changing status');
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }

    public static function ajax_change_status() {
        check_ajax_referer( 'adv_change_banner_status_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $new_status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        
        if ( ! $post_id || ! $new_status ) {
            wp_send_json_error( 'Invalid parameters' );
        }
        
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'adv_banner' ) {
            wp_send_json_error( 'Invalid post' );
        }
        
        $result = wp_update_post( [
            'ID' => $post_id,
            'post_status' => $new_status
        ] );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        
        wp_send_json_success( 'Status updated' );
    }
}

// Add filters immediately after class definition
add_filter( 'post_row_actions', [ 'ADV_Banner_CPT', 'custom_row_actions' ], 10, 2 );
add_action( 'admin_footer-edit.php', [ 'ADV_Banner_CPT', 'add_status_change_script' ] );
add_action( 'wp_ajax_adv_change_banner_status', [ 'ADV_Banner_CPT', 'ajax_change_status' ] );