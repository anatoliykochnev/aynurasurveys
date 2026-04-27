// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Survey Hub modal AMD module.
 *
 * Features:
 * - Fetches full survey data via AJAX on init (minimal boot payload)
 * - Language picker when survey has multiple languages
 * - Renders questions in selected language using translations data
 * - Handles submit, dismiss-with-confirmation
 *
 * @module     local_aynurasurveys/modal
 * @copyright  2026 Surveys Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {

    var cfg  = {};   // Full data from AJAX load
    var boot = {};   // Minimal boot data from PHP
    var currentLang = 'en';

    return {
        init: function(data) {
            boot = data;
            $(document).ready(function() {
                loadAndRender();
            });
        }
    };

    // ------------------------------------------------------------------
    // Load full data then render
    // ------------------------------------------------------------------
    function loadAndRender() {
        $.post(boot.ajaxurl, {
            action:    'load',
            pendingid: boot.pendingid,
            sesskey:   boot.sesskey,
        })
        .done(function(res) {
            if (!res || !res.success) return;
            cfg         = res;
            currentLang = res.lang || res.lang_default || 'en';
            renderModal();
        })
        .fail(function() {
            // Silent fail — will show on next page load.
        });
    }

    // ------------------------------------------------------------------
    // Modal render
    // ------------------------------------------------------------------
    function renderModal() {
        var hasMultiLang = cfg.langs_enabled && cfg.langs_enabled.length > 1;
        var title        = getTitle(currentLang);

        var langPickerHtml = hasMultiLang ? buildLangPicker() : '';

        var html =
            '<div id="hs-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;' +
            'background:rgba(0,0,0,0.55);z-index:9998;display:flex;align-items:center;justify-content:center;">' +

            '<div id="hs-modal" style="background:#fff;border-radius:12px;width:90%;max-width:640px;' +
            'max-height:88vh;display:flex;flex-direction:column;' +
            'box-shadow:0 8px 32px rgba(0,0,0,0.22);overflow:hidden;">' +

            // Header
            '<div style="padding:18px 22px 14px;border-bottom:1px solid #E5E7EB;background:#FAFBFF;">' +
            '<div style="display:flex;justify-content:space-between;align-items:flex-start;">' +
            '<h4 id="hs-survey-title" style="margin:0;font-size:16px;font-weight:700;' +
            'color:#1A1A2E;font-family:\'DM Sans\',sans-serif;flex:1;">' +
            esc(title) + '</h4>' +
            '<button id="hs-close" style="background:none;border:none;cursor:pointer;' +
            'font-size:22px;color:#9CA3AF;line-height:1;padding:0 0 0 12px;flex-shrink:0;">&times;</button>' +
            '</div>' +
            langPickerHtml +
            '</div>' +

            // Body
            '<div id="hs-body" style="padding:22px;overflow-y:auto;flex:1;">' +
            '<form id="hs-form" novalidate>' +
            buildQuestions(currentLang) +
            '</form>' +
            '</div>' +

            // Footer
            '<div id="hs-footer" style="padding:14px 22px;border-top:1px solid #E5E7EB;' +
            'display:flex;justify-content:flex-end;align-items:center;gap:10px;background:#FAFBFF;">' +
            '<button id="hs-submit" style="background:linear-gradient(135deg,#6C6FF5,#9B8FF5);' +
            'color:#fff;border:none;border-radius:999px;padding:9px 24px;font-size:14px;' +
            'font-weight:600;cursor:pointer;font-family:\'DM Sans\',sans-serif;">' +
            esc(cfg.strings.submit) + '</button>' +
            '</div>' +

            '</div></div>' +

            // Confirm dismiss overlay
            '<div id="hs-confirm" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;' +
            'background:rgba(0,0,0,0.65);z-index:9999;align-items:center;justify-content:center;">' +
            '<div style="background:#fff;border-radius:12px;width:90%;max-width:420px;padding:28px;' +
            'box-shadow:0 8px 32px rgba(0,0,0,0.25);font-family:\'DM Sans\',sans-serif;">' +
            '<h5 style="margin:0 0 10px;font-size:16px;font-weight:700;color:#1A1A2E;">' +
            esc(cfg.strings.close_confirm_title) + '</h5>' +
            '<p style="color:#6B7280;margin:0 0 20px;font-size:13px;line-height:1.5;">' +
            esc(cfg.is_repeating ? cfg.strings.close_confirm_msg_repeating : cfg.strings.close_confirm_msg_onetime) + '</p>' +
            '<div style="display:flex;gap:10px;justify-content:flex-end;">' +
            '<button id="hs-confirm-no" style="background:#F3F4F6;color:#374151;border:none;' +
            'border-radius:999px;padding:8px 18px;cursor:pointer;font-size:13px;">' +
            esc(cfg.strings.close_confirm_no) + '</button>' +
            '<button id="hs-confirm-yes" style="background:#dc2626;color:#fff;border:none;' +
            'border-radius:999px;padding:8px 18px;cursor:pointer;font-size:13px;font-weight:600;">' +
            esc(cfg.strings.close_confirm_yes) + '</button>' +
            '</div></div></div>';

        $('body').append(html);
        bindEvents();
    }

    // ------------------------------------------------------------------
    // Language picker
    // ------------------------------------------------------------------
    function buildLangPicker() {
        var html = '<div id="hs-lang-picker" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;">';
        cfg.langs_enabled.forEach(function(lang) {
            var active = lang === currentLang;
            html += '<button type="button" data-lang="' + esc(lang) + '" class="hs-lang-btn" style="' +
                'padding:4px 12px;border-radius:999px;border:1.5px solid ' +
                (active ? '#6C6FF5' : '#E5E7EB') + ';background:' +
                (active ? '#EEF0FF' : '#fff') + ';color:' +
                (active ? '#6C6FF5' : '#6B7280') + ';font-size:11px;font-weight:' +
                (active ? '700' : '500') + ';cursor:pointer;' +
                'font-family:\'DM Sans\',sans-serif;text-transform:uppercase;letter-spacing:0.5px;">' +
                esc(lang.toUpperCase()) + '</button>';
        });
        html += '</div>';
        return html;
    }

    function switchLanguage(lang) {
        if (lang === currentLang) return;
        currentLang = lang;

        // Update title.
        var title = getTitle(lang);
        $('#hs-survey-title').text(title);

        // Update lang picker button styles.
        $('.hs-lang-btn').each(function() {
            var active = $(this).data('lang') === lang;
            $(this).css({
                'border-color': active ? '#6C6FF5' : '#E5E7EB',
                'background':   active ? '#EEF0FF' : '#fff',
                'color':        active ? '#6C6FF5' : '#6B7280',
                'font-weight':  active ? '700' : '500',
            });
        });

        // Re-render questions in new language — preserve existing answers.
        var answers = collectAnswers();
        $('#hs-form').html(buildQuestions(lang));
        restoreAnswers(answers);
    }

    function getTitle(lang) {
        return (cfg.titles && cfg.titles[lang]) ? cfg.titles[lang] : cfg.surveyname;
    }

    // ------------------------------------------------------------------
    // Question rendering
    // ------------------------------------------------------------------
    function buildQuestions(lang) {
        if (!cfg.questions || !cfg.questions.length) return '';
        return cfg.questions.map(function(q) {
            return buildQuestion(q, lang);
        }).join('');
    }

    function getTranslated(q, lang, field) {
        // Returns translated field value, falling back to default.
        if (lang && q.translations && q.translations[lang] && q.translations[lang][field]) {
            return q.translations[lang][field];
        }
        return q[field] || '';
    }

    function getTranslatedOptions(q, lang) {
        // Returns options array with translated labels, values unchanged.
        var defaultOpts = q.options || [];
        if (!lang || !q.translations || !q.translations[lang] || !q.translations[lang].options) {
            return defaultOpts;
        }
        var transOpts = q.translations[lang].options;
        // Build a map of value -> translated label.
        var transMap = {};
        transOpts.forEach(function(o) { transMap[o.value] = o.label; });
        return defaultOpts.map(function(o) {
            return { value: o.value, label: transMap[o.value] || o.label };
        });
    }

    function buildQuestion(q, lang) {
        var label    = getTranslated(q, lang, 'label');
        var required = q.required;
        var reqMark  = required ? ' <span style="color:#dc2626;">*</span>' : '';
        var reqAttr  = required ? 'data-required="1"' : '';
        var opts     = getTranslatedOptions(q, lang);

        var labelHtml = '<label style="display:block;font-weight:600;font-size:13px;' +
            'margin-bottom:8px;color:#1A1A2E;font-family:\'DM Sans\',sans-serif;">' +
            esc(label) + reqMark + '</label>';

        var input = '';
        switch (q.type) {
            case 'radio':
                input = opts.map(function(o) {
                    return '<label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;' +
                        'cursor:pointer;font-size:13px;color:#374151;font-family:\'DM Sans\',sans-serif;">' +
                        '<input type="radio" name="q_' + q.id + '" value="' + esc(o.value) + '" ' + reqAttr + '>' +
                        esc(o.label) + '</label>';
                }).join('');
                break;

            case 'checkbox':
                input = opts.map(function(o) {
                    return '<label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;' +
                        'cursor:pointer;font-size:13px;color:#374151;font-family:\'DM Sans\',sans-serif;">' +
                        '<input type="checkbox" name="q_' + q.id + '[]" value="' + esc(o.value) +
                        '" data-qid="' + q.id + '">' +
                        esc(o.label) + '</label>';
                }).join('');
                break;

            case 'dropdown':
                var optHtml = '<option value="">— ' + esc(cfg.strings.select_language || 'Select') + ' —</option>';
                opts.forEach(function(o) {
                    optHtml += '<option value="' + esc(o.value) + '">' + esc(o.label) + '</option>';
                });
                input = '<select name="q_' + q.id + '" ' + reqAttr + ' style="width:100%;padding:9px 12px;' +
                    'border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;color:#1A1A2E;' +
                    'background:#fff;font-family:\'DM Sans\',sans-serif;">' + optHtml + '</select>';
                break;

            case 'nps':
            case 'scale':
                var min = q.scale_min !== undefined ? q.scale_min : 0;
                var max = q.scale_max !== undefined ? q.scale_max : 10;
                input = '<div style="display:flex;gap:5px;flex-wrap:wrap;">';
                for (var i = min; i <= max; i++) {
                    input += '<label style="cursor:pointer;">' +
                        '<input type="radio" name="q_' + q.id + '" value="' + i + '" ' + reqAttr +
                        ' style="display:none;" class="hs-scale-radio">' +
                        '<span class="hs-scale-btn" data-qid="' + q.id + '" data-val="' + i + '" style="' +
                        'display:inline-flex;align-items:center;justify-content:center;' +
                        'width:38px;height:38px;border:1.5px solid #E5E7EB;border-radius:8px;' +
                        'font-size:13px;font-weight:500;color:#374151;cursor:pointer;' +
                        'font-family:\'DM Sans\',sans-serif;transition:all 120ms;">' + i + '</span></label>';
                }
                input += '</div>';
                break;

            case 'star':
                input = '<div style="display:flex;gap:4px;">';
                for (var s = 1; s <= 5; s++) {
                    input += '<label class="hs-star" data-qid="' + q.id + '" data-val="' + s + '" ' +
                        'style="cursor:pointer;font-size:32px;color:#D1D5DB;line-height:1;">' +
                        '<input type="radio" name="q_' + q.id + '" value="' + s + '" ' + reqAttr +
                        ' style="display:none;">&#9733;</label>';
                }
                input += '</div>';
                break;

            case 'textarea':
                input = '<textarea name="q_' + q.id + '" ' + reqAttr + ' rows="4" style="width:100%;' +
                    'padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;' +
                    'color:#1A1A2E;resize:vertical;box-sizing:border-box;' +
                    'font-family:\'DM Sans\',sans-serif;"></textarea>';
                break;

            default: // text
                input = '<input type="text" name="q_' + q.id + '" ' + reqAttr + ' style="width:100%;' +
                    'padding:9px 12px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:13px;' +
                    'color:#1A1A2E;box-sizing:border-box;font-family:\'DM Sans\',sans-serif;">';
                break;
        }

        return '<div class="hs-q" data-qid="' + q.id + '" data-type="' + q.type + '" ' +
            'style="margin-bottom:22px;">' +
            labelHtml + input +
            '<div class="hs-err" style="display:none;color:#dc2626;font-size:11px;margin-top:4px;">' +
            esc(cfg.strings.required) + '</div></div>';
    }

    // ------------------------------------------------------------------
    // Answer preserve/restore on language switch
    // ------------------------------------------------------------------
    function collectAnswers() {
        var answers = [];
        if (!cfg.questions) return answers;
        cfg.questions.forEach(function(q) {
            var ans = {question_id: q.id, type: q.type};
            switch (q.type) {
                case 'radio': case 'dropdown':
                    ans.val = $('[name="q_' + q.id + '"]').val() || null; break;
                case 'checkbox':
                    ans.val = $('input[data-qid="' + q.id + '"]:checked').map(function(){ return $(this).val(); }).get(); break;
                case 'nps': case 'scale': case 'star':
                    var v = $('input[name="q_' + q.id + '"]:checked').val();
                    ans.val = v !== undefined ? parseInt(v, 10) : null; break;
                default:
                    ans.val = $('[name="q_' + q.id + '"]').val() || null; break;
            }
            answers.push(ans);
        });
        return answers;
    }

    function restoreAnswers(answers) {
        answers.forEach(function(ans) {
            if (ans.val === null || ans.val === undefined) return;
            switch (ans.type) {
                case 'radio': case 'dropdown':
                    $('[name="q_' + ans.question_id + '"][value="' + ans.val + '"]').prop('checked', true);
                    $('select[name="q_' + ans.question_id + '"]').val(ans.val);
                    break;
                case 'checkbox':
                    if (Array.isArray(ans.val)) {
                        ans.val.forEach(function(v) {
                            $('input[data-qid="' + ans.question_id + '"][value="' + v + '"]').prop('checked', true);
                        });
                    }
                    break;
                case 'nps': case 'scale':
                    $('input[name="q_' + ans.question_id + '"][value="' + ans.val + '"]').prop('checked', true);
                    $('.hs-scale-btn[data-qid="' + ans.question_id + '"][data-val="' + ans.val + '"]')
                        .css({background: '#6C6FF5', color: '#fff', borderColor: '#6C6FF5'});
                    break;
                case 'star':
                    $('input[name="q_' + ans.question_id + '"][value="' + ans.val + '"]').prop('checked', true);
                    $('.hs-star[data-qid="' + ans.question_id + '"]').each(function() {
                        $(this).css('color', $(this).data('val') <= ans.val ? '#F59E0B' : '#D1D5DB');
                    });
                    break;
            }
        });
    }

    // ------------------------------------------------------------------
    // Event binding
    // ------------------------------------------------------------------
    function bindEvents() {
        // Language picker.
        $(document).on('click', '.hs-lang-btn', function() {
            switchLanguage($(this).data('lang'));
        });

        // Scale buttons.
        $(document).on('click', '.hs-scale-btn', function() {
            var qid = $(this).data('qid'), val = $(this).data('val');
            $('.hs-scale-btn[data-qid="' + qid + '"]').css({background:'', color:'#374151', borderColor:'#E5E7EB'});
            $(this).css({background:'#6C6FF5', color:'#fff', borderColor:'#6C6FF5'});
            $('input[name="q_' + qid + '"][value="' + val + '"]').prop('checked', true);
        });

        // Star rating.
        $(document).on('click', '.hs-star', function() {
            var qid = $(this).data('qid'), val = $(this).data('val');
            $('.hs-star[data-qid="' + qid + '"]').each(function() {
                $(this).css('color', $(this).data('val') <= val ? '#F59E0B' : '#D1D5DB');
            });
            $('input[name="q_' + qid + '"][value="' + val + '"]').prop('checked', true);
        });

        // Close button.
        $('#hs-close').on('click', function() {
            $('#hs-confirm').css('display', 'flex');
        });
        $('#hs-confirm-no').on('click', function() {
            $('#hs-confirm').hide();
        });
        $('#hs-confirm-yes').on('click', function() {
            dismissSurvey();
        });

        // Submit.
        $('#hs-submit').on('click', function(e) {
            e.preventDefault();
            if (validateForm()) submitSurvey();
        });
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------
    function validateForm() {
        var valid = true;
        $('.hs-q').each(function() {
            var qid  = $(this).data('qid');
            var type = $(this).data('type');
            var req  = $(this).find('[data-required="1"]').length > 0;
            if (!req) return;
            var answered = false;
            if (type === 'checkbox') {
                answered = $('input[data-qid="' + qid + '"]:checked').length > 0;
            } else if (type === 'radio' || type === 'nps' || type === 'scale' || type === 'star') {
                answered = $('input[name="q_' + qid + '"]:checked').length > 0;
            } else {
                var v = $('[name="q_' + qid + '"]').val();
                answered = v && v.trim().length > 0;
            }
            if (!answered) {
                $(this).find('.hs-err').show();
                valid = false;
            } else {
                $(this).find('.hs-err').hide();
            }
        });
        if (!valid) $('#hs-body').animate({scrollTop: 0}, 300);
        return valid;
    }

    // ------------------------------------------------------------------
    // Final answer collection for submission
    // ------------------------------------------------------------------
    function collectFinalAnswers() {
        var answers = [];
        if (!cfg.questions) return answers;
        cfg.questions.forEach(function(q) {
            var ans = {question_id: q.id};
            switch (q.type) {
                case 'radio': case 'dropdown':
                    ans.answer_text = $('[name="q_' + q.id + '"]').val() || null; break;
                case 'checkbox':
                    ans.answer_options = $('input[data-qid="' + q.id + '"]:checked')
                        .map(function(){ return $(this).val(); }).get(); break;
                case 'nps': case 'scale': case 'star':
                    var v = $('input[name="q_' + q.id + '"]:checked').val();
                    ans.answer_value = v !== undefined ? parseInt(v, 10) : null; break;
                default:
                    var t = $('[name="q_' + q.id + '"]').val();
                    ans.answer_text = t ? t.trim() : null; break;
            }
            answers.push(ans);
        });
        return answers;
    }

    // ------------------------------------------------------------------
    // Submit
    // ------------------------------------------------------------------
    function submitSurvey() {
        var $btn = $('#hs-submit');
        $btn.prop('disabled', true).text(cfg.strings.submitting);

        $.post(boot.ajaxurl, {
            action:    'submit',
            pendingid: cfg.pendingid,
            answers:   JSON.stringify(collectFinalAnswers()),
            language:  currentLang,
            sesskey:   boot.sesskey,
        })
        .done(function(res) {
            if (res.success) { showThankYou(); }
            else {
                $btn.prop('disabled', false).text(cfg.strings.submit);
                showError(res.error || cfg.strings.error_submit);
            }
        })
        .fail(function() {
            $btn.prop('disabled', false).text(cfg.strings.submit);
            showError(cfg.strings.error_submit);
        });
    }

    // ------------------------------------------------------------------
    // Dismiss
    // ------------------------------------------------------------------
    function dismissSurvey() {
        $.post(boot.ajaxurl, {
            action: 'dismiss', pendingid: cfg.pendingid, sesskey: boot.sesskey
        }).always(function() { removeModal(); });
    }

    // ------------------------------------------------------------------
    // UI helpers
    // ------------------------------------------------------------------
    function showThankYou() {
        $('#hs-body').html(
            '<div style="text-align:center;padding:40px 20px;">' +
            '<div style="font-size:52px;margin-bottom:14px;color:#6C6FF5;">✓</div>' +
            '<p style="font-size:16px;color:#1A1A2E;font-weight:600;margin:0;' +
            'font-family:\'DM Sans\',sans-serif;">' +
            esc(cfg.strings.thankyou) + '</p></div>'
        );
        $('#hs-footer').hide();
        setTimeout(removeModal, 2500);
    }

    function showError(msg) {
        var $e = $('#hs-submit-err');
        if ($e.length) { $e.text(msg); }
        else {
            $('#hs-footer').prepend('<span id="hs-submit-err" style="color:#dc2626;font-size:12px;' +
                'margin-right:auto;">' + esc(msg) + '</span>');
        }
    }

    function removeModal() {
        $('#hs-overlay, #hs-confirm').remove();
    }

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }
});
