/* ============================================================
   Auth form validation — red while invalid, green when valid.
   Used by login/register. Fields opt in via data-rule.
   ============================================================ */

(function () {
    'use strict';

    var RULES = {
        username: {
            test: function (v) { return v.trim().length >= 3; },
            err: 'Username must be at least 3 characters.',
            ok: 'Username looks good.'
        },
        email: {
            test: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()); },
            err: 'Enter a valid email address.',
            ok: 'Email looks good.'
        },
        password: {
            test: function (v) { return v.length >= 8; },
            err: 'At least 8 characters.',
            ok: 'Password meets the requirement.'
        }
    };

    function feedbackFor(input) {
        var label = input.closest('label');
        return label ? label.querySelector('.feedback') : null;
    }

    function validateField(input) {
        var rule = RULES[input.dataset.rule];
        if (!rule) { return true; }

        var value = input.value;
        var ok = rule.test(value);
        var touched = value.length > 0;

        input.classList.toggle('valid', ok);
        input.classList.toggle('invalid', !ok && touched);

        var fb = feedbackFor(input);
        if (fb) {
            if (!touched) {
                fb.textContent = '';
                fb.className = 'hint feedback';
            } else {
                fb.textContent = ok ? rule.ok : rule.err;
                fb.className = 'hint feedback ' + (ok ? 'hint-ok' : 'hint-err');
            }
        }
        return ok;
    }

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        var fields = form.querySelectorAll('input[data-rule]');

        fields.forEach(function (input) {
            input.addEventListener('input', function () { validateField(input); });
            input.addEventListener('blur', function () { validateField(input); });
        });

        form.addEventListener('submit', function (e) {
            var bad = [];
            fields.forEach(function (input) {
                if (!validateField(input) && input.value.length > 0) { bad.push(input); }
            });
            if (bad.length) {
                e.preventDefault();
                bad[0].focus();
            }
        });
    });
})();
