<?php
defined( 'ABSPATH' ) || exit;

class ADV_Blog_CPT {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
        add_action( 'init', function() {
            if ( ! get_option( 'adv_blog_slug_flushed_v1' ) ) {
                flush_rewrite_rules();
                update_option( 'adv_blog_slug_flushed_v1', 1 );
            }
        }, 99 );
    }

    public static function register() {
        register_post_type( 'featured_blog', [
            'label'        => 'Featured Blogs',
            'labels'       => [
                'name'          => 'Featured Blogs',
                'singular_name' => 'Featured Blog',
                'add_new_item'  => 'Add New Featured Blog',
                'edit_item'     => 'Edit Featured Blog',
                'view_item'     => 'View Featured Blog',
                'search_items'  => 'Search Featured Blogs',
            ],
            'public'       => true,
            'has_archive'  => true,
            'rewrite'      => [ 'slug' => 'featured-blogs' ],
            'supports'     => [ 'title', 'editor', 'thumbnail', 'author' ],
            'show_in_rest' => true,
            'show_in_menu' => 'advertisement',
            'menu_icon'    => 'dashicons-welcome-write-blog',
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ]);
    }

    public static function custom_row_actions( $actions, $post ) {
        if ( $post->post_type === 'featured_blog' ) {
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
        $columns['blog_status'] = 'Status';
        return $columns;
    }

    public static function render_status_column( $column, $post_id ) {
        if ( $column === 'blog_status' ) {
            $post = get_post( $post_id );
            $current_status = $post->post_status;
            
            echo '<select class="adv-blog-status-dropdown" data-post-id="' . esc_attr( $post_id ) . '">';
            echo '<option value="publish" ' . selected( $current_status, 'publish', false ) . '>Published</option>';
            echo '<option value="draft" ' . selected( $current_status, 'draft', false ) . '>Draft</option>';
            echo '<option value="pending" ' . selected( $current_status, 'pending', false ) . '>Pending</option>';
            echo '</select>';
            echo '<span class="adv-blog-status-spinner" style="display:none; margin-left:10px;">Saving...</span>';
        }
    }

    public static function add_status_change_script() {
        $screen = get_current_screen();
        if ( $screen && $screen->post_type === 'featured_blog' && $screen->base === 'edit' ) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('.adv-blog-status-dropdown').on('change', function() {
                    var $select = $(this);
                    var postId = $select.data('post-id');
                    var newStatus = $select.val();
                    var $spinner = $select.siblings('.adv-blog-status-spinner');
                    
                    $spinner.show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'adv_change_blog_status',
                            post_id: postId,
                            status: newStatus,
                            nonce: '<?php echo wp_create_nonce( 'adv_change_blog_status_nonce' ); ?>'
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
        check_ajax_referer( 'adv_change_blog_status_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $new_status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        
        if ( ! $post_id || ! $new_status ) {
            wp_send_json_error( 'Invalid parameters' );
        }
        
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'featured_blog' ) {
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
        if ( $post && $post->post_type === 'featured_blog' ) {
            return null;
        }
        return $link;
    }
}

// Add filters immediately after class definition
add_filter( 'post_row_actions', [ 'ADV_Blog_CPT', 'custom_row_actions' ], 10, 2 );
add_filter( 'manage_featured_blog_posts_columns', [ 'ADV_Blog_CPT', 'add_status_column' ], 20 );
add_action( 'manage_featured_blog_posts_custom_column', [ 'ADV_Blog_CPT', 'render_status_column' ], 10, 2 );
add_action( 'admin_footer-edit.php', [ 'ADV_Blog_CPT', 'add_status_change_script' ] );
add_action( 'wp_ajax_adv_change_blog_status', [ 'ADV_Blog_CPT', 'ajax_change_status' ] );
add_filter( 'get_edit_post_link', [ 'ADV_Blog_CPT', 'disable_edit_link' ], 10, 2 );