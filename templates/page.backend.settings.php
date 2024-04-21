<?php

/**
 * MRPacket
 * The MRPacket plugin enables you to import your order data from your WooCommerce shop directly to MRPacket.
 * 
 * @version 0.0.1
 * @link https://www.mrpacket.de/api
 * @license GPLv2
 * @author MRPacket <info@mrpacket.de>
 * 
 * Copyright (c) 2023 MRPacket
 */
?>
<div class="wrap mrpacket">

    <h1><?php esc_html_e('MRPacket Settings Page', 'mrpacket'); ?></h1>

    <?php wp_nonce_field('mrpacket_update', 'mrpacket_settings_form_nonce'); ?>
    <table class="form-table settings">
        <tbody>

            <!-- If Token is set, show token field as disabled field -->
            <?php if ($token) { ?>
                <tr>
                    <th><label for="mrpacket_api_token"><?php esc_html_e('MRPacket API-Token', 'mrpacket'); ?></label></th>
                    <td><input disabled autocomplete="off" name="mrpacket_api_token" id="mrpacket_api_token" type="text" value="<?php esc_attr_e(get_option('mrpacket_api_token')); ?>" class="regular-text" /></td>
                </tr>

                <!-- Reset-Settings Button -->
                <tr>
                    <th><label for="mrpacket_reset_settings">&nbsp;</label></th>
                    <td><button name="mrpacket_reset_settings" id="mrpacket_reset_settings" type="submit" value="1" class="button-secondary"><?php esc_html_e('Reset settings', 'mrpacket'); ?></button>
                </tr>
            <?php } else { ?>
                <tr>
                    <th><label for="mrpacket_api_username"><?php esc_html_e('MRPacket Username', 'mrpacket'); ?></label></th>
                    <td><input required autocomplete="off" placeholder="Bitte geben Sie hier Ihren MRPacket-Benutzernamen (E-Mail) ein" name="mrpacket_api_username" id="mrpacket_api_username" type="text" value="" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="mrpacket_api_password"><?php esc_html_e('MRPacket Password', 'mrpacket'); ?></label></th>
                    <td><input required autocomplete="off" placeholder="Bitte geben Sie hier Ihr MRPacket-Passwort ein" name="mrpacket_api_password" id="mrpacket_api_password" type="password" value="" class="regular-text" /></td>
                </tr>

            <?php } ?>

        </tbody>
    </table>

    <br />

    <h2><?php esc_html_e('Allowed order status', 'mrpacket'); ?></h2>
    <table class="form-table settings">
        <tbody>
            <tr>
                <th><label for="mrpacket_transfer_settings"><?php esc_html_e('The transfer of an order to MRPacket depends on the status of the order. Please make sure to configure all status that you would like to use for sending to mrpacket here. An order is sent to MRPacket for each status configured (selected).', 'mrpacket'); ?></label></th>
                <td>
                    <select id="status" name="mrpacket_transfer_settings[]" multiple="multiple" size="10" style="height: 100%;" autocomplete="off">
                        <option <?php if ($orderStatusSelected[0] == '999') {
                                    echo ' selected';
                                } ?> value="999">
                            <?php esc_html_e('-- DISABLE ALL --', 'mrpacket'); ?>
                        </option>
                        <?php foreach ($orderStatusFromSystem as $key => $value) { ?>
                            <option <?php if ($orderStatusSelected[0] != '999' && in_array($key, $orderStatusSelected)) {
                                        echo ' selected';
                                    } ?> value="<?php esc_html_e($key) ?>">
                                <?php esc_html_e($value); ?> (ID: <?php esc_html_e($key); ?>)
                            </option>
                        <?php } ?>
                    </select>
                </td>
            </tr>
        </tbody>
    </table>

    <br />

    <h2><?php esc_html_e('E-Mail Settings', 'mrpacket'); ?></h2>
    <table class="form-table settings">
        <tbody>
            <tr>
                <th><label for="mrpacket_admin_email"><?php esc_html_e('E-Mail for failure messages', 'mrpacket'); ?></label></th>
                <td><input placeholder="<?php esc_html_e('E-Mail for failure messages', 'mrpacket'); ?>" autocomplete="off" name="mrpacket_admin_email" id="mrpacket_admin_email" type="email" value="<?php esc_attr_e(get_option('mrpacket_admin_email')); ?>" class="regular-text" /></td>
            </tr>
        </tbody>
    </table>

    <br />

    <h2><?php esc_html_e('Last automatic cron run', 'mrpacket'); ?></h2>
    <table class="form-table settings">
        <tbody>
            <tr>
                <th><label for="mrpacket_cron_last_run"><?php esc_html_e('Cron runs every 5 minutes. Here you can see the time of the last automatic cron run:', 'mrpacket'); ?></label></th>
                <td><input disabled autocomplete="off" name="mrpacket_cron_last_run" id="mrpacket_cron_last_run" type="text" value="<?php esc_attr_e($mrpacket_cron_last_run); ?>" class="regular-text" /></td>
            </tr>
        </tbody>
    </table>

    <style>
        .notice.wcs-nux__notice {
            display: none;
        }
    </style>

</div>