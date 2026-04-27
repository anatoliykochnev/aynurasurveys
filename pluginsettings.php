<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin settings page for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin settings page for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Custom settings page for local_aynurasurveys.
 * Renders with the plugin's own nav and design system.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_aynurasurveys\api;

admin_externalpage_setup('local_aynurasurveys_settings');
$PAGE->set_url(new moodle_url('/local/aynurasurveys/pluginsettings.php'));

$saved   = false;
$error   = null;
$pingres = null;

// ------------------------------------------------------------------
// Handle form save
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $enabled = optional_param('enabled', 0, PARAM_INT);
    $baseurl = optional_param('baseurl', '', PARAM_URL);
    $apikey  = optional_param('apikey', '', PARAM_RAW);

    set_config('enabled', $enabled, 'local_aynurasurveys');
    set_config('baseurl', rtrim($baseurl, '/'), 'local_aynurasurveys');

    // Only update apikey if a new one was submitted (not masked).
    if ($apikey && strpos($apikey, '••••') === false) {
        set_config('apikey', $apikey, 'local_aynurasurveys');
    }

    $saved = true;

    // Auto-test connection after save.
    try {
        $apiclient = new api();
        $pingres   = $apiclient->ping();
    } catch (\Exception $e) {
        $pingres = ['success' => false, 'message' => $e->getMessage()];
    }
}

// ------------------------------------------------------------------
// Load current config
// ------------------------------------------------------------------
$cfgenabled = (bool) get_config('local_aynurasurveys', 'enabled');
$cfgbaseurl = (string) get_config('local_aynurasurveys', 'baseurl');
$cfgapikey  = (string) get_config('local_aynurasurveys', 'apikey');
$maskedkey  = $cfgapikey ? substr($cfgapikey, 0, 6) . str_repeat('•', max(0, strlen($cfgapikey) - 6)) : '';

// Test connection on page load if configured.
if (!$saved && $cfgbaseurl && $cfgapikey) {
    try {
        $apiclient = new api();
        $pingres   = $apiclient->ping();
    } catch (\Exception $e) {
        $pingres = ['success' => false, 'message' => $e->getMessage()];
    }
}

echo $OUTPUT->header();

$currentpage = 'settings';
require_once(__DIR__ . '/templates/nav.php');
?>
<style>
/* Ensure form section cards render correctly on settings page */
.hs-wrap .hs-form-section {
    background: #FAFBFF !important;
    border: 1.5px solid #E5E7EB !important;
    border-radius: 12px !important;
    padding: 18px 20px !important;
    margin-bottom: 20px !important;
}
.hs-wrap .hs-form-section-title {
    font-size: 11px !important;
    font-weight: 700 !important;
    color: #6C6FF5 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.7px !important;
    margin-bottom: 16px !important;
    display: flex !important;
    align-items: center !important;
    gap: 7px !important;
}
.hs-wrap .hs-form-section-title::after {
    content: '' !important;
    flex: 1 !important;
    height: 1px !important;
    background: #E5E7EB !important;
}
.hs-wrap .hs-toggle-row {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 12px 16px !important;
    background: #fff !important;
    border: 1.5px solid #E5E7EB !important;
    border-radius: 10px !important;
    margin-bottom: 12px !important;
    cursor: pointer !important;
}
.hs-wrap .hs-toggle-label {
    font-size: 13px !important;
    font-weight: 500 !important;
    color: #1A1A2E !important;
}
.hs-wrap .hs-toggle-sub {
    font-size: 11px !important;
    color: #9CA3AF !important;
    margin-top: 2px !important;
}
.hs-wrap .hs-toggle {
    position: relative !important;
    width: 40px !important;
    height: 22px !important;
    flex-shrink: 0 !important;
}
.hs-wrap .hs-toggle input { opacity: 0; width: 0; height: 0; }
.hs-wrap .hs-toggle-slider {
    position: absolute !important;
    inset: 0 !important;
    background: #E5E7EB !important;
    border-radius: 999px !important;
    transition: background 200ms !important;
    cursor: pointer !important;
}
.hs-wrap .hs-toggle-slider::before {
    content: '' !important;
    position: absolute !important;
    width: 16px; height: 16px;
    left: 3px; top: 3px;
    background: #fff !important;
    border-radius: 50% !important;
    transition: transform 200ms !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2) !important;
}
.hs-wrap .hs-toggle input:checked + .hs-toggle-slider {
    background: linear-gradient(135deg, #6C6FF5, #9B8FF5) !important;
}
.hs-wrap .hs-toggle input:checked + .hs-toggle-slider::before {
    transform: translateX(18px) !important;
}
.hs-wrap input[type="url"],
.hs-wrap input[type="password"],
.hs-wrap input[type="text"] {
    width: 100% !important;
    padding: 10px 14px !important;
    border: 1.5px solid #E5E7EB !important;
    border-radius: 10px !important;
    font-size: 13px !important;
    color: #1A1A2E !important;
    background: #fff !important;
    font-family: 'DM Sans', sans-serif !important;
    box-sizing: border-box !important;
}
.hs-wrap input[type="url"]:focus,
.hs-wrap input[type="password"]:focus,
.hs-wrap input[type="text"]:focus {
    outline: none !important;
    border-color: #6C6FF5 !important;
    box-shadow: 0 0 0 3px rgba(108,111,245,0.12) !important;
}
.hs-settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 0;
}
@media (max-width: 700px) {
    .hs-settings-grid { grid-template-columns: 1fr; }
}
</style>

