<?php
/**
 * Shared navigation component — Option C: prominent tab bar inside content area.
 *
 * Include AFTER $OUTPUT->header() on every plugin page.
 * Requires $current_page to be set before including.
 *
 * @package local_aynurasurveys
 */
defined('MOODLE_INTERNAL') || die();

$nav_items = [
    'overview'    => ['label' => 'Overview',       'url' => '/local/aynurasurveys/index.php',       'icon' => '◈'],
    'rules'       => ['label' => 'Trigger Rules',  'url' => '/local/aynurasurveys/rules.php',       'icon' => '⚡'],
    'log'         => ['label' => 'Delivery Log',   'url' => '/local/aynurasurveys/log.php',         'icon' => '📋'],
    'diagnostics' => ['label' => 'API Diagnostics','url' => '/local/aynurasurveys/diagnostics.php', 'icon' => '🔬'],
    'settings'    => ['label' => 'Settings',       'url' => '/local/aynurasurveys/pluginsettings.php', 'icon' => '⚙'],
];
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');

/* Scope all styles */
.hs-wrap * { font-family: 'DM Sans', sans-serif !important; box-sizing: border-box; }

/* ── Outer wrapper ─────────────────────────────────────── */
.hs-wrap {
    background: #ECEEF8;
    border-radius: 16px;
    margin-bottom: 24px;
    overflow: hidden;
}

/* ── Top bar: brand + tabs in one row ──────────────────── */
.hs-topbar {
    display: flex;
    align-items: stretch;
    background: #fff;
    border-bottom: 2px solid #ECEEF8;
    padding: 0 24px;
    gap: 0;
    min-height: 58px;
}

/* Brand mark on the left */
.hs-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-right: 24px;
    border-right: 1px solid #E5E7EB;
    margin-right: 8px;
    flex-shrink: 0;
}

.hs-brand-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: #000;
}

.hs-brand-text { line-height: 1.2; }
.hs-brand-name { font-size: 14px; font-weight: 700; color: #1A1A2E; letter-spacing: -0.3px; }
.hs-brand-sub  { font-size: 10px; color: #9CA3AF; font-weight: 400; }

/* Tab row */
.hs-tabs {
    display: flex;
    align-items: stretch;
    gap: 0;
    flex: 1;
    overflow-x: auto;
}

.hs-tab {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 0 18px;
    font-size: 13px;
    font-weight: 500;
    color: #6B7280;
    text-decoration: none !important;
    white-space: nowrap;
    position: relative;
    transition: color 150ms ease;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px; /* overlap the border-bottom of topbar */
}

.hs-tab:hover {
    color: #1A1A2E;
    text-decoration: none !important;
    background: #FAFBFF;
}

.hs-tab-active {
    color: #6C6FF5 !important;
    font-weight: 600;
    border-bottom: 2px solid #6C6FF5 !important;
    background: #FAFBFF;
}

.hs-tab-icon { font-size: 14px; line-height: 1; }

/* ── Content area ──────────────────────────────────────── */
.hs-content {
    padding: 24px;
}

/* ── Cards ─────────────────────────────────────────────── */
.hs-card {
    background: #fff;
    border-radius: 14px;
    padding: 22px 24px;
    box-shadow: 0 2px 12px rgba(100,110,200,0.08);
    transition: box-shadow 200ms ease, transform 200ms ease;
    margin-bottom: 16px;
}
.hs-card:hover { box-shadow: 0 6px 20px rgba(100,110,200,0.12); }

/* ── KPI grid ──────────────────────────────────────────── */
.hs-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}
.hs-kpi-card {
    background: #fff;
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 2px 12px rgba(100,110,200,0.08);
    transition: transform 200ms ease, box-shadow 200ms ease;
}
.hs-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(100,110,200,0.14); }
.hs-kpi-label { font-size: 10px; font-weight: 600; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 8px; }
.hs-kpi-value { font-size: 1.9rem; font-weight: 700; color: #1A1A2E; line-height: 1; margin-bottom: 4px; }
.hs-kpi-sub   { font-size: 11px; color: #9CA3AF; }

/* ── Badges ────────────────────────────────────────────── */
.hs-badge { display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:600; }
.hs-badge-green  { background:#DCFCE7;color:#16a34a; }
.hs-badge-red    { background:#FEE2E2;color:#dc2626; }
.hs-badge-blue   { background:#EEF0FF;color:#6C6FF5; }
.hs-badge-amber  { background:#FEF9C3;color:#ca8a04; }
.hs-badge-grey   { background:#F3F4F6;color:#6B7280; }

/* ── Section title ─────────────────────────────────────── */
.hs-section-title { font-size:13px;font-weight:600;color:#1A1A2E;margin:0 0 14px;letter-spacing:-0.2px; }

/* ── Table ─────────────────────────────────────────────── */
.hs-table { width:100%;border-collapse:collapse;font-size:13px; }
.hs-table th { font-size:10px;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:0.5px;padding:0 12px 10px;border-bottom:1px solid #E5E7EB;text-align:left; }
.hs-table td { padding:11px 12px;color:#374151;border-bottom:1px solid #F3F4F6;vertical-align:middle; }
.hs-table tr:last-child td { border-bottom:none; }
.hs-table tr:hover td { background:#FAFBFF; }

/* ── Buttons ───────────────────────────────────────────── */
.hs-btn { display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:999px;font-size:13px;font-weight:500;cursor:pointer;text-decoration:none !important;transition:all 150ms ease;border:none; }
.hs-btn-primary { background:linear-gradient(135deg,#6C6FF5,#9B8FF5);color:#fff !important;box-shadow:0 4px 12px rgba(108,111,245,0.25); }
.hs-btn-primary:hover { box-shadow:0 6px 18px rgba(108,111,245,0.4);transform:translateY(-1px);color:#fff !important; }
.hs-btn-secondary { background:#fff;color:#374151 !important;border:1px solid #E5E7EB; }
.hs-btn-secondary:hover { background:#F9FAFB;color:#374151 !important; }
.hs-btn-danger { background:#FEE2E2;color:#dc2626 !important;border:none; }
.hs-btn-danger:hover { background:#FECACA;color:#dc2626 !important; }
.hs-btn-sm { padding:5px 13px;font-size:12px; }

/* ── Animations ────────────────────────────────────────── */
@keyframes hsCardIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="hs-wrap">

  <!-- Top bar: brand + inline tabs -->
  <div class="hs-topbar">

    <!-- Brand -->
    <div class="hs-brand">
      <div class="hs-brand-icon"><img src="<?php echo $OUTPUT->image_url('icon', 'local_aynurasurveys'); ?>" alt="Aynura.Surveys" style="width:28px;height:28px;border-radius:6px;display:block;"></div>
      <div class="hs-brand-text">
        <div class="hs-brand-name">Aynura.Surveys</div>
        <div class="hs-brand-sub">Survey Intelligence</div>
      </div>
    </div>

    <!-- Tabs -->
    <nav class="hs-tabs">
      <?php foreach ($nav_items as $key => $item): ?>
        <a href="<?php echo (new moodle_url($item['url']))->out(false); ?>"
           class="hs-tab <?php echo ($current_page === $key) ? 'hs-tab-active' : ''; ?>">
          <span class="hs-tab-icon"><?php echo $item['icon']; ?></span>
          <?php echo $item['label']; ?>
        </a>
      <?php endforeach; ?>
    </nav>

  </div><!-- /.hs-topbar -->

  <!-- Page content goes here -->
  <div class="hs-content">
