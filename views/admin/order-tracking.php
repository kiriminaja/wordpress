<table class="ka-tracking">
    <tbody>
    <tr>
        <th><?php esc_html_e('Order ID', 'kiriminaja'); ?></th>
        <td>:</td>
        <td><?php echo esc_html($order_id) ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e('Pickup ID', 'kiriminaja'); ?></th>
        <td>:</td>
        <td><?php echo esc_html($pickup_id) ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e('Service', 'kiriminaja'); ?></th>
        <td>:</td>
        <td><?php echo esc_html($shipping->get_name()) ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e('AWB', 'kiriminaja'); ?></th>
        <td>:</td>
        <td id="awb"><?php echo esc_html($shipping->get_meta('awb')); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e('Status', 'kiriminaja'); ?></th>
        <td>:</td>
        <td><?php echo isset($tracking['text']) ? esc_html($tracking['text']) : '&ndash;'; ?></td>
    </tr>
    </tbody>
</table>
<?php if (!empty($tracking['histories'])) : ?>
    <a href="#" class="toggle-ka-shipping-histories"><?php esc_html_e('Show/hide histories', 'kiriminaja'); ?></a>
    <div class="ka-shipping-history">
        <ul>
            <?php foreach ($tracking['histories'] as $history) : ?>
                <li>
                    <abbr
                        class="timestamp"><?php echo !empty($history->created_at) ? esc_html(date('Y-m-d H:i', strtotime($history->created_at))) : '&ndash;'; ?></abbr>
                    <p class="description"><?php echo esc_html($history->status); ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
