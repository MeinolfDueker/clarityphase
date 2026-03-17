<?php
/*
Plugin Name: ClarityPhase
Description: Client Portal + Project Workflow (White-Label ready)
Version: 0.9.8
Author: Meinolf Düker DK-Digitalbau
Text Domain: clarityphase
Domain Path: /languages
*/

if (!defined('ABSPATH')) exit;

if (!defined('CLARITYPHASE_VERSION')) {
    define('CLARITYPHASE_VERSION', '0.9.8');
}

add_action('plugins_loaded', function () {
    load_plugin_textdomain(
        'clarityphase',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});

add_action('init', function () {

    register_post_type('cp_project', [
        'labels' => [
            'name' => 'ClarityPhase Projekte',
            'singular_name' => 'Projekt',
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 25,
        'menu_icon' => 'dashicons-chart-line',
        'supports' => ['title'],
        'capability_type' => 'post',
    ]);

});

// Lizenz API URL
if (!defined('CP_LICENSE_API_URL')) {
    define('CP_LICENSE_API_URL', home_url('/wp-json/cp-license/v1/check'));
}

// Shared Secret zwischen Kunden-Plugin & Lizenz-Server
// Wenn Server + Client auf derselben WP laufen, wird das Secret automatisch aus der Server-Option gelesen.
// Optional kannst du es in wp-config.php setzen: define('CP_LICENSE_CLIENT_SECRET', '...');
if (!defined('CP_LICENSE_CLIENT_SECRET')) {
    define('CP_LICENSE_CLIENT_SECRET', '');
}

// -------------------------------------
// Admin: Meta Box für cp_project
// -------------------------------------
add_action('add_meta_boxes', function () {
    add_meta_box(
        'cp_project_details',
        'Projekt Details',
        'cp_render_project_details_metabox',
        'cp_project',
        'normal',
        'high'
    );
});

function cp_render_project_details_metabox($post) {

    $owner_id   = (int) get_post_meta($post->ID, 'cp_owner_id', true);
    $status     = (string) get_post_meta($post->ID, 'cp_status', true);
    $progress   = (int) get_post_meta($post->ID, 'cp_progress', true);
    $next_step  = (string) get_post_meta($post->ID, 'cp_next_step', true);
    $deadline   = (string) get_post_meta($post->ID, 'cp_deadline', true);
    $project_page_id = (int) get_post_meta($post->ID, 'cp_project_page_id', true);
    
    if ($progress < 0) $progress = 0;
    if ($progress > 100) $progress = 100;

    wp_nonce_field('cp_project_save', 'cp_project_nonce');

    // Users (nur Subscriber, optional)
    $users = get_users([
        'role__in' => ['subscriber', 'editor', 'author', 'contributor'],
        'orderby'  => 'display_name',
        'order'    => 'ASC',
        'number'   => 200
    ]);

    $status_choices = [
        'analyse_briefing' => 'Analyse & Briefing',
        'struktur_konzept' => 'Struktur & Konzept',
        'umsetzung'        => 'Umsetzung',
        'review_feedback'  => 'Review & Feedback',
        'launch_abschluss' => 'Launch & Abschluss',
    ];

    ?>
    <style>
      .cp-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;max-width:900px}
      .cp-field{display:flex;flex-direction:column;gap:6px}
      .cp-field label{font-weight:800}
      .cp-field input[type="text"],
      .cp-field input[type="number"],
      .cp-field input[type="date"],
      .cp-field select,
      .cp-field textarea{
        padding:10px 12px;border-radius:10px;border:1px solid rgba(0,0,0,.15);max-width:100%;
      }
      .cp-field textarea{min-height:110px}
      .cp-help{opacity:.7;font-size:12px;margin-top:4px}
      @media(max-width:850px){.cp-grid{grid-template-columns:1fr}}
    </style>

    <div class="cp-grid">

      <div class="cp-field">
        <label for="cp_owner_id">Kunde / Seitenbesitzer</label>
        <select name="cp_owner_id" id="cp_owner_id">
          <option value="">— auswählen —</option>
          <?php foreach ($users as $u): ?>
            <option value="<?php echo (int)$u->ID; ?>" <?php selected($owner_id, (int)$u->ID); ?>>
              <?php echo esc_html($u->display_name . ' (' . $u->user_email . ')'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="cp-help">Wem gehört dieses Projekt? Dieser User sieht es im Portal.</div>
      </div>

     <div class="cp-field">
 	<label for="cp_project_page_id">Projektseite (WordPress Seite)</label>
  	<?php
    		wp_dropdown_pages([
      		'name'              => 'cp_project_page_id',
      		'id'                => 'cp_project_page_id',
      		'selected'          => $project_page_id,
      		'show_option_none'  => '— auswählen —',
      		'option_none_value' => '',
    	]);
     ?>
     <div class="cp-help">Diese Seite öffnet der Button „Zum Projekt“.</div>
   </div>

      <div class="cp-field">
        <label for="cp_status">Projektstatus (Phase)</label>
        <select name="cp_status" id="cp_status">
          <?php foreach ($status_choices as $key => $label): ?>
            <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>>
              <?php echo esc_html($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="cp-help">Die Phase steuert Workflow & Status-Anzeige im Portal.</div>
      </div>

      <div class="cp-field">
        <label for="cp_progress">Fortschritt (%)</label>
        <input type="number" name="cp_progress" id="cp_progress" min="0" max="100" value="<?php echo esc_attr($progress); ?>">
        <div class="cp-help">0–100</div>
      </div>

      <div class="cp-field">
        <label for="cp_deadline">Deadline</label>
        <input type="date" name="cp_deadline" id="cp_deadline" value="<?php echo esc_attr($deadline); ?>">
        <div class="cp-help">Optional – später können wir Reminder bauen.</div>
      </div>

      <div class="cp-field" style="grid-column:1/-1;">
        <label for="cp_next_step">Nächster Schritt</label>
        <textarea name="cp_next_step" id="cp_next_step" placeholder="z.B. Bitte fehlende Bilder bis Freitag hochladen."><?php echo esc_textarea($next_step); ?></textarea>
      </div>

    </div>
    <?php
}

// -------------------------------------
// Admin: Spalten in "ClarityPhase Projekte"
// -------------------------------------

add_filter('manage_cp_project_posts_columns', function ($columns) {

    // Titel behalten, aber neue Spalten ergänzen
    $new = [];

    // Checkbox + Titel zuerst lassen
    if (isset($columns['cb']))    $new['cb'] = $columns['cb'];
    if (isset($columns['title'])) $new['title'] = $columns['title'];

    $new['cp_owner']    = 'Kunde';
    $new['cp_status']   = 'Status';
    $new['cp_progress'] = 'Fortschritt';
    $new['cp_deadline'] = 'Deadline';
    $new['cp_page']     = 'Projektseite';

    // Datum am Ende lassen (falls vorhanden)
    if (isset($columns['date'])) $new['date'] = $columns['date'];

    return $new;
});

add_action('manage_cp_project_posts_custom_column', function ($column, $post_id) {

    if ($column === 'cp_owner') {
        $uid = (int) get_post_meta($post_id, 'cp_owner_id', true);
        if ($uid) {
            $u = get_userdata($uid);
            if ($u) {
                echo esc_html($u->display_name);
                echo '<br><small style="opacity:.75;">' . esc_html($u->user_email) . '</small>';
                return;
            }
        }
        echo '<span style="opacity:.6;">—</span>';
        return;
    }

    if ($column === 'cp_status') {
        $status = (string) get_post_meta($post_id, 'cp_status', true);
    if ($status === '') { echo '<span style="opacity:.6;">—</span>'; return; }

    $labels = [
        'analyse_briefing' => 'Analyse & Briefing',
        'struktur_konzept' => 'Struktur & Konzept',
        'umsetzung'        => 'Umsetzung',
        'review_feedback'  => 'Review & Feedback',
        'launch_abschluss' => 'Launch & Abschluss',
    ];
    $label = $labels[$status] ?? $status;

        echo '<span class="cp-badge cp-badge--status">' . esc_html($label) . '</span>';
        echo '<span class="cp-qe" data-cp-status="' . esc_attr($status) . '" style="display:none"></span>';
    return;
}

    if ($column === 'cp_progress') {
        $p = (int) get_post_meta($post_id, 'cp_progress', true);
        $p = max(0, min(100, $p));

        echo '<strong>' . esc_html($p) . '%</strong>';
        echo '<div class="cp-progress">';
        echo '  <div class="cp-progress__bar" style="width:' . esc_attr($p) . '%"></div>';
        echo '</div>';
        echo '<span class="cp-qe" data-cp-progress="' . esc_attr($p) . '" style="display:none"></span>';
    return;
}

    if ($column === 'cp_deadline') {
    $raw = trim((string) get_post_meta($post_id, 'cp_deadline', true));
    if ($raw === '') { echo '<span style="opacity:.6;">—</span>'; return; }

    // Wir erwarten idealerweise YYYY-MM-DD
    $ts = null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $ts = strtotime($raw . ' 00:00:00');
    } else {
        // Fallback: Versuch zu parsen (falls mal d.m.Y o.ä. drinsteht)
        $try = strtotime($raw);
        if ($try) $ts = $try;
    }

    if (!$ts) { echo esc_html($raw); return; }

    $today = strtotime(date('Y-m-d') . ' 00:00:00');
    $days  = (int) floor(($ts - $today) / 86400);

    // Anzeigeformat
    $display = date('d.m.Y', $ts);

    // Styles je Zustand
    if ($days < 0) {
        // überfällig
        echo '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#ffebee;color:#b71c1c;font-weight:700;font-size:12px;">'
            . esc_html($display) . ' · Überfällig</span>';
        return;
    }

    if ($days === 0) {
        // heute
        echo '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#fff3e0;color:#e65100;font-weight:700;font-size:12px;">'
            . esc_html($display) . ' · Heute</span>';
        return;
    }

    if ($days <= 7) {
        // bald
        $label = ($days === 1) ? 'Tag' : 'Tagen';

        echo '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#fff8e1;color:#8d6e00;font-weight:700;font-size:12px;">'
            . esc_html($display) . ' · in ' . esc_html($days) . ' ' . $label . '</span>';
        return;
}

    // normal
    echo '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#eef2ff;color:#1e3a8a;font-weight:700;font-size:12px;">'
        . esc_html($display) . '</span>';
    return;
}

    if ($column === 'cp_page') {
        $page_id = (int) get_post_meta($post_id, 'cp_project_page_id', true);
        if ($page_id) {
            $title = get_the_title($page_id);
            $link  = get_edit_post_link($page_id);
            if ($link) {
                echo '<a href="' . esc_url($link) . '">' . esc_html($title ?: 'Seite bearbeiten') . '</a>';
            } else {
                echo esc_html($title ?: '—');
            }
            return;
        }
        echo '<span style="opacity:.6;">—</span>';
        return;
    }

}, 10, 2);

add_action('current_screen', function($screen) {
    if (empty($screen) || $screen->base !== 'edit') return;

    $pt = $screen->post_type;

    // 1) Spalten als "sortable" markieren
    add_filter("manage_edit-{$pt}_sortable_columns", function($sortable) {
        $sortable['cp_status']   = 'cp_status';
        $sortable['cp_deadline'] = 'cp_deadline';
        $sortable['cp_progress'] = 'cp_progress';
        return $sortable;
    });

    // 2) Sortierlogik: meta_key + orderby setzen
    add_action('pre_get_posts', function($q) use ($pt) {
        if (!is_admin() || !$q->is_main_query()) return;

        // Nur unsere CPT-Liste
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->base !== 'edit' || $screen->post_type !== $pt) return;

        $orderby = $q->get('orderby');
        if (!$orderby) return;

        if ($orderby === 'cp_progress') {
            $q->set('meta_key', 'cp_progress');
            $q->set('orderby', 'meta_value_num'); // Zahl
            return;
        }

        if ($orderby === 'cp_deadline') {
            $q->set('meta_key', 'cp_deadline');
            // Wenn Deadline als YYYY-MM-DD gespeichert ist, reicht meta_value (String) -> lexikalisch korrekt
            $q->set('orderby', 'meta_value');
            return;
        }

        if ($orderby === 'cp_status') {
            $q->set('meta_key', 'cp_status');
            $q->set('orderby', 'meta_value');
            return;
        }
    });
});

add_action('current_screen', function($screen) {
    if (empty($screen) || $screen->base !== 'edit') return;
    $pt = $screen->post_type;

    // Dropdown anzeigen
    add_action('restrict_manage_posts', function() use ($pt) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== $pt) return;

        $current = isset($_GET['cp_status_filter']) ? sanitize_text_field($_GET['cp_status_filter']) : '';

        $options = [
            ''                 => 'Alle Status',
            'analyse_briefing' => 'Analyse & Briefing',
            'struktur_konzept' => 'Struktur & Konzept',
            'umsetzung'        => 'Umsetzung',
            'review_feedback'  => 'Review & Feedback',
            'launch_abschluss' => 'Launch & Abschluss',
        ];

        echo '<select name="cp_status_filter">';
        foreach ($options as $val => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($val),
                selected($current, $val, false),
                esc_html($label)
            );
        }
        echo '</select>';

        $current_deadline = isset($_GET['cp_deadline_filter']) ? sanitize_text_field($_GET['cp_deadline_filter']) : '';

        echo '<select name="cp_deadline_filter" style="margin-left:8px;">';
        echo '<option value=""' . selected($current_deadline, '', false) . '>Alle Deadlines</option>';
        echo '<option value="overdue"' . selected($current_deadline, 'overdue', false) . '>Überfällig</option>';
        echo '<option value="today"' . selected($current_deadline, 'today', false) . '>Heute</option>';
        echo '<option value="next7"' . selected($current_deadline, 'next7', false) . '>Nächste 7 Tage</option>';
        echo '<option value="next30"' . selected($current_deadline, 'next30', false) . '>Nächste 30 Tage</option>';
        echo '<option value="none"' . selected($current_deadline, 'none', false) . '>Ohne Deadline</option>';
        echo '</select>';

    });

    // Filter in Query anwenden
    add_action('pre_get_posts', function($q) use ($pt) {
        if (!is_admin() || !$q->is_main_query()) return;

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->base !== 'edit' || $screen->post_type !== $pt) return;

        $filter = isset($_GET['cp_status_filter']) ? sanitize_text_field($_GET['cp_status_filter']) : '';
        if ($filter === '') return;

        $q->set('meta_query', [
            [
                'key'     => 'cp_status',
                'value'   => $filter,
                'compare' => '=',
            ]
        ]);
    });
});

