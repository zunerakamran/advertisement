<?php
defined( 'ABSPATH' ) || exit;

/**
 * Security hardening: REST lockdown, registration rate limits,
 * disposable-email blocking, Turnstile CAPTCHA, and capability cleanup.
 */
class ADV_Security {

    const OPTION_TURNSTILE_SITE   = 'adv_turnstile_site_key';
    const OPTION_TURNSTILE_SECRET = 'adv_turnstile_secret_key';
    const OPTION_EMAIL_VERIFY     = 'adv_require_email_verification';
    const OPTION_RATE_LIMIT       = 'adv_registration_rate_limit';
    const META_EMAIL_VERIFIED     = '_adv_email_verified';
    const META_VERIFY_TOKEN       = '_adv_email_verify_token';
    const META_VERIFY_EXPIRES     = '_adv_email_verify_expires';

    public function __construct() {
        add_filter( 'rest_pre_dispatch', [ $this, 'block_rest_content_writes' ], 10, 3 );
        add_action( 'init', [ $this, 'maybe_cleanup_polluted_caps' ], 5 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_turnstile' ] );
    }

    // ─── Settings helpers ────────────────────────────────────

    public static function turnstile_enabled() {
        return self::get_turnstile_site_key() && self::get_turnstile_secret_key();
    }

    public static function get_turnstile_site_key() {
        return trim( (string) get_option( self::OPTION_TURNSTILE_SITE, '' ) );
    }

    public static function get_turnstile_secret_key() {
        return trim( (string) get_option( self::OPTION_TURNSTILE_SECRET, '' ) );
    }

    public static function email_verification_enabled() {
        return get_option( self::OPTION_EMAIL_VERIFY, '1' ) === '1';
    }

    public static function registration_rate_limit() {
        $limit = absint( get_option( self::OPTION_RATE_LIMIT, 5 ) );
        return max( 1, min( 50, $limit ?: 5 ) );
    }

    // ─── Block REST API content creation for non-admins ──────

    public function block_rest_content_writes( $result, $server, $request ) {
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( current_user_can( 'manage_options' ) ) {
            return $result;
        }

        $method = strtoupper( $request->get_method() );
        if ( ! in_array( $method, [ 'POST', 'PUT', 'PATCH', 'DELETE' ], true ) ) {
            return $result;
        }

        $route = $request->get_route();

        // Allow users to update their own profile only
        if ( preg_match( '#^/wp/v2/users/me/?$#', $route ) ) {
            return $result;
        }

        // Block ALL content writes via core REST for non-admins:
        // posts, pages, media, featured_recipe, admin Recipe CPT, listings, etc.
        if ( preg_match( '#^/wp/v2/#', $route ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You are not allowed to create or modify content via the API.', 'advertisement' ),
                [ 'status' => 403 ]
            );
        }

        return $result;
    }

    // ─── Scoped media upload capability (no edit/publish posts) ─

    /**
     * Whether the current request is a legitimate plugin media context.
     * Never grants edit_posts / publish_posts.
     */
    public static function is_plugin_upload_context() {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        // Never elevate capabilities for REST API requests
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return false;
        }

        if ( wp_doing_ajax() ) {
            $action  = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
            $allowed = [
                'upload-attachment',
                'query-attachments',
                'send-attachment-to-editor',
                'get-attachment',
                'save-attachment',
                'save-attachment-compat',
            ];
            return in_array( $action, $allowed, true );
        }

        // Plugin form submissions that upload files server-side
        $form_nonces = [
            'adv_blog_nonce',
            'adv_recipe_nonce',
            'adv_listing_nonce',
            'adv_banner_nonce',
            'adv_magazine_nonce',
        ];
        foreach ( $form_nonces as $key ) {
            if ( ! empty( $_POST[ $key ] ) ) {
                return true;
            }
        }

        // Frontend blog editor (Add Media button needs upload_files)
        if ( ! is_admin() ) {
            global $post;
            if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'goss_add_blog' ) ) {
                return true;
            }
        }

        return false;
    }

    // ─── Registration rate limiting ──────────────────────────

    public static function get_client_ip() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        return $ip ?: '0.0.0.0';
    }

    public static function is_registration_rate_limited() {
        $ip    = self::get_client_ip();
        $key   = 'adv_reg_' . md5( $ip );
        $count = (int) get_transient( $key );
        return $count >= self::registration_rate_limit();
    }

    public static function bump_registration_attempt() {
        $ip    = self::get_client_ip();
        $key   = 'adv_reg_' . md5( $ip );
        $count = (int) get_transient( $key );
        set_transient( $key, $count + 1, HOUR_IN_SECONDS );
    }

    // ─── Disposable email check ──────────────────────────────

    public static function is_disposable_email( $email ) {
        $email = strtolower( trim( $email ) );
        $parts = explode( '@', $email );
        if ( count( $parts ) !== 2 ) {
            return false;
        }
        $domain = $parts[1];

        $blocked = [
            'mailinator.com', 'guerrillamail.com', 'guerrillamailblock.com',
            'sharklasers.com', 'grr.la', 'guerrillamail.info', 'guerrillamail.net',
            'guerrillamail.org', 'spam4.me', 'trashmail.com', 'trashmail.me',
            'yopmail.com', 'yopmail.fr', 'tempmail.com', 'temp-mail.org',
            'temp-mail.io', '10minutemail.com', '10minemail.com', 'throwaway.email',
            'fakeinbox.com', 'maildrop.cc', 'dispostable.com', 'mailnesia.com',
            'getnada.com', 'emailondeck.com', 'moakt.com', 'tmpmail.org',
            'tmpmail.net', 'discard.email', 'mailcatch.com', 'mailnull.com',
        ];

        /**
         * Filter disposable email domains blocked at registration.
         *
         * @param string[] $blocked Domain list.
         */
        $blocked = apply_filters( 'adv_disposable_email_domains', $blocked );

        return in_array( $domain, $blocked, true );
    }

    // ─── Cloudflare Turnstile ────────────────────────────────

    public function enqueue_turnstile() {
        if ( ! self::turnstile_enabled() ) {
            return;
        }
        if ( ! is_singular() ) {
            return;
        }
        global $post;
        if ( ! $post instanceof WP_Post ) {
            return;
        }
        if ( ! has_shortcode( $post->post_content, 'goss_register' ) ) {
            return;
        }
        wp_enqueue_script(
            'cf-turnstile',
            'https://challenges.cloudflare.com/turnstile/v0/api.js',
            [],
            null,
            true
        );
    }

    public static function verify_turnstile( $token ) {
        if ( ! self::turnstile_enabled() ) {
            return true; // Not configured — skip
        }

        $token = is_string( $token ) ? trim( $token ) : '';
        if ( $token === '' ) {
            return false;
        }

        $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'timeout' => 10,
            'body'    => [
                'secret'   => self::get_turnstile_secret_key(),
                'response' => $token,
                'remoteip' => self::get_client_ip(),
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return ! empty( $body['success'] );
    }

    // ─── Email verification ──────────────────────────────────

    public static function is_user_email_verified( $user_id ) {
        if ( ! self::email_verification_enabled() ) {
            return true;
        }
        $status = get_user_meta( $user_id, self::META_EMAIL_VERIFIED, true );
        // Empty = legacy account (grandfathered as verified)
        if ( $status === '' || $status === false ) {
            return true;
        }
        return $status === '1' || $status === 1;
    }

    public static function mark_unverified_and_send( $user_id ) {
        $token = wp_generate_password( 32, false );
        update_user_meta( $user_id, self::META_EMAIL_VERIFIED, '0' );
        update_user_meta( $user_id, self::META_VERIFY_TOKEN, $token );
        update_user_meta( $user_id, self::META_VERIFY_EXPIRES, time() + DAY_IN_SECONDS );

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $verify_url = add_query_arg( [
            'adv_verify_email' => 1,
            'uid'              => $user_id,
            'token'            => $token,
        ], home_url( '/' ) );

        $site_name = 'The Goss';
        $subject   = "Verify your {$site_name} email address";
        $content   = '
        <h2>Confirm your email</h2>
        <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ',</p>
        <p>Thanks for registering. Please verify your email address to activate your advertiser account.</p>
        <p style="margin-top:24px; text-align:center;">
            <a href="' . esc_url( $verify_url ) . '" class="btn-primary">Verify Email Address</a>
        </p>
        <p style="margin-top:16px;font-size:13px;color:#666;">This link expires in 24 hours. If you did not create an account, you can ignore this email.</p>';

        if ( class_exists( 'ADV_Emails' ) ) {
            ADV_Emails::send( $user->user_email, $subject, $content );
        } else {
            wp_mail( $user->user_email, $subject, wp_strip_all_tags( $content ) );
        }
    }

    public static function verify_email_token( $user_id, $token ) {
        $user_id = absint( $user_id );
        $token   = sanitize_text_field( $token );
        if ( ! $user_id || $token === '' ) {
            return new WP_Error( 'invalid', 'Invalid verification link.' );
        }

        $stored  = (string) get_user_meta( $user_id, self::META_VERIFY_TOKEN, true );
        $expires = (int) get_user_meta( $user_id, self::META_VERIFY_EXPIRES, true );

        if ( ! $stored || ! hash_equals( $stored, $token ) ) {
            return new WP_Error( 'invalid', 'Invalid or already used verification link.' );
        }

        if ( $expires && time() > $expires ) {
            return new WP_Error( 'expired', 'This verification link has expired. Please register again or request a new link.' );
        }

        update_user_meta( $user_id, self::META_EMAIL_VERIFIED, '1' );
        delete_user_meta( $user_id, self::META_VERIFY_TOKEN );
        delete_user_meta( $user_id, self::META_VERIFY_EXPIRES );

        return true;
    }

    // ─── Clean polluted capabilities from past bug ───────────

    public function maybe_cleanup_polluted_caps() {
        if ( get_option( 'adv_security_caps_cleaned_v1' ) ) {
            return;
        }
        self::cleanup_polluted_capabilities();
        update_option( 'adv_security_caps_cleaned_v1', 1, true );
    }

    public static function cleanup_polluted_capabilities() {
        $dangerous = [ 'edit_posts', 'publish_posts', 'edit_others_posts', 'delete_posts', 'delete_others_posts' ];

        // Advertiser role should only have read (+ upload never permanently)
        $advertiser = get_role( 'advertiser' );
        if ( $advertiser ) {
            foreach ( array_merge( $dangerous, [ 'upload_files' ] ) as $cap ) {
                if ( $advertiser->has_cap( $cap ) ) {
                    $advertiser->remove_cap( $cap );
                }
            }
            if ( ! $advertiser->has_cap( 'read' ) ) {
                $advertiser->add_cap( 'read' );
            }
        }

        // Remove user-level caps that bypass_upload_check permanently added
        $user_ids = get_users( [
            'role'   => 'advertiser',
            'fields' => 'ID',
        ] );

        foreach ( $user_ids as $user_id ) {
            $user = new WP_User( $user_id );
            foreach ( array_merge( $dangerous, [ 'upload_files' ] ) as $cap ) {
                // Only remove if stored as user-level meta cap
                if ( isset( $user->caps[ $cap ] ) && $user->caps[ $cap ] ) {
                    $user->remove_cap( $cap );
                }
            }
            // Grandfather existing accounts as email-verified
            if ( get_user_meta( $user_id, self::META_EMAIL_VERIFIED, true ) === '' ) {
                update_user_meta( $user_id, self::META_EMAIL_VERIFIED, '1' );
            }
        }

        // Do not strip upload_files from author/editor — those are legitimate WP roles.
        // But stop the plugin from having permanently granted it to subscriber/contributor/customer.
        foreach ( [ 'subscriber', 'contributor', 'customer' ] as $role_name ) {
            $role = get_role( $role_name );
            if ( $role && $role->has_cap( 'upload_files' ) ) {
                $role->remove_cap( 'upload_files' );
            }
        }
    }
}
