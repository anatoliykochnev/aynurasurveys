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
 * Delivery log viewer for local_aynurasurveys.
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
 * Delivery log viewer for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Delivery log viewer for local_aynurasurveys.
 *
 * Shows the last 200 dispatch attempts with status, user, trigger,
 * survey ID, HTTP status code, and timestamp.
 * Supports filtering by status and trigger type.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_aynurasurveys\trigger_manager;

admin_externalpage_setup('local_aynurasurveys_log');

$filterstatus  = optional_param('status', '', PARAM_ALPHA);
$filtertrigger = optional_param('trigger', '', PARAM_ALPHANUMEXT);
$page           = optional_param('page', 0, PARAM_INT);
$perpage        = 50;

$pageurl = new moodle_url('/local/aynurasurveys/log.php');
$PAGE->set_url($pageurl);

// ------------------------------------------------------------------
// Build query.
// ------------------------------------------------------------------
$where  = [];
$params = [];

if ($filterstatus) {
    $where[]          = 'l.status = :status';
    $params['status'] = $filterstatus;
}
if ($filtertrigger) {
    $where[]           = 'l.trigger = :trigger';
    $params['trigger'] = $filtertrigger;
}

$wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Always exclude infrastructure entries.
$where[] = "(l.response IS NULL OR l.response != 'pending_modal_created')";
$where[] = "l.status != 'info'";
$wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {local_aynurasurveys_log} l {$wheresql}",
    $params
);

$sql = "SELECT l.id, l.userid, l.surveyid, l.trigger, l.courseid,
               l.status, l.statuscode, l.timecreated,
               u.firstname, u.lastname, u.email
          FROM {local_aynurasurveys_log} l
          LEFT JOIN {user} u ON u.id = l.userid
               {$wheresql}
         ORDER BY l.timecreated DESC";

$logs = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

// ------------------------------------------------------------------
// Render.
// ------------------------------------------------------------------
echo $OUTPUT->header();

$currentpage = 'log';
require_once(__DIR__ . '/templates/nav.php');

echo '<div class="hs-card">';
echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">';
echo '<h4 style="margin:0;font-size:16px;font-weight:600;color:#1A1A2E;">' . get_string('deliverylog', 'local_aynurasurveys') . '</h4>';
echo '</div>';

// Filter form.
?>
<form method="get" action="<?php echo s($pageurl); ?>" class="mb-3 form-inline">
  <div class="form-group mr-2">
    <label for="filter_status" class="mr-1"><?php echo get_string('log_status', 'local_aynurasurveys'); ?></label>
    <select name="status" id="filter_status" class="form-control form-control-sm">
      <option value=""><?php echo get_string('all'); ?></option>
      <option value="success" <?php echo ($filterstatus === 'success') ? 'selected' : ''; ?>>
        <?php echo get_string('log_status_success', 'local_aynurasurveys'); ?>
      </option>
      <option value="failed" <?php echo ($filterstatus === 'failed') ? 'selected' : ''; ?>>
        <?php echo get_string('log_status_failed', 'local_aynurasurveys'); ?>
      </option>
    </select>
  </div>
  <div class="form-group mr-2">
    <label for="filter_trigger" class="mr-1"><?php echo get_string('log_trigger', 'local_aynurasurveys'); ?></label>
    <select name="trigger" id="filter_trigger" class="form-control form-control-sm">
      <option value=""><?php echo get_string('all'); ?></option>
      <?php foreach (trigger_manager::get_all_triggers() as $t): ?>
        <option value="<?php echo s($t); ?>" <?php echo ($filtertrigger === $t) ? 'selected' : ''; ?>>
          <?php echo get_string('trigger_' . $t, 'local_aynurasurveys'); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-sm btn-secondary"><?php echo get_string('filter'); ?></button>
  <a href="<?php echo s($pageurl); ?>" class="btn btn-sm btn-link"><?php echo get_string('reset'); ?></a>
</form>

<?php
if (empty($logs)) {
    echo $OUTPUT->notification(get_string('nolog', 'local_aynurasurveys'), \core\output\notification::NOTIFY_INFO);
} else {
    $table             = new html_table();
    $table->attributes = ['class' => 'hs-table'];
    $table->head       = [
        '#',
        get_string('log_userid', 'local_aynurasurveys'),
        get_string('log_surveyid', 'local_aynurasurveys'),
        get_string('log_trigger', 'local_aynurasurveys'),
        get_string('log_status', 'local_aynurasurveys'),
        get_string('log_statuscode', 'local_aynurasurveys'),
        get_string('log_timecreated', 'local_aynurasurveys'),
    ];
    $table->attributes = ['class' => 'generaltable w-100'];

    foreach ($logs as $log) {
        $username = trim("{$log->firstname} {$log->lastname}") ?: $log->userid;
        $statusbadge = $log->status === 'success'
            ? html_writer::span(get_string('log_status_success', 'local_aynurasurveys'), 'badge badge-success')
            : html_writer::span(get_string('log_status_failed', 'local_aynurasurveys'), 'badge badge-danger');

        $table->data[] = [
            $log->id,
            html_writer::tag('small', s($username) . '<br>' . html_writer::tag('code', $log->email ?? '')),
            html_writer::tag('code', s($log->surveyid)),
            get_string('trigger_' . $log->trigger, 'local_aynurasurveys'),
            $statusbadge,
            $log->statuscode ?? '—',
            userdate($log->timecreated),
        ];
    }

    echo html_writer::table($table);

    // Pagination.
    echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url($pageurl, [
        'status' => $filterstatus,
        'trigger' => $filtertrigger,
    ]));
}

echo '</div>'; // hs-card
echo '</div></div>'; // hs-content + hs-wrap
echo $OUTPUT->footer();
