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
 * Language strings for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Language strings for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Language strings for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'Aynura.Surveys';

// Settings page.
$string['settings']                  = 'Aynura.Surveys Settings';
$string['settings_connection']       = 'Connection';
$string['settings_baseurl']          = 'Base URL';
$string['settings_baseurl_desc']     = 'The base URL of your Aynura.Surveys instance (e.g. https://surveys.sebale.net).';
$string['settings_apikey']           = 'API Key';
$string['settings_apikey_desc']      = 'Your organisation API key from the Aynura.Surveys API Docs page.';
$string['settings_testconnection']   = 'Test Connection';
$string['settings_enabled']          = 'Enable plugin';
$string['settings_enabled_desc']     = 'When disabled, no surveys will be triggered.';

// Connection test.
$string['connection_success']        = 'Connection successful.';
$string['connection_fail']           = 'Connection failed: {$a}';
$string['connection_notconfigured']  = 'Base URL and API Key must be configured first.';

// Trigger rules management.
$string['triggerrules']              = 'Trigger Rules';
$string['triggerrules_desc']         = 'Configure which surveys are sent for each event trigger.';
$string['addrule']                   = 'Add Trigger Rule';
$string['editrule']                  = 'Edit Trigger Rule';
$string['deleterule']                = 'Delete Rule';
$string['deleterule_confirm']        = 'Are you sure you want to delete this trigger rule?';
$string['norules']                   = 'No trigger rules configured yet.';
$string['rule_trigger']              = 'Trigger Event';
$string['rule_survey']               = 'Survey';
$string['rule_scope']                = 'Scope';
$string['rule_scope_global']         = 'Global (all courses)';
$string['rule_scope_course']         = 'Specific course(s)';
$string['rule_courses']              = 'Course(s)';
$string['rule_courses_help']         = 'Select one or more courses this rule applies to.';
$string['rule_enabled']              = 'Enabled';
$string['rule_conditions']           = 'Conditions';
$string['rule_saved']                = 'Rule saved successfully.';
$string['rule_deleted']              = 'Rule deleted.';

// Trigger types.
$string['trigger_first_login']            = 'First login ever';
$string['trigger_every_login']            = 'Every login';
$string['trigger_login_after_inactivity'] = 'Login after X days of inactivity';
$string['trigger_days_after_enrollment']  = 'X days after enrollment';
$string['trigger_unenrolled']             = 'Unenrolled from course';
$string['trigger_course_percent']         = 'X% of course completed';
$string['trigger_course_started']         = 'Course started (first activity accessed)';
$string['trigger_course_completed']       = 'Course completed';
$string['trigger_grade_passed']           = 'Passed grade threshold';
$string['trigger_grade_failed']           = 'Failed grade threshold';
$string['trigger_fixed_date']             = 'Fixed date/time';
$string['trigger_recurring']              = 'Recurring schedule';
$string['trigger_days_after_start']       = 'X days after course start date';
$string['trigger_days_before_end']        = 'X days before course end date';
$string['trigger_days_after_completion']  = 'X days after course completion';

// Condition field labels.
$string['condition_days']            = 'Days';
$string['condition_percent']         = 'Percentage (%)';
$string['condition_grade_threshold'] = 'Grade threshold (%)';
$string['condition_fixed_date']      = 'Date/time';
$string['condition_recurrence']      = 'Recurrence';
$string['condition_recurrence_daily']   = 'Daily';
$string['condition_recurrence_weekly']  = 'Weekly';
$string['condition_recurrence_monthly'] = 'Monthly';

// Delivery log.
$string['deliverylog']               = 'Delivery Log';
$string['deliverylog_desc']          = 'Recent webhook dispatch attempts.';
$string['log_userid']                = 'User';
$string['log_surveyid']              = 'Survey ID';
$string['log_trigger']               = 'Trigger';
$string['log_status']                = 'Status';
$string['log_statuscode']            = 'HTTP Status';
$string['log_timecreated']           = 'Time';
$string['log_status_success']        = 'Success';
$string['log_status_failed']         = 'Failed';
$string['nolog']                     = 'No delivery log entries yet.';

