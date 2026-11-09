<?php
defined( 'ABSPATH' ) || exit;

class Advertisement_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_menus() {
        add_menu_page(
            'Advertisement',
            'Advertisement',
            'manage_options',
            'advertisement',
            [ $this, 'dashboard_page' ],
            'dashicons-megaphone',
            30
        );

        // CPTs automatically add themselves to the menu via show_in_menu => 'advertisement'
        // We only need to add non-CPT menu items
        add_submenu_page( 'advertisement', 'Categories',      'Categories',      'manage_options', 'edit-tags.php?taxonomy=adv_category&post_type=adv_listing' );

        // Settings menu - all settings in one page with tabs
        add_submenu_page( 'advertisement', 'Settings', 'Settings', 'manage_options', 'adv-settings', [ $this, 'settings_page' ] );

        // Remove duplicate "Advertisement" submenu item
        remove_submenu_page( 'advertisement', 'advertisement' );
    }

    public function dashboard_page() {
        echo '<div class="wrap"><h1>Advertisement Dashboard</h1><p>Welcome. Use the menu to manage plans and coupons.</p></div>';
    }

    public function settings_page() {
        include ADV_PATH . 'admin/views/settings.php';
    }

    public function plans_page() {
        ADV_Plans::init();
        include ADV_PATH . 'admin/views/plans.php';
    }

    public function coupons_page() {
        ADV_Coupons::init();
        include ADV_PATH . 'admin/views/coupons.php';
    }

    public function stripe_settings_page() {
        include ADV_PATH . 'admin/views/stripe-settings.php';
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'adv-' ) === false && $hook !== 'toplevel_page_advertisement' ) return;
        wp_enqueue_style( 'adv-admin', ADV_URL . 'admin/assets/admin.css', [], ADV_VERSION );
    }
    public function recipe_settings_page() {
        if (
            isset( $_POST['adv_recipe_settings_nonce'] )
            && wp_verify_nonce( $_POST['adv_recipe_settings_nonce'], 'adv_recipe_settings_save' )
            && current_user_can( 'manage_options' )
        ) {
            ADV_Recipe_Settings::save( $_POST['adv_recipe_price'] ?? 35 );
            wp_safe_redirect( add_query_arg( 'saved', '1', admin_url( 'admin.php?page=adv-recipe-settings' ) ) );
            exit;
        }
        include ADV_PATH . 'admin/views/recipe-settings.php';
    }
    public function blog_settings_page() {
    if (
        isset( $_POST['adv_blog_settings_nonce'] )
        && wp_verify_nonce( $_POST['adv_blog_settings_nonce'], 'adv_blog_settings_save' )
        && current_user_can( 'manage_options' )
    ) {
        ADV_Blog_Settings::save( $_POST['adv_blog_price'] ?? 50 );
        wp_safe_redirect( add_query_arg( 'saved', '1', admin_url( 'admin.php?page=adv-blog-settings' ) ) );
        exit;
    }
    include ADV_PATH . 'admin/views/blog-settings.php';
}
public function magazine_settings_page() {
    if (
        isset( $_POST['adv_mag_settings_nonce'] )
        && wp_verify_nonce( $_POST['adv_mag_settings_nonce'], 'adv_mag_settings_save' )
        && current_user_can( 'manage_options' )
    ) {
        ADV_Magazine_Settings::save_all( $_POST['prices'] ?? [] );
        wp_safe_redirect( add_query_arg( 'saved', '1', admin_url( 'admin.php?page=adv-magazine-settings' ) ) );
        exit;
    }
    include ADV_PATH . 'admin/views/magazine-settings.php';
}
public function banner_settings_page() {
        if (
            isset( $_POST['adv_banner_settings_nonce'] )
            && wp_verify_nonce( $_POST['adv_banner_settings_nonce'], 'adv_banner_settings_save' )
            && current_user_can( 'manage_options' )
        ) {
            ADV_Banner_Settings::save_all( $_POST['prices'] ?? [] );
            wp_safe_redirect( add_query_arg( 'saved', '1', admin_url( 'admin.php?page=adv-banner-settings' ) ) );
            exit;
        }
        include ADV_PATH . 'admin/views/banner-settings.php';
    }
}