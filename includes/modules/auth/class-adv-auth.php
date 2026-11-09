<?php
defined( 'ABSPATH' ) || exit;

class ADV_Auth {

    public function __construct() {
        add_action( 'init', [ $this, 'handle_login' ] );
        add_action( 'init', [ $this, 'handle_register' ] );
        add_action( 'init', [ $this, 'handle_logout' ] );
        add_action( 'init', [ $this, 'handle_email_verification' ] );
        add_shortcode( 'goss_login',    [ $this, 'render_login_form' ] );
        add_shortcode( 'goss_register', [ $this, 'render_register_form' ] );

        // Hide top WordPress admin bar for non-admins
        add_filter( 'show_admin_bar', [ $this, 'hide_admin_bar_for_non_admins' ] );

        // Redirect non-admins away from /wp-admin/ to front-end dashboard
        add_action( 'admin_init', [ $this, 'restrict_admin_access' ] );

        // Redirect non-logged-in users away from dashboard to login
        add_action( 'template_redirect', [ $this, 'restrict_dashboard_access' ] );

        // Block unverified advertisers from staying logged in
        add_action( 'wp_login', [ $this, 'block_unverified_login' ], 10, 2 );
    }

    public function hide_admin_bar_for_non_admins( $show ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        return $show;
    }

    public function restrict_admin_access() {
        if ( ! current_user_can( 'manage_options' ) && ! wp_doing_ajax() ) {
            wp_safe_redirect( home_url( '/dashboard/' ) );
            exit;
        }
    }

    public function restrict_dashboard_access() {
        if ( ! is_user_logged_in() && ! wp_doing_ajax() ) {
            if ( is_page( 'dashboard' ) || get_query_var( 'pagename' ) === 'dashboard' ) {
                wp_safe_redirect( home_url( '/login/' ) );
                exit;
            }
        }
    }

    // ─── LOGIN ───────────────────────────────────────────────

