jQuery(document).ready(function($) {
    // Initialize floating labels
    initFloatingLabels();
    
    // Form switching animation
    function switchToForgotPassword() {
        const loginForm = $('#dtr-login-form');
        const forgotForm = $('#dtr-forgot-password-form');
        
        loginForm.addClass('dtr-form-slide-out');
        
        setTimeout(() => {
            loginForm.hide().removeClass('dtr-form-slide-out');
            forgotForm.show().addClass('dtr-form-slide-in');
            // Reinitialize floating labels for forgot password form
            setTimeout(initFloatingLabels, 50);
        }, 300);
    }
    
    function switchToLogin() {
        const loginForm = $('#dtr-login-form');
        const forgotForm = $('#dtr-forgot-password-form');
        
        forgotForm.addClass('dtr-form-slide-out');
        
        setTimeout(() => {
            forgotForm.hide().removeClass('dtr-form-slide-out dtr-form-slide-in');
            loginForm.show().addClass('dtr-form-slide-in');
            setTimeout(() => {
                loginForm.removeClass('dtr-form-slide-in');
                // Reinitialize floating labels for login form
                setTimeout(initFloatingLabels, 50);
            }, 300);
        }, 300);
    }
    
    // Event handlers for form switching
    $(document).on('click', '#dtr-forgot-password-trigger', function(e) {
        e.preventDefault();
        clearMessages();
        switchToForgotPassword();
    });
    
    $(document).on('click', '#dtr-back-to-login', function(e) {
        e.preventDefault();
        clearMessages();
        switchToLogin();
    });
    
    // Initialize floating labels
    function initFloatingLabels() {
        const floatingFields = document.querySelectorAll('.floating-label input, .floating-label select, .floating-label .password-field input');
        
        floatingFields.forEach(field => {
            // Set initial state on page load
            updateFloatingLabel(field);
            
            // Handle focus/blur events
            field.addEventListener('focus', () => updateFloatingLabel(field));
            field.addEventListener('blur', () => updateFloatingLabel(field));
            field.addEventListener('input', () => updateFloatingLabel(field));
            field.addEventListener('change', () => updateFloatingLabel(field));
        });
    }

    function updateFloatingLabel(field) {
        const fieldContainer = field.closest('.floating-label');
        if (!fieldContainer) return;
        
        const label = fieldContainer.querySelector('label');
        if (!label) return;
        
        const hasValue = field.value && field.value.trim() !== '';
        const isFocused = document.activeElement === field;
        
        // For selects, check if a non-empty option is selected
        if (field.tagName === 'SELECT') {
            const selectedOption = field.options[field.selectedIndex];
            const selectHasValue = selectedOption && selectedOption.value && selectedOption.value !== '';
            
            if (selectHasValue || isFocused) {
                fieldContainer.classList.add('floating-active');
            } else {
                fieldContainer.classList.remove('floating-active');
            }
        } else {
            // For regular inputs and password inputs
            if (hasValue || isFocused) {
                fieldContainer.classList.add('floating-active');
            } else {
                fieldContainer.classList.remove('floating-active');
            }
        }
    }

    // Password toggle functions
    window.toggleLoginPassword = function(fieldId) {
        const field = document.getElementById(fieldId);
        const button = field.parentNode.querySelector('.password-toggle');
        
        if (field.type === 'password') {
            field.type = 'text';
            button.textContent = '🙈';
        } else {
            field.type = 'password';
            button.textContent = '👁';
        }
    };

    window.toggleResetPassword = function(fieldId) {
        const field = document.getElementById(fieldId);
        const button = field.parentNode.querySelector('.password-toggle');
        
        if (field.type === 'password') {
            field.type = 'text';
            button.textContent = '🙈';
        } else {
            field.type = 'password';
            button.textContent = '👁';
        }
    };
    $(document).on('input', '#dtr-new-password', function() {
        const password = $(this).val();
        const strengthIndicator = $('#dtr-password-strength');
        
        if (password.length === 0) {
            strengthIndicator.text('').removeClass('weak medium strong');
            return;
        }
        
        let strength = 0;
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;
        
        switch (strength) {
            case 1:
                strengthIndicator.text('Weak password').removeClass('medium strong').addClass('weak');
                break;
            case 2:
                strengthIndicator.text('Fair password').removeClass('weak strong').addClass('medium');
                break;
            case 3:
                strengthIndicator.text('Good password').removeClass('weak medium').addClass('strong');
                break;
            case 4:
                strengthIndicator.text('Strong password').removeClass('weak medium').addClass('strong');
                break;
            default:
                strengthIndicator.text('Too short').removeClass('medium strong').addClass('weak');
        }
    });
    
    // Password confirmation checker
    $(document).on('input', '#dtr-confirm-password', function() {
        const password = $('#dtr-new-password').val();
        const confirm = $(this).val();
        const errorSpan = $('#confirm-password-error');
        
        if (confirm.length === 0) {
            errorSpan.text('');
            $(this).removeClass('error');
            return;
        }
        
        if (password !== confirm) {
            errorSpan.text('Passwords do not match');
            $(this).addClass('error');
        } else {
            errorSpan.text('');
            $(this).removeClass('error');
        }
    });
    
    // Clear form errors
    function clearFormErrors(form) {
        form.find('.dtr-form-error').text('');
        form.find('.dtr-form-input').removeClass('error');
    }
    
    // Clear messages
    function clearMessages() {
        $('#dtr-form-messages, #dtr-reset-messages').empty();
    }
    
    // Show message
    function showMessage(message, type, container) {
        const messageHtml = `<div class="dtr-message ${type}">${message}</div>`;
        $(container).html(messageHtml);
    }
    
    // Button loading state
    function setButtonLoading(button, loading) {
        if (loading) {
            button.prop('disabled', true);
            button.addClass('btn-loading');
        } else {
            button.prop('disabled', false);
            button.removeClass('btn-loading');
        }
    }
    
    // Login form submission
    $(document).on('submit', '#dtr-login-form', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = $('#dtr-login-submit');
        
        clearFormErrors(form);
        clearMessages();
        setButtonLoading(submitBtn, true);
        
        $.ajax({
            url: dtr_login_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dtr_user_login',
                username: $('#dtr-username').val(),
                password: $('#dtr-password').val(),
                remember: $('input[name="remember"]').is(':checked') ? 1 : 0,
                redirect_to: $('input[name="redirect_to"]').val(),
                dtr_login_nonce: $('input[name="dtr_login_nonce"]').val()
            },
            success: function(response) {
                setButtonLoading(submitBtn, false);
                
                if (response.success) {
                    showMessage(response.data.message, 'success', '#dtr-form-messages');
                    
                    setTimeout(() => {
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 1000);
                } else {
                    showMessage(response.data.message, 'error', '#dtr-form-messages');
                }
            },
            error: function() {
                setButtonLoading(submitBtn, false);
                showMessage('Something went wrong. Please try again.', 'error', '#dtr-form-messages');
            }
        });
    });
    
    // Forgot password form submission
    $(document).on('submit', '#dtr-forgot-password-form', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = $('#dtr-forgot-submit');
        
        clearFormErrors(form);
        clearMessages();
        setButtonLoading(submitBtn, true);
        
        $.ajax({
            url: dtr_login_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dtr_forgot_password',
                user_email: $('#dtr-forgot-email').val(),
                dtr_forgot_nonce: $('input[name="dtr_forgot_nonce"]').val()
            },
            success: function(response) {
                setButtonLoading(submitBtn, false);
                
                if (response.success) {
                    showMessage(response.data.message, 'success', '#dtr-form-messages');
                    form[0].reset();
                    
                    // Switch back to login form after 3 seconds
                    setTimeout(() => {
                        switchToLogin();
                        clearMessages();
                    }, 3000);
                } else {
                    showMessage(response.data.message, 'error', '#dtr-form-messages');
                }
            },
            error: function() {
                setButtonLoading(submitBtn, false);
                showMessage('Something went wrong. Please try again.', 'error', '#dtr-form-messages');
            }
        });
    });
    // Password strength checker for reset form
    $(document).on('input', '#dtr-new-password', function() {
        const password = $(this).val();
        const meter = $('#password-strength-meter');
        const text = $('#dtr-password-strength');

        if (password.length === 0) {
            text.text('').removeClass('weak medium strong');
            if (meter.length) meter[0].value = 0;
            checkResetPasswordMatch();
            return;
        }

        let strength = 0;
        let message = "";

        if (password.length < 8) {
            message = "At least 8 characters required";
            strength = 0;
        } else if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
            message = "Add a special character";
            strength = 1;
        } else if (!/[A-Z]/.test(password)) {
            message = "Add an uppercase letter";
            strength = 2;
        } else {
            message = "Strong password!";
            strength = 3;
        }

        // Update meter
        if (meter.length) meter[0].value = strength;
        
        // Update text
        text.text(message);
        text.removeClass('weak medium strong');
        if (strength <= 1) text.addClass('weak');
        else if (strength === 2) text.addClass('medium');
        else text.addClass('strong');

        // Check password matching
        checkResetPasswordMatch();
    });

    // Password confirmation checker for reset form
    function checkResetPasswordMatch() {
        const password = $('#dtr-new-password').val();
        const confirm = $('#dtr-confirm-password').val();
        const matchText = $('#password-match-text');

        if (!confirm) {
            matchText.removeClass('match no-match').text('');
            return;
        }

        if (password === confirm) {
            matchText.removeClass('no-match').addClass('match').text('✓ Passwords match');
        } else {
            matchText.removeClass('match').addClass('no-match').text('✗ Passwords do not match');
        }
    }

    $(document).on('input', '#dtr-confirm-password', checkResetPasswordMatch);
    $(document).on('submit', '#dtr-reset-password-form', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = $('#dtr-reset-submit');
        const password = $('#dtr-new-password').val();
        const confirmPassword = $('#dtr-confirm-password').val();
        
        clearFormErrors(form);
        clearMessages();
        
        // Client-side validation
        if (password.length < 8) {
            showMessage('Password must be at least 8 characters long.', 'error', '#dtr-reset-messages');
            return;
        }
        
        if (password !== confirmPassword) {
            showMessage('Passwords do not match.', 'error', '#dtr-reset-messages');
            return;
        }
        
        setButtonLoading(submitBtn, true);
        
        $.ajax({
            url: dtr_login_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dtr_reset_password',
                key: $('input[name="key"]').val(),
                login: $('input[name="login"]').val(),
                new_password: password,
                confirm_password: confirmPassword,
                dtr_reset_nonce: $('input[name="dtr_reset_nonce"]').val()
            },
            success: function(response) {
                setButtonLoading(submitBtn, false);
                
                if (response.success) {
                    showMessage(response.data.message, 'success', '#dtr-reset-messages');
                    form[0].reset();
                    
                    setTimeout(() => {
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    }, 2000);
                } else {
                    showMessage(response.data.message, 'error', '#dtr-reset-messages');
                }
            },
            error: function() {
                setButtonLoading(submitBtn, false);
                showMessage('Something went wrong. Please try again.', 'error', '#dtr-reset-messages');
            }
        });
    });
    
    // Real-time form validation
    $(document).on('blur', '.dtr-form-input', function() {
        const field = $(this);
        const value = field.val().trim();
        
        // Email validation
        if (field.attr('type') === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                field.addClass('error');
                field.siblings('.dtr-form-error').text('Please enter a valid email address');
            } else {
                field.removeClass('error');
                field.siblings('.dtr-form-error').text('');
            }
        }
        
        // Required field validation
        if (field.prop('required') && !value) {
            field.addClass('error');
            field.siblings('.dtr-form-error').text('This field is required');
        } else if (field.attr('type') !== 'email') {
            field.removeClass('error');
            field.siblings('.dtr-form-error').text('');
        }
    });
    
    // Clear errors on input
    $(document).on('input', '.dtr-form-input', function() {
        const field = $(this);
        if (field.hasClass('error') && field.val().trim()) {
            field.removeClass('error');
            field.siblings('.dtr-form-error').text('');
        }
    });

    // Password strength checker for reset form
    $(document).on('input', '#dtr-new-password', function() {
        const password = $(this).val();
        const meter = $('#password-strength-meter');
        const text = $('#dtr-password-strength');

        if (password.length === 0) {
            text.text('').removeClass('weak medium strong');
            if (meter.length) meter[0].value = 0;
            checkResetPasswordMatch();
            return;
        }

        let strength = 0;
        let message = "";

        if (password.length < 8) {
            message = "At least 8 characters required";
            strength = 0;
        } else if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
            message = "Add a special character";
            strength = 1;
        } else if (!/[A-Z]/.test(password)) {
            message = "Add an uppercase letter";
            strength = 2;
        } else {
            message = "Strong password!";
            strength = 3;
        }

        // Update meter
        if (meter.length) meter[0].value = strength;
        
        // Update text
        text.text(message);
        text.removeClass('weak medium strong');
        if (strength <= 1) text.addClass('weak');
        else if (strength === 2) text.addClass('medium');
        else text.addClass('strong');

        // Check password matching
        checkResetPasswordMatch();
    });

    // Password confirmation checker for reset form
    function checkResetPasswordMatch() {
        const password = $('#dtr-new-password').val();
        const confirm = $('#dtr-confirm-password').val();
        const matchText = $('#password-match-text');

        if (!confirm) {
            matchText.removeClass('match no-match').text('');
            return;
        }

        if (password === confirm) {
            matchText.removeClass('no-match').addClass('match').text('✓ Passwords match');
        } else {
            matchText.removeClass('match').addClass('no-match').text('✗ Passwords do not match');
        }
    }

    $(document).on('input', '#dtr-confirm-password', checkResetPasswordMatch);
});