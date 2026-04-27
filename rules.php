<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Trigger rules management page for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_aynurasurveys\trigger_manager;
use local_aynurasurveys\api;

admin_externalpage_setup('local_aynurasurveys_rules');

$action  = optional_param('action', 'list', PARAM_ALPHA);
$ruleid  = optional_param('id', 0, PARAM_INT);
$tab     = optional_param('tab', 'active', PARAM_ALPHA);

$pageurl = new moodle_url('/local/aynurasurveys/rules.php');
$PAGE->set_url(new moodle_url($pageurl, ['action' => $action, 'id' => $ruleid, 'tab' => $tab]));

// ------------------------------------------------------------------
// Actions: archive, reactivate
// ------------------------------------------------------------------
if ($action === 'archive' && $ruleid) {
    require_sesskey();
    $DB->set_field('local_aynurasurveys_rules', 'enabled', 0, ['id' => $ruleid]);
    redirect(new moodle_url($pageurl, ['tab' => 'archived']),
        get_string('rule_archived', 'local_aynurasurveys'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'reactivate' && $ruleid) {
    require_sesskey();
    $DB->set_field('local_aynurasurveys_rules', 'enabled', 1, ['id' => $ruleid]);
    redirect(new moodle_url($pageurl, ['tab' => 'active']),
        get_string('rule_reactivated', 'local_aynurasurveys'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// ------------------------------------------------------------------
// Handle form POST (add / edit)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'])) {
    require_sesskey();

    $rulename        = optional_param('rulename',        '', PARAM_TEXT);
    $trigger         = required_param('trigger',         PARAM_ALPHANUMEXT);
    $surveyid        = required_param('surveyid',        PARAM_RAW);
    $scope           = required_param('scope',           PARAM_ALPHA);
    $display_context = required_param('display_context', PARAM_ALPHA);
    $courseids       = optional_param_array('courseids', [], PARAM_INT);
    $enabled         = optional_param('enabled', 0, PARAM_INT);
    $use_dates       = optional_param('use_dates', 0, PARAM_INT);
    $valid_from_str  = optional_param('valid_from',  '', PARAM_RAW);
    $valid_until_str = optional_param('valid_until', '', PARAM_RAW);
    $delay_minutes   = optional_param('delay_minutes', 0, PARAM_INT);

    if (trigger_manager::is_login_trigger($trigger)) {
        $display_context = 'site';
        $scope           = 'global';
    }

    $valid_from = $valid_until = null;
    if ($use_dates) {
        if ($valid_from_str)  $valid_from  = strtotime($valid_from_str  . ' 00:00:00');
        if ($valid_until_str) $valid_until = strtotime($valid_until_str . ' 00:00:00');
    }

    $conditions = [];
    switch ($trigger) {
        case trigger_manager::TRIGGER_LOGIN_AFTER_INACTIVITY:
        case trigger_manager::TRIGGER_DAYS_AFTER_ENROLLMENT:
        case trigger_manager::TRIGGER_DAYS_AFTER_START:
        case trigger_manager::TRIGGER_DAYS_BEFORE_END:
        case trigger_manager::TRIGGER_DAYS_AFTER_COMPLETION:
        case trigger_manager::TRIGGER_DAYS_AFTER_QUIZ:
            $conditions['days'] = (int) optional_param('condition_days', 0, PARAM_INT);
            break;
        case trigger_manager::TRIGGER_COURSE_PERCENT:
            $conditions['percent'] = (float) optional_param('condition_percent', 0, PARAM_FLOAT);
            break;
        case trigger_manager::TRIGGER_GRADE_PASSED:
        case trigger_manager::TRIGGER_GRADE_FAILED:
        case trigger_manager::TRIGGER_QUIZ_PASSED:
        case trigger_manager::TRIGGER_QUIZ_FAILED:
            $conditions['threshold'] = (float) optional_param('condition_threshold', 0, PARAM_FLOAT);
            break;
        case trigger_manager::TRIGGER_FIXED_DATE:
            $conditions['fixed_date'] = (int) optional_param('condition_fixed_date', 0, PARAM_INT);
            break;
        case trigger_manager::TRIGGER_RECURRING:
            $conditions['recurrence'] = required_param('condition_recurrence', PARAM_ALPHA);
            break;
        case trigger_manager::TRIGGER_ACTIVITY_COMPLETED:
            $cmid = optional_param('condition_cmid_val', 0, PARAM_INT);
            if ($cmid > 0) {
                $conditions['cmid'] = $cmid;
            }
            break;
    }

    // Fetch survey name for caching.
    $surveyname = $surveyid;
    try {
        $apiclient  = new api();
        $surveys    = $apiclient->get_surveys(['status' => 'active']);
        foreach ($surveys as $s) {
            if ($s['id'] === $surveyid) { $surveyname = $s['title'] ?? $surveyid; break; }
        }
    } catch (\Exception $e) {}

    if ($action === 'edit' && $ruleid) {
        $rule = $DB->get_record('local_aynurasurveys_rules', ['id' => $ruleid], '*', MUST_EXIST);
        $rule->rulename        = $rulename;
        $rule->trigger         = $trigger;
        $rule->surveyid        = $surveyid;
        $rule->surveyname      = $surveyname;
        $rule->scope           = $scope;
        $rule->display_context = $display_context;
        $rule->conditions      = json_encode($conditions);
        $rule->valid_from      = $valid_from;
        $rule->valid_until     = $valid_until;
        $rule->delay_minutes   = $delay_minutes;
        $rule->enabled         = $enabled;
        $rule->timemodified    = time();
        $DB->update_record('local_aynurasurveys_rules', $rule);
    } else {
        $ruleid = $DB->insert_record('local_aynurasurveys_rules', (object) [
            'rulename'        => $rulename,
                'trigger'         => $trigger,
            'surveyid'        => $surveyid,
            'surveyname'      => $surveyname,
            'scope'           => $scope,
            'display_context' => $display_context,
            'conditions'      => json_encode($conditions),
            'valid_from'      => $valid_from,
            'valid_until'     => $valid_until,
            'delay_minutes'   => $delay_minutes,
            'enabled'         => $enabled,
            'timecreated'     => time(),
            'timemodified'    => time(),
            'createdby'       => $USER->id,
        ]);
    }

    $DB->delete_records('local_aynurasurveys_rule_courses', ['ruleid' => $ruleid]);
    if ($scope === 'course' && !empty($courseids)) {
        foreach ($courseids as $cid) {
            $DB->insert_record('local_aynurasurveys_rule_courses', ['ruleid' => $ruleid, 'courseid' => (int) $cid]);
        }
    }

    redirect(new moodle_url($pageurl, ['tab' => $enabled ? 'active' : 'archived']),
        get_string('rule_saved', 'local_aynurasurveys'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// ------------------------------------------------------------------
// Prepare form data (for add / edit rendering)
// ------------------------------------------------------------------
$rule                = null;
$existing_courseids  = [];
$existing_conditions = [];

if ($action === 'add') {
    // Empty defaults — rule stays null.
} elseif ($action === 'edit' && $ruleid) {
    $rule                = $DB->get_record('local_aynurasurveys_rules', ['id' => $ruleid], '*', MUST_EXIST);
    $existing_courseids  = $DB->get_fieldset_select('local_aynurasurveys_rule_courses', 'courseid', 'ruleid = ?', [$ruleid]);
    $existing_conditions = json_decode($rule->conditions ?? '{}', true) ?? [];
}

// Fetch surveys for dropdown.
$survey_options = ['' => get_string('choosedots')];
try {
    $apiclient = new api();
    $surveys   = $apiclient->get_surveys(['status' => 'active']);
    foreach ($surveys as $s) {
        $survey_options[$s['id']] = $s['title'] ?? $s['id'];
    }
} catch (\Exception $e) {
    \core\notification::warning(get_string('error_nosurveys', 'local_aynurasurveys'));
}

// Trigger options.
$trigger_options = ['' => get_string('choosedots')];
foreach (trigger_manager::get_all_triggers() as $t) {
    $trigger_options[$t] = get_string('trigger_' . $t, 'local_aynurasurveys');
}

// Course options.
$course_options = [];
$courses = $DB->get_records_select('course', 'id != :site', ['site' => SITEID], 'fullname ASC', 'id, fullname, shortname, enablecompletion');
foreach ($courses as $c) {
    $course_options[$c->id] = "{$c->fullname} ({$c->shortname})";
}

$conflicturl = (new moodle_url('/local/aynurasurveys/conflict.php'))->out(false);
$formaction  = new moodle_url($pageurl, [
    'action'  => ($rule ? 'edit' : 'add'),
    'id'      => $ruleid,
    'tab'     => $tab,
    'sesskey' => sesskey(),
]);

// ------------------------------------------------------------------
// Load rules for table
// ------------------------------------------------------------------
$today = mktime(0, 0, 0);
if ($tab === 'active') {
    $rules = $DB->get_records('local_aynurasurveys_rules', ['enabled' => 1], 'timecreated DESC');
} else {
    $rules = $DB->get_records('local_aynurasurveys_rules', ['enabled' => 0], 'timecreated DESC');
}

// ------------------------------------------------------------------
// Render
// ------------------------------------------------------------------
echo $OUTPUT->header();

$current_page = 'rules';
require_once(__DIR__ . '/templates/nav.php');
?>

<!-- ── Toolbar: tabs + add button ─────────────────────── -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
  <div style="display:flex;gap:8px;">
    <a href="<?php echo (new moodle_url($pageurl, ['tab' => 'active']))->out(false); ?>"
       class="hs-btn hs-btn-sm <?php echo ($tab === 'active') ? 'hs-btn-primary' : 'hs-btn-secondary'; ?>">
      Active
    </a>
    <a href="<?php echo (new moodle_url($pageurl, ['tab' => 'archived']))->out(false); ?>"
       class="hs-btn hs-btn-sm <?php echo ($tab === 'archived') ? 'hs-btn-primary' : 'hs-btn-secondary'; ?>">
      Archived
    </a>
  </div>
  <?php if ($action !== 'add' && $action !== 'edit'): ?>
    <a href="<?php echo (new moodle_url($pageurl, ['action' => 'add', 'tab' => $tab]))->out(false); ?>"
       class="hs-btn hs-btn-primary">
      + <?php echo get_string('addrule', 'local_aynurasurveys'); ?>
    </a>
  <?php endif; ?>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- ── Inline form card ───────────────────────────────── -->
<div class="hs-card" style="margin-bottom:24px;border:2px solid #EEF0FF;">

  <!-- Form header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:32px;height:32px;background:linear-gradient(135deg,#6C6FF5,#9B8FF5);
                  border-radius:9px;display:flex;align-items:center;justify-content:center;
                  color:#fff;font-size:16px;">
        <?php echo $rule ? '✏' : '＋'; ?>
      </div>
      <div>
        <div style="font-size:15px;font-weight:700;color:#1A1A2E;">
          <?php echo $rule ? get_string('editrule', 'local_aynurasurveys') : get_string('addrule', 'local_aynurasurveys'); ?>
        </div>
        <div style="font-size:11px;color:#9CA3AF;">
          <?php echo $rule ? 'Update existing trigger rule' : 'Configure a new survey trigger'; ?>
        </div>
      </div>
    </div>
    <a href="<?php echo (new moodle_url($pageurl, ['tab' => $tab]))->out(false); ?>"
       class="hs-btn hs-btn-secondary hs-btn-sm">
      ✕ Cancel
    </a>
  </div>

  <?php require_once(__DIR__ . '/templates/rule_form.php'); ?>

</div>
<?php endif; ?>

<!-- ── Rules table card ───────────────────────────────── -->
<div class="hs-card">

  <?php if (empty($rules)): ?>
    <div style="text-align:center;padding:40px 20px;">
      <div style="font-size:40px;margin-bottom:12px;">⚡</div>
      <div style="font-size:15px;font-weight:600;color:#1A1A2E;margin-bottom:6px;">No rules yet</div>
      <div style="font-size:13px;color:#9CA3AF;margin-bottom:20px;">
        <?php echo $tab === 'active' ? 'Create your first trigger rule to start collecting feedback.' : 'No archived rules.'; ?>
      </div>
      <?php if ($tab === 'active'): ?>
        <a href="<?php echo (new moodle_url($pageurl, ['action' => 'add', 'tab' => $tab]))->out(false); ?>"
           class="hs-btn hs-btn-primary">
          + <?php echo get_string('addrule', 'local_aynurasurveys'); ?>
        </a>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <table class="hs-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Status</th>
          <th>Trigger</th>
          <th>Survey</th>
          <th>Scope</th>
          <th>Display</th>
          <th>Created</th>
          <th>Active From</th>
          <th>Active Until</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $strdate = get_string('strftimedate', 'core_langconfig');
        foreach ($rules as $rule_row):
            // Status badge.
            if ($tab === 'active') {
                if (!empty($rule_row->valid_from) && $today < (int)$rule_row->valid_from) {
                    $badge = '<span class="hs-badge hs-badge-blue">Scheduled</span>';
                } elseif (!empty($rule_row->valid_until) && $today > ((int)$rule_row->valid_until + 86399)) {
                    $badge = '<span class="hs-badge hs-badge-red">Expired</span>';
                } else {
                    $badge = '<span class="hs-badge hs-badge-green">Active</span>';
                }
            } else {
                $badge = '<span class="hs-badge hs-badge-grey">Archived</span>';
            }

            // Scope label.
            if ($rule_row->scope === 'global') {
                $scope_label = '🌐 All courses';
            } else {
                $cids  = $DB->get_fieldset_select('local_aynurasurveys_rule_courses', 'courseid', 'ruleid = ?', [$rule_row->id]);
                $names = array_map(function($cid) use ($DB) {
                    return $DB->get_field('course', 'shortname', ['id' => $cid]) ?: $cid;
                }, $cids);
                $scope_label = implode(', ', $names) ?: '—';
            }

            // Display context.
            $ctx_label = ($rule_row->display_context === 'course') ? '📖 On course' : '🖥 On site';

            // Completion warning.
            $warn = '';
            if (trigger_manager::requires_completion($rule_row->trigger) && $rule_row->scope === 'course') {
                $cids = $DB->get_fieldset_select('local_aynurasurveys_rule_courses', 'courseid', 'ruleid = ?', [$rule_row->id]);
                foreach ($cids as $cid) {
                    if (!$DB->get_field('course', 'enablecompletion', ['id' => $cid])) {
                        $warn = ' <span title="' . get_string('completion_warning', 'local_aynurasurveys') . '" style="color:#f59e0b;cursor:help;">⚠</span>';
                        break;
                    }
                }
            }

            // Dates.
            $from_str  = $rule_row->valid_from  ? userdate($rule_row->valid_from,  $strdate) : '—';
            $until_str = $rule_row->valid_until ? userdate($rule_row->valid_until, $strdate) : '—';

            // Action URLs.
            $editurl = new moodle_url($pageurl, ['action' => 'edit', 'id' => $rule_row->id, 'tab' => $tab]);
            if ($tab === 'active') {
                $actionurl = new moodle_url($pageurl, ['action' => 'archive',    'id' => $rule_row->id, 'tab' => $tab, 'sesskey' => sesskey()]);
                $actionlbl = get_string('rule_archive', 'local_aynurasurveys');
                $actioncls = 'hs-btn hs-btn-secondary hs-btn-sm';
            } else {
                $actionurl = new moodle_url($pageurl, ['action' => 'reactivate', 'id' => $rule_row->id, 'tab' => $tab, 'sesskey' => sesskey()]);
                $actionlbl = get_string('rule_reactivate', 'local_aynurasurveys');
                $actioncls = 'hs-btn hs-btn-primary hs-btn-sm';
            }
        ?>
        <tr>
          <td style="font-weight:600;color:#1A1A2E;max-width:180px;">
            <?php echo s($rule_row->rulename ?: '—'); ?>
          </td>
          <td><?php echo $badge; ?></td>
          <td style="font-weight:500;color:#1A1A2E;">
            <?php echo get_string('trigger_' . $rule_row->trigger, 'local_aynurasurveys'); ?>
          </td>
          <td style="color:#6B7280;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?php echo s($rule_row->surveyname ?: $rule_row->surveyid); ?><?php echo $warn; ?>
          </td>
          <td style="font-size:12px;color:#6B7280;"><?php echo s($scope_label); ?></td>
          <td style="font-size:12px;color:#6B7280;"><?php echo $ctx_label; ?></td>
          <td style="font-size:12px;color:#9CA3AF;"><?php echo userdate($rule_row->timecreated, $strdate); ?></td>
          <td style="font-size:12px;color:#9CA3AF;"><?php echo $from_str; ?></td>
          <td style="font-size:12px;color:#9CA3AF;"><?php echo $until_str; ?></td>
          <td style="white-space:nowrap;">
            <a href="<?php echo $editurl->out(false); ?>"
               class="hs-btn hs-btn-secondary hs-btn-sm" style="margin-right:6px;">
              ✏ Edit
            </a>
            <a href="<?php echo $actionurl->out(false); ?>"
               class="<?php echo $actioncls; ?>">
              <?php echo $actionlbl; ?>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php
echo '</div></div>'; // hs-content + hs-wrap
echo $OUTPUT->footer();
