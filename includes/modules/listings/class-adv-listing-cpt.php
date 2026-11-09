<?php
defined( 'ABSPATH' ) || exit;

class ADV_Listing_CPT {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
        add_action( 'init', [ __CLASS__, 'register_statuses' ] );
        add_action( 'init', function() {
            if ( ! get_option( 'adv_listing_slug_flushed_v2' ) ) {
                flush_rewrite_rules();
                update_option( 'adv_listing_slug_flushed_v2', 1 );
            }
        }, 99 );
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post_adv_listing', [ __CLASS__, 'save_meta_fields' ], 10, 2 );
        add_filter( 'wp_dropdown_users_args', [ __CLASS__, 'show_all_users_in_dropdown' ], 10, 2 );
        add_filter( 'authors_dropdown_args', [ __CLASS__, 'show_all_users_in_dropdown' ], 10, 2 );
        add_action( 'add_meta_boxes', [ __CLASS__, 'replace_author_meta_box' ] );
    }

    public static function register() {
        register_post_type( 'adv_listing', [
            'label'         => 'Listings',
            'labels'        => [
                'name'          => 'Listings',
                'singular_name' => 'Listing',
                'add_new_item'  => 'Add New Listing',
                'edit_item'     => 'Edit Listing',
                'view_item'     => 'View Listing',
                'search_items'  => 'Search Listings',
            ],
            'public'        => true,
            'has_archive'   => true,
            'rewrite'       => [ 'slug' => 'listing' ],
            'supports'      => [ 'title', 'editor', 'thumbnail', 'author' ],
            'show_in_rest'  => true,
            'show_in_menu'  => 'advertisement',
            'menu_icon'     => 'dashicons-store',
            'taxonomies'    => [ 'adv_category' ],
        ]);
    }

    public static function register_statuses() {
        register_post_status( 'suspended', [
            'label'                     => _x( 'Suspended', 'post status', 'advertisement' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Suspended <span class="count">(%s)</span>', 'Suspended <span class="count">(%s)</span>' ),
        ] );
    }

    public static function custom_row_actions( $actions, $post ) {
        if ( $post->post_type === 'adv_listing' ) {
            // Remove unwanted actions
            unset( $actions['edit'] );
            unset( $actions['inline hide-if-no-js'] );
            unset( $actions['clone'] );
            
            // Remove any cache-related actions if they exist
            unset( $actions['delete_cache'] );
            unset( $actions['clear_cache'] );
            
            // Keep only view and trash (delete) actions
            $keep_actions = [];
            if ( isset( $actions['view'] ) ) {
                $keep_actions['view'] = $actions['view'];
            }
            if ( isset( $actions['trash'] ) ) {
                $keep_actions['trash'] = $actions['trash'];
            }
            
            return $keep_actions;
        }
        return $actions;
    }

    public static function add_status_column( $columns ) {
        $columns['listing_status'] = 'Status';
        return $columns;
    }

    public static function render_status_column( $column, $post_id ) {
        if ( $column === 'listing_status' ) {
            $post = get_post( $post_id );
            $current_status = $post->post_status;
            
            echo '<select class="adv-status-dropdown" data-post-id="' . esc_attr( $post_id ) . '">';
            echo '<option value="publish" ' . selected( $current_status, 'publish', false ) . '>Published</option>';
            echo '<option value="draft" ' . selected( $current_status, 'draft', false ) . '>Draft</option>';
            echo '<option value="pending" ' . selected( $current_status, 'pending', false ) . '>Pending</option>';
            echo '<option value="suspended" ' . selected( $current_status, 'suspended', false ) . '>Suspended</option>';
            echo '</select>';
            echo '<span class="adv-status-spinner" style="display:none; margin-left:10px;">Saving...</span>';
        }
    }

    public static function add_status_change_script() {
        $screen = get_current_screen();
        if ( $screen && $screen->post_type === 'adv_listing' && $screen->base === 'edit' ) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('.adv-status-dropdown').on('change', function() {
                    var $select = $(this);
                    var postId = $select.data('post-id');
                    var newStatus = $select.val();
                    var $spinner = $select.siblings('.adv-status-spinner');
                    
                    $spinner.show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'adv_change_listing_status',
                            post_id: postId,
                            status: newStatus,
                            nonce: '<?php echo wp_create_nonce( 'adv_change_status_nonce' ); ?>'
                        },
                        success: function(response) {
                            $spinner.hide();
                            if (response.success) {
                                // Refresh the page to show updated status
                                location.reload();
                            } else {
                                alert('Error: ' + response.data);
                                $select.val($select.data('original-status'));
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
        check_ajax_referer( 'adv_change_status_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $new_status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        
        if ( ! $post_id || ! $new_status ) {
            wp_send_json_error( 'Invalid parameters' );
        }
        
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'adv_listing' ) {
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

    public static function disable_edit_link( $link, $post_id ) {
        $post = get_post( $post_id );
        if ( $post && $post->post_type === 'adv_listing' ) {
            return null;
        }
        return $link;
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'adv_listing_details',
            'Listing Details',
            [ __CLASS__, 'render_meta_box' ],
            'adv_listing',
            'normal',
            'high'
        );
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'adv_listing_meta_box', 'adv_listing_meta_nonce' );

        $phone = get_post_meta( $post->ID, 'adv_phone', true );
        $email = get_post_meta( $post->ID, 'adv_email', true );
        $whatsapp = get_post_meta( $post->ID, 'adv_whatsapp', true );
        $facebook = get_post_meta( $post->ID, 'adv_facebook', true );
        $instagram = get_post_meta( $post->ID, 'adv_instagram', true );
        $linkedin = get_post_meta( $post->ID, 'adv_linkedin', true );
        $features = get_post_meta( $post->ID, 'adv_features', true );
        $pricing_type = get_post_meta( $post->ID, 'adv_pricing_type', true ) ?: 'custom';
        $price_custom = get_post_meta( $post->ID, 'adv_price_custom', true );
        $price_range = get_post_meta( $post->ID, 'adv_price_range', true );

        $price_ranges = [
            'cheap'     => 'Cheap (£)',
            'economy'   => 'Economy (££)',
            'moderate'  => 'Moderate (£££)',
            'ultrahigh' => 'Ultra High (££££)',
        ];

        include ADV_PATH . 'admin/views/listing-meta-box.php';
    }

    public static function save_meta_fields( $post_id, $post ) {
        if ( ! isset( $_POST['adv_listing_meta_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['adv_listing_meta_nonce'], 'adv_listing_meta_box' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $meta_fields = [
            'adv_phone'        => sanitize_text_field( $_POST['adv_phone'] ?? '' ),
            'adv_email'        => sanitize_email( $_POST['adv_email'] ?? '' ),
            'adv_whatsapp'     => sanitize_text_field( $_POST['adv_whatsapp'] ?? '' ),
            'adv_facebook'     => esc_url_raw( $_POST['adv_facebook'] ?? '' ),
            'adv_instagram'    => esc_url_raw( $_POST['adv_instagram'] ?? '' ),
            'adv_linkedin'     => esc_url_raw( $_POST['adv_linkedin'] ?? '' ),
            'adv_features'     => sanitize_textarea_field( $_POST['adv_features'] ?? '' ),
            'adv_pricing_type' => sanitize_text_field( $_POST['adv_pricing_type'] ?? 'custom' ),
            'adv_price_custom' => sanitize_text_field( $_POST['adv_price_custom'] ?? '' ),
            'adv_price_range'  => sanitize_text_field( $_POST['adv_price_range'] ?? '' ),
        ];

        foreach ( $meta_fields as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
    }

    public static function show_all_users_in_dropdown( $query_args, $r ) {
        // Only apply this filter for adv_listing post type
        $screen = get_current_screen();
        if ( $screen && $screen->post_type === 'adv_listing' ) {
            // Remove the 'who' parameter to show all users, not just admins
            if ( isset( $query_args['who'] ) ) {
                unset( $query_args['who'] );
            }
            // Also remove capability restrictions
            if ( isset( $query_args['capability'] ) ) {
                unset( $query_args['capability'] );
            }
        }
        return $query_args;
    }

    public static function replace_author_meta_box() {
        $screen = get_current_screen();
        if ( $screen && $screen->post_type === 'adv_listing' ) {
            // Remove the default author meta box
            remove_meta_box( 'authordiv', 'adv_listing', 'side' );
            // Add our custom author meta box
            add_meta_box(
                'adv_author_div',
                'Author',
                [ __CLASS__, 'render_custom_author_box' ],
                'adv_listing',
                'side',
                'default'
            );
        }
    }

    public static function render_custom_author_box( $post ) {
        $users = get_users();
        $current_author = $post->post_author;
        ?>
        <label class="screen-reader-text" for="post_author_override">Author</label>
        <select name="post_author_override" id="post_author_override" class="">
            <?php foreach ( $users as $user ) : ?>
                <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $current_author, $user->ID ); ?>>
                    <?php echo esc_html( $user->display_name ); ?> (<?php echo esc_html( $user->user_login ); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}

// Add filters immediately after class definition
add_filter( 'post_row_actions', [ 'ADV_Listing_CPT', 'custom_row_actions' ], 10, 2 );
add_filter( 'manage_adv_listing_posts_columns', [ 'ADV_Listing_CPT', 'add_status_column' ], 20 );
add_action( 'manage_adv_listing_posts_custom_column', [ 'ADV_Listing_CPT', 'render_status_column' ], 10, 2 );
add_action( 'admin_footer-edit.php', [ 'ADV_Listing_CPT', 'add_status_change_script' ] );
add_action( 'wp_ajax_adv_change_listing_status', [ 'ADV_Listing_CPT', 'ajax_change_status' ] );
// add_filter( 'get_edit_post_link', [ 'ADV_Listing_CPT', 'disable_edit_link' ], 10, 2 ); // Removed to allow admin editing