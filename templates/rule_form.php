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
 * Rule form template for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Rule form template for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Aynura.Surveys
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add/edit rule form template for local_aynurasurveys.
 *
 * @package    local_aynurasurveys
 * @copyright  2026 Sebale <info@sebale.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Rule add/edit form — styled with hs design system.
 *
 * Variables in scope (provided by rules.php):
 *   $formaction, $triggeroptions, $surveyoptions, $courseoptions,
 *   $rule (stdClass|null), $existingcourseids (array),
 *   $existingconditions (array), $conflicturl (string)
 */
defined('MOODLE_INTERNAL') || die();

$selrulename = $rule->rulename ?? '';
$seltrigger  = $rule->trigger ?? '';
$selsurvey   = $rule->surveyid ?? '';
$selscope    = $rule->scope ?? 'global';
$selctx      = $rule->display_context ?? 'site';
$selenabled  = isset($rule->enabled)  ? (int) $rule->enabled : 1;
$selfrom     = !empty($rule->valid_from)  ? date('Y-m-d', $rule->valid_from)  : '';
$seluntil    = !empty($rule->valid_until) ? date('Y-m-d', $rule->valid_until) : '';
$usedates    = (!empty($rule->valid_from) || !empty($rule->valid_until)) ? 1 : 0;

$storeddelay = (int) ($rule->delay_minutes ?? 0);
if ($storeddelay > 0 && $storeddelay % 1440 === 0) {
    $delaydisplay = $storeddelay / 1440; $delayunit = 'days';
} else if ($storeddelay > 0 && $storeddelay % 60 === 0) {
    $delaydisplay = $storeddelay / 60;   $delayunit = 'hours';
} else {
    $delaydisplay = max(1, $storeddelay); $delayunit = 'minutes';
}
$usedelay = $storeddelay > 0 ? 1 : 0;

$islogin         = \local_aynurasurveys\trigger_manager::is_login_trigger($seltrigger);
$needscompletion = \local_aynurasurveys\trigger_manager::requires_completion($seltrigger);

// Condition visibility map
$condtriggersdays = ['login_after_inactivity','days_after_enrollment','days_after_start','days_before_end','days_after_completion','days_after_quiz'];
?>

<style>
/* Form-specific overrides */
.hs-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
.hs-form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-bottom:20px; }
.hs-form-single { margin-bottom:20px; }

