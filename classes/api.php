<?php
// This file is part of Moodle - http://moodle.org/.
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
// Along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Aynura.Surveys API client.
 *
 * Handles all outbound HTTP communication from Moodle to the Aynura.Surveys API.
 * Uses Moodle's built-in curl wrapper for compatibility and proxy support.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aynurasurveys;


global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * HTTP client for the Aynura.Surveys API.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var string Base URL of the Aynura.Surveys instance (no trailing slash). */
    private string $baseurl;

    /** @var string Organisation API key. */
    private string $apikey;

    /**
     * Constructor — reads config from Moodle settings.
     *
     * @throws \moodle_exception if plugin is not configured.
     */
    public function __construct() {
        $this->baseurl = rtrim((string) get_config('local_aynurasurveys', 'baseurl'), '/');
        $this->apikey  = (string) get_config('local_aynurasurveys', 'apikey');

        if (empty($this->baseurl) || empty($this->apikey)) {
            throw new \moodle_exception('connection_notconfigured', 'local_aynurasurveys');
        }
    }

    // -----------------------------------------------------------------------
    // Public API methods.
    // -----------------------------------------------------------------------

    /**
     * Test connectivity via GET /ping.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function ping(): array {
        try {
            $url = $this->baseurl . '/ping';
            $curl = new \curl();
            $curl->setHeader([
                'X-API-Key: ' . $this->apikey,
                'Accept: application/json',
                'Content-Type: application/json',
            ]);
            $raw  = $curl->get($url);
            $info = $curl->get_info();
            $code = (int) ($info['http_code'] ?? 0);

            // Reject HTML responses — means Base URL points to the frontend, not the API.
            if (str_starts_with(trim($raw), '<')) {
                return [
                    'success' => false,
                    'message' => get_string('connection_fail', 'local_aynurasurveys',
                        'Base URL returned an HTML page. Please set the Base URL to the API endpoint, not the web app URL.'),
                ];
            }

            if ($code < 200 || $code >= 300) {
                return [
                    'success' => false,
                    'message' => get_string('connection_fail', 'local_aynurasurveys', "HTTP {$code}"),
                ];
            }

            return ['success' => true, 'message' => get_string('connection_success', 'local_aynurasurveys')];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('connection_fail', 'local_aynurasurveys', $e->getMessage()),
            ];
        }
    }

    /**
     * Retrieve list of surveys from GET /surveys.
     *
     * @param  array $params  Optional query params, e.g. ['status' => 'active', 'page' => 1].
     * @return array          Array of survey objects decoded from JSON.
     */
    public function get_surveys(array $params = []): array {
        $response = $this->get('/surveys', $params);
        return $response['surveys'] ?? [];
    }

    /**
     * Retrieve a single survey with all questions/options/translations.
     *
     * @param  string $surveyid  External Aynura.Surveys survey ID.
     * @return array             Survey object.
     */
    public function get_survey(string $surveyid): array {
        return $this->get("/surveys/{$surveyid}");
    }

    /**
     * Lightweight status check for a survey — GET /surveys/:id/status.
     *
     * @param  string $surveyid
     * @return array  Status object (e.g. ['status' => 'active']).
     */
    public function get_survey_status(string $surveyid): array {
        return $this->get("/surveys/{$surveyid}/status");
    }

    /**
     * Submit a survey response on behalf of a Moodle user.
     *
     * POST /surveys/:id/responses
     *
     * @param  string $surveyid   External survey ID.
     * @param  array  $payload    Response payload — see buildPayload().
     * @return array              API response body.
     * @throws \moodle_exception  On HTTP error or non-2xx response.
     */
    public function submit_response(string $surveyid, array $payload): array {
        return $this->post("/surveys/{$surveyid}/responses", $payload);
    }

    /**
     * Build the standard webhook payload sent on every trigger.
     *
     * @param  string      $trigger    Trigger key (e.g. 'course_completed').
     * @param  \stdClass   $user       Moodle user record (must have id, email, firstname, lastname).
     * @param  array       $context    Contextual data — keys: courseid, coursename, etc.
     * @return array                   Associative payload array (will be JSON-encoded by post()).
     */
    public static function build_payload(string $trigger, \stdClass $user, array $context = []): array {
        $courseid = $context['courseid'] ?? null;

        return [
            // Required fields matching POST /surveys/:id/responses spec.
            'respondent_name' => trim($user->firstname . ' ' . $user->lastname),
            'respondent_email' => $user->email,
            'moodle_user_id' => (string) $user->id,
            'moodle_course_id' => $courseid ? (string) $courseid : null,
            'language' => $user->lang ?? 'en',
            'source' => 'moodle',
            // Empty answers array — trigger registration only.
            // User fills in answers via the survey link sent by Aynura.Surveys.
            'answers' => [],
            // Extra context passed as metadata for Aynura.Surveys analytics.
            'metadata' => [
                'trigger' => $trigger,
                'timestamp' => date('c'),
                'coursename' => $context['coursename'] ?? null,
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Private HTTP helpers.
    // -----------------------------------------------------------------------

    /**
     * Make an authenticated GET request.
     *
     * @param  string $path    API path (e.g. '/surveys').
     * @param  array  $params  Query string parameters.
     * @return array           Decoded JSON response.
     */
    private function get(string $path, array $params = []): array {
        $url = $this->baseurl . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $curl = new \curl();
        $curl->setHeader([
            'X-API-Key: ' . $this->apikey,
            'Accept: application/json',
            'Content-Type: application/json',
        ]);

        $raw = $curl->get($url);
        return $this->handle_response($curl, $raw, 'GET', $path);
    }

    /**
     * Make an authenticated POST request with a JSON body.
     *
     * @param  string $path     API path.
     * @param  array  $payload  Data to JSON-encode and send.
     * @return array            Decoded JSON response.
     */
    private function post(string $path, array $payload): array {
        $url = $this->baseurl . $path;

        $curl = new \curl();
        $curl->setHeader([
            'X-API-Key: ' . $this->apikey,
            'Accept: application/json',
            'Content-Type: application/json',
        ]);

        $raw = $curl->post($url, json_encode($payload));
        return $this->handle_response($curl, $raw, 'POST', $path);
    }

    /**
     * Parse and validate an HTTP response.
     *
     * @param  \curl   $curl    The curl instance (to read info/errno).
     * @param  string  $raw     Raw response body.
     * @param  string  $method  HTTP verb (for error messages).
     * @param  string  $path    API path (for error messages).
     * @return array            Decoded JSON response body.
     * @throws \moodle_exception on curl error or non-2xx HTTP status.
     */
    private function handle_response(\curl $curl, string $raw, string $method, string $path): array {
        // Check for curl-level errors.
        if ($curl->get_errno()) {
            throw new \moodle_exception(
                'connection_fail',
                'local_aynurasurveys',
                '',
                $curl->error
            );
        }

        $info       = $curl->get_info();
        $statuscode = (int) ($info['http_code'] ?? 0);

        if ($statuscode < 200 || $statuscode >= 300) {
            $decoded = json_decode($raw, true);
            $message = $decoded['message'] ?? $decoded['error'] ?? $raw;
            throw new \moodle_exception(
                'connection_fail',
                'local_aynurasurveys',
                '',
                "HTTP {$statuscode} on {$method} {$path}: {$message}"
            );
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // /ping may return plain text — treat as success with empty array.
            return [];
        }

        return $decoded;
    }
}
