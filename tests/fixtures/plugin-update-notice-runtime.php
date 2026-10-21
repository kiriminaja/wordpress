<?php
// Isolated WordPress boundary: execute the actual service without WordPress/network access.
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('KIRIOF_VERSION', '2.4.3');
define('KIRIOF_PLUGIN_BASENAME', 'kiriminaja-official/kiriminaja-official.php');
$scenario = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$calls = array();
$writes = array();
$nonce_actions = array();
$compatibility_calls = array();
$hooks = array();
function add_action($hook, $callback) { global $hooks; $hooks[$hook][] = $callback; }
$meta = $scenario['dismissed'] ?? '';
$cache = array();
if (!empty($scenario['old_cache'])) {
    $cache['kiriof_wordpress_org_plugin_info'] = (object) array('version' => '9.0.0');
}
if (isset($scenario['cached_info'])) {
    $cache['kiriof_wordpress_org_plugin_info_v2'] = (object) $scenario['cached_info'];
}
if (!empty($scenario['native'])) {
    $cache['update_plugins'] = (object) array('response' => array(KIRIOF_PLUGIN_BASENAME => (object) ($scenario['native_response'] ?? array('new_version' => '2.4.6', 'package' => 'https://downloads.wordpress.org/plugin/kiriminaja-official.2.4.6.zip'))));
}
function is_multisite() { global $scenario; return !empty($scenario['multisite']); }
function is_network_admin() { global $scenario; return !empty($scenario['network_admin']); }
if (empty($scenario['compatibility_helpers_unavailable'])) {
    function is_php_version_compatible($required) { global $scenario, $compatibility_calls; $compatibility_calls['php'] = $required; return $scenario['php_compatible'] ?? true; }
    function is_wp_version_compatible($required) { global $scenario, $compatibility_calls; $compatibility_calls['wp'] = $required; return $scenario['wp_compatible'] ?? true; }
}
function __($text, $domain) { return $text; }
function current_user_can($cap) { global $scenario; return in_array($cap, $scenario['caps'] ?? array('update_plugins'), true); }
function get_current_screen() { global $scenario; return ($scenario['screen'] ?? '') === 'none' ? null : (object) array('id' => $scenario['screen'] ?? 'plugins'); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_unslash($value) { return stripslashes($value); }
function wp_strip_all_tags($value) { return strip_tags(preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', '', $value)); }
function get_site_transient($key) { global $cache; return $cache[$key] ?? false; }
function set_site_transient($key, $value, $ttl) { global $cache, $writes; $cache[$key] = $value; $writes[] = array('key' => $key, 'ttl' => $ttl); }
function plugins_api($action, $args) {
    global $scenario, $calls;
    $calls[] = array('action' => $action, 'args' => $args);
    if (!empty($scenario['failure'])) { return (object) array('wp_error' => true); }
    return (object) array('slug' => $scenario['slug'] ?? 'kiriminaja-official', 'version' => $scenario['version'] ?? '2.4.6', 'download_link' => $scenario['download'] ?? 'https://downloads.wordpress.org/plugin/kiriminaja-official.2.4.6.zip', 'homepage' => 'https://evil.example/', 'sections' => array('changelog' => $scenario['html'] ?? '<h4>2.4.6</h4><ul><li>fix(payments): reconcile QRIS webhook payment status (#290)</li></ul><p>Full Changelog: https://example.test/compare</p><h4>2.4.5</h4><p>OLD RELEASE SECRET</p>'));
}
function is_wp_error($value) { return !empty($value->wp_error); }
function esc_url_raw($value) { return $value; }
function wp_parse_url($value, $part) { return parse_url($value, $part); }
function admin_url($path) { return 'https://site.test/wp-admin/' . $path; }
function self_admin_url($path) { return admin_url((is_network_admin() ? 'network/' : '') . $path); }
function add_query_arg($key, $value = null, $url = null) {
    if (is_array($key)) { $args = $key; $url = $value; } else { $args = array($key => $value); }
    $url = $url ?? 'https://site.test/wp-admin/plugins.php';
    parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
    return strtok($url, '?') . '?' . http_build_query(array_merge($query, $args));
}
function wp_create_nonce($action) { global $nonce_actions; $nonce_actions[] = $action; return strpos($action, 'upgrade-plugin_') === 0 ? 'upgrade-valid' : 'valid'; }
// WordPress wp_nonce_url returns an HTML-escaped URL, not a raw JSON URL.
function wp_nonce_url($url, $action) { return htmlspecialchars(add_query_arg('_wpnonce', wp_create_nonce($action), $url), ENT_QUOTES); }
function wp_verify_nonce($nonce, $action) { global $scenario; return $nonce === 'valid' && $action === 'kiriof_dismiss_update_notice_' . ($scenario['dismiss_version'] ?? '2.4.6'); }
function get_current_user_id() { return 7; }
function get_user_meta($user, $key, $single) { global $meta; return $meta; }
function update_user_meta($user, $key, $value) { global $meta; $meta = $value; }
function remove_query_arg($keys) { return 'https://site.test/wp-admin/plugins.php'; }
function wp_safe_redirect($url) { global $meta; echo json_encode(array('dismissed' => $meta, 'redirect' => $url)); }
function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES); }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES); }
require dirname(__DIR__, 2) . '/inc/Base/BaseService.php';
require dirname(__DIR__, 2) . '/inc/Services/PluginUpdateNoticeService.php';
$service = new KiriminAjaOfficial\Services\PluginUpdateNoticeService();
$service->register();
if (isset($scenario['dismiss_url'])) {
    parse_str(parse_url($scenario['dismiss_url'], PHP_URL_QUERY), $_GET);
    $service->kiriof_handle_dismiss_request();
}
if (isset($scenario['nonce'])) {
    $_GET = array('kiriof_dismiss_update_notice' => '1', 'kiriof_update_notice_version' => $scenario['dismiss_version'] ?? '2.4.6', '_wpnonce' => $scenario['nonce']);
    $service->kiriof_handle_dismiss_request();
}
$update = $service->get_toolbar_update();
$service->get_toolbar_update();
ob_start();
$notice_hook = is_network_admin() ? 'network_admin_notices' : 'admin_notices';
foreach ($hooks[$notice_hook] ?? array() as $callback) { $callback(); }
$rendered = ob_get_clean();
echo json_encode(array('update' => $update, 'calls' => $calls, 'writes' => $writes, 'rendered' => $rendered, 'dismissed' => $meta, 'nonce_actions' => $nonce_actions, 'compatibility_calls' => $compatibility_calls, 'hooks' => array_keys($hooks)), JSON_THROW_ON_ERROR);
