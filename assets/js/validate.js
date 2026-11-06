(function () {
    'use strict';

    function showError(input, message) {
        clearError(input);
        input.classList.add('invalid');
        var msg = document.createElement('div');
        msg.className = 'field-error';
        msg.textContent = message;
        var host = input.closest('.pw-wrap') || input;
        host.insertAdjacentElement('afterend', msg);
    }

    function clearError(input) {
        input.classList.remove('invalid');
        var host = input.closest('.pw-wrap') || input;
        var next = host.nextElementSibling;
        if (next && next.classList.contains('field-error')) {
            next.remove();
        }
    }

    // Show/hide password buttons
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
        });
    });

    // Success messages fade out after a few seconds.
    document.querySelectorAll('.alert-success[data-autohide]').forEach(function (el) {
        setTimeout(function () { el.classList.add('hide'); }, 4500);
    });

    var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // Login form
    var loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            var ok = true;
            var email = loginForm.email, pw = loginForm.password;
            [email, pw].forEach(clearError);
            if (!emailRe.test(email.value.trim())) { showError(email, 'Enter a valid email.'); ok = false; }
            if (pw.value === '') { showError(pw, 'Enter your password.'); ok = false; }
            if (!ok) { e.preventDefault(); }
        });
    }

    // Signup form
    var signupForm = document.getElementById('signupForm');
    if (signupForm) {
        // Role is a group of radio cards; .value gives the checked one.
        var roleSelect = signupForm.elements.role;

        function syncRoleFields() {
            signupForm.querySelectorAll('.role-fields').forEach(function (box) {
                box.classList.toggle('active', box.dataset.role === roleSelect.value);
            });
        }
        signupForm.addEventListener('change', function (e) {
            if (e.target.name === 'role') { syncRoleFields(); }
        });
        syncRoleFields();

        signupForm.addEventListener('submit', function (e) {
            var f = signupForm, ok = true;
            [f.full_name, f.email, f.phone, f.password, f.confirm_password, f.ngo_name, f.registration_no]
                .forEach(function (i) { if (i) { clearError(i); } });

            if (f.full_name.value.trim() === '') { showError(f.full_name, 'Full name is required.'); ok = false; }
            if (!emailRe.test(f.email.value.trim())) { showError(f.email, 'Enter a valid email.'); ok = false; }
            if (f.phone.value.trim() !== '' && !/^[0-9+\- ]{7,20}$/.test(f.phone.value.trim())) {
                showError(f.phone, 'Enter a valid phone number.'); ok = false;
            }
            if (f.password.value.length < 8) { showError(f.password, 'Use at least 8 characters.'); ok = false; }
            if (f.password.value !== f.confirm_password.value) {
                showError(f.confirm_password, 'Passwords do not match.'); ok = false;
            }
            if (roleSelect.value === 'ngo') {
                if (f.ngo_name.value.trim() === '') { showError(f.ngo_name, 'NGO name is required.'); ok = false; }
                if (f.registration_no.value.trim() === '') { showError(f.registration_no, 'Registration number is required.'); ok = false; }
            }
            if (!ok) { e.preventDefault(); }
        });
    }
})();
