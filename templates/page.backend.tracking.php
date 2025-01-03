<?php

/**
 * MRPacket
 * The MRPacket plugin enables you to import your order data from your WooCommerce shop directly to MRPacket.
 * 
 * @version 1.0.0
 * @link https://www.mrpacket.de/api
 * @license GPLv2
 * @author MRPacket <info@mrpacket.de>
 * 
 * Copyright (c) 2023 MRPacket
 */
?>
<div class="wrap mrpacket tracking ajaxresponse">

    <?php settings_errors(); ?>

    <form method="post" action="<?php esc_url($adminURL) ?>" class="refresh-form">

        <?php wp_nonce_field('mrpacket_tracking', 'mrpacket_tracking_form_nonce'); ?>

        <div class="container-fluid intro">
            <div class="row">
                <div>
                    <h1><?php esc_html_e('Orders transferred to MRPacket', 'mrpacket'); ?></h1>
                    <p>
                        <?php echo wp_kses(
                            __('<p>Below you can see all orders that have already been transferred from your shop to MRPacket. </p><strong>Please note:</strong><p>Orders must have the status which were selected in the plugin settings in order to be transferred. <br />The transfer takes place automatically when the status changes. In addition, for the transfer, only orders that have already been received in your shop prior to the activation of the plugin will be considered!</p>', 'mrpacket'),
                            [
                                'p' => [],
                                'strong' => []
                            ]
                        ); ?>
                    </p>
                </div>

                <div class="buttons">
                    <button name="refresh" type="submit" value="1" class="btn btn-primary">
                        <i class="fa fa-refresh"></i> <?php esc_html_e('Update list / send orders', 'mrpacket'); ?>
                    </button>
                    <button name="cancel" type="button" class="btn btn-secondary">
                        <i class="fa fa-cancel"></i> <?php esc_html_e('Cancel selected orders', 'mrpacket'); ?>
                    </button>
                    <button name="enable" type="button" class="btn btn-secondary">
                        <i class="fa fa-refresh"></i> <?php esc_html_e('Re-enable selected orders', 'mrpacket'); ?>
                    </button>
                    <button name="archive" type="button" class="btn btn-secondary">
                        <i class="fa fa-trash"></i> <?php esc_html_e('Archive selected orders', 'mrpacket'); ?>
                    </button>
                </div>

            </div>

            <hr>
        </div>

        <table id="dataTable" class="table table-striped table-bordered" width="100%" cellspacing="0">
            <thead>
                <tr>
                    <th class="text-center"><input type="checkbox" class="selectAll" name="selectAll" value="all"></th>
                    <th><?php esc_html_e('ID', 'mrpacket'); ?></th>
                    <th><?php esc_html_e('PK', 'mrpacket'); ?></th>
                    <th><?php esc_html_e('Order-ID', 'mrpacket'); ?></th>
                    <th><?php esc_html_e('Reference-ID', 'mrpacket'); ?></th>
                    <th><?php esc_html_e('Order-Status', 'mrpacket'); ?></th>
                    <th><?php esc_html_e('Date created', 'mrpacket'); ?></th>
                    <th><?php esc_html_e('Date changed', 'mrpacket'); ?></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($mrpacketData as $key => $value) { ?>
                    <tr>
                        <td></td>
                        <td><?php echo esc_html($value->id) ?></td>
                        <td><?php echo esc_html($value->pk) ?></td>
                        <td><?php echo esc_html($value->orderId) ?></td>
                        <td><?php echo esc_html($value->orderReference) ?></td>
                        <td><?php echo esc_html($value->orderStatus) ?></td>
                        <td><?php echo esc_html($value->dCreated) ?></td>
                        <td><?php echo esc_html($value->dChanged) ?></td>
                    </tr>
                <?php } ?>
            </tbody>

        </table>

    </form>

</div>