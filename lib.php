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
 * Plugin hook callbacks for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Plugin hook callbacks for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Plugin hook callbacks for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Hook: called after every page requires login.
 *
 * Handles two responsibilities:
 * 1. Fire login-based triggers (every_login, first_login, login_after_inactivity)
 *    once per session — runs outside DB transaction so API calls work safely.
 * 2. Show any pending survey modal to the current user.
 */
function local_aynurasurveys_after_require_login() {
    global $USER, $PAGE, $DB, $SESSION, $CFG;

    // Skip guests, CLI, and ajax requests.
    if (!isloggedin() || isguestuser() || CLI_SCRIPT || AJAX_SCRIPT) {
        return;
    }

    if (!get_config('local_aynurasurveys', 'enabled')) {
        return;
    }

    // ----------------------------------------------------------------
    // Step 1: Fire login-based triggers once per session.
    // We use a session flag to ensure this only runs once per login,.
    // Not on every page load. This runs outside any DB transaction.
    // So dispatch() works correctly.
    // ----------------------------------------------------------------
    if (empty($SESSION->aynurasurveys_login_triggered)) {
        $SESSION->aynurasurveys_login_triggered = true;

        try {
            $user = $DB->get_record(
                'user',
                ['id' => $USER->id],
                'id, email, firstname, lastname, lang, firstaccess, lastaccess'
            );

            if ($user) {
                $now = time();

                // First_login: firstaccess set within last 60 seconds.
                if ($user->firstaccess && ($now - $user->firstaccess) < 60) {
                    \local_aynurasurveys\trigger_manager::fire(
                        \local_aynurasurveys\trigger_manager::TRIGGER_FIRST_LOGIN,
                        $user,
                        null,
                        ['login_time' => date('c', $now)]
                    );
                }

                // Every_login: always fires.
                \local_aynurasurveys\trigger_manager::fire(
                    \local_aynurasurveys\trigger_manager::TRIGGER_EVERY_LOGIN,
                    $user,
                    null,
                    ['login_time' => date('c', $now)]
                );

                // Login_after_inactivity: check each rule's threshold.
                if ($user->lastaccess > 0) {
                    $daysinactive = (int) floor(($now - $user->lastaccess) / DAYSECS);
                    $rules = $DB->get_records('local_aynurasurveys_rules', [
                        'trigger' => \local_aynurasurveys\trigger_manager::TRIGGER_LOGIN_AFTER_INACTIVITY,
                        'enabled' => 1,
                    ]);
                    foreach ($rules as $rule) {
                        $conditions = \local_aynurasurveys\trigger_manager::get_conditions($rule);
                        $threshold  = (int) ($conditions['days'] ?? 0);
                        if ($threshold > 0 && $daysinactive >= $threshold) {
                            \local_aynurasurveys\trigger_manager::fire(
                                \local_aynurasurveys\trigger_manager::TRIGGER_LOGIN_AFTER_INACTIVITY,
                                $user,
                                null,
                                ['daysinactive' => $daysinactive, 'login_time' => date('c', $now)]
                            );
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Non-fatal — log and continue to modal display.
            debugging('local_aynurasurveys: login trigger error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // ----------------------------------------------------------------
    // Step 2: Show pending survey modal.
    // ----------------------------------------------------------------
    $now = time();

    // Determine current course context.
    // Guard against $PAGE->context not being set yet (fires early on some pages).
    $currentcourseid = null;
    try {
        if ($PAGE->has_set_url() || !empty($PAGE->context)) {
            $ctx = $PAGE->context;
            if ($ctx instanceof \context_course) {
                $currentcourseid = $ctx->instanceid;
            } else {
                $coursecontext = $ctx->get_course_context(false);
                if ($coursecontext) {
                    $currentcourseid = $coursecontext->instanceid;
                }
            }
            if ($currentcourseid == SITEID) {
                $currentcourseid = null;
            }
        }
    } catch (\Exception $e) {
        $currentcourseid = null;
    }

    // Find the oldest pending survey ready to show.
    $pendings = $DB->get_records_select(
        'local_aynurasurveys_pending',
        'userid = :userid AND status = :status AND (show_after IS NULL OR show_after <= :now)',
        ['userid' => $USER->id, 'status' => 'pending', 'now' => $now],
        'timecreated ASC'
    );

    $pending = null;
    foreach ($pendings as $p) {
        $displaycontext = $p->display_context ?? 'site';
        if ($displaycontext === 'site') {
            $pending = $p;
            break;
        }
        if (
            $displaycontext === 'course'
            && $currentcourseid !== null
            && (int) $p->courseid === (int) $currentcourseid
        ) {
            $pending = $p;
            break;
        }
    }

    if (!$pending) {
        return;
    }

    // Pass minimal bootstrap data — full survey data fetched via AJAX.
    $modaldata = [
        'pendingid' => (int) $pending->id,
        'ajaxurl' => (new \moodle_url('/local/aynurasurveys/ajax.php'))->out(false),
        'sesskey' => sesskey(),
    ];

    $PAGE->requires->js_call_amd('local_aynurasurveys/modal', 'init', [$modaldata]);
}
