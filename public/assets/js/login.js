// Reload only the captcha image instead of the whole page.
document.addEventListener('DOMContentLoaded', function () {
    var link = document.getElementById('captcha-refresh');
    var img = document.getElementById('captcha-img');
    var form = document.getElementById('login-form');
    var fields = document.getElementById('login-fields');
    var notice = document.getElementById('login-lockout');
    var timer = document.getElementById('lockout-time');
    var captchaLoading = !!img && !img.complete;
    var clearCaptchaError = false;

    function hideCaptchaError() {
        var error = document.getElementById('login-error');
        if (error && error.dataset.errorType === 'captcha') {
            error.hidden = true;
        }
    }

    if (form) {
        form.querySelectorAll('#username, #password, #captcha').forEach(function (input) {
            ['focus', 'input', 'click'].forEach(function (eventName) {
                input.addEventListener(eventName, hideCaptchaError);
            });
        });
    }

    if (img) {
        img.addEventListener('load', function () {
            captchaLoading = false;
            if (clearCaptchaError) { hideCaptchaError(); }
            clearCaptchaError = false;
        });
        img.addEventListener('error', function () {
            captchaLoading = false;
        });
    }

    function refreshCaptcha() {
        if (!img || captchaLoading) { return; }
        captchaLoading = true;
        clearCaptchaError = true;
        img.src = '/captcha_image.php?t=' + Date.now();
        var input = document.getElementById('captcha');
        if (input) { input.value = ''; }
    }

    if (link) {
        link.addEventListener('click', function (ev) {
            ev.preventDefault();
            if (fields && fields.disabled) { return; }
            refreshCaptcha();
            var input = document.getElementById('captcha');
            if (input) { input.focus(); }
        });
    }

    if (!form || !fields || !notice || !timer) { return; }

    form.addEventListener('submit', function (ev) {
        if (fields.disabled || captchaLoading) { ev.preventDefault(); }
    });

    var seconds = Number(notice.dataset.remainingSeconds);
    if (!Number.isFinite(seconds) || seconds <= 0) { return; }

    var deadline = Date.now() + seconds * 1000;
    fields.disabled = true;
    notice.hidden = false;
    if (link) {
        link.setAttribute('aria-disabled', 'true');
        link.tabIndex = -1;
    }

    function updateCountdown() {
        var remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        timer.textContent = String(Math.floor(remaining / 60)).padStart(2, '0') + ':' +
            String(remaining % 60).padStart(2, '0');
        if (remaining > 0) { return; }

        clearInterval(interval);
        fields.disabled = false;
        notice.hidden = true;
        if (link) {
            link.removeAttribute('aria-disabled');
            link.removeAttribute('tabindex');
        }
        refreshCaptcha();
        var username = document.getElementById('username');
        if (username) { username.focus(); }
    }

    var interval = setInterval(updateCountdown, 250);
    updateCountdown();
});
