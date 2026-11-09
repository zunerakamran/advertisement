<?php
defined( 'ABSPATH' ) || exit;

class ADV_Coupons {

    private static $table;
    private static $usage_table;

    public static function get_table() {
        global $wpdb;
        if ( empty( self::$table ) ) {
            self::$table = $wpdb->prefix . 'adv_coupons';
        }
        return self::$table;
    }

    public static function get_usage_table() {
        global $wpdb;
        if ( empty( self::$usage_table ) ) {
            self::$usage_table = $wpdb->prefix . 'adv_coupon_usage';
        }
        return self::$usage_table;
    }

    public static function init() {
        global $wpdb;
        $table       = self::get_table();
        $usage_table = self::get_usage_table();

        if ( is_admin() ) {
            $table_exists       = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
            $usage_table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $usage_table ) ) === $usage_table;

            if ( ! $table_exists || ! $usage_table_exists ) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                $charset = $wpdb->get_charset_collate();

                $sql2 = "CREATE TABLE {$table} (
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

                $sql3 = "CREATE TABLE {$usage_table} (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    coupon_id bigint(20) NOT NULL,
                    user_id bigint(20) NOT NULL,
                    used_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id)
                ) $charset;";

                dbDelta( $sql2 );
                dbDelta( $sql3 );
            }
        }
    }

    public static function get_all() {
        global $wpdb;
        $table = self::get_table();
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" ) ?: [];
    }

    public static function get_by_code( $code ) {
        global $wpdb;
        $table = self::get_table();
        $code  = strtoupper( trim( sanitize_text_field( $code ) ) );
        if ( empty( $code ) ) {
            return null;
        }
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE code = %s", $code
        ) );
    }

    public static function get_by_id( $id ) {
        global $wpdb;
        $table = self::get_table();
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d", intval( $id )
        ) );
    }

    public static function create( $data ) {
        global $wpdb;
        $code       = strtoupper( trim( sanitize_text_field( $data['code'] ?? '' ) ) );
        $trial_days = intval( $data['trial_days'] ?? 15 );
        $max_uses   = ( isset( $data['max_uses'] ) && '' !== $data['max_uses'] && null !== $data['max_uses'] ) ? intval( $data['max_uses'] ) : null;
        
        $expires_at = null;
        if ( ! empty( $data['expires_at'] ) ) {
            $timestamp = strtotime( $data['expires_at'] );
            if ( $timestamp ) {
                $expires_at = date( 'Y-m-d H:i:s', $timestamp );
            }
        }

        if ( empty( $code ) ) {
            return false;
        }

        return $wpdb->insert( self::get_table(), [
            'code'       => $code,
            'trial_days' => $trial_days,
            'max_uses'   => $max_uses,
            'used_count' => 0,
            'is_active'  => 1,
            'expires_at' => $expires_at,
        ] );
    }

    public static function delete( $id ) {
        global $wpdb;
        $coupon_id = intval( $id );
        if ( ! $coupon_id ) {
            return false;
        }

        $wpdb->delete( self::get_usage_table(), [ 'coupon_id' => $coupon_id ] );
        return $wpdb->delete( self::get_table(), [ 'id' => $coupon_id ] );
    }

    public static function validate( $code, $user_id = 0 ) {
        global $wpdb;

        $code = strtoupper( trim( sanitize_text_field( $code ) ) );
        if ( empty( $code ) ) {
            return [
                'valid'   => false,
                'message' => 'Please enter a coupon code.',
                'coupon'  => null,
            ];
        }

        $coupon = self::get_by_code( $code );
        if ( ! $coupon ) {
            return [
                'valid'   => false,
                'message' => 'Invalid coupon code.',
                'coupon'  => null,
            ];
        }

        if ( empty( $coupon->is_active ) ) {
            return [
                'valid'   => false,
                'message' => 'This coupon is no longer active.',
                'coupon'  => $coupon,
            ];
        }

        if ( ! empty( $coupon->expires_at ) && strtotime( $coupon->expires_at ) < current_time( 'timestamp' ) ) {
            return [
                'valid'   => false,
                'message' => 'This coupon has expired.',
                'coupon'  => $coupon,
            ];
        }

        if ( ! is_null( $coupon->max_uses ) && intval( $coupon->max_uses ) > 0 && intval( $coupon->used_count ) >= intval( $coupon->max_uses ) ) {
            return [
                'valid'   => false,
                'message' => 'This coupon has reached its maximum usage limit.',
                'coupon'  => $coupon,
            ];
        }

        if ( $user_id > 0 ) {
            $already_used = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::get_usage_table() . " WHERE coupon_id = %d AND user_id = %d",
                $coupon->id,
                $user_id
            ) );
            if ( intval( $already_used ) > 0 ) {
                return [
                    'valid'   => false,
                    'message' => 'You have already used this coupon.',
                    'coupon'  => $coupon,
                ];
            }
        }

        return [
            'valid'   => true,
            'message' => sprintf( 'Coupon applied! You get %d days free trial.', $coupon->trial_days ),
            'coupon'  => $coupon,
        ];
    }

    public static function mark_used( $coupon_id, $user_id = 0 ) {
        global $wpdb;
        $coupon_id = intval( $coupon_id );
        if ( ! $coupon_id ) {
            return false;
        }

        $wpdb->query( $wpdb->prepare(
            "UPDATE " . self::get_table() . " SET used_count = used_count + 1 WHERE id = %d",
            $coupon_id
        ) );

        if ( $user_id > 0 ) {
            $wpdb->insert( self::get_usage_table(), [
                'coupon_id' => $coupon_id,
                'user_id'   => intval( $user_id ),
                'used_at'   => current_time( 'mysql' ),
            ] );
        }

        return true;
    }
}
