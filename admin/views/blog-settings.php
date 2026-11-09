<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap">
    <h1>Featured Blog Settings</h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'adv_blog_settings_save', 'adv_blog_settings_nonce' ); ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="adv_blog_price">Submission Price (£)</label></th>
                    <td>
                        <input type="number" id="adv_blog_price" name="adv_blog_price"
                               value="<?php echo esc_attr( ADV_Blog_Settings::get_price() ); ?>"
                               min="0" step="0.01" class="regular-text">
                        <p class="description">One-time fee charged when a user submits a featured guest blog.</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php submit_button( 'Save Settings' ); ?>
    </form>
</div>