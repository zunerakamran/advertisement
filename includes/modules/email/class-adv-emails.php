<?php
defined( 'ABSPATH' ) || exit;

class ADV_Emails {

    public function __construct() {
        // User registration
        add_action( 'adv_user_registered', [ $this, 'on_user_registered' ] );

        // Payment / Subscription confirmed
        add_action( 'adv_payment_confirmed', [ $this, 'on_payment_confirmed' ], 10, 2 );

        // Payment renewal success
        add_action( 'adv_payment_renewal_success', [ $this, 'on_payment_renewal_success' ], 10, 1 );

        // Payment failed
        add_action( 'adv_payment_failed', [ $this, 'on_payment_failed' ], 10, 1 );

        // Listing submitted or updated
        add_action( 'adv_listing_submitted', [ $this, 'on_listing_submitted' ], 10, 2 );
        add_action( 'adv_listing_updated',   [ $this, 'on_listing_updated' ], 10, 2 );

        // Listing deleted/trashed
        add_action( 'adv_listing_deleted', [ $this, 'on_listing_deleted' ], 10, 2 );

        // Listing approved/published by admin
        add_action( 'transition_post_status', [ $this, 'on_listing_status_changed' ], 10, 3 );

        // Subscription cancelled / unsubscribed
        add_action( 'adv_subscription_cancelled', [ $this, 'on_subscription_cancelled' ] );

        add_action( 'adv_recipe_submitted',  [ $this, 'on_recipe_submitted' ], 10, 2 );
        add_action( 'transition_post_status', [ $this, 'on_recipe_status_changed' ], 10, 3 );

        add_action( 'adv_blog_submitted',   [ $this, 'on_blog_submitted' ], 10, 2 );
        add_action( 'transition_post_status', [ $this, 'on_blog_status_changed' ], 10, 3 );

        add_action( 'adv_magazine_submitted', [ $this, 'on_magazine_submitted' ], 10, 2 );
        add_action( 'adv_magazine_approved',  [ $this, 'on_magazine_approved' ],  10, 3 );

        add_action( 'adv_banner_submitted',   [ $this, 'on_banner_submitted' ], 10, 2 );
        add_action( 'transition_post_status', [ $this, 'on_banner_status_changed' ], 10, 3 );
        add_action( 'adv_banner_renewed',     [ $this, 'on_banner_renewed' ], 10, 2 );
        add_action( 'adv_banner_expired',     [ $this, 'on_banner_expired' ], 10, 2 );
    }

    // ─── 1. USER REGISTERED ────────────────────────────────────

    public function on_user_registered( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        $admin_email = get_option( 'adv_admin_email', get_option( 'admin_email' ) );
        $site_name   = 'The Goss';

        // Email to User
        $user_subject = "Welcome to {$site_name}!";
        $user_content = '
        <div class="icon-header">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" fill="#A07949"/>
                <path d="M12 7V12L15 15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h2>Welcome, ' . esc_html( $user->display_name ?: $user->user_login ) . '!</h2>
        <p>Thank you for creating an account on <strong>' . esc_html( $site_name ) . '</strong>. Please verify your email if prompted, then choose a membership plan and list your business in our directory.</p>
        <div class="info-box">
            <p style="margin:0;"><strong>Your Account Details</strong><br>
            Username: <code>' . esc_html( $user->user_login ) . '</code><br>
            Email: ' . esc_html( $user->user_email ) . '</p>
        </div>
        <p style="margin-top:24px; text-align:center;">
            <a href="' . esc_url( home_url( '/pricing/' ) ) . '" class="btn-primary">Choose a Pricing Plan</a>
        </p>';

        self::send( $user->user_email, $user_subject, $user_content );

        // Email to Admin
        $admin_subject = "[{$site_name}] New User Registered: " . $user->user_login;
        $admin_content = '
        <h2>New User Registration</h2>
        <p>A new user has registered on your directory website.</p>
        <div class="info-box">
            <p style="margin:0;"><strong>User Details</strong><br>
            Username: ' . esc_html( $user->user_login ) . '<br>
            Email: ' . esc_html( $user->user_email ) . '<br>
            Date: ' . esc_html( current_time( 'j M Y, g:i a' ) ) . '</p>
        </div>';

        self::send( $admin_email, $admin_subject, $admin_content );
    }

    // ─── 2. PAYMENT CONFIRMED ──────────────────────────────────

