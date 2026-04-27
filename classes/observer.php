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
 * Event observer for local_aynurasurveys.
 *
 * Each public static method corresponds to an event registered in db/events.php.
 * Methods perform minimal event-specific logic (e.g. checking firstaccess,
 * grade thresholds) then delegate to trigger_manager::fire().
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aynurasurveys;

/**
 * Event observer callbacks for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    // -----------------------------------------------------------------------
    // User-Based Triggers
    // -----------------------------------------------------------------------

    /**
     * Handles \core\event\user_loggedin
     *
     * Evaluates three triggers from a single login event:
     *   - first_login           : user's firstaccess == current time (set by Moodle on very first login)
     *   - every_login           : always fires
     *   - login_after_inactivity: fires if gap between lastaccess and now >= configured days
     */
    /**
     * Handles \core\event\user_loggedin
     *
     * NOTE: Login-based triggers (first_login, every_login, login_after_inactivity)
     * are handled in lib.php::local_aynurasurveys_after_require_login() instead,
     * because dispatch() requires making HTTP API calls which fail silently
     * inside the DB transaction that wraps the login event.
     *
     * This method is intentionally a no-op. The event observer registration
     * is kept so Moodle does not report a missing callback.
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        // Handled in lib.php after_require_login hook — see comment above.
    }

    // -----------------------------------------------------------------------
    // Enrollment-Based Triggers
    // -----------------------------------------------------------------------

    /**
     * Handles \core\event\user_enrolment_deleted
     *
     * Trigger: unenrolled
     */
    public static function user_unenrolled(\core\event\user_enrolment_deleted $event): void {
        global $DB;

        $userid   = $event->relateduserid;
        $courseid = $event->courseid;

        $user = $DB->get_record('user', ['id' => $userid], 'id, email, firstname, lastname');
        if (!$user) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname');

        trigger_manager::fire(
            trigger_manager::TRIGGER_UNENROLLED,
            $user,
            $courseid, [
                'coursename' => $course->fullname ?? '',
                'courseshortname' => $course->shortname ?? '',
            ]
        );
    }

    // -----------------------------------------------------------------------
    // Course Progress Triggers
    // -----------------------------------------------------------------------

    /**
     * Handles \core\event\course_module_viewed
     *
     * Trigger: course_started (first time a user views any activity in a course)
     *
     * We use the delivery log to detect "first access" — if no prior dispatch
     * attempt (success or fail) exists for this user + course_started trigger
     * in this course, it qualifies as the first activity access
     */
    public static function course_module_viewed(\core\event\course_module_viewed $event): void {
        global $DB;

        $userid   = $event->userid;
        $courseid = $event->courseid;

        // Check if we have already fired a course_started for this user+course.
        $already = $DB->record_exists_select(
            'local_aynurasurveys_log',
            'userid = :userid AND trigger = :trigger AND courseid = :courseid', [
                'userid' => $userid,
                'trigger' => trigger_manager::TRIGGER_COURSE_STARTED,
                'courseid' => $courseid,
            ]
        );

        if ($already) {
            return; // Not the first activity access.
        }

        $user   = $DB->get_record('user', ['id' => $userid], 'id, email, firstname, lastname');
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname');

        if (!$user || !$course) {
            return;
        }

        trigger_manager::fire(
            trigger_manager::TRIGGER_COURSE_STARTED,
            $user,
            $courseid, [
                'coursename' => $course->fullname,
                'courseshortname' => $course->shortname,
            ]
        );
    }

    /**
     * Handles \core\event\course_completed
     *
     * Trigger: course_completed
     */
    public static function course_completed(\core\event\course_completed $event): void {
        global $DB;

        $userid   = $event->relateduserid;
        $courseid = $event->courseid;

        $user   = $DB->get_record('user', ['id' => $userid], 'id, email, firstname, lastname');
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname');

        if (!$user || !$course) {
            return;
        }

        trigger_manager::fire(
            trigger_manager::TRIGGER_COURSE_COMPLETED,
            $user,
            $courseid, [
                'coursename' => $course->fullname,
                'courseshortname' => $course->shortname,
                'completed_at' => date('c', time()),
            ]
        );
    }

    // -----------------------------------------------------------------------
    // Grade-Based Triggers
    // -----------------------------------------------------------------------

    /**
     * Handles \core\event\user_graded
     *
     * Evaluates both grade_passed and grade_failed triggers
     * Each rule's conditions carry { "threshold": N } where N is a percentage (0–100).
     */
    public static function user_graded(\core\event\user_graded $event): void {
        global $DB;

        $userid   = $event->userid;
        $courseid = $event->courseid;

        // Retrieve the grade item to calculate the percentage.
        $gradeitem = $DB->get_record('grade_items', ['id' => $event->other['itemid']], 'id, grademax, grademin, itemname');
        $grade     = $DB->get_record('grade_grades', ['userid' => $userid, 'itemid' => $event->other['itemid']], 'finalgrade');

        if (!$gradeitem || !$grade || $grade->finalgrade === null) {
            return;
        }

        $range      = (float) $gradeitem->grademax - (float) $gradeitem->grademin;
        $percentage = $range > 0 ? (((float) $grade->finalgrade - (float) $gradeitem->grademin) / $range) * 100
            : 0;

        $user   = $DB->get_record('user', ['id' => $userid], 'id, email, firstname, lastname');
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname');

        if (!$user) {
            return;
        }

        $context = [
            'coursename' => $course->fullname ?? '',
            'courseshortname' => $course->shortname ?? '',
            'grade_percent' => round($percentage, 2),
            'item_name' => $gradeitem->itemname ?? '',
        ];

        // --- grade_passed ---
        // Load rules, check each threshold
        $passedrules = $DB->get_records('local_aynurasurveys_rules', [
            'trigger' => trigger_manager::TRIGGER_GRADE_PASSED,
            'enabled' => 1,
        ]);

        foreach ($passedrules as $rule) {
            $conditions = trigger_manager::get_conditions($rule);
            $threshold  = (float) ($conditions['threshold'] ?? 0);
            if ($percentage >= $threshold) {
                trigger_manager::fire(
                    trigger_manager::TRIGGER_GRADE_PASSED,
                    $user,
                    $courseid,
                    $context
                );
                break; // fire() handles iteration of all matching rules.
            }
        }

        // --- grade_failed ---
        $failedrules = $DB->get_records('local_aynurasurveys_rules', [
            'trigger' => trigger_manager::TRIGGER_GRADE_FAILED,
            'enabled' => 1,
        ]);

        foreach ($failedrules as $rule) {
            $conditions = trigger_manager::get_conditions($rule);
            $threshold  = (float) ($conditions['threshold'] ?? 100);
            if ($percentage < $threshold) {
                trigger_manager::fire(
                    trigger_manager::TRIGGER_GRADE_FAILED,
                    $user,
                    $courseid,
                    $context
                );
                break;
            }
        }
    }

    // -----------------------------------------------------------------------
    // Activity Completion Trigger
    // -----------------------------------------------------------------------

    /**
     * Handles \core\event\course_module_completion_updated
     *
     * Trigger: activity_completed
     * Fires when an activity's completion status is updated to complete
     * Checks rule condition { "cmid": N } to match specific activity,
     * or fires for any activity if global scope with no cmid set.
     */
    public static function activity_completion_updated(\core\event\course_module_completion_updated $event): void {
        global $DB;

        // Only fire on completion (completionstate 1 = complete, 2 = complete with pass).
        // Note: for course_module_completion_updated:.
        //   $event->objectid          = course_modules_completion.id (the completion record ID).
        //   $event->contextinstanceid = course module ID (cmid).
        //   $event->other['completionstate'] = the new state.
        $completionstate = $event->other['completionstate'] ?? 0;
        if ($completionstate < 1) {
            return;
        }

        $userid   = $event->relateduserid;
        $courseid = $event->courseid;
        $cmid     = $event->contextinstanceid; // actual course module ID.

        $user   = $DB->get_record('user', ['id' => $userid], 'id, email, firstname, lastname, lang');
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname');
        if (!$user || !$course) {
            return;
        }

        // Get the activity name for context.
        $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id, module, instance');
        $activityname = '';
        if ($cm) {
            $modname = $DB->get_field('modules', 'name', ['id' => $cm->module]);
            if ($modname) {
                $activityname = $DB->get_field($modname, 'name', ['id' => $cm->instance]) ?: '';
            }
        }

        $context = [
            'coursename' => $course->fullname,
            'courseshortname' => $course->shortname,
            'cmid' => $cmid,
            'activity_name' => $activityname,
        ];

        // Load enabled activity_completed rules.
        $rules = $DB->get_records('local_aynurasurveys_rules', [
            'trigger' => trigger_manager::TRIGGER_ACTIVITY_COMPLETED,
            'enabled' => 1,
        ]);

        foreach ($rules as $rule) {
            $conditions = trigger_manager::get_conditions($rule);
            $rulecmid  = isset($conditions['cmid']) ? (int) $conditions['cmid'] : 0;

            // If rule has a specific cmid, it must match.
            // If no cmid set (global scope / any activity), always proceed.
            if ($rulecmid > 0 && $rulecmid !== (int) $cmid) {
                continue;
            }

            trigger_manager::fire(
                trigger_manager::TRIGGER_ACTIVITY_COMPLETED,
                $user,
                $courseid,
                $context
            );
            // Fire() handles scope, frequency, and dispatch — break after.
            // First match to avoid duplicate fire() calls for same rule set.
            break;
        }
    }

    // -----------------------------------------------------------------------
    // Quiz Triggers
    // -----------------------------------------------------------------------

    /**
     * Handles \mod_quiz\event\attempt_submitted
     *
     * Evaluates quiz_passed and quiz_failed triggers
     * Grade threshold comes from each rule's conditions: { "threshold": N }
     * where N is a percentage (0-100).
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        global $DB;

        $userid   = $event->userid;
        $courseid = $event->courseid;
        $quizid   = $event->objectid;

        // Load the quiz attempt to get the final grade.
        $attempt = $DB->get_record('quiz_attempts', [
            'id' => $event->other['attemptid'] ?? 0,
        ], 'id, quiz, userid, sumgrades, state');

        if (!$attempt || $attempt->state !== 'finished') {
            return;
        }

        // Get the quiz max grade.
        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id, sumgrades, grade, course');
        if (!$quiz || (float) $quiz->sumgrades == 0) {
            return;
        }

        // Calculate percentage.
        $percentage = ((float) $attempt->sumgrades / (float) $quiz->sumgrades) * 100;

        $user   = $DB->get_record('user', ['id' => $userid], 'id, email, firstname, lastname, lang');
        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname');
        if (!$user || !$course) {
            return;
        }

        $context = [
            'coursename' => $course->fullname,
            'courseshortname' => $course->shortname,
            'quiz_id' => $quizid,
            'grade_percent' => round($percentage, 2),
        ];

        // Quiz_passed — check each rule's threshold.
        $passedrules = $DB->get_records('local_aynurasurveys_rules', [
            'trigger' => trigger_manager::TRIGGER_QUIZ_PASSED,
            'enabled' => 1,
        ]);
        foreach ($passedrules as $rule) {
            $conditions = trigger_manager::get_conditions($rule);
            $threshold  = (float) ($conditions['threshold'] ?? 0);
            if ($percentage >= $threshold) {
                trigger_manager::fire(
                    trigger_manager::TRIGGER_QUIZ_PASSED,
                    $user, $courseid, $context
                );
                break;
            }
        }

        // Quiz_failed — check each rule's threshold.
        $failedrules = $DB->get_records('local_aynurasurveys_rules', [
            'trigger' => trigger_manager::TRIGGER_QUIZ_FAILED,
            'enabled' => 1,
        ]);
        foreach ($failedrules as $rule) {
            $conditions = trigger_manager::get_conditions($rule);
            $threshold  = (float) ($conditions['threshold'] ?? 100);
            if ($percentage < $threshold) {
                trigger_manager::fire(
                    trigger_manager::TRIGGER_QUIZ_FAILED,
                    $user, $courseid, $context
                );
                break;
            }
        }

        // Log quiz completion for days_after_quiz cron trigger.
        // Store in a lightweight way using the existing log table with a special status.
        if (!$DB->record_exists('local_aynurasurveys_log', [
            'userid' => $userid,
            'trigger' => 'quiz_completion_recorded',
            'courseid' => $courseid,
            'surveyid' => (string) $quizid,
        ])) {
            $DB->insert_record('local_aynurasurveys_log', (object) [
                'ruleid' => 0,
                'userid' => $userid,
                'surveyid' => (string) $quizid,
                'trigger' => 'quiz_completion_recorded',
                'courseid' => $courseid,
                'status' => 'info',
                'statuscode' => null,
                'response' => json_encode(['grade_percent' => round($percentage, 2)]),
                'timecreated' => time(),
            ]);
        }
    }
}
