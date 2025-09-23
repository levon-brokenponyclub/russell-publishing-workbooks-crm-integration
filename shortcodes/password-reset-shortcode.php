<?php
if (!defined('ABSPATH')) exit;

// Add shortcode for password reset form
add_shortcode('reset_password_form', 'dtr_reset_password_form_shortcode');

// Enqueue styles for the reset form
add_action('wp_enqueue_scripts', function() {
    // Check if we're on a page that might use the reset password shortcode
    global $post;
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'reset_password_form') || isset($_GET['key']))) {
        wp_enqueue_style(
            'dtr-password-reset-form',
            plugin_dir_url(__FILE__) . '../assets/css/membership-registration-form.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . '../assets/css/membership-registration-form.css'),
            'all'
        );
    }
}, 10);

function dtr_reset_password_form_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'title' => 'Reset Your Password',
        'description' => 'Enter your new password below to complete the reset process.',
    ), $atts, 'reset_password_form');

    // Check if we have the required parameters from the email link
    $reset_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
    $user_login = isset($_GET['login']) ? sanitize_text_field($_GET['login']) : '';

    ob_start();
    
    // If no key or login provided, show error
    if (empty($reset_key) || empty($user_login)) {
        ?>
        <div class="password-reset-container error-state">
            <div class="reset-form-wrapper">
                <h2>Invalid Reset Link</h2>
                <div class="error-message">
                    <p>This password reset link is invalid or has expired.</p>
                    <p>Please <a href="<?php echo wp_lostpassword_url(); ?>">request a new password reset link</a>.</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // Verify the reset key
    $user = check_password_reset_key($reset_key, $user_login);
    if (is_wp_error($user)) {
        ?>
        <div class="password-reset-container error-state">
            <div class="reset-form-wrapper">
                <h2>Reset Link Expired</h2>
                <div class="error-message">
                    <p>This password reset link has expired or is invalid.</p>
                    <p>Please <a href="<?php echo wp_lostpassword_url(); ?>">request a new password reset link</a>.</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    ?>
    <div class="password-reset-container">
        <div class="reset-form-wrapper">
            <h2><?php echo esc_html($atts['title']); ?></h2>
            <p class="reset-description"><?php echo esc_html($atts['description']); ?></p>
            
            <div id="reset-messages" class="reset-messages" style="display: none;"></div>
            
            <form id="passwordResetForm" class="password-reset-form">
                <div class="form-row">
                    <div class="form-field floating-label">
                        <div class="password-field">
                            <input type="password" id="newPassword" required minlength="6" placeholder=" ">
                            <span class="password-toggle" onclick="togglePasswordVisibility('newPassword')">👁</span>
                            <meter max="3" id="password-strength-meter"></meter>
                        </div>
                        <label for="newPassword">New Password <span class="required">*</span></label>
                        <p id="password-strength-text" class="password-strength-feedback"></p>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field floating-label">
                        <div class="password-field">
                            <input type="password" id="confirmPassword" required placeholder=" ">
                            <span class="password-toggle" onclick="togglePasswordVisibility('confirmPassword')">👁</span>
                        </div>
                        <label for="confirmPassword">Confirm New Password <span class="required">*</span></label>
                        <p id="password-match-text" class="password-match-feedback"></p>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <button type="submit" class="button btn-small global btn-rounded btn-blue shimmer-effect shimmer-slow text-left chevron right">
                            Reset Password
                        </button>
                    </div>
                </div>

                <!-- Hidden fields -->
                <input type="hidden" id="resetKey" value="<?php echo esc_attr($reset_key); ?>">
                <input type="hidden" id="userLogin" value="<?php echo esc_attr($user_login); ?>">
            </form>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="form-loader-overlay" id="resetLoaderOverlay" style="display: none;">
        <div class="loader-content">
            <h2>Resetting Your Password</h2>
            <div class="loader-spinner">
                <div class="progress-circle">
                    <div class="progress-circle-fill progress-100"></div>
                </div>
                <div class="loader-icon"><i class="fa-light fa-lock"></i></div>
            </div>
            <p id="resetStatusText">Processing your request...</p>
        </div>
    </div>

    <style>
    .password-reset-container {
        max-width: 500px;
        margin: 40px auto;
        padding: 0 20px;
    }

    .reset-form-wrapper {
        background: #fff;
        padding: 40px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        border: 1px solid #e1e4e7;
    }

    .reset-form-wrapper h2 {
        text-align: center;
        margin-bottom: 10px;
        color: #23282d;
        font-size: 28px;
        font-weight: 600;
    }

    .reset-description {
        text-align: center;
        margin-bottom: 30px;
        color: #666;
        font-size: 16px;
        line-height: 1.5;
    }

    .password-reset-form .form-row {
        margin-bottom: 25px;
    }

    .reset-messages {
        margin-bottom: 20px;
        padding: 15px;
        border-radius: 4px;
        font-weight: 500;
    }

    .reset-messages.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .reset-messages.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .error-state .reset-form-wrapper {
        text-align: center;
    }

    .error-message {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
        padding: 20px;
        border-radius: 4px;
        margin-top: 20px;
    }

    .error-message a {
        color: #0073aa;
        text-decoration: underline;
    }

    .password-strength-feedback,
    .password-match-feedback {
        margin-top: 8px;
        font-size: 13px;
        min-height: 18px;
    }

    .password-match-feedback.match {
        color: #00a32a;
        font-weight: 500;
    }

    .password-match-feedback.no-match {
        color: #d63638;
        font-weight: 500;
    }

    /* Password strength meter styling */
    #password-strength-meter {
        width: 100%;
        height: 4px;
        margin-top: 8px;
        border: none;
        border-radius: 2px;
    }

    #password-strength-meter::-webkit-meter-bar {
        background: #eee;
        border-radius: 2px;
    }

    #password-strength-meter::-webkit-meter-optimum-value {
        background: #00a32a;
        border-radius: 2px;
    }

    #password-strength-meter::-webkit-meter-suboptimum-value {
        background: #ffb900;
        border-radius: 2px;
    }

    #password-strength-meter::-webkit-meter-even-less-good-value {
        background: #d63638;
        border-radius: 2px;
    }
    </style>

    <script>
        // Password strength checker
        const strength = {
            0: "Weak",
            1: "Weak", 
            2: "Good",
            3: "Strong"
        };

        function togglePasswordVisibility(fieldId) {
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

        function initPasswordStrength() {
            const password = document.getElementById('newPassword');
            const meter = document.getElementById('password-strength-meter');
            const text = document.getElementById('password-strength-text');

            if (password && meter && text) {
                password.addEventListener('input', function() {
                    const val = password.value;
                    let score = 0;
                    let message = "";
                    
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
                    
                    // Update the password strength meter
                    meter.value = score;
                   
                    // Update the text indicator
                    if (message) {
                        text.innerHTML = message;
                        text.style.color = '#d63638';
                    } else {
                        text.innerHTML = "Strength: " + "<strong>" + strength[score] + "</strong>";
                        text.style.color = '#00a32a';
                    }
                    
                    // Check password matching whenever password changes
                    checkPasswordMatch();
                });
            }
        }

        function initPasswordMatch() {
            const password = document.getElementById('newPassword');
            const confirmPassword = document.getElementById('confirmPassword');
            const matchText = document.getElementById('password-match-text');

            if (password && confirmPassword && matchText) {
                confirmPassword.addEventListener('input', checkPasswordMatch);
                password.addEventListener('input', checkPasswordMatch);
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('newPassword');
            const confirmPassword = document.getElementById('confirmPassword');
            const matchText = document.getElementById('password-match-text');

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

        function showResetLoader() {
            const loadingOverlay = document.getElementById('resetLoaderOverlay');
            const statusText = document.getElementById('resetStatusText');
            
            if (loadingOverlay) {
                loadingOverlay.style.display = 'flex';
                statusText.textContent = 'Processing your request...';
                
                // Set header z-index to ensure overlay appears above it
                const header = document.querySelector('header');
                if (header) {
                    header.style.zIndex = '2';
                }
            }
        }

        function hideResetLoader() {
            const loadingOverlay = document.getElementById('resetLoaderOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
                
                // Restore header z-index
                const header = document.querySelector('header');
                if (header) {
                    header.style.zIndex = '';
                }
            }
        }

        function showMessage(message, type = 'success') {
            const messagesDiv = document.getElementById('reset-messages');
            messagesDiv.className = `reset-messages ${type}`;
            messagesDiv.innerHTML = message;
            messagesDiv.style.display = 'block';
            
            // Scroll to top of form to show message
            messagesDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function validateForm() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (!newPassword || newPassword.length < 6) {
                showMessage('Password must be at least 6 characters long.', 'error');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                showMessage('Passwords do not match.', 'error');
                return false;
            }
            
            return true;
        }

        // Initialize floating labels
        function initFloatingLabels() {
            const floatingFields = document.querySelectorAll('.floating-label input');
            
            floatingFields.forEach(field => {
                function updateLabel() {
                    const fieldContainer = field.closest('.floating-label');
                    const hasValue = field.value && field.value.trim() !== '';
                    const isFocused = document.activeElement === field;
                    
                    if (hasValue || isFocused) {
                        fieldContainer.classList.add('floating-active');
                    } else {
                        fieldContainer.classList.remove('floating-active');
                    }
                }
                
                // Set initial state
                updateLabel();
                
                // Handle events
                field.addEventListener('focus', updateLabel);
                field.addEventListener('blur', updateLabel);
                field.addEventListener('input', updateLabel);
            });
        }

        // Handle form submission
        document.getElementById('passwordResetForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return;
            }
            
            showResetLoader();
            
            const formData = new FormData();
            formData.append('action', 'dtr_reset_password');
            formData.append('new_password', document.getElementById('newPassword').value);
            formData.append('reset_key', document.getElementById('resetKey').value);
            formData.append('user_login', document.getElementById('userLogin').value);
            formData.append('nonce', '<?php echo wp_create_nonce('dtr_reset_password'); ?>');
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideResetLoader();
                
                if (data.success) {
                    showMessage('Your password has been successfully reset! You will be redirected to the login page.', 'success');
                    
                    // Redirect to login page after 3 seconds
                    setTimeout(() => {
                        window.location.href = '<?php echo wp_login_url(); ?>';
                    }, 3000);
                } else {
                    showMessage(data.data || 'An error occurred. Please try again.', 'error');
                }
            })
            .catch(error => {
                hideResetLoader();
                showMessage('An error occurred. Please try again.', 'error');
                console.error('Reset error:', error);
            });
        });

        // Initialize everything
        document.addEventListener('DOMContentLoaded', function() {
            initFloatingLabels();
            initPasswordStrength();
            initPasswordMatch();
        });

        console.log('%cPassword Reset Form Ready', 'background: #4CAF50; color: white; padding: 8px 12px; border-radius: 4px; font-weight: bold;');
    </script>
    <?php
    return ob_get_clean();
}