add_action('current_screen', function($screen) {
    if (empty($screen) || $screen->base !== 'edit') return;
    $pt = $screen->post_type;

    add_action('quick_edit_custom_box', function($column_name, $post_type) use ($pt) {
        if ($post_type !== $pt) return;
        if (!in_array($column_name, ['cp_status', 'cp_progress'], true)) return;

        // Nur einmal UI ausgeben (bei cp_status)
        if ($column_name !== 'cp_status') return;

        wp_nonce_field('cp_quickedit_save', 'cp_quickedit_nonce');

        ?>
        <fieldset class="inline-edit-col-right">
          <div class="inline-edit-col">
            <label class="alignleft">
              <span class="title">Status</span>
              <select name="cp_status">
                <option value="">—</option>
                <option value="analyse_briefing">Analyse &amp; Briefing</option>
                <option value="struktur_konzept">Struktur &amp; Konzept</option>
                <option value="umsetzung">Umsetzung</option>
                <option value="review_feedback">Review &amp; Feedback</option>
                <option value="launch_abschluss">Launch &amp; Abschluss</option>
              </select>
            </label>

            <label class="alignleft" style="margin-left:12px;">
              <span class="title">Fortschritt (%)</span>
              <input type="number" name="cp_progress" min="0" max="100" step="1" style="width:90px;">
            </label>
          </div>
        </fieldset>
        <?php
    }, 10, 2);

    // Speichern (Quick Edit + normal speichern)
    add_action('save_post', function($post_id) use ($pt) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['post_type']) || $_POST['post_type'] !== $pt) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (!isset($_POST['cp_quickedit_nonce']) || !wp_verify_nonce($_POST['cp_quickedit_nonce'], 'cp_quickedit_save')) {
            return;
        }

        if (isset($_POST['cp_status'])) {
            $status = sanitize_text_field($_POST['cp_status']);
            update_post_meta($post_id, 'cp_status', $status);
        }

        if (isset($_POST['cp_progress'])) {
            $p = (int) $_POST['cp_progress'];
            $p = max(0, min(100, $p));
            update_post_meta($post_id, 'cp_progress', $p);
        }
    });
});

// -------------------------------------
// Speichern der Meta-Felder
// -------------------------------------
add_action('save_post_cp_project', function ($post_id) {

    // Autosave / Rechte / Nonce
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['cp_project_nonce']) || !wp_verify_nonce($_POST['cp_project_nonce'], 'cp_project_save')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $owner_id  = isset($_POST['cp_owner_id']) ? (int) $_POST['cp_owner_id'] : 0;
    $status    = isset($_POST['cp_status']) ? sanitize_text_field((string)$_POST['cp_status']) : '';
    $progress  = isset($_POST['cp_progress']) ? (int) $_POST['cp_progress'] : 0;
    $deadline  = isset($_POST['cp_deadline']) ? sanitize_text_field((string)$_POST['cp_deadline']) : '';
    $next_step = isset($_POST['cp_next_step']) ? wp_kses_post((string)$_POST['cp_next_step']) : '';
    $project_page_id = isset($_POST['cp_project_page_id']) ? (int) $_POST['cp_project_page_id'] : 0;

    if ($progress < 0) $progress = 0;
    if ($progress > 100) $progress = 100;

    update_post_meta($post_id, 'cp_owner_id', $owner_id);
    update_post_meta($post_id, 'cp_status', $status);
    update_post_meta($post_id, 'cp_progress', $progress);
    update_post_meta($post_id, 'cp_deadline', $deadline);
    update_post_meta($post_id, 'cp_next_step', $next_step);
    update_post_meta($post_id, 'cp_project_page_id', $project_page_id);

});

/**
 * Hinweis:
 * Dieses MVP setzt voraus, dass ACF aktiv ist und die Felder existieren:
 * - seitenbesitzer (User ID)
 * - projekt_status (Select)
 * - naechster_schritt (Text)
 * - fortschritt_prozent (Number)
 */

// ------------------------------------------------------
// 1) Helpers – Projektseite des eingeloggten Users finden
// ------------------------------------------------------

function cp_get_project_page_id_for_current_user() {
    if (!is_user_logged_in()) return 0;

    $user_id = get_current_user_id();

    // WICHTIG: Trage hier den SLUG deiner "Kundenbereich"-Parent-Seite ein:
    $parent_slug = 'kundenbereich';

    $parent = get_page_by_path($parent_slug);
    if (!$parent) return 0;

    $q = new WP_Query([
        'post_type'      => 'page',
        'posts_per_page' => 1,
        'post_parent'    => $parent->ID,
        'meta_query'     => [[
            'key'     => 'seitenbesitzer',
            'value'   => (int)$user_id,
            'compare' => '='
        ]]
    ]);

    if ($q->have_posts()) {
        return (int)$q->posts[0]->ID;
    }

    return 0;
}

// ------------------------------------
// 2) Shortcodes – Portal-Ausgabe (MVP)
// ------------------------------------

add_shortcode('clarityphase_dashboard', function () {

    if (!is_user_logged_in()) return '';

    $project_id = function_exists('cp_get_project_id_for_current_user')
        ? (int) cp_get_project_id_for_current_user()
        : 0;

    if (!$project_id) {
        return '<div class="cp-empty">Kein Projekt gefunden. Bitte Admin kontaktieren.</div>';
    }

    $user = wp_get_current_user();
    $name = $user->first_name ?: ($user->display_name ?: $user->user_login);

    // Projekt-URL (bevorzugt Projektseite, sonst Portal-URL aus Settings)
    $page_id = (int) get_post_meta($project_id, 'cp_project_page_id', true);
    $url = $page_id ? get_permalink($page_id) : (function_exists('cp_setting') ? cp_setting('portal_url', home_url('/portal/')) : home_url('/portal/'));

    // Branding
    $brand_name = function_exists('cp_setting') ? (string) cp_setting('brand_name', 'ClarityPhase') : 'ClarityPhase';
    $logo_id    = function_exists('cp_setting') ? (int) cp_setting('logo_id', 0) : 0;
    $logo_url   = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';

    ob_start();
    ?>
    <div class="dk-card cp-card cp-dashboard">
        <div class="cp-dashboard__header">
            <?php if ($logo_url) : ?>
                <img class="cp-dashboard__logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($brand_name); ?>">
            <?php else : ?>
                <div class="cp-dashboard__brand"><?php echo esc_html($brand_name); ?></div>
            <?php endif; ?>

            <div class="cp-dashboard__title">Willkommen, <?php echo esc_html($name); ?></div>
        </div>

        <div class="cp-dashboard__actions">
            <a class="cp-btn" href="<?php echo esc_url($url); ?>">Zum Projekt</a>
            <?php echo do_shortcode('[clarityphase_logout_button label="Abmelden" redirect="/kundenbereich/"]'); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
});
   
