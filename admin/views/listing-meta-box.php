<?php defined( 'ABSPATH' ) || exit; ?>

<?php
// Debug: Check if variables are set
if ( ! isset( $phone ) ) $phone = '';
if ( ! isset( $email ) ) $email = '';
if ( ! isset( $whatsapp ) ) $whatsapp = '';
if ( ! isset( $facebook ) ) $facebook = '';
if ( ! isset( $instagram ) ) $instagram = '';
if ( ! isset( $linkedin ) ) $linkedin = '';
if ( ! isset( $features ) ) $features = '';
if ( ! isset( $pricing_type ) ) $pricing_type = 'custom';
if ( ! isset( $price_custom ) ) $price_custom = '';
if ( ! isset( $price_range ) ) $price_range = '';
if ( ! isset( $price_ranges ) ) $price_ranges = [];
?>

<div class="adv-listing-meta-box">
    <table class="form-table">
        <!-- Contact Details -->
        <tr>
            <th><label for="adv_phone">Phone Number</label></th>
            <td>
                <input type="tel" id="adv_phone" name="adv_phone"
                       value="<?php echo esc_attr( $phone ); ?>"
                       class="regular-text" placeholder="e.g. 0191 123 4567">
            </td>
        </tr>

        <tr>
            <th><label for="adv_whatsapp">WhatsApp Number</label></th>
            <td>
                <input type="tel" id="adv_whatsapp" name="adv_whatsapp"
                       value="<?php echo esc_attr( $whatsapp ); ?>"
                       class="regular-text" placeholder="e.g. 07700 900000">
            </td>
        </tr>

        <tr>
            <th><label for="adv_email">Business Email</label></th>
            <td>
                <input type="email" id="adv_email" name="adv_email"
                       value="<?php echo esc_attr( $email ); ?>"
                       class="regular-text" placeholder="e.g. hello@yourbusiness.co.uk">
            </td>
        </tr>

        <!-- Features -->
        <tr>
            <th><label for="adv_features">Features / Key Selling Points</label></th>
            <td>
                <textarea id="adv_features" name="adv_features" rows="3" class="large-text"
                          placeholder="e.g. Free parking, Family friendly, Open weekends"><?php echo esc_textarea( $features ); ?></textarea>
                <p class="description">Separate each feature with a comma</p>
            </td>
        </tr>

        <!-- Pricing -->
        <tr>
            <th><label>Pricing Type</label></th>
            <td>
                <label>
                    <input type="radio" name="adv_pricing_type" value="custom"
                           <?php checked( $pricing_type, 'custom' ); ?>>
                    Custom Price
                </label>
                <label style="margin-left: 20px;">
                    <input type="radio" name="adv_pricing_type" value="range"
                           <?php checked( $pricing_type, 'range' ); ?>>
                    Price Range
                </label>
            </td>
        </tr>

        <tr>
            <th><label for="adv_price_custom">Your Price</label></th>
            <td>
                <input type="text" id="adv_price_custom" name="adv_price_custom"
                       value="<?php echo esc_attr( $price_custom ); ?>"
                       class="regular-text" placeholder="e.g. From £10/hour or £20 - £50">
            </td>
        </tr>

        <tr>
            <th><label for="adv_price_range">Price Range</label></th>
            <td>
                <select id="adv_price_range" name="adv_price_range" class="regular-text">
                    <option value="">— Select price range —</option>
                    <?php foreach ( $price_ranges as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>"
                            <?php selected( $price_range, $value ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>

        <!-- Social Media -->
        <tr>
            <th><label for="adv_facebook">Facebook Page URL</label></th>
            <td>
                <input type="url" id="adv_facebook" name="adv_facebook"
                       value="<?php echo esc_attr( $facebook ); ?>"
                       class="regular-text" placeholder="https://facebook.com/yourbusiness">
            </td>
        </tr>

        <tr>
            <th><label for="adv_instagram">Instagram Profile URL</label></th>
            <td>
                <input type="url" id="adv_instagram" name="adv_instagram"
                       value="<?php echo esc_attr( $instagram ); ?>"
                       class="regular-text" placeholder="https://instagram.com/yourbusiness">
            </td>
        </tr>

        <tr>
            <th><label for="adv_linkedin">LinkedIn Page URL</label></th>
            <td>
                <input type="url" id="adv_linkedin" name="adv_linkedin"
                       value="<?php echo esc_attr( $linkedin ); ?>"
                       class="regular-text" placeholder="https://linkedin.com/company/yourbusiness">
            </td>
        </tr>
    </table>
</div>

<style>
.adv-listing-meta-box .form-table th {
    width: 200px;
}
</style>