// AJAX handler for password reset
add_action('wp_ajax_dtr_reset_password', 'dtr_handle_password_reset');
add_action('wp_ajax_nopriv_dtr_reset_password', 'dtr_handle_password_reset');

function dtr_handle_password_reset() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'dtr_reset_password')) {
        wp_send_json_error('Security verification failed.');
        return;
    }
    
    $new_password = sanitize_text_field($_POST['new_password']);
    $reset_key = sanitize_text_field($_POST['reset_key']);
    $user_login = sanitize_text_field($_POST['user_login']);
    
    // Validate input
    if (empty($new_password) || empty($reset_key) || empty($user_login)) {
        wp_send_json_error('Missing required information.');
        return;
    }
    
    if (strlen($new_password) < 6) {
        wp_send_json_error('Password must be at least 6 characters long.');
        return;
    }
    
    // Verify the reset key again
    $user = check_password_reset_key($reset_key, $user_login);
    if (is_wp_error($user)) {
        wp_send_json_error('Invalid or expired reset key.');
        return;
    }
    
    // Reset the password
    reset_password($user, $new_password);
    
    // Log the password reset
    error_log("DTR: Password reset successful for user: {$user_login}");
    
    wp_send_json_success('Password reset successfully.');
}

// Override default password reset email link
add_filter('retrieve_password_message', 'dtr_custom_reset_password_email_link', 10, 4);

function dtr_custom_reset_password_email_link($message, $key, $user_login, $user_data) {
    // Change this URL to your custom reset page
    $reset_page = site_url('/reset-password/');

    // Build custom reset link
    $reset_link = add_query_arg(array(
        'key'   => $key,
        'login' => rawurlencode($user_login),
    ), $reset_page);

    // Create custom email message
    $message = "Hi " . $user_data->display_name . ",\n\n";
    $message .= "Someone has requested a password reset for your account on " . get_bloginfo('name') . ".\n\n";
    $message .= "If this was you, click the link below to reset your password:\n\n";
    $message .= $reset_link . "\n\n";
    $message .= "If you didn't request this password reset, you can safely ignore this email. Your password will not be changed.\n\n";
    $message .= "This link will expire in 24 hours for security reasons.\n\n";
    $message .= "Best regards,\n";
    $message .= "The " . get_bloginfo('name') . " Team";

    return $message;
}

// Optional: Change subject line too
add_filter('retrieve_password_title', 'dtr_custom_reset_password_subject', 10, 3);

function dtr_custom_reset_password_subject($subject, $user_login, $user_data) {
    return 'Reset Your Password - ' . get_bloginfo('name');
}
?>