add_shortcode('clarityphase_status', function () {

    $project_id = cp_get_project_id_for_current_user();
    if (!$project_id) return '';

    $status = (string) get_post_meta($project_id, 'cp_status', true);
    if ($status === '') return '';

    $labels = [
        'analyse_briefing' => 'Analyse & Briefing',
        'struktur_konzept' => 'Struktur & Konzept',
        'umsetzung'        => 'Umsetzung',
        'review_feedback'  => 'Review & Feedback',
        'launch_abschluss' => 'Launch & Abschluss',
    ];
    $label = $labels[$status] ?? $status;

    return '<span class="cp-status-pill cp-status-' . esc_attr($status) . '">' . esc_html($label) . '</span>';
});

add_shortcode('clarityphase_next_step', function () {

    $project_id = cp_get_project_id_for_current_user();
    if (!$project_id) return '';

    $next = (string) get_post_meta($project_id, 'cp_next_step', true);
    $next = trim($next);

    ob_start();
    ?>
    <div class="cp-nextstep">
      <div class="cp-nextstep-head">
        <span class="cp-nextstep-dot" aria-hidden="true"></span>
        <span class="cp-nextstep-title">Nächster Schritt</span>
      </div>

      <div class="cp-nextstep-body">
        <?php if ($next !== ''): ?>
          <?php echo wp_kses_post(wpautop($next)); ?>
        <?php else: ?>
          <div class="cp-nextstep-empty">Aktuell ist kein nächster Schritt hinterlegt.</div>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('clarityphase_workflow', function () {

    $project_id = cp_get_project_id_for_current_user();
    if (!$project_id) return '';

    $current = (string) get_post_meta($project_id, 'cp_status', true);
    if ($current === '') return '';

    $steps = [
        'analyse_briefing' => 'Analyse & Briefing',
        'struktur_konzept' => 'Struktur & Konzept',
        'umsetzung'        => 'Umsetzung',
        'review_feedback'  => 'Review & Feedback',
        'launch_abschluss' => 'Launch & Abschluss',
    ];

    $keys = array_keys($steps);
    $current_index = array_search($current, $keys, true);

    ob_start();
    ?>
    <div class="cp-card">
      <strong>Workflow</strong>
      <div class="cp-flow" style="margin-top:12px;display:grid;gap:10px;">
        <?php
        $i = 0;
        foreach ($steps as $key => $label):
            $state = 'todo';
            if ($current_index !== false) {
                if ($i < $current_index) $state = 'done';
                if ($i === $current_index) $state = 'active';
            }
        ?>
          <div class="cp-flow-item cp-flow-<?php echo esc_attr($state); ?>" style="display:flex;align-items:center;gap:10px;">
            <span class="cp-dot" style="width:12px;height:12px;border-radius:999px;display:inline-block;"></span>
            <span style="font-weight:800;"><?php echo esc_html($label); ?></span>
          </div>
        <?php
          $i++;
        endforeach;
        ?>
      </div>

      <style>
        .cp-flow-done .cp-dot{background:#0b1630;opacity:.85}
        .cp-flow-active .cp-dot{background:#0b1630}
        .cp-flow-todo .cp-dot{background:rgba(11,22,48,.22)}
        .cp-flow-done span{opacity:.7}
      </style>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('clarityphase_progress', function () {

    $project_id = cp_get_project_id_for_current_user();
    if (!$project_id) return '';

    $p = (int) get_post_meta($project_id, 'cp_progress', true);
    $p = max(0, min(100, $p));

    ob_start();
    ?>
    <div class="cp-progress-card">
      <div class="cp-progress-head">
        <span class="cp-progress-label">Fortschritt</span>
        <span class="cp-progress-value"><?php echo esc_html($p); ?>%</span>
      </div>

      <div class="cp-progress-track" role="progressbar" aria-valuenow="<?php echo esc_attr($p); ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="cp-progress-bar" style="width: <?php echo esc_attr($p); ?>%;"></div>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('clarityphase_deadline', function () {

    $project_id = cp_get_project_id_for_current_user();
    if (!$project_id) return '';

    $deadline = (string) get_post_meta($project_id, 'cp_deadline', true);
    $deadline = trim($deadline);

    if ($deadline === '') return '';

    // Optional: wenn du im Backend als YYYY-MM-DD speicherst, hier hübsch formatieren
    $display = $deadline;

    // Wenn Format YYYY-MM-DD ist, umwandeln auf d.m.Y
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
        $ts = strtotime($deadline);
        if ($ts) $display = date('d.m.Y', $ts);
    }

    return '<div class="cp-deadline"><strong>Deadline:</strong> ' . esc_html($display) . '</div>';
});

add_shortcode('clarityphase_phase_pills', function () {

    $project_id = cp_get_project_id_for_current_user();
    if (!$project_id) return '';

    $current = (string) get_post_meta($project_id, 'cp_status', true);
    if ($current === '') return '';

    $steps = [
        'analyse_briefing' => 'Analyse',
        'struktur_konzept' => 'Konzept',
        'umsetzung'        => 'Umsetzung',
        'review_feedback'  => 'Review',
        'launch_abschluss' => 'Launch',
    ];

    $keys = array_keys($steps);
    $current_index = array_search($current, $keys, true);

    ob_start();
    ?>
    <div class="cp-phase-pills" aria-label="Projektphasen">
      <?php
      $i = 0;
      foreach ($steps as $key => $label):
        $state = 'upcoming';
        if ($current_index !== false) {
            if ($i < $current_index) $state = 'done';
            if ($i === $current_index) $state = 'active';
        }
      ?>
        <span class="cp-phase-pill cp-phase-<?php echo esc_attr($state); ?>">
            <?php if ($state === 'done'): ?>
                <span class="cp-phase-check" aria-hidden="true">✓</span>
            <?php endif; ?>
            <?php echo esc_html($label); ?>
        </span>
      <?php
        $i++;
      endforeach;
      ?>
    </div>
    <?php
    return ob_get_clean();
});

// --------------------------
// 3) Logout Shortcode (MVP)
// --------------------------

add_shortcode('clarityphase_logout_button', function ($atts) {
    if (!is_user_logged_in()) return '';

    $atts = shortcode_atts([
        'label' => 'Abmelden',
        'redirect' => '/kundenbereich/'
    ], $atts);

    $redirect_url = home_url($atts['redirect']);
    $logout_url   = wp_logout_url($redirect_url);

    return '<a class="cp-btn" href="' . esc_url($logout_url) . '">' . esc_html($atts['label']) . '</a>';
});

// -------------------------------------
// Statuswechsel-Mail (ACF-frei, robust)
// Trigger: sobald cp_status Meta geändert wird (Meta-Box / Quick Edit / Bulk)
// -------------------------------------

// Cache für "old -> new" (weil updated_post_meta uns den old value nicht liefert)
$GLOBALS['cp_status_change_cache'] = [];

// Vor dem Speichern: alten Wert merken
add_filter('update_post_metadata', function($check, $object_id, $meta_key, $meta_value, $prev_value) {

    if ($meta_key !== 'cp_status') return $check;
    if (get_post_type($object_id) !== 'cp_project') return $check;

    $old = (string) get_post_meta($object_id, 'cp_status', true);
    $new = is_array($meta_value) ? '' : (string) $meta_value;

    $GLOBALS['cp_status_change_cache'][(int)$object_id] = [
        'old' => $old,
        'new' => $new,
    ];

    return $check;
}, 10, 5);

// Nach dem Speichern: vergleichen und ggf. mailen
add_action('updated_post_meta', function($meta_id, $object_id, $meta_key, $meta_value) {

    if ($meta_key !== 'cp_status') return;
    if (get_post_type($object_id) !== 'cp_project') return;

    $object_id = (int) $object_id;

    if (empty($GLOBALS['cp_status_change_cache'][$object_id])) return;

    $old_status = (string) $GLOBALS['cp_status_change_cache'][$object_id]['old'];
    $new_status = (string) $GLOBALS['cp_status_change_cache'][$object_id]['new'];

    unset($GLOBALS['cp_status_change_cache'][$object_id]);

    if ($new_status === '' || $old_status === $new_status) return;

    // Baseline: erstes Setzen => keine Mail
    $meta_key_old = '_cp_old_status';
    $baseline_key = '_cp_status_baseline_set';

    $baseline = (string) get_post_meta($object_id, $baseline_key, true);
    if ($baseline !== '1') {
        update_post_meta($object_id, $meta_key_old, $new_status);
        update_post_meta($object_id, $baseline_key, '1');
        return;
    }

    // Doppelversand verhindern
    $last_notified = (string) get_post_meta($object_id, '_cp_last_notified_status', true);
    if ($last_notified === $new_status) return;

    // Kunde ermitteln
    $owner_id = (int) get_post_meta($object_id, 'cp_owner_id', true);
    if (!$owner_id) return;

    $user = get_userdata($owner_id);
    if (!$user || empty($user->user_email)) return;

    $labels = [
        'analyse_briefing' => 'Analyse & Briefing',
        'struktur_konzept' => 'Struktur & Konzept',
        'umsetzung'        => 'Umsetzung',
        'review_feedback'  => 'Review & Feedback',
        'launch_abschluss' => 'Launch & Abschluss',
    ];

    $new_label = $labels[$new_status] ?? $new_status;
    $old_label = $labels[$old_status] ?? $old_status;

    // ✅ Timeline-Eintrag auf der Projektseite (Page) schreiben
    if (function_exists('cp_add_timeline_entry')) {
        $page_id = (int) get_post_meta($object_id, 'cp_project_page_id', true);
    if ($page_id) {
        $msg = 'Status geändert: ' . $old_label . ' → ' . $new_label;
        cp_add_timeline_entry($page_id, 'Status', $msg, 0);
    }
}

    // Link zur Projektseite (Page) bevorzugen
    $page_id = (int) get_post_meta($object_id, 'cp_project_page_id', true);
    $project_link = $page_id ? get_permalink($page_id) : get_permalink($object_id);

    $to      = $user->user_email;
    $subject = 'Update zu deinem Projekt: ' . $new_label;

    $message  = "Hallo " . ($user->first_name ?: $user->display_name) . ",\n\n";
    $message .= "Der Status deines Projekts wurde aktualisiert.\n\n";
    $message .= "Vorher: " . $old_label . "\n";
    $message .= "Jetzt:  " . $new_label . "\n\n";
    $message .= "Zum Projekt:\n" . $project_link . "\n\n";
    $message .= "Viele Grüße\nClarityPhase";

    $headers = [
        'From: ClarityPhase <info@clarity-phase.com>',
        'Reply-To: info@clarity-phase.com'
    ];

    $sent = wp_mail($to, $subject, $message, $headers);

    if ($sent) {
        update_post_meta($object_id, $meta_key_old, $new_status);
        update_post_meta($object_id, '_cp_last_notified_status', $new_status);
    } else {
        error_log('ClarityPhase: wp_mail FAILED on cp_status change (cp_project=' . $object_id . ')');
    }

}, 10, 4);

// -------------------------------------
// Projekt für aktuellen User finden (cp_project)
// -------------------------------------
function cp_get_project_id_for_current_user() {

    if (!is_user_logged_in()) return 0;

    $user_id = get_current_user_id();

    $q = new WP_Query([
        'post_type'      => 'cp_project',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'     => 'cp_owner_id',
                'value'   => (int)$user_id,
                'compare' => '='
            ]
        ],
    ]);

    if ($q->have_posts()) {
        return (int) $q->posts[0]->ID;
    }

    return 0;
}

// -------------------------------------
// 5) Workflow
// -------------------------------------

add_shortcode('clarityphase_workflow', function () {

    $project_id = cp_get_project_page_id_for_current_user();
    if (!$project_id || !function_exists('get_field')) return '';

    $current = get_field('projekt_status', $project_id);
    if (!$current) return '';

    // Reihenfolge der 5 Phasen
    $steps = [
        'analyse_briefing' => 'Analyse & Briefing',
        'struktur_konzept' => 'Struktur & Konzept',
        'umsetzung'        => 'Umsetzung',
        'review_feedback'  => 'Review & Feedback',
        'launch_abschluss' => 'Launch & Abschluss',
    ];

    // Aktuellen Index finden
    $keys = array_keys($steps);
    $current_index = array_search($current, $keys, true);

    ob_start();
    ?>
    <div class="cp-card">
      <strong>Workflow</strong>
      <div class="cp-flow" style="margin-top:12px;display:grid;gap:10px;">
        <?php
        $i = 0;
        foreach ($steps as $key => $label):
            $state = 'todo';
            if ($current_index !== false) {
                if ($i < $current_index) $state = 'done';
                if ($i === $current_index) $state = 'active';
            }
        ?>
          <div class="cp-flow-item cp-flow-<?php echo esc_attr($state); ?>" style="display:flex;align-items:center;gap:10px;">
            <span class="cp-dot" style="width:12px;height:12px;border-radius:999px;display:inline-block;"></span>
            <span style="font-weight:700;"><?php echo esc_html($label); ?></span>
          </div>
        <?php
          $i++;
        endforeach;
        ?>
      </div>

      <style>
        .cp-flow-done .cp-dot{background:#111;opacity:.9}
        .cp-flow-active .cp-dot{background:#111}
        .cp-flow-todo .cp-dot{background:rgba(0,0,0,.18)}
        .cp-flow-done span{opacity:.7}
      </style>
    </div>
    <?php
    return ob_get_clean();
});

// -------------------------------------
// Login Redirect (Kunden -> /portal/)
// -------------------------------------
add_filter('login_redirect', function ($redirect_to, $requested_redirect_to, $user) {

    // Wenn Login fehlgeschlagen
    if (is_wp_error($user) || !$user) return $redirect_to;

    // Admins (und alle mit Backend-Rechten) bleiben im Backend
    if (user_can($user, 'manage_options')) {
        return admin_url();
    }

    // Kunden (Subscriber) ins Portal
    if (in_array('subscriber', (array) $user->roles, true)) {
        return home_url('/portal/');
    }

    // Alle anderen Rollen ebenfalls ins Backend (optional)
    return admin_url();

}, 10, 3);

// -------------------------------------
// Backend sperren für Nicht-Admins
// -------------------------------------
add_action('admin_init', function () {

    if (current_user_can('manage_options')) return;

    // Erlaubt: ajax/rest/admin-post (sonst gehen Uploads/Mails manchmal kaputt)
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    if (defined('WP_CLI') && WP_CLI) return;

    // Manche WP-Calls laufen über admin-post.php
    $script = isset($_SERVER['PHP_SELF']) ? basename($_SERVER['PHP_SELF']) : '';
    if ($script === 'admin-post.php') return;

    wp_safe_redirect(home_url('/portal/'));
    exit;

}, 1);

// -------------------------------------
// Login Redirect (Nicht-Admins -> /portal/)
// -------------------------------------
add_filter('login_redirect', function ($redirect_to, $requested_redirect_to, $user) {

    if (is_wp_error($user) || !$user) return $redirect_to;

    // Admins ins Backend
    if (user_can($user, 'manage_options')) {
        return admin_url();
    }

    // Alle anderen immer ins Portal
    return home_url('/portal/');

}, 9999, 3);

// -------------------------------------
// Kunden: Admin-Bar ausblenden
// -------------------------------------
add_filter('show_admin_bar', function ($show) {
    if (current_user_can('manage_options')) return $show; // Admins wie gehabt
    return false; // alle anderen: aus
});

// -------------------------------------
// Frontend: Projektseiten nur für Besitzer (inkl. 2 Ebenen)
// -------------------------------------
add_action('template_redirect', function () {

    if (is_admin()) return;
    if (!is_user_logged_in()) return;

    if (current_user_can('manage_options')) return;

    if (!is_page()) return;

    $post_id = get_queried_object_id();
    if (!$post_id) return;

    $parent_slug = 'kundenbereich';
    $parent = get_page_by_path($parent_slug);
    if (!$parent) return;

    // Prüfen ob Seite im Kundenbereich-Baum liegt (max. 2 Ebenen)
    $current_id = $post_id;
    $is_in_tree = false;

    for ($i = 0; $i < 3; $i++) {
        if ($current_id == $parent->ID) {
            $is_in_tree = true;
            break;
        }
        $current_id = wp_get_post_parent_id($current_id);
        if (!$current_id) break;
    }

    if (!$is_in_tree) return;

    if (!function_exists('get_field')) return;

    $owner_id = (int) get_field('seitenbesitzer', $post_id);

    if (!$owner_id || $owner_id !== get_current_user_id()) {
        wp_safe_redirect(home_url('/portal/'));
        exit;
    }

});

// -------------------------------------
// Upload + Feedback (Shortcode)
// -------------------------------------
add_shortcode('clarityphase_upload', function () {

    if (!is_user_logged_in()) return '';

    $project_id = function_exists('cp_get_project_page_id_for_current_user')
        ? cp_get_project_page_id_for_current_user()
        : 0;

    if (!$project_id) {
        return '<div class="cp-upload-msg">Kein Projekt gefunden.</div>';
    }

    $out = '';

    // Form submitted?
    if (!empty($_POST['cp_upload_nonce']) && wp_verify_nonce($_POST['cp_upload_nonce'], 'cp_upload')) {

        $feedback = isset($_POST['cp_feedback']) ? wp_kses_post(trim((string)$_POST['cp_feedback'])) : '';

        // Upload file if present
        if (!empty($_FILES['cp_file']['name'])) {

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $attachment_id = media_handle_upload('cp_file', 0);

            if (is_wp_error($attachment_id)) {
                $out .= '<div class="cp-upload-error">Upload fehlgeschlagen: ' . esc_html($attachment_id->get_error_message()) . '</div>';
            } else {

                // Meta speichern (Zuweisung)
                update_post_meta($attachment_id, '_cp_project_id', (int)$project_id);
                update_post_meta($attachment_id, '_cp_user_id', (int)get_current_user_id());

                // Optional: Feedback als Meta am Attachment
                if ($feedback !== '') {
                    update_post_meta($attachment_id, '_cp_feedback', wp_strip_all_tags($feedback));
                }

                // Mail an Admin/Agentur
                $to = get_option('admin_email');
                $user = wp_get_current_user();
                $subject = 'ClarityPhase Upload: ' . ($user->display_name ?: $user->user_login);
                $file_url = wp_get_attachment_url($attachment_id);

                $message  = "Neuer Upload im Kundenportal\n\n";
                $message .= "User: " . ($user->display_name ?: $user->user_login) . "\n";
                $message .= "Projekt-ID: " . $project_id . "\n";
                $message .= "Datei: " . $file_url . "\n\n";
                if ($feedback !== '') {
                    $message .= "Feedback:\n" . wp_strip_all_tags($feedback) . "\n";
                }

                wp_mail($to, $subject, $message);

                $out .= '<div class="cp-upload-success">✅ Danke! Datei & Feedback wurden gesendet.</div>';
            }

        } else {
            // Kein File, aber evtl. Feedback
            if ($feedback !== '') {
                $to = get_option('admin_email');
                $user = wp_get_current_user();
                $subject = 'ClarityPhase Feedback: ' . ($user->display_name ?: $user->user_login);

                $message  = "Neues Feedback im Kundenportal\n\n";
                $message .= "User: " . ($user->display_name ?: $user->user_login) . "\n";
                $message .= "Projekt-ID: " . $project_id . "\n\n";
                $message .= "Feedback:\n" . wp_strip_all_tags($feedback) . "\n";

                wp_mail($to, $subject, $message);

                $out .= '<div class="cp-upload-success">✅ Danke! Feedback wurde gesendet.</div>';
            } else {
                $out .= '<div class="cp-upload-error">Bitte wähle eine Datei aus oder schreibe ein Feedback.</div>';
            }
        }
    }

    ob_start();
    ?>
    <div class="cp-upload-box">
      <?php echo $out; ?>

      <form method="post" enctype="multipart/form-data" class="cp-upload-form">
        <?php wp_nonce_field('cp_upload', 'cp_upload_nonce'); ?>

        <label class="cp-label">Datei hochladen (PDF/JPG/PNG)</label>
        <input type="file" name="cp_file" class="cp-input" />

        <label class="cp-label">Feedback / Hinweis</label>
        <textarea name="cp_feedback" class="cp-textarea" placeholder="Schreib hier dein Feedback oder Hinweise…"></textarea>

        <button type="submit" class="cp-submit">Senden</button>
      </form>
    </div>
    <?php
    return ob_get_clean();
});

// -------------------------------------
// Upload + Feedback (Shortcode)
// -------------------------------------
add_shortcode('clarityphase_upload', function () {

    if (!is_user_logged_in()) return '';

    if (!function_exists('cp_has_plan') || !cp_has_plan('pro')) {
        return '<div class="cp-upload-error">Diese Funktion ist nur in der Pro-Version verfügbar.</div>';
    }

    // Projektseite des eingeloggten Users ermitteln
    $project_id = function_exists('cp_get_project_page_id_for_current_user')
        ? (int) cp_get_project_page_id_for_current_user()
        : 0;

    if (!$project_id) {
        return '<div class="cp-upload-error">Kein Projekt gefunden. Bitte Admin kontaktieren.</div>';
    }

    $notice = '';

    // Formular verarbeitet?
    if (
        isset($_POST['cp_upload_nonce']) &&
        wp_verify_nonce($_POST['cp_upload_nonce'], 'cp_upload_submit')
    ) {

        $feedback = isset($_POST['cp_feedback']) ? trim((string)$_POST['cp_feedback']) : '';
        $feedback_clean = wp_kses_post($feedback);

        $has_file = !empty($_FILES['cp_file']['name']);

        if (!$has_file && $feedback_clean === '') {
            $notice = '<div class="cp-upload-error">Bitte Datei auswählen oder Feedback eingeben.</div>';
        } else {

            $attachment_id = 0;
            $file_url = '';

            // Datei-Upload (optional)
            if ($has_file) {

                // Dateitypen erlauben (einfach & sicher)
                $allowed = ['pdf','png','jpg','jpeg','webp'];
                $ext = strtolower(pathinfo($_FILES['cp_file']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) {
                    $notice = '<div class="cp-upload-error">Dateityp nicht erlaubt (nur PDF/JPG/PNG/WebP).</div>';
                } else {

                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';

                    $attachment_id = media_handle_upload('cp_file', 0);

                    if (is_wp_error($attachment_id)) {
                        $notice = '<div class="cp-upload-error">Upload fehlgeschlagen: ' . esc_html($attachment_id->get_error_message()) . '</div>';
                        $attachment_id = 0;
                    } else {
                        // Zuordnung speichern
                        update_post_meta($attachment_id, '_cp_project_id', $project_id);
                        update_post_meta($attachment_id, '_cp_user_id', get_current_user_id());
                        if ($feedback_clean !== '') {
                            update_post_meta($attachment_id, '_cp_feedback', wp_strip_all_tags($feedback_clean));
                        }

                        $file_url = (string) wp_get_attachment_url($attachment_id);
                    }
                }
            }

          // Wenn bisher kein Fehler: Mail schicken + Timeline schreiben
		  if ($notice === '') {

    			$user = wp_get_current_user();
    			$to = get_option('admin_email');
    			$subject = 'ClarityPhase: Upload/Feedback von ' . ($user->display_name ?: $user->user_login);

    			$message  = "Neuer Upload/Feedback im Kundenportal\n\n";
    			$message .= "Kunde: " . ($user->display_name ?: $user->user_login) . "\n";
    			$message .= "Projekt-ID: " . $project_id . "\n";
    			$message .= "Projekt-Link: " . get_permalink($project_id) . "\n\n";

    			if ($file_url !== '') {
        			$message .= "Datei: " . $file_url . "\n\n";
    			}

    			if ($feedback_clean !== '') {
        		$message .= "Feedback:\n" . wp_strip_all_tags($feedback_clean) . "\n";
    			}

    			wp_mail($to, $subject, $message);

    		// ✅ TIMELINE: genau EIN Eintrag pro Aktion
    		if (function_exists('cp_add_timeline_entry')) {
        		if (!empty($attachment_id)) {
            		cp_add_timeline_entry($project_id, 'Upload', $feedback_clean, (int)$attachment_id);
        		} else {
            		cp_add_timeline_entry($project_id, 'Feedback', $feedback_clean, 0);
        }
    }

    $notice = '<div class="cp-upload-success">✅ Danke! Datei/Feedback wurde gesendet.</div>';
}
        }
    }

    ob_start();
    ?>
    <div class="cp-upload-box">
      <?php echo $notice; ?>

      <form method="post" enctype="multipart/form-data" class="cp-upload-form">
        <?php wp_nonce_field('cp_upload_submit', 'cp_upload_nonce'); ?>

        <label class="cp-label">Datei hochladen (PDF/JPG/PNG/WebP)</label>
        <input class="cp-input" type="file" name="cp_file" />

        <label class="cp-label">Feedback / Hinweis</label>
        <textarea class="cp-textarea" name="cp_feedback" placeholder="Schreib hier dein Feedback oder Hinweise…"></textarea>

        <button class="cp-submit" type="submit">Senden</button>
      </form>
      <?php
// ---------------------------
// Letzte Uploads anzeigen
// ---------------------------
$uploads = get_posts([
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'posts_per_page' => 5,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => [
        [
            'key'   => '_cp_project_id',
            'value' => $project_id,
            'compare' => '='
        ],
    ],
]);

if ($uploads) : ?>
  <div class="cp-upload-list">
    <div class="cp-upload-list-title">Letzte Uploads</div>
    <ul>
      <?php foreach ($uploads as $att) :
        $url  = wp_get_attachment_url($att->ID);
        $name = get_the_title($att->ID);
        $date = get_the_date('d.m.Y H:i', $att->ID);
      ?>
        <li>
          <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">
            <?php echo esc_html($name); ?>
          </a>
          <span class="cp-upload-date"><?php echo esc_html($date); ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});

// -------------------------------------
// Timeline Helper (speichert als Kommentar am Projekt)
// -------------------------------------
if (!function_exists('cp_add_timeline_entry')) {
function cp_add_timeline_entry($project_id, $type, $message, $attachment_id = 0) {

    $project_id = (int) $project_id;
    if (!$project_id) return;

    $user = wp_get_current_user();
    $user_id = (int) ($user ? $user->ID : 0);
    $author  = $user ? ($user->display_name ?: $user->user_login) : 'Unbekannt';

    $file_url = '';
    $file_name = '';
    if ($attachment_id) {
        $file_url  = (string) wp_get_attachment_url((int)$attachment_id);
        $file_name = (string) get_the_title((int)$attachment_id);
    }

    // Inhalt für Timeline (HTML ist ok, wird später sicher ausgegeben)
    $content  = '<strong>' . esc_html($type) . '</strong> – ' . esc_html($author) . "<br>";
    if ($file_url) {
        $content .= 'Datei: <a href="' . esc_url($file_url) . '" target="_blank" rel="noopener">'
            . esc_html($file_name ?: $file_url) . "</a><br>";
    }
    if ($message !== '') {
        $content .= 'Feedback: ' . wp_kses_post(wpautop($message));
    }

    wp_insert_comment([
        'comment_post_ID'      => $project_id,
        'comment_content'      => $content,
        'user_id'              => $user_id,
        'comment_author'       => $author,
        'comment_author_email' => $user ? $user->user_email : '',
        'comment_approved'     => 1,
        'comment_type'         => 'clarityphase',
    ]);
}
}

// -------------------------------------
// Admin: Timeline Meta Box anzeigen
// -------------------------------------
add_action('add_meta_boxes', function () {

    // Nur Seiten (Projektseiten sind bei dir Pages)
    add_meta_box(
        'cp_timeline_box',
        'ClarityPhase Timeline',
        'cp_render_timeline_box',
        'page',
        'normal',
        'default'
    );
});

function cp_render_timeline_box($post) {

    // Optional: Nur anzeigen, wenn Seite einen Seitenbesitzer hat (ACF)
    if (function_exists('get_field')) {
        $owner = (int) get_field('seitenbesitzer', $post->ID);
        if (!$owner) {
            echo '<p style="opacity:.7;">Keine Timeline (kein Seitenbesitzer gesetzt).</p>';
            return;
        }
    }

    $comments = get_comments([
        'post_id' => $post->ID,
        'type'    => 'clarityphase',
        'status'  => 'approve',
        'number'  => 50,
        'orderby' => 'comment_date_gmt',
        'order'   => 'DESC',
    ]);

    if (!$comments) {
        echo '<p style="opacity:.7;">Noch keine Einträge.</p>';
        return;
    }

    echo '<div style="display:grid;gap:12px;">';

    foreach ($comments as $c) {
        $date = mysql2date('d.m.Y H:i', $c->comment_date);
        echo '<div style="padding:12px 14px;border:1px solid rgba(0,0,0,.08);border-radius:12px;background:#fff;">';
        echo '<div style="font-weight:800;margin-bottom:6px;">' . esc_html($date) . '</div>';
        // Content ist von uns erzeugt, dennoch sauber ausgeben
        echo '<div>' . wp_kses_post($c->comment_content) . '</div>';
        echo '</div>';
    }

    echo '</div>';
}

add_action('admin_footer', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'edit' || $screen->post_type !== 'cp_project') return;
    ?>
    <script>
    (function($){

      // 1) Quick Edit vorausfüllen
      const oldEdit = inlineEditPost.edit;
      inlineEditPost.edit = function(id){
        oldEdit.apply(this, arguments);

        const postId = (typeof id === "object") ? this.getId(id) : id;
        if (!postId) return;

        const $row  = $("#post-" + postId);
        const $edit = $("#edit-" + postId);

        const status   = $row.find(".cp-qe[data-cp-status]").data("cp-status") || "";
        const progress = $row.find(".cp-qe[data-cp-progress]").data("cp-progress");

        $edit.find('select[name="cp_status"]').val(status);
        if (progress !== undefined) {
          $edit.find('input[name="cp_progress"]').val(progress);
        }
      };

      // 2) Nach "Aktualisieren" die Tabellenzeile aktualisieren
      $(document).on("click", ".inline-edit-save .save", function(){
        const $editRow = $(this).closest("tr.inline-edit-row");
        const postId = $editRow.attr("id") ? $editRow.attr("id").replace("edit-","") : null;
        if (!postId) return;

        const statusVal = $editRow.find('select[name="cp_status"]').val() || "";
        let progressVal = $editRow.find('input[name="cp_progress"]').val();
        progressVal = (progressVal === "" || progressVal === null) ? 0 : parseInt(progressVal, 10);
        if (isNaN(progressVal)) progressVal = 0;
        progressVal = Math.max(0, Math.min(100, progressVal));

        const labels = {
          "analyse_briefing": "Analyse & Briefing",
          "struktur_konzept": "Struktur & Konzept",
          "umsetzung": "Umsetzung",
          "review_feedback": "Review & Feedback",
          "launch_abschluss": "Launch & Abschluss"
        };

        setTimeout(function(){
          const $row = $("#post-" + postId);

          // hidden data aktualisieren
          $row.find(".cp-qe[data-cp-status]").attr("data-cp-status", statusVal).data("cp-status", statusVal);
          $row.find(".cp-qe[data-cp-progress]").attr("data-cp-progress", progressVal).data("cp-progress", progressVal);

          const label = labels[statusVal] || statusVal || "—";
          const statusHtml = (statusVal === "")
            ? '<span style="opacity:.6;">—</span>'
            : '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#0b1630;color:#fff;font-weight:700;font-size:12px;">' + label + '</span>';

          $row.find("td.column-cp_status").html(
            statusHtml + '<span class="cp-qe" data-cp-status="' + statusVal + '" style="display:none"></span>'
          );

          const progressHtml =
            '<strong>' + progressVal + '%</strong>' +
            '<div style="margin-top:6px;height:8px;border-radius:999px;background:rgba(11,22,48,.12);overflow:hidden;max-width:140px;">' +
              '<div style="height:8px;width:' + progressVal + '%;background:#0b1630;"></div>' +
            '</div>' +
            '<span class="cp-qe" data-cp-progress="' + progressVal + '" style="display:none"></span>';

          $row.find("td.column-cp_progress").html(progressHtml);

        }, 600);
      });

    })(jQuery);
    </script>
    <?php
});

// =====================================================
// Bulk Actions: Status für mehrere Projekte setzen
// =====================================================

// Dropdown-Einträge hinzufügen
add_filter('bulk_actions-edit-cp_project', function($actions) {
    $actions['cp_set_status_analyse_briefing'] = 'Status → Analyse & Briefing';
    $actions['cp_set_status_struktur_konzept'] = 'Status → Struktur & Konzept';
    $actions['cp_set_status_umsetzung']        = 'Status → Umsetzung';
    $actions['cp_set_status_review_feedback']  = 'Status → Review & Feedback';
    $actions['cp_set_status_launch_abschluss'] = 'Status → Launch & Abschluss';
    return $actions;
});

// Aktion ausführen
add_filter('handle_bulk_actions-edit-cp_project', function($redirect_url, $action, $post_ids) {

    $map = [
        'cp_set_status_analyse_briefing' => 'analyse_briefing',
        'cp_set_status_struktur_konzept' => 'struktur_konzept',
        'cp_set_status_umsetzung'        => 'umsetzung',
        'cp_set_status_review_feedback'  => 'review_feedback',
        'cp_set_status_launch_abschluss' => 'launch_abschluss',
    ];

    if (!isset($map[$action])) {
        return $redirect_url;
    }

    $status = $map[$action];
    $changed = 0;

    foreach ($post_ids as $post_id) {
        if (!current_user_can('edit_post', $post_id)) continue;
        update_post_meta($post_id, 'cp_status', $status);
        $changed++;
    }

    return add_query_arg(['cp_bulk_changed' => $changed], $redirect_url);
}, 10, 3);

// Erfolgsanzeige
add_action('admin_notices', function() {
    if (!isset($_GET['cp_bulk_changed'])) return;
    $n = (int) $_GET['cp_bulk_changed'];
    echo '<div class="notice notice-success is-dismissible"><p>'
        . esc_html($n) . ' Projekt(e) aktualisiert.'
        . '</p></div>';
});

// ======================================================
// ClarityPhase: Kommentare auf Projektseiten deaktivieren (Elementor-sicher)
// ======================================================

// Prüft, ob aktuelle Seite als Projektseite verknüpft ist
function cp_is_linked_project_page($page_id) {

    $page_id = (int) $page_id;
    if (!$page_id) return false;

    $q = new WP_Query([
        'post_type'      => 'cp_project',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'cp_project_page_id',
                'value'   => $page_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ]
        ],
        'no_found_rows'  => true,
    ]);

    return $q->have_posts();
}

// Kommentare komplett deaktivieren
add_filter('comments_open', function($open, $post_id) {

    if (is_admin()) return $open;

    if (is_page($post_id) && cp_is_linked_project_page($post_id)) {
        return false;
    }

    return $open;

}, 10, 2);

// Bereits geladene Kommentare leeren (greift auch bei Elementor)
add_filter('comments_array', function($comments, $post_id) {

    if (is_admin()) return $comments;

    if (is_page($post_id) && cp_is_linked_project_page($post_id)) {
        return []; // komplett leer
    }

    return $comments;

}, 10, 2);

// =====================================================
// ClarityPhase White-Label (Global) – Settings V1
// =====================================================

/**
 * Default Settings
 */
function cp_settings_defaults() {
    return [
        'brand_name'   => 'ClarityPhase',
        'logo_id'      => 0,
        'accent_color' => '#0b1630',

        'portal_url'   => home_url('/portal/'),
        'support_email'=> get_option('admin_email'),
        'footer_text'  => 'Viele Grüße',

        'from_name'    => 'ClarityPhase',
        'from_email'   => get_option('admin_email'),
        'reply_to'     => get_option('admin_email'),
        'bcc'          => '',

        'license_key' => '',
    ];
}

/**
 * Read setting (safe)
 */
function cp_setting($key, $fallback = null) {
    $opts = get_option('clarityphase_settings', []);
    $opts = is_array($opts) ? $opts : [];
    $defaults = cp_settings_defaults();

    $pro_only_keys = ['brand_name', 'logo_id', 'accent_color', 'from_name', 'footer_text', 'bcc'];
    if (in_array($key, $pro_only_keys, true) && function_exists('cp_has_plan') && !cp_has_plan('pro')) {
        return $defaults[$key] ?? $fallback;
    }

    $val = $opts[$key] ?? $defaults[$key] ?? $fallback;
    return $val;
}

/**
 * Update helpers (optional)
 */
function cp_settings_update($key, $value) {
    $opts = get_option('clarityphase_settings', []);
    $opts = is_array($opts) ? $opts : [];
    $opts[$key] = $value;
    update_option('clarityphase_settings', $opts);
}

/**
 * Sanitize settings
 */
function cp_settings_sanitize($input) {
    $defaults = cp_settings_defaults();
    $out = [];

    $out['brand_name'] = isset($input['brand_name']) ? sanitize_text_field($input['brand_name']) : $defaults['brand_name'];

    $out['logo_id'] = isset($input['logo_id']) ? (int) $input['logo_id'] : 0;

    $accent = isset($input['accent_color']) ? trim((string)$input['accent_color']) : $defaults['accent_color'];
    // very light validation: allow hex like #RRGGBB
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) $accent = $defaults['accent_color'];
    $out['accent_color'] = $accent;

    $out['portal_url'] = isset($input['portal_url']) ? esc_url_raw($input['portal_url']) : $defaults['portal_url'];

    $out['support_email'] = isset($input['support_email']) ? sanitize_email($input['support_email']) : $defaults['support_email'];

    $out['footer_text'] = isset($input['footer_text']) ? sanitize_text_field($input['footer_text']) : $defaults['footer_text'];

    $out['from_name'] = isset($input['from_name']) ? sanitize_text_field($input['from_name']) : $defaults['from_name'];
    $out['from_email'] = isset($input['from_email']) ? sanitize_email($input['from_email']) : $defaults['from_email'];
    $out['reply_to'] = isset($input['reply_to']) ? sanitize_email($input['reply_to']) : $defaults['reply_to'];

    // bcc can be comma separated list
    $bcc = isset($input['bcc']) ? trim((string)$input['bcc']) : '';
    $bcc = preg_replace('/\s+/', '', $bcc);
    $out['bcc'] = $bcc;

    $out['license_key'] = isset($input['license_key']) ? sanitize_text_field($input['license_key']) : '';

    $pro_only_keys = ['brand_name', 'logo_id', 'accent_color', 'from_name', 'footer_text', 'bcc'];
    if (!function_exists('cp_has_plan') || !cp_has_plan('pro')) {
        foreach ($pro_only_keys as $pro_key) {
            $out[$pro_key] = $defaults[$pro_key];
        }
    }

    return $out;
}

// ---------------------------
// Admin Menü + Settings Page
// ---------------------------
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=cp_project',
        'ClarityPhase Einstellungen',
        'Einstellungen',
        'manage_options',
        'clarityphase_settings',
        'cp_render_settings_page'
    );
}, 99);

