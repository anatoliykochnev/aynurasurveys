<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify.
// It under the terms of the GNU General Public License as published by.
// The Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// But WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// Along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * API diagnostics page for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * API diagnostics page for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * API Diagnostics page for local_aynurasurveys.
 *
 * Tests all Aynura.Surveys API endpoints and displays raw responses.
 * Accessible only to site admins.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_aynurasurveys_diagnostics');

$PAGE->set_url(new moodle_url('/local/aynurasurveys/diagnostics.php'));

$baseurl = rtrim((string) get_config('local_aynurasurveys', 'baseurl'), '/');
$apikey  = (string) get_config('local_aynurasurveys', 'apikey');

echo $OUTPUT->header();

$currentpage = 'diagnostics';
require_once(__DIR__ . '/templates/nav.php');

echo '<div class="hs-card">';
echo '<h4 style="margin:0 0 20px;font-size:16px;font-weight:600;color:#1A1A2E;">API Diagnostics</h4>';

if (empty($baseurl) || empty($apikey)) {
    echo $OUTPUT->notification(
        'Base URL or API Key is not configured. Go to Site Admin → Plugins → Local plugins → Aynura.Surveys.',
        \core\output\notification::NOTIFY_ERROR
    );
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', 'Base URL: ' . html_writer::tag('code', s($baseurl)));
echo html_writer::tag('p', 'API Key: ' . html_writer::tag('code', substr($apikey, 0, 6) . str_repeat('*', max(0, strlen($apikey) - 6))));

// ------------------------------------------------------------------
// Helper: run a test and display results.
// ------------------------------------------------------------------
/**
 * Run a connectivity test and return formatted result HTML
 *
 * @param string \$label Test label.
 * @param bool \$success Whether the test passed.
 * @param string \$message Result message.
 * @return string HTML output.
 */
function run_test(string $label, string $url, string $apikey): void {
    $curl = new curl();
    $curl->setHeader([
        'X-API-Key: ' . $apikey,
        'Accept: application/json',
        'Content-Type: application/json',
    ]);

    $start  = microtime(true);
    $raw    = $curl->get($url);
    $ms     = round((microtime(true) - $start) * 1000);
    $info   = $curl->get_info();
    $status = (int) ($info['http_code'] ?? 0);
    $errno  = $curl->get_errno();

    $statusclass = ($status >= 200 && $status < 300) ? 'success' : 'danger';
    $statusbadge = html_writer::span(
        $status ?: 'cURL error',
        "badge badge-{$statusclass}"
    );

    echo html_writer::start_tag('div', ['class' => 'card mb-3']);
    echo html_writer::start_tag('div', ['class' => 'card-header d-flex justify-content-between align-items-center']);
    echo html_writer::tag('strong', s($label));
    echo html_writer::span("{$statusbadge} &nbsp; {$ms}ms");
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'card-body']);

    // URL.
    echo html_writer::tag('p', 'URL: ' . html_writer::tag('code', s($url)));

    // CURL error.
    if ($errno) {
        echo html_writer::tag('p', html_writer::tag('strong', 'cURL error: ') . s($curl->error), ['class' => 'text-danger']);
    }

    // Raw response.
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $pretty = $raw;
    }

    echo html_writer::tag('pre', s($pretty ?: '(empty response)'), [
        'style' => 'background:#f8f9fa;padding:12px;border-radius:4px;max-height:400px;overflow:auto;font-size:12px;',
    ]);

    // Detect HTML response — wrong Base URL.
    if (str_starts_with(trim($raw), '<')) {
        echo html_writer::tag(
            'p',
            '⛔ Response is HTML, not JSON. Your Base URL points to the web frontend, not the API. ' .
            'Change it to: <code>https://frturwmbnmqdeangsmnp.supabase.co/functions/v1/plugin-api</code>',
            ['class' => 'text-danger font-weight-bold']
        );
    }

    // Parsed structure summary (for /surveys).
    if ($decoded !== null && isset($decoded['surveys'])) {
        $count = count($decoded['surveys']);
        echo html_writer::tag(
            'p',
            html_writer::tag('strong', "✓ Found {$count} survey(s) in response[surveys]"),
            ['class' => 'text-success']
        );
        if ($count > 0) {
            $first = $decoded['surveys'][0];
            echo html_writer::tag('p', 'First survey keys: ' . html_writer::tag('code', implode(', ', array_keys($first))));
        }
    } else if ($decoded !== null && is_array($decoded) && !isset($decoded['surveys'])) {
        echo html_writer::tag(
            'p',
            html_writer::tag('strong', '⚠ Response is an array/object but has no "surveys" key. Top-level keys: ')
            . html_writer::tag('code', implode(', ', array_keys($decoded))),
            ['class' => 'text-warning']
        );
    }

    echo html_writer::end_tag('div'); // card-body
    echo html_writer::end_tag('div'); // card
}

// ------------------------------------------------------------------
// Run tests.
// ------------------------------------------------------------------

echo html_writer::tag('h4', '1. Ping', ['class' => 'mt-4']);
run_test('GET /ping', $baseurl . '/ping', $apikey);

echo html_writer::tag('h4', '2. Surveys — status=all', ['class' => 'mt-4']);
run_test('GET /surveys?status=all', $baseurl . '/surveys?status=all', $apikey);

echo html_writer::tag('h4', '3. Surveys — status=active', ['class' => 'mt-4']);
run_test('GET /surveys?status=active', $baseurl . '/surveys?status=active', $apikey);

echo html_writer::tag('h4', '4. Surveys — no filter', ['class' => 'mt-4']);
run_test('GET /surveys (no params)', $baseurl . '/surveys', $apikey);

// ------------------------------------------------------------------
// Back link.
// ------------------------------------------------------------------
echo '</div>'; // hs-card
echo '</div></div>'; // hs-content + hs-wrap
echo $OUTPUT->footer();
