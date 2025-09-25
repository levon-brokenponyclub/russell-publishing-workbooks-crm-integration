<?php
/**
 * Login and Forgot Password Shortcode
 * 
 * Provides comprehensive login functionality with modern styling
 * Based on Codyhouse design patterns
 * 
 * Shortcodes:
 * [login_form] - Login form with forgot password link
 * [reset_password_form] - Password reset form
 */

if (!defined('ABSPATH')) {
    exit;
}

class DTR_Login_Forgot_Password_Shortcode {
    
    public function __construct() {
        add_shortcode('login_form', array($this, 'render_login_form'));
        add_shortcode('reset_password_form', array($this, 'render_reset_password_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_nopriv_dtr_user_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_dtr_forgot_password', array($this, 'handle_forgot_password'));
        add_action('wp_ajax_nopriv_dtr_reset_password', array($this, 'handle_reset_password'));
        add_action('wp_ajax_dtr_change_password', array($this, 'handle_change_password'));
        
        // Override WordPress password reset email
        add_filter('retrieve_password_message', array($this, 'custom_reset_password_email'), 10, 4);
        add_filter('retrieve_password_title', array($this, 'custom_reset_password_subject'), 10, 3);
    }
    
    public function enqueue_scripts() {
        // Enqueue dynamic forms CSS first
        wp_enqueue_style(
            'dtr-dynamic-forms-login',
            plugin_dir_url(__FILE__) . '../assets/css/dynamic-forms.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . '../assets/css/dynamic-forms.css'),
            'all'
        );
        
        wp_enqueue_script('dtr-login-form', plugin_dir_url(__FILE__) . '../assets/js/login-form.js', array('jquery'), '1.0.0', true);
        wp_localize_script('dtr-login-form', 'dtr_login_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dtr_login_nonce'),
            'reset_password_url' => site_url('/reset-password/')
        ));
    }
    
    /**
     * Render Login Form Shortcode
     */
    public function render_login_form($atts) {
        // Don't show login form if user is already logged in
        if (is_user_logged_in()) {
            return '<div class="login-message">You are already logged in. <a href="' . wp_logout_url(home_url()) . '">Logout</a></div>';
        }
        
        $atts = shortcode_atts(array(
            'redirect' => '',
            'title' => 'Login',
            'subtitle' => 'Please sign in to your account'
        ), $atts, 'login_form');
        
        ob_start();
        ?>
        <div class="dtr-login-container">
            <div class="dtr-login-form-wrapper">
                <!-- <div class="dtr-login-header">
                    <h1 class="dtr-login-title"><?php echo esc_html($atts['title']); ?></h1>
                    <p class="dtr-login-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
                </div> -->
                
                <form class="dtr-login-form form-container" id="dtr-login-form" method="post">
                    <?php wp_nonce_field('dtr_login_nonce', 'dtr_login_nonce'); ?>
                    
                    <div class="form-row">
                        <div class="form-field floating-label">
                            <input type="text" id="dtr-username" name="username" required placeholder=" ">
                            <label for="dtr-username">Username or Email <span class="required">*</span></label>
                            <span class="dtr-form-error" id="username-error"></span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field floating-label">
                            <div class="password-field">
                                <input type="password" id="dtr-password" name="password" required placeholder=" ">
                                <span class="password-toggle" onclick="toggleLoginPassword('dtr-password')">👁</span>
                            </div>
                            <label for="dtr-password">Password <span class="required">*</span></label>
                            <span class="dtr-form-error" id="password-error"></span>
                        </div>
                    </div>
                    
                    <div class="dtr-form-options">
                        <label class="dtr-checkbox-wrapper">
                            <input type="checkbox" name="remember" value="1">
                            <span class="dtr-checkbox-mark"></span>
                            Remember me
                        </label>
                        
                        <a href="#" class="dtr-forgot-password-link" id="dtr-forgot-password-trigger">
                            Forgot password?
                        </a>
                    </div>
                    
                    <button type="submit" class="dtr-login-btn" id="dtr-login-submit">
                        <span class="button global btn-medium btn-rounded btn-purple shimmer-effect shimmer-slow is-toggle text-left chevron right" style="width:100% !important;line-height:1.6 !important;">Sign In</span>
                    </button>
                    <p style="text-align: center;margin-top:20px;">Don't have an account? <a href="<?php echo site_url('/free-membership/'); ?>" class="dtr-login-link" style="color:#009fe3;">Become a Member</a></p>
                    
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect']); ?>">
                </form>
                
                <!-- Forgot Password Form (Initially Hidden) -->
                <form class="dtr-forgot-password-form form-container" id="dtr-forgot-password-form" style="display: none;" method="post">
                    <?php wp_nonce_field('dtr_login_nonce', 'dtr_forgot_nonce'); ?>
                    
                    <div class="dtr-forgot-header">
                        <button type="button" class="dtr-back-btn" id="dtr-back-to-login">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Back to login
                        </button>
                        <h2 class="dtr-forgot-title">Reset Password</h2>
                        <p class="dtr-forgot-subtitle">Enter your email address and we'll send you a link to reset your password.</p>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field floating-label">
                            <input type="email" id="dtr-forgot-email" name="user_email" required placeholder=" ">
                            <label for="dtr-forgot-email">Email Address <span class="required">*</span></label>
                            <span class="dtr-form-error" id="forgot-email-error"></span>
                        </div>
                    </div>
                    
                    <button type="submit" class="dtr-forgot-btn" id="dtr-forgot-submit">
                        <span class="button global btn-medium btn-rounded btn-blue shimmer-effect shimmer-slow is-toggle text-left chevron right" style="width:100% !important;line-height:1.6 !important;">Send Reset Link</span>
                    </button>
                </form>
                
                <div class="dtr-form-messages" id="dtr-form-messages"></div>
            </div>
        </div>
        
        <style>
        /* Import Dynamic Forms Styling */
        
        /* Global placeholder styling */
        ::placeholder {
            color: #333 !important;
        }

        /* Form row and field layout */
        .form-row {
            display: flex !important;
            gap: 1rem !important;
            margin-bottom: 0 !important;
        }

        .form-field {
            flex: 1 !important;
        }

        .form-field.full-width {
            width: 100% !important;
        }

        /* Basic label styling */
        .form-field label {
            display: block !important;
            margin-bottom: 0.5rem !important;
            font-weight: bold !important;
            color: #2d3748 !important;
        }

        /* Global input and select styling */
        .form-container input[type='text'], 
        .form-container input[type='password'], 
        .form-container input[type='email'], 
        .form-container input[type='number'], 
        .form-container input[type='tel'], 
        .form-container input[type='search'], 
        .form-container select, 
        .form-container textarea {
            background-color: #f2f2f2a6 !important;
            box-shadow: none !important;
            border-radius: 5px !important;
            font-family: "Titillium Web", Sans-serif !important;
            min-height: 50px !important;
            color: #333333 !important;
            border-color: #99a2a899 !important;
            font-size: 15px !important;
        }

        /* Standard form field inputs */
        .form-field input,
        .form-field select {
            width: 100% !important;
            padding: 0.75rem !important;
            border: 1px solid #cbd5e0 !important;
            border-radius: 4px !important;
            font-size: 1rem !important;
            transition: border-color 0.2s ease !important;
            font-size:15px !important;
        }

        .form-field input:focus,
        .form-field select:focus {
            outline: none !important;
            border-color: #4caf50 !important;
        }

        /* Floating Label Styles */
        .form-field.floating-label {
            position: relative !important;
        }

        .form-field.floating-label label {
            position: absolute !important;
            left: 1rem !important;
            top: 0.6rem !important;
            margin-bottom: 0 !important;
            padding: 0.10rem 0.2rem !important;
            color: #333 !important;
            font-size: 15px !important;
            font-weight: 400 !important;
            transition: all 0.2s ease-in-out !important;
            pointer-events: none !important;
            z-index: 1 !important;
        }

        .form-field.floating-label input,
        .form-field.floating-label select {
            padding: 0.5rem 0.75rem 0.5rem 0.75rem !important;
        }

        .form-field.floating-label input:focus + label,
        .form-field.floating-label input:not(:placeholder-shown) + label,
        .form-field.floating-label input[value]:not([value=""]) + label,
        .form-field.floating-label select:focus + label,
        .form-field.floating-label select:not([value=""]) + label,
        .form-field.floating-label.floating-active label {
            top: -0.6rem !important;
            font-size: 0.6rem !important;
            color: #fff !important;
            font-weight: 600 !important;
            background: #4caf50 !important;
            border-radius: 4px !important;
            padding: 0.10rem 0.3rem !important;
        }

        /* Password field specific styles */
        .password-field {
            position: relative !important;
            width: 100% !important;
            border-radius: 4px !important;
            overflow: hidden !important;
        }

        .password-toggle {
            position: absolute !important;
            right: 10px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            cursor: pointer !important;
            color: #4a5568 !important;
            z-index: 2 !important;
        }

        /* Adjust password toggle for floating labels */
        .form-field.floating-label .password-field {
            width: 100% !important;
        }

        .form-field.floating-label .password-field input:focus + .password-toggle,
        .form-field.floating-label .password-field input:not(:placeholder-shown) + .password-toggle {
            top: calc(50% - 0.25rem) !important;
        }

        .form-field.floating-label .password-field input:focus ~ label,
        .form-field.floating-label .password-field input:not(:placeholder-shown) ~ label,
        .form-field.floating-label .password-field input[value]:not([value=""]) ~ label,
        .form-field.floating-label.floating-active .password-field + label {
            top: -0.5rem !important;
            font-size: 0.6rem !important;
            color: #4caf50 !important;
            font-weight: 600 !important;
            color:#fff !important;
        }

        /* Required asterisk styling */
        .form-field.floating-label label .required,
        .required {
            color: #e53e3e !important;
        }

        /* Password strength meter */
        meter {
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            appearance: none !important;
            margin: 0 auto 0 !important;
            width: 100% !important;
            height: 4px !important;
            background-color: transparent !important;
            margin-top: -4px !important;
            padding: 0 !important;
            position: relative !important;
            border-radius: 0 0 4px 4px !important;
            display: block !important;
            line-height: 0 !important;
        }

        meter::-webkit-meter-bar {
            background: none !important;
            background-color: transparent !important;
            height: 4px !important;
        }

        meter[value="0"]::-webkit-meter-optimum-value { background: red !important; }
        meter[value="1"]::-webkit-meter-optimum-value { background: red !important; }
        meter[value="2"]::-webkit-meter-optimum-value { background: orange !important; }
        meter[value="3"]::-webkit-meter-optimum-value { background: #4caf50 !important; }

        meter[value="0"]::-moz-meter-bar { background: red !important; }
        meter[value="1"]::-moz-meter-bar { background: red !important; }
        meter[value="2"]::-moz-meter-bar { background: orange !important; }
        meter[value="3"]::-moz-meter-bar { background: #4caf50 !important; }

        .dtr-password-strength {
            font-size: 13px !important;
            top: 7px !important;
            position: relative !important;
        }

        /* Password Match Feedback */
        .password-match-feedback {
            margin-top: 0.5rem !important;
            font-size: 0.9rem !important;
            padding: 0.5rem !important;
            border-radius: 4px !important;
            display: none !important;
        }

        .password-match-feedback.match {
            background-color: #d4edda !important;
            color: #155724 !important;
            border: 1px solid #c3e6cb !important;
            display: block !important;
        }

        .password-match-feedback.no-match {
            background-color: #f8d7da !important;
            color: #721c24 !important;
            border: 1px solid #f5c6cb !important;
            display: block !important;
        }

        /* Login form specific styling */
        .dtr-login-container {
            max-width: 100%;
        }
        
        .dtr-login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .dtr-login-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 0.5rem 0;
            line-height: 1.2;
        }
        
        .dtr-login-subtitle {
            color: #6b7280;
            margin: 0;
            font-size: 0.875rem;
        }

        .dtr-form-error {
            display: block;
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            min-height: 1rem;
        }
        
        .dtr-form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }
        
        .dtr-checkbox-wrapper {
            display: flex;
            align-items: center;
            cursor: pointer;
            color: #374151;
        }
        
        .dtr-checkbox-wrapper input[type="checkbox"] {
            display: none;
        }
        
        .dtr-checkbox-mark {
            width: 16px;
            height: 16px;
            border: 2px solid #d1d5db;
            border-radius: 4px;
            margin-right: 0.5rem;
            position: relative;
            transition: all 0.15s ease;
        }
        
        .dtr-checkbox-wrapper input[type="checkbox"]:checked + .dtr-checkbox-mark {
            background: #3b82f6;
            border-color: #3b82f6;
        }
        
        .dtr-checkbox-wrapper input[type="checkbox"]:checked + .dtr-checkbox-mark::after {
            content: '';
            position: absolute;
            left: 3px;
            top: 0px;
            width: 6px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        .dtr-forgot-password-link {
            color: #871f82;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.15s ease;
        }
        
        .dtr-forgot-password-link:hover {
            color: #5b1360;
        }
        
        .dtr-login-btn, .dtr-forgot-btn, .dtr-reset-btn {
            width: 100%;
            background-color:transparent;
            position: relative;
        }
        
        .dtr-login-btn:disabled, .dtr-forgot-btn:disabled, .dtr-reset-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Button Loading States */
        .dtr-login-btn.btn-loading, .dtr-forgot-btn.btn-loading, .dtr-reset-btn.btn-loading {
            pointer-events: none;
        }

        .dtr-login-btn.btn-loading .button, .dtr-forgot-btn.btn-loading .button, .dtr-reset-btn.btn-loading .button {
            color: transparent !important;
        }

        .dtr-login-btn.btn-loading::after, .dtr-forgot-btn.btn-loading::after, .dtr-reset-btn.btn-loading::after {
            content: '' !important;
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;;
            width: 16px !important;
            height: 16px !important;
            margin: -8px 0 0 -8px !important;
            border: 2px solid rgba(255, 255, 255, 0.3) !important;
            border-top-color: #fff !important;
            border-radius: 50% !important;
            animation: btn-spin 0.8s linear infinite !important;
            z-index: 3 !important;
        }

        @keyframes btn-spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        .dtr-login-btn.btn-disabled, .dtr-forgot-btn.btn-disabled, .dtr-reset-btn.btn-disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .dtr-forgot-header {
            margin-bottom: 1.5rem;
        }
        
        .dtr-back-btn {
            background: none;
            border: none;
            color: #871f82;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 0;
            transition: color 0.15s ease;
        }
        
        .dtr-back-btn:hover {
            color: #5b1360;
        }
        
        .dtr-forgot-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #111827;
            margin: 0 0 0.5rem 0;
        }
        
        .dtr-forgot-subtitle {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0;
            line-height: 1.4;
        }
        
        .dtr-form-messages {
            margin-top: 1rem;
        }
        
        .dtr-message {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .dtr-message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .dtr-message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        /* Animation for form switching */
        .dtr-form-slide-out {
            animation: slideOut 0.3s ease forwards;
        }
        
        .dtr-form-slide-in {
            animation: slideIn 0.3s ease forwards;
        }
        
        @keyframes slideOut {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(-20px); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Reset Password Form Styling */
        .dtr-reset-container {
            max-width: 400px;
            margin: 0 auto;
            padding: 2rem 1rem;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .dtr-reset-form-wrapper {
            background: #fff;
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e5e7eb;
        }
        
        .dtr-reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .dtr-reset-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 0.5rem 0;
            line-height: 1.2;
        }
        
        .dtr-reset-subtitle {
            color: #6b7280;
            margin: 0;
            font-size: 0.875rem;
        }
        
        .dtr-reset-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .dtr-reset-footer p {
            margin: 0;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .dtr-login-link {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        
        .dtr-login-link:hover {
            color: #1d4ed8;
        }
        
        .dtr-reset-error {
            max-width: 400px;
            margin: 2rem auto;
            padding: 1rem;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 8px;
            text-align: center;
        }
        
        @media (max-width: 480px) {
            .dtr-login-container, .dtr-reset-container {
                padding: 1rem 0.5rem;
            }
            
            .dtr-login-form-wrapper, .dtr-reset-form-wrapper {
                padding: 1.5rem;
            }
            
            .dtr-form-options {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .form-row {
                flex-direction: column !important;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Reset Password Form Shortcode
     */
    public function render_reset_password_form($atts) {
        $atts = shortcode_atts(array(
            'title' => '',
            'subtitle' => ''
        ), $atts, 'reset_password_form');
        
        // Check if user is logged in (admin area usage)
        if (is_user_logged_in()) {
            return $this->render_change_password_form($atts);
        }
        
        // Check if we have the required parameters for email reset
        $key = isset($_GET['key']) ? $_GET['key'] : '';
        $login = isset($_GET['login']) ? $_GET['login'] : '';
        
        if (empty($key) || empty($login)) {
            return '<div class="dtr-reset-error">Invalid reset link. Please request a new password reset.</div>';
        }
        
        // Validate the key
        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            return '<div class="dtr-reset-error">This reset link is invalid or has expired. Please request a new password reset.</div>';
        }
        
        ob_start();
        ?>
        <div class="dtr-reset-container">
            <div class="dtr-reset-form-wrapper">
                <div class="dtr-reset-header">
                    <h1 class="dtr-reset-title"><?php echo esc_html($atts['title']); ?></h1>
                    <p class="dtr-reset-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
                </div>
                
                <form class="dtr-reset-form form-container" id="dtr-reset-password-form" method="post">
                    <?php wp_nonce_field('dtr_reset_nonce', 'dtr_reset_nonce'); ?>
                    <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
                    <input type="hidden" name="login" value="<?php echo esc_attr($login); ?>">
                    
                    <div class="form-row">
                        <div class="form-field floating-label">
                            <div class="password-field">
                                <input type="password" id="dtr-new-password" name="new_password" required placeholder=" ">
                                <span class="password-toggle" onclick="toggleResetPassword('dtr-new-password')">👁</span>
                                <meter max="3" id="password-strength-meter"></meter>
                            </div>
                            <label for="dtr-new-password">New Password <span class="required">*</span></label>
                            <div class="dtr-password-strength" id="dtr-password-strength"></div>
                            <span class="dtr-form-error" id="new-password-error"></span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field floating-label">
                            <div class="password-field">
                                <input type="password" id="dtr-confirm-password" name="confirm_password" required placeholder=" ">
                                <span class="password-toggle" onclick="toggleResetPassword('dtr-confirm-password')">👁</span>
                            </div>
                            <label for="dtr-confirm-password">Confirm New Password <span class="required">*</span></label>
                            <p id="password-match-text" class="password-match-feedback"></p>
                            <span class="dtr-form-error" id="confirm-password-error"></span>
                        </div>
                    </div>
                    
                    <button type="submit" class="dtr-reset-btn" id="dtr-reset-submit">
                        <span class="button global btn-medium btn-rounded btn-blue shimmer-effect shimmer-slow is-toggle text-left chevron right" style="width:100% !important;line-height:1.6 !important;">Reset Password</span>
                    </button>
                </form>
                
                <div class="dtr-form-messages" id="dtr-reset-messages"></div>
                
                <div class="dtr-reset-footer">
                    <p>Remember your password? <a href="<?php echo site_url('/login/'); ?>" class="dtr-login-link">Sign in here</a></p>
                    
                </div>
            </div>
        </div>
        
        <style>
        .dtr-reset-container {
            max-width: 100%;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render Change Password Form for Logged-in Users
     */
    public function render_change_password_form($atts) {
        $current_user = wp_get_current_user();
        
        ob_start();
        ?>
        <div class="dtr-reset-container">
            <div class="dtr-reset-form-wrapper">
                <div class="dtr-reset-header">
                    <h1 class="dtr-reset-title"><?php echo esc_html($atts['title']); ?></h1>
                    <p class="dtr-reset-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
                </div>
                
                <form class="dtr-reset-form form-container" id="dtr-change-password-form" method="post">
                    <?php wp_nonce_field('dtr_change_password_nonce', 'dtr_change_password_nonce'); ?>
                    <input type="hidden" name="user_id" value="<?php echo esc_attr($current_user->ID); ?>">
                    
                    <div class="form-row">
                        <div class="form-field floating-label">
                            <div class="password-field">
                                <input type="password" id="dtr-current-password" name="current_password" required placeholder=" ">
                                <span class="password-toggle" onclick="toggleResetPassword('dtr-current-password')">👁</span>
                            </div>
                            <label for="dtr-current-password">Current Password <span class="required">*</span></label>
                            <span class="dtr-form-error" id="current-password-error"></span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field floating-label">
                            <div class="password-field">
                                <input type="password" id="dtr-new-password" name="new_password" required placeholder=" ">
                                <span class="password-toggle" onclick="toggleResetPassword('dtr-new-password')">👁</span>
                                <meter max="3" id="password-strength-meter"></meter>
                            </div>
                            <label for="dtr-new-password">New Password <span class="required">*</span></label>
                            <div class="dtr-password-strength" id="dtr-password-strength"></div>
                            <span class="dtr-form-error" id="new-password-error"></span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field floating-label">
                            <div class="password-field">
                                <input type="password" id="dtr-confirm-password" name="confirm_password" required placeholder=" ">
                                <span class="password-toggle" onclick="toggleResetPassword('dtr-confirm-password')">👁</span>
                            </div>
                            <label for="dtr-confirm-password">Confirm New Password <span class="required">*</span></label>
                            <p id="password-match-text" class="password-match-feedback"></p>
                            <span class="dtr-form-error" id="confirm-password-error"></span>
                        </div>
                    </div>
                    
                    <button type="submit" class="dtr-reset-btn" id="dtr-change-submit">
                        <span class="button global btn-medium btn-rounded btn-purple shimmer-effect shimmer-slow is-toggle text-left chevron right" style="width:100% !important;line-height:1.6 !important;">Update Password</span>
                    </button>
                </form>
                
                <div class="dtr-form-messages" id="dtr-change-messages"></div>
            </div>
        </div>
        
        <style>
        .dtr-reset-container {
            max-width: 100%;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Handle Login AJAX Request
     */
    public function handle_login() {
        if (!wp_verify_nonce($_POST['dtr_login_nonce'], 'dtr_login_nonce')) {
            wp_die('Security check failed');
        }
        
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']) ? true : false;
        $redirect_to = isset($_POST['redirect_to']) ? esc_url($_POST['redirect_to']) : home_url();
        
        $credentials = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember
        );
        
        $user = wp_signon($credentials, false);
        
        if (is_wp_error($user)) {
            wp_send_json_error(array(
                'message' => 'Invalid username or password. Please try again.'
            ));
        }
        
        wp_send_json_success(array(
            'message' => 'Login successful! Redirecting...',
            'redirect' => empty($redirect_to) ? home_url() : $redirect_to
        ));
    }
    
    /**
     * Handle Forgot Password AJAX Request
     */
    public function handle_forgot_password() {
        if (!wp_verify_nonce($_POST['dtr_forgot_nonce'], 'dtr_login_nonce')) {
            wp_die('Security check failed');
        }
        
        $user_email = sanitize_email($_POST['user_email']);
        
        if (empty($user_email)) {
            wp_send_json_error(array('message' => 'Please enter your email address.'));
        }
        
        if (!is_email($user_email)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        }
        
        $user = get_user_by('email', $user_email);
        if (!$user) {
            wp_send_json_error(array('message' => 'No account found with that email address.'));
        }
        
        // Use WordPress built-in password reset
        $result = retrieve_password($user->user_login);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => 'Unable to send reset email. Please try again.'));
        }
        
        wp_send_json_success(array(
            'message' => 'Password reset email sent! Check your inbox for instructions.'
        ));
    }
    
    /**
     * Handle Reset Password AJAX Request
     */
    public function handle_reset_password() {
        if (!wp_verify_nonce($_POST['dtr_reset_nonce'], 'dtr_reset_nonce')) {
            wp_die('Security check failed');
        }
        
        $key = sanitize_text_field($_POST['key']);
        $login = sanitize_text_field($_POST['login']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_password) || empty($confirm_password)) {
            wp_send_json_error(array('message' => 'Please fill in all fields.'));
        }
        
        if ($new_password !== $confirm_password) {
            wp_send_json_error(array('message' => 'Passwords do not match.'));
        }
        
        if (strlen($new_password) < 8) {
            wp_send_json_error(array('message' => 'Password must be at least 8 characters long.'));
        }
        
        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            wp_send_json_error(array('message' => 'Invalid or expired reset link.'));
        }
        
        reset_password($user, $new_password);
        
        wp_send_json_success(array(
            'message' => 'Password reset successful! You can now log in with your new password.',
            'redirect' => site_url('/login/')
        ));
    }
    
    /**
     * Handle Change Password AJAX Request (for logged-in users)
     */
    public function handle_change_password() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to change your password.'));
        }
        
        if (!wp_verify_nonce($_POST['dtr_change_password_nonce'], 'dtr_change_password_nonce')) {
            wp_die('Security check failed');
        }
        
        $user_id = intval($_POST['user_id']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify the user ID matches the current user
        $current_user = wp_get_current_user();
        if ($user_id !== $current_user->ID) {
            wp_send_json_error(array('message' => 'Invalid user authentication.'));
        }
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            wp_send_json_error(array('message' => 'Please fill in all fields.'));
        }
        
        if ($new_password !== $confirm_password) {
            wp_send_json_error(array('message' => 'New passwords do not match.'));
        }
        
        if (strlen($new_password) < 8) {
            wp_send_json_error(array('message' => 'New password must be at least 8 characters long.'));
        }
        
        // Verify current password
        if (!wp_check_password($current_password, $current_user->user_pass, $current_user->ID)) {
            wp_send_json_error(array('message' => 'Current password is incorrect.'));
        }
        
        // Update the password
        wp_set_password($new_password, $current_user->ID);
        
        // Log the user out (they'll need to log back in with new password)
        wp_logout();
        
        wp_send_json_success(array(
            'message' => 'Password updated successfully! You have been logged out for security. Please log in again with your new password.',
            'redirect' => site_url('/login/')
        ));
    }
    
    /**
     * Override WordPress password reset email to use custom page
     */
    public function custom_reset_password_email($message, $key, $user_login, $user_data) {
        $reset_page = site_url('/reset-password/');
        
        $reset_link = add_query_arg(array(
            'key' => $key,
            'login' => rawurlencode($user_login),
        ), $reset_page);
        
        $message = "Hi " . $user_login . ",\n\n";
        $message .= "Click the link below to reset your password:\n\n";
        $message .= $reset_link . "\n\n";
        $message .= "If you didn't request this, you can ignore this email.\n\n";
        $message .= "This link will expire in 24 hours for your security.";
        
        return $message;
    }
    
    /**
     * Custom password reset email subject
     */
    public function custom_reset_password_subject($subject, $user_login, $user_data) {
        return 'Reset Your Password - ' . get_bloginfo('name');
    }
}

// Initialize the shortcode
new DTR_Login_Forgot_Password_Shortcode();

// Enqueue custom stylesheet for shortcodes with high priority to override theme styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'dtr-dynamic-forms', 
        plugin_dir_url(__FILE__) . '../assets/css/dynamic-forms.css', 
        array(), // No dependencies - loads independently
        filemtime(plugin_dir_path(__FILE__) . '../assets/css/dynamic-forms.css'), // Version based on file modification time
        'all'
    );
}, 999); // High priority to ensure it loads after theme styles