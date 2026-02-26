/**
 * ═══════════════════════════════════════════════════════════════
 *  validation.js  —  Sama Roadlines TMS
 *  Central validation library for all forms across the project.
 *  Include this file in every page that has a form.
 *
 *  Usage:
 *    <script src="/Sama_Roadlines/assets/js/validation.js"></script>
 *
 *  How to use on a form:
 *    SRV.validate('#myForm', rules, { onSuccess: fn });
 * ═══════════════════════════════════════════════════════════════
 */

const SRV = (function () {

    'use strict';

    /* ═══════════════════════════════
       UTILITY HELPERS
    ═══════════════════════════════ */

    const utils = {
        /** Trim and return value of a field */
        val(el) {
            if (!el) return '';
            return (el.value || '').trim();
        },

        /** Show error on a field */
        showError(el, message) {
            if (!el) return;
            el.classList.add('is-invalid');
            el.classList.remove('is-valid');

            let fb = el.parentElement.querySelector('.srv-feedback');
            if (!fb) {
                fb = document.createElement('div');
                fb.className = 'srv-feedback invalid-feedback';
                el.parentElement.appendChild(fb);
            }
            fb.textContent = message;
            fb.style.display = 'block';
        },

        /** Show success on a field */
        showSuccess(el) {
            if (!el) return;
            el.classList.remove('is-invalid');
            el.classList.add('is-valid');

            const fb = el.parentElement.querySelector('.srv-feedback');
            if (fb) fb.style.display = 'none';
        },

        /** Clear validation state from a field */
        clear(el) {
            if (!el) return;
            el.classList.remove('is-invalid', 'is-valid');
            const fb = el.parentElement.querySelector('.srv-feedback');
            if (fb) fb.style.display = 'none';
        },

        /** Clear all validation from a form */
        clearForm(form) {
            form.querySelectorAll('input, select, textarea').forEach(el => utils.clear(el));
        },

        /** Get field by id or selector */
        field(idOrEl) {
            if (!idOrEl) return null;
            if (typeof idOrEl === 'string') return document.getElementById(idOrEl) || document.querySelector(idOrEl);
            return idOrEl;
        }
    };


    /* ═══════════════════════════════
       RULE VALIDATORS
    ═══════════════════════════════ */

    const validators = {

        /** Field must not be empty */
        required(value) {
            return value !== null && value !== undefined && String(value).trim() !== '';
        },

        /** Minimum string length */
        minLength(value, len) {
            return String(value).trim().length >= parseInt(len);
        },

        /** Maximum string length */
        maxLength(value, len) {
            return String(value).trim().length <= parseInt(len);
        },

        /** Must be a valid number */
        numeric(value) {
            return !isNaN(parseFloat(value)) && isFinite(value);
        },

        /** Must be a positive number (> 0) */
        positive(value) {
            return parseFloat(value) > 0;
        },

        /** Must be >= 0 */
        nonNegative(value) {
            return parseFloat(value) >= 0;
        },

        /** Valid Indian mobile number */
        mobile(value) {
            return /^[6-9]\d{9}$/.test(String(value).trim());
        },

        /** Valid email address */
        email(value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value).trim());
        },

        /** Valid vehicle number (Indian format) e.g. GJ-05-AB-1234 */
        vehicleNumber(value) {
            return /^[A-Z]{2}[\s-]?\d{2}[\s-]?[A-Z]{1,3}[\s-]?\d{4}$/i.test(String(value).trim());
        },

        /** Valid date string */
        date(value) {
            if (!value) return false;
            const d = new Date(value);
            return d instanceof Date && !isNaN(d);
        },

        /** Date must not be in the future */
        notFutureDate(value) {
            if (!value) return false;
            return new Date(value) <= new Date();
        },

        /** Date must not be in the past */
        notPastDate(value) {
            if (!value) return false;
            const d = new Date(value);
            const today = new Date();
            today.setHours(0,0,0,0);
            return d >= today;
        },

        /** Valid GSTIN (India) */
        gstin(value) {
            if (!value) return true; // Optional field
            return /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i.test(String(value).trim());
        },

        /** Valid PAN number (India) */
        pan(value) {
            if (!value) return true; // Optional
            return /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i.test(String(value).trim());
        },

        /** Regex match */
        regex(value, pattern) {
            return new RegExp(pattern).test(String(value).trim());
        },

        /** Must match another field */
        matches(value, otherFieldId) {
            const other = document.getElementById(otherFieldId);
            return other ? value === other.value : false;
        },

        /** Amount must be <= another field's value */
        maxAmount(value, otherFieldId) {
            const other = document.getElementById(otherFieldId);
            if (!other) return true;
            return parseFloat(value) <= parseFloat(other.value || 0);
        },

        /** Select must not be empty / default */
        selectRequired(value) {
            return value !== '' && value !== null && value !== undefined && value !== '0';
        },
    };


    /* ═══════════════════════════════
       DEFAULT ERROR MESSAGES
    ═══════════════════════════════ */

    const defaultMessages = {
        required:      'This field is required.',
        minLength:     'Minimum {0} characters required.',
        maxLength:     'Maximum {0} characters allowed.',
        numeric:       'Please enter a valid number.',
        positive:      'Value must be greater than 0.',
        nonNegative:   'Value must be 0 or greater.',
        mobile:        'Enter a valid 10-digit mobile number.',
        email:         'Enter a valid email address.',
        vehicleNumber: 'Enter a valid vehicle number (e.g. GJ05AB1234).',
        date:          'Enter a valid date.',
        notFutureDate: 'Date cannot be in the future.',
        notPastDate:   'Date cannot be in the past.',
        gstin:         'Enter a valid 15-digit GSTIN.',
        pan:           'Enter a valid PAN number.',
        regex:         'Invalid format.',
        matches:       'Values do not match.',
        maxAmount:     'Amount cannot exceed the allowed limit.',
        selectRequired:'Please select an option.',
    };

    function getMessage(ruleName, ruleParam, customMsg) {
        if (customMsg) return customMsg;
        let msg = defaultMessages[ruleName] || 'Invalid value.';
        if (ruleParam !== undefined && ruleParam !== null) {
            msg = msg.replace('{0}', ruleParam);
        }
        return msg;
    }


    /* ═══════════════════════════════
       MAIN VALIDATE FUNCTION
    ═══════════════════════════════
     
     rules example:
     {
       'fieldId': {
         required:  true,
         minLength: [3, 'Too short'],
         numeric:   true,
       }
     }
    */
    function validate(formSelector, rules, options = {}) {
        const form = typeof formSelector === 'string'
            ? document.querySelector(formSelector)
            : formSelector;

        if (!form) {
            console.warn('SRV.validate: Form not found:', formSelector);
            return false;
        }

        utils.clearForm(form);

        let isValid = true;
        let firstErrorEl = null;

        for (const [fieldId, fieldRules] of Object.entries(rules)) {
            const el = utils.field(fieldId);
            if (!el) continue;

            const value = utils.val(el);
            let fieldValid = true;

            for (const [ruleName, ruleConfig] of Object.entries(fieldRules)) {
                // ruleConfig can be: true | number | [param, message] | message string
                let ruleParam  = null;
                let customMsg  = null;

                if (Array.isArray(ruleConfig)) {
                    ruleParam = ruleConfig[0];
                    customMsg = ruleConfig[1] || null;
                } else if (typeof ruleConfig === 'boolean' && ruleConfig === false) {
                    continue; // Rule disabled
                } else if (typeof ruleConfig === 'string') {
                    customMsg = ruleConfig;
                } else if (typeof ruleConfig === 'number') {
                    ruleParam = ruleConfig;
                }

                // Skip non-required empty fields (unless rule is 'required')
                if (ruleName !== 'required' && value === '') {
                    const reqRule = fieldRules['required'];
                    if (!reqRule) continue;
                }

                const validatorFn = validators[ruleName];
                if (!validatorFn) {
                    console.warn('SRV: Unknown rule:', ruleName);
                    continue;
                }

                const passed = ruleParam !== null
                    ? validatorFn(value, ruleParam)
                    : validatorFn(value);

                if (!passed) {
                    const msg = getMessage(ruleName, ruleParam, customMsg);
                    utils.showError(el, msg);
                    fieldValid = false;
                    isValid = false;
                    if (!firstErrorEl) firstErrorEl = el;
                    break; // Show one error at a time per field
                }
            }

            if (fieldValid && value !== '') {
                utils.showSuccess(el);
            }
        }

        // Scroll to first error
        if (!isValid && firstErrorEl && options.scrollToError !== false) {
            firstErrorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstErrorEl.focus();
        }

        // Callbacks
        if (isValid && typeof options.onSuccess === 'function') {
            options.onSuccess(form);
        }
        if (!isValid && typeof options.onError === 'function') {
            options.onError(form, firstErrorEl);
        }

        return isValid;
    }


    /* ═══════════════════════════════
       REAL-TIME VALIDATION (on input/change)
    ═══════════════════════════════ */
    function bindLive(formSelector, rules) {
        const form = typeof formSelector === 'string'
            ? document.querySelector(formSelector)
            : formSelector;
        if (!form) return;

        for (const fieldId of Object.keys(rules)) {
            const el = utils.field(fieldId);
            if (!el) continue;

            const events = (el.tagName === 'SELECT') ? ['change'] : ['input', 'blur'];
            events.forEach(evt => {
                el.addEventListener(evt, function () {
                    // Validate only this field
                    const singleRule = { [fieldId]: rules[fieldId] };
                    validateSingle(el, rules[fieldId]);
                });
            });
        }
    }

    function validateSingle(el, fieldRules) {
        utils.clear(el);
        const value = utils.val(el);

        for (const [ruleName, ruleConfig] of Object.entries(fieldRules)) {
            let ruleParam = null;
            let customMsg = null;

            if (Array.isArray(ruleConfig)) {
                ruleParam = ruleConfig[0];
                customMsg = ruleConfig[1] || null;
            } else if (typeof ruleConfig === 'string') {
                customMsg = ruleConfig;
            } else if (typeof ruleConfig === 'number') {
                ruleParam = ruleConfig;
            }

            if (ruleName !== 'required' && value === '') continue;

            const validatorFn = validators[ruleName];
            if (!validatorFn) continue;

            const passed = ruleParam !== null
                ? validatorFn(value, ruleParam)
                : validatorFn(value);

            if (!passed) {
                const msg = getMessage(ruleName, ruleParam, customMsg);
                utils.showError(el, msg);
                return false;
            }
        }

        if (value !== '') utils.showSuccess(el);
        return true;
    }


    /* ═══════════════════════════════
       PRESET RULE SETS
       (Ready-to-use for common forms)
    ═══════════════════════════════ */

    const presets = {

        /** Party / Consigner / Consignee */
        partyForm: {
            'party-name':    { required: true, minLength: 2 },
            'party-mobile':  { mobile: true },
            'party-gstin':   { gstin: true },
            'party-pan':     { pan: true },
        },

        /** Vehicle */
        vehicleForm: {
            'vehicle-number': { required: true, vehicleNumber: true },
            'vehicle-type':   { required: true },
        },

        /** Vehicle Owner */
        ownerForm: {
            'owner-name':   { required: true, minLength: 2 },
            'owner-mobile': { mobile: true },
            'owner-pan':    { pan: true },
        },

        /** Trip Form (Regular & Agent) */
        tripForm: {
            'trip-date':       { required: true, date: true },
            'vehicle-id':      { required: true, selectRequired: true },
            'consigner-id':    { required: true, selectRequired: true },
            'consignee-id':    { required: true, selectRequired: true },
            'from-location':   { required: true },
            'to-location':     { required: true },
            'freight-amount':  { required: true, numeric: true, positive: true },
        },

        /** Bill Payment */
        billPaymentForm: {
            'payment-date':   { required: true, date: true },
            'payment-amount': { required: true, numeric: true, positive: true },
            'payment-mode':   { required: true },
        },

        /** Owner Payment */
        ownerPaymentForm: {
            'payment-date':   { required: true, date: true },
            'payment-amount': { required: true, numeric: true, positive: true },
        },

        /** Party Advance */
        partyAdvanceForm: {
            'advance-date':   { required: true, date: true },
            'advance-amount': { required: true, numeric: true, positive: true },
            'party-id':       { required: true, selectRequired: true },
        },

        /** Owner Advance */
        ownerAdvanceForm: {
            'advance-date':   { required: true, date: true },
            'advance-amount': { required: true, numeric: true, positive: true },
            'owner-id':       { required: true, selectRequired: true },
        },
    };


    /* ═══════════════════════════════
       TOAST HELPER
       (Uses SweetAlert2 toast if available)
    ═══════════════════════════════ */

    const toast = {
        success(msg, duration = 3000) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: msg,
                    showConfirmButton: false, timer: duration, timerProgressBar: true });
            } else { console.log('✅', msg); }
        },
        error(msg, duration = 4000) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: msg,
                    showConfirmButton: false, timer: duration, timerProgressBar: true });
            } else { console.error('❌', msg); }
        },
        warning(msg, duration = 3500) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: msg,
                    showConfirmButton: false, timer: duration, timerProgressBar: true });
            } else { console.warn('⚠️', msg); }
        },
        info(msg, duration = 3000) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: msg,
                    showConfirmButton: false, timer: duration, timerProgressBar: true });
            } else { console.info('ℹ️', msg); }
        }
    };


    /* ═══════════════════════════════
       CONFIRM DIALOG HELPER
    ═══════════════════════════════ */

    function confirm(options = {}) {
        const defaults = {
            title:       'Are you sure?',
            text:        'This action cannot be undone.',
            icon:        'warning',
            confirmText: 'Yes, Proceed',
            cancelText:  'Cancel',
            onConfirm:   null,
        };
        const cfg = Object.assign({}, defaults, options);

        if (typeof Swal === 'undefined') {
            if (window.confirm(cfg.title + '\n' + cfg.text)) {
                if (typeof cfg.onConfirm === 'function') cfg.onConfirm();
            }
            return;
        }

        Swal.fire({
            title:              cfg.title,
            text:               cfg.text,
            icon:               cfg.icon,
            showCancelButton:   true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor:  '#64748b',
            confirmButtonText:  cfg.confirmText,
            cancelButtonText:   cfg.cancelText,
        }).then(function (result) {
            if (result.isConfirmed && typeof cfg.onConfirm === 'function') {
                cfg.onConfirm();
            }
        });
    }


    /* ═══════════════════════════════
       NUMBER / AMOUNT FORMATTERS
    ═══════════════════════════════ */

    const format = {
        /** Format number as Indian currency: 1,23,456.00 */
        inr(value, decimals = 2) {
            const n = parseFloat(value);
            if (isNaN(n)) return '₹ 0.00';
            return '₹ ' + n.toLocaleString('en-IN', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        },

        /** Format number as Rs. prefix */
        rs(value, decimals = 0) {
            const n = parseFloat(value);
            if (isNaN(n)) return 'Rs. 0';
            return 'Rs. ' + n.toLocaleString('en-IN', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        },

        /** Uppercase vehicle number automatically */
        vehicleNumber(value) {
            return String(value).toUpperCase().replace(/[^A-Z0-9]/g, '');
        },

        /** Format phone: add spaces every 5 digits */
        phone(value) {
            return String(value).replace(/\D/g, '').slice(0, 10);
        }
    };


    /* ═══════════════════════════════
       AUTO-BIND HELPERS
       Call on DOMContentLoaded
    ═══════════════════════════════ */

    function autoBindUppercase(selector = '[data-uppercase]') {
        document.querySelectorAll(selector).forEach(el => {
            el.addEventListener('input', function () {
                const pos = this.selectionStart;
                this.value = this.value.toUpperCase();
                this.setSelectionRange(pos, pos);
            });
        });
    }

    function autoBindNumericOnly(selector = '[data-numeric]') {
        document.querySelectorAll(selector).forEach(el => {
            el.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9.]/g, '');
            });
            el.addEventListener('keypress', function (e) {
                if (!/[0-9.]/.test(e.key)) e.preventDefault();
            });
        });
    }

    function autoBindMobileOnly(selector = '[data-mobile]') {
        document.querySelectorAll(selector).forEach(el => {
            el.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
            });
        });
    }

    /** Call this once on every page */
    function init() {
        autoBindUppercase();
        autoBindNumericOnly();
        autoBindMobileOnly();
    }


    /* ═══════════════════════════════
       PUBLIC API
    ═══════════════════════════════ */
    return {
        validate,
        bindLive,
        validators,
        presets,
        toast,
        confirm,
        format,
        utils,
        init,
    };

})();

