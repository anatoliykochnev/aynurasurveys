<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify.
// It under the terms of the GNU General Public License as published by.
// The Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// But WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// Along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Scheduled task: process all time-based and percentage-based trigger rules.
 *
 * Runs hourly (configurable). Handles:
 *   - days_after_enrollment
 *   - course_percent
 *   - fixed_date
 *   - recurring (daily/weekly/monthly)
 *   - days_after_start
 *   - days_before_end
 *   - days_after_completion
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aynurasurveys\task;


use local_aynurasurveys\trigger_manager;

/**
 * Scheduled task: evaluates time-based survey triggers.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_time_triggers extends \core\task\scheduled_task {
    /**
     * Get name.
     */
    public function get_name(): string {
        return get_string('pluginname', 'local_aynurasurveys') . ': Process time-based triggers';
    }

    /**
     * Execute.
     */
    public function execute(): void {
        global $DB;

        if (!get_config('local_aynurasurveys', 'enabled')) {
            return;
        }

        $now   = time();
        $today = mktime(0, 0, 0);

        // Auto-disable expired rules.
        $expired = $DB->get_records_select(
            'local_aynurasurveys_rules',
            'enabled = 1 AND valid_until IS NOT NULL AND valid_until < :today',
            ['today' => $today]
        );
        if (!empty($expired)) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($expired), SQL_PARAMS_NAMED);
            $DB->set_field_select('local_aynurasurveys_rules', 'enabled', 0, "id {$insql}", $params);
            mtrace('local_aynurasurveys: auto-disabled ' . count($expired) . ' expired rule(s).');
        }

        $timetriggers = [
            trigger_manager::TRIGGER_DAYS_AFTER_ENROLLMENT,
            trigger_manager::TRIGGER_COURSE_PERCENT,
            trigger_manager::TRIGGER_FIXED_DATE,
            trigger_manager::TRIGGER_RECURRING,
            trigger_manager::TRIGGER_DAYS_AFTER_START,
            trigger_manager::TRIGGER_DAYS_BEFORE_END,
            trigger_manager::TRIGGER_DAYS_AFTER_COMPLETION,
            trigger_manager::TRIGGER_DAYS_AFTER_QUIZ,
        ];

        [$insql, $inparams] = $DB->get_in_or_equal($timetriggers, SQL_PARAMS_NAMED);
        $rules = $DB->get_records_select(
            'local_aynurasurveys_rules',
            "trigger {$insql} AND enabled = 1",
            $inparams
        );

        if (empty($rules)) {
            return;
        }

        foreach ($rules as $rule) {
            try {
                $this->process_rule($rule, $now);
            } catch (\Exception $e) {
                debugging("local_aynurasurveys task: error processing rule {$rule->id}: " . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    // -----------------------------------------------------------------------.
    // Rule dispatcher.
    // -----------------------------------------------------------------------.

    /**
     * Process rule.
     */
    private function process_rule(\stdClass $rule, int $now): void {
        switch ($rule->trigger) {
            case trigger_manager::TRIGGER_DAYS_AFTER_ENROLLMENT:
                $this->process_days_after_enrollment($rule, $now);
                break;

            case trigger_manager::TRIGGER_COURSE_PERCENT:
                $this->process_course_percent($rule, $now);
                break;

            case trigger_manager::TRIGGER_FIXED_DATE:
                $this->process_fixed_date($rule, $now);
                break;

            case trigger_manager::TRIGGER_RECURRING:
                $this->process_recurring($rule, $now);
                break;

            case trigger_manager::TRIGGER_DAYS_AFTER_START:
                $this->process_days_after_start($rule, $now);
                break;

            case trigger_manager::TRIGGER_DAYS_BEFORE_END:
                $this->process_days_before_end($rule, $now);
                break;

            case trigger_manager::TRIGGER_DAYS_AFTER_COMPLETION:
                $this->process_days_after_completion($rule, $now);
                break;

            case trigger_manager::TRIGGER_DAYS_AFTER_QUIZ:
                $this->process_days_after_quiz($rule, $now);
                break;
        }
    }

    // -----------------------------------------------------------------------.
    // Individual trigger processors.
    // -----------------------------------------------------------------------.

    /**
     * days_after_enrollment — fire N days after a user was enrolled.
     * Condition: { "days": N }
     */
    private function process_days_after_enrollment(\stdClass $rule, int $now): void {
        global $DB;

        $conditions = trigger_manager::get_conditions($rule);
        $days       = (int) ($conditions['days'] ?? 0);
        if ($days <= 0) {
            return;
        }

        $threshold = $now - ($days * DAYSECS);

        // Find enrollments that crossed the threshold since last cron run (1 hour ago).
        $windowstart = $threshold - HOURSECS;
        $windowend   = $threshold;

        $coursefilter = $this->get_course_filter_sql($rule, 'ue.courseid');

        $sql = "SELECT ue.userid, ue.courseid, ue.timecreated, c.fullname, c.shortname
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                  JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
                 WHERE ue.timecreated BETWEEN :window_start AND :window_end
                       {$coursefilter['sql']}";

        $params = array_merge([
            'window_start' => $windowstart,
            'window_end' => $windowend,
        ], $coursefilter['params']);

        $enrollments = $DB->get_records_sql($sql, $params);

        foreach ($enrollments as $enrollment) {
            $user = $DB->get_record('user', ['id' => $enrollment->userid], 'id, email, firstname, lastname');
            if (!$user) {
                continue;
            }

            trigger_manager::fire(
                trigger_manager::TRIGGER_DAYS_AFTER_ENROLLMENT,
                $user,
                (int) $enrollment->courseid,
                [
                    'coursename' => $enrollment->fullname,
                    'courseshortname' => $enrollment->shortname,
                    'days_after' => $days,
                ]
            );
        }
    }

    /**
     * course_percent — fire when a user reaches X% course completion.
     * Condition: { "percent": N }
     *
     * Uses Moodle's course_modules_completion and course_completion_criteria
     * to calculate a simple activity-based percentage.
     */
    private function process_course_percent(\stdClass $rule, int $now): void {
        global $DB;

        $conditions = trigger_manager::get_conditions($rule);
        $targetpct = (float) ($conditions['percent'] ?? 0);
        if ($targetpct <= 0) {
            return;
        }

        $coursefilter = $this->get_course_filter_sql($rule, 'e.courseid');

        // For each active enrolled user, compare completed modules / total modules.
        $sql = "SELECT ue.userid, e.courseid, c.fullname, c.shortname,
                       COUNT(DISTINCT cm.id) AS total_modules,
                       COUNT(DISTINCT cmc.coursemoduleid) AS completed_modules
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                  JOIN {course_modules} cm ON cm.course = e.courseid AND cm.completion > 0
                  LEFT JOIN {course_modules_completion} cmc
                         ON cmc.coursemoduleid = cm.id
                        AND cmc.userid = ue.userid
                        AND cmc.completionstate > 0
                  JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
                 WHERE cm.deletioninprogress = 0
                       {$coursefilter['sql']}
                 GROUP BY ue.userid, e.courseid, c.fullname, c.shortname
                HAVING COUNT(DISTINCT cm.id) > 0";

        $rows = $DB->get_records_sql($sql, $coursefilter['params']);

        foreach ($rows as $row) {
            $pct = ($row->completed_modules / $row->total_modules) * 100;

            // Check if percentage is within the trigger window.
            // We fire when pct is >= target but we have not yet dispatched this rule for this user.
            if ($pct < $targetpct) {
                continue;
            }

            $user = $DB->get_record('user', ['id' => $row->userid], 'id, email, firstname, lastname');
            if (!$user) {
                continue;
            }

            trigger_manager::fire(
                trigger_manager::TRIGGER_COURSE_PERCENT,
                $user,
                (int) $row->courseid,
                [
                    'coursename' => $row->fullname,
                    'courseshortname' => $row->shortname,
                    'percent_reached' => round($pct, 1),
                    'target_percent' => $targetpct,
                ]
            );
        }
    }

    /**
     * fixed_date — fire once on/after a specific timestamp.
     * Condition: { "fixed_date": <unix timestamp> }
     */
    private function process_fixed_date(\stdClass $rule, int $now): void {
        global $DB;

        $conditions  = trigger_manager::get_conditions($rule);
        $fixeddate  = (int) ($conditions['fixed_date'] ?? 0);
        if ($fixeddate <= 0 || $fixeddate > $now) {
            return;
        }

        // Only fire within a 1-hour window after the fixed date to avoid re-firing.
        if ($now > $fixeddate + HOURSECS) {
            return;
        }

        $this->fire_for_all_enrolled_users($rule, $now, trigger_manager::TRIGGER_FIXED_DATE, [
            'fixed_date' => date('c', $fixeddate),
        ]);
    }

    /**
     * recurring — fire on a schedule (daily/weekly/monthly) for all enrolled users.
     * Condition: { "recurrence": "daily"|"weekly"|"monthly" }
     */
    private function process_recurring(\stdClass $rule, int $now): void {
        $conditions  = trigger_manager::get_conditions($rule);
        $recurrence  = $conditions['recurrence'] ?? '';

        $shouldfire = match ($recurrence) {
            'daily' => true, // Task runs hourly — fire once per day using the log.
            'weekly' => (date('N', $now) === '1'), // Monday.
            'monthly' => (date('j', $now) === '1'), // 1st of month.
            default => false,
        };

        if (!$shouldfire) {
            return;
        }

        // For recurring, frequency protection is relaxed — we allow re-firing.
        // On schedule. We handle this by checking the log for entries within.
        // The current recurrence period instead of all-time.
        $this->fire_for_all_enrolled_users($rule, $now, trigger_manager::TRIGGER_RECURRING, [
            'recurrence' => $recurrence,
        ], $recurrence);
    }

    /**
     * days_after_start — fire N days after the course start date.
     * Condition: { "days": N }
     */
    private function process_days_after_start(\stdClass $rule, int $now): void {
        global $DB;

        $conditions = trigger_manager::get_conditions($rule);
        $days       = (int) ($conditions['days'] ?? 0);
        if ($days <= 0) {
            return;
        }

        $coursefilter = $this->get_course_filter_sql($rule, 'c.id');

        $targetstartmin = $now - (($days + 0) * DAYSECS) - HOURSECS;
        $targetstartmax = $now - ($days * DAYSECS);

        $sql = "SELECT ue.userid, c.id AS courseid, c.fullname, c.shortname
                  FROM {course} c
                  JOIN {enrol} e ON e.courseid = c.id
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
                 WHERE c.startdate BETWEEN :min AND :max
                       {$coursefilter['sql']}";

        $params = array_merge([
            'min' => $targetstartmin,
            'max' => $targetstartmax,
        ], $coursefilter['params']);

        $rows = $DB->get_records_sql($sql, $params);
        foreach ($rows as $row) {
            $user = $DB->get_record('user', ['id' => $row->userid], 'id, email, firstname, lastname');
            if (!$user) {
                continue;
            }
            trigger_manager::fire(
                trigger_manager::TRIGGER_DAYS_AFTER_START,
                $user, (int) $row->courseid,
                ['coursename' => $row->fullname, 'courseshortname' => $row->shortname, 'days_after' => $days]
            );
        }
    }

    /**
     * days_before_end — fire N days before the course end date.
     * Condition: { "days": N }
     */
    private function process_days_before_end(\stdClass $rule, int $now): void {
        global $DB;

        $conditions = trigger_manager::get_conditions($rule);
        $days       = (int) ($conditions['days'] ?? 0);
        if ($days <= 0) {
            return;
        }

        $coursefilter = $this->get_course_filter_sql($rule, 'c.id');

        $targetendmin = $now + ($days * DAYSECS);
        $targetendmax = $targetendmin + HOURSECS;

        $sql = "SELECT ue.userid, c.id AS courseid, c.fullname, c.shortname
                  FROM {course} c
                  JOIN {enrol} e ON e.courseid = c.id
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
                 WHERE c.enddate BETWEEN :min AND :max
                       {$coursefilter['sql']}";

        $params = array_merge([
            'min' => $targetendmin,
            'max' => $targetendmax,
        ], $coursefilter['params']);

        $rows = $DB->get_records_sql($sql, $params);
        foreach ($rows as $row) {
            $user = $DB->get_record('user', ['id' => $row->userid], 'id, email, firstname, lastname');
            if (!$user) {
                continue;
            }
            trigger_manager::fire(
                trigger_manager::TRIGGER_DAYS_BEFORE_END,
                $user, (int) $row->courseid,
                ['coursename' => $row->fullname, 'courseshortname' => $row->shortname, 'days_before' => $days]
            );
        }
    }

    /**
     * days_after_completion — fire N days after a user completed the course.
     * Condition: { "days": N }
     */
    private function process_days_after_completion(\stdClass $rule, int $now): void {
        global $DB;

        $conditions = trigger_manager::get_conditions($rule);
        $days       = (int) ($conditions['days'] ?? 0);
        if ($days <= 0) {
            return;
        }

        $coursefilter = $this->get_course_filter_sql($rule, 'cc.course');

        $targetmin = $now - (($days + 0) * DAYSECS) - HOURSECS;
        $targetmax = $now - ($days * DAYSECS);

        $sql = "SELECT cc.userid, cc.course AS courseid, c.fullname, c.shortname
                  FROM {course_completions} cc
                  JOIN {course} c ON c.id = cc.course
                  JOIN {user} u ON u.id = cc.userid AND u.deleted = 0 AND u.suspended = 0
                 WHERE cc.timecompleted BETWEEN :min AND :max
                       {$coursefilter['sql']}";

        $params = array_merge([
            'min' => $targetmin,
            'max' => $targetmax,
        ], $coursefilter['params']);

        $rows = $DB->get_records_sql($sql, $params);
        foreach ($rows as $row) {
            $user = $DB->get_record('user', ['id' => $row->userid], 'id, email, firstname, lastname');
            if (!$user) {
                continue;
            }
            trigger_manager::fire(
                trigger_manager::TRIGGER_DAYS_AFTER_COMPLETION,
            trigger_manager::TRIGGER_DAYS_AFTER_QUIZ,
                $user, (int) $row->courseid,
                ['coursename' => $row->fullname, 'courseshortname' => $row->shortname, 'days_after' => $days]
            );
        }
    }

    /**
     * days_after_quiz — fire N days after a user completed a quiz.
     * Condition: { "days": N }
     * Uses quiz_completion_recorded entries in the log table.
     */
    private function process_days_after_quiz(\stdClass $rule, int $now): void {
        global $DB;

        $conditions = trigger_manager::get_conditions($rule);
        $days       = (int) ($conditions['days'] ?? 0);
        if ($days <= 0) {
            return;
        }

        $targetmin = $now - ($days * DAYSECS) - HOURSECS;
        $targetmax = $now - ($days * DAYSECS);

        $coursefilter = $this->get_course_filter_sql($rule, 'l.courseid');

        $sql = "SELECT l.userid, l.courseid, l.timecreated,
                       c.fullname, c.shortname
                  FROM {local_aynurasurveys_log} l
                  JOIN {course} c ON c.id = l.courseid
                  JOIN {user} u ON u.id = l.userid AND u.deleted = 0 AND u.suspended = 0
                 WHERE l.trigger = 'quiz_completion_recorded'
                   AND l.timecreated BETWEEN :tmin AND :tmax
                       {$coursefilter['sql']}";

        $params = array_merge([
            'tmin' => $targetmin,
            'tmax' => $targetmax,
        ], $coursefilter['params']);

        $rows = $DB->get_records_sql($sql, $params);
        foreach ($rows as $row) {
            $user = $DB->get_record('user', ['id' => $row->userid], 'id, email, firstname, lastname, lang');
            if (!$user) {
                continue;
            }
            trigger_manager::fire(
                trigger_manager::TRIGGER_DAYS_AFTER_QUIZ,
                $user, (int) $row->courseid,
                [
                    'coursename' => $row->fullname,
                    'courseshortname' => $row->shortname,
                    'days_after' => $days,
                ]
            );
        }
    }

    // -----------------------------------------------------------------------.
    // Shared helpers.
    // -----------------------------------------------------------------------.

    /**
     * Fire a trigger for all users enrolled in the rule's target courses.
     * Used by fixed_date and recurring triggers.
     *
     * @param \stdClass   $rule
     * @param int         $now
     * @param string      $trigger
     * @param array       $extracontext
     * @param string|null $recurrence  If set, applies recurrence-period dedup instead of all-time.
     */
    private function fire_for_all_enrolled_users(
        \stdClass $rule,
        int $now,
        string $trigger,
        array $extracontext = [], ?string $recurrence = null
    ): void {
        global $DB;

        $coursefilter = $this->get_course_filter_sql($rule, 'e.courseid');

        $sql = "SELECT DISTINCT ue.userid, e.courseid, c.fullname, c.shortname
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                  JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
                 WHERE 1=1 {$coursefilter['sql']}";

        $rows = $DB->get_records_sql($sql, $coursefilter['params']);

        foreach ($rows as $row) {
            $user = $DB->get_record('user', ['id' => $row->userid], 'id, email, firstname, lastname');
            if (!$user) {
                continue;
            }

            // For recurring triggers, check within the current period instead of all-time.
            if ($recurrence) {
                $periodstart = match ($recurrence) {
                    'daily' => strtotime('today midnight'),
                    'weekly' => strtotime('last Monday midnight'),
                    'monthly' => strtotime('first day of this month midnight'),
                    default => 0,
                };

                if ($DB->record_exists_select(
                    'local_aynurasurveys_log',
                    'userid = :uid AND surveyid = :sid AND trigger = :t AND timecreated >= :period AND status = :s',
                    ['uid' => $user->id, 'sid' => $rule->surveyid, 't' => $trigger, 'period' => $periodstart, 's' => 'success']
                )) {
                    continue;
                }
            }

            trigger_manager::fire(
                $trigger,
                $user,
                (int) $row->courseid,
                array_merge($extracontext, [
                    'coursename' => $row->fullname,
                    'courseshortname' => $row->shortname,
                ])
            );
        }
    }

    /**
     * Build a SQL fragment + params to filter by course scope.
     *
     * @param \stdClass $rule
     * @param string    $courseidfield  The SQL alias/field referencing the course ID.
     * @return array ['sql' => string, 'params' => array]
     */
    private function get_course_filter_sql(\stdClass $rule, string $courseidfield): array {
        global $DB;

        if ($rule->scope === 'global') {
            return ['sql' => '', 'params' => []];
        }

        $courseids = $DB->get_fieldset_select(
            'local_aynurasurveys_rule_courses',
            'courseid',
            'ruleid = :ruleid',
            ['ruleid' => $rule->id]
        );

        if (empty($courseids)) {
            // Scoped rule with no courses configured — fire for nothing.
            return ['sql' => 'AND 1=0', 'params' => []];
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        return ['sql' => "AND {$courseidfield} {$insql}", 'params' => $params];
    }
}
