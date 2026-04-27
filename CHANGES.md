# Changelog — Aynura.Surveys (local_aynurasurveys)

All notable changes to this project are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [2.0.0] — 2026-04-24

### Changed
- Plugin renamed from `local_hubsurveys` (Surveys Hub) to `local_aynurasurveys` (Aynura.Surveys)
- All internal identifiers, DB tables, namespaces, and AMD modules updated
- DB migration step renames existing tables from `local_hubsurveys_*` to `local_aynurasurveys_*`

### Added
- `db/access.php` — proper capability definitions (`local/aynurasurveys:manage`, `local/aynurasurveys:viewlog`)
- `thirdpartylibs.xml` — declares no third-party libraries shipped
- Plugin icon (`pix/icon.svg`)

### Fixed
- All admin pages now use capability checks instead of `is_siteadmin()`
- Unescaped echo variables wrapped with `s()` or cast to int

---

## [1.9.7] — 2026-04-13

### Added
- Multi-language survey support — language picker in modal when survey has 2+ languages
- Translation rendering per question using `translations[langCode]` structure
- Answer preservation across language switches
- Selected language sent in submission payload

### Fixed
- `ajax.php` load action — live API fetch when stored pending record lacks language data
- Removed global `require_sesskey()` from `ajax.php` — now only on write actions

---

## [1.8.1] — 2026-04-13

### Added
- `data_collector.php` — collects standard Moodle user fields, custom profile fields, course metadata, cohort memberships
- `metadata` object sent with every survey submission payload
- Standard user fields: username, city, country, language, timezone, institution, department, phone, address, website, id_number

---

## [1.7.5] — 2026-04-13

### Added
- Rule Name field on trigger rules — displayed in rules list, overview dashboard, and delivery log

### Fixed
- `log.php` — broken WHERE clause when no filters active (PostgreSQL syntax error)
- `index.php` — `ruleid` missing from recent deliveries SQL query

---

## [1.6.6] — 2026-04-13

### Fixed
- `already_dispatched()` now checks pending table first, preventing duplicate survey queuing
- `get_in_or_equal()` used for PostgreSQL-safe IN clause
- Removed session flag approach (caused surveys to stop showing)

---

## [1.6.0] — 2026-04-13

### Fixed
- `lib.php` — removed large payload from `js_call_amd`, now passes only 3 fields (pendingid, ajaxurl, sesskey)
- `observer.php` — `activity_completion_updated` now uses `contextinstanceid` for cmid (not `objectid`)

---

## [1.5.0] — 2026-04-13

### Added
- Activity completed trigger with specific activity picker
- `conflict.php` — `get_activities` action returns activities with completion enabled
- `db/events.php` — registers `course_module_completion_updated` observer

### Fixed
- `conflict.php` — `PARAM_ALPHA` → `PARAM_ALPHANUMEXT` for action param (underscore stripping)
- `conflict.php` — `require_capability` → `is_siteadmin()` for AJAX compatibility
- `conflict.php` — `cm.position` → `cm.id` in ORDER BY (column does not exist)

---

## [1.4.0] — 2026-04-12

### Added
- 19 trigger types across login, enrollment, course, grade, quiz, and schedule categories
- Moodle native modal dialog for survey display
- Frequency protection via pending + log table checks
- Delivery log with filtering
- Overview dashboard
- Admin settings page matching plugin design
- Multi-course scope support
- Validity date ranges and delay settings per rule
