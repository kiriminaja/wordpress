<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Reusable filter selects for transaction list.
 *
 * @param string $kiriof_filter_suffix  '_1' for top tablenav, '_2' for bottom.
 * @param bool   $kiriof_show_apply  Whether to render the Apply button.
 * @var string $kiriof_month_filter
 * @var string $kiriof_cod_filter
 * @var string $kiriof_courier_filter
 * @var array  $kiriof_monthOptions
 * @var array  $kiriof_couriers
 */
$kiriof_filter_suffix  = $kiriof_filter_suffix ?? '_1';
$kiriof_show_apply     = $kiriof_show_apply ?? true;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
$kiriof_month_filter = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : '';
?>

<select id="month_search<?php echo esc_attr( $kiriof_filter_suffix ); ?>">
    <option value="" <?php echo empty( $kiriof_month_filter ) ? 'selected' : ''; ?>><?php esc_html_e( 'All Dates', 'kiriminaja-official' ); ?></option>
    <?php
    if ( ! empty( $kiriof_monthOptions ) && count($kiriof_monthOptions) > 0 ) {
        foreach ($kiriof_monthOptions as $kiriof_key => $kiriof_value) {
            echo '<option value="' . esc_attr($kiriof_key) . '" ' . ( $kiriof_month_filter === $kiriof_key ? 'selected' : '' ) . '>' . esc_html($kiriof_value) . '</option>';
        }
    }
    ?>
</select>

<select id="cod_search<?php echo esc_attr( $kiriof_filter_suffix ); ?>">
    <option value="" <?php echo empty($kiriof_cod_filter) ? 'selected' : ''; ?>><?php esc_html_e( 'All Payment', 'kiriminaja-official' ); ?></option>
    <option value="1" <?php echo $kiriof_cod_filter === '1' ? 'selected' : ''; ?>><?php esc_html_e( 'COD', 'kiriminaja-official' ); ?></option>
    <option value="0" <?php echo $kiriof_cod_filter === '0' ? 'selected' : ''; ?>><?php esc_html_e( 'Non-COD', 'kiriminaja-official' ); ?></option>
</select>

<select id="courier_search<?php echo esc_attr( $kiriof_filter_suffix ); ?>">
    <option value="" <?php echo empty($kiriof_courier_filter) ? 'selected' : ''; ?>><?php esc_html_e( 'All Couriers', 'kiriminaja-official' ); ?></option>
    <?php
    if ( ! empty( $kiriof_couriers ) ) {
        foreach ( $kiriof_couriers as $kiriof_courier ) {
            $kiriof_courier_label = $kiriof_courier->label ?? strtoupper( $kiriof_courier->service );
            echo '<option value="' . esc_attr( $kiriof_courier->service ) . '" ' . ( $kiriof_courier_filter === $kiriof_courier->service ? 'selected' : '' ) . '>' . esc_html( $kiriof_courier_label ) . '</option>';
        }
    }
    ?>
</select>

<?php if ( $kiriof_show_apply ) : ?>
<button class="button" type="button" onclick="kiriofSubmitFilters<?php echo esc_attr( $kiriof_filter_suffix === '_1' ? '' : 'Bottom' ); ?>()"><?php esc_html_e( 'Apply', 'kiriminaja-official' ); ?></button>
<?php endif; ?>
