<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Section: Courier List (detail page)
 *
 * @var string $locale
 * @var string $kiriof_base_url
 */
?>
<div class="wrap kj-wrap">
    <style><?php include '_section-css-shared.php'; ?></style>

    <?php $kiriof_title = __( 'Courier List', 'kiriminaja-official' ); $kiriof_parent_url = $kiriof_base_url; $kiriof_parent_title = __( 'Settings', 'kiriminaja-official' ); include KIRIOF_DIR . 'templates/_header.php'; ?>
    <hr class="wp-header-end">

    <div class="kj-detail">
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
            <div style="margin-bottom:0.75rem;">
                <button type="button" class="button kj-courier-enable-all"><?php echo esc_html( __( 'Enable All', 'kiriminaja-official' ) ); ?></button>
                <button type="button" class="button kj-courier-disable-all" style="margin-left:0.5rem"><?php echo esc_html( __( 'Disable All', 'kiriminaja-official' ) ); ?></button>
                <span class="kj-courier-status" style="margin-left:0.75rem;vertical-align:middle;font-size:12px;color:#50575e"></span>
            </div>
            <div id="kiriof-courier-list" class="kj-courier-grid">
                <div style="padding:0.5rem;text-align:center;color:#50575e"><span class="spinner is-active" style="float:none;margin:0 8px 0 0"></span><?php echo esc_html( __( 'Loading couriers…', 'kiriminaja-official' ) ); ?></div>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
    <?php include '_section-js-shared.php'; ?>
    jQuery(document).ready(function($) {
        var $list = $('#kiriof-courier-list');
        var $status = $('.kj-courier-status');
        var allCouriers = [];
        var whitelistSet = {};

        function escHtml(value) {
            var element = document.createElement('div');
            element.appendChild(document.createTextNode(value || ''));
            return element.innerHTML;
        }

        function escAttr(value) {
            return (value || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function enabledCountText(count, total) {
            <?php /* translators: %1$s: enabled courier count, %2$s: total courier count. */ ?>
            return '<?php echo esc_js( _x( '%1$s of %2$s enabled', 'courier enabled count', 'kiriminaja-official' ) ); ?>'
                .replace('%1$s', count)
                .replace('%2$s', total);
        }

        function updateStatus() {
            $status.text(enabledCountText(Object.keys(whitelistSet).length, allCouriers.length));
        }

        function render() {
            var html = '';
            allCouriers.forEach(function(courier) {
                var enabled = Object.prototype.hasOwnProperty.call(whitelistSet, courier.code);
                html += '<div class="kj-courier-item"><div class="kj-courier-item-info">';
                html += '<div class="kj-courier-item-name">' + escHtml(courier.name) + '</div>';
                html += '<div class="kj-courier-item-type">' + escHtml(courier.type || '') + '</div></div>';
                html += '<label class="kj-ios-toggle"><input type="checkbox" class="kj-courier-toggle" data-code="' + escAttr(courier.code) + '" data-name="' + escAttr(courier.name) + '" ' + (enabled ? 'checked' : '') + '>';
                html += '<span class="kj-ios-toggle-track"><span class="kj-ios-toggle-thumb"></span></span></label></div>';
            });
            $list.html(html || '<div style="padding:1rem;color:#787c82"><?php echo esc_js( __( 'No couriers are available for this account.', 'kiriminaja-official' ) ); ?></div>');
            updateStatus();
        }

        function renderError(message) {
            var text = message || '<?php echo esc_js( __( 'Could not load couriers. Reload this page and try again.', 'kiriminaja-official' ) ); ?>';
            $list.html('<div class="notice notice-error inline" style="margin:0"><p>' + escHtml(text) + '</p></div>');
            $status.text('');
        }

        function renderSaveError(message) {
            $status.css('color', '#b32d2e').text(message || '<?php echo esc_js( __( 'Could not save courier settings.', 'kiriminaja-official' ) ); ?>');
        }

        function saveWhitelist() {
            var ids = Object.keys(whitelistSet);
            var names = ids.map(function(id) {
                return whitelistSet[id];
            });
            return $.ajax({
                type: 'post',
                url: kiriofAjaxRoute(),
                data: {
                    action: 'kiriof_store_courier_whitelist',
                    data: {
                        whitelist_ids: ids.join(','),
                        whitelist_names: names.join(','),
                        nonce: kiriofAjax.nonce
                    }
                }
            });
        }

        $.ajax({
            type: 'post',
            url: kiriofAjaxRoute(),
            data: {
                action: 'kiriof_get_courier_whitelist',
                data: { nonce: kiriofAjax.nonce }
            }
        }).done(function(response, textStatus, request) {
            var parsed = kiriofParseAjaxResponse(request);
            if (!parsed || parsed.status !== 200 || !parsed.data) {
                renderError(parsed && parsed.message);
                return;
            }

            allCouriers = parsed.data.couriers || [];
            (parsed.data.whitelist_ids || []).forEach(function(id) {
                whitelistSet[id] = true;
            });
            allCouriers.forEach(function(courier) {
                if (whitelistSet[courier.code]) {
                    whitelistSet[courier.code] = courier.name;
                }
            });
            render();
        }).fail(function(request) {
            var parsed = kiriofParseAjaxResponse(request);
            renderError(parsed && parsed.message);
        });

        $list.on('change', '.kj-courier-toggle', function() {
            var $toggle = $(this);
            var code = String($toggle.data('code'));
            var previous = Object.assign({}, whitelistSet);
            if ($toggle.is(':checked')) {
                whitelistSet[code] = String($toggle.data('name'));
            } else {
                delete whitelistSet[code];
            }
            updateStatus();
            saveWhitelist().fail(function(request) {
                whitelistSet = previous;
                render();
                var parsed = kiriofParseAjaxResponse(request);
                renderSaveError(parsed && parsed.message);
            });
        });

        $('.kj-courier-enable-all').on('click', function() {
            var previous = Object.assign({}, whitelistSet);
            allCouriers.forEach(function(courier) {
                whitelistSet[courier.code] = courier.name;
            });
            render();
            saveWhitelist().fail(function(request) {
                whitelistSet = previous;
                render();
                var parsed = kiriofParseAjaxResponse(request);
                renderSaveError(parsed && parsed.message);
            });
        });

        $('.kj-courier-disable-all').on('click', function() {
            var previous = Object.assign({}, whitelistSet);
            whitelistSet = {};
            render();
            saveWhitelist().fail(function(request) {
                whitelistSet = previous;
                render();
                var parsed = kiriofParseAjaxResponse(request);
                renderSaveError(parsed && parsed.message);
            });
        });
    });
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
