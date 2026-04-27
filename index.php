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
 * Overview dashboard for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Overview dashboard for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Overview dashboard for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_aynurasurveys\api;
use local_aynurasurveys\trigger_manager;

admin_externalpage_setup('local_aynurasurveys_overview');
$PAGE->set_url(new moodle_url('/local/aynurasurveys/index.php'));

// ------------------------------------------------------------------
// Gather stats
// ------------------------------------------------------------------
$activerules   = $DB->count_records('local_aynurasurveys_rules', ['enabled' => 1]);
$archivedrules = $DB->count_records('local_aynurasurveys_rules', ['enabled' => 0]);
$totaldispatch = $DB->count_records_select('local_aynurasurveys_log', "status != 'info' AND response != 'pending_modal_created'");
$successcount  = $DB->count_records_select('local_aynurasurveys_log', "status = 'success' AND response != 'pending_modal_created'");
$pendingcount  = $DB->count_records('local_aynurasurveys_pending', ['status' => 'pending']);
$dismissed      = $DB->count_records('local_aynurasurveys_pending', ['status' => 'dismissed']);
$completed      = $DB->count_records('local_aynurasurveys_pending', ['status' => 'completed']);

$successrate = $totaldispatch > 0 ? round(($successcount / $totaldispatch) * 100) : 0;

