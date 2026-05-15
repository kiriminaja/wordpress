<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="cancel-transaction-modal" class="kj-hidden">
    <div class="modal-container">
        <div style="width: 100%; max-width: 400px;background-color: #f0f0f1" tabindex="0" class="media-modal" role="dialog">
            <div class="media-modal-container">
                <div class="closebtn-container">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16.0659 8.99481C16.3588 8.70191 16.3588 8.22704 16.0659 7.93415C15.773 7.64125 15.2981 7.64125 15.0052 7.93415L12 10.9393L8.99482 7.93415C8.70192 7.64125 8.22705 7.64125 7.93416 7.93415C7.64126 8.22704 7.64126 8.70191 7.93416 8.99481L10.9394 12L7.93415 15.0052C7.64125 15.2981 7.64125 15.773 7.93415 16.0659C8.22704 16.3588 8.70191 16.3588 8.99481 16.0659L12 13.0607L15.0052 16.0659C15.2981 16.3588 15.773 16.3588 16.0659 16.0659C16.3588 15.773 16.3588 15.2981 16.0659 15.0052L13.0607 12L16.0659 8.99481Z" fill="black"/>
                    </svg>
                </div>
                <div class="content-header" style="background-color: #ffffff">
                    <h1><?php echo esc_html__( 'Cancel Shipment', 'kiriminaja-official' ); ?></h1>
                </div>
                <div class="content-body">

                    <div class="kj-modal-loader kj-hidden">
                        <div style="width: 100%;height: 10rem;position: relative; display: flex">
                            <div class="kj-loader" style="margin: auto"></div>
                        </div>
                    </div>

                    <div class="kj-modal-content">
                        <div style="margin-top: .75rem"></div>
                        <div>
                            <input type="hidden" id="cancel-order-id" value="">
                            <label for="cancel-reason" style="font-weight: 600; display: block; margin-bottom: .5rem">
                                <?php echo esc_html__( 'Reason for Cancellation', 'kiriminaja-official' ); ?> <span style="color: red">*</span>
                            </label>
                            <textarea id="cancel-reason" rows="4" style="width: 100%; resize: vertical" minlength="5" maxlength="200" placeholder="<?php echo esc_attr__( 'Enter reason (min 5, max 200 characters)', 'kiriminaja-official' ); ?>"></textarea>
                            <div style="text-align: right; font-size: 12px; color: #757575">
                                <span id="cancel-reason-count">0</span>/200
                            </div>
                        </div>
                        <div style="margin-top: .75rem; font-weight: 600; color: red" class="err_msg kj-hidden"></div>
                        <div class="row-divider" style="margin-top: .75rem"></div>
                        <div>
                            <button onclick="kjCancelTransactionProcess()" class="button btn-lg" type="button" style="background-color: #d63638; color: #fff; border-color: #d63638">
                                <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                    <span style="margin: auto"><?php echo esc_html__( 'Cancel Shipment', 'kiriminaja-official' ); ?></span>
                                </div>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <div class="media-modal-backdrop"></div>
</div>

<?php ob_start(); ?>
    jQuery(document).ready(function($) {
        // Close modal
        $('#cancel-transaction-modal .closebtn-container').on('click', function(){
            $('#cancel-transaction-modal').addClass('kj-hidden');
            $('#cancel-transaction-modal .err_msg').html('').addClass('kj-hidden');
        });

        // Character count
        $('#cancel-reason').on('input', function(){
            $('#cancel-reason-count').text($(this).val().length);
        });
    });
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
