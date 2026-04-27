<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Scheduled task definitions for local_aynurasurveys.
 *
 * Handles all time-based and percentage-based triggers that cannot be
 * captured by a single Moodle event:
 *
 *  - days_after_enrollment
 *  - course_percent
 *  - fixed_date
 *  - recurring (daily/weekly/monthly)
 *  - days_after_start
 *  - days_before_end
 *  - days_after_completion
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname'   => '\local_aynurasurveys\task\process_time_triggers',
        'blocking'    => 0,
        // Runs hourly by default. Admins can adjust in Site Admin > Server > Scheduled tasks.
        'minute'      => '0',
        'hour'        => '*',
        'day'         => '*',
        'month'       => '*',
        'dayofweek'   => '*',
        'disabled'    => 0,
    ],
];