add_action('admin_init', function() {

    // 1) Settings registrieren
    register_setting('clarityphase_settings_group', 'clarityphase_settings', [
        'type'              => 'array',
        'sanitize_callback' => 'cp_settings_sanitize',
        'default'           => cp_settings_defaults(),
    ]);

    // =====================================================
    // 2) LITE: Basis-Einstellungen (immer sichtbar)
    // =====================================================
    add_settings_section('cp_sec_core', esc_html__('Basis (Lite)', 'clarityphase'), function() {
        echo '<p>Technische Grundeinstellungen für Portal & Versand.</p>';
    }, 'clarityphase_settings');

    add_settings_field('cp_portal_url', esc_html__('Portal URL', 'clarityphase'), 'cp_field_portal_url', 'clarityphase_settings', 'cp_sec_core');
    add_settings_field('cp_support_email', esc_html__('Support E-Mail', 'clarityphase'), 'cp_field_support_email', 'clarityphase_settings', 'cp_sec_core');

    // Mail-Basics: technisch notwendig -> Lite
    add_settings_field('cp_from_email', esc_html__('From E-Mail', 'clarityphase'), 'cp_field_from_email', 'clarityphase_settings', 'cp_sec_core');
    add_settings_field('cp_reply_to', esc_html__('Reply-To', 'clarityphase'), 'cp_field_reply_to', 'clarityphase_settings', 'cp_sec_core');

    // =====================================================
    // 3) Lizenz (immer sichtbar)
    // =====================================================
    add_settings_section('cp_sec_license', esc_html__('Lizenz', 'clarityphase'), function() {
        echo '<p>' . esc_html__('Lizenzprüfung für Pro/Enterprise Features.', 'clarityphase') . '</p>';
    }, 'clarityphase_settings');

    add_settings_field(
        'cp_license_key',
        esc_html__('Lizenzschlüssel', 'clarityphase'),
        'cp_field_license_key',
        'clarityphase_settings',
        'cp_sec_license'
    );

    // =====================================================
    // 4) PRO GATE: ab hier nur wenn Pro/Enterprise aktiv
    // =====================================================
    if (!function_exists('cp_has_plan') || !cp_has_plan('pro')) {

        add_settings_section('cp_sec_locked', esc_html__('Pro Features', 'clarityphase'), function() {
            echo '<div style="padding:14px 16px;background:#fff3cd;border:1px solid #ffeeba;border-radius:8px;max-width:820px;">
                    <strong>' . esc_html__('Pro Version erforderlich', 'clarityphase') . '</strong><br><br>
                    ' . wp_kses_post(__('Branding (Logo/Farben), Brand Name und erweiterte Mail-Anpassungen sind nur in <b>Pro</b> verfügbar.<br>Hinterlege einen gültigen Lizenzschlüssel und klicke auf <b>Lizenz prüfen</b>.', 'clarityphase')) . '
                  </div>';
        }, 'clarityphase_settings');

        return; // stoppt hier -> keine Pro Felder
    }

    // =====================================================
    // 5) PRO: Branding / White-Label
    // =====================================================
    add_settings_section('cp_sec_brand', esc_html__('Branding (Pro)', 'clarityphase'), function() {
        echo '<p>' . esc_html__('White-Label Branding für Portal & E-Mails.', 'clarityphase') . '</p>';
    }, 'clarityphase_settings');

    add_settings_field('cp_brand_name', esc_html__('Brand Name', 'clarityphase'), 'cp_field_brand_name', 'clarityphase_settings', 'cp_sec_brand');
    add_settings_field('cp_logo_id', esc_html__('Logo', 'clarityphase'), 'cp_field_logo', 'clarityphase_settings', 'cp_sec_brand');
    add_settings_field('cp_accent_color', esc_html__('Accent Farbe', 'clarityphase'), 'cp_field_accent', 'clarityphase_settings', 'cp_sec_brand');

    // =====================================================
    // 6) PRO: Mail Branding (nicht technisch nötig)
    // =====================================================
    add_settings_section('cp_sec_mail_pro', esc_html__('E-Mail Branding (Pro)', 'clarityphase'), function() {
        echo '<p>' . esc_html__('Optische/Marken-Anpassungen für E-Mails.', 'clarityphase') . '</p>';
    }, 'clarityphase_settings');

    add_settings_field('cp_from_name', esc_html__('From Name', 'clarityphase'), 'cp_field_from_name', 'clarityphase_settings', 'cp_sec_mail_pro');
    add_settings_field('cp_footer_text', esc_html__('E-Mail Footer Text', 'clarityphase'), 'cp_field_footer_text', 'clarityphase_settings', 'cp_sec_mail_pro');
    add_settings_field('cp_bcc', esc_html__('BCC (optional)', 'clarityphase'), 'cp_field_bcc', 'clarityphase_settings', 'cp_sec_mail_pro');

});

