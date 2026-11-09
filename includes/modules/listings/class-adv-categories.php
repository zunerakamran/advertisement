<?php
defined( 'ABSPATH' ) || exit;

class ADV_Categories {

    public static function init() {
        // Register custom taxonomy for listing categories
        add_action( 'init', [ __CLASS__, 'register_taxonomy' ] );
        add_action( 'init', [ __CLASS__, 'seed_defaults' ], 20 );
    }

    public static function register_taxonomy() {
        $labels = [
            'name'              => 'Categories',
            'singular_name'     => 'Category',
            'search_items'      => 'Search Categories',
            'all_items'         => 'All Categories',
            'parent_item'       => 'Parent Category',
            'parent_item_colon' => 'Parent Category:',
            'edit_item'         => 'Edit Category',
            'update_item'       => 'Update Category',
            'add_new_item'      => 'Add New Category',
            'new_item_name'     => 'New Category Name',
            'menu_name'         => 'Categories',
        ];

        register_taxonomy( 'adv_category', 'adv_listing', [
            'labels'            => $labels,
            'hierarchical'      => true, // supports subcategories
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_menu'      => false,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'listing-category' ],
            'show_in_rest'      => true,
        ]);
    }

    public static function get_all() {
        return get_terms([
            'taxonomy'   => 'adv_category',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);
    }

    public static function get_children( $parent_id ) {
        return get_terms([
            'taxonomy'   => 'adv_category',
            'hide_empty' => false,
            'parent'     => $parent_id,
        ]);
    }

    public static function get_top_level() {
        return get_terms([
            'taxonomy'   => 'adv_category',
            'hide_empty' => false,
            'parent'     => 0,
            'orderby'    => 'name',
        ]);
    }

    public static function seed_defaults() {
        if ( get_option( 'adv_categories_seeded' ) ) return;

        $existing = get_terms([
            'taxonomy'   => 'adv_category',
            'hide_empty' => false,
        ]);

        if ( empty( $existing ) || is_wp_error( $existing ) ) {
            $defaults = [ 'Automotive', 'Beauty & Wellness', 'Food & Dining', 'Home Services', 'Real Estate', 'Services', 'Shopping', 'Technology' ];
            foreach ( $defaults as $cat ) {
                wp_insert_term( $cat, 'adv_category' );
            }
        }

        update_option( 'adv_categories_seeded', 1 );
    }
}