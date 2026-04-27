# Aynura.Surveys — Moodle Plugin

**Package:** `local_aynurasurveys`  
**Type:** Local plugin  
**Moodle:** 4.5+  
**License:** GNU GPL v3+

---

## Overview

Aynura.Surveys connects Moodle to the [Aynura.Surveys](https://surveys.sebale.net) survey intelligence platform. It triggers surveys automatically based on learner activity and displays them as native Moodle modal dialogs — no iframes, no redirects.

---

## Features

- **19 trigger types** across login, enrollment, course, grade, quiz, activity, and schedule categories
- **Activity completed trigger** with specific activity picker (completion-tracking aware)
- **Multi-language surveys** — language picker rendered in modal when survey has 2+ languages
- **Rich metadata collection** — custom user profile fields, course data, cohort memberships, trigger context
- **Frequency protection** — prevents duplicate survey delivery per user per rule
- **Delivery log** — full audit trail of survey dispatches, completions, and dismissals
- **Native Moodle modal** — surveys render inside Moodle UI with no external redirects
- **Validity periods and delays** — restrict rules to date ranges, add delays before showing

---

## Requirements

- Moodle 4.5 or later (requires 2024100700)
- PHP 8.1+
- An active [Aynura.Surveys](https://surveys.sebale.net) account with API key

---

## Installation

1. Download the plugin zip
2. Upload via **Site Administration → Plugins → Install plugins**
3. Follow the upgrade screen
4. Go to **Site Administration → Plugins → Aynura.Surveys → Settings**
5. Enter your API Base URL and API Key
6. Click **Test Connection** to verify

---

## Configuration

All settings are under **Site Administration → Local plugins → Aynura.Surveys → Settings**:

| Setting | Description |
|---|---|
| Enable Plugin | Master on/off switch |
| Base URL | Your Aynura.Surveys API endpoint |
| API Key | Your API authentication key |

---

## Creating Trigger Rules

1. Go to **Aynura.Surveys → Trigger Rules**
2. Click **Add Rule**
3. Set a Rule Name, choose a Trigger Event, select a Survey
4. Configure Scope (all courses or specific courses), Display context, Schedule, and Status
5. Save

---

## Metadata Collected

On survey submission, the plugin enriches the response with:

- **Profile** — all standard and custom Moodle user profile fields
- **Course** — fullname, shortname, category, dates, custom course fields
- **Cohorts** — all cohort names the user belongs to
- **Moodle context** — trigger type, activity name, site URL, submission time

---

## Privacy

This plugin stores user data in:

- `local_aynurasurveys_pending` — survey queue (userid, surveyid, answers pending)
- `local_aynurasurveys_log` — delivery log (userid, surveyid, status)

It implements the Moodle Privacy API. User data can be exported and deleted via **Site Administration → Privacy and policies → Data requests**.

---

## Support

- GitHub: [sebale/moodle-local_aynurasurveys](https://github.com/sebale/moodle-local_aynurasurveys)
- Email: info@sebale.net

---

## License

GNU General Public License v3 or later — see [LICENSE](LICENSE) for details.