// ---------------------------
// Fields
// ---------------------------
function cp_field_brand_name() {
    $val = esc_attr(cp_setting('brand_name'));
    echo '<input type="text" name="clarityphase_settings[brand_name]" value="'.$val.'" class="regular-text" />';
}

function cp_field_portal_url() {
    $val = esc_attr(cp_setting('portal_url'));
    echo '<input type="url" name="clarityphase_settings[portal_url]" value="'.$val.'" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Wird in Status-Mails verlinkt.', 'clarityphase') . '</p>';
}

function cp_field_support_email() {
    $val = esc_attr(cp_setting('support_email'));
    echo '<input type="email" name="clarityphase_settings[support_email]" value="'.$val.'" class="regular-text" />';
}

function cp_field_footer_text() {
    $val = esc_attr(cp_setting('footer_text'));
    echo '<input type="text" name="clarityphase_settings[footer_text]" value="'.$val.'" class="regular-text" />';
}

function cp_field_from_name() {
    $val = esc_attr(cp_setting('from_name'));
    echo '<input type="text" name="clarityphase_settings[from_name]" value="'.$val.'" class="regular-text" />';
}

function cp_field_from_email() {
    $val = esc_attr(cp_setting('from_email'));
    echo '<input type="email" name="clarityphase_settings[from_email]" value="'.$val.'" class="regular-text" />';
}