<?php if ($saved): ?>
<div style="background:#DCFCE7;border:1.5px solid #86EFAC;border-radius:10px;padding:12px 16px;
            margin-bottom:20px;font-size:13px;color:#166534;display:flex;align-items:center;gap:8px;">
  ✓ Settings saved successfully.
</div>
<?php endif; ?>

<form method="post"
      action="<?php echo (new moodle_url('/local/aynurasurveys/pluginsettings.php', ['sesskey' => sesskey()]))->out(false); ?>">
<input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

<!-- ── Connection status banner ────────────────────────── -->
<?php if ($pingres !== null): ?>
<div style="background:<?php echo $pingres['success'] ? '#DCFCE7' : '#FEE2E2'; ?>;
            border:1.5px solid <?php echo $pingres['success'] ? '#86EFAC' : '#FCA5A5'; ?>;
            border-radius:10px;padding:12px 16px;margin-bottom:20px;
            font-size:13px;color:<?php echo $pingres['success'] ? '#166534' : '#991B1B'; ?>;
            display:flex;align-items:center;gap:8px;">
  <?php echo $pingres['success'] ? '✓' : '✗'; ?>
  <?php echo s($pingres['message']); ?>
</div>
<?php endif; ?>

<!-- ── Section: Plugin ──────────────────────────────────── -->
<div class="hs-form-section">
  <div class="hs-form-section-title">⚙ Plugin</div>

  <label class="hs-toggle-row" for="hs_enabled" style="cursor:pointer;">
    <div>
      <div class="hs-toggle-label">Enable Plugin</div>
      <div class="hs-toggle-sub">When disabled, no surveys will be triggered for any user</div>
    </div>
    <div class="hs-toggle">
      <input type="checkbox" id="hs_enabled" name="enabled" value="1"
             <?php echo $cfgenabled ? 'checked' : ''; ?>>
      <span class="hs-toggle-slider"></span>
    </div>
  </label>
</div>

<!-- ── Section: Connection ──────────────────────────────── -->
<div class="hs-form-section">
  <div class="hs-form-section-title">🔌 API Connection</div>

  <div class="hs-settings-grid">

    <div class="hs-field">
      <label for="hs_baseurl">
        Base URL
        <span style="font-weight:400;color:#9CA3AF;text-transform:none;font-size:11px;letter-spacing:0;">
          — e.g. https://frturwmbnmqdeangsmnp.supabase.co/functions/v1/plugin-api
        </span>
      </label>
      <input type="url" name="baseurl" id="hs_baseurl" placeholder="https://..."
             value="<?php echo s($cfgbaseurl); ?>">
    </div>

    <div class="hs-field">
      <label for="hs_apikey">
        API Key
        <span style="font-weight:400;color:#9CA3AF;text-transform:none;font-size:11px;letter-spacing:0;">
          — from your Aynura.Surveys API Docs page
        </span>
      </label>
      <div style="position:relative;">
        <input type="password" name="apikey" id="hs_apikey"
               placeholder="Leave blank to keep current key"
               style="width:100%;padding:10px 40px 10px 14px;border:1.5px solid #E5E7EB;
                      border-radius:10px;font-size:13px;color:#1A1A2E;background:#fff;
                      font-family:'DM Sans',sans-serif;transition:border-color 150ms ease;">
        <button type="button" onclick="toggleKey()"
                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                       background:none;border:none;cursor:pointer;color:#9CA3AF;font-size:14px;">
          👁
        </button>
      </div>
      <?php if ($maskedkey): ?>
        <div style="font-size:11px;color:#9CA3AF;margin-top:5px;">
          Current: <code><?php echo s($maskedkey); ?></code>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ── Section: Quick Links ─────────────────────────────── -->
<div class="hs-form-section">
  <div class="hs-form-section-title">🔗 Quick Links</div>
  <div style="display:flex;gap:12px;flex-wrap:wrap;">
    <a href="<?php echo (new moodle_url('/local/aynurasurveys/diagnostics.php'))->out(false); ?>"
       class="hs-btn hs-btn-secondary">
      🔬 API Diagnostics
    </a>
    <a href="<?php echo (new moodle_url('/admin/settings.php?section=local_aynurasurveys'))->out(false); ?>"
       class="hs-btn hs-btn-secondary" target="_blank">
      ⚙ Moodle Config Page ↗
    </a>
  </div>
</div>

<!-- ── Actions ──────────────────────────────────────────── -->
<div style="display:flex;gap:12px;align-items:center;margin-top:8px;">
  <button type="submit" class="hs-btn hs-btn-primary">
    Save Settings
  </button>
  <button type="submit" name="test_connection" value="1" class="hs-btn hs-btn-secondary">
    🔌 Test Connection
  </button>
</div>

</form>

<script>
function toggleKey() {
    var f = document.getElementById('hs_apikey');
    f.type = f.type === 'password' ? 'text' : 'password';
}
</script>

<?php
echo '</div></div>'; // hs-content + hs-wrap
echo $OUTPUT->footer();
