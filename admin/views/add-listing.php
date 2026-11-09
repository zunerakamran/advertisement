<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap">
    <h1>Add New Listing</h1>
    <p>Create a new business directory listing from the WordPress admin panel.</p>

    <?php if ( $error ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $error ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( $success ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html( $success ); ?></p>
        </div>
    <?php endif; ?>

    <form class="adv-form adv-listing-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'adv_admin_add_listing', 'adv_admin_listing_nonce' ); ?>

        <table class="form-table">
            <!-- Business Name -->
            <tr>
                <th><label for="listing_name">Business Name <span class="description">(required)</span></label></th>
                <td>
                    <input type="text" id="listing_name" name="listing_name" 
                           value="<?php echo esc_attr( $old['listing_name'] ?? '' ); ?>"
                           class="regular-text" required>
                </td>
            </tr>

            <!-- Author -->
            <tr>
                <th><label for="listing_author">Author <span class="description">(required)</span></label></th>
                <td>
                    <select id="listing_author" name="listing_author" class="regular-text" required>
                        <option value="">— Select an author —</option>
                        <?php foreach ( $users as $user ) : ?>
                            <option value="<?php echo esc_attr( $user->ID ); ?>"
                                <?php selected( $old['listing_author'] ?? '', $user->ID ); ?>>
                                <?php echo esc_html( $user->display_name ); ?> (<?php echo esc_html( $user->user_login ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <!-- Description -->
            <tr>
                <th><label for="listing_desc">Description <span class="description">(required)</span></label></th>
                <td>
                    <?php
                    $content = $old['listing_desc'] ?? '';
                    $editor_id = 'listing_desc';
                    $settings = [
                        'textarea_name' => 'listing_desc',
                        'media_buttons' => false,
                        'textarea_rows' => 8,
                        'teeny' => true,
                    ];
                    wp_editor( $content, $editor_id, $settings );
                    ?>
                </td>
            </tr>

            <!-- Category -->
            <tr>
                <th><label for="listing_category">Category <span class="description">(required)</span></label></th>
                <td>
                    <select id="listing_category" name="listing_category" class="regular-text" required>
                        <option value="">— Select a category —</option>
                        <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat->term_id ); ?>"
                                <?php selected( $old['listing_category'] ?? '', $cat->term_id ); ?>>
                                <?php echo esc_html( $cat->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <!-- Features -->
            <tr>
                <th><label for="listing_features">Features / Key Selling Points</label></th>
                <td>
                    <textarea id="listing_features" name="listing_features" rows="3" class="large-text"
                              placeholder="e.g. Free parking, Family friendly, Open weekends"><?php echo esc_textarea( $old['listing_features'] ?? '' ); ?></textarea>
                    <p class="description">Separate each feature with a comma</p>
                </td>
            </tr>

            <!-- Logo -->
            <tr>
                <th><label for="listing_logo">Business Logo</label></th>
                <td>
                    <input type="file" id="listing_logo" name="listing_logo" accept="image/jpeg,image/png,image/webp">
                    <p class="description">JPG, PNG or WebP. Max 2MB.</p>
                </td>
            </tr>

            <!-- Phone -->
            <tr>
                <th><label for="listing_phone">Phone Number</label></th>
                <td>
                    <input type="tel" id="listing_phone" name="listing_phone"
                           value="<?php echo esc_attr( $old['listing_phone'] ?? '' ); ?>"
                           class="regular-text" placeholder="e.g. 0191 123 4567">
                </td>
            </tr>

            <!-- WhatsApp -->
            <tr>
                <th><label for="listing_whatsapp">WhatsApp Number</label></th>
                <td>
                    <input type="tel" id="listing_whatsapp" name="listing_whatsapp"
                           value="<?php echo esc_attr( $old['listing_whatsapp'] ?? '' ); ?>"
                           class="regular-text" placeholder="e.g. 07700 900000">
                </td>
            </tr>

            <!-- Email -->
            <tr>
                <th><label for="listing_email">Business Email</label></th>
                <td>
                    <input type="email" id="listing_email" name="listing_email"
                           value="<?php echo esc_attr( $old['listing_email'] ?? '' ); ?>"
                           class="regular-text" placeholder="e.g. hello@yourbusiness.co.uk">
                </td>
            </tr>

            <!-- Pricing Type -->
            <tr>
                <th><label>Pricing Type</label></th>
                <td>
                    <label>
                        <input type="radio" name="listing_pricing_type" value="custom"
                               <?php checked( $old['listing_pricing_type'] ?? 'custom', 'custom' ); ?>>
                        Custom Price
                    </label>
                    <label style="margin-left: 20px;">
                        <input type="radio" name="listing_pricing_type" value="range"
                               <?php checked( $old['listing_pricing_type'] ?? '', 'range' ); ?>>
                        Price Range
                    </label>
                </td>
            </tr>

            <!-- Custom Price -->
            <tr>
                <th><label for="listing_price_custom">Your Price</label></th>
                <td>
                    <input type="text" id="listing_price_custom" name="listing_price_custom"
                           value="<?php echo esc_attr( $old['listing_price_custom'] ?? '' ); ?>"
                           class="regular-text" placeholder="e.g. From £10/hour or £20 - £50">
                </td>
            </tr>

            <!-- Price Range -->
            <tr>
                <th><label for="listing_price_range">Price Range</label></th>
                <td>
                    <select id="listing_price_range" name="listing_price_range" class="regular-text">
                        <option value="">— Select price range —</option>
                        <?php foreach ( ADV_Listing_Admin::$price_ranges as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>"
                                <?php selected( $old['listing_price_range'] ?? '', $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <!-- Facebook -->
            <tr>
                <th><label for="listing_facebook">Facebook Page URL</label></th>
                <td>
                    <input type="url" id="listing_facebook" name="listing_facebook"
                           value="<?php echo esc_attr( $old['listing_facebook'] ?? '' ); ?>"
                           class="regular-text" placeholder="https://facebook.com/yourbusiness">
                </td>
            </tr>

            <!-- Instagram -->
            <tr>
                <th><label for="listing_instagram">Instagram Profile URL</label></th>
                <td>
                    <input type="url" id="listing_instagram" name="listing_instagram"
                           value="<?php echo esc_attr( $old['listing_instagram'] ?? '' ); ?>"
                           class="regular-text" placeholder="https://instagram.com/yourbusiness">
                </td>
            </tr>

            <!-- LinkedIn -->
            <tr>
                <th><label for="listing_linkedin">LinkedIn Page URL</label></th>
                <td>
                    <input type="url" id="listing_linkedin" name="listing_linkedin"
                           value="<?php echo esc_attr( $old['listing_linkedin'] ?? '' ); ?>"
                           class="regular-text" placeholder="https://linkedin.com/company/yourbusiness">
                </td>
            </tr>

            <!-- Status -->
            <tr>
                <th><label for="listing_status">Listing Status</label></th>
                <td>
                    <select id="listing_status" name="listing_status" class="regular-text">
                        <option value="publish" <?php selected( $old['listing_status'] ?? 'publish', 'publish' ); ?>>Published</option>
                        <option value="draft" <?php selected( $old['listing_status'] ?? '', 'draft' ); ?>>Draft</option>
                        <option value="pending" <?php selected( $old['listing_status'] ?? '', 'pending' ); ?>>Pending Review</option>
                    </select>
                    <p class="description">Choose whether to publish immediately or save as draft/pending</p>
                </td>
            </tr>
        </table>

        <?php submit_button( 'Create Listing', 'primary', 'submit', false ); ?>
    </form>
</div>

<style>
.adv-listing-form {
    max-width: 800px;
    margin-top: 20px;
}
.adv-listing-form .form-table th {
    width: 200px;
}
</style>
