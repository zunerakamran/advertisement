<?php
defined( 'ABSPATH' ) || exit;

class ADV_Banner_Admin {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post_adv_banner', [ $this, 'save_banner_meta' ] );
    }

    // ─── Meta boxes ──────────────────────────────────────────

    public function register_meta_boxes() {
        // Banner size and image panel
        add_meta_box(
            'adv_banner_size_image',
            '� Banner Size & Image',
            [ $this, 'size_image_meta_box' ],
            'adv_banner', 'normal', 'high'
        );
        // Contact details panel
        add_meta_box(
            'adv_banner_contact',
            '👤 Contact Information',
            [ $this, 'contact_meta_box' ],
            'adv_banner', 'normal', 'high'
        );
        // Banner URL panel
        add_meta_box(
            'adv_banner_url',
            '🔗 Banner Link',
            [ $this, 'url_meta_box' ],
            'adv_banner', 'side', 'high'
        );
        // Payment and status panel
        add_meta_box(
            'adv_banner_payment',
            '💳 Payment & Status',
            [ $this, 'payment_meta_box' ],
            'adv_banner', 'side', 'high'
        );
    }

    public function size_image_meta_box( $post ) {
        wp_nonce_field( 'adv_banner_save', 'adv_banner_nonce' );
        $size    = get_post_meta( $post->ID, '_adv_banner_size', true );
        $img_id  = get_post_meta( $post->ID, '_adv_banner_image_id', true );
        ?>
        <style>
            .adv-field-group { margin-bottom:15px; }
            .adv-field-group label { display:block; font-weight:600; font-size:.85rem; color:#374151; margin-bottom:5px; }
            .adv-field-group select, .adv-field-group input { width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:.9rem; }
            .adv-image-preview { margin-top:15px; max-width:100%; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; }
            .adv-image-preview img { width:100%; height:auto; display:block; }
            .adv-dim-info { font-size:.8rem; color:#6b7280; margin-top:5px; }
        </style>
        <div class="adv-field-group">
            <label for="adv_banner_size">Banner Size</label>
            <select id="adv_banner_size" name="adv_banner_size">
                <option value="">Select a size...</option>
                <?php foreach ( ADV_Banner_Settings::BANNERS as $key => $cfg ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $size, $key ); ?>>
                        <?php echo esc_html( $cfg['label'] ); ?> - £<?php echo esc_html( ADV_Banner_Settings::get_price( $key ) ); ?>/mo
                    </option>
                <?php endforeach; ?>
            </select>
            <div id="adv_banner_dimensions" class="adv-dim-info"></div>
        </div>

        <div class="adv-field-group">
            <label for="adv_banner_image">Banner Image</label>
            <input type="hidden" id="adv_banner_image_id" name="adv_banner_image_id" value="<?php echo esc_attr( $img_id ); ?>">
            <button type="button" class="button" id="adv_upload_banner_image">Upload Image</button>
            <button type="button" class="button" id="adv_remove_banner_image" style="display:none;">Remove Image</button>
            <?php if ( $img_id ) : ?>
                <div class="adv-image-preview">
                    <?php echo wp_get_attachment_image( $img_id, 'large', false, [ 'style' => 'max-height:400px;width:auto;display:block;margin:10px auto;' ] ); ?>
                </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var dimensions = <?php echo json_encode( ADV_Banner_Settings::BANNERS ); ?>;
            $('#adv_banner_size').on('change', function() {
                var size = $(this).val();
                if (size && dimensions[size]) {
                    $('#adv_banner_dimensions').text('Required dimensions: ' + dimensions[size].width + ' × ' + dimensions[size].height + ' px');
                } else {
                    $('#adv_banner_dimensions').text('');
                }
            }).trigger('change');

            var mediaUploader;
            $('#adv_upload_banner_image').on('click', function(e) {
                e.preventDefault();
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: 'Select Banner Image',
                    button: { text: 'Use this image' },
                    multiple: false
                });
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#adv_banner_image_id').val(attachment.id);
                    $('.adv-image-preview').html('<img src="' + attachment.url + '" style="max-height:400px;width:auto;display:block;margin:10px auto;">');
                    $('#adv_remove_banner_image').show();
                });
                mediaUploader.open();
            });

            $('#adv_remove_banner_image').on('click', function(e) {
                e.preventDefault();
                $('#adv_banner_image_id').val('');
                $('.adv-image-preview').empty();
                $(this).hide();
            });

            <?php if ( $img_id ) : ?>
                $('#adv_remove_banner_image').show();
            <?php endif; ?>
        });
        </script>
        <?php
    }

    public function contact_meta_box( $post ) {
        $biz     = get_post_meta( $post->ID, '_adv_banner_business', true );
        $cname   = get_post_meta( $post->ID, '_adv_banner_contact_name', true );
        $cemail  = get_post_meta( $post->ID, '_adv_banner_contact_email', true );
        $cphone  = get_post_meta( $post->ID, '_adv_banner_contact_phone', true );
        $notes   = get_post_meta( $post->ID, '_adv_banner_notes', true );
        ?>
        <style>
            .adv-contact-grid { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
            .adv-field-group { margin-bottom:15px; }
            .adv-field-group label { display:block; font-weight:600; font-size:.85rem; color:#374151; margin-bottom:5px; }
            .adv-field-group input, .adv-field-group textarea { width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:.9rem; }
            .adv-field-group textarea { min-height:100px; resize:vertical; }
        </style>
        <div class="adv-contact-grid">
            <div class="adv-field-group">
                <label for="adv_banner_business">Business Name *</label>
                <input type="text" id="adv_banner_business" name="adv_banner_business" value="<?php echo esc_attr( $biz ); ?>" required>
            </div>
            <div class="adv-field-group">
                <label for="adv_banner_contact_name">Contact Name</label>
                <input type="text" id="adv_banner_contact_name" name="adv_banner_contact_name" value="<?php echo esc_attr( $cname ); ?>">
            </div>
            <div class="adv-field-group">
                <label for="adv_banner_contact_email">Contact Email *</label>
                <input type="email" id="adv_banner_contact_email" name="adv_banner_contact_email" value="<?php echo esc_attr( $cemail ); ?>" required>
            </div>
            <div class="adv-field-group">
                <label for="adv_banner_contact_phone">Contact Phone</label>
                <input type="text" id="adv_banner_contact_phone" name="adv_banner_contact_phone" value="<?php echo esc_attr( $cphone ); ?>">
            </div>
        </div>
        <div class="adv-field-group">
            <label for="adv_banner_notes">Notes</label>
            <textarea id="adv_banner_notes" name="adv_banner_notes"><?php echo esc_textarea( $notes ); ?></textarea>
        </div>
        <?php
    }

    public function url_meta_box( $post ) {
        $banner_url = get_post_meta( $post->ID, '_adv_banner_url', true );
        ?>
        <style>
            .adv-url-box p { margin:0 0 10px; font-size:.875rem; }
            .adv-url-box input[type="url"],
            .adv-url-box input[type="text"] {
                width:100%; padding:8px 10px; border:1.5px solid #d1d5db;
                border-radius:6px; font-size:.875rem; box-sizing:border-box;
            }
            .adv-url-box label { font-weight:600; display:block; margin-bottom:4px; font-size:.8rem; }
        </style>
        <div class="adv-url-box">
            <p><label for="adv_banner_url">Banner Destination URL *</label>
            <input type="url" id="adv_banner_url" name="adv_banner_url"
                   value="<?php echo esc_attr( $banner_url ); ?>"
                   placeholder="https://example.com" required>
            <span style="font-size:.78rem;color:#6b7280;display:block;margin-top:4px;">
                The URL where the banner should link when clicked.
            </span></p>
        </div>
        <?php
    }

    public function payment_meta_box( $post ) {
        $paid    = get_post_meta( $post->ID, '_adv_banner_paid', true );
        $paid_at = get_post_meta( $post->ID, '_adv_banner_paid_at', true );
        $expires = get_post_meta( $post->ID, '_adv_banner_expires_at', true );
        $auto_renew = get_post_meta( $post->ID, '_adv_banner_auto_renew', true );
        $pm_method = get_post_meta( $post->ID, '_adv_banner_payment_method', true );
        $session = get_post_meta( $post->ID, '_adv_banner_stripe_session', true );
        $subscription = get_post_meta( $post->ID, '_adv_banner_stripe_subscription', true );
        ?>
        <style>
            .adv-payment-info { font-size:.85rem; }
            .adv-payment-info p { margin:0 0 8px; }
            .adv-payment-info label { font-weight:600; color:#374151; display:block; }
            .adv-payment-info span { color:#6b7280; }
            .adv-paid-badge {
                display:inline-block; padding:4px 12px; border-radius:12px;
                background:<?php echo $paid ? '#d1fae5' : '#fee2e2'; ?>;
                color:<?php echo $paid ? '#065f46' : '#991b1b'; ?>;
                font-weight:700; font-size:.8rem; margin-bottom:12px;
            }
        </style>
        <div class="adv-payment-info">
            <p><span class="adv-paid-badge"><?php echo $paid ? '✅ Paid' : '❌ Not Paid'; ?></span></p>
            <p><label>Payment Method:</label><span><?php echo esc_html( ucfirst( str_replace( '_', ' ', $pm_method ?: 'stripe' ) ) ); ?></span></p>
            <p><label>Paid At:</label><span><?php echo $paid_at ? esc_html( date_i18n( 'd M Y H:i', strtotime( $paid_at ) ) ) : '—'; ?></span></p>
            <p><label>Expires At:</label><span><?php echo $expires ? esc_html( date_i18n( 'd M Y H:i', strtotime( $expires ) ) ) : '—'; ?></span></p>
            <p><label>Auto-Renew:</label><span><?php echo $auto_renew ? '✅ Enabled' : '❌ Disabled'; ?></span></p>
            <?php if ( $session ) : ?>
                <p><label>Stripe Session:</label><span style="font-size:.75rem;word-break:break-all;"><?php echo esc_html( $session ); ?></span></p>
            <?php endif; ?>
            <?php if ( $subscription ) : ?>
                <p><label>Stripe Subscription:</label><span style="font-size:.75rem;word-break:break-all;"><?php echo esc_html( $subscription ); ?></span></p>
            <?php endif; ?>
        </div>
        <?php
    }

    // ─── Save banner meta ──────────────────────────────────

    public function save_banner_meta( $post_id ) {
        if ( ! isset( $_POST['adv_banner_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['adv_banner_nonce'], 'adv_banner_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Save banner size
        $size = sanitize_key( $_POST['adv_banner_size'] ?? '' );
        if ( array_key_exists( $size, ADV_Banner_Settings::BANNERS ) ) {
            update_post_meta( $post_id, '_adv_banner_size', $size );
            update_post_meta( $post_id, '_adv_banner_price', ADV_Banner_Settings::get_price( $size ) );
        }

        // Save banner image
        $img_id = intval( $_POST['adv_banner_image_id'] ?? 0 );
        if ( $img_id ) {
            update_post_meta( $post_id, '_adv_banner_image_id', $img_id );
            set_post_thumbnail( $post_id, $img_id );
        }

        // Save contact information
        update_post_meta( $post_id, '_adv_banner_business', sanitize_text_field( $_POST['adv_banner_business'] ?? '' ) );
        update_post_meta( $post_id, '_adv_banner_contact_name', sanitize_text_field( $_POST['adv_banner_contact_name'] ?? '' ) );
        update_post_meta( $post_id, '_adv_banner_contact_email', sanitize_email( $_POST['adv_banner_contact_email'] ?? '' ) );
        update_post_meta( $post_id, '_adv_banner_contact_phone', sanitize_text_field( $_POST['adv_banner_contact_phone'] ?? '' ) );
        update_post_meta( $post_id, '_adv_banner_notes', sanitize_textarea_field( $_POST['adv_banner_notes'] ?? '' ) );

        // Save banner URL
        $banner_url = esc_url_raw( $_POST['adv_banner_url'] ?? '' );
        update_post_meta( $post_id, '_adv_banner_url', $banner_url );
    }
}
