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
 * Trigger manager for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aynurasurveys;

/**
 * Manages survey trigger evaluation, frequency protection, and dispatch.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trigger_manager {
    // Trigger type constants.
    /** @var string Trigger type: first login. */
    /** @var string Trigger type constant. */
    const TRIGGER_FIRST_LOGIN            = 'first_login';
    /** @var string Trigger type constant. */
    const TRIGGER_EVERY_LOGIN            = 'every_login';
    /** @var string Trigger type constant. */
    const TRIGGER_LOGIN_AFTER_INACTIVITY = 'login_after_inactivity';
    /** @var string Trigger type constant. */
    const TRIGGER_DAYS_AFTER_ENROLLMENT  = 'days_after_enrollment';
    /** @var string Trigger type constant. */
    const TRIGGER_UNENROLLED             = 'unenrolled';
    /** @var string Trigger type constant. */
    const TRIGGER_COURSE_PERCENT         = 'course_percent';
    /** @var string Trigger type constant. */
    const TRIGGER_COURSE_STARTED         = 'course_started';
    /** @var string Trigger type constant. */
    const TRIGGER_COURSE_COMPLETED       = 'course_completed';
    /** @var string Trigger type constant. */
    const TRIGGER_GRADE_PASSED           = 'grade_passed';
    /** @var string Trigger type constant. */
    const TRIGGER_GRADE_FAILED           = 'grade_failed';
    /** @var string Trigger type constant. */
    const TRIGGER_FIXED_DATE             = 'fixed_date';
    /** @var string Trigger type constant. */
    const TRIGGER_RECURRING              = 'recurring';
    /** @var string Trigger type constant. */
    const TRIGGER_DAYS_AFTER_START       = 'days_after_start';
    /** @var string Trigger type constant. */
    const TRIGGER_DAYS_BEFORE_END        = 'days_before_end';
    /** @var string Trigger type constant. */
    const TRIGGER_DAYS_AFTER_COMPLETION  = 'days_after_completion';
    /** @var string Trigger type constant. */
    const TRIGGER_QUIZ_PASSED            = 'quiz_passed';
    /** @var string Trigger type constant. */
    const TRIGGER_QUIZ_FAILED            = 'quiz_failed';
    /** @var string Trigger type constant. */
    const TRIGGER_DAYS_AFTER_QUIZ        = 'days_after_quiz';
    /** @var string Trigger type constant. */
    const TRIGGER_ACTIVITY_COMPLETED     = 'activity_completed';

    /** Login-only triggers — always site context, no course selector. */
    const LOGIN_TRIGGERS = [
        self::TRIGGER_FIRST_LOGIN,
        self::TRIGGER_EVERY_LOGIN,
        self::TRIGGER_LOGIN_AFTER_INACTIVITY,
    ];

    /** Triggers that require course completion tracking to be enabled. */
    const COMPLETION_TRIGGERS = [
        self::TRIGGER_COURSE_PERCENT,
        self::TRIGGER_COURSE_COMPLETED,
        self::TRIGGER_DAYS_AFTER_START,
        self::TRIGGER_DAYS_BEFORE_END,
        self::TRIGGER_DAYS_AFTER_COMPLETION,
        self::TRIGGER_ACTIVITY_COMPLETED,
    ];

    /** Quiz triggers — need grade context. */
    const QUIZ_TRIGGERS = [
        self::TRIGGER_QUIZ_PASSED,
        self::TRIGGER_QUIZ_FAILED,
        self::TRIGGER_DAYS_AFTER_QUIZ,
    ];

    /**
     * Get all triggers.
     */
    public static function get_all_triggers(): array {
        return [
            self::TRIGGER_FIRST_LOGIN,
            self::TRIGGER_EVERY_LOGIN,
            self::TRIGGER_LOGIN_AFTER_INACTIVITY,
            self::TRIGGER_DAYS_AFTER_ENROLLMENT,
            self::TRIGGER_UNENROLLED,
            self::TRIGGER_COURSE_PERCENT,
            self::TRIGGER_COURSE_STARTED,
            self::TRIGGER_COURSE_COMPLETED,
            self::TRIGGER_GRADE_PASSED,
            self::TRIGGER_GRADE_FAILED,
            self::TRIGGER_FIXED_DATE,
            self::TRIGGER_RECURRING,
            self::TRIGGER_DAYS_AFTER_START,
            self::TRIGGER_DAYS_BEFORE_END,
            self::TRIGGER_DAYS_AFTER_COMPLETION,
            self::TRIGGER_QUIZ_PASSED,
            self::TRIGGER_QUIZ_FAILED,
            self::TRIGGER_DAYS_AFTER_QUIZ,
            self::TRIGGER_ACTIVITY_COMPLETED,
        ];
    }

    // -----------------------------------------------------------------------
    // Main dispatch entry point.
    // -----------------------------------------------------------------------

    /**
     * Fire
     */
    public static function fire(
        string $trigger,
        \stdClass $user,
        ?int $courseid = null,
        array $extra = []
    ): void {
        global $DB;

        if (!get_config('local_aynurasurveys', 'enabled')) {
            return;
        }

        $rules = $DB->get_records('local_aynurasurveys_rules', [
            'trigger' => $trigger,
            'enabled' => 1,
        ]);

        if (empty($rules)) {
            return;
        }

        $today = mktime(0, 0, 0);

        foreach ($rules as $rule) {
            try {
                // Validity period check.
                if (!self::is_rule_valid($rule, $today)) {
                    continue;
                }

                // Scope check.
                if (!self::rule_matches_course($rule, $courseid)) {
                    continue;
                }

                // Frequency protection.
                if (self::already_dispatched($rule, $user->id, $courseid)) {
                    continue;
                }

                $context = array_merge($extra, ['courseid' => $courseid]);
                self::dispatch($rule, $user, $trigger, $courseid, $context);
            } catch (\Exception $e) {
                // Log and continue — don't let one rule failure block others.
                debugging('local_aynurasurveys: fire() error for rule ' . $rule->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    // -----------------------------------------------------------------------
    // Validity period check.
    // -----------------------------------------------------------------------

    /**
     * Is rule valid
     */
    public static function is_rule_valid(\stdClass $rule, ?int $today = null): bool {
        $today = $today ?? mktime(0, 0, 0);

        if (!empty($rule->valid_from) && $today < (int) $rule->valid_from) {
            return false; // Not started yet.
        }

        if (!empty($rule->valid_until)) {
            $endofday = (int) $rule->valid_until + 86399; // 23:59:59.
            if ($today > $endofday) {
                return false; // Expired.
            }
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // Scope check.
    // -----------------------------------------------------------------------

    /**
     * Rule matches course
     */
    private static function rule_matches_course(\stdClass $rule, ?int $courseid): bool {
        global $DB;

        if ($rule->scope === 'global') {
            return true;
        }

        if ($courseid === null) {
            return false;
        }

        return $DB->record_exists('local_aynurasurveys_rule_courses', [
            'ruleid' => $rule->id,
            'courseid' => $courseid,
        ]);
    }

    // -----------------------------------------------------------------------
    // Frequency protection.
    // -----------------------------------------------------------------------

    /**
     * Returns true if a trigger is repeating (dismiss = delete, not permanently block)
     * Repeating triggers re-queue the survey on the next event occurrence.
     */
    public static function is_repeating(string $trigger): bool {
        return in_array($trigger, [
            self::TRIGGER_EVERY_LOGIN,
            self::TRIGGER_LOGIN_AFTER_INACTIVITY,
            self::TRIGGER_RECURRING,
        ], true);
    }

    /**
     * Already dispatched.
     */
    private static function already_dispatched(\stdClass $rule, int $userid, ?int $courseid): bool {
        global $DB;

        $displaycontext = $rule->display_context ?? 'site';
        $isrepeating    = self::is_repeating($rule->trigger);

        if ($isrepeating) {
            // Repeating triggers: only block if a pending record exists (survey queued but not seen yet).
            // Dismissed records are deleted, completed records allow re-queue on next event.
            if ($DB->record_exists(
                'local_aynurasurveys_pending',
                [
                    'userid' => $userid,
                    'ruleid' => $rule->id,
                    'status' => 'pending',
                ]
            )) {
                return true;
            }
        } else {
            // One-time triggers: block if any record exists (pending, completed, or dismissed).
            [$insql, $inparams] = $DB->get_in_or_equal(
                ['pending', 'completed', 'dismissed'],
                SQL_PARAMS_NAMED,
                'st'
            );
            if (
                $DB->record_exists_select(
                    'local_aynurasurveys_pending',
                    "userid = :uid AND ruleid = :rid AND status $insql",
                    array_merge(['uid' => $userid, 'rid' => $rule->id], $inparams)
                )
            ) {
                return true;
            }

            // Also block if already submitted successfully (log table).
            if ($displaycontext === 'course' && $courseid !== null) {
                return $DB->record_exists('local_aynurasurveys_log', [
                    'userid' => $userid,
                    'surveyid' => $rule->surveyid,
                    'courseid' => $courseid,
                    'status' => 'success',
                ]);
            }
            return $DB->record_exists('local_aynurasurveys_log', [
                'userid' => $userid,
                'surveyid' => $rule->surveyid,
                'status' => 'success',
            ]);
        }

        return false;
    }

    // -----------------------------------------------------------------------
    // Dispatch — writes pending modal record.
    // -----------------------------------------------------------------------

    /**
     * Dispatch
     */
    private static function dispatch(
        \stdClass $rule,
        \stdClass $user,
        string $trigger,
        ?int $courseid,
        array $context
    ): void {
        global $DB;

        try {
            // NOTE: Do NOT make HTTP API calls here — dispatch() runs inside a DB.
            // Transaction during login events. HTTP calls inside transactions can fail.
            // Silently. Full survey data (questions, translations) is fetched lazily.
            // By ajax.php when the modal loads.

            $language = !empty($user->lang) ? $user->lang : 'en';

            // Calculate show_after from delay_minutes (0 = show immediately).
            $delay = (int) ($rule->delay_minutes ?? 0);
            $showafter = ($delay > 0) ? (time() + ($delay * 60)) : null;

            // Store minimal context — full survey data fetched at modal load time.
            $pendingdata = [
                'questions' => [], // Fetched lazily by ajax.php load action.
                'survey' => [],
                'survey_translations' => [],
                'trigger' => $trigger,
                'activity_name' => $context['activity_name'] ?? null,
                'coursename' => $context['coursename'] ?? null,
            ];

            $pending = (object) [
                'userid' => (int) $user->id,
                'ruleid' => (int) $rule->id,
                'surveyid' => $rule->surveyid,
                'surveyname' => $rule->surveyname ?: $rule->surveyid,
                'trigger' => $trigger,
                'courseid' => $courseid,
                'display_context' => $rule->display_context ?? 'site',
                'questions' => json_encode($pendingdata),
                'language' => $language,
                'show_after' => $showafter,
                'status' => 'pending',
                'timecreated' => time(),
                'timeresolved' => null,
            ];

            $DB->insert_record('local_aynurasurveys_pending', $pending);
            self::write_log($rule, $user->id, $trigger, $courseid, 'success', null, 'pending_modal_created');
        } catch (\Exception $e) {
            debugging(
                "local_aynurasurveys: dispatch failed for user {$user->id}, "
                . "survey {$rule->surveyid}: " . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            self::write_log($rule, $user->id, $trigger, $courseid, 'failed', null, $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // Delivery log.
    // -----------------------------------------------------------------------

    /**
     * Write log
     */
    private static function write_log(
        \stdClass $rule,
        int $userid,
        string $trigger,
        ?int $courseid,
        string $status,
        ?int $statuscode,
        ?string $response
    ): void {
        global $DB;

        $DB->insert_record('local_aynurasurveys_log', (object) [
            'ruleid' => $rule->id,
            'userid' => $userid,
            'surveyid' => $rule->surveyid,
            'trigger' => $trigger,
            'courseid' => $courseid,
            'status' => $status,
            'statuscode' => $statuscode,
            'response' => $response,
            'timecreated' => time(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers.
    // -----------------------------------------------------------------------

    /**
     * Get conditions
     */
    public static function get_conditions(\stdClass $rule): array {
        if (empty($rule->conditions)) {
            return [];
        }
        return json_decode($rule->conditions, true) ?? [];
    }

    /**
     * Is login trigger.
     */
    public static function is_login_trigger(string $trigger): bool {
        return in_array($trigger, self::LOGIN_TRIGGERS, true);
    }

    /**
     * Is quiz trigger.
     */
    public static function is_quiz_trigger(string $trigger): bool {
        return in_array($trigger, self::QUIZ_TRIGGERS, true);
    }

    /**
     * Requires completion.
     */
    public static function requires_completion(string $trigger): bool {
        return in_array($trigger, self::COMPLETION_TRIGGERS, true);
    }
}