// Auto-init on DOM ready
document.addEventListener('DOMContentLoaded', function () {
    SRV.init();
});


/* ═══════════════════════════════════════════════════════════════
   USAGE EXAMPLES:

   // 1. Validate on submit
   document.getElementById('myForm').addEventListener('submit', function(e) {
       e.preventDefault();
       const ok = SRV.validate('#myForm', {
           'party-name':    { required: true, minLength: 2 },
           'party-mobile':  { mobile: true },
           'freight-amount':{ required: true, numeric: true, positive: true },
       }, {
           onSuccess: function(form) { form.submit(); }
       });
   });

   // 2. Use preset rules
   const ok = SRV.validate('#tripForm', SRV.presets.tripForm);

   // 3. Live validation
   SRV.bindLive('#tripForm', SRV.presets.tripForm);

   // 4. Toast messages
   SRV.toast.success('Trip saved successfully!');
   SRV.toast.error('Failed to save. Try again.');

   // 5. Confirm delete
   SRV.confirm({
       title: 'Delete Trip?',
       text: 'This trip will be permanently deleted.',
       onConfirm: function() { deleteTrip(id); }
   });

   // 6. Format amount
   SRV.format.rs(12500);   // "Rs. 12,500"
   SRV.format.inr(12500);  // "₹ 12,500.00"

   // 7. Auto-uppercase input (add attribute to HTML)
   <input type="text" data-uppercase id="vehicle-number">

   // 8. Numeric only input
   <input type="text" data-numeric id="freight-amount">

   // 9. Mobile only input
   <input type="text" data-mobile id="owner-mobile">
═══════════════════════════════════════════════════════════════ */
