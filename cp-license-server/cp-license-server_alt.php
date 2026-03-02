<?php
/*
Plugin Name: CP License Server
Description: License API for ClarityPhase (domain binding, expiry, plans).
Version: 0.2.0
Author: ClarityPhase
*/

if (!defined('ABSPATH')) { exit; }

// =====================================================
// Constants
// =====================================================
define('CP_LIC_CPT',            'cp_license');
define('CP_LIC_META_KEY',       '_cp_license_key');        // stored on CPT
define('CP_LIC_API_SECRET_OPT', 'cp_license_api_secret');  // option name (admin UI)

// Optional: define this in wp-config.php to avoid storing secrets in DB
// define('CP_LICENSE_SERVER_SECRET', '...');

function cp_lic_allowed_plans() {
    return ['lite','pro','enterprise'];
}

// =====================================================
// Activation: flush rewrites (CPT + REST not strictly needed, but safe for CPT)
// =====================================================
register_activation_hook(__FILE__, function() {
    cp_lic_register_cpt();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// =====================================================
// 1) CPT "Lizenzen"
// =====================================================
add_action('init', 'cp_lic_register_cpt');
function cp_lic_register_cpt() {
    register_post_type(CP_LIC_CPT, [
        'label'           => 'Lizenzen',
        'public'          => false,
        'show_ui'         => true,
        'menu_icon'       => 'dashicons-admin-network',
        'supports'        => ['title'],
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ]);
}

// =====================================================
// 2) License Meta Box (Plan, Expiry, Status, Domain Binding)
// =====================================================
add_action('add_meta_boxes', function() {
    add_meta_box('cp_lic_box', 'Lizenzdaten', 'cp_lic_render_metabox', CP_LIC_CPT, 'normal', 'high');
});

function cp_lic_render_metabox($post) {
    if (!current_user_can('manage_options')) {
        echo '<p>Keine Berechtigung.</p>';
        return;
    }

    wp_nonce_field('cp_lic_save', 'cp_lic_nonce');

    $key    = (string) get_post_meta($post->ID, CP_LIC_META_KEY, true);
    $plan   = (string) get_post_meta($post->ID, '_cp_plan', true);
    $status = (string) get_post_meta($post->ID, '_cp_status', true);
    $exp    = (string) get_post_meta($post->ID, '_cp_expires', true); // YYYY-MM-DD
    $maxd   = (int)    get_post_meta($post->ID, '_cp_max_domains', true);
    $domains= (array)  get_post_meta($post->ID, '_cp_domains', true);

    if (!is_array($domains)) $domains = [];
    $domains_txt = implode("\n", array_map('sanitize_text_field', $domains));

    if ($plan === '')   $plan = 'pro';
    if ($status === '') $status = 'active';
    if ($maxd <= 0)     $maxd = 1;

    echo '<table class="form-table"><tbody>';

    echo '<tr><th scope="row"><label for="cp_license_key">Lizenzschlüssel</label></th><td>';
    echo '<input type="text" class="regular-text" id="cp_license_key" name="cp_license_key" value="' . esc_attr($key) . '" placeholder="z.B. CP-PRO-XXXX-YYYY" />';
    echo '<p class="description">Muss eindeutig sein. Wird im Kunden-Plugin hinterlegt.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="cp_plan">Plan</label></th><td>';
    echo '<select id="cp_plan" name="cp_plan">';
    foreach (cp_lic_allowed_plans() as $p) {
        $sel = selected($plan, $p, false);
        echo '<option value="'.esc_attr($p).'" '.$sel.'>'.esc_html($p).'</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="cp_status">Status</label></th><td>';
    echo '<select id="cp_status" name="cp_status">';
    $statuses = ['active' => 'active', 'paused' => 'paused', 'revoked' => 'revoked'];
    foreach ($statuses as $k => $label) {
        $sel = selected($status, $k, false);
        echo '<option value="'.esc_attr($k).'" '.$sel.'>'.esc_html($label).'</option>';
    }
    echo '</select>';
    echo '<p class="description">paused/revoked liefern immer <code>valid=false</code>.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="cp_expires">Ablaufdatum</label></th><td>';
    echo '<input type="date" id="cp_expires" name="cp_expires" value="' . esc_attr($exp) . '" />';
    echo '<p class="description">Leer = kein Ablauf (nicht empfohlen). Format: YYYY-MM-DD.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="cp_max_domains">Max. Domains</label></th><td>';
    echo '<input type="number" min="1" step="1" id="cp_max_domains" name="cp_max_domains" value="' . esc_attr($maxd) . '" />';
    echo '<p class="description">Wie viele Domains diese Lizenz binden darf.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="cp_domains">Gebundene Domains</label></th><td>';
    echo '<textarea id="cp_domains" name="cp_domains" rows="5" style="width:420px" placeholder="example.com&#10;www.example.com">' . esc_textarea($domains_txt) . '</textarea>';
    echo '<p class="description">Eine Domain pro Zeile. Wird automatisch ergänzt, wenn Auto-Binding greift.</p>';
    echo '</td></tr>';

    echo '</tbody></table>';
}

add_action('save_post_' . CP_LIC_CPT, function($post_id) {
    if (!current_user_can('manage_options')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['cp_lic_nonce']) || !wp_verify_nonce($_POST['cp_lic_nonce'], 'cp_lic_save')) return;

    $key = isset($_POST['cp_license_key']) ? trim((string)$_POST['cp_license_key']) : '';
    $key = preg_replace('/\s+/', '', $key);
    update_post_meta($post_id, CP_LIC_META_KEY, $key);

    $plan = isset($_POST['cp_plan']) ? sanitize_key($_POST['cp_plan']) : 'lite';
    if (!in_array($plan, cp_lic_allowed_plans(), true)) $plan = 'lite';
    update_post_meta($post_id, '_cp_plan', $plan);

    $status = isset($_POST['cp_status']) ? sanitize_key($_POST['cp_status']) : 'active';
    if (!in_array($status, ['active','paused','revoked'], true)) $status = 'active';
    update_post_meta($post_id, '_cp_status', $status);

    $exp = isset($_POST['cp_expires']) ? trim((string)$_POST['cp_expires']) : '';
    if ($exp !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) $exp = '';
    update_post_meta($post_id, '_cp_expires', $exp);

    $maxd = isset($_POST['cp_max_domains']) ? (int)$_POST['cp_max_domains'] : 1;
    if ($maxd < 1) $maxd = 1;
    update_post_meta($post_id, '_cp_max_domains', $maxd);

    $domains_txt = isset($_POST['cp_domains']) ? (string)$_POST['cp_domains'] : '';
    $domains = array_filter(array_map('cp_lic_normalize_domain', preg_split('/\R+/', $domains_txt)));
    $domains = array_values(array_unique($domains));
    update_post_meta($post_id, '_cp_domains', $domains);
}, 10);

// =====================================================
// 3) Admin Page: API Secret
// =====================================================
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=' . CP_LIC_CPT,
        'API Secret',
        'API Secret',
        'manage_options',
        'cp_license_secret',
        'cp_lic_render_secret_page'
    );
}, 99);