.hs-field label {
    display:block;
    font-size:12px;
    font-weight:600;
    color:#6B7280;
    text-transform:uppercase;
    letter-spacing:0.5px;
    margin-bottom:7px;
}
.hs-field label span.req { color:#6C6FF5; }

.hs-field select,
.hs-field input[type="text"],
.hs-field input[type="number"],
.hs-field input[type="date"],
.hs-field input[type="datetime-local"] {
    width:100%;
    padding:10px 14px;
    border:1.5px solid #E5E7EB;
    border-radius:10px;
    font-size:13px;
    color:#1A1A2E;
    background:#fff;
    font-family:'DM Sans',sans-serif;
    transition:border-color 150ms ease, box-shadow 150ms ease;
    appearance:none;
    -webkit-appearance:none;
}
.hs-field select:focus,
.hs-field input:focus {
    outline:none;
    border-color:#6C6FF5;
    box-shadow:0 0 0 3px rgba(108,111,245,0.12);
}

/* Section divider */
.hs-form-section {
    background:#FAFBFF;
    border:1.5px solid #E5E7EB;
    border-radius:12px;
    padding:18px 20px;
    margin-bottom:20px;
}
.hs-form-section-title {
    font-size:11px;
    font-weight:700;
    color:#6C6FF5;
    text-transform:uppercase;
    letter-spacing:0.7px;
    margin-bottom:16px;
    display:flex;
    align-items:center;
    gap:7px;
}
.hs-form-section-title::after {
    content:'';
    flex:1;
    height:1px;
    background:#E5E7EB;
}

/* Toggle switch */
.hs-toggle-row {
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:12px 16px;
    background:#fff;
    border:1.5px solid #E5E7EB;
    border-radius:10px;
    margin-bottom:12px;
    cursor:pointer;
    transition:border-color 150ms;
}
.hs-toggle-row:hover { border-color:#6C6FF5; }
.hs-toggle-label { font-size:13px; font-weight:500; color:#1A1A2E; }
.hs-toggle-sub   { font-size:11px; color:#9CA3AF; margin-top:2px; }

/* Custom toggle switch */
.hs-toggle {
    position:relative;
    width:40px;
    height:22px;
    flex-shrink:0;
}
.hs-toggle input { opacity:0; width:0; height:0; }
.hs-toggle-slider {
    position:absolute;
    inset:0;
    background:#E5E7EB;
    border-radius:999px;
    transition:background 200ms;
    cursor:pointer;
}
.hs-toggle-slider::before {
    content:'';
    position:absolute;
    width:16px; height:16px;
    left:3px; top:3px;
    background:#fff;
    border-radius:50%;
    transition:transform 200ms;
    box-shadow:0 1px 3px rgba(0,0,0,0.2);
}
.hs-toggle input:checked + .hs-toggle-slider { background:linear-gradient(135deg,#6C6FF5,#9B8FF5); }
.hs-toggle input:checked + .hs-toggle-slider::before { transform:translateX(18px); }

/* Radio-style scope buttons */
.hs-scope-btns { display:flex; gap:10px; }
.hs-scope-btn {
    flex:1;
    padding:10px 14px;
    border:1.5px solid #E5E7EB;
    border-radius:10px;
    cursor:pointer;
    transition:all 150ms;
    text-align:center;
}
.hs-scope-btn input { display:none; }
.hs-scope-btn-label { font-size:13px; font-weight:500; color:#6B7280; }
.hs-scope-btn-sub   { font-size:11px; color:#9CA3AF; margin-top:2px; }
.hs-scope-btn.hs-scope-active {
    border-color:#6C6FF5;
    background:#EEF0FF;
}
.hs-scope-btn.hs-scope-active .hs-scope-btn-label { color:#6C6FF5; }

/* Conflict panel */
.hs-conflict-panel {
    background:#FFFBEB;
    border:1.5px solid #FDE68A;
    border-radius:10px;
    padding:14px 16px;
    margin-top:12px;
    font-size:13px;
}

/* Warning alert */
.hs-alert-warning {
    background:#FFFBEB;
    border:1.5px solid #FDE68A;
    border-radius:10px;
    padding:12px 16px;
    font-size:12px;
    color:#92400E;
    margin-top:10px;
}

/* Multi-select courses */
.hs-courses-select {
    width:100%;
    border:1.5px solid #E5E7EB;
    border-radius:10px;
    font-size:13px;
    color:#1A1A2E;
    background:#fff;
    font-family:'DM Sans',sans-serif;
    padding:4px;
}
.hs-courses-select:focus {
    outline:none;
    border-color:#6C6FF5;
    box-shadow:0 0 0 3px rgba(108,111,245,0.12);
}

/* Delay input group */
.hs-input-group { display:flex; gap:10px; }
.hs-input-group .hs-field { flex:1; margin:0; }
.hs-input-group .hs-field:first-child { flex:2; }
</style>

<form method="post" action="<?php echo s($formaction); ?>" id="hs-rule-form" novalidate>
<input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

<!-- ── Section 1: Core ──────────────────────────────────── -->
<div class="hs-form-section">
  <div class="hs-form-section-title">⚡ Trigger &amp; Survey</div>

  <div class="hs-form-single" style="margin-bottom:20px;">
    <div class="hs-field">
      <label for="hs_rulename"><?php echo get_string('rule_name','local_aynurasurveys'); ?> <span class="req">*</span></label>
      <input type="text" name="rulename" id="hs_rulename" required
             placeholder="e.g. Post-course feedback, New member onboarding"
             value="<?php echo s($selrulename); ?>">
    </div>
  </div>

  <div class="hs-form-grid">

    <div class="hs-field">
      <label for="hs_trigger"><?php echo get_string('rule_trigger','local_aynurasurveys'); ?> <span class="req">*</span></label>
      <select name="trigger" id="hs_trigger" required>
        <?php foreach ($triggeroptions as $val => $lbl): ?>
          <option value="<?php echo s($val); ?>" <?php echo ($seltrigger === $val) ? 'selected' : ''; ?>>
            <?php echo s($lbl); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="hs-field">
      <label for="hs_survey"><?php echo get_string('rule_survey','local_aynurasurveys'); ?> <span class="req">*</span></label>
      <select name="surveyid" id="hs_survey" required>
        <?php foreach ($surveyoptions as $val => $lbl): ?>
          <option value="<?php echo s($val); ?>" <?php echo ($selsurvey === $val) ? 'selected' : ''; ?>>
            <?php echo s($lbl); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

  </div>

  <!-- Condition fields -->
  <div id="hs_conditions">

    <div class="hs-field cond-field" id="cond_days"
         style="display:<?php echo in_array($seltrigger, $condtriggersdays) ? 'block' : 'none'; ?>">
      <label><?php echo get_string('condition_days','local_aynurasurveys'); ?></label>
      <input type="number" name="condition_days" min="1"
             value="<?php echo (int)($existingconditions['days'] ?? 1); ?>">
    </div>

    <div class="hs-field cond-field" id="cond_percent"
         style="display:<?php echo ($seltrigger === 'course_percent') ? 'block' : 'none'; ?>">
      <label><?php echo get_string('condition_percent','local_aynurasurveys'); ?></label>
      <input type="number" name="condition_percent" min="1" max="100"
             value="<?php echo (float)($existingconditions['percent'] ?? 50); ?>">
    </div>

    <div class="hs-field cond-field" id="cond_threshold"
         style="display:<?php echo in_array($seltrigger, ['grade_passed','grade_failed','quiz_passed','quiz_failed']) ? 'block' : 'none'; ?>">
      <label><?php echo get_string('condition_grade_threshold','local_aynurasurveys'); ?></label>
      <input type="number" name="condition_threshold" min="0" max="100"
             value="<?php echo (float)($existingconditions['threshold'] ?? 50); ?>">
    </div>

    <div class="hs-field cond-field" id="cond_fixed_date"
         style="display:<?php echo ($seltrigger === 'fixed_date') ? 'block' : 'none'; ?>">
      <label><?php echo get_string('condition_fixed_date','local_aynurasurveys'); ?></label>
      <input type="datetime-local" name="condition_fixed_date_human" id="cond_fixed_date_input"
             value="<?php echo !empty($existingconditions['fixed_date']) ? date('Y-m-d\TH:i', $existingconditions['fixed_date']) : ''; ?>">
      <input type="hidden" name="condition_fixed_date" id="cond_fixed_date_unix"
             value="<?php echo (int)($existingconditions['fixed_date'] ?? 0); ?>">
    </div>

    <div class="hs-field cond-field" id="cond_recurrence"
         style="display:<?php echo ($seltrigger === 'recurring') ? 'block' : 'none'; ?>">
      <label><?php echo get_string('condition_recurrence','local_aynurasurveys'); ?></label>
      <?php
      $recurrenceoptions = [
          'daily' => get_string('condition_recurrence_daily', 'local_aynurasurveys'),
          'weekly' => get_string('condition_recurrence_weekly', 'local_aynurasurveys'),
          'monthly' => get_string('condition_recurrence_monthly', 'local_aynurasurveys'),
      ];
      $selrecurrence = $existingconditions['recurrence'] ?? '';
      ?>
      <select name="condition_recurrence">
        <?php foreach ($recurrenceoptions as $v => $l): ?>
          <option value="<?php echo s($v); ?>" <?php echo ($selrecurrence === $v) ? 'selected' : ''; ?>>
            <?php echo s($l); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>


  </div>
</div>

<!-- ── Section 2: Scope & Display ───────────────────────── -->
<div class="hs-form-section" id="hs_scope_section"
     style="display:<?php echo $islogin ? 'none' : 'block'; ?>">
  <div class="hs-form-section-title">🎯 Scope &amp; Display</div>

  <div class="hs-form-grid">

    <!-- Scope -->
    <div>
      <div style="font-size:12px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">
        <?php echo get_string('rule_scope','local_aynurasurveys'); ?>
      </div>
      <div class="hs-scope-btns">
        <label class="hs-scope-btn <?php echo ($selscope === 'global') ? 'hs-scope-active' : ''; ?>"
               id="scope_btn_global">
          <input type="radio" name="scope" value="global"
                 <?php echo ($selscope === 'global') ? 'checked' : ''; ?>>
          <div class="hs-scope-btn-label">🌐 All Courses</div>
          <div class="hs-scope-btn-sub">Apply globally</div>
        </label>
        <label class="hs-scope-btn <?php echo ($selscope === 'course') ? 'hs-scope-active' : ''; ?>"
               id="scope_btn_course">
          <input type="radio" name="scope" value="course"
                 <?php echo ($selscope === 'course') ? 'checked' : ''; ?>>
          <div class="hs-scope-btn-label">📚 Specific Courses</div>
          <div class="hs-scope-btn-sub">Choose below</div>
        </label>
      </div>
    </div>

    <!-- Display context -->
    <div>
      <div style="font-size:12px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">
        <?php echo get_string('display_context','local_aynurasurveys'); ?>
      </div>
      <div class="hs-scope-btns">
        <label class="hs-scope-btn <?php echo ($selctx === 'site') ? 'hs-scope-active' : ''; ?>"
               id="ctx_btn_site">
          <input type="radio" name="display_context" value="site"
                 <?php echo ($selctx === 'site') ? 'checked' : ''; ?>>
          <div class="hs-scope-btn-label">🖥 On Site</div>
          <div class="hs-scope-btn-sub">Anywhere in Moodle</div>
        </label>
        <label class="hs-scope-btn <?php echo ($selctx === 'course') ? 'hs-scope-active' : ''; ?>"
               id="ctx_btn_course">
          <input type="radio" name="display_context" value="course"
                 <?php echo ($selctx === 'course') ? 'checked' : ''; ?>>
          <div class="hs-scope-btn-label">📖 On Course</div>
          <div class="hs-scope-btn-sub">Inside course only</div>
        </label>
      </div>
    </div>

  </div>

  <!-- Course multi-select -->
  <div id="hs_courses_row" style="display:<?php echo ($selscope === 'course') ? 'block' : 'none'; ?>; margin-top:16px;">
    <div style="font-size:12px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
      <?php echo get_string('rule_courses','local_aynurasurveys'); ?>
    </div>
    <select name="courseids[]" id="hs_courses" class="hs-courses-select" multiple size="6">
      <?php foreach ($courseoptions as $cid => $cname): ?>
        <option value="<?php echo (int)$cid; ?>"
          <?php echo in_array($cid, $existingcourseids) ? 'selected' : ''; ?>>
          <?php echo s($cname); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <div style="font-size:11px;color:#9CA3AF;margin-top:6px;">
      <?php echo get_string('rule_courses_help','local_aynurasurveys'); ?>
    </div>
    <div id="hs_completion_warnings"></div>
  </div>

  <!-- Activity selector — shown when trigger=activity_completed AND scope=course -->
  <?php
  $selcmid = (int)($existingconditions['cmid'] ?? 0);
  // Pre-populate activities for edit mode.
  $preloadedactivities = [];
  if ($seltrigger === 'activity_completed' && !empty($existingcourseids)) {
      foreach ($existingcourseids as $precid) {
          $presql = "SELECT cm.id AS cmid, m.name AS modname, cm.instance, c.fullname AS cname
                       FROM {course_modules} cm
                       JOIN {modules} m ON m.id = cm.module
                       JOIN {course} c  ON c.id = cm.course
                      WHERE cm.course = :cid AND cm.completion > 0 AND cm.deletioninprogress = 0
                      ORDER BY cm.section, cm.id";
          $precms = $DB->get_records_sql($presql, ['cid' => $precid]);
          foreach ($precms as $pcm) {
              $pname = $DB->get_field($pcm->modname, 'name', ['id' => $pcm->instance]);
              if ($pname) {
                  $preloadedactivities[] = ['cmid' => (int)$pcm->cmid, 'name' => $pname, 'type' => $pcm->modname, 'coursename' => $pcm->cname];
              }
          }
      }
  }
  ?>
  <div id="hs_activity_section"
       style="display:<?php echo ($seltrigger === 'activity_completed' && $selscope === 'course') ? 'block' : 'none'; ?>;
              margin-top:16px;">
    <div style="font-size:12px;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
      <?php echo get_string('condition_activity','local_aynurasurveys'); ?>
    </div>
    <select name="condition_cmid" id="hs_activity_select" class="hs-courses-select" size="6">
      <?php if (empty($preloadedactivities)): ?>
        <option value="0"><?php echo get_string('condition_activity_select_course','local_aynurasurveys'); ?></option>
      <?php else: ?>
        <option value="0">— <?php echo get_string('choosedots'); ?></option>
        <?php
        $curcourse = '';
        foreach ($preloadedactivities as $pa):
            if ($pa['coursename'] !== $curcourse):
                if ($curcourse !== '') echo '</optgroup>';
                echo '<optgroup label="' . s($pa['coursename']) . '">';
                $curcourse = $pa['coursename'];
            endif;
        ?>
          <option value="<?php echo $pa['cmid']; ?>"
            <?php echo ($pa['cmid'] === $selcmid) ? 'selected' : ''; ?>>
            [<?php echo s($pa['type']); ?>] <?php echo s($pa['name']); ?>
          </option>
        <?php endforeach; ?>
        <?php if ($curcourse) echo '</optgroup>'; ?>
      <?php endif; ?>
    </select>
    <div style="font-size:11px;color:#9CA3AF;margin-top:6px;">
      <?php echo get_string('condition_activity_help','local_aynurasurveys'); ?>
    </div>
    <input type="hidden" name="condition_cmid_val" id="hs_cmid_hidden"
           value="<?php echo (int)$selcmid; ?>">
    <!-- Debug panel -->
    <div id="hs_activity_debug" style="margin-top:8px;font-size:11px;font-family:monospace;
         background:#f8f9fa;border:1px solid #e5e7eb;border-radius:6px;padding:8px;display:none;"></div>
    <button type="button" onclick="debugActivities()" style="margin-top:6px;padding:4px 10px;
            font-size:11px;cursor:pointer;border:1px solid #d1d5db;border-radius:6px;background:#fff;">
      🔍 Debug: Load Activities
    </button>
  </div>

  <!-- Global completion warning -->
  <div id="hs_global_completion_warning" class="hs-alert-warning"
       style="display:<?php echo ($selscope === 'global' && $needscompletion) ? 'block' : 'none'; ?>">
    ⚠ <?php echo get_string('completion_warning_global','local_aynurasurveys'); ?>
  </div>

  <!-- Conflict panel -->
  <div id="hs_conflict_panel"
       style="display:<?php echo ($selscope === 'global' && $selctx === 'site') ? 'block' : 'none'; ?>">
    <div class="hs-conflict-panel">
      <div style="font-size:12px;font-weight:600;color:#92400E;margin-bottom:8px;">
        ⚠ <?php echo get_string('conflict_panel_title','local_aynurasurveys'); ?>
      </div>
      <div id="hs_conflict_body" style="font-size:12px;color:#6B7280;">
        <?php echo get_string('loading','moodle'); ?>...
      </div>
    </div>
  </div>

</div><!-- /#hs_scope_section -->

<!-- ── Section 3: Schedule ──────────────────────────────── -->
<div class="hs-form-section">
  <div class="hs-form-section-title">📅 Schedule</div>

  <!-- Validity period toggle -->
  <label class="hs-toggle-row" for="hs_use_dates" style="cursor:pointer;">
    <div>
      <div class="hs-toggle-label"><?php echo get_string('validity_period','local_aynurasurveys'); ?></div>
      <div class="hs-toggle-sub">Restrict this rule to a date range</div>
    </div>
    <div class="hs-toggle">
      <input type="checkbox" id="hs_use_dates" name="use_dates" value="1"
             <?php echo $usedates ? 'checked' : ''; ?>>
      <span class="hs-toggle-slider"></span>
    </div>
  </label>

  <div id="hs_dates_row" style="display:<?php echo $usedates ? 'grid' : 'none'; ?>;
       grid-template-columns:1fr 1fr;gap:16px;margin-bottom:12px;">
    <div class="hs-field" style="margin:0;">
      <label for="hs_valid_from"><?php echo get_string('valid_from','local_aynurasurveys'); ?></label>
      <input type="date" name="valid_from" id="hs_valid_from"
             value="<?php echo s($selfrom); ?>">
    </div>
    <div class="hs-field" style="margin:0;">
      <label for="hs_valid_until"><?php echo get_string('valid_until','local_aynurasurveys'); ?></label>
      <input type="date" name="valid_until" id="hs_valid_until"
             value="<?php echo s($seluntil); ?>">
    </div>
  </div>

  <!-- Delay toggle -->
  <label class="hs-toggle-row" for="hs_use_delay" style="cursor:pointer;">
    <div>
      <div class="hs-toggle-label"><?php echo get_string('delay_enabled','local_aynurasurveys'); ?></div>
      <div class="hs-toggle-sub">Wait before showing the survey after trigger fires</div>
    </div>
    <div class="hs-toggle">
      <input type="checkbox" id="hs_use_delay" name="use_delay" value="1"
             <?php echo $usedelay ? 'checked' : ''; ?>>
      <span class="hs-toggle-slider"></span>
    </div>
  </label>

  <div id="hs_delay_row" style="display:<?php echo $usedelay ? 'block' : 'none'; ?>">
    <div class="hs-input-group">
      <div class="hs-field">
        <label><?php echo get_string('delay_value','local_aynurasurveys'); ?></label>
        <input type="number" name="delay_value" id="hs_delay_value"
               min="1" value="<?php echo $delaydisplay; ?>">
      </div>
      <div class="hs-field">
        <label><?php echo get_string('delay_unit','local_aynurasurveys'); ?></label>
        <select name="delay_unit" id="hs_delay_unit">
          <option value="minutes" <?php echo ($delayunit === 'minutes') ? 'selected' : ''; ?>>
            <?php echo get_string('delay_unit_minutes','local_aynurasurveys'); ?>
          </option>
          <option value="hours" <?php echo ($delayunit === 'hours') ? 'selected' : ''; ?>>
            <?php echo get_string('delay_unit_hours','local_aynurasurveys'); ?>
          </option>
          <option value="days" <?php echo ($delayunit === 'days') ? 'selected' : ''; ?>>
            <?php echo get_string('delay_unit_days','local_aynurasurveys'); ?>
          </option>
        </select>
      </div>
    </div>
  </div>

</div>

<!-- ── Section 4: Status ─────────────────────────────────── -->
<div class="hs-form-section">
  <div class="hs-form-section-title">⚙ Status</div>
  <label class="hs-toggle-row" for="hs_enabled" style="cursor:pointer;">
    <div>
      <div class="hs-toggle-label"><?php echo get_string('rule_enabled','local_aynurasurveys'); ?></div>
      <div class="hs-toggle-sub">Rule will fire when trigger conditions are met</div>
    </div>
    <div class="hs-toggle">
      <input type="checkbox" id="hs_enabled" name="enabled" value="1"
             <?php echo $selenabled ? 'checked' : ''; ?>>
      <span class="hs-toggle-slider"></span>
    </div>
  </label>
</div>

<!-- Hidden fields -->
<input type="hidden" name="delay_minutes" id="hs_delay_minutes_hidden" value="<?php echo $storeddelay; ?>">

<!-- Actions -->
<div style="display:flex;gap:12px;align-items:center;margin-top:8px;">
  <button type="submit" class="hs-btn hs-btn-primary">
    <?php echo get_string('savechanges'); ?>
  </button>
  <a href="<?php echo new moodle_url('/local/aynurasurveys/rules.php'); ?>"
     class="hs-btn hs-btn-secondary">
    <?php echo get_string('cancel'); ?>
  </a>
</div>

</form>

<script>
(function() {
    var CONFLICT_URL   = <?php echo json_encode($conflicturl); ?>;
    var EXCLUDE_ID     = <?php echo (int)($rule->id ?? 0); ?>;
    var NEEDS_COMP     = <?php echo json_encode(\local_aynurasurveys\trigger_manager::COMPLETION_TRIGGERS); ?>;
    var LOGIN_TRIGGERS = <?php echo json_encode(\local_aynurasurveys\trigger_manager::LOGIN_TRIGGERS); ?>;
    var COND_MAP = {
        'login_after_inactivity': 'cond_days',
        'days_after_enrollment':  'cond_days',
        'days_after_start':       'cond_days',
        'days_before_end':        'cond_days',
        'days_after_completion':  'cond_days',
        'days_after_quiz':        'cond_days',
        // activity_completed uses hs_activity_section in Section 2 — no Section 1 cond field
        'course_percent':         'cond_percent',
        'grade_passed':           'cond_threshold',
        'grade_failed':           'cond_threshold',
        'quiz_passed':            'cond_threshold',
        'quiz_failed':            'cond_threshold',
        'fixed_date':             'cond_fixed_date',
        'recurring':              'cond_recurrence',
    };
    var ALL_CONDS = ['cond_days','cond_percent','cond_threshold','cond_fixed_date','cond_recurrence'];
    // Note: cond_activity is in Section 2 (hs_activity_section), handled separately.

    function g(id) { return document.getElementById(id); }

    function updateConditions(t) {
        ALL_CONDS.forEach(function(id) {
            var el = g(id);
            if (el) el.style.display = (COND_MAP[t] === id) ? 'block' : 'none';
        });
    }

    function updateScopeSection(trigger) {
        var sec = g('hs_scope_section');
        if (sec) sec.style.display = (LOGIN_TRIGGERS.indexOf(trigger) !== -1) ? 'none' : 'block';
    }

    function updateScopeButtons() {
        var scope = document.querySelector('input[name="scope"]:checked');
        var val   = scope ? scope.value : 'global';
        g('scope_btn_global').className = 'hs-scope-btn' + (val === 'global' ? ' hs-scope-active' : '');
        g('scope_btn_course').className = 'hs-scope-btn' + (val === 'course' ? ' hs-scope-active' : '');
        var row = g('hs_courses_row');
        if (row) row.style.display = (val === 'course') ? 'block' : 'none';
        updateConflictPanel();
        updateGlobalCompWarning();
        checkCompletionWarnings();
        loadActivities();
    }

    function updateCtxButtons() {
        var ctx = document.querySelector('input[name="display_context"]:checked');
        var val = ctx ? ctx.value : 'site';
        g('ctx_btn_site').className   = 'hs-scope-btn' + (val === 'site'   ? ' hs-scope-active' : '');
        g('ctx_btn_course').className = 'hs-scope-btn' + (val === 'course' ? ' hs-scope-active' : '');
        updateConflictPanel();
    }

    function updateConflictPanel() {
        var scope = document.querySelector('input[name="scope"]:checked');
        var ctx   = document.querySelector('input[name="display_context"]:checked');
        var panel = g('hs_conflict_panel');
        if (!panel) return;
        var show  = scope && scope.value === 'global' && ctx && ctx.value === 'site';
        panel.style.display = show ? 'block' : 'none';
        if (show) loadConflicts();
    }

    function loadConflicts() {
        var trigger = g('hs_trigger') ? g('hs_trigger').value : '';
        if (!trigger) return;
        var body = g('hs_conflict_body');
        if (body) body.innerHTML = 'Checking for conflicts...';
        fetch(CONFLICT_URL + '?action=conflict_check&trigger=' + encodeURIComponent(trigger) + '&exclude_rule_id=' + EXCLUDE_ID)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!body) return;
                if (data.count === 0) {
                    body.innerHTML = '<span style="color:#16a34a;">✓ No conflicts — no other site-wide surveys for this trigger.</span>';
                } else {
                    var rows = data.conflicts.map(function(c) {
                        return '<tr><td style="padding:4px 8px;">' + esc(c.survey) + '</td><td style="padding:4px 8px;color:#9CA3AF;">' + esc(c.valid) + '</td></tr>';
                    }).join('');
                    body.innerHTML = '<div style="color:#92400E;margin-bottom:8px;">A user could receive up to <strong>' + (data.count + 1) + '</strong> surveys for this trigger.</div>'
                        + '<table style="font-size:12px;width:100%;"><thead><tr><th style="padding:4px 8px;text-align:left;color:#9CA3AF;">Survey</th><th style="padding:4px 8px;text-align:left;color:#9CA3AF;">Valid period</th></tr></thead><tbody>' + rows + '</tbody></table>';
                }
            })
            .catch(function() {
                if (body) body.innerHTML = '<span style="color:#dc2626;">Could not load conflict data.</span>';
            });
    }

    function updateGlobalCompWarning() {
        var t     = g('hs_trigger') ? g('hs_trigger').value : '';
        var scope = document.querySelector('input[name="scope"]:checked');
        var warn  = g('hs_global_completion_warning');
        if (!warn) return;
        warn.style.display = (scope && scope.value === 'global' && NEEDS_COMP.indexOf(t) !== -1) ? 'block' : 'none';
    }

    function checkCompletionWarnings() {
        var t     = g('hs_trigger') ? g('hs_trigger').value : '';
        var scope = document.querySelector('input[name="scope"]:checked');
        var warnEl = g('hs_completion_warnings');
        if (!warnEl) return;
        if (!scope || scope.value !== 'course' || NEEDS_COMP.indexOf(t) === -1) {
            warnEl.innerHTML = ''; return;
        }
        var coursesSelectEl = g('hs_courses');
        var selected = coursesSelectEl ? Array.from(coursesSelectEl.options).filter(function(o){ return o.selected; }).map(function(o){ return o.value; }) : [];
        if (!selected.length) { warnEl.innerHTML = ''; return; }
        fetch(CONFLICT_URL + '?action=completion_check&courseids=' + selected.join(','))
            .then(function(r){ return r.json(); })
            .then(function(data){
                if (!data.warnings || !data.warnings.length) { warnEl.innerHTML = ''; return; }
                var html = '<div class="hs-alert-warning" style="margin-top:8px;"><strong>⚠ Completion tracking disabled:</strong><ul style="margin:6px 0 0 16px;padding:0;">';
                data.warnings.forEach(function(w){ html += '<li>' + esc(w.coursename) + '</li>'; });
                html += '</ul></div>';
                warnEl.innerHTML = html;
            });
    }

    // Load activities from server when courses change.
    function loadActivities() {
        var trigger  = g('hs_trigger') ? g('hs_trigger').value : '';
        var scope    = document.querySelector('input[name="scope"]:checked');
        var section  = g('hs_activity_section');
        var actSel   = g('hs_activity_select');

        // Show/hide the activity section.
        var showSection = trigger === 'activity_completed' && scope && scope.value === 'course';
        if (section) section.style.display = showSection ? 'block' : 'none';

        if (trigger !== 'activity_completed') return;
        if (!actSel) return;

        if (!scope || scope.value !== 'course') {
            return;
        }

        var coursesEl = g('hs_courses');
        var selected = coursesEl ? Array.from(coursesEl.selectedOptions || coursesEl.options).filter(function(o){ return o.selected; }).map(function(o){ return o.value; }) : [];
        if (!selected.length) {
            actSel.innerHTML = '<option value="0">' + esc('Select a course first') + '</option>';
            return;
        }

        actSel.innerHTML = '<option value="0">Loading activities...</option>';
        actSel.disabled  = true;

        fetch(CONFLICT_URL + '?action=get_activities&courseids=' + selected.join(','))
            .then(function(r){ return r.json(); })
            .then(function(data){
                actSel.disabled = false;
                if (!data.activities || !data.activities.length) {
                    var dbg = (data.debug && data.debug.length) ? ' (' + data.debug.join('; ') + ')' : '';
                    actSel.innerHTML = '<option value="0">No activities with completion enabled found' + dbg + '</option>';
                    return;
                }
                var html = '<option value="0">— Select activity —</option>';
                var curCourse = '';
                data.activities.forEach(function(a) {
                    if (a.coursename !== curCourse) {
                        if (curCourse) html += '</optgroup>';
                        html += '<optgroup label="' + esc(a.coursename) + '">';
                        curCourse = a.coursename;
                    }
                    var sel = (parseInt(g('hs_cmid_hidden') ? g('hs_cmid_hidden').value : 0) === a.cmid) ? ' selected' : '';
                    html += '<option value="' + a.cmid + '"' + sel + '>[' + esc(a.type) + '] ' + esc(a.name) + '</option>';
                });
                if (curCourse) html += '</optgroup>';
                actSel.innerHTML = html;
            })
            .catch(function(){
                actSel.disabled = false;
                actSel.innerHTML = '<option value="0">Could not load activities</option>';
            });
    }

    // Sync hidden cmid field on activity select change.
    var actSel = g('hs_activity_select');
    if (actSel) {
        actSel.addEventListener('change', function() {
            var hidden = g('hs_cmid_hidden');
            if (hidden) hidden.value = this.value;
        });
    }

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }

    // Toggle date row
    var useDates = g('hs_use_dates');
    if (useDates) {
        useDates.addEventListener('change', function() {
            g('hs_dates_row').style.display = this.checked ? 'grid' : 'none';
        });
    }

    // Toggle delay row
    var useDelay = g('hs_use_delay');
    if (useDelay) {
        useDelay.addEventListener('change', function() {
            g('hs_delay_row').style.display = this.checked ? 'block' : 'none';
        });
    }

    // Convert datetime-local + delay to hidden fields on submit
    var form = g('hs-rule-form');
    if (form) {
        form.addEventListener('submit', function() {
            var fi = g('cond_fixed_date_input'), fu = g('cond_fixed_date_unix');
            if (fi && fu && fi.value) fu.value = Math.floor(new Date(fi.value).getTime() / 1000);
            // Sync cmid hidden field from select.
            var asel = g('hs_activity_select'), cmidh = g('hs_cmid_hidden');
            if (asel && cmidh) cmidh.value = asel.value;

            var useDelayEl = g('hs_use_delay'), dv = g('hs_delay_value'), du = g('hs_delay_unit'), hd = g('hs_delay_minutes_hidden');
            if (hd) {
                if (useDelayEl && useDelayEl.checked && dv && du) {
                    var mult = du.value === 'days' ? 1440 : (du.value === 'hours' ? 60 : 1);
                    hd.value = (parseInt(dv.value, 10) || 0) * mult;
                } else {
                    hd.value = 0;
                }
            }
        });
    }

    // Scope radio buttons
    document.querySelectorAll('input[name="scope"]').forEach(function(el) {
        el.addEventListener('change', updateScopeButtons);
    });
    document.querySelectorAll('input[name="display_context"]').forEach(function(el) {
        el.addEventListener('change', updateCtxButtons);
    });

    // Trigger select
    var triggerEl = g('hs_trigger');
    if (triggerEl) {
        triggerEl.addEventListener('change', function() {
            updateConditions(this.value);
            updateScopeSection(this.value);
            updateConflictPanel();
            updateGlobalCompWarning();
            checkCompletionWarnings();
            loadActivities();
        });
    }

    // Courses multi-select
    var coursesEl = g('hs_courses');
    if (coursesEl) {
        coursesEl.addEventListener('change', function() {
            checkCompletionWarnings();
            loadActivities();
        });
        // mouseup catches selection on click without needing to unfocus
        coursesEl.addEventListener('mouseup', function() {
            setTimeout(function() {
                checkCompletionWarnings();
                loadActivities();
            }, 50);
        });
    }

    // Init
    if (triggerEl) {
        updateConditions(triggerEl.value);
        updateScopeSection(triggerEl.value);
    }
    updateScopeButtons();
    updateCtxButtons();
    updateConflictPanel();
    updateGlobalCompWarning();
    checkCompletionWarnings();
    loadActivities();
    window.debugActivities = function() {
        var dbgEl = document.getElementById('hs_activity_debug');
        if (dbgEl) dbgEl.style.display = 'block';

        var coursesEl = document.getElementById('hs_courses');
        var trigger   = document.getElementById('hs_trigger');
        var scope     = document.querySelector('input[name="scope"]:checked');

        var log = [];
        log.push('Trigger: ' + (trigger ? trigger.value : 'NOT FOUND'));
        log.push('Scope: ' + (scope ? scope.value : 'NOT FOUND'));

        if (coursesEl) {
            var allOpts = Array.from(coursesEl.options);
            var selOpts = allOpts.filter(function(o){ return o.selected; });
            log.push('Total course options: ' + allOpts.length);
            log.push('Selected courses: ' + selOpts.map(function(o){ return o.value + '=' + o.text; }).join(', '));

            if (selOpts.length > 0) {
                var ids = selOpts.map(function(o){ return o.value; }).join(',');
                var conflictUrl = document.getElementById('hs_activity_select') ? CONFLICT_URL : 'CONFLICT_URL NOT SET';
                log.push('Fetching: ' + CONFLICT_URL + '?action=get_activities&courseids=' + ids);
                if (dbgEl) dbgEl.innerHTML = log.join('<br>') + '<br>Fetching...';

                fetch(CONFLICT_URL + '?action=get_activities&courseids=' + ids)
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        log.push('Response: ' + JSON.stringify(data));
                        if (dbgEl) dbgEl.innerHTML = log.join('<br>');
                    })
                    .catch(function(e){
                        log.push('Fetch ERROR: ' + e.message);
                        if (dbgEl) dbgEl.innerHTML = log.join('<br>');
                    });
            } else {
                if (dbgEl) dbgEl.innerHTML = log.join('<br>');
            }
        } else {
            log.push('ERROR: #hs_courses element NOT FOUND in DOM');
            if (dbgEl) dbgEl.innerHTML = log.join('<br>');
        }
    };
})();
</script>