function cp_field_reply_to() {
    $val = esc_attr(cp_setting('reply_to'));
    echo '<input type="email" name="clarityphase_settings[reply_to]" value="'.$val.'" class="regular-text" />';
}

function cp_field_bcc() {
    $val = esc_attr(cp_setting('bcc'));
    echo '<input type="text" name="clarityphase_settings[bcc]" value="'.$val.'" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Mehrere Empfänger mit Komma trennen.', 'clarityphase') . '</p>';
}

function cp_field_accent() {
    $val = esc_attr(cp_setting('accent_color'));
    echo '<input type="text" name="clarityphase_settings[accent_color]" value="'.$val.'" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Format: #RRGGBB (z.B. #0b1630)', 'clarityphase') . '</p>';
}

function cp_field_logo() {
    $logo_id = (int) cp_setting('logo_id');
    $url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
    echo '<div style="display:flex;gap:12px;align-items:center;">';
    echo '<div style="width:80px;height:80px;border:1px solid #ddd;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fff;">';
    if ($url) {
        echo '<img src="'.esc_url($url).'" style="max-width:100%;height:auto;" alt="" />';
    } else {
        echo '<span style="opacity:.6;">' . esc_html__('Kein Logo', 'clarityphase') . '</span>';
    }
    echo '</div>';
    echo '<div>';
    echo '<input type="hidden" id="cp_logo_id" name="clarityphase_settings[logo_id]" value="'.esc_attr($logo_id).'" />';
    echo '<button type="button" class="button" id="cp_logo_pick">' . esc_html__('Logo wählen', 'clarityphase') . '</button> ';
    echo '<button type="button" class="button" id="cp_logo_remove">' . esc_html__('Entfernen', 'clarityphase') . '</button>';
    echo '</div>';
    echo '</div>';
    echo '<p class="description">' . esc_html__('Logo wird später im Portal/Emails genutzt (V1: Settings + CSS).', 'clarityphase') . '</p>';
}