function cp_lic_render_secret_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['cp_secret_nonce']) && wp_verify_nonce($_POST['cp_secret_nonce'], 'cp_secret_save')) {
        $val = isset($_POST['cp_api_secret']) ? trim((string)$_POST['cp_api_secret']) : '';

        if ($val === '') {
            // 32 bytes -> base64url (no +,/)
            $raw = random_bytes(32);
            $val = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
        }
        update_option(CP_LIC_API_SECRET_OPT, $val, false);

        echo '<div class="notice notice-success"><p>Secret gespeichert.</p></div>';
    }

    $secret = (string) get_option(CP_LIC_API_SECRET_OPT, '');
    echo '<div class="wrap"><h1>CP License API Secret</h1>';
    echo '<form method="post">';
    wp_nonce_field('cp_secret_save', 'cp_secret_nonce');
    echo '<p>Dieses Secret muss das Kunden-Plugin bei jeder Lizenzprüfung mitsenden (Header <code>X-CP-Secret</code>).</p>';
    echo '<input style="width:520px" type="text" name="cp_api_secret" value="' . esc_attr($secret) . '" placeholder="leer lassen zum Generieren" /> ';
    echo '<button class="button button-primary">Speichern</button>';
    echo '</form>';

    if (defined('CP_LICENSE_SERVER_SECRET') && (string)CP_LICENSE_SERVER_SECRET !== '') {
        echo '<p class="description">Hinweis: <code>CP_LICENSE_SERVER_SECRET</code> ist in wp-config.php gesetzt und wird als Fallback genutzt.</p>';
    }

    echo '</div>';
}

