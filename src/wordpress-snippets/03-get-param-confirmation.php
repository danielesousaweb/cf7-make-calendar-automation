<?php
/**
 * One-click RSVP confirmation via tracked email links (Brevo / Mailchimp).
 *
 * Email campaigns send a personalized link containing the recipient's
 * data as GET parameters (name, email, company, role, department,
 * phone). When that link is opened, this script:
 *
 *   1. Reads and decodes the GET parameters.
 *   2. Normalizes "empty-ish" values (placeholders like "--",
 *      "nao informado", "/", or literal unresolved merge tags such as
 *      Mailchimp's *|FNAME|* when a contact has no first name) into
 *      safe fallback values, so the downstream form never submits
 *      garbage data.
 *   3. Hides the manual form and shows a single confirmation prompt
 *      ("Confirm attendance for [Name]?").
 *   4. On confirmation, programmatically fills the real CF7 form
 *      fields and submits it - re-using the exact same submit path
 *      (and therefore the same webhook dispatcher) as a manual
 *      submission, so there is only one code path to maintain.
 *
 * This collapses "email click" and "manual form fill" into a single
 * downstream flow, which is what keeps the Make.com scenario and the
 * webhook dispatcher snippet free of any branching logic.
 *
 * @sanitized true - the target page slug below is a placeholder.
 */

add_action( 'wp_footer', 'cas_confirm_attendance_via_get' );

function cas_confirm_attendance_via_get() {

    if ( ! is_page( 'webinar-event-slug-placeholder' ) ) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {

        var params = new URLSearchParams(window.location.search);
        var nome = params.get('nome');
        var email = params.get('email');
        var cargo = params.get('cargo');
        var empresa = params.get('empresa');
        var departamento = params.get('departamento');
        var telefone = params.get('telefone');

        var form = document.querySelector('.wpcf7');
        var formEl = document.querySelector('.wpcf7 form');

        // No email in the URL: this is a normal visit, show the manual form.
        if (!email || !email.trim()) {
            if (form) {
                form.style.display = 'block';
                form.style.visibility = 'visible';
                form.style.position = 'relative';
            }
            return;
        }

        if (form) form.style.display = 'none';

        var nomeDecoded = decodeURIComponent(nome || '');
        var emailDecoded = decodeURIComponent(email);
        var cargoDecoded = decodeURIComponent(cargo || '');
        var empresaDecoded = decodeURIComponent(empresa || '');
        var departamentoDecoded = decodeURIComponent(departamento || '');
        var telefoneDecoded = decodeURIComponent(telefone || '');

        // Values treated as "effectively empty" across both ESPs.
        var invalidValues = ['', '--', 'nao informado', 'não informado', '/'];

        function valueOrFallback(value, fallback) {
            if (!value) return fallback;
            var normalized = value.trim().toLowerCase();
            for (var i = 0; i < invalidValues.length; i++) {
                if (normalized === invalidValues[i]) return fallback;
            }
            return value.trim();
        }

        // Detects unresolved merge tags (e.g. contact has no first name
        // saved in the ESP, so the literal tag string leaks into the URL).
        var nameIsInvalid = !nomeDecoded.trim()
            || nomeDecoded.includes('|FULLNAME|')
            || nomeDecoded.includes('|FNAME|');

        var nameForDisplay = nameIsInvalid ? emailDecoded : nomeDecoded;
        var nameForForm = nameIsInvalid ? 'Not informed' : nomeDecoded;

        var roleForForm = valueOrFallback(cargoDecoded, 'auto');
        var departmentForForm = valueOrFallback(departamentoDecoded, 'auto');
        var companyForForm = valueOrFallback(empresaDecoded, 'Company');
        var phoneForForm = valueOrFallback(telefoneDecoded, '00000000000');

        var promptBlock = document.createElement('div');
        promptBlock.style.cssText =
            'max-width:600px;margin:40px auto;padding:40px;background:#fff;' +
            'border-radius:12px;box-shadow:0 2px 20px rgba(0,0,0,0.1);' +
            'text-align:center;font-family:sans-serif;';

        promptBlock.innerHTML =
            '<h2 style="color:#1a1a2e;margin-bottom:10px;">Confirm attendance</h2>' +
            '<p style="color:#555;font-size:16px;margin-bottom:30px;">' +
            '<strong>' + nameForDisplay + '</strong>, do you want to confirm your spot at the webinar?</p>' +
            '<button id="btn-confirm" style="display:block;width:100%;background:#0073aa;color:#fff;' +
            'border:none;padding:14px 40px;border-radius:6px;font-size:16px;cursor:pointer;margin-bottom:12px;">' +
            'Yes, confirm</button>' +
            '<button id="btn-decline" style="display:block;width:100%;background:#f0f0f0;color:#333;' +
            'border:none;padding:14px 40px;border-radius:6px;font-size:16px;cursor:pointer;">' +
            'Register someone else</button>';

        if (form) form.parentNode.insertBefore(promptBlock, form);

        document.getElementById('btn-confirm').addEventListener('click', function () {
            if (!formEl) return;

            var fieldName = formEl.querySelector('input[name="nome"]');
            var fieldEmail = formEl.querySelector('input[name="email"]');
            var fieldDepartment = formEl.querySelector('input[name="departamento"]');
            var fieldRole = formEl.querySelector('input[name="cargo"]');
            var fieldPhone = formEl.querySelector('input[name="telefone"]');
            var fieldConsent = formEl.querySelector('input[name="aceite"]');
            var fieldCompany = formEl.querySelector('select[name="empresa"]');
            var submitButton = formEl.querySelector('input[type="submit"]');
            var spinner = formEl.querySelector('.wpcf7-spinner');

            if (fieldName) fieldName.value = nameForForm;
            if (fieldEmail) fieldEmail.value = emailDecoded;
            if (fieldDepartment) fieldDepartment.value = departmentForForm;
            if (fieldRole) fieldRole.value = roleForForm;
            if (fieldPhone) fieldPhone.value = phoneForForm;

            if (fieldConsent) {
                fieldConsent.checked = true;
                fieldConsent.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (fieldCompany) {
                fieldCompany.value = companyForForm;
                fieldCompany.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // CF7's own validation listens for input/change events, so
            // setting .value alone is not enough - dispatch both.
            [fieldName, fieldEmail, fieldDepartment, fieldRole, fieldPhone].forEach(function (field) {
                if (field) {
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            promptBlock.innerHTML = '<p style="font-size:18px;color:#0073aa;">Confirming your attendance...</p>';

            form.style.display = 'block';
            form.style.visibility = 'hidden';
            form.style.position = 'absolute';

            if (submitButton) {
                submitButton.removeAttribute('disabled');
                if (spinner) spinner.style.display = 'inline-block';
                // Small delay lets CF7's own JS finish initializing before
                // we trigger a programmatic submit.
                setTimeout(function () {
                    submitButton.click();
                }, 300);
            }
        });

        document.getElementById('btn-decline').addEventListener('click', function () {
            promptBlock.remove();
            if (form) {
                form.style.display = 'block';
                form.style.visibility = 'visible';
                form.style.position = 'relative';
            }
        });
    });
    </script>
    <?php
}