// ---------------------------
// Settings Page render
// ---------------------------
function cp_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('ClarityPhase – Einstellungen (White-Label)', 'clarityphase') . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('clarityphase_settings_group');
    do_settings_sections('clarityphase_settings');
    submit_button(esc_html__('Speichern', 'clarityphase'));
    echo '</form>';
    echo '</div>';
}

// ---------------------------
// Media Uploader JS für Logo
// ---------------------------
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_clarityphase_settings' && $hook !== 'clarityphase_page_clarityphase_settings') return;
    wp_enqueue_media();
    wp_add_inline_script('jquery-core', "
    jQuery(function($){
        let frame;
        $('#cp_logo_pick').on('click', function(e){
            e.preventDefault();
            if(frame){ frame.open(); return; }
            frame = wp.media({
                title: 'Logo auswählen',
                button: { text: 'Übernehmen' },
                multiple: false
            });
            frame.on('select', function(){
                const att = frame.state().get('selection').first().toJSON();
                $('#cp_logo_id').val(att.id);
                location.reload(); // simpel & robust
            });
            frame.open();
        });
        $('#cp_logo_remove').on('click', function(e){
            e.preventDefault();
            $('#cp_logo_id').val('0');
            $('form').first().submit();
        });
    });
    ");
});

// =====================================================
// Mail Branding: From / Reply-To / BCC
// =====================================================
add_filter('wp_mail_from', function($from) {
    $v = cp_setting('from_email');
    return $v ? $v : $from;
});

add_filter('wp_mail_from_name', function($name) {
    $v = cp_setting('from_name');
    return $v ? $v : $name;
});

function cp_mail_headers() {
    $headers = [];
    $reply_to = cp_setting('reply_to');
    if ($reply_to) $headers[] = 'Reply-To: ' . $reply_to;

    $bcc = (string) cp_setting('bcc');
    if ($bcc) {
        foreach (explode(',', $bcc) as $email) {
            $email = sanitize_email($email);
            if ($email) $headers[] = 'Bcc: ' . $email;
        }
    }
    return $headers;
}

// =====================================================
// Accent-Farbe als CSS Variable (Frontend + Admin)
// =====================================================

// Frontend
add_action('wp_head', function() {

    if (!function_exists('cp_setting')) return;

    $accent = cp_setting('accent_color', '#0b1630');
    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : '#0b1630';

    echo "<style>:root{--cp-accent:" . esc_html($accent) . ";}</style>\n";

}, 50);


// Backend (Admin)
add_action('admin_head', function() {

    if (!function_exists('cp_setting')) return;

    $accent = cp_setting('accent_color', '#0b1630');
    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : '#0b1630';

    echo "<style>:root{--cp-accent:" . esc_html($accent) . ";}</style>\n";

}, 50);

// -------------------------------------------------
// Frontend White-Label CSS (für Portal-Kacheln)
// Nutzt --cp-accent aus den Settings
// -------------------------------------------------
add_action('wp_head', function() {

    if (!function_exists('cp_setting')) return;

    // Optional: nur im Portal laden (wenn URL /portal/ enthält)
    // Wenn du es global willst: diese if-Zeile entfernen.
    $portal_url = (string) cp_setting('portal_url', home_url('/portal/'));
    $portal_path = wp_parse_url($portal_url, PHP_URL_PATH);
    if ($portal_path && !str_contains($_SERVER['REQUEST_URI'] ?? '', trim($portal_path, '/'))) {
        // Wenn du es auch auf anderen Seiten willst, kommentiere return aus.
        // return;
    }

    echo '<style>
    /* Kacheln */
    .dk-card{
        border-radius: 18px;
        border: 1px solid rgba(0,0,0,.08);
        box-shadow: 0 8px 26px rgba(0,0,0,.08);
        overflow: hidden;
    }

    /* Akzent-Leiste oben */
    .dk-card::before{
        content:"";
        display:block;
        height: 4px;
        background: var(--cp-accent);
    }

    /* Buttons im Portal (falls du Buttons/Links drin hast) */
    .dk-card a.cp-btn,
    .dk-card button.cp-btn,
    .dk-card .cp-btn{
        background: var(--cp-accent);
        color: #fff !important;
        border: 0;
        border-radius: 12px;
        padding: 10px 14px;
        font-weight: 700;
        display: inline-block;
        text-decoration: none;
    }

    .dk-card a.cp-btn:hover,
    .dk-card button.cp-btn:hover,
    .dk-card .cp-btn:hover{
        filter: brightness(.95);
    }

    /* Badges/Pills, falls deine Shortcodes solche Elemente haben */
    .dk-card .cp-badge,
    .dk-card .cp-pill{
        background: var(--cp-accent);
        color:#fff;
        border-radius:999px;
        padding:4px 10px;
        font-weight:700;
        font-size:12px;
        display:inline-block;
    }

    /* Progress Bar im Portal, wenn du Klassen nutzt */
    .dk-card .cp-progress{
        margin-top:8px;
        height:10px;
        border-radius:999px;
        background: rgba(0,0,0,.08);
        overflow:hidden;
    }
    .dk-card .cp-progress__bar{
        height:10px;
        background: var(--cp-accent);
        border-radius:999px;
    }
    </style>';
}, 60);