// =====================================================
// 4) REST API: /check (Domain binding)
// =====================================================

add_action('rest_api_init', function () {
    register_rest_route('cp-license/v1', '/check', [
        'methods'             => WP_REST_Server::CREATABLE, // POST
        'permission_callback' => 'cp_lic_rest_permission',
        'callback'            => 'cp_license_api_check',
        'args'                => [
            'license_key' => ['required' => true],
            'domain'      => ['required' => true],
        ],
    ]);
});

/**
 * Permission: Shared Secret Header + Rate Limit
 */
function cp_lic_rest_permission(WP_REST_Request $req) {

    // Secret: bevorzugt Option, fallback auf konstante (wenn vorhanden)
    $secret = (string) get_option(CP_LIC_API_SECRET_OPT, '');
    if ($secret === '' && defined('CP_LICENSE_SERVER_SECRET')) {
        $secret = (string) CP_LICENSE_SERVER_SECRET;
    }

    // Header robust lesen
    // WP macht daraus intern lowercase; get_header ist "eigentlich" case-insensitive,
    // aber wir probieren beide Varianten.
    $hdr = (string) $req->get_header('X-CP-Secret');
    if ($hdr === '') {
        $hdr = (string) $req->get_header('x-cp-secret');
    }

    if ($secret === '' || $hdr === '' || !hash_equals($secret, $hdr)) {
        return new WP_Error('cp_unauthorized', 'Unauthorized', ['status' => 401]);
    }

    // Rate Limit: 60 req / minute / IP
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    $rk = 'cp_lic_rl_' . md5($ip);
    $n  = (int) get_transient($rk);

    if ($n >= 60) {
        return new WP_Error('cp_rate_limited', 'Too Many Requests', ['status' => 429]);
    }
    set_transient($rk, $n + 1, 60);

    return true;
}

/**
 * Callback: License Check + Auto-Bind Domain
 */
