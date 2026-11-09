<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap">
    <h1>Featured Recipe Settings</h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'adv_recipe_settings_save', 'adv_recipe_settings_nonce' ); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="adv_recipe_price">Submission Price (£)</label>
                    </th>
                    <td>
                        <input type="number" id="adv_recipe_price" name="adv_recipe_price"
                               value="<?php echo esc_attr( ADV_Recipe_Settings::get_price() ); ?>"
                               min="0" step="0.01" class="regular-text">
                        <p class="description">The one-time fee charged when a user submits a featured guest recipe.</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button( 'Save Settings' ); ?>
    </form>
</div>