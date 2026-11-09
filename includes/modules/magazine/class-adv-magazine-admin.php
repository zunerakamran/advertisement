<?php
defined( 'ABSPATH' ) || exit;

class ADV_Magazine_Admin {

    public function __construct() {
        add_filter( 'manage_adv_magazine_posts_columns',       [ $this, 'columns' ] );
        add_action( 'manage_adv_magazine_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
        add_action( 'add_meta_boxes',                          [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post_adv_magazine',                  [ $this, 'save_approval_meta' ] );
        // Show custom statuses in status dropdown
        add_action( 'post_submitbox_misc_actions',             [ $this, 'add_status_to_dropdown' ] );
        add_action( 'admin_footer-post.php',                   [ $this, 'status_dropdown_js' ] );
        add_action( 'admin_footer-edit.php',                   [ $this, 'status_label_js' ] );
    }

    // ─── List table columns ──────────────────────────────────

    public function columns( $cols ) {
        return [
            'cb'              => $cols['cb'],
            'title'           => 'Business / Ad',
            'adv_type'        => 'Ad Size',
            'adv_image'       => 'Advert Image',
            'adv_contact'     => 'Contact',
            'adv_mag_status'  => 'Status & URL',
        ];
    }

    public function column_content( $col, $post_id ) {
        switch ( $col ) {
            case 'adv_type':
                $type = get_post_meta( $post_id, '_adv_mag_type', true );
                echo esc_html( ADV_Magazine_Settings::get_label( $type ) );
                break;

            case 'adv_image':
                $img_id = get_post_meta( $post_id, '_adv_mag_image_id', true );
                if ( $img_id ) {
                    $url = wp_get_attachment_url( $img_id );
                    echo '<a href="' . esc_url( $url ) . '" target="_blank" class="adv-view-attachment">';
                    echo '<img src="' . esc_url( wp_get_attachment_thumb_url( $img_id ) ) . '" style="max-width:80px;max-height:60px;border-radius:4px;border:1px solid #ddd;">';
                    echo '<br><small>View Full</small></a>';
                } else {
                    echo '—';
                }
                break;

            case 'adv_contact':
                $name  = get_post_meta( $post_id, '_adv_mag_contact_name', true );
                $email = get_post_meta( $post_id, '_adv_mag_contact_email', true );
                $phone = get_post_meta( $post_id, '_adv_mag_contact_phone', true );
                echo esc_html( $name ) . '<br>';
                echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
                if ( $phone ) echo '<br>' . esc_html( $phone );
                break;

            case 'adv_mag_status':
                $post   = get_post( $post_id );
                $status = $post->post_status;
                $url    = get_post_meta( $post_id, '_adv_mag_url', true );
                
                echo '<div style="margin-bottom:8px;">';
                echo '<label style="font-size:.75rem;color:#6b7280;font-weight:600;">Status:</label><br>';
                echo '<select class="adv-mag-status-dropdown" data-post-id="' . esc_attr( $post_id ) . '" style="width:100%;max-width:200px;">';
                echo '<option value="publish" ' . selected( $status, 'publish', false ) . '>Published</option>';
                echo '<option value="draft" ' . selected( $status, 'draft', false ) . '>Draft</option>';
                echo '<option value="pending" ' . selected( $status, 'pending', false ) . '>Pending</option>';
                echo '<option value="adv_pending_payment" ' . selected( $status, 'adv_pending_payment', false ) . '>Pending Payment</option>';
                echo '<option value="adv_approved" ' . selected( $status, 'adv_approved', false ) . '>Approved</option>';
                echo '</select>';
                echo '</div>';
                
                echo '<div>';
                echo '<label style="font-size:.75rem;color:#6b7280;font-weight:600;">Magazine URL:</label><br>';
                echo '<input type="text" class="adv-mag-url-input" data-post-id="' . esc_attr( $post_id ) . '" value="' . esc_attr( $url ) . '" placeholder="Enter URL" style="width:150px;">';
                echo '<button class="button button-small adv-mag-save-all" data-post-id="' . esc_attr( $post_id ) . '" style="margin-left:5px;">Save</button>';
                echo '</div>';
                echo '<span class="adv-mag-spinner" style="display:none; margin-left:10px;">Saving...</span>';
                break;
        }
    }

    // ─── Meta boxes ──────────────────────────────────────────

    public function register_meta_boxes() {
        // Full advert details panel
        add_meta_box(
            'adv_magazine_details',
            '📋 Advert Details',
            [ $this, 'details_meta_box' ],
            'adv_magazine', 'normal', 'high'
        );
        // Approval panel — admin enters magazine URL here
        add_meta_box(
            'adv_magazine_approval',
            '✅ Approval & Magazine URL',
            [ $this, 'approval_meta_box' ],
            'adv_magazine', 'side', 'high'
        );
    }

    public function details_meta_box( $post ) {
        $img_id  = get_post_meta( $post->ID, '_adv_mag_image_id', true );
        $type    = get_post_meta( $post->ID, '_adv_mag_type', true );
        $price   = get_post_meta( $post->ID, '_adv_mag_price', true );
        $biz     = get_post_meta( $post->ID, '_adv_mag_business', true );
        $cname   = get_post_meta( $post->ID, '_adv_mag_contact_name', true );
        $cemail  = get_post_meta( $post->ID, '_adv_mag_contact_email', true );
        $cphone  = get_post_meta( $post->ID, '_adv_mag_contact_phone', true );
        $notes   = get_post_meta( $post->ID, '_adv_mag_notes', true );
        $paid_at = get_post_meta( $post->ID, '_adv_mag_paid_at', true );
        $session = get_post_meta( $post->ID, '_adv_mag_stripe_session', true );
        ?>
        <style>
            .adv-meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px 24px; margin-bottom:20px; }
            .adv-meta-field label { display:block; font-weight:600; font-size:.8rem; color:#6b7280; margin-bottom:2px; text-transform:uppercase; letter-spacing:.5px; }
            .adv-meta-field span  { font-size:.95rem; color:#111827; }
            .adv-advert-preview { border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; margin-top:16px; }
            .adv-advert-preview img { width:100%; height:auto; display:block; }
            .adv-advert-preview-actions { padding:10px 14px; background:#f9fafb; display:flex; gap:10px; align-items:center; }
        </style>
        <div class="adv-meta-grid">
            <div class="adv-meta-field"><label>Business</label><span><?php echo esc_html( $biz ); ?></span></div>
            <div class="adv-meta-field"><label>Ad Size</label><span><?php echo esc_html( ADV_Magazine_Settings::get_label( $type ) ); ?></span></div>
            <div class="adv-meta-field"><label>Amount Paid</label><span>£<?php echo number_format( (float) $price, 2 ); ?></span></div>
            <div class="adv-meta-field"><label>Paid At</label><span><?php echo $paid_at ? esc_html( date_i18n( 'd M Y H:i', strtotime( $paid_at ) ) ) : '—'; ?></span></div>
            <div class="adv-meta-field"><label>Contact Name</label><span><?php echo esc_html( $cname ); ?></span></div>
            <div class="adv-meta-field"><label>Contact Email</label><span><a href="mailto:<?php echo esc_attr( $cemail ); ?>"><?php echo esc_html( $cemail ); ?></a></span></div>
            <div class="adv-meta-field"><label>Contact Phone</label><span><?php echo esc_html( $cphone ?: '—' ); ?></span></div>
            <div class="adv-meta-field"><label>Stripe Session</label><span style="font-size:.8rem;word-break:break-all;"><?php echo esc_html( $session ?: '—' ); ?></span></div>
        </div>
        <?php if ( $notes ) : ?>
            <div class="adv-meta-field" style="margin-bottom:16px;">
                <label>Notes from Client</label>
                <span><?php echo nl2br( esc_html( $notes ) ); ?></span>
            </div>
        <?php endif; ?>

        <?php if ( $img_id ) : ?>
            <p style="font-weight:600;margin-bottom:8px;">Advert Image</p>
            <div class="adv-advert-preview">
                <?php echo wp_get_attachment_image( $img_id, 'large', false, [ 'style' => 'max-height:600px;width:auto;display:block;margin:0 auto;' ] ); ?>
                <div class="adv-advert-preview-actions">
                    <a href="<?php echo esc_url( wp_get_attachment_url( $img_id ) ); ?>" target="_blank" class="button">🔍 View Full Size</a>
                    <a href="<?php echo esc_url( wp_get_attachment_url( $img_id ) ); ?>" download class="button">⬇ Download</a>
                </div>
            </div>
        <?php else : ?>
            <p style="color:#999;">No image uploaded.</p>
        <?php endif; ?>
        <?php
    }

    public function approval_meta_box( $post ) {
        wp_nonce_field( 'adv_magazine_approval_save', 'adv_magazine_approval_nonce' );
        $mag_url = get_post_meta( $post->ID, '_adv_mag_url', true );
        $paid    = get_post_meta( $post->ID, '_adv_mag_paid', true );
        ?>
        <style>
            .adv-approval-box p { margin:0 0 10px; font-size:.875rem; }
            .adv-approval-box input[type="url"],
            .adv-approval-box input[type="text"] {
                width:100%; padding:8px 10px; border:1.5px solid #d1d5db;
                border-radius:6px; font-size:.875rem; box-sizing:border-box;
            }
            .adv-approval-box label { font-weight:600; display:block; margin-bottom:4px; font-size:.8rem; }
            .adv-paid-badge {
                display:inline-block; padding:4px 12px; border-radius:12px;
                background:<?php echo $paid ? '#d1fae5' : '#fee2e2'; ?>;
                color:<?php echo $paid ? '#065f46' : '#991b1b'; ?>;
                font-weight:700; font-size:.8rem; margin-bottom:12px;
            }
        </style>
        <div class="adv-approval-box">
            <p><span class="adv-paid-badge"><?php echo $paid ? '✅ Payment Confirmed' : '❌ Not Paid'; ?></span></p>

            <p><label for="adv_mag_url">Magazine Issue URL</label>
            <input type="url" id="adv_mag_url" name="adv_mag_url"
                   value="<?php echo esc_attr( $mag_url ); ?>"
                   placeholder="https://getthegoss.co.uk/issues/...">
            <span style="font-size:.78rem;color:#6b7280;display:block;margin-top:4px;">
                Enter the URL of the magazine issue where this advert appears. The client will be notified by email.
            </span></p>

            <p style="margin-top:14px;padding-top:14px;border-top:1px solid #e5e7eb;">
                <strong>Change Status:</strong><br>
                <select name="post_status" style="width:100%;margin-top:6px;padding:7px 10px;border:1.5px solid #d1d5db;border-radius:6px;">
                    <option value="pending"      <?php selected( $post->post_status, 'pending' ); ?>>Pending Review</option>
                    <option value="adv_approved" <?php selected( $post->post_status, 'adv_approved' ); ?>>Approved</option>
                    <option value="draft"        <?php selected( $post->post_status, 'draft' ); ?>>Rejected</option>
                </select>
            </p>
        </div>
        <?php
    }

    // ─── Save approval meta ──────────────────────────────────

    public function save_approval_meta( $post_id ) {
        if ( ! isset( $_POST['adv_magazine_approval_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['adv_magazine_approval_nonce'], 'adv_magazine_approval_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $old_url = get_post_meta( $post_id, '_adv_mag_url', true );
        $new_url = esc_url_raw( $_POST['adv_mag_url'] ?? '' );

        update_post_meta( $post_id, '_adv_mag_url', $new_url );

        // If URL was just set and post is being approved → fire email
        $new_status = $_POST['post_status'] ?? '';
        if ( $new_url && $new_url !== $old_url && in_array( $new_status, [ 'adv_approved', 'publish' ] ) ) {
            do_action( 'adv_magazine_approved', $post_id, get_post( $post_id )->post_author, $new_url );
        }
    }

    // ─── Status label JS helpers ─────────────────────────────

    public function add_status_to_dropdown() {
        global $post;
        if ( ! $post || $post->post_type !== 'adv_magazine' ) return;
    }

    public function status_dropdown_js() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'adv_magazine' ) return;
        ?>
        <script>
        jQuery(function($){
            // Make our custom statuses available in the status select
            var $sel = $('select[name="post_status"]');
            if(!$sel.length) return;
            if(!$sel.find('option[value="adv_approved"]').length){
                $sel.append('<option value="adv_approved">Approved</option>');
            }
            if(!$sel.find('option[value="adv_pending_payment"]').length){
                $sel.append('<option value="adv_pending_payment">Pending Payment</option>');
            }
        });
        </script>
        <?php
    }

    public function status_label_js() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'adv_magazine' ) return;
        ?>
        <script>
        jQuery(function($){
            var labels = {
                'adv_pending_payment': 'Pending Payment',
                'pending': 'Pending Review',
                'adv_approved': 'Approved',
                'publish': 'Published',
                'draft': 'Rejected'
            };
            // Fix the "Status:" label in list table
            $('.column-post_status_col').each(function(){
                // already rendered via PHP column
            });

            // Combined save handler for status and URL
            $('.adv-mag-save-all').on('click', function() {
                var $button = $(this);
                var postId = $button.data('post-id');
                var $cell = $button.closest('td');
                var $statusSelect = $cell.find('.adv-mag-status-dropdown');
                var $urlInput = $cell.find('.adv-mag-url-input');
                var newStatus = $statusSelect.val();
                var url = $urlInput.val();
                var $spinner = $cell.find('.adv-mag-spinner');
                
                $spinner.show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'adv_save_magazine_all',
                        post_id: postId,
                        status: newStatus,
                        url: url,
                        nonce: '<?php echo wp_create_nonce( 'adv_save_magazine_all_nonce' ); ?>'
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
                        alert('Error saving');
                    }
                });
            });
        });
        </script>
        <?php
    }
}