function cp_license_api_check(WP_REST_Request $req) {

    $body = (array) $req->get_json_params();

    $license_key = isset($body['license_key']) ? trim((string) $body['license_key']) : '';
    $domain_raw  = isset($body['domain']) ? trim((string) $body['domain']) : '';

    if ($license_key === '' || $domain_raw === '') {
        return new WP_REST_Response([
            'valid'   => false,
            'status'  => 'invalid_request',
            'plan'    => 'lite',
            'message' => 'Missing license_key or domain',
        ], 200);
    }

    // Domain normalisieren
    $domain = function_exists('cp_lic_normalize_domain') ? cp_lic_normalize_domain($domain_raw) : strtolower($domain_raw);
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#/.*$#', '', $domain);
    $domain = trim($domain);

    if ($domain === '') {
        return new WP_REST_Response([
            'valid'   => false,
            'status'  => 'invalid_domain',
            'plan'    => 'lite',
            'message' => 'Invalid domain',
        ], 200);
    }

    // Lizenz finden
    if (!function_exists('cp_license_find_by_key')) {
        return new WP_REST_Response([
            'valid'   => false,
            'status'  => 'server_misconfigured',
            'plan'    => 'lite',
            'message' => 'cp_license_find_by_key() missing',
        ], 500);
    }

    $lic = cp_license_find_by_key($license_key);
    if (!$lic) {
        return new WP_REST_Response([
            'valid'   => false,
            'status'  => 'not_found',
            'plan'    => 'lite',
            'message' => 'License not found',
        ], 200);
    }

    // Meta laden
    $plan    = (string) get_post_meta($lic->ID, '_cp_plan', true);
    $status  = (string) get_post_meta($lic->ID, '_cp_status', true);
    $exp     = (string) get_post_meta($lic->ID, '_cp_expires', true);
    $maxd    = (int)    get_post_meta($lic->ID, '_cp_max_domains', true);
    $domains = (array)  get_post_meta($lic->ID, '_cp_domains', true);

    // Defaults/Validation
    if (!in_array($plan, ['lite','pro','enterprise'], true)) $plan = 'lite';
    if (!in_array($status, ['active','paused','revoked'], true)) $status = 'active';
    if ($maxd < 1) $maxd = 1;

    if (!is_array($domains)) $domains = [];
    $domains = array_values(array_unique(array_filter(array_map(function($d){
        $d = strtolower(trim((string)$d));
        $d = preg_replace('#^https?://#', '', $d);
        $d = preg_replace('#/.*$#', '', $d);
        return trim($d);
    }, $domains))));

    // Status Gate
    if ($status !== 'active') {
        return new WP_REST_Response([
            'valid'   => false,
            'status'  => $status,
            'plan'    => 'lite',
            'message' => 'License is not active',
            'expires' => $exp,
        ], 200);
    }

    // Expiry Gate
    if ($exp !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) {
        $today = gmdate('Y-m-d');
        if ($today > $exp) {
            return new WP_REST_Response([
                'valid'   => false,
                'status'  => 'expired',
                'plan'    => 'lite',
                'message' => 'License expired',
                'expires' => $exp,
            ], 200);
        }
    }

    // Domain Binding
    $bound = in_array($domain, $domains, true);

    if (!$bound) {
        if (count($domains) >= $maxd) {
            return new WP_REST_Response([
                'valid'   => false,
                'status'  => 'domain_limit',
                'plan'    => 'lite',
                'message' => 'Domain limit reached',
                'expires' => $exp,
            ], 200);
        }

        // Auto-bind
        $domains[] = $domain;
        $domains   = array_values(array_unique($domains));
        update_post_meta($lic->ID, '_cp_domains', $domains);
        $bound = true;
    }

    return new WP_REST_Response([
        'valid'         => true,
        'status'        => 'valid',
        'plan'          => $plan,
        'message'       => 'OK',
        'expires'       => $exp,
        'domain'        => $domain,
        'domains_bound' => count($domains),
        'max_domains'   => $maxd,
    ], 200);
}

// =====================================================
// Helpers
// =====================================================
function cp_license_find_by_key($key) {
    $key = preg_replace('/\s+/', '', trim((string)$key));
    if ($key === '') return null;

    $q = new WP_Query([
        'post_type'      => CP_LIC_CPT,
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'no_found_rows'  => true,
        'fields'         => 'all',
        'meta_query'     => [
            [
                'key'     => CP_LIC_META_KEY,
                'value'   => $key,
                'compare' => '=',
            ]
        ],
    ]);

    if (!empty($q->posts)) return $q->posts[0];
    return null;
}

function cp_lic_normalize_domain($domain_raw) {
    $d = strtolower(trim((string)$domain_raw));
    if ($d === '') return '';

    // If URL, parse host
    if (preg_match('#^https?://#i', $d)) {
        $p = wp_parse_url($d);
        if (!empty($p['host'])) $d = (string)$p['host'];
    }

    // strip path / port
    $d = preg_replace('#/.*$#', '', $d);
    $d = preg_replace('#:\d+$#', '', $d);
    $d = trim($d, ". \t\n\r\0\x0B");

    // Basic validation (allow subdomains + punycode)
    if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z0-9\-]{2,63}$/', $d)) {
        return '';
    }

    return $d;
}
