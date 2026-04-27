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
 * Privacy provider for local_aynurasurveys.
 *
 * Required by Moodle's Privacy API (GDPR compliance).
 * We store user IDs in the delivery log — this class describes that data
 * and implements export/deletion.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aynurasurveys\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API provider for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    // -----------------------------------------------------------------------
    // Metadata
    // -----------------------------------------------------------------------

    /**
     * Get metadata
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_aynurasurveys_log',
            [
                'userid' => 'privacy:metadata:local_aynurasurveys_log:userid',
                'surveyid' => 'privacy:metadata:local_aynurasurveys_log:surveyid',
                'trigger' => 'privacy:metadata:local_aynurasurveys_log:trigger',
                'status' => 'privacy:metadata:local_aynurasurveys_log:status',
                'timecreated' => 'privacy:metadata:local_aynurasurveys_log:timecreated',
            ],
            'privacy:metadata:local_aynurasurveys_log'
        );
        return $collection;
    }

    // -----------------------------------------------------------------------
    // Context discovery
    // -----------------------------------------------------------------------

    /**
     * Get contexts for userid
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    /**
     * Get users in context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $sql = "SELECT DISTINCT userid FROM {local_aynurasurveys_log}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    // -----------------------------------------------------------------------
    // Export
    // -----------------------------------------------------------------------

    /**
     * Export user data
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $userid = $contextlist->get_user()->id;
            $logs   = $DB->get_records('local_aynurasurveys_log', ['userid' => $userid]);
            if (!$logs) continue;

            $data = array_values(array_map(function($log) {
                return [
                    'surveyid' => $log->surveyid,
                    'trigger' => $log->trigger,
                    'status' => $log->status,
                    'timecreated' => \core_privacy\local\request\transform::datetime($log->timecreated),
                ];
            }, $logs));

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_aynurasurveys')],
                (object) ['dispatch_log' => $data]
            );
        }
    }

    // -----------------------------------------------------------------------
    // Deletion
    // -----------------------------------------------------------------------

    /**
     * Delete data for all users in context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof \context_system) {
            $DB->delete_records('local_aynurasurveys_log');
        }
    }

    /**
     * Delete data for user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $DB->delete_records('local_aynurasurveys_log', ['userid' => $contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Delete data for users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) return;

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_aynurasurveys_log', "userid {$insql}", $params);
    }
}