    public function handle_login() {
        if ( ! isset( $_POST['adv_login_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['adv_login_nonce'], 'adv_login' ) ) return;

        $username = sanitize_user( $_POST['adv_username'] ?? '' );
        $password = $_POST['adv_password'] ?? '';
        $remember = isset( $_POST['adv_remember'] );

        if ( empty( $username ) || empty( $password ) ) {
            adv_set_flash( 'login_error', 'Please enter your username and password.' );
            return;
        }

        // Resolve user before signon so we can check email verification
        $user_check = strpos( $username, '@' ) !== false
            ? get_user_by( 'email', $username )
            : get_user_by( 'login', $username );

        if ( $user_check && class_exists( 'ADV_Security' ) && ! ADV_Security::is_user_email_verified( $user_check->ID ) ) {
            // Still verify password so we don't leak "account exists but unverified" vs wrong password too obviously —
            // but we block login either way after a successful credential check below.
        }

        $user = wp_signon([
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ], is_ssl() );

        if ( is_wp_error( $user ) ) {
            adv_set_flash( 'login_error', 'Invalid username or password.' );
            return;
        }

        if ( class_exists( 'ADV_Security' ) && ! ADV_Security::is_user_email_verified( $user->ID ) ) {
            wp_logout();
            adv_set_flash( 'login_error', 'Please verify your email address before logging in. Check your inbox for the verification link.' );
            wp_safe_redirect( home_url( '/login/' ) );
            exit;
        }

        $redirect = $_POST['adv_redirect'] ?? home_url( '/dashboard/' );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Extra safety if something else logs the user in.
     */
    public function block_unverified_login( $user_login, $user ) {
        if ( ! $user instanceof WP_User ) {
            return;
        }
        if ( user_can( $user, 'manage_options' ) ) {
            return;
        }
        if ( class_exists( 'ADV_Security' ) && ! ADV_Security::is_user_email_verified( $user->ID ) ) {
            wp_clear_auth_cookie();
        }
    }

    public function render_login_form( $atts ) {
        if ( is_user_logged_in() ) {
            wp_safe_redirect( home_url( '/dashboard/' ) );
            exit;
        }
        $error    = adv_get_flash( 'login_error' );
        $success  = adv_get_flash( 'login_success' );
        $redirect = $_GET['redirect_to'] ?? home_url( '/dashboard/' );
        ob_start();
        include ADV_PATH . 'public/views/auth/login-form.php';
        return ob_get_clean();
    }

    // ─── REGISTER ────────────────────────────────────────────

    public function handle_register() {
        if ( ! isset( $_POST['adv_register_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['adv_register_nonce'], 'adv_register' ) ) return;

        // Honeypot spam check
        if ( ! empty( $_POST['adv_website'] ) ) return;

        $username = sanitize_user( $_POST['adv_username'] ?? '' );
        $email    = sanitize_email( $_POST['adv_email'] ?? '' );
        $password = $_POST['adv_password'] ?? '';
        $confirm  = $_POST['adv_confirm_password'] ?? '';

        $flash_old = function() use ( $username, $email ) {
            adv_set_flash( 'register_old', compact( 'username', 'email' ) );
        };

        // Count every real form attempt (after honeypot) toward the IP rate limit
        if ( class_exists( 'ADV_Security' ) ) {
            if ( ADV_Security::is_registration_rate_limited() ) {
                adv_set_flash( 'register_error', 'Too many registration attempts from your network. Please try again in an hour.' );
                $flash_old();
                return;
            }
            ADV_Security::bump_registration_attempt();
        }

        // Turnstile CAPTCHA (when configured)
        if ( class_exists( 'ADV_Security' ) && ADV_Security::turnstile_enabled() ) {
            $token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
            if ( ! ADV_Security::verify_turnstile( $token ) ) {
                adv_set_flash( 'register_error', 'CAPTCHA verification failed. Please try again.' );
                $flash_old();
                return;
            }
        }

        // Validation
        if ( empty( $username ) || empty( $email ) || empty( $password ) ) {
            adv_set_flash( 'register_error', 'Please fill in all required fields.' );
            $flash_old();
            return;
        }

        if ( ! validate_username( $username ) ) {
            adv_set_flash( 'register_error', 'Username may only contain letters, numbers, and underscores.' );
            $flash_old();
            return;
        }

        if ( username_exists( $username ) ) {
            adv_set_flash( 'register_error', 'That username is already taken. Please choose another.' );
            $flash_old();
            return;
        }

        if ( ! is_email( $email ) ) {
            adv_set_flash( 'register_error', 'Please enter a valid email address.' );
            $flash_old();
            return;
        }

        if ( class_exists( 'ADV_Security' ) && ADV_Security::is_disposable_email( $email ) ) {
            adv_set_flash( 'register_error', 'Please use a permanent email address. Temporary email services are not allowed.' );
            $flash_old();
            return;
        }

        if ( strlen( $password ) < 8 ) {
            adv_set_flash( 'register_error', 'Password must be at least 8 characters.' );
            $flash_old();
            return;
        }

        if ( $password !== $confirm ) {
            adv_set_flash( 'register_error', 'Passwords do not match.' );
            $flash_old();
            return;
        }

        if ( email_exists( $email ) ) {
            adv_set_flash( 'register_error', 'An account with this email already exists.' );
            $flash_old();
            return;
        }

        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            adv_set_flash( 'register_error', $user_id->get_error_message() );
            return;
        }

        // Set role
        wp_update_user([
            'ID'   => $user_id,
            'role' => 'advertiser',
        ]);

        // Fire hook so email module can listen (welcome + admin notice)
        do_action( 'adv_user_registered', $user_id );

        $require_verify = class_exists( 'ADV_Security' ) && ADV_Security::email_verification_enabled();

        if ( $require_verify ) {
            ADV_Security::mark_unverified_and_send( $user_id );
            adv_set_flash( 'login_success', 'Account created! Please check your email and click the verification link before logging in.' );
            wp_safe_redirect( home_url( '/login/' ) );
            exit;
        }

        // Auto login when email verification is disabled
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        wp_safe_redirect( home_url( '/dashboard/' ) );
        exit;
    }

    public function handle_email_verification() {
        if ( empty( $_GET['adv_verify_email'] ) ) {
            return;
        }

        $uid   = isset( $_GET['uid'] ) ? absint( $_GET['uid'] ) : 0;
        $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

        if ( ! class_exists( 'ADV_Security' ) ) {
            return;
        }

        $result = ADV_Security::verify_email_token( $uid, $token );

        if ( is_wp_error( $result ) ) {
            adv_set_flash( 'login_error', $result->get_error_message() );
            wp_safe_redirect( home_url( '/login/' ) );
            exit;
        }

        adv_set_flash( 'login_success', 'Email verified successfully! You can now log in.' );
        wp_safe_redirect( home_url( '/login/' ) );
        exit;
    }

    public function render_register_form( $atts ) {
        if ( is_user_logged_in() ) {
            return '<p>You already have an account. <a href="' . home_url( '/dashboard/' ) . '">Go to dashboard</a></p>';
        }
        $error = adv_get_flash( 'register_error' );
        $old   = adv_get_flash( 'register_old' ) ?? [];
        ob_start();
        include ADV_PATH . 'public/views/auth/register-form.php';
        return ob_get_clean();
    }

    // ─── LOGOUT ──────────────────────────────────────────────

    public function handle_logout() {
        if ( ! isset( $_GET['adv_logout'] ) ) return;
        if ( ! wp_verify_nonce( $_GET['adv_logout'], 'adv_logout' ) ) return;

        wp_logout();
        wp_safe_redirect( home_url( '/login/' ) );
        exit;
    }

    // ─── HELPERS ─────────────────────────────────────────────

    private function unique_username( $base ) {
        $username = $base;
        $i = 1;
        while ( username_exists( $username ) ) {
            $username = $base . $i;
            $i++;
        }
        return $username;
    }
}
