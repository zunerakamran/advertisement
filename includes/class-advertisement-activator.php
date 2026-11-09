<?php
defined( 'ABSPATH' ) || exit;

class Advertisement_Activator {

    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ── Pricing Plans ────────────────────────────────────
        $sql1 = "CREATE TABLE {$wpdb->prefix}adv_plans (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            price decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'gbp',
            duration_days int(11) NOT NULL DEFAULT 30,
            features longtext,
            stripe_product_id varchar(255),
            stripe_price_id varchar(255),
            is_featured tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset;";

        // ── Coupons ──────────────────────────────────────────
        $sql2 = "CREATE TABLE {$wpdb->prefix}adv_coupons (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            code varchar(100) NOT NULL UNIQUE,
            trial_days int(11) NOT NULL DEFAULT 15,
            max_uses int(11) DEFAULT NULL,
            used_count int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset;";

        // ── Coupon Usage ─────────────────────────────────────
        $sql3 = "CREATE TABLE {$wpdb->prefix}adv_coupon_usage (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            coupon_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            used_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset;";

        // ── Bookings (for when Stripe module is built) ───────
        $sql4 = "CREATE TABLE {$wpdb->prefix}adv_bookings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            plan_id bigint(20) NOT NULL,
            listing_id bigint(20) DEFAULT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            status varchar(20) DEFAULT 'pending',
            stripe_subscription_id varchar(255),
            stripe_customer_id varchar(255),
            trial_ends_at datetime DEFAULT NULL,
            starts_at datetime,
            ends_at datetime,
            amount decimal(10,2),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset;";

        // ── Plan History (for preserving deleted plan data) ──
        $sql5 = "CREATE TABLE {$wpdb->prefix}adv_plan_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            original_plan_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            price decimal(10,2) NOT NULL,
            currency varchar(10) DEFAULT 'gbp',
            duration_days int(11) NOT NULL DEFAULT 30,
            features longtext,
            stripe_product_id varchar(255),
            stripe_price_id varchar(255),
            is_featured tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            deleted_at datetime DEFAULT CURRENT_TIMESTAMP,
            deleted_by bigint(20),
            PRIMARY KEY  (id)
        ) $charset;";

        dbDelta( $sql1 );
        dbDelta( $sql2 );
        dbDelta( $sql3 );
        dbDelta( $sql4 );
        dbDelta( $sql5 );

        self::maybe_upgrade();

        // ── User Role & Permalinks ───────────────────────────
        ADV_User_Roles::register();
        ADV_Listing_CPT::register();
        ADV_Recipe_CPT::register();

        // Default security options
        if ( get_option( 'adv_require_email_verification' ) === false ) {
            update_option( 'adv_require_email_verification', '1' );
        }
        if ( get_option( 'adv_registration_rate_limit' ) === false ) {
            update_option( 'adv_registration_rate_limit', 5 );
        }

        if ( class_exists( 'ADV_Security' ) ) {
            ADV_Security::cleanup_polluted_capabilities();
            update_option( 'adv_security_caps_cleaned_v1', 1, true );
        }

        flush_rewrite_rules();
    }

    /**
     * Ensure database columns are up-to-date for existing installations
     */
    public static function maybe_upgrade() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'adv_bookings';
        $column = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_name}` LIKE 'quantity'" );
        if ( empty( $column ) ) {
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `quantity` int(11) NOT NULL DEFAULT 1 AFTER `listing_id`" );
        }
        $wpdb->query( "UPDATE `{$table_name}` SET `quantity` = 1 WHERE `quantity` IS NULL OR `quantity` = 0" );

        if ( get_option( 'adv_require_email_verification' ) === false ) {
            update_option( 'adv_require_email_verification', '1' );
        }
        if ( get_option( 'adv_registration_rate_limit' ) === false ) {
            update_option( 'adv_registration_rate_limit', 5 );
        }
    }
}