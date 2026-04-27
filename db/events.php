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
 * Event observer registrations for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Event observer registrations for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Event observer registrations for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [

    [
        'eventname' => '\core\event\user_loggedin',
        'callback' => '\local_aynurasurveys\observer::user_loggedin',
        'internal' => true,
        'priority' => 0,
    ],

    [
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => '\local_aynurasurveys\observer::user_unenrolled',
        'internal' => true,
        'priority' => 0,
    ],

    [
        'eventname' => '\core\event\course_module_viewed',
        'callback' => '\local_aynurasurveys\observer::course_module_viewed',
        'internal' => true,
        'priority' => 0,
    ],

    [
        'eventname' => '\core\event\course_completed',
        'callback' => '\local_aynurasurveys\observer::course_completed',
        'internal' => true,
        'priority' => 0,
    ],

    [
        'eventname' => '\core\event\user_graded',
        'callback' => '\local_aynurasurveys\observer::user_graded',
        'internal' => true,
        'priority' => 0,
    ],

    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => '\local_aynurasurveys\observer::activity_completion_updated',
        'internal' => true,
        'priority' => 0,
    ],

    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_aynurasurveys\observer::quiz_attempt_submitted',
        'internal' => true,
        'priority' => 0,
    ],

];