// =====================================================
// Assets: Version Helper
// =====================================================

function cp_asset_ver($relative_path) {
    $path = plugin_dir_path(__FILE__) . ltrim($relative_path, '/');

    // Dev: Cache-Busting über filemtime
    if (file_exists($path)) {
        return (string) filemtime($path);
    }

    // Fallback: Plugin-Version
    return defined('CLARITYPHASE_VERSION') ? CLARITYPHASE_VERSION : '0.0.0';
}

// =====================================================
// Assets: CSS sauber enqueuen (Frontend + Admin)
// =====================================================

add_action('wp_enqueue_scripts', function() {

    wp_enqueue_style(
        'clarityphase-frontend',
        plugin_dir_url(__FILE__) . 'assets/frontend.css',
        [],
        cp_asset_ver('assets/frontend.css')
    );

}, 20);

add_action('admin_enqueue_scripts', function($hook) {

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'cp_project') {
        return;
    }

    wp_enqueue_style(
        'clarityphase-admin',
        plugin_dir_url(__FILE__) . 'assets/admin.css',
        [],
        cp_asset_ver('assets/admin.css')
    );

}, 20);

// =====================================================
// Licensing (API-ready) – V1
// =====================================================


/**
 * Resolve license API secret.
 * - Preferred: CP License Server option (same WP installation): cp_license_api_secret
 * - Fallback: CP_LICENSE_SERVER_SECRET constant (if you set it in wp-config.php for the server plugin)
 * - Fallback: CP_LICENSE_CLIENT_SECRET constant (if you set it manually)
 */
function cp_license_client_secret() {
    // 1) Same WP: read server option
    $opt = (string) get_option('cp_license_api_secret', '');
    if ($opt !== '') return $opt;

    // 2) Server constant
    if (defined('CP_LICENSE_SERVER_SECRET') && (string) CP_LICENSE_SERVER_SECRET !== '') {
        return (string) CP_LICENSE_SERVER_SECRET;
    }

    // 3) Client constant override
    if (defined('CP_LICENSE_CLIENT_SECRET') && (string) CP_LICENSE_CLIENT_SECRET !== '') {
        return (string) CP_LICENSE_CLIENT_SECRET;
    }

    return '';
}

/**
 * Option keys
 */
function cp_license_option_key() { return 'clarityphase_license'; }           // gespeicherter Key
function cp_license_status_key() { return 'clarityphase_license_status'; }    // cached response array
function cp_license_cache_ttl()  { return 12 * HOUR_IN_SECONDS; }             // 12 Stunden Cache

/**
 * Get license key
 */
function cp_get_license_key() {
    $opts = get_option('clarityphase_settings', []);
    $opts = is_array($opts) ? $opts : [];
    $key  = isset($opts['license_key']) ? trim((string)$opts['license_key']) : '';

    // Fallback: direkt aus POST, falls jemand "Lizenz prüfen" drückt ohne speichern
    if ($key === '' && isset($_POST['clarityphase_settings']['license_key'])) {
        $key = trim((string) $_POST['clarityphase_settings']['license_key']);
    }

    return $key;
}

/**
 * Read cached status
 */
function cp_get_license_status_cached() {
    $data = get_option(cp_license_status_key(), []);
    return is_array($data) ? $data : [];
}

/**
 * Save cached status
 */
function cp_set_license_status_cached($data) {
    if (!is_array($data)) $data = [];
    update_option(cp_license_status_key(), $data, false);
}

/**
 * Determine if cache is fresh
 */
function cp_license_cache_is_fresh($cached) {
    $checked_at = isset($cached['checked_at']) ? (int)$cached['checked_at'] : 0;
    if (!$checked_at) return false;
    return (time() - $checked_at) < cp_license_cache_ttl();
}

/**
 * Main check (calls API, caches response)
 * $force = true -> ignores cache
 */
function cp_check_license($force = false) {

    $key = cp_get_license_key();
    if ($key === '') {
        $out = [
            'ok'         => false,
            'status'     => 'unchecked',
            'plan'       => 'lite',
            'message'    => __('Kein Lizenzschlüssel hinterlegt.', 'clarityphase'),
            'checked_at' => time(),
        ];
        cp_set_license_status_cached($out);
        return $out;
    }

    $cached = cp_get_license_status_cached();
    if (!$force && $cached && cp_license_cache_is_fresh($cached)) {
        return $cached;
    }

    // Payload an API
    $body = [
        'license_key'    => $key,
        'site_url'       => home_url(),
        'domain'         => parse_url(home_url(), PHP_URL_HOST),
        'plugin_slug'    => 'clarityphase',
        'plugin_version' => defined('CLARITYPHASE_VERSION') ? CLARITYPHASE_VERSION : '',
    ];

    $secret = cp_license_client_secret();

    $headers = [
        'Content-Type' => 'application/json',
    ];
    if ($secret !== '') {
        $headers['X-CP-Secret'] = $secret;
    }

    $resp = wp_remote_post(CP_LICENSE_API_URL, [
    'timeout' => 12,
    'headers' => $headers,
    'body'    => wp_json_encode($body),
]);

    if (is_wp_error($resp)) {
        $out = [
            'ok'         => false,
            'status'     => 'error',
            'plan'       => 'lite',
            'message'    => 'API Fehler: ' . $resp->get_error_message(),
            'checked_at' => time(),
        ];
        cp_set_license_status_cached($out);
        return $out;
    }

    $code = (int) wp_remote_retrieve_response_code($resp);
    $json = json_decode((string) wp_remote_retrieve_body($resp), true);
    $json = is_array($json) ? $json : [];

    // Erwartetes Response-Beispiel:
    // { "valid": true, "status":"active", "plan": "pro", "message": "OK", "expires": "2027-01-01" }

    $status_api = isset($json['status']) ? sanitize_key($json['status']) : '';

    // WP REST Errors (401/429 etc.) usually return: { code, message, data:{status} }
    if ($status_api === '' && isset($json['code'])) {
        $status_api = sanitize_key($json['code']);
    }

    $valid  = !empty($json['valid']);
    $plan   = isset($json['plan']) ? sanitize_key($json['plan']) : 'lite';
    $msg    = isset($json['message']) ? sanitize_text_field($json['message']) : '';
    $exp    = isset($json['expires']) ? sanitize_text_field($json['expires']) : '';

    // Wenn Server sagt: expired/over_limit/unauthorized -> erzwinge Lite
    if (in_array($status_api, ['expired', 'domain_limit', 'unauthorized', 'cp_rate_limited', 'cp_unauthorized'], true)) {
    $valid = false;
    $plan  = 'lite';
}

// Plan absichern
$plan = in_array($plan, ['lite', 'pro', 'enterprise'], true) ? $plan : 'lite';

    // Fallback wenn API nichts liefert:
    if ($msg === '') {
        $msg = ($code >= 200 && $code < 300) ? 'Antwort empfangen.' : 'Unerwartete API Antwort.';
    }

   $out = [
    'ok'         => ($code >= 200 && $code < 300),
    'status'     => $valid ? 'valid' : 'invalid',
    'status_api' => $status_api, 
    'plan'       => $plan,
    'message'    => $msg,
    'expires'    => $exp,
    'checked_at' => time(),
    'http_code'  => $code,
];

    cp_set_license_status_cached($out);
    return $out;
}

/**
 * Helper: plan gating
 */
function cp_has_plan($min_plan = 'pro') {
    $rank = ['lite' => 0, 'pro' => 1, 'enterprise' => 2];
    $min  = isset($rank[$min_plan]) ? $rank[$min_plan] : 1;

    $st = cp_get_license_status_cached();
    // Wenn noch nie geprüft: nicht erlauben (außer lite)
    $current_plan = isset($st['plan']) ? $st['plan'] : 'lite';
    $status       = isset($st['status']) ? $st['status'] : 'unchecked';

    if ($min === 0) return true; // lite immer
    if ($status !== 'valid') return false;

    return (isset($rank[$current_plan]) ? $rank[$current_plan] : 0) >= $min;
}

/**
 * Admin: License field in settings (integrate into your existing settings sanitize/defaults)
 *
 * IMPORTANT:
 * Add to cp_settings_defaults(): 'license_key' => ''
 * Add to cp_settings_sanitize(): $out['license_key'] = sanitize_text_field(...)
 * Add field renderer below.
 */

// Field renderer
function cp_field_license_key() {
    $opts = get_option('clarityphase_settings', []);
    $opts = is_array($opts) ? $opts : [];
    $val  = isset($opts['license_key']) ? esc_attr($opts['license_key']) : '';

    echo '<input type="text" name="clarityphase_settings[license_key]" value="'.$val.'" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Lizenzschlüssel für Pro/Enterprise Features.', 'clarityphase') . '</p>';

    $st = cp_get_license_status_cached();
    $status = isset($st['status']) ? $st['status'] : 'unchecked';
    $plan   = isset($st['plan']) ? $st['plan'] : 'lite';
    $msg    = isset($st['message']) ? $st['message'] : '';
    $exp    = isset($st['expires']) ? $st['expires'] : '';

    echo '<div style="margin-top:10px;padding:10px;border:1px solid #ddd;background:#fff;max-width:720px;">';
    echo '<strong>' . esc_html__('Lizenz Status:', 'clarityphase') . '</strong> ' . esc_html($status) . ' &nbsp; <strong>' . esc_html__('Plan:', 'clarityphase') . '</strong> ' . esc_html($plan);
    if ($exp) echo ' &nbsp; <strong>' . esc_html__('Expires:', 'clarityphase') . '</strong> ' . esc_html($exp);
    if ($msg) echo '<div style="margin-top:6px;opacity:.85;">' . esc_html($msg) . '</div>';

    $url = wp_nonce_url(admin_url('admin-post.php?action=cp_check_license'), 'cp_check_license');
    echo '<p style="margin-top:10px;"><a class="button" href="'.esc_url($url).'">' . esc_html__('Lizenz prüfen', 'clarityphase') . '</a></p>';
    echo '</div>';
}

// Button handler
add_action('admin_post_cp_check_license', function() {
    if (!current_user_can('manage_options')) wp_die('Nope.');
    check_admin_referer('cp_check_license');

    cp_check_license(true);

    wp_safe_redirect(admin_url('edit.php?post_type=cp_project&page=clarityphase_settings'));
    exit;
});


