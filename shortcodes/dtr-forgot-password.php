<?php
if (!defined('ABSPATH')) exit;

// Shortcode: [dtr-forgot-password]
add_shortcode('dtr-forgot-password', function() {
    if (!is_user_logged_in()) return '<p>You must be logged in to change your password.</p>';
    $current_user = wp_get_current_user();
    $message = '';
    $error = '';

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

            // Clear authentication cookies
            wp_clear_auth_cookie();

            // Redirect to homepage
            wp_redirect(home_url());
            exit;
        }
    }

    ob_start();
    ?>
    <div id="dtr-password-error" class="error" style="display:none;"></div>
    <?php
    if ($message) {
        echo '<div class="updated" style="margin-bottom:1em;">' . esc_html($message) . '</div>';
    } elseif ($error) {
        echo '<div class="error" style="margin-bottom:1em;">' . esc_html($error) . '</div>';
    }
    ?>
    <form class="dtr-account-form dtr-password-form" method="post" action="">
        <input type="hidden" name="active_tab" value="tab-login-details">
        <section>
            <div class="dtr-form-group">
                <label class="dtr-form-label full-width">
                    New Password
                    <input type="password" name="new-password" id="new-password" class="dtr-form-input dtr-password-input" required minlength="6">
                </label>
                <label class="dtr-form-label full-width">
                    Confirm New Password
                    <input type="password" name="password-confirm" id="password-confirm" class="dtr-form-input dtr-password-input" required minlength="6">
                </label>
            </div>
            <button type="submit" class="dtr-input-button custom-btn-decorated dtr-password-submit" name="save_login_details">Save Changes</button>
        </section>
    </form>
    <script>
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
                errorMsg.style.display = 'none';
                btn.disabled = true;
                startedTyping = false;
                return;
            }
            startedTyping = true;

            if (!val1 || !val2) {
                error = 'Please fill out both password fields.';
            } else if (val1.length < 6) {
                error = 'Password must be at least 6 characters.';
            } else if (val1 !== val2) {
                error = 'Passwords do not match.';
            }

            if (error && startedTyping) {
                errorMsg.textContent = error;
                errorMsg.style.display = 'block';
                btn.disabled = true;
            } else {
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
            btn.textContent = 'Submitting' + '.'.repeat(dots);
        }

        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) return;
            
            btn.disabled = true;
            submitting = true;
            submitInterval = setInterval(animateSubmitting, 500);

            // Check for success/error message
            var checkCompletion = setInterval(function() {
                var successMsg = document.querySelector('.updated');
                if (successMsg) {
                    clearInterval(submitInterval);
                    clearInterval(checkCompletion);
                    btn.textContent = 'Settings Saved!';
                    setTimeout(function() {
                        btn.textContent = originalBtnText;
                        btn.disabled = false;
                    }, 2000);
                }
                
                var errorMsg = document.querySelector('.error');
                if (errorMsg && errorMsg.style.display !== 'none') {
                    clearInterval(submitInterval);
                    clearInterval(checkCompletion);
                    btn.textContent = originalBtnText;
                    btn.disabled = false;
                }
            }, 100);

            // Fallback timeout
            setTimeout(function() {
                if (submitting) {
                    clearInterval(submitInterval);
                    btn.disabled = false;
                    btn.textContent = originalBtnText;
                }
            }, 10000);
        });

        pw1.addEventListener('input', validatePasswords);
        pw2.addEventListener('input', validatePasswords);
        validatePasswords();
    });
    </script>
    <?php
    return ob_get_clean();
});