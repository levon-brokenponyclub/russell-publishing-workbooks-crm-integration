<?php
if (!defined('ABSPATH')) exit;

// Enqueue dynamic forms CSS for consistent styling
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'dtr-dynamic-forms-forgot-password', 
        plugin_dir_url(__FILE__) . '../assets/css/dynamic-forms.css', 
        array(),
        filemtime(plugin_dir_path(__FILE__) . '../assets/css/dynamic-forms.css'),
        'all'
    );
}, 999);

// Shortcode: [dtr-forgot-password]
add_shortcode('dtr-forgot-password', function() {
    if (!is_user_logged_in()) return '<p>You must be logged in to change your password.</p>';
    $current_user = wp_get_current_user();
    $message = '';
    $error = '';

    // Get form configuration from plugin settings
    $form_config = DTR_Workbooks_Integration::get_shortcode_form_config('forgot_password');
    $development_mode = $form_config['dev_mode'] ?? false;
    
    // Check if form is enabled (assuming forgot password form should always be available)
    // if (!$form_config['enabled']) {
    //     return '<div class="dtr-form-disabled">Password Reset form is currently disabled in plugin settings.</div>';
    // }

    // Handle POST (do NOT redirect)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_login_details'])) {
        $new_password = isset($_POST['new-password']) ? $_POST['new-password'] : '';
        $confirm_password = isset($_POST['password-confirm']) ? $_POST['password-confirm'] : '';
        
        if (empty($new_password)) {
            $error = 'Please enter a new password.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Update password
            wp_set_password($new_password, $current_user->ID);
            
            // Log out the user
            wp_logout();
            wp_clear_auth_cookie();

            // Redirect to login page instead of homepage
            wp_redirect(home_url('/login'));
            exit;
        }
    }

    ob_start();
    ?>
    <?php if ($development_mode): ?>
    <!-- Development Mode Indicator -->
    <div class="dev-mode-indicator active" id="devModeIndicator">
        🛠️ DEVELOPMENT MODE - Password Reset Testing Enabled
    </div>
    <?php endif; ?>
    
    <div class="form-container dtr-password-reset-container">
        <style>
            .dtr-password-reset-container {
                max-width: 500px;
                margin: 2rem auto;
                padding: 2rem;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            
            .dtr-password-reset-container h2 {
                color: #2d3748;
                margin-bottom: 0.5rem;
                font-size: 1.5rem;
                font-weight: 600;
            }
            
            .dtr-password-reset-container .description {
                color: #666;
                margin-bottom: 2rem;
                line-height: 1.5;
            }
            
            .dtr-password-reset-container .dtr-input-button {
                width: 100%;
                padding: 12px 24px;
                background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                margin-top: 1rem;
            }
            
            .dtr-password-reset-container .dtr-input-button:hover {
                background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%);
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
            }
            
            .dtr-password-reset-container .dtr-input-button:disabled {
                background: #ccc;
                cursor: not-allowed;
                transform: none;
                box-shadow: none;
            }
            
            .error, .updated {
                padding: 12px 16px;
                border-radius: 4px;
                margin-bottom: 1rem;
                font-weight: 500;
            }
            
            .error {
                background: #fee;
                border: 1px solid #fcc;
                color: #c33;
            }
            
            .updated {
                background: #efe;
                border: 1px solid #cfc;
                color: #363;
            }
        </style>
        
        <div id="dtr-password-error" class="error" style="display:none;"></div>
        <?php
        if ($message) {
            echo '<div class="updated" style="margin-bottom:1em;">' . esc_html($message) . '</div>';
        } elseif ($error) {
            echo '<div class="error" style="margin-bottom:1em;">' . esc_html($error) . '</div>';
        }
        ?>
        
        <h2>Reset Your Password</h2>
        <p class="description">Enter your new password below. You will be logged out and redirected to the login page after successfully changing your password.</p>
        
        <form class="dtr-account-form dtr-password-form" method="post" action="">
            <input type="hidden" name="active_tab" value="tab-login-details">
            
            <section>
                <div class="form-row">
                    <div class="form-field floating-label">
                        <div class="password-field">
                            <input type="password" name="new-password" id="new-password" class="dtr-form-input dtr-password-input" required minlength="6" placeholder=" " uuid="<?php echo wp_generate_uuid4(); ?>">
                            <span class="password-toggle" onclick="togglePassword('new-password')">👁</span>
                            <meter max="3" id="password-strength-meter"></meter>
                        </div>
                        <label for="new-password">New Password <span class="required">*</span></label>
                        <p id="password-strength-text"></p>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field floating-label">
                        <div class="password-field">
                            <input type="password" name="password-confirm" id="password-confirm" class="dtr-form-input dtr-password-input" required minlength="6" placeholder=" " uuid="<?php echo wp_generate_uuid4(); ?>">
                            <span class="password-toggle" onclick="togglePassword('password-confirm')">👁</span>
                        </div>
                        <label for="password-confirm">Confirm New Password <span class="required">*</span></label>
                        <p id="password-match-text" class="password-match-feedback"></p>
                    </div>
                </div>
                
                <div class="form-row">
                    <button type="submit" class="dtr-input-button custom-btn-decorated dtr-password-submit" name="save_login_details">Reset Password</button>
                </div>
                
                <?php if ($development_mode): ?>
                <!-- Development Mode Tools -->
                <div style="margin-top: 15px; text-align: center; padding: 10px; background: rgba(255, 193, 7, 0.1); border-radius: 4px;">
                    <button type="button" onclick="fillTestPassword()" class="button" style="background: #28a745; color: white; padding: 8px 16px; font-size: 12px; border-radius: 4px; margin-right: 10px;">
                        📝 Fill Test Password
                    </button>
                    <small style="color: #666;">Development mode - password requirements relaxed for testing</small>
                </div>
                <?php endif; ?>
            </section>
        </form>
    </div>
    <script>
        // Development mode is controlled by plugin settings
        let devModeActive = <?php echo $development_mode ? 'true' : 'false'; ?>;

        // Password strength checker
        var strength = {
            0: "Weak",
            1: "Weak", 
            2: "Good",
            3: "Strong"
        };

        // Initialize password strength monitoring
        function initPasswordStrength() {
            var password = document.getElementById('new-password');
            var meter = document.getElementById('password-strength-meter');
            var text = document.getElementById('password-strength-text');

            if (password && meter && text) {
                password.addEventListener('input', function() {
                    var val = password.value;
                    var score = 0;
                    var message = "";
                    
                    if (val.length === 0) {
                        text.innerHTML = "";
                        meter.value = 0;
                        checkPasswordMatch();
                        return;
                    }
                    
                    // Check requirements and build message
                    if (val.length < 6) {
                        message = "At least six characters";
                        score = 0;
                    } else if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(val)) {
                        message = "At least one special character";
                        score = 1;
                    } else if (!/[A-Z]/.test(val)) {
                        message = "At least one uppercase character";
                        score = 2;
                    } else {
                        // All requirements met
                        score = 3;
                        message = "";
                    }
                    
                    // In development mode, make passwords less strict
                    if (devModeActive && val.length >= 6) {
                        score = Math.max(score, 2); // Always at least "Good" in dev mode
                    }
                    
                    // Update the password strength meter
                    meter.value = score;
                   
                    // Update the text indicator
                    if (message && !devModeActive) {
                        text.innerHTML = message;
                    } else {
                        text.innerHTML = "Strength: " + "<strong>" + strength[score] + "</strong>" + 
                            (devModeActive ? " <small>(Dev Mode)</small>" : "");
                    }
                    
                    // Check password matching whenever password changes
                    checkPasswordMatch();
                });
            }
        }

        // Password matching checker
        function initPasswordMatch() {
            var password = document.getElementById('new-password');
            var confirmPassword = document.getElementById('password-confirm');
            var matchText = document.getElementById('password-match-text');

            if (password && confirmPassword && matchText) {
                confirmPassword.addEventListener('input', checkPasswordMatch);
                password.addEventListener('input', checkPasswordMatch);
            }
        }

        function checkPasswordMatch() {
            var password = document.getElementById('new-password');
            var confirmPassword = document.getElementById('password-confirm');
            var matchText = document.getElementById('password-match-text');

            if (password && confirmPassword && matchText) {
                if (confirmPassword.value === '') {
                    matchText.className = 'password-match-feedback';
                    matchText.innerHTML = '';
                } else if (password.value === confirmPassword.value) {
                    matchText.className = 'password-match-feedback match';
                    matchText.innerHTML = '✓ Passwords match';
                } else {
                    matchText.className = 'password-match-feedback no-match';
                    matchText.innerHTML = '✗ Passwords do not match';
                }
            }
        }

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                button.textContent = '🙈';
            } else {
                field.type = 'password';
                button.textContent = '👁';
            }
        }

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
            
            if (hasValue || isFocused) {
                fieldContainer.classList.add('floating-active');
            } else {
                fieldContainer.classList.remove('floating-active');
            }
        }

        // Development mode test function
        function fillTestPassword() {
            document.getElementById('new-password').value = 'TestPassword123!';
            document.getElementById('password-confirm').value = 'TestPassword123!';
            
            // Trigger floating label and validation updates
            initFloatingLabels();
            checkPasswordMatch();
            
            // Trigger password strength check
            const passwordField = document.getElementById('new-password');
            if (passwordField) {
                passwordField.dispatchEvent(new Event('input'));
            }
            
            alert('✅ Test password filled in! (TestPassword123!)');
        }

        // Enhanced form validation and submission
        document.addEventListener('DOMContentLoaded', function() {
            var form = document.querySelector('.dtr-password-form');
            if (!form) return;
            
            var pw1 = form.querySelector('#new-password');
            var pw2 = form.querySelector('#password-confirm');
            var btn = form.querySelector('.dtr-password-submit');
            var errorMsg = document.getElementById('dtr-password-error');
            var startedTyping = false;

            function validatePasswords() {
                var val1 = pw1.value;
                var val2 = pw2.value;
                var error = '';

                if (!val1 && !val2) {
                    if (errorMsg) errorMsg.style.display = 'none';
                    btn.disabled = true;
                    startedTyping = false;
                    return;
                }
                startedTyping = true;

                if (!val1 || !val2) {
                    error = 'Please fill out both password fields.';
                } else if (!devModeActive && val1.length < 6) {
                    error = 'Password must be at least 6 characters.';
                } else if (val1 !== val2) {
                    error = 'Passwords do not match.';
                }

                if (error && startedTyping && errorMsg) {
                    errorMsg.textContent = error;
                    errorMsg.style.display = 'block';
                    btn.disabled = true;
                } else if (errorMsg) {
                    errorMsg.textContent = '';
                    errorMsg.style.display = 'none';
                    btn.disabled = false;
                }
            }

            // Button animation
            var originalBtnText = btn.textContent;
            var dots = 0;
            var submitting = false;
            var submitInterval;

            function animateSubmitting() {
                dots = (dots + 1) % 4;
                btn.textContent = 'Resetting Password' + '.'.repeat(dots);
            }

            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) return;
                
                // Skip validation in development mode
                if (devModeActive) {
                    console.log('🛠️ Development mode: Skipping strict validation');
                } else if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
                
                btn.disabled = true;
                submitting = true;
                submitInterval = setInterval(animateSubmitting, 500);

                // Check for success/error message
                var checkCompletion = setInterval(function() {
                    var successMsg = document.querySelector('.updated');
                    if (successMsg) {
                        clearInterval(submitInterval);
                        clearInterval(checkCompletion);
                        btn.textContent = 'Password Reset! Redirecting...';
                        setTimeout(function() {
                            window.location.href = '/login';
                        }, 2000);
                        return;
                    }
                    
                    var errorMsg = document.querySelector('.error');
                    if (errorMsg && errorMsg.style.display !== 'none') {
                        clearInterval(submitInterval);
                        clearInterval(checkCompletion);
                        btn.textContent = originalBtnText;
                        btn.disabled = false;
                        submitting = false;
                    }
                }, 100);

                // Fallback timeout
                setTimeout(function() {
                    if (submitting) {
                        clearInterval(submitInterval);
                        btn.disabled = false;
                        btn.textContent = originalBtnText;
                        submitting = false;
                    }
                }, 10000);
            });

            function validateForm() {
                var pw1Value = pw1.value;
                var pw2Value = pw2.value;
                
                if (!pw1Value || !pw2Value) {
                    alert('Please fill out both password fields.');
                    return false;
                }
                
                if (pw1Value.length < 6) {
                    alert('Password must be at least 6 characters.');
                    return false;
                }
                
                if (pw1Value !== pw2Value) {
                    alert('Passwords do not match.');
                    return false;
                }
                
                return true;
            }

            pw1.addEventListener('input', validatePasswords);
            pw2.addEventListener('input', validatePasswords);
            validatePasswords();

            // Initialize all components
            initFloatingLabels();
            initPasswordStrength();
            initPasswordMatch();
            
            console.log('%cPassword Reset Form Ready', 'background: #4CAF50; color: white; padding: 8px 12px; border-radius: 4px; font-weight: bold;');
            console.log('Development Mode:', devModeActive ? 'ON 🛠️' : 'OFF 🟢');
        });
    </script>
    <?php
    return ob_get_clean();
});