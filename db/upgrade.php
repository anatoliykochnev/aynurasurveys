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
 * Upgrade steps for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade steps for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade steps for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Sebale <info@sebale.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the local_aynurasurveys plugin.
 *
 * @param int $oldversion The old plugin version.
 * @return bool
 */
function xmldb_local_aynurasurveys_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026041000) {
        $table = new xmldb_table('local_aynurasurveys_pending');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ruleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('surveyid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('surveyname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('trigger', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('questions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('language', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'en');
        $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeresolved', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('fk_rule', XMLDB_KEY_FOREIGN, ['ruleid'], 'local_aynurasurveys_rules', ['id']);
        $table->add_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('idx_user_pending', XMLDB_INDEX_NOTUNIQUE, ['userid', 'status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026041000, 'local', 'aynurasurveys');
    }

    if ($oldversion < 2026041100) {
        // Local_aynurasurveys_rules: add valid_from, valid_until, display_context.
        $rulestable = new xmldb_table('local_aynurasurveys_rules');

        $f = new xmldb_field('display_context', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'site', 'scope');
        if (!$dbman->field_exists($rulestable, $f)) {
            $dbman->add_field($rulestable, $f);
        }

        $f = new xmldb_field('valid_from', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'conditions');
        if (!$dbman->field_exists($rulestable, $f)) {
            $dbman->add_field($rulestable, $f);
        }

        $f = new xmldb_field('valid_until', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'valid_from');
        if (!$dbman->field_exists($rulestable, $f)) {
            $dbman->add_field($rulestable, $f);
        }

        // Local_aynurasurveys_log: replace idx_user_survey with idx_user_survey_course.
        $logtable = new xmldb_table('local_aynurasurveys_log');
        $oldidx = new xmldb_index('idx_user_survey', XMLDB_INDEX_NOTUNIQUE, ['userid', 'surveyid']);
        if ($dbman->index_exists($logtable, $oldidx)) {
            $dbman->drop_index($logtable, $oldidx);
        }
        $newidx = new xmldb_index('idx_user_survey_course', XMLDB_INDEX_NOTUNIQUE, ['userid', 'surveyid', 'courseid']);
        if (!$dbman->index_exists($logtable, $newidx)) {
            $dbman->add_index($logtable, $newidx);
        }

        // Local_aynurasurveys_pending: add display_context.
        $pendingtable = new xmldb_table('local_aynurasurveys_pending');
        $f = new xmldb_field('display_context', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'site', 'courseid');
        if (!$dbman->field_exists($pendingtable, $f)) {
            $dbman->add_field($pendingtable, $f);
        }

        upgrade_plugin_savepoint(true, 2026041100, 'local', 'aynurasurveys');
    }

    // 2026041200 — version bump, no schema changes.
    if ($oldversion < 2026041200) {
        upgrade_plugin_savepoint(true, 2026041200, 'local', 'aynurasurveys');
    }

    // 2026041300 — add delay_minutes to rules, show_after to pending.
    if ($oldversion < 2026041300) {
        // Local_aynurasurveys_rules: add delay_minutes.
        $rulestable = new xmldb_table('local_aynurasurveys_rules');
        $f = new xmldb_field('delay_minutes', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'valid_until');
        if (!$dbman->field_exists($rulestable, $f)) {
            $dbman->add_field($rulestable, $f);
        }

        // Local_aynurasurveys_pending: add show_after.
        $pendingtable = new xmldb_table('local_aynurasurveys_pending');
        $f = new xmldb_field('show_after', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'language');
        if (!$dbman->field_exists($pendingtable, $f)) {
            $dbman->add_field($pendingtable, $f);
        }

        upgrade_plugin_savepoint(true, 2026041300, 'local', 'aynurasurveys');
    }

    // 2026041301 — UI redesign, no schema changes.
    if ($oldversion < 2026041301) {
        upgrade_plugin_savepoint(true, 2026041301, 'local', 'aynurasurveys');
    }

    if ($oldversion < 2026041302) {
        upgrade_plugin_savepoint(true, 2026041302, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041303) {
        upgrade_plugin_savepoint(true, 2026041303, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041304) {
        upgrade_plugin_savepoint(true, 2026041304, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041305) {
        upgrade_plugin_savepoint(true, 2026041305, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041306) {
        upgrade_plugin_savepoint(true, 2026041306, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041307) {
        upgrade_plugin_savepoint(true, 2026041307, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041308) {
        upgrade_plugin_savepoint(true, 2026041308, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041309) {
        upgrade_plugin_savepoint(true, 2026041309, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041310) {
        upgrade_plugin_savepoint(true, 2026041310, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041311) {
        upgrade_plugin_savepoint(true, 2026041311, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041312) {
        upgrade_plugin_savepoint(true, 2026041312, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041313) {
        upgrade_plugin_savepoint(true, 2026041313, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041314) {
        upgrade_plugin_savepoint(true, 2026041314, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041315) {
        upgrade_plugin_savepoint(true, 2026041315, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041316) {
        upgrade_plugin_savepoint(true, 2026041316, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041317) {
        upgrade_plugin_savepoint(true, 2026041317, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041318) {
        upgrade_plugin_savepoint(true, 2026041318, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041319) {
        upgrade_plugin_savepoint(true, 2026041319, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041320) {
        upgrade_plugin_savepoint(true, 2026041320, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041321) {
        $table = new xmldb_table('local_aynurasurveys_rules');
        $field = new xmldb_field('rulename', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'trigger');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026041321, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041322) {
        upgrade_plugin_savepoint(true, 2026041322, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041323) {
        upgrade_plugin_savepoint(true, 2026041323, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041324) {
        upgrade_plugin_savepoint(true, 2026041324, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041325) {
        upgrade_plugin_savepoint(true, 2026041325, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041326) {
        upgrade_plugin_savepoint(true, 2026041326, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041327) {
        upgrade_plugin_savepoint(true, 2026041327, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041328) {
        upgrade_plugin_savepoint(true, 2026041328, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041329) {
        upgrade_plugin_savepoint(true, 2026041329, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041330) {
        upgrade_plugin_savepoint(true, 2026041330, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041331) {
        upgrade_plugin_savepoint(true, 2026041331, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041332) {
        upgrade_plugin_savepoint(true, 2026041332, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041333) {
        upgrade_plugin_savepoint(true, 2026041333, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041334) {
        upgrade_plugin_savepoint(true, 2026041334, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041335) {
        upgrade_plugin_savepoint(true, 2026041335, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041336) {
        upgrade_plugin_savepoint(true, 2026041336, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026041337) {
        // Rename: local_hubsurveys -> local_aynurasurveys.
        // DB tables are created fresh on new install via install.xml.
        // For existing installs upgrading from local_hubsurveys, tables.
        // Are renamed in this step.
        $tables = [
            'local_hubsurveys_rules' => 'local_aynurasurveys_rules',
            'local_hubsurveys_rule_courses' => 'local_aynurasurveys_rule_courses',
            'local_hubsurveys_log' => 'local_aynurasurveys_log',
            'local_hubsurveys_pending' => 'local_aynurasurveys_pending',
        ];
        foreach ($tables as $old => $new) {
            $oldtable = new xmldb_table($old);
            if ($dbman->table_exists($oldtable) && !$dbman->table_exists(new xmldb_table($new))) {
                $dbman->rename_table($oldtable, $new);
            }
        }
        upgrade_plugin_savepoint(true, 2026041337, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042401) {
        upgrade_plugin_savepoint(true, 2026042401, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042402) {
        upgrade_plugin_savepoint(true, 2026042402, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042403) {
        upgrade_plugin_savepoint(true, 2026042403, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042404) {
        upgrade_plugin_savepoint(true, 2026042404, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042405) {
        upgrade_plugin_savepoint(true, 2026042405, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042406) {
        upgrade_plugin_savepoint(true, 2026042406, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042407) {
        upgrade_plugin_savepoint(true, 2026042407, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042408) {
        upgrade_plugin_savepoint(true, 2026042408, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042409) {
        upgrade_plugin_savepoint(true, 2026042409, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042410) {
        upgrade_plugin_savepoint(true, 2026042410, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042411) {
        upgrade_plugin_savepoint(true, 2026042411, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042412) {
        upgrade_plugin_savepoint(true, 2026042412, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042413) {
        upgrade_plugin_savepoint(true, 2026042413, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042414) {
        upgrade_plugin_savepoint(true, 2026042414, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042415) {
        upgrade_plugin_savepoint(true, 2026042415, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042416) {
        upgrade_plugin_savepoint(true, 2026042416, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042417) {
        upgrade_plugin_savepoint(true, 2026042417, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042418) {
        upgrade_plugin_savepoint(true, 2026042418, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042419) {
        upgrade_plugin_savepoint(true, 2026042419, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042420) {
        upgrade_plugin_savepoint(true, 2026042420, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042421) {
        upgrade_plugin_savepoint(true, 2026042421, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042422) {
        upgrade_plugin_savepoint(true, 2026042422, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042423) {
        upgrade_plugin_savepoint(true, 2026042423, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042424) {
        upgrade_plugin_savepoint(true, 2026042424, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042425) {
        upgrade_plugin_savepoint(true, 2026042425, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042426) {
        upgrade_plugin_savepoint(true, 2026042426, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042427) {
        upgrade_plugin_savepoint(true, 2026042427, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042429) {
        upgrade_plugin_savepoint(true, 2026042429, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042430) {
        upgrade_plugin_savepoint(true, 2026042430, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042431) {
        upgrade_plugin_savepoint(true, 2026042431, 'local', 'aynurasurveys');
    }
    if ($oldversion < 2026042402) {
        upgrade_plugin_savepoint(true, 2026042402, 'local', 'aynurasurveys');
    }
    return true;
}
