<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX handler for local_aynurasurveys modal actions.
 *
 * Actions:
 *   submit  — collects answers, posts to Aynura.Surveys, marks pending as completed
 *   dismiss — marks pending as dismissed (user confirmed close)
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

use local_aynurasurveys\data_collector;
require_once($CFG->libdir . '/filelib.php');

require_login();

global $DB, $USER, $CFG;

header('Content-Type: application/json');

$action    = required_param('action',    PARAM_ALPHANUMEXT);
$pendingid = required_param('pendingid', PARAM_INT);

// load action is read-only — no sesskey needed.
// ------------------------------------------------------------------
// Action: load — returns full modal data for the pending survey.
// Called by JS on init to avoid large js_call_amd payloads.
// ------------------------------------------------------------------
if ($action === 'load') {
    $pending = $DB->get_record('local_aynurasurveys_pending', [
        'id'     => $pendingid,
        'userid' => $USER->id,
        'status' => 'pending',
    ], '*', IGNORE_MISSING);

    if (!$pending) {
        echo json_encode(['success' => false, 'error' => 'No pending survey found.']);
        exit;
    }

    // Questions are stored with context wrapper.
    $stored = json_decode($pending->questions, true) ?? [];
    // Support both old flat format and new wrapped format.
    $questions           = isset($stored['questions'])           ? $stored['questions']           : $stored;
    $survey_obj          = $stored['survey']             ?? [];
    $survey_translations = $stored['survey_translations'] ?? [];

    // If survey_obj is missing (old pending record), fetch live from API.
    if (empty($survey_obj) || empty($survey_obj['languages_enabled'])) {
        try {
            $apiclient  = new \local_aynurasurveys\api();
            $fresh      = $apiclient->get_survey($pending->surveyid);
            $survey_obj          = $fresh['survey']        ?? $survey_obj;
            $survey_translations = $fresh['translations']  ?? $survey_translations;
            // Also update questions if missing translations.
            if (!empty($fresh['questions'])) {
                $questions = $fresh['questions'];
            }
        } catch (\Exception $e) {
            // Fall through with whatever we have.
        }
    }

    // Language resolution:
    // 1. User's Moodle language if it's in languages_enabled
    // 2. Otherwise survey's language_default
    $lang_default  = $survey_obj['language_default'] ?? 'en';
    $langs_enabled = $survey_obj['languages_enabled'] ?? [$lang_default];
    $user_lang     = $pending->language ?? 'en';
    $lang          = in_array($user_lang, $langs_enabled) ? $user_lang : $lang_default;

    // Build translated survey title for each language.
    $titles = [$lang_default => $pending->surveyname];
    foreach ($survey_translations as $t) {
        $lc = $t['language_code'] ?? null;
        if ($lc && !empty($t['title'])) {
            $titles[$lc] = $t['title'];
        }
    }

    echo json_encode([
        'success'          => true,
        'pendingid'        => (int) $pending->id,
        'surveyid'         => $pending->surveyid,
        'surveyname'       => $pending->surveyname,
        'titles'           => $titles,
        'questions'        => $questions,   // Raw with translations intact — JS handles rendering
        'lang'             => $lang,
        'lang_default'     => $lang_default,
        'langs_enabled'    => array_values($langs_enabled),
        'is_repeating'     => \local_aynurasurveys\trigger_manager::is_repeating($pending->trigger),
        'strings'          => [
            'close_confirm_title'          => 'Close survey?',
            'close_confirm_msg_onetime'    => "If you close this survey you won't be able to complete it again.",
            'close_confirm_msg_repeating'  => "You can complete this survey next time. Close for now?",
            'close_confirm_yes'            => 'Yes, close',
            'close_confirm_no'             => 'Go back',
            'submit'                       => get_string('savechanges'),
            'required'                     => 'This field is required.',
            'submitting'                   => 'Submitting...',
            'thankyou'                     => 'Thank you for completing the survey!',
            'error_submit'                 => 'There was a problem submitting your response. Please try again.',
            'select_language'              => 'Select language',
        ],
    ]);
    exit;
}

// Load and validate the pending record belongs to current user.
$pending = $DB->get_record('local_aynurasurveys_pending', [
    'id'     => $pendingid,
    'userid' => $USER->id,
    'status' => 'pending',
], '*', IGNORE_MISSING);

if (!$pending) {
    echo json_encode(['success' => false, 'error' => 'Invalid or already resolved pending record.']);
    exit;
}

