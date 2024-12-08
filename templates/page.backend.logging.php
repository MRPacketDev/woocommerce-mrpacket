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
<div class="wrap mrpacket logging">
    <form method="post" action="<?php esc_url($adminURL) ?>">
        <?php wp_nonce_field('mrpacket_logging', 'mrpacket_logging_table_nonce'); ?>

        <div class="container-fluid intro">
            <div class="row">
                <div>
                    <h1><?php esc_html_e('Logs of Plugin', 'mrpacket'); ?></h1>
                </div>
            </div>

            <div class="row">
                <div class="buttons">
                    <button name="download" type="submit" value="1" class="btn btn-primary">
                        <i class="fa fa-download"></i> <?php esc_html_e('Download logs', 'mrpacket'); ?>
                    </button>
                    <button name="clear" type="submit" value="1" class="btn btn-danger">
                        <i class="fa fa-download"></i> <?php esc_html_e('Clear logs', 'mrpacket'); ?>
                    </button>
                </div>
            </div>

            <hr>
        </div>

        <table id="logTable" class="table table-striped table-bordered" width="100%" cellspacing="0">
            <thead>
                <tr>
                    <th><?php esc_html_e('Id', 'mrpacket'); ?></th>
                    <th><?php esc_html_e('Type', 'mrpacket'); ?></th>
                    <th><?php esc_html_e('message', 'mrpacket'); ?></th>
                    <th><?php esc_html_e('dCreated', 'mrpacket'); ?></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($loggingData as $key => $value) { ?>
                    <tr>
                        <td><?php echo esc_html($value->id) ?></td>
                        <td><?php echo esc_html($value->type) ?></td>
                        <td><?php echo esc_html($value->message) ?></td>
                        <td><?php echo esc_html(gmdate('d.m.Y H:i:s', strtotime($value->dCreated))) ?></td>
                    </tr>
                <?php } ?>
            </tbody>

        </table>

    </form>

</div>