// Last dispatch time
$lastlog = $DB->get_record_select(
    'local_aynurasurveys_log',
    "status = 'success'",
    [],
    '*',
    IGNORE_MULTIPLE
);
$lastdispatch = $lastlog ? userdate($lastlog->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')) : 'Never';

// Recent deliveries (last 8).
$recentlogs = $DB->get_records_sql(
    "SELECT l.id, l.ruleid, l.userid, l.surveyid, l.trigger, l.status, l.timecreated,
            u.firstname, u.lastname
       FROM {local_aynurasurveys_log} l
       LEFT JOIN {user} u ON u.id = l.userid
      WHERE l.status != 'info'
        AND (l.response IS NULL OR l.response != 'pending_modal_created')
      ORDER BY l.timecreated DESC
      LIMIT 8"
);

// Connection status.
$connok = false;
$connmsg = 'Not configured';
try {
    $apiclient = new api();
    $ping      = $apiclient->ping();
    $connok   = $ping['success'];
    $connmsg  = $connok ? 'Connected' : 'Connection failed';
} catch (\Exception $e) {
    $connmsg = 'Not configured';
}

// ------------------------------------------------------------------
// Render
// ------------------------------------------------------------------
echo $OUTPUT->header();

$currentpage = 'overview';
require_once(__DIR__ . '/templates/nav.php');

// KPI row.
$kpis = [
    ['label' => 'Connection', 'value' => $connok ? '✓' : '✗',
     'sub' => $connmsg,
     'color' => $connok ? '#16a34a' : '#dc2626'],
    ['label' => 'Active Rules', 'value' => $activerules, 'sub' => $archivedrules . ' archived'],
    ['label' => 'Total Dispatched', 'value' => $totaldispatch, 'sub' => 'all time'],
    ['label' => 'Success Rate', 'value' => $successrate . '%', 'sub' => $successcount . ' successful'],
    ['label' => 'Pending', 'value' => $pendingcount, 'sub' => 'awaiting display'],
    ['label' => 'Completed', 'value' => $completed, 'sub' => $dismissed . ' dismissed'],
];
?>

<!-- KPI cards -->
<div class="hs-kpi-grid">
  <?php foreach ($kpis as $i => $kpi): ?>
    <div class="hs-kpi-card" style="animation: hsCardIn <?php echo 100 + $i * 60; ?>ms ease both;">
      <div class="hs-kpi-label"><?php echo $kpi['label']; ?></div>
      <div class="hs-kpi-value" style="<?php echo isset($kpi['color']) ? 'color:' . $kpi['color'] : ''; ?>">
        <?php echo $kpi['value']; ?>
      </div>
      <div class="hs-kpi-sub"><?php echo $kpi['sub']; ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

  <!-- Recent deliveries -->
  <div class="hs-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <div class="hs-section-title">Recent Deliveries</div>
      <a href="<?php echo (new moodle_url('/local/aynurasurveys/log.php'))->out(false); ?>"
         class="hs-btn hs-btn-secondary hs-btn-sm">View all</a>
    </div>
    <?php if (empty($recentlogs)): ?>
      <p style="color:#9CA3AF;font-size:13px;margin:0;">No deliveries yet.</p>
    <?php else: ?>
      <table class="hs-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Rule</th>
            <th>Status</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentlogs as $log):
            $name = trim($log->firstname . ' ' . $log->lastname) ?: 'User #' . $log->userid;
            $statusclass = $log->status === 'success' ? 'hs-badge-green' : ($log->status === 'dismissed' ? 'hs-badge-amber' : 'hs-badge-red');
            $rulelabel = !empty($log->ruleid) ? $DB->get_field('local_aynurasurveys_rules', 'rulename', ['id' => $log->ruleid]) : null;
            if (!$rulelabel) $rulelabel = get_string('trigger_' . $log->trigger, 'local_aynurasurveys'); ?>
          <tr>
            <td style="font-weight:500;"><?php echo s($name); ?></td>
            <td>
              <div style="font-weight:500;color:#1A1A2E;"><?php echo s($rulelabel); ?></div>
              <div style="font-size:11px;color:#9CA3AF;"><?php echo get_string('trigger_' . $log->trigger, 'local_aynurasurveys'); ?></div>
            </td>
            <td><span class="hs-badge <?php echo s($statusclass); ?>"><?php echo ucfirst($log->status); ?></span></td>
            <td style="color:#9CA3AF;font-size:12px;"><?php echo userdate($log->timecreated, '%d %b %H:%M'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Active rules summary -->
  <div class="hs-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <div class="hs-section-title">Active Trigger Rules</div>
      <a href="<?php echo (new moodle_url('/local/aynurasurveys/rules.php', ['action' => 'add']))->out(false); ?>"
         class="hs-btn hs-btn-primary hs-btn-sm">+ Add Rule</a>
    </div>
    <?php
    $today = mktime(0, 0, 0);
    $rules = $DB->get_records('local_aynurasurveys_rules', ['enabled' => 1], 'timecreated DESC', '*', 0, 8);
    if (empty($rules)): ?>
      <p style="color:#9CA3AF;font-size:13px;">No active rules yet.</p>
      <a href="<?php echo (new moodle_url('/local/aynurasurveys/rules.php', ['action' => 'add']))->out(false); ?>"
         class="hs-btn hs-btn-primary" style="margin-top:12px;">Create your first rule</a>
    <?php else: ?>
      <table class="hs-table">
        <thead>
          <tr><th>Rule</th><th>Survey</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rules as $rule):
            if (!empty($rule->valid_from) && $today < (int)$rule->valid_from) {
                $badge = '<span class="hs-badge hs-badge-blue">Scheduled</span>';
            } else if (!empty($rule->valid_until) && $today > ((int)$rule->valid_until + 86399)) {
                $badge = '<span class="hs-badge hs-badge-red">Expired</span>';
            } else {
                $badge = '<span class="hs-badge hs-badge-green">Active</span>';
            } ?>
          <tr>
            <td>
              <div style="font-weight:600;color:#1A1A2E;"><?php echo s($rule->rulename ?: get_string('trigger_' . $rule->trigger, 'local_aynurasurveys')); ?></div>
              <div style="font-size:11px;color:#9CA3AF;"><?php echo get_string('trigger_' . $rule->trigger, 'local_aynurasurveys'); ?></div>
            </td>
            <td style="color:#6B7280;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?php echo s($rule->surveyname ?: $rule->surveyid); ?>
            </td>
            <td><?php echo $badge; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($activerules > 8): ?>
        <a href="<?php echo (new moodle_url('/local/aynurasurveys/rules.php'))->out(false); ?>"
           style="display:block;text-align:center;margin-top:12px;font-size:12px;color:#6C6FF5;">
          View all <?php echo (int)$activerules; ?> rules →
        </a>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</div>

<style>
@keyframes hsCardIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<?php
// Close hs-content div and hs-wrap div.
echo '</div></div>';
echo $OUTPUT->footer();