// ------------------------------------------------------------------
// Action: dismiss
// ------------------------------------------------------------------
if ($action === 'dismiss') {
    // For repeating triggers (every_login, recurring, login_after_inactivity),
    // delete the pending record so the trigger can re-queue next time.
    // For one-time triggers, mark dismissed to permanently block re-queuing.
    if (\local_aynurasurveys\trigger_manager::is_repeating($pending->trigger)) {
        $DB->delete_records('local_aynurasurveys_pending', ['id' => $pending->id]);
    } else {
        $DB->update_record('local_aynurasurveys_pending', (object) [
            'id'           => $pending->id,
            'status'       => 'dismissed',
            'timeresolved' => time(),
        ]);
    }


    // Write to delivery log as dismissed.
    $rule = $DB->get_record('local_aynurasurveys_rules', ['id' => $pending->ruleid]);
    if ($rule) {
        $DB->insert_record('local_aynurasurveys_log', (object) [
            'ruleid'      => $pending->ruleid,
            'userid'      => $USER->id,
            'surveyid'    => $pending->surveyid,
            'trigger'     => $pending->trigger,
            'courseid'    => $pending->courseid,
            'status'      => 'dismissed',
            'statuscode'  => null,
            'response'    => 'User dismissed the modal.',
            'timecreated' => time(),
        ]);
    }

    echo json_encode(['success' => true]);
    exit;
}

// ------------------------------------------------------------------
// Action: submit
// ------------------------------------------------------------------
if ($action === 'submit') {
    require_sesskey();
    $answers_raw   = required_param('answers',  PARAM_RAW);
    $selected_lang = optional_param('language', $pending->language ?? 'en', PARAM_ALPHANUMEXT);
    $answers     = json_decode($answers_raw, true);

    if (!is_array($answers)) {
        echo json_encode(['success' => false, 'error' => 'Invalid answers format.']);
        exit;
    }

    // Collect enrichment metadata.
    $user_full = $DB->get_record('user', ['id' => $USER->id], '*');
    $meta_context = [
        'trigger'       => $pending->trigger,
        'activity_name' => null, // populated below if available
    ];

    // Try to get activity name from pending context if stored.
    $pending_questions = json_decode($pending->questions ?? '[]', true);
    if (!empty($pending_questions[0]['activity_name'])) {
        $meta_context['activity_name'] = $pending_questions[0]['activity_name'];
    }

    $metadata = data_collector::collect(
        $user_full ?: $USER,
        $pending->courseid ? (int) $pending->courseid : null,
        $meta_context
    );

    // Build payload matching POST /surveys/:id/responses spec.
    $payload = [
        'respondent_name'  => fullname($USER),
        'respondent_email' => $USER->email,
        'moodle_user_id'   => (string) $USER->id,
        'moodle_course_id' => $pending->courseid ? (string) $pending->courseid : null,
        'language'         => $selected_lang,
        'source'           => 'moodle',
        'answers'          => $answers,
        'metadata'         => $metadata,
    ];

    // Post to Aynura.Surveys.
    $baseurl = rtrim((string) get_config('local_aynurasurveys', 'baseurl'), '/');
    $apikey  = (string) get_config('local_aynurasurveys', 'apikey');

    $curl = new curl();
    $curl->setHeader([
        'X-API-Key: ' . $apikey,
        'Accept: application/json',
        'Content-Type: application/json',
    ]);

    $raw        = $curl->post($baseurl . '/surveys/' . $pending->surveyid . '/responses', json_encode($payload));
    $info       = $curl->get_info();
    $statuscode = (int) ($info['http_code'] ?? 0);
    $decoded    = json_decode($raw, true);

    if ($statuscode >= 200 && $statuscode < 300 && !empty($decoded['success'])) {
        // For repeating triggers, delete the pending record after completion
        // so the trigger can re-queue on the next event.
        // For one-time triggers, mark completed to permanently block re-queuing.
        if (\local_aynurasurveys\trigger_manager::is_repeating($pending->trigger)) {
            $DB->delete_records('local_aynurasurveys_pending', ['id' => $pending->id]);
        } else {
            $DB->update_record('local_aynurasurveys_pending', (object) [
                'id'           => $pending->id,
                'status'       => 'completed',
                'timeresolved' => time(),
            ]);
        }


        // Write success log.
        $DB->insert_record('local_aynurasurveys_log', (object) [
            'ruleid'      => $pending->ruleid,
            'userid'      => $USER->id,
            'surveyid'    => $pending->surveyid,
            'trigger'     => $pending->trigger,
            'courseid'    => $pending->courseid,
            'status'      => 'success',
            'statuscode'  => $statuscode,
            'response'    => $raw,
            'timecreated' => time(),
        ]);

        echo json_encode(['success' => true]);
    } else {
        $errormsg = $decoded['message'] ?? $decoded['error'] ?? $raw;

        // Write failed log.
        $DB->insert_record('local_aynurasurveys_log', (object) [
            'ruleid'      => $pending->ruleid,
            'userid'      => $USER->id,
            'surveyid'    => $pending->surveyid,
            'trigger'     => $pending->trigger,
            'courseid'    => $pending->courseid,
            'status'      => 'failed',
            'statuscode'  => $statuscode,
            'response'    => $raw,
            'timecreated' => time(),
        ]);

        echo json_encode(['success' => false, 'error' => $errormsg]);
    }

    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action.']);
