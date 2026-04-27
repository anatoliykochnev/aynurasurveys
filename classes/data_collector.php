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
 * Data collector for local_aynurasurveys.
 *
 * Gathers enrichment data from Moodle at survey submission time:
 *   - Custom user profile fields
 *   - Course metadata + course custom fields
 *   - Cohort memberships
 *   - Trigger/plugin context
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aynurasurveys;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Collects Moodle user, course, and cohort metadata at survey submission time.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_collector {
    /**
     * Collect all available metadata for a user + course context.
     *
     * @param  \stdClass  $user      Moodle user record.
     * @param  int|null   $courseid  Course ID if survey is course-triggered.
     * @param  array      $context   Extra context from trigger (trigger type, activity name, etc.)
     * @return array      Metadata array ready to be sent as 'metadata' in the API payload.
     */
    public static function collect(\stdClass $user, ?int $courseid = null, array $context = []): array {
        $metadata = [];

        $metadata['profile']  = self::get_profile_fields($user);
        $metadata['cohorts']  = self::get_cohorts($user->id);
        $metadata['moodle']   = self::get_moodle_context($context);

        if ($courseid && $courseid !== SITEID) {
            $metadata['course'] = self::get_course_data($courseid);
        }

        // Remove empty top-level keys to keep payload clean.
        return array_filter($metadata, function ($v) {
            if (is_array($v)) {
                return !empty($v);
            }
            return $v !== null && $v !== '';
        });
    }

    // -----------------------------------------------------------------------
    // Profile fields.
    // -----------------------------------------------------------------------

    /**
     * Get all custom user profile field values
     * Returns key-value pairs where key = field shortname, value = field value.
     * Empty/null/unset fields are omitted.
     *
     * @param  \stdClass $user
     * @return array
     */
    private static function get_profile_fields(\stdClass $user): array {
        global $DB;

        $profile = [];

        // --- Standard Moodle user fields ---.
        $standardfields = [
            'username' => 'username',
            'city' => 'city',
            'country' => 'country',
            'lang' => 'language',
            'timezone' => 'timezone',
            'institution' => 'institution',
            'department' => 'department',
            'phone1' => 'phone',
            'phone2' => 'phone2',
            'address' => 'address',
            'description' => 'description',
            'url' => 'website',
            'idnumber' => 'id_number',
        ];

        foreach ($standardfields as $prop => $key) {
            if (!empty($user->$prop)) {
                $profile[$key] = (string) $user->$prop;
            }
        }

        // --- Custom user profile fields ---.
        try {
            profile_load_data($user);
        } catch (\Exception $e) {
            return $profile;
        }

        $fields = $DB->get_records('user_info_field', [], 'sortorder ASC', 'id, shortname, datatype');
        foreach ($fields as $field) {
            $prop = 'profile_field_' . $field->shortname;
            if (!isset($user->$prop)) {
                continue;
            }

            $raw = $user->$prop;
            if ($raw === null || $raw === '' || $raw === false) {
                continue;
            }

            switch ($field->datatype) {
                case 'datetime':
                    $profile[$field->shortname] = date('Y-m-d', (int) $raw);
                    break;
                case 'checkbox':
                    $profile[$field->shortname] = (bool) $raw ? 'yes' : 'no';
                    break;
                default:
                    $profile[$field->shortname] = (string) $raw;
                    break;
            }
        }

        return $profile;
    }

    // -----------------------------------------------------------------------
    // Course data.
    // -----------------------------------------------------------------------

    /**
     * Get course metadata including standard fields and custom course fields
     *
     * @param  int $courseid
     * @return array
     */
    private static function get_course_data(int $courseid): array {
        global $DB;

        $course = $DB->get_record(
            'course',
            ['id' => $courseid],
            'id, fullname, shortname, startdate, enddate, category'
        );
        if (!$course) {
            return [];
        }

        // Get category name.
        $categoryname = '';
        if ($course->category) {
            $categoryname = $DB->get_field('course_categories', 'name', ['id' => $course->category]) ?: '';
        }

        $data = [
            'fullname' => $course->fullname,
            'shortname' => $course->shortname,
            'category' => $categoryname,
            'start_date' => $course->startdate ? date('Y-m-d', $course->startdate) : null,
            'end_date' => $course->enddate ? date('Y-m-d', $course->enddate) : null,
        ];

        // Course custom fields (Moodle 3.7+).
        $custom = self::get_course_custom_fields($courseid);
        if (!empty($custom)) {
            $data['custom_fields'] = $custom;
        }

        // Remove nulls.
        return array_filter($data, fn($v) => $v !== null && $v !== '');
    }

    /**
     * Get course custom field values.
     *
     * @param  int $courseid
     * @return array key => value
     */
    private static function get_course_custom_fields(int $courseid): array {
        global $DB;

        try {
            // Use Moodle's custom field API if available.
            $handler = \core_course\customfield\course_handler::create();
            $datas   = $handler->get_instance_data($courseid, true);
            $result  = [];
            foreach ($datas as $data) {
                $field = $data->get_field();
                $val   = $data->export_value();
                if ($val === null || $val === '') {
                    continue;
                }
                $result[$field->get('shortname')] = (string) $val;
            }
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    // -----------------------------------------------------------------------
    // Cohorts.
    // -----------------------------------------------------------------------

    /**
     * Get all cohort names the user belongs to
     *
     * @param  int $userid
     * @return array Array of cohort name strings.
     */
    private static function get_cohorts(int $userid): array {
        global $DB;

        try {
            $sql = "SELECT c.name
                      FROM {cohort} c
                      JOIN {cohort_members} cm ON cm.cohortid = c.id
                     WHERE cm.userid = :userid
                     ORDER BY c.name ASC";
            $cohorts = $DB->get_records_sql($sql, ['userid' => $userid]);
            return array_values(array_map(fn($c) => $c->name, $cohorts));
        } catch (\Exception $e) {
            return [];
        }
    }

    // -----------------------------------------------------------------------
    // Moodle context.
    // -----------------------------------------------------------------------

    /**
     * Build the moodle context block
     *
     * @param  array $context  Context data from trigger (trigger, activity_name, etc.)
     * @return array
     */
    private static function get_moodle_context(array $context): array {
        global $CFG;

        $moodle = [
            'trigger' => $context['trigger'] ?? null,
            'activity_name' => $context['activity_name'] ?? null,
            'site_url' => rtrim($CFG->wwwroot, '/'),
            'submission_time' => date('c'),
        ];

        // Remove nulls.
        return array_filter($moodle, fn($v) => $v !== null && $v !== '');
    }
}
