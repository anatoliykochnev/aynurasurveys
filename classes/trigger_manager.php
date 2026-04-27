<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Trigger manager for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aynurasurveys;

defined('MOODLE_INTERNAL') || die();

class trigger_manager {

    // Trigger type constants.
    const TRIGGER_FIRST_LOGIN            = 'first_login';
    const TRIGGER_EVERY_LOGIN            = 'every_login';
    const TRIGGER_LOGIN_AFTER_INACTIVITY = 'login_after_inactivity';
    const TRIGGER_DAYS_AFTER_ENROLLMENT  = 'days_after_enrollment';
    const TRIGGER_UNENROLLED             = 'unenrolled';
    const TRIGGER_COURSE_PERCENT         = 'course_percent';
    const TRIGGER_COURSE_STARTED         = 'course_started';
    const TRIGGER_COURSE_COMPLETED       = 'course_completed';
    const TRIGGER_GRADE_PASSED           = 'grade_passed';
    const TRIGGER_GRADE_FAILED           = 'grade_failed';
    const TRIGGER_FIXED_DATE             = 'fixed_date';
    const TRIGGER_RECURRING              = 'recurring';
    const TRIGGER_DAYS_AFTER_START       = 'days_after_start';
    const TRIGGER_DAYS_BEFORE_END        = 'days_before_end';
    const TRIGGER_DAYS_AFTER_COMPLETION  = 'days_after_completion';
    const TRIGGER_QUIZ_PASSED            = 'quiz_passed';
    const TRIGGER_QUIZ_FAILED            = 'quiz_failed';
    const TRIGGER_DAYS_AFTER_QUIZ        = 'days_after_quiz';
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
    // Main dispatch entry point
    // -----------------------------------------------------------------------

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
    // Validity period check
    // -----------------------------------------------------------------------

    public static function is_rule_valid(\stdClass $rule, ?int $today = null): bool {
        $today = $today ?? mktime(0, 0, 0);

        if (!empty($rule->valid_from) && $today < (int) $rule->valid_from) {
            return false; // Not started yet.
        }

        if (!empty($rule->valid_until)) {
            $end_of_day = (int) $rule->valid_until + 86399; // 23:59:59.
            if ($today > $end_of_day) {
                return false; // Expired.
            }
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // Scope check
    // -----------------------------------------------------------------------

    private static function rule_matches_course(\stdClass $rule, ?int $courseid): bool {
        global $DB;

        if ($rule->scope === 'global') {
            return true;
        }

        if ($courseid === null) {
            return false;
        }

        return $DB->record_exists('local_aynurasurveys_rule_courses', [
            'ruleid'   => $rule->id,
            'courseid' => $courseid,
        ]);
    }

    // -----------------------------------------------------------------------
    // Frequency protection
    // -----------------------------------------------------------------------

    /**
     * Returns true if a trigger is repeating (dismiss = delete, not permanently block).
     * Repeating triggers re-queue the survey on the next event occurrence.
     */
    public static function is_repeating(string $trigger): bool {
        return in_array($trigger, [
            self::TRIGGER_EVERY_LOGIN,
            self::TRIGGER_LOGIN_AFTER_INACTIVITY,
            self::TRIGGER_RECURRING,
        ], true);
    }

    private static function already_dispatched(\stdClass $rule, int $userid, ?int $courseid): bool {
        global $DB;

        $display_context = $rule->display_context ?? 'site';
        $is_repeating    = self::is_repeating($rule->trigger);

        if ($is_repeating) {
            // Repeating triggers: only block if a pending record exists (survey queued but not seen yet).
            // Dismissed records are deleted, completed records allow re-queue on next event.
            if ($DB->record_exists('local_aynurasurveys_pending', [
                'userid'  => $userid,
                'ruleid'  => $rule->id,
                'status'  => 'pending',
            ])) {
                return true;
            }
        } else {
            // One-time triggers: block if any record exists (pending, completed, or dismissed).
            [$insql, $inparams] = $DB->get_in_or_equal(
                ['pending', 'completed', 'dismissed'],
                SQL_PARAMS_NAMED, 'st'
            );
            if ($DB->record_exists_select(
                'local_aynurasurveys_pending',
                "userid = :uid AND ruleid = :rid AND status $insql",
                array_merge(['uid' => $userid, 'rid' => $rule->id], $inparams)
            )) {
                return true;
            }

            // Also block if already submitted successfully (log table).
            if ($display_context === 'course' && $courseid !== null) {
                return $DB->record_exists('local_aynurasurveys_log', [
                    'userid'   => $userid,
                    'surveyid' => $rule->surveyid,
                    'courseid' => $courseid,
                    'status'   => 'success',
                ]);
            }
            return $DB->record_exists('local_aynurasurveys_log', [
                'userid'   => $userid,
                'surveyid' => $rule->surveyid,
                'status'   => 'success',
            ]);
        }

        return false;
    }

    // -----------------------------------------------------------------------
    // Dispatch — writes pending modal record
    // -----------------------------------------------------------------------

    private static function dispatch(
        \stdClass $rule,
        \stdClass $user,
        string $trigger,
        ?int $courseid,
        array $context
    ): void {
        global $DB;

        try {
            // NOTE: Do NOT make HTTP API calls here — dispatch() runs inside a DB
            // transaction during login events. HTTP calls inside transactions can fail
            // silently. Full survey data (questions, translations) is fetched lazily
            // by ajax.php when the modal loads.

            $language = !empty($user->lang) ? $user->lang : 'en';

            // Calculate show_after from delay_minutes (0 = show immediately).
            $delay = (int) ($rule->delay_minutes ?? 0);
            $show_after = ($delay > 0) ? (time() + ($delay * 60)) : null;

            // Store minimal context — full survey data fetched at modal load time.
            $pending_data = [
                'questions'           => [],  // Fetched lazily by ajax.php load action.
                'survey'              => [],
                'survey_translations' => [],
                'trigger'             => $trigger,
                'activity_name'       => $context['activity_name'] ?? null,
                'coursename'          => $context['coursename']    ?? null,
            ];

            $pending = (object) [
                'userid'          => (int) $user->id,
                'ruleid'          => (int) $rule->id,
                'surveyid'        => $rule->surveyid,
                'surveyname'      => $rule->surveyname ?: $rule->surveyid,
                'trigger'         => $trigger,
                'courseid'        => $courseid,
                'display_context' => $rule->display_context ?? 'site',
                'questions'       => json_encode($pending_data),
                'language'        => $language,
                'show_after'      => $show_after,
                'status'          => 'pending',
                'timecreated'     => time(),
                'timeresolved'    => null,
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
    // Delivery log
    // -----------------------------------------------------------------------

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
            'ruleid'      => $rule->id,
            'userid'      => $userid,
            'surveyid'    => $rule->surveyid,
            'trigger'     => $trigger,
            'courseid'    => $courseid,
            'status'      => $status,
            'statuscode'  => $statuscode,
            'response'    => $response,
            'timecreated' => time(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public static function get_conditions(\stdClass $rule): array {
        if (empty($rule->conditions)) {
            return [];
        }
        return json_decode($rule->conditions, true) ?? [];
    }

    public static function is_login_trigger(string $trigger): bool {
        return in_array($trigger, self::LOGIN_TRIGGERS, true);
    }

    public static function is_quiz_trigger(string $trigger): bool {
        return in_array($trigger, self::QUIZ_TRIGGERS, true);
    }

    public static function requires_completion(string $trigger): bool {
        return in_array($trigger, self::COMPLETION_TRIGGERS, true);
    }
}