    public function on_payment_confirmed( $user_id, $plan_id ) {
        $user = get_userdata( $user_id );
        $plan = ADV_Plans::get( $plan_id );
        if ( ! $user || ! $plan ) return;

        // Get booking record for dates
        global $wpdb;
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}adv_bookings WHERE user_id = %d AND plan_id = %d ORDER BY id DESC LIMIT 1",
            $user_id,
            $plan_id
        ));

        $admin_email = get_option( 'adv_admin_email', get_option( 'admin_email' ) );
        $site_name   = 'The Goss';

        // Calculate next billing date
        $next_billing_date = '';
        if ( $booking && $plan->duration_days ) {
            $starts_at = $booking->starts_at ? strtotime( $booking->starts_at ) : current_time( 'timestamp' );
            $next_billing_timestamp = strtotime( '+' . $plan->duration_days . ' days', $starts_at );
            $next_billing_date = date( 'j M Y', $next_billing_timestamp );
        }

        // Format billing cycle
        $billing_cycle = $plan->duration_days == 30 ? 'Monthly' : 
                       ( $plan->duration_days == 365 ? 'Yearly' : 
                       ( $plan->duration_days == 7 ? 'Weekly' : 
                       'Every ' . $plan->duration_days . ' days' ) );

        // Email to User
        $user_subject = "Payment Confirmed — {$plan->name} Membership";
        $user_content = '
        <div class="icon-header">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="2" y="5" width="20" height="14" rx="2" stroke="#A07949" stroke-width="2" fill="none"/>
                <path d="M2 10h20" stroke="#A07949" stroke-width="2"/>
                <circle cx="6" cy="15" r="1" fill="#A07949"/>
                <circle cx="10" cy="15" r="1" fill="#A07949"/>
            </svg>
        </div>
        <h2>Subscription Active!</h2>
        <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ', your payment for the <strong>' . esc_html( $plan->name ) . '</strong> plan has been processed successfully.</p>
        <div class="info-box">
            <p style="margin:0;"><strong>Plan:</strong> ' . esc_html( $plan->name ) . '<br>
            <strong>Price:</strong> £' . number_format( (float) $plan->price, 2 ) . '<br>
            <strong>Billing Cycle:</strong> ' . esc_html( $billing_cycle ) . '<br>
            <strong>Status:</strong> <span style="color:#16a34a; font-weight:700;">Active</span>';
        if ( $next_billing_date ) {
            $user_content .= '<br><strong>Next Billing Date:</strong> ' . esc_html( $next_billing_date );
        }
        $user_content .= '</p>
        </div>
        <p>You can now add your business directory listing straight away!</p>
        <p style="margin-top:24px; text-align:center;">
            <a href="' . esc_url( home_url( '/add-listing/' ) ) . '" class="btn-primary">Create Your Listing Now</a>
        </p>';

        self::send( $user->user_email, $user_subject, $user_content );

        // Email to Admin
        $admin_subject = "[{$site_name}] New Subscription: " . $plan->name . " by " . $user->user_login;
        $admin_content = '
        <h2>New Subscription Payment Received</h2>
        <p>A customer has successfully subscribed to a plan.</p>
        <div class="info-box">
            <p style="margin:0;"><strong>Customer:</strong> ' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')<br>
            <strong>Plan:</strong> ' . esc_html( $plan->name ) . '<br>
            <strong>Price:</strong> £' . number_format( (float) $plan->price, 2 ) . '<br>
            <strong>Billing Cycle:</strong> ' . esc_html( $billing_cycle ) . '<br>
            <strong>Status:</strong> Active<br>
            <strong>Date:</strong> ' . esc_html( current_time( 'j M Y, g:i a' ) );
        if ( $next_billing_date ) {
            $admin_content .= '<br><strong>Next Billing Date:</strong> ' . esc_html( $next_billing_date );
        }
        $admin_content .= '</p>
        </div>';

        self::send( $admin_email, $admin_subject, $admin_content );
    }

    // ─── 3. LISTING SUBMITTED / UPDATED ───────────────────────

    public function on_listing_submitted( $post_id, $user_id ) {
        $this->send_listing_review_emails( $post_id, $user_id, false );
    }

    public function on_listing_updated( $post_id, $user_id ) {
        $this->send_listing_review_emails( $post_id, $user_id, true );
    }

    private function send_listing_review_emails( $post_id, $user_id, $is_update = false ) {
        $user    = get_userdata( $user_id );
        $listing = get_post( $post_id );
        if ( ! $user || ! $listing ) return;

        $admin_email  = get_option( 'adv_admin_email', get_option( 'admin_email' ) );
        $site_name    = 'The Goss';
        $action_label = $is_update ? 'updated' : 'submitted';

        // Email to User
        $user_subject = "Listing Received: " . $listing->post_title;
        $user_content = '
        <div class="icon-header">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke="#A07949" stroke-width="2" fill="none"/>
                <path d="M12 6v6l4 2" stroke="#A07949" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h2>Listing Received</h2>
        <p>Hi ' . esc_html( $user->display_name ) . ', your business listing <strong>"' . esc_html( $listing->post_title ) . '"</strong> has been ' . $action_label . ' and is currently pending review by our administration team.</p>
        <p>We will let you know by email as soon as your listing has been approved and is live on the directory.</p>
        <p style="margin-top:24px; text-align:center;">
            <a href="' . esc_url( adv_get_listing_preview_url( $post_id ) ) . '" class="btn-primary">Preview Your Listing</a>
        </p>';

        self::send( $user->user_email, $user_subject, $user_content );

        // Email to Admin
        $admin_subject = "[{$site_name}] Listing Pending Approval: " . $listing->post_title;
        $edit_link     = get_edit_post_link( $post_id, '' );

        $admin_content = '
        <h2>Listing Requires Review</h2>
        <p>A business listing has been ' . $action_label . ' and requires your approval.</p>
        <div class="info-box">
            <p style="margin:0;"><strong>Listing Title:</strong> ' . esc_html( $listing->post_title ) . '<br>
            <strong>Submitted By:</strong> ' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')<br>
            <strong>Date:</strong> ' . esc_html( current_time( 'j M Y, g:i a' ) ) . '</p>
        </div>
        <p style="margin-top:24px; text-align:center;">
            <a href="' . esc_url( $edit_link ) . '" class="btn-primary">Review &amp; Approve in WP Admin</a>
        </p>';

        self::send( $admin_email, $admin_subject, $admin_content );
    }

    // ─── 4. LISTING APPROVED / PUBLISHED ──────────────────────

    public function on_listing_status_changed( $new_status, $old_status, $post ) {
        if ( $post->post_type !== 'adv_listing' ) return;
        if ( $old_status === 'publish' || $new_status !== 'publish' ) return;

        $user_id = (int) $post->post_author;
        $user    = get_userdata( $user_id );
        if ( ! $user ) return;

        $admin_email = get_option( 'adv_admin_email', get_option( 'admin_email' ) );
        $site_name    = 'The Goss';
        $permalink    = get_permalink( $post->ID );
        $edit_link    = get_edit_post_link( $post->ID, '' );

        // Email to User
        $user_subject = "Your Listing is Live: " . $post->post_title;
        $user_content = '
        <div class="icon-header">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="#A07949"/>
            </svg>
        </div>
        <h2>Congratulations! Your Listing is Approved</h2>
        <p>Hi ' . esc_html( $user->display_name ) . ', great news! Your business listing <strong>"' . esc_html( $post->post_title ) . '"</strong> has been approved by our team and is now live on <strong>' . esc_html( $site_name ) . '</strong>.</p>
        <p style="margin-top:24px; text-align:center;">
            <a href="' . esc_url( $permalink ) . '" class="btn-primary">View Your Published Listing</a>
        </p>';

        self::send( $user->user_email, $user_subject, $user_content );

        // Email to Admin
        $admin_subject = "[{$site_name}] Listing Published: " . $post->post_title;
        $admin_content = '
        <h2>Listing Published</h2>
        <p>A business listing has been approved and published.</p>
        <div class="info-box">
            <p style="margin:0;"><strong>Listing Title:</strong> ' . esc_html( $post->post_title ) . '<br>
            <strong>Owner:</strong> ' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')<br>
            <strong>Status:</strong> Published<br>
            <strong>Date:</strong> ' . esc_html( current_time( 'j M Y, g:i a' ) ) . '</p>
        </div>
        <p style="margin-top:24px; text-align:center;">
            <a href="' . esc_url( $permalink ) . '" class="btn-primary">View Listing</a>
            <a href="' . esc_url( $edit_link ) . '" class="btn-primary" style="margin-left:10px;">Edit in WP Admin</a>
        </p>';

        self::send( $admin_email, $admin_subject, $admin_content );
    }

    // ─── 5. LISTING DELETED ─────────────────────────────────────

    public function on_listing_deleted( $post_id, $user_id ) {
        $user    = get_userdata( $user_id );
        $listing = get_post( $post_id );
        if ( ! $user || ! $listing ) return;

        $admin_email = get_option( 'adv_admin_email', get_option( 'admin_email' ) );
        $site_name   = 'The Goss';

        // Email to User
        $user_subject = "Listing Deleted: " . $listing->post_title;
        $user_content = '
        <div class="icon-header">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6" stroke="#A07949" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h2>Listing Deleted</h2>
        <p>Hi ' . esc_html( $user->display_name ) . ', your business listing <strong>"' . esc_html( $listing->post_title ) . '"</strong> has been deleted from our directory.</p>
        <p>If this was done in error, please contact our support team for assistance.</p>
        <p style="margin-top:24px; text-align:center;">
            <a href="' . esc_url( home_url( '/dashboard/' ) ) . '" class="btn-primary">Go to Dashboard</a>
        </p>';

        self::send( $user->user_email, $user_subject, $user_content );

        // Email to Admin
        $admin_subject = "[{$site_name}] Listing Deleted: " . $listing->post_title;
        $admin_content = '
        <h2>Listing Deleted</h2>
        <p>A business listing has been deleted by the user.</p>
        <div class="info-box">
            <p style="margin:0;"><strong>Listing Title:</strong> ' . esc_html( $listing->post_title ) . '<br>
            <strong>Deleted By:</strong> ' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')<br>
            <strong>Date:</strong> ' . esc_html( current_time( 'j M Y, g:i a' ) ) . '</p>
        </div>';

        self::send( $admin_email, $admin_subject, $admin_content );
    }

    // ─── 6. PAYMENT RENEWAL SUCCESS ────────────────────────────

    public function on_payment_renewal_success( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        global $wpdb;
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}adv_bookings WHERE user_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1",
            $user_id
        ));

        if ( ! $booking ) return;

        $plan = ADV_Plans::get( $booking->plan_id );
        if ( ! $plan ) return;

        $admin_email = get_option( 'adv_admin_email', get_option( 'admin_email' ) );
        $site_name   = 'The Goss';

        // Email to User
        $user_subject = "Payment Renewed — {$plan->name} Membership";
        $user_content = '
        <div class="icon-header">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 4V1L8 5L12 9V6C15.31 6 18 8.69 18 12C18 15.31 15.31 18 12 18C8.69 18 6 15.31 6 12H4C4 16.42 7.58 20 12 20C16.42 20 20 16.42 20 12C20 7.58 16.42 4 12 4Z" fill="#A07949"/>
            </svg>
        </div>
        <h2>Payment Renewed Successfully</h2>
        <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ', your monthly payment for the <strong>' . esc_html( $plan->name ) . '</strong> plan has been processed successfully.</p>
        <div class="info-box">
            <p style="margin:0;"><strong>Plan:</strong> ' . esc_html( $plan->name ) . '<br>
            <strong>Price:</strong> £' . number_format( (float) $plan->price, 2 ) . '/month<br>
            <strong>Status:</strong> <span style="color:#16a34a; font-weight:700;">Active</span></p>
        </div>
        <p>Your listing quota has been renewed and your business listings remain active.</p>';

        self::send( $user->user_email, $user_subject, $user_content );

        // Email to Admin
        $admin_subject = "[{$site_name}] Payment Renewed: " . $plan->name . " by " . $user->user_login;
        $admin_content = '
        <h2>Payment Renewal Received</h2>
        <p>A customer has successfully renewed their subscription.</p>
        <div class="info-box">
            <p style="margin:0;"><strong>Customer:</strong> ' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')<br>
            <strong>Plan:</strong> ' . esc_html( $plan->name ) . ' (£' . number_format( (float) $plan->price, 2 ) . '/month)<br>
            <strong>Date:</strong> ' . esc_html( current_time( 'j M Y, g:i a' ) ) . '</p>
        </div>';

        self::send( $admin_email, $admin_subject, $admin_content );
    }

    // ─── 7. PAYMENT FAILED ───────────────────────────────────────

    public function on_payment_failed( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        global $wpdb;
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}adv_bookings WHERE user_id = %d AND status = 'suspended' ORDER BY id DESC LIMIT 1",
            $user_id
        ));

        if ( ! $booking ) return;

        $plan = ADV_Plans::get( $booking->plan_id );
        if ( ! $plan ) return;

        $admin_email = get_option( 'adv_admin_email', get_option( 'admin_email' ) );
        $site_name   = 'The Goss';

        // Email to User
        $user_subject = "Payment Failed — Action Required";
        $user_content = '
        <div class="icon-header">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke="#A07949" stroke-width="2" fill="none"/>
                <path d="M12 8v4M12 16h.01" stroke="#A07949" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h2>Payment Failed</h2>
        <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ', we were unable to process your monthly payment for the <strong>' . esc_html( $plan->name ) . '</strong> plan.</p>
        <div class="info-box">
            <p style="margin:0;"><strong>Plan:</strong> ' . esc_html( $plan->name ) . '<br>
            <strong>Amount:</strong> £' . number_format( (float) $plan->price, 2 ) . '/month<br>
            <strong>Status:</strong> <span style="color:#dc2626; font-weight:700;">Suspended</span></p>
        </div>
        <p>Your subscription has been suspended and your business listings have been taken offline. Please update your payment method or contact support to resolve this issue.</p>
        <p style="margin-top:24px; text-align:center;">
            <a href="' . esc_url( home_url( '/dashboard/?tab=billing' ) ) . '" class="btn-primary">Update Payment Method</a>
        </p>';

        self::send( $user->user_email, $user_subject, $user_content );

        // Email to Admin
        $admin_subject = "[{$site_name}] Payment Failed: " . $plan->name . " by " . $user->user_login;
        $admin_content = '
        <h2>Payment Failed</h2>
        <p>A customer\'s payment has failed and their subscription has been suspended.</p>
        <div class="info-box">
            <p style="margin:0;"><strong>Customer:</strong> ' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')<br>
            <strong>Plan:</strong> ' . esc_html( $plan->name ) . ' (£' . number_format( (float) $plan->price, 2 ) . '/month)<br>
            <strong>Date:</strong> ' . esc_html( current_time( 'j M Y, g:i a' ) ) . '</p>
        </div>';

        self::send( $admin_email, $admin_subject, $admin_content );
    }

    // ─── 8. SUBSCRIPTION CANCELLED ────────────────────────────

    public function on_subscription_cancelled( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        $admin_email = get_option( 'adv_admin_email', get_option( 'admin_email' ) );
        $site_name   = 'The Goss';

        // Email to User
        $user_subject = "Subscription Cancelled — {$site_name}";
        $user_content = '
        <div class="icon-header">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke="#A07949" stroke-width="2" fill="none"/>
                <path d="M15 9L9 15M9 9L15 15" stroke="#A07949" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h2>Subscription Cancelled</h2>
        <p>Hi ' . esc_html( $user->display_name ) . ', your directory subscription has been cancelled as requested. Your active business listings have been taken offline (suspended).</p>
        <p>If this was done in error or you wish to reactivate your listing in the future, you are welcome to resubscribe at any time from our pricing page.</p>
        <p style="margin-top:24px; text-align:center;">
            <a href="' . esc_url( home_url( '/pricing/' ) ) . '" class="btn-primary">View Pricing Plans</a>
        </p>';

        self::send( $user->user_email, $user_subject, $user_content );

        // Email to Admin
        $admin_subject = "[{$site_name}] Subscription Cancelled: " . $user->user_login;
        $admin_content = '
        <h2>Subscription Cancelled</h2>
        <p>A user has cancelled their subscription.</p>
        <div class="info-box">
            <p style="margin:0;"><strong>User:</strong> ' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')<br>
            <strong>Date:</strong> ' . esc_html( current_time( 'j M Y, g:i a' ) ) . '</p>
        </div>';

        self::send( $admin_email, $admin_subject, $admin_content );
    }

    // ─── 6. RECIPE SUBMITTED ──────────────────────────────────

    public function on_recipe_submitted( $recipe_id, $user_id ) {
        $user        = get_userdata( $user_id );
        $admin_email = get_option( 'adv_admin_email', get_option( 'admin_email' ) );
        $site_name   = 'The Goss';
        $recipe_link = admin_url( 'post.php?post=' . $recipe_id . '&action=edit' );
        $title       = get_the_title( $recipe_id );

        self::send(
            $user->user_email,
            "Your recipe has been submitted – {$site_name}",
            '<div class="icon-header">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C8.13 2 5 5.13 05 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2ZM12 11.5C10.62 11.5 9.5 10.38 9.5 9C9.5 7.62 10.62 6.5 12 6.5C13.38 6.5 14.5 7.62 14.5 9C14.5 10.38 13.38 11.5 12 11.5Z" fill="#A07949"/>
                </svg>
            </div>
            <h2>Recipe Received!</h2>
            <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ',</p>
            <p>Thank you for submitting your featured guest recipe <strong>"' . esc_html( $title ) . '"</strong> to The Goss. We are delighted to have you share your culinary creation with our community!</p>
            <p>Your payment has been successfully confirmed and your recipe is now under review by our editorial team. We carefully review each submission to ensure it meets our quality standards and aligns with our content guidelines.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>What happens next:</strong><br>
                • Our team will review your recipe within 1-2 business days<br>
                • You will receive an email notification once it is approved<br>
                • Your recipe will be featured prominently on our website<br>
                • Readers will be able to discover and try your creation</p>
            </div>
            <p>If you have any questions or need to make changes to your submission, please feel free to contact our support team.</p>'
        );

        self::send(
            $admin_email,
            "[{$site_name}] New Featured Recipe Submission: " . $title,
            '<h2>New Recipe Pending Review</h2>
            <p>A new featured guest recipe has been submitted and paid for. Please review the submission for quality and appropriateness before publishing.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>Recipe:</strong> ' . esc_html( $title ) . '<br>
                <strong>Author:</strong> ' . esc_html( $user->user_login ) . ' (' . esc_html( $user->user_email ) . ')<br>
                <strong>Submission Date:</strong> ' . esc_html( current_time( 'j M Y, g:i a' ) ) . '</p>
            </div>
            <p>Please review the recipe content, ingredients, and instructions to ensure they meet our editorial standards. Once approved, the recipe will be featured on the website for our community to enjoy.</p>
            <p style="margin-top:24px; text-align:center;">
                <a href="' . esc_url( $recipe_link ) . '" class="btn-primary">Review &amp; Approve Recipe →</a>
            </p>'
        );
    }

    // ─── 7. RECIPE APPROVED / PUBLISHED ───────────────────────

    public function on_recipe_status_changed( $new_status, $old_status, $post ) {
        if ( $post->post_type !== 'featured_recipe' ) return;
        if ( $new_status !== 'publish' || $old_status === 'publish' ) return;

        $user      = get_userdata( $post->post_author );
        $site_name = 'The Goss';
        if ( ! $user ) return;

        self::send(
            $user->user_email,
            "Your recipe is live! – {$site_name}",
            '<div class="icon-header">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="#A07949"/>
                </svg>
            </div>
            <h2>Your Recipe Is Live!</h2>
            <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ',</p>
            <p>Wonderful news! Your featured guest recipe <strong>"' . esc_html( $post->post_title ) . '"</strong> has been approved by our editorial team and is now published on The Goss.</p>
            <p>Your culinary creation is now live and ready to be discovered by our community of food enthusiasts. Readers can now view your recipe, try it at home, and share their experiences with your dish.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>Your recipe is now:</strong><br>
                • Visible to all visitors on our website<br>
                • Featured in our recipes section<br>
                • Shareable on social media platforms<br>
                • Part of our growing collection of guest recipes</p>
            </div>
            <p>Thank you for contributing to our community. We hope this is just the beginning of your culinary journey with The Goss!</p>
            <p style="margin-top:24px; text-align:center;">
                <a href="' . esc_url( get_permalink( $post->ID ) ) . '" class="btn-primary">View Your Recipe →</a>
            </p>'
        );
    }

    // ─── 8. BLOG SUBMITTED ────────────────────────────────────

    public function on_blog_submitted( $blog_id, $user_id ) {
        $user        = get_userdata( $user_id );
        $admin_email = get_option( 'adv_admin_email', get_option( 'admin_email' ) );
        $site_name   = 'The Goss';
        $blog_link   = admin_url( 'post.php?post=' . $blog_id . '&action=edit' );
        $title       = get_the_title( $blog_id );

        self::send(
            $user->user_email,
            "Your blog post has been submitted – {$site_name}",
            '<div class="icon-header">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="#A07949"/>
                </svg>
            </div>
            <h2>Blog Post Received!</h2>
            <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ',</p>
            <p>Thank you for submitting your featured blog post <strong>"' . esc_html( $title ) . '"</strong> to The Goss. We are excited to share your insights and expertise with our community!</p>
            <p>Your payment has been successfully confirmed and your blog post is now under review by our editorial team. We review each submission carefully to ensure it meets our quality standards and provides value to our readers.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>What happens next:</strong><br>
                • Our editorial team will review your post within 1-2 business days<br>
                • You will receive an email notification once it is approved<br>
                • Your blog post will be featured prominently on our website<br>
                • Readers can engage with your content through comments and shares</p>
            </div>
            <p>If you need to make any changes to your submission or have questions, please don\'t hesitate to contact our support team.</p>'
        );

        self::send(
            $admin_email,
            "[{$site_name}] New Featured Blog Submission: " . $title,
            '<h2>New Blog Pending Review</h2>
            <p>A new featured blog post has been submitted and paid for. Please review the submission for quality, accuracy, and appropriateness before publishing.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>Title:</strong> ' . esc_html( $title ) . '<br>
                <strong>Author:</strong> ' . esc_html( $user->user_login ) . ' (' . esc_html( $user->user_email ) . ')<br>
                <strong>Submission Date:</strong> ' . esc_html( current_time( 'j M Y, g:i a' ) ) . '</p>
            </div>
            <p>Please review the blog post content, check for factual accuracy, and ensure it aligns with our editorial guidelines. Once approved, the post will be featured on our website for our community to read and engage with.</p>
            <p style="margin-top:24px; text-align:center;">
                <a href="' . esc_url( $blog_link ) . '" class="btn-primary">Review &amp; Approve Blog →</a>
            </p>'
        );
    }

    // ─── 9. BLOG APPROVED / PUBLISHED ─────────────────────────

    public function on_blog_status_changed( $new_status, $old_status, $post ) {
        if ( $post->post_type !== 'featured_blog' ) return;
        if ( $new_status !== 'publish' || $old_status === 'publish' ) return;

        $user      = get_userdata( $post->post_author );
        $site_name = 'The Goss';
        if ( ! $user ) return;

        self::send(
            $user->user_email,
            "Your blog post is live! – {$site_name}",
            '<div class="icon-header">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="#A07949"/>
                </svg>
            </div>
            <h2>Your Blog Is Live!</h2>
            <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ',</p>
            <p>Excellent news! Your featured blog post <strong>"' . esc_html( $post->post_title ) . '"</strong> has been approved by our editorial team and is now published on The Goss.</p>
            <p>Your insights and expertise are now live and ready to reach our community of readers. Your post will be featured prominently on our website, allowing readers to engage with your content through comments, shares, and social media.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>Your blog post is now:</strong><br>
                • Visible to all visitors on our website<br>
                • Featured in our blog section<br>
                • Shareable across social media platforms<br>
                • Open for reader comments and engagement</p>
            </div>
            <p>Thank you for contributing your valuable content to our platform. We look forward to seeing the engagement your post receives!</p>
            <p style="margin-top:24px; text-align:center;">
                <a href="' . esc_url( get_permalink( $post->ID ) ) . '" class="btn-primary">View Your Blog Post →</a>
            </p>'
        );
    }

    // ─── 10. MAGAZINE SUBMITTED ───────────────────────────────

    public function on_magazine_submitted( $post_id, $user_id ) {
        $user        = get_userdata( $user_id );
        $admin_email = get_option( 'adv_admin_email', get_option( 'admin_email' ) );
        $site_name   = 'The Goss';
        $admin_link  = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
        $type        = get_post_meta( $post_id, '_adv_mag_type', true );
        $biz         = get_post_meta( $post_id, '_adv_mag_business', true );
        $label       = ADV_Magazine_Settings::get_label( $type );
        $price       = get_post_meta( $post_id, '_adv_mag_price', true );

        self::send(
            $user->user_email,
            "Your magazine advert has been received – {$site_name}",
            '<div class="icon-header">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 3H5C3.89 3 3 3.89 3 5V19C3 20.1 3.89 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.89 20.1 3 19 3ZM19 19H5V5H19V19ZM7 10H17V12H7V10ZM7 14H14V16H7V14ZM7 7H17V9H7V7Z" fill="#A07949"/>
                </svg>
            </div>
            <h2>Magazine Advert Received!</h2>
            <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ',</p>
            <p>Thank you for submitting your <strong>' . esc_html( $label ) . '</strong> magazine advertisement for <strong>' . esc_html( $biz ) . '</strong>. We are delighted to feature your business in our upcoming publication!</p>
            <p>Your payment of <strong>£' . number_format( (float)$price, 2 ) . '</strong> has been successfully confirmed. Our design team will now review your artwork to ensure it meets our print specifications and quality standards.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>What happens next:</strong><br>
                • Our team will review your artwork within 2-3 business days<br>
                • We will contact you if any adjustments are needed<br>
                • Once approved, your advert will be scheduled for the next magazine edition<br>
                • You will receive a digital copy and link when the magazine is published</p>
            </div>
            <p>If you have any questions about your submission or need to provide additional materials, please feel free to contact our advertising team.</p>'
        );

        self::send(
            $admin_email,
            "[{$site_name}] New Magazine Advert: {$label} — {$biz}",
            '<h2>New Magazine Advert Submission</h2>
            <p>A new magazine advertisement has been submitted and paid for. Please review the artwork and set the magazine publication details.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>Business:</strong> ' . esc_html( $biz ) . '<br>
                <strong>Ad Size:</strong> ' . esc_html( $label ) . '<br>
                <strong>Amount:</strong> £' . number_format( (float)$price, 2 ) . '<br>
                <strong>Submission Date:</strong> ' . esc_html( current_time( 'j M Y, g:i a' ) ) . '</p>
            </div>
            <p>Please review the submitted artwork for print quality, dimensions, and compliance with our advertising guidelines. Once approved, set the magazine URL and schedule the advert for the upcoming edition.</p>
            <p style="margin-top:24px; text-align:center;">
                <a href="' . esc_url( $admin_link ) . '" class="btn-primary">Review Advert &amp; Set Magazine URL →</a>
            </p>'
        );
    }

    // ─── 11. MAGAZINE APPROVED ────────────────────────────────

    public function on_magazine_approved( $post_id, $user_id, $mag_url ) {
        $user      = get_userdata( $user_id );
        $site_name = 'The Goss';
        $biz       = get_post_meta( $post_id, '_adv_mag_business', true );
        $type      = get_post_meta( $post_id, '_adv_mag_type', true );
        $label     = ADV_Magazine_Settings::get_label( $type );
        if ( ! $user ) return;

        self::send(
            $user->user_email,
            "Your magazine advert is live! – {$site_name}",
            '<div class="icon-header">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="#A07949"/>
                </svg>
            </div>
            <h2>Your Advert Is Live!</h2>
            <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ',</p>
            <p>Exciting news! Your <strong>' . esc_html( $label ) . '</strong> advertisement for <strong>' . esc_html( $biz ) . '</strong> has been approved and is now featured in the latest edition of The Goss magazine.</p>
            <p>Your advert is now live and reaching our extensive readership across the local community. The magazine has been published both in print and digitally, giving your business maximum exposure to potential customers.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>Your advert is now:</strong><br>
                • Featured in the current print edition<br>
                • Available in the digital magazine version<br>
                • Visible to thousands of local readers<br>
                • Part of our targeted local advertising network</p>
            </div>
            <p>Thank you for choosing to advertise with The Goss. We hope this campaign brings great results for your business!</p>
            <p style="margin-top:24px; text-align:center;">
                <a href="' . esc_url( $mag_url ) . '" class="btn-primary">View the Magazine →</a>
            </p>'
        );
    }

    // ─── 12. BANNER SUBMITTED ─────────────────────────────────

    public function on_banner_submitted( $post_id, $user_id ) {
        $user        = get_userdata( $user_id );
        $site_name   = 'The Goss';
        $admin_email = get_option( 'adv_admin_email', get_option( 'admin_email' ) );
        $biz         = get_post_meta( $post_id, '_adv_banner_business', true );
        $size        = get_post_meta( $post_id, '_adv_banner_size', true );
        $label       = ADV_Banner_Settings::get_label( $size );
        $price       = get_post_meta( $post_id, '_adv_banner_price', true );
        $admin_link  = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
        if ( ! $user ) return;

        self::send(
            $user->user_email,
            "Homepage Banner Submission Received – {$site_name}",
            '<div class="icon-header">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="3" width="18" height="18" rx="2" stroke="#A07949" stroke-width="2" fill="none"/>
                    <circle cx="12" cy="12" r="4" stroke="#A07949" stroke-width="2" fill="none"/>
                    <path d="M12 8V16M8 12H16" stroke="#A07949" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <h2>Banner Advert Received!</h2>
            <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ',</p>
            <p>Thank you for submitting your <strong>' . esc_html( $label ) . '</strong> homepage banner advertisement for <strong>' . esc_html( $biz ) . '</strong>. We are excited to feature your business prominently on our website!</p>
            <p>Your monthly subscription of <strong>£' . number_format( (float)$price, 2 ) . '/month</strong> has been successfully set up. Your banner will be displayed on our homepage, giving your business excellent visibility to all our visitors.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>What happens next:</strong><br>
                • Our team will review your banner within 1 business day<br>
                • We will check that your banner meets our size and quality standards<br>
                • Once approved, your banner will go live on our homepage<br>
                • Your subscription will renew automatically each month</p>
            </div>
            <p>If you need to make any changes to your banner or have questions, please contact our support team.</p>'
        );

        self::send(
            $admin_email,
            "[{$site_name}] New Homepage Banner Advert: {$biz}",
            '<h2>New Banner Advert Submission</h2>
            <p>A new homepage banner advertisement has been submitted with a monthly subscription. Please review the banner for quality and appropriateness before publishing.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>Business:</strong> ' . esc_html( $biz ) . '<br>
                <strong>Size:</strong> ' . esc_html( $label ) . '<br>
                <strong>Amount:</strong> £' . number_format( (float)$price, 2 ) . '/month<br>
                <strong>Submission Date:</strong> ' . esc_html( current_time( 'j M Y, g:i a' ) ) . '</p>
            </div>
            <p>Please review the banner image for quality, dimensions, and compliance with our advertising guidelines. Once approved, the banner will be displayed prominently on the homepage and the monthly subscription will be active.</p>
            <p style="margin-top:24px; text-align:center;">
                <a href="' . esc_url( $admin_link ) . '" class="btn-primary">Review &amp; Approve Banner Advert →</a>
            </p>'
        );
    }

    // ─── 13. BANNER APPROVED / PUBLISHED ──────────────────────

    public function on_banner_status_changed( $new_status, $old_status, $post ) {
        if ( $post->post_type !== 'adv_banner' ) return;
        if ( $new_status !== 'publish' || $old_status === 'publish' ) return;

        $user_id   = $post->post_author;
        $user      = get_userdata( $user_id );
        $site_name = 'The Goss';
        $biz       = get_post_meta( $post->ID, '_adv_banner_business', true );
        $size      = get_post_meta( $post->ID, '_adv_banner_size', true );
        $label     = ADV_Banner_Settings::get_label( $size );
        if ( ! $user ) return;

        self::send(
            $user->user_email,
            "Your Homepage Banner Advert Is Now Live! – {$site_name}",
            '<div class="icon-header">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="#A07949"/>
                </svg>
            </div>
            <h2>Your Banner Advert Is Live!</h2>
            <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ',</p>
            <p>Great news! Your <strong>' . esc_html( $label ) . '</strong> homepage banner advertisement for <strong>' . esc_html( $biz ) . '</strong> has been approved and is now live on The Goss website.</p>
            <p>Your banner is now prominently displayed on our homepage, giving your business excellent visibility to all our visitors. Every person who visits our site will see your advertisement, helping you reach potential customers in the local community.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>Your banner is now:</strong><br>
                • Displayed prominently on our homepage<br>
                • Visible to all website visitors<br>
                • Generating impressions for your business<br>
                • Part of your active monthly subscription</p>
            </div>
            <p>Your monthly subscription is now active and will renew automatically. You can manage your banner settings from your dashboard at any time.</p>
            <p style="margin-top:24px; text-align:center;">
                <a href="' . esc_url( home_url('/') ) . '" class="btn-primary">View Homepage →</a>
            </p>'
        );
    }

    // ─── 14. BANNER RENEWED ───────────────────────────────────

    public function on_banner_renewed( $post_id, $user_id ) {
        $user       = get_userdata( $user_id );
        $site_name  = 'The Goss';
        $biz        = get_post_meta( $post_id, '_adv_banner_business', true );
        $expires_at = get_post_meta( $post_id, '_adv_banner_expires_at', true );
        if ( ! $user ) return;

        self::send(
            $user->user_email,
            "Homepage Banner Subscription Renewed – {$site_name}",
            '<div class="icon-header">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 4V1L8 5L12 9V6C15.31 6 18 8.69 18 12C18 15.31 15.31 18 12 18C8.69 18 6 15.31 6 12H4C4 16.42 7.58 20 12 20C16.42 20 20 16.42 20 12C20 7.58 16.42 4 12 4Z" fill="#A07949"/>
                </svg>
            </div>
            <h2>Subscription Renewed</h2>
            <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ',</p>
            <p>Your homepage banner subscription for <strong>' . esc_html( $biz ) . '</strong> has been successfully renewed. Your banner will continue to be displayed prominently on our homepage until <strong>' . esc_html( date( 'j M Y', strtotime( $expires_at ) ) ) . '</strong>.</p>
            <p>Your payment has been processed successfully and there is no action required from you. Your banner will remain live, continuing to give your business excellent visibility to all our website visitors.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>Renewal details:</strong><br>
                • Banner remains active on homepage<br>
                • Next renewal date: ' . esc_html( date( 'j M Y', strtotime( $expires_at ) ) ) . '<br>
                • Subscription will renew automatically<br>
                • No interruption to your advertising campaign</p>
            </div>
            <p>Thank you for your continued partnership with The Goss. We appreciate your business and look forward to helping you reach more customers!</p>'
        );
    }

    // ─── 15. BANNER EXPIRED ───────────────────────────────────

    public function on_banner_expired( $post_id, $user_id ) {
        $user      = get_userdata( $user_id );
        $site_name = 'The Goss';
        $biz       = get_post_meta( $post_id, '_adv_banner_business', true );
        if ( ! $user ) return;

        self::send(
            $user->user_email,
            "Homepage Banner Advert Expired – {$site_name}",
            '<div class="icon-header">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="6" y="4" width="4" height="16" rx="1" fill="#A07949"/>
                    <rect x="14" y="4" width="4" height="16" rx="1" fill="#A07949"/>
                </svg>
            </div>
            <h2>Banner Advert Expired</h2>
            <p>Hi ' . esc_html( $user->display_name ?: $user->user_login ) . ',</p>
            <p>Your homepage banner advertisement for <strong>' . esc_html( $biz ) . '</strong> has reached the end of its billing cycle and auto-renewal was not enabled. Your banner has been temporarily removed from our homepage.</p>
            <p>We have enjoyed featuring your business on our website and would love to continue our partnership. Your banner has been generating visibility for your business, and we would hate to see that momentum lost.</p>
            <div class="info-box">
                <p style="margin:0;"><strong>To reactivate your banner:</strong><br>
                • Visit your dashboard to enable auto-renewal<br>
                • Submit a new banner advertisement from your account<br>
                • Contact our support team for assistance with renewal<br>
                • Your previous banner design can be reused</p>
            </div>
            <p>If you have any questions about your subscription or would like to discuss renewal options, please don\'t hesitate to reach out to our advertising team. We are here to help ensure your business continues to get the exposure it deserves.</p>'
        );
    }

    // ─── HTML EMAIL SENDER ────────────────────────────────────

    /**
     * Sends a branded The Goss HTML email.
     *
     * Colour palette from getthegoss.co.uk:
     *   Header/footer bg  : #000000  (black)
     *   Accent / CTA btn  : #A07949  (gold – brand colour)
     *   Body bg           : #f5f5f5  (light grey)
     *   Card bg           : #ffffff
     *   Heading text      : #111111
     *   Body text         : #444444
     *   Info-box bg/border: #faf6f1 / #e0c9a8  (soft gold tint)
     */
    public static function send( $to, $subject, $body_html ) {
        if ( empty( $to ) || ! is_email( $to ) ) {
            error_log( 'ADV_Emails: Invalid recipient email - ' . $to );
            return false;
        }

        $site_name  = 'The Goss'; // Always display as "The Goss" in emails
        $from_email = get_option( 'adv_admin_email', get_option( 'admin_email' ) );

        if ( empty( $from_email ) || ! is_email( $from_email ) ) {
            error_log( 'ADV_Emails: Invalid from email - ' . $from_email );
            return false;
        }

        // Logo URL
        $logo_url = 'https://getthegoss.co.uk/wp-content/uploads/2026/06/Logo-Footer-scaled-1.png';
        $logo_data_uri = '';

        $home_url   = home_url( '/' );
        $year       = date( 'Y' );

        $logo_html = $logo_url
            ? '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $site_name ) . '" style="display:block; max-width:200px; width:100%; height:auto; margin:0 auto;" />'
            : '<span style="color:#ffffff; font-weight:800; font-size:20px; letter-spacing:0.5px;">' . esc_html( $site_name ) . '</span>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email,
            'Return-Path: ' . $from_email,
            'X-Mailer: PHP/' . phpversion(),
        ];

        $full_html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>' . esc_html( $subject ) . '</title>
    <!--[if mso]>
    <noscript>
    <xml>
        <o:OfficeDocumentSettings>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    </noscript>
    <![endif]-->
    <style>
        /* ── Reset ── */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { color: inherit; }

        /* ── Base ── */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f2f2f2;
            color: #444444;
            margin: 0;
            padding: 0;
        }

        /* ── Outer wrapper ── */
        .email-wrapper {
            width: 100%;
            background-color: #f2f2f2;
            padding: 30px 10px;
        }

        /* ── Container card ── */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 0 0 12px 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.10);
        }

        /* ── Header ── */
        .email-header {
            background-color: #000000;
            padding: 28px 30px;
            text-align: center;
        }
        .email-header a {
            display: inline-block;
            text-decoration: none;
        }
        .email-header img {
            max-width: 200px;
            width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        /* ── Accent bar ── */
        .accent-bar {
            height: 4px;
            background: linear-gradient(90deg, #A07949 0%, #c49a5e 100%);
        }

        /* ── Icon Header ── */
        .icon-header {
            text-align: center;
            margin-bottom: 20px;
            padding: 16px;
            background: linear-gradient(135deg, #faf6f1 0%, #f5efe4 100%);
            border-radius: 12px;
            border: 1px solid #e0c9a8;
            display: none; /* Hide if no content */
        }
        .icon-header:not(:empty) {
            display: block; /* Show only if has content */
        }
        .icon-header svg {
            display: inline-block;
            vertical-align: middle;
        }

        /* ── Body ── */
        .email-body {
            padding: 36px 36px 28px;
            font-size: 15px;
            line-height: 1.65;
            color: #444444;
        }
        .email-body h2 {
            font-size: 22px;
            font-weight: 800;
            color: #111111;
            margin-top: 0;
            margin-bottom: 14px;
            line-height: 1.3;
            text-align: center;
        }
        .email-body p {
            margin: 0 0 14px;
        }
        .email-body code {
            background: #f2f2f2;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 13px;
            color: #111111;
        }
        .email-body strong {
            color: #111111;
        }

        /* ── Info box ── */
        .info-box {
            background: #faf6f1;
            border: 1px solid #e0c9a8;
            border-left: 4px solid #A07949;
            border-radius: 8px;
            padding: 16px 18px;
            margin: 18px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        .info-box p { margin: 0; }

        /* ── CTA Button ── */
        .btn-primary {
            display: inline-block;
            background-color: #A07949;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 15px;
            padding: 13px 28px;
            border-radius: 8px;
            text-decoration: none;
            letter-spacing: 0.3px;
        }
        .btn-primary:hover {
            background-color: #8a6438;
        }

        /* ── Divider ── */
        .divider {
            border: none;
            border-top: 1px solid #eeeeee;
            margin: 24px 0;
        }

        /* ── Footer ── */
        .email-footer {
            background-color: #000000;
            padding: 22px 30px;
            text-align: center;
        }
        .email-footer p {
            font-size: 12px;
            color: #888888;
            margin: 4px 0;
            line-height: 1.6;
        }
        .email-footer a {
            color: #A07949;
            text-decoration: none;
        }

        /* ── Mobile ── */
        @media only screen and (max-width: 620px) {
            .email-wrapper { padding: 0 !important; }
            .email-container { border-radius: 0 !important; box-shadow: none !important; }
            .email-body { padding: 24px 20px 20px !important; }
            .email-body h2 { font-size: 19px !important; }
            .email-header { padding: 22px 20px !important; }
            .email-header img { max-width: 160px !important; }
            .email-footer { padding: 18px 20px !important; }
            .btn-primary { display: block !important; text-align: center !important; }
            .icon-header { padding: 12px !important; margin-bottom: 16px !important; }
            .icon-header svg { width: 40px !important; height: 40px !important; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">

            <!-- Header -->
            <div class="email-header">
                <a href="' . esc_url( $home_url ) . '" target="_blank">
                    ' . $logo_html . '
                </a>
            </div>

            <!-- Gold accent bar -->
            <div class="accent-bar"></div>

            <!-- Body -->
            <div class="email-body">
                ' . $body_html . '
            </div>

            <!-- Footer -->
            <div class="email-footer">
                <p>&copy; ' . $year . ' ' . esc_html( $site_name ) . ' &mdash; All Rights Reserved.</p>
                <p>Great Park &amp; Gosforth Magazine &mdash; <a href="' . esc_url( $home_url ) . '">' . esc_url( $home_url ) . '</a></p>
            </div>

        </div>
    </div>
</body>
</html>';

        error_log( 'ADV_Emails: Attempting to send email to ' . $to . ' with subject: ' . $subject );
        $result = wp_mail( $to, $subject, $full_html, $headers );

        if ( $result ) {
            error_log( 'ADV_Emails: Email sent successfully to ' . $to );
        } else {
            error_log( 'ADV_Emails: FAILED to send email to ' . $to . ' - Subject: ' . $subject );
            global $phpmailer;
            if ( isset( $phpmailer ) && is_object( $phpmailer ) && method_exists( $phpmailer, 'ErrorInfo' ) ) {
                error_log( 'ADV_Emails: PHPMailer ErrorInfo: ' . $phpmailer->ErrorInfo );
            }
        }

        return $result;
    }
}