// Privacy / GDPR.
$string['privacy:metadata']                      = 'The Aynura.Surveys plugin stores delivery log data to track survey dispatch to users.';
$string['privacy:metadata:local_aynurasurveys_log'] = 'Delivery log linking Moodle users to survey dispatch events.';
$string['privacy:metadata:local_aynurasurveys_log:userid']     = 'The ID of the user who triggered the survey.';
$string['privacy:metadata:local_aynurasurveys_log:surveyid']   = 'The external Aynura.Surveys survey ID.';
$string['privacy:metadata:local_aynurasurveys_log:trigger']    = 'The trigger type that fired the survey.';
$string['privacy:metadata:local_aynurasurveys_log:status']     = 'Whether the dispatch succeeded or failed.';
$string['privacy:metadata:local_aynurasurveys_log:timecreated'] = 'The time the dispatch was attempted.';

// Errors.
$string['error_nosurveys']           = 'No surveys could be loaded from Aynura.Surveys. Check your connection settings.';
$string['error_invalidrule']         = 'Invalid rule configuration.';
$string['error_dispatch']            = 'Failed to dispatch survey {$a->survey} for user {$a->user}: {$a->reason}';

// Display context.
$string['display_context']             = 'Show survey';
$string['display_context_site']        = 'On site (anywhere in Moodle)';
$string['display_context_course']      = 'On course (only when user is inside the course)';

// Validity period.
$string['validity_period']             = 'Restrict to date range';
$string['validity_period_desc']        = 'Only fire this trigger within the specified date range.';
$string['valid_from']                  = 'Active from';
$string['valid_until']                 = 'Active until';

// Status badges.
$string['status_active']               = 'Active';
$string['status_scheduled']            = 'Scheduled';
$string['status_expired']              = 'Expired';
$string['status_inactive']             = 'Inactive';

// Tabs.
$string['tab_active']                  = 'Active';
$string['tab_archived']                = 'Archived';
$string['rule_archive']                = 'Archive';
$string['rule_reactivate']             = 'Re-activate';
$string['rule_archived']               = 'Rule archived.';
$string['rule_reactivated']            = 'Rule re-activated.';

// Course selector.
$string['scope_all_courses']           = 'All courses';
$string['scope_specific_courses']      = 'Specific courses';
$string['completion_warning']          = 'Completion tracking disabled';
$string['completion_warning_global']   = 'Note: courses without completion tracking enabled will be skipped for this trigger.';

// Conflict panel.
$string['conflict_panel_title']        = 'Site-wide survey conflict check';
$string['conflict_panel_none']         = 'No conflicts — no other site-wide surveys are scheduled for this trigger.';
$string['conflict_panel_found']        = 'A user could receive up to {$a} surveys in sequence for this trigger.';

// Cron.
$string['task_expired']                = '{$a} expired rule(s) auto-disabled.';

// Quiz triggers.
$string['trigger_quiz_passed']              = 'Quiz passed';
$string['trigger_quiz_failed']              = 'Quiz failed';
$string['trigger_days_after_quiz']          = 'X days after quiz completion';

// Delay setting.
$string['delay_enabled']                    = 'Delay before showing survey';
$string['delay_enabled_desc']               = 'Wait a set amount of time before showing the survey to the user.';
$string['delay_value']                      = 'Delay amount';
$string['delay_unit']                       = 'Unit';
$string['delay_unit_minutes']               = 'Minutes';
$string['delay_unit_hours']                 = 'Hours';
$string['delay_unit_days']                  = 'Days';

// Activity completed trigger.
$string['trigger_activity_completed']   = 'Activity completed';
$string['condition_activity']           = 'Activity';
$string['condition_activity_help']      = 'Select the specific activity that must be completed to fire this survey.';
$string['condition_activity_any']       = 'Any activity (global scope)';
$string['condition_activity_loading']   = 'Loading activities...';
$string['condition_activity_select_course'] = 'Select a course first';

// Rule name.
$string['rule_name']      = 'Rule Name';
$string['rule_name_help'] = 'A short name to identify this rule (e.g. "Post-course feedback", "New member onboarding").';
