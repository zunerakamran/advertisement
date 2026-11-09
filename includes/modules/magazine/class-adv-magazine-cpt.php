<?php
defined( 'ABSPATH' ) || exit;

class ADV_Magazine_CPT {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
        add_action( 'init', [ __CLASS__, 'register_statuses' ] );
        add_action( 'init', function() {
            if ( ! get_option( 'adv_magazine_slug_flushed_v1' ) ) {
                flush_rewrite_rules();
                update_option( 'adv_magazine_slug_flushed_v1', 1 );
            }
        }, 99 );
    }

    public static function maybe_flush_rewrite_rules() {
        if ( isset( $_GET['flush_magazine_rules'] ) && current_user_can( 'manage_options' ) ) {
            flush_rewrite_rules();
            wp_redirect( admin_url( 'edit.php?post_type=adv_magazine' ) );
            exit;
        }
    }

    public static function register() {
        register_post_type( 'adv_magazine', [
            'label'        => 'Magazine Adverts',
            'labels'       => [
                'name'          => 'Magazine Adverts',
                'singular_name' => 'Magazine Advert',
                'add_new_item'  => 'Add New Magazine Advert',
                'edit_item'     => 'Edit Magazine Advert',
                'view_item'     => 'View Magazine Advert',
                'search_items'  => 'Search Magazine Adverts',
                'all_items'     => 'All Magazine Adverts',
            ],
            'public'       => false,   // not front-end browsable
            'show_ui'      => true,
            'show_in_menu' => 'advertisement',
            'supports'     => [ 'title', 'author' ],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function register_statuses() {
        register_post_status( 'adv_pending_payment', [
            'label'                     => _x( 'Pending Payment', 'post status', 'advertisement' ),
            'public'                    => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Pending Payment <span class="count">(%s)</span>',
                'Pending Payment <span class="count">(%s)</span>'
            ),
        ] );

        register_post_status( 'adv_approved', [
            'label'                     => _x( 'Approved', 'post status', 'advertisement' ),
            'public'                    => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Approved <span class="count">(%s)</span>',
                'Approved <span class="count">(%s)</span>'
            ),
        ] );
    }

    public static function custom_row_actions( $actions, $post ) {
        if ( $post->post_type === 'adv_magazine' ) {
            unset( $actions['edit'] );
            unset( $actions['inline hide-if-no-js'] );
            unset( $actions['clone'] );
            unset( $actions['delete_cache'] );
            unset( $actions['clear_cache'] );
            
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
        $columns['magazine_status'] = 'Status';
        $columns['magazine_url'] = 'URL';
        return $columns;
    }

    public static function render_status_column( $column, $post_id ) {
        if ( $column === 'magazine_status' ) {
            $post = get_post( $post_id );
            $current_status = $post->post_status;
            
            echo '<select class="adv-magazine-status-dropdown" data-post-id="' . esc_attr( $post_id ) . '">';
            echo '<option value="publish" ' . selected( $current_status, 'publish', false ) . '>Published</option>';
            echo '<option value="draft" ' . selected( $current_status, 'draft', false ) . '>Draft</option>';
            echo '<option value="pending" ' . selected( $current_status, 'pending', false ) . '>Pending</option>';
            echo '<option value="adv_pending_payment" ' . selected( $current_status, 'adv_pending_payment', false ) . '>Pending Payment</option>';
            echo '<option value="adv_approved" ' . selected( $current_status, 'adv_approved', false ) . '>Approved</option>';
            echo '</select>';
            echo '<span class="adv-magazine-status-spinner" style="display:none; margin-left:10px;">Saving...</span>';
        }
        
        if ( $column === 'magazine_url' ) {
            $post = get_post( $post_id );
            $current_status = $post->post_status;
            $existing_url = get_post_meta( $post_id, 'magazine_url', true );
            
            if ( $current_status === 'pending' ) {
                echo '<input type="text" class="adv-magazine-url-input" data-post-id="' . esc_attr( $post_id ) . '" value="' . esc_attr( $existing_url ) . '" placeholder="Enter URL" style="width:200px;">';
                echo '<button class="button button-small adv-magazine-url-save" data-post-id="' . esc_attr( $post_id ) . '" style="margin-left:5px;">Save</button>';
                echo '<span class="adv-magazine-url-spinner" style="display:none; margin-left:10px;">Saving...</span>';
            } else {
                echo $existing_url ? '<a href="' . esc_url( $existing_url ) . '" target="_blank">' . esc_html( $existing_url ) . '</a>' : '-';
            }
        }
    }

    public static function add_status_change_script() {
        $screen = get_current_screen();
        if ( $screen && $screen->post_type === 'adv_magazine' && $screen->base === 'edit' ) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                // Status change handler
                $('.adv-magazine-status-dropdown').on('change', function() {
                    var $select = $(this);
                    var postId = $select.data('post-id');
                    var newStatus = $select.val();
                    var $spinner = $select.siblings('.adv-magazine-status-spinner');
                    
                    $spinner.show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'adv_change_magazine_status',
                            post_id: postId,
                            status: newStatus,
                            nonce: '<?php echo wp_create_nonce( 'adv_change_magazine_status_nonce' ); ?>'
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
                
                // URL save handler
                $('.adv-magazine-url-save').on('click', function() {
                    var $button = $(this);
                    var postId = $button.data('post-id');
                    var $input = $button.siblings('.adv-magazine-url-input');
                    var url = $input.val();
                    var $spinner = $button.siblings('.adv-magazine-url-spinner');
                    
                    $spinner.show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'adv_save_magazine_url',
                            post_id: postId,
                            url: url,
                            nonce: '<?php echo wp_create_nonce( 'adv_save_magazine_url_nonce' ); ?>'
                        },
                        success: function(response) {
                            $spinner.hide();
                            if (response.success) {
                                alert('URL saved successfully');
                            } else {
                                alert('Error: ' + response.data);
                            }
                        },
                        error: function() {
                            $spinner.hide();
                            alert('Error saving URL');
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }

    public static function ajax_change_status() {
        check_ajax_referer( 'adv_change_magazine_status_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $new_status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        
        if ( ! $post_id || ! $new_status ) {
            wp_send_json_error( 'Invalid parameters' );
        }
        
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'adv_magazine' ) {
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

    public static function ajax_save_url() {
        check_ajax_referer( 'adv_save_magazine_url_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
        
        if ( ! $post_id ) {
            wp_send_json_error( 'Invalid parameters' );
        }
        
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'adv_magazine' ) {
            wp_send_json_error( 'Invalid post' );
        }
        
        update_post_meta( $post_id, 'magazine_url', $url );
        
        wp_send_json_success( 'URL saved' );
    }

    public static function ajax_save_all() {
        check_ajax_referer( 'adv_save_magazine_all_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $new_status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        $url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
        
        if ( ! $post_id || ! $new_status ) {
            wp_send_json_error( 'Invalid parameters' );
        }
        
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'adv_magazine' ) {
            wp_send_json_error( 'Invalid post' );
        }
        
        // Update status
        $result = wp_update_post( [
            'ID' => $post_id,
            'post_status' => $new_status
        ] );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        
        // Update URL
        update_post_meta( $post_id, '_adv_mag_url', $url );
        
        wp_send_json_success( 'Status and URL saved' );
    }

    public static function disable_edit_link( $link, $post_id ) {
        $post = get_post( $post_id );
        if ( $post && $post->post_type === 'adv_magazine' ) {
            return null;
        }
        return $link;
    }
}

// Add filters immediately after class definition
add_filter( 'post_row_actions', [ 'ADV_Magazine_CPT', 'custom_row_actions' ], 10, 2 );
add_action( 'wp_ajax_adv_change_magazine_status', [ 'ADV_Magazine_CPT', 'ajax_change_status' ] );
add_action( 'wp_ajax_adv_save_magazine_url', [ 'ADV_Magazine_CPT', 'ajax_save_url' ] );
add_action( 'wp_ajax_adv_save_magazine_all', [ 'ADV_Magazine_CPT', 'ajax_save_all' ] );
add_filter( 'get_edit_post_link', [ 'ADV_Magazine_CPT', 'disable_edit_link' ], 10, 2 );