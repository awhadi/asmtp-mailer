(function () {
    'use strict';

    function getLocalizedValue(key, fallback) {
        return window.asmtpMailerAdmin && window.asmtpMailerAdmin[key]
            ? window.asmtpMailerAdmin[key]
            : fallback;
    }

    function confirmFormSubmit(form, messageKey, fallbackMessage) {
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            if (!window.confirm(getLocalizedValue(messageKey, fallbackMessage))) {
                event.preventDefault();
            }
        });
    }

    function bindEncryptionPortSync() {
        var encryptionSelect = document.querySelector('#type_of_encryption');
        var portInput = document.querySelector('#smtp_port');
        var defaultPorts = getLocalizedValue('defaultPorts', {
            tls: 587,
            ssl: 465,
            none: 25
        });

        if (!encryptionSelect || !portInput) {
            return;
        }

        portInput.addEventListener('input', function () {
            portInput.dataset.portManual = 'true';
        });

        encryptionSelect.addEventListener('change', function () {
            var selectedEncryption = encryptionSelect.value;
            if (defaultPorts[selectedEncryption]) {
                portInput.value = defaultPorts[selectedEncryption];
                portInput.dataset.portManual = 'false';
            }
        });
    }

    function buildToast(message, type) {
        var toastRegion = document.querySelector('.asmtp-mailer-toast-region');
        if (!toastRegion || !message) {
            return;
        }

        var toast = document.createElement('div');
        toast.className = 'asmtp-mailer-toast asmtp-mailer-toast-' + type;
        toast.textContent = message;
        toastRegion.appendChild(toast);

        window.setTimeout(function () {
            toast.classList.add('is-visible');
        }, 20);

        window.setTimeout(function () {
            toast.classList.remove('is-visible');
            window.setTimeout(function () {
                toast.remove();
            }, 240);
        }, 5200);
    }

    function moveAdminNoticesToToasts() {
        var notices = document.querySelectorAll('.asmtp-mailer-admin-content .notice');

        notices.forEach(function (notice) {
            var message = notice.textContent.trim();
            var type = notice.classList.contains('notice-error') ? 'error' : 'success';

            buildToast(message, type);
            notice.remove();
        });
    }

    function bindChangePassword() {
        document.querySelectorAll('.asmtp-mailer-change-password-button').forEach(function (button) {
            button.addEventListener('click', function () {
                var target = document.getElementById(button.dataset.passwordTarget);
                if (!target) {
                    return;
                }

                target.disabled = false;
                target.value = '';
                target.focus();
                button.disabled = true;
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        confirmFormSubmit(
            document.querySelector('.asmtp-mailer-reset-form'),
            'deleteConfirmMessage',
            'Are you sure you want to reset these settings?'
        );
        confirmFormSubmit(
            document.querySelector('.asmtp-mailer-inline-form'),
            'clearLogConfirmMessage',
            'Are you sure you want to clear the logs?'
        );
        bindEncryptionPortSync();
        bindChangePassword();
        moveAdminNoticesToToasts();
    });
})();
