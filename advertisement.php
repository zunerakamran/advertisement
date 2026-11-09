<?php
/**
 * Plugin Name: Advertisement
 * Plugin URI:  https://getthegoss.co.uk
 * Description: Ad management, pricing plans, Stripe payments and email for Get The Goss.
 * Version:     1.0.0
 * Author:      ePatronus IT Solutions
 * Text Domain: advertisement
 */

defined( 'ABSPATH' ) || exit;

define( 'ADV_VERSION', '1.0.1' );
define( 'ADV_PATH',    plugin_dir_path( __FILE__ ) );
define( 'ADV_URL',     plugin_dir_url( __FILE__ )  );

// Helpers — load first
require_once ADV_PATH . 'includes/class-advertisement-helpers.php';

// Auth module
require_once ADV_PATH . 'includes/modules/auth/class-adv-user-roles.php';
require_once ADV_PATH . 'includes/modules/auth/class-adv-security.php';
require_once ADV_PATH . 'includes/modules/auth/class-adv-auth.php';

// Activator
require_once ADV_PATH . 'includes/class-advertisement-activator.php';

// Public
require_once ADV_PATH . 'public/class-advertisement-public.php';

// Pricing module
require_once ADV_PATH . 'includes/modules/pricing/class-adv-plans.php';
require_once ADV_PATH . 'includes/modules/pricing/class-adv-coupons.php';
require_once ADV_PATH . 'includes/modules/pricing/class-adv-pricing-shortcode.php';

// Admin
require_once ADV_PATH . 'admin/class-advertisement-admin.php';

require_once ADV_PATH . 'includes/modules/stripe/class-adv-stripe-settings.php';
require_once ADV_PATH . 'includes/modules/stripe/class-adv-stripe.php';
require_once ADV_PATH . 'includes/modules/listings/class-adv-listing-cpt.php';
require_once ADV_PATH . 'includes/modules/listings/class-adv-categories.php';
require_once ADV_PATH . 'includes/modules/listings/class-adv-listing-form.php';
if ( is_admin() ) {
    require_once ADV_PATH . 'includes/modules/listings/class-adv-listing-admin.php';
}
require_once ADV_PATH . 'includes/modules/listings/class-adv-listings-shortcode.php';
require_once ADV_PATH . 'includes/modules/dashboard/class-adv-dashboard.php';
require_once ADV_PATH . 'includes/modules/email/class-adv-emails.php';
// Recipe module
require_once ADV_PATH . 'includes/modules/recipes/class-adv-recipe-cpt.php';
require_once ADV_PATH . 'includes/modules/recipes/class-adv-recipe-settings.php';
require_once ADV_PATH . 'includes/modules/recipes/class-adv-recipe-form.php';
// Blog module
require_once ADV_PATH . 'includes/modules/blogs/class-adv-blog-cpt.php';
require_once ADV_PATH . 'includes/modules/blogs/class-adv-blog-settings.php';
require_once ADV_PATH . 'includes/modules/blogs/class-adv-blog-form.php';

// Magazine module
require_once ADV_PATH . 'includes/modules/magazine/class-adv-magazine-cpt.php';
require_once ADV_PATH . 'includes/modules/magazine/class-adv-magazine-settings.php';
require_once ADV_PATH . 'includes/modules/magazine/class-adv-magazine-form.php';
require_once ADV_PATH . 'includes/modules/magazine/class-adv-magazine-admin.php';

// Banner module
require_once ADV_PATH . 'includes/modules/banners/class-adv-banner-cpt.php';
require_once ADV_PATH . 'includes/modules/banners/class-adv-banner-settings.php';
require_once ADV_PATH . 'includes/modules/banners/class-adv-banner-form.php';
require_once ADV_PATH . 'includes/modules/banners/class-adv-banner-display.php';
require_once ADV_PATH . 'includes/modules/banners/class-adv-banner-admin.php';

// Hooks
register_activation_hook(   __FILE__, [ 'Advertisement_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'ADV_User_Roles', 'remove' ] );

// Boot
function run_advertisement() {
    Advertisement_Activator::maybe_upgrade();
    new ADV_Security();
    new ADV_Auth();
    new Advertisement_Public();
    new ADV_Pricing_Shortcode();
    new Advertisement_Admin();
    new ADV_Stripe_Settings();
    new ADV_Stripe();
    ADV_Listing_CPT::init();
    ADV_Categories::init();
    new ADV_Listing_Form();
    if ( is_admin() ) new ADV_Listing_Admin();
    new ADV_Listings_Shortcode();
    new ADV_Dashboard();
    new ADV_Emails();
    ADV_Recipe_CPT::init();
    new ADV_Recipe_Form();
    ADV_Blog_CPT::init();
    new ADV_Blog_Form();
    ADV_Magazine_CPT::init();
    new ADV_Magazine_Form();
    if ( is_admin() ) new ADV_Magazine_Admin();
    ADV_Banner_CPT::init();
    new ADV_Banner_Form();
    new ADV_Banner_Display();
    if ( is_admin() ) new ADV_Banner_Admin();
}
add_action( 'plugins_loaded', 'run_advertisement' );