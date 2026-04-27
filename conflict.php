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
 * AJAX conflict and completion check handler.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * AJAX conflict and completion check handler.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Conflict check AJAX endpoint for local_aynurasurveys rule form.
 *
 * Called live when admin sets scope=global + display_context=site.
 * Returns other enabled rules with the same trigger + site context.
 *
 * Also handles course completion verification.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_login();
// Capability check using is_siteadmin — compatible with all Moodle versions.
// And works correctly in fetch() calls from admin pages.
require_capability('local/aynurasurveys:manage', context_system::instance());

header('Content-Type: application/json');

global $DB;

$action = required_param('action', PARAM_ALPHANUMEXT);

// ------------------------------------------------------------------
// Action: conflict_check.
// ------------------------------------------------------------------
if ($action === 'conflict_check') {
    $trigger    = required_param('trigger', PARAM_ALPHANUMEXT);
    $excludeid  = optional_param('exclude_rule_id', 0, PARAM_INT);

    $today = mktime(0, 0, 0);

    // Find other enabled, site-context, not-yet-expired rules for same trigger.
    $sql = "SELECT r.id, r.surveyname, r.surveyid, r.trigger, r.valid_from, r.valid_until
              FROM {local_aynurasurveys_rules} r
             WHERE r.trigger = :trigger
               AND r.enabled = 1
               AND r.scope = 'global'
               AND r.display_context = 'site'
               AND (r.valid_until IS NULL OR r.valid_until >= :today)
               AND r.id != :excludeid";

    $params = ['trigger' => $trigger, 'today' => $today, 'excludeid' => $excludeid];
    $conflicts = $DB->get_records_sql($sql, $params);

    $rows = [];
    foreach ($conflicts as $c) {
        $valid = '—';
        if ($c->valid_from || $c->valid_until) {
            $from  = $c->valid_from ? userdate($c->valid_from, get_string('strftimedate', 'core_langconfig')) : '...';
            $until = $c->valid_until ? userdate($c->valid_until, get_string('strftimedate', 'core_langconfig')) : '...';
            $valid = "{$from} – {$until}";
        }
        $rows[] = [
            'survey' => $c->surveyname ?: $c->surveyid,
            'valid' => $valid,
        ];
    }

    echo json_encode([
        'count' => count($rows),
        'conflicts' => $rows,
    ]);
    exit;
}

// ------------------------------------------------------------------
// Action: completion_check.
// ------------------------------------------------------------------
if ($action === 'completion_check') {
    $courseidsraw = required_param('courseids', PARAM_RAW);
    $courseids     = array_filter(array_map('intval', explode(',', $courseidsraw)));

    if (empty($courseids)) {
        echo json_encode(['warnings' => []]);
        exit;
    }

    $warnings = [];
    foreach ($courseids as $cid) {
        $course = $DB->get_record('course', ['id' => $cid], 'id, fullname, enablecompletion');
        if (!$course) {
            continue;
        }
        if (empty($course->enablecompletion)) {
            $warnings[] = [
                'courseid' => $cid,
                'coursename' => $course->fullname,
            ];
        }
    }

    echo json_encode(['warnings' => $warnings]);
    exit;
}

// ------------------------------------------------------------------
// Action: get_activities — returns activities with completion enabled.
// For given course IDs, grouped by course.
// ------------------------------------------------------------------
if ($action === 'get_activities') {
    $courseidsraw = required_param('courseids', PARAM_RAW);
    $courseids     = array_filter(array_map('intval', explode(',', $courseidsraw)));

    if (empty($courseids)) {
        echo json_encode(['activities' => []]);
        exit;
    }

    $activities = [];
    foreach ($courseids as $cid) {
        $course = $DB->get_record('course', ['id' => $cid], 'id, fullname, shortname');
        if (!$course) {
            continue;
        }

        // Get all course modules with completion enabled.
        $sql = "SELECT cm.id AS cmid, m.name AS modname, cm.instance, c.fullname AS coursename
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {course} c  ON c.id = cm.course
                 WHERE cm.course = :courseid
                   AND cm.completion > 0
                   AND cm.deletioninprogress = 0
                 ORDER BY cm.section, cm.id";

        $cms = $DB->get_records_sql($sql, ['courseid' => $cid]);

        foreach ($cms as $cm) {
            // Get activity name from its own table.
            $name = $DB->get_field($cm->modname, 'name', ['id' => $cm->instance]);
            if (!$name) {
                continue;
            }

            $activities[] = [
                'cmid' => (int) $cm->cmid,
                'name' => $name,
                'type' => $cm->modname,
                'coursename' => $cm->coursename,
                'courseid' => $cid,
            ];
        }
    }

    // Debug: if no activities found, also return a debug hint.
    $debug = [];
    if (empty($activities)) {
        foreach ($courseids as $cid) {
            $totalcms = $DB->count_records('course_modules', ['course' => $cid]);
            $completioncms = $DB->count_records_select(
                'course_modules',
                'course = :cid AND completion > 0 AND deletioninprogress = 0',
                ['cid' => $cid]
            );
            $debug[] = "Course {$cid}: {$totalcms} total modules, {$completioncms} with completion enabled";
        }
    }

    echo json_encode(['activities' => $activities, 'debug' => $debug]);
    exit;
}

echo json_encode(['error' => 'Unknown action.']);
