<?php
defined( 'ABSPATH' ) || exit;

class ADV_Recipe_CPT {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
        add_action( 'init', [ __CLASS__, 'register_statuses' ] );
        add_action( 'init', function() {
            if ( ! get_option( 'featured_recipe_slug_flushed_v1' ) ) {
                flush_rewrite_rules();
                update_option( 'featured_recipe_slug_flushed_v1', 1 );
            }
        }, 99 );
    }

    public static function register() {
        register_post_type( 'featured_recipe', [
            'labels'       => [
                'name'          => 'Featured Recipes',
                'singular_name' => 'Featured Recipe',
                'add_new'       => 'Add New Recipe',
                'add_new_item'  => 'Add New Featured Recipe',
                'edit_item'     => 'Edit Featured Recipe',
                'new_item'      => 'New Featured Recipe',
                'view_item'     => 'View Recipe',
                'search_items'  => 'Search Recipes',
            ],
            'public'       => true,
            'has_archive'  => true,
            'rewrite'      => [ 'slug' => 'featured-recipes' ],
            'supports'     => [ 'title', 'editor', 'thumbnail', 'author' ],
            'show_in_rest' => true,
            'show_in_menu' => 'advertisement',
            'menu_icon'    => 'dashicons-food',
        ]);
    }

    public static function register_statuses() {
        register_post_status( 'pending_payment', [
            'label'                     => _x( 'Pending Payment', 'post status', 'advertisement' ),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Pending Payment <span class="count">(%s)</span>',
                'Pending Payment <span class="count">(%s)</span>'
            ),
        ] );
    }

    public static function custom_row_actions( $actions, $post ) {
        if ( $post->post_type === 'featured_recipe' ) {
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
        $columns['recipe_status'] = 'Status';
        return $columns;
    }

    public static function render_status_column( $column, $post_id ) {
        if ( $column === 'recipe_status' ) {
            $post = get_post( $post_id );
            $current_status = $post->post_status;
            
            echo '<select class="adv-recipe-status-dropdown" data-post-id="' . esc_attr( $post_id ) . '">';
            echo '<option value="publish" ' . selected( $current_status, 'publish', false ) . '>Published</option>';
            echo '<option value="draft" ' . selected( $current_status, 'draft', false ) . '>Draft</option>';
            echo '<option value="pending" ' . selected( $current_status, 'pending', false ) . '>Pending</option>';
            echo '<option value="pending_payment" ' . selected( $current_status, 'pending_payment', false ) . '>Pending Payment</option>';
            echo '</select>';
            echo '<span class="adv-recipe-status-spinner" style="display:none; margin-left:10px;">Saving...</span>';
        }
    }

    public static function add_status_change_script() {
        $screen = get_current_screen();
        if ( $screen && $screen->post_type === 'featured_recipe' && $screen->base === 'edit' ) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('.adv-recipe-status-dropdown').on('change', function() {
                    var $select = $(this);
                    var postId = $select.data('post-id');
                    var newStatus = $select.val();
                    var $spinner = $select.siblings('.adv-recipe-status-spinner');
                    
                    $spinner.show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'adv_change_recipe_status',
                            post_id: postId,
                            status: newStatus,
                            nonce: '<?php echo wp_create_nonce( 'adv_change_recipe_status_nonce' ); ?>'
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
        check_ajax_referer( 'adv_change_recipe_status_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $new_status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        
        if ( ! $post_id || ! $new_status ) {
            wp_send_json_error( 'Invalid parameters' );
        }
        
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'featured_recipe' ) {
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
        if ( $post && $post->post_type === 'featured_recipe' ) {
            return null;
        }
        return $link;
    }
}

// Add filters immediately after class definition
add_filter( 'post_row_actions', [ 'ADV_Recipe_CPT', 'custom_row_actions' ], 10, 2 );
add_filter( 'manage_featured_recipe_posts_columns', [ 'ADV_Recipe_CPT', 'add_status_column' ], 20 );
add_action( 'manage_featured_recipe_posts_custom_column', [ 'ADV_Recipe_CPT', 'render_status_column' ], 10, 2 );
add_action( 'admin_footer-edit.php', [ 'ADV_Recipe_CPT', 'add_status_change_script' ] );
add_action( 'wp_ajax_adv_change_recipe_status', [ 'ADV_Recipe_CPT', 'ajax_change_status' ] );
add_filter( 'get_edit_post_link', [ 'ADV_Recipe_CPT', 'disable_edit_link' ], 10, 2 );