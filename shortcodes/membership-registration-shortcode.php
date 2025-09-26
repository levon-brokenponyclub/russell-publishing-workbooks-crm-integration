<?php
if (!defined('ABSPATH')) exit;

// Add shortcode for membership registration form
add_shortcode('dtr_membership_registration', 'dtr_membership_registration_shortcode');

// Add a simple test shortcode
add_shortcode('dtr_test', 'dtr_test_shortcode');

// Enqueue custom stylesheet for shortcodes with high priority to override theme styles
add_action('wp_enqueue_scripts', function() {
    // Load the original membership form styles first
    wp_enqueue_style(
        'dtr-membership-registration-form', 
        plugin_dir_url(__FILE__) . '../assets/css/membership-registration-form.css', 
        array(), 
        filemtime(plugin_dir_path(__FILE__) . '../assets/css/membership-registration-form.css'),
        'all'
    );
    
    // Load dynamic forms styles second to override/supplement the base styles
    wp_enqueue_style(
        'dtr-dynamic-forms', 
        plugin_dir_url(__FILE__) . '../assets/css/dynamic-forms.css', 
        array('dtr-membership-registration-form'), // Depends on base styles to load after
        filemtime(plugin_dir_path(__FILE__) . '../assets/css/dynamic-forms.css'),
        'all'
    );
    
    // Note: lead-generation-registration.js was removed - login modal functionality disabled
}, 999); // High priority to ensure it loads after theme styles

function dtr_test_shortcode() {
    return '<div style="background: red; color: white; padding: 10px;">DTR TEST SHORTCODE WORKS!</div>';
}

function dtr_membership_registration_shortcode($atts) {
    // Debug: Log that shortcode is being called
    error_log('DTR: membership-registration-shortcode called with attributes: ' . print_r($atts, true));
    
    // Parse shortcode attributes with proper defaults
    $atts = shortcode_atts(array(
        'title' => 'Join Drug Target Review Today',
        'description' => 'Create your free account to access exclusive content, industry insights, and connect with the global pharmaceutical community.',
        'development_mode' => 'false',
    ), $atts, 'dtr_membership_registration');
    
    // Get form configuration from plugin settings
    $form_config = DTR_Workbooks_Integration::get_shortcode_form_config('membership_registration');
    $development_mode = $form_config['dev_mode'] ?? false;
    
    // Check if form is enabled
    if (!$form_config['enabled']) {
        return '<div class="dtr-form-disabled">Membership Registration form is currently disabled in plugin settings.</div>';
    }

    ob_start();
    ?>
    <?php if ($development_mode): ?>
    <!-- Development Mode Indicator -->
    <div class="dev-mode-indicator active" id="devModeIndicator">
        🛠️ DEVELOPMENT MODE - Form Submission Disabled
    </div>
    <?php endif; ?>

    <div class="sign-up-container" id="html-form-test">
        <!-- Blue Container -->
        <div class="blue-container initial" id="blueContainer">
            <div class="main-content">
                <h2>Sign up for free</h2>
                <h3>Unlock industry insights now for FREE</h3>
                <div class="divider"></div>
                <div class="policies-text">
                    Please review and accept Drug Target Review's 
                    <a href="<?php echo home_url('/privacy-policy'); ?>">privacy policy</a> and 
                    <a href="<?php echo home_url('/terms-conditions'); ?>">terms and conditions</a>
                </div>
                <div class="gdpr-text">
                    By becoming a member of Drug Target Review you are agreeing to our terms and conditions and privacy policy. As part of your registration, and in compliance with GDPR, we will share your data with the specific sponsor(s)/partner(s) of this webinar who may wish to contact you. You may opt-out at any time by using the unsubscribe link in one of our, or our sponsor's, Communication emails, or by contacting admin@drugtargetreview.com
                </div>
                
                <!-- Development Mode Toggle -->
                <!-- <div class="dev-mode-toggle">
                    <h4>🛠️ Development Mode</h4>
                    <label class="toggle-switch">
                        <input type="checkbox" id="devModeToggle">
                        <span class="toggle-slider"></span>
                    </label>
                    <div class="toggle-labels">
                        <span class="live">Live Form</span>
                        <span class="dev">Dev Mode</span>
                    </div>
                    <button type="button" class="preview-loader-btn" onclick="previewLoader()">👁️ Preview Loader</button>
                </div> -->
                
                <div class="divider"></div>
                <h3>Already a member?</h3>
                <a role="menuitem" href="#" id="gated-content-signup-button" class="button global btn-small btn-rounded btn-purple shimmer-effect shimmer-slow is-toggle text-left chevron right" onclick="event.preventDefault(); openLoginModal();">Login</a>
            </div>
            <div class="vertical-text">View Policies & Terms</div>
        </div>

        <!-- White Container -->
        <div class="white-container initial" id="whiteContainer">
            <div class="form-container membership-registration-form">

                <div class="progress-container">
                    <!-- Progress Bar -->
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill" style="width: 33.33%;"></div>
                    </div>

                    <!-- Breadcrumbs -->
                    <ul class="breadcrumbs">
                        <li class="active" data-step="1"><span class="step">01</span><span class="label">Personal Details</span></li>
                        <li data-step="2"><span class="step">02</span><span class="label">Communication Preferences</span></li>
                        <li data-step="3"><span class="step">03</span><span class="label">Topics of Interest</span></li>
                    </ul>
                </div>

                <!-- Step 1: Personal Information -->
                <div class="form-step active" id="step1">
                    <div class="form-row">
                        <div class="form-field floating-label">
                            <select id="title">
                                <option value="Dr">Dr.</option>
                                <option value="Mr">Mr.</option>
                                <option value="Mrs">Mrs.</option>
                                <option value="Master">Master</option>
                                <option value="Miss">Miss.</option>
                                <option value="Ms">Ms.</option>
                                <option value="Prof">Prof.</option>
                            </select>
                            <label for="title">Title</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field floating-label">
                            <input type="text" id="firstName" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                            <label for="firstName">First Name <span class="required">*</span></label>
                        </div>
                        <div class="form-field floating-label">
                            <input type="text" id="lastName" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                            <label for="lastName">Last Name <span class="required">*</span></label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field floating-label">
                            <input type="email" id="email" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                            <label for="email">Email Address <span class="required">*</span></label>
                        </div>
                        <div class="form-field floating-label">
                            <input type="tel" id="phone" uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                            <label for="phone">Telephone Number</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <?php echo do_shortcode('[workbooks_employer_select]'); ?>
                        </div>
                        <div class="form-field floating-label">
                            <input type="text" id="jobTitle" uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                            <label for="jobTitle">Job Title</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field floating-label">
                            <select id="country" class="country-validator" uuid="<?php echo wp_generate_uuid4(); ?>">
                                <option value="">-</option>
                                <?php 
                                // Use the full country list function if available
                                if (function_exists('nf_full_country_names_options')) {
                                    echo nf_full_country_names_options('United Kingdom'); // Default to UK
                                } else {
                                    // Fallback to basic options
                                    echo '<option value="United Kingdom" selected>United Kingdom</option>';
                                    echo '<option value="United States">United States</option>';
                                    echo '<option value="Canada">Canada</option>';
                                    echo '<option value="Australia">Australia</option>';
                                    echo '<option value="Germany">Germany</option>';
                                    echo '<option value="France">France</option>';
                                    echo '<option value="Other">Other</option>';
                                }
                                ?>
                            </select>
                            <label for="country">Country</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field floating-label ">
                            <input type="text" id="city" uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                            <label for="city">Town/City</label>
                        </div>
                        <div class="form-field floating-label">
                            <input type="text" id="postcode" class="postcode-validator" uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                            <label for="postcode">Postal/Zip Code</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field floating-label">
                            <div class="password-field">
                                <input type="password" id="password" required minlength="6" uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                                <span class="password-toggle" onclick="togglePassword('password')">👁</span>
                                <meter max="3" id="password-strength-meter"></meter>
                            </div>
                            
                            <label for="password">Password <span class="required">*</span></label>
                            
                            <p id="password-strength-text"></p>
                        </div>
                        <div class="form-field floating-label">
                            <div class="password-field">
                                <input type="password" id="confirmPassword" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                                <span class="password-toggle" onclick="togglePassword('confirmPassword')">👁</span>
                            </div>
                            <label for="confirmPassword">Confirm Password <span class="required">*</span></label>
                            <p id="password-match-text" class="password-match-feedback"></p>
                        </div>
                    </div>

                </div>

                <!-- Step 2: Communication Preferences -->
                <div class="form-step" id="step2">
                    <h3>Communication Preferences</h3>
                    <p class="description">Select preferred Communication Preferences to ensure you don’t miss out. Receive our weekly e-newsletter with the latest news and articles, plus updates from our trusted partners, webinars and events.</p>
                    
                    <fieldset>
                        <div class="workbooks-checkboxes dtr membership-regstration subscription list">
                            <label>
                                <input type="checkbox" class="dtr-marketing-checkbox" id="dtr_marketing_1" name="cf_person_dtr_news" value="1">
                                <strong>Newsletter:</strong><br>News, articles and analysis by email
                            </label>
                            <label>
                                <input type="checkbox" class="dtr-marketing-checkbox" id="dtr_marketing_2" name="cf_person_dtr_events" value="1">
                                <strong>Event:</strong><br>Information about events by email
                            </label>
                            <label>
                                <input type="checkbox" class="dtr-marketing-checkbox" id="dtr_marketing_3" name="cf_person_dtr_third_party" value="1">
                                <strong>Third party:</strong><br>Application notes, product developments and updates from our trusted partners by email
                            </label>
                            <label>
                                <input type="checkbox" class="dtr-marketing-checkbox" id="dtr_marketing_4" name="cf_person_dtr_webinar" value="1">
                                <strong>Webinar:</strong><br>Information about webinars by email
                            </label>
                        </div>
                    </fieldset>
                </div>

                <!-- Step 3: Topics of Interest -->
                <div class="form-step" id="step3">
                    <h3>Select Topics Interests</h3>
                    <p class="description">Select which topics you are interested in. We will use your selection to tailor the content you see to your preferences.</p>
                    
                    <fieldset>
                        <div class="workbooks-checkboxes dtr membership-regstration subscription list">
                            <label>
                                <input type="checkbox" class="dtr-topics-checkbox" id="dtr_interest_1" name="cf_person_business" value="1">
                                    Business
                            </label>
                            <label>
                                <input type="checkbox" class="dtr-topics-checkbox" id="dtr_interest_2" name="cf_person_diseases" value="1">
                                Diseases
                            </label>
                            <label>
                                <input type="checkbox" class="dtr-topics-checkbox" id="dtr_interest_3" name="cf_person_drugs_therapies" value="1">
                                Drugs & Therapies
                            </label>
                            <label>
                                <input type="checkbox" class="dtr-topics-checkbox" id="dtr_interest_4" name="cf_person_genomics_3774" value="1">
                                Genomics
                            </label>
                            <label>
                                <input type="checkbox" class="dtr-topics-checkbox" id="dtr_interest_5" name="cf_person_research_development" value="1">
                                Research & Development
                            </label>
                            <label>
                                <input type="checkbox" class="dtr-topics-checkbox" id="dtr_interest_6" name="cf_person_technology" value="1">
                                Technology
                            </label>
                            <label>
                                <input type="checkbox" class="dtr-topics-checkbox" id="dtr_interest_7" name="cf_person_tools_techniques" value="1">
                                Tools & Techniques
                            </label>
                        </div>
                    </fieldset>

                    <!-- Terms and Conditions Checkbox -->
                    <div class="checkbox-group consent-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="terms" required uuid="<?php echo wp_generate_uuid4(); ?>">
                            <label for="terms" class="checkbox-label">
                                I confirm that by becoming a member of Drug Target Review I accept the terms and conditions and privacy policy
                            </label>
                        </div>
                    </div>

                    <div class="navigation-buttons submit">
                        <button type="button" class="button border btn-small global btn-rounded btn-purple shimmer-effect shimmer-slow text-left chevron left" onclick="previousStep()">Previous</button>
                        <button type="button" class="button btn-small global btn-rounded btn-blue shimmer-effect shimmer-slow text-left chevron right" onclick="submitForm()">Get Started</button>
                        
                       
                        <!-- Debug Test Button -->
                        <!-- <div style="margin-top: 15px; text-align: center;position:absolute:top:100px;left:100px;width:100%;dsiplay:block;">
                            <button type="button" onclick="testAjaxEndpoint()" class="button" style="background: #ff6600; color: white; padding: 8px 16px; font-size: 12px; border-radius: 4px; margin-right: 10px;">
                                🔧 Test AJAX Connection
                            </button>
                            <button type="button" onclick="fillTestData()" class="button" style="background: #28a745; color: white; padding: 8px 16px; font-size: 12px; border-radius: 4px;">
                                📝 Fill Test Data
                            </button>
                        </div> -->
                       
                    </div>
                </div>

                <!-- Navigation -->
                <div class="navigation-buttons" id="navigationButtons">
                    <button type="button" class="button border btn-small global btn-rounded btn-purple shimmer-effect shimmer-slow text-left chevron left" onclick="previousStep()" id="prevBtn" style="visibility: hidden;">Previous</button>
                    <button type="button" class="button btn-small global btn-rounded btn-blue shimmer-effect shimmer-slow text-left chevron right" onclick="nextStep()" id="nextBtn">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="form-loader-overlay" id="formLoaderOverlay" style="display: none;">
        <div class="loader-content">
            <h2>Registration in progress...</h2>
            <div class="loader-spinner">
                <div class="progress-circle">
                    <div class="progress-circle-fill" id="progressCircleFill"></div>
                </div>
                <div class="loader-icon"><i class="fa-light fa-user"></i></div>
                <div class="countdown-container" id="countdownContainer">
                    <div class="countdown-number" id="countdownNumber"></div>
                    <div class="countdown-message" id="countdownMessage"></div>
                </div>
            </div>
            <p id="loaderStatusText">Creating your profile...</p>
        </div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 3;
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
            var password = document.getElementById('password');
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
                    
                    // Update the password strength meter
                    meter.value = score;
                   
                    // Update the text indicator
                    if (message) {
                        text.innerHTML = message;
                    } else {
                        text.innerHTML = "Strength: " + "<strong>" + strength[score] + "</strong>";
                    }
                    
                    // Check password matching whenever password changes
                    checkPasswordMatch();
                });
            }
        }

        // Password matching checker
        function initPasswordMatch() {
            var password = document.getElementById('password');
            var confirmPassword = document.getElementById('confirmPassword');
            var matchText = document.getElementById('password-match-text');

            if (password && confirmPassword && matchText) {
                confirmPassword.addEventListener('input', checkPasswordMatch);
                password.addEventListener('input', checkPasswordMatch);
            }
        }

        function checkPasswordMatch() {
            var password = document.getElementById('password');
            var confirmPassword = document.getElementById('confirmPassword');
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

        <?php if ($development_mode): ?>
        // Development mode toggle (only available in dev mode)
        function initDevModeToggle() {
            // Since we're in development mode, we always start in dev mode
            devModeActive = true;
            removeRequiredFields();
            console.log('🛠️ Development Mode: ON - Form configured for testing');
        }
        <?php else: ?>
        // No dev mode toggle available - always live mode
        function initDevModeToggle() {
            // Dev mode not available in live configuration
            devModeActive = false;
            console.log('🟢 Live Mode: Form configured for production use');
        }
        <?php endif; ?>

        // Store original required fields
        let originalRequiredFields = [];

        function removeRequiredFields() {
            // Clear previous storage
            originalRequiredFields = [];
            
            // Find all required fields and store them
            const requiredFields = document.querySelectorAll('input[required], select[required]');
            requiredFields.forEach(field => {
                originalRequiredFields.push(field);
                field.removeAttribute('required');
                field.classList.add('dev-mode-optional');
            });
        }

        function restoreRequiredFields() {
            // Restore required attribute to originally required fields
            originalRequiredFields.forEach(field => {
                field.setAttribute('required', 'required');
                field.classList.remove('dev-mode-optional');
            });
        }

        function updateProgress() {
            // Progress is now handled by CSS :after pseudo-elements
            // The green line will automatically show up to the active/completed steps
            // No need for manual width calculation since CSS handles the line display
        }

        function updateBreadcrumbs() {
            document.querySelectorAll('.breadcrumbs li').forEach((li, index) => {
                const stepNumber = index + 1;
                li.classList.remove('active', 'completed');
                
                if (stepNumber === currentStep) {
                    li.classList.add('active');
                } else if (stepNumber < currentStep) {
                    li.classList.add('completed');
                }
            });
        }

        function updateLayout() {
            const blueContainer = document.getElementById('blueContainer');
            const whiteContainer = document.getElementById('whiteContainer');

            if (currentStep === 1) {
                // Initial layout
                blueContainer.className = 'blue-container initial';
                whiteContainer.className = 'white-container initial';
            } else {
                // Sidebar layout
                blueContainer.className = 'blue-container sidebar';
                whiteContainer.className = 'white-container expanded';
            }
        }

        function showStep(step) {
            // Hide all steps
            document.querySelectorAll('.form-step').forEach(stepElement => {
                stepElement.classList.remove('active');
            });

            // Show current step
            document.getElementById('step' + step).classList.add('active');

            // Update navigation buttons visibility
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const navButtons = document.getElementById('navigationButtons');

            if (step === 1) {
                prevBtn.style.visibility = 'hidden';
                nextBtn.style.display = 'block';
                navButtons.style.display = 'flex';
            } else if (step === totalSteps) {
                prevBtn.style.visibility = 'visible';
                nextBtn.style.display = 'none';
                navButtons.style.display = 'none';
            } else {
                prevBtn.style.visibility = 'visible';
                nextBtn.style.display = 'block';
                navButtons.style.display = 'flex';
            }

            // Reinitialize floating labels for the current step
            setTimeout(() => initFloatingLabels(), 50);
        }

        function validateCurrentStep() {
            // Skip validation in development mode
            if (devModeActive) {
                return true;
            }

            const currentStepElement = document.getElementById('step' + currentStep);
            
            // Validate required fields
            const requiredFields = currentStepElement.querySelectorAll('input[required], select[required]');
            for (let field of requiredFields) {
                if (!field.value.trim()) {
                    alert('Please fill in all required fields marked with *');
                    field.focus();
                    return false;
                }
            }
            
            // Validate password confirmation if both fields have values
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirmPassword');
            
            if (passwordField && confirmPasswordField && 
                passwordField.value && confirmPasswordField.value &&
                passwordField.value !== confirmPasswordField.value) {
                alert('Passwords do not match');
                confirmPasswordField.focus();
                return false;
            }
            
            // Validate email format
            const emailField = document.getElementById('email');
            if (emailField && emailField.value && !isValidEmail(emailField.value)) {
                alert('Please enter a valid email address');
                emailField.focus();
                return false;
            }

            // Validate terms checkbox (only on step 3)
            const termsField = document.getElementById('terms');
            if (currentStep === 3 && termsField && !termsField.checked) {
                alert('Please accept the terms and conditions to continue');
                termsField.focus();
                return false;
            }
            
            return true;
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function nextStep() {
            if (!validateCurrentStep()) {
                return;
            }

            if (currentStep < totalSteps) {
                currentStep++;
                showStep(currentStep);
                updateProgress();
                updateBreadcrumbs();
                updateLayout();
            }
        }

        function previousStep() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
                updateProgress();
                updateBreadcrumbs();
                updateLayout();
            }
        }

        function goToStep(step) {
            if (step >= 1 && step <= totalSteps) {
                currentStep = step;
                showStep(currentStep);
                updateProgress();
                updateBreadcrumbs();
                updateLayout();
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

        // Enhanced Progress Loader Functions
        function showProgressLoader() {
            const loadingOverlay = document.getElementById('formLoaderOverlay');
            const progressFill = document.getElementById('progressCircleFill');
            const statusText = document.getElementById('loaderStatusText');
            const countdownContainer = document.getElementById('countdownContainer');
            
            if (loadingOverlay) {
                loadingOverlay.style.display = 'flex';
                
                // Set header z-index to ensure overlay appears above it
                const header = document.querySelector('header');
                if (header) {
                    header.style.zIndex = '1';
                }
                
                // Reset progress
                progressFill.className = 'progress-circle-fill progress-0';
                statusText.textContent = 'Preparing submission...';
                countdownContainer.classList.remove('active');
            }
        }

        // Real-time progress updater that matches actual submission stages
        function updateFormProgress(stage, message) {
            const progressFill = document.getElementById('progressCircleFill');
            const statusText = document.getElementById('loaderStatusText');
            
            if (progressFill && statusText) {
                progressFill.className = `progress-circle-fill progress-${stage}`;
                statusText.textContent = message;
                console.log(`🔄 Progress Update: ${stage}% - ${message}`);
            }
        }

        // Start countdown after successful submission (called from success handler)
        function startSubmissionCountdown() {
            const countdownContainer = document.getElementById('countdownContainer');
            const countdownNumber = document.getElementById('countdownNumber');
            const countdownMessage = document.getElementById('countdownMessage');
            const loaderIcon = document.querySelector('.loader-icon');
            
            // Hide the user icon and show countdown
            if (loaderIcon) loaderIcon.style.opacity = '0';
            if (countdownContainer) countdownContainer.classList.add('active');
            
            let count = 3;
            
            function showNextCount() {
                if (count > 0) {
                    if (countdownNumber) countdownNumber.textContent = count;
                    if (countdownMessage) countdownMessage.textContent = '';
                    count--;
                    setTimeout(showNextCount, 1000);
                } else {
                    // Show final message
                    if (countdownNumber) countdownNumber.textContent = '';
                    if (countdownMessage) countdownMessage.textContent = 'Membership Activated!';
                    
                    // Keep overlay visible and redirect immediately after final message
                    setTimeout(() => {
                        window.location.href = '/thank-you-for-becoming-a-member-update/';
                    }, 1000); // Redirect 1s after "Membership Activated!" shows - overlay stays visible
                }
            }
            
            showNextCount();
        }

        function simulateFormProgress() {
            // This function is now deprecated - progress is handled by real-time updates
            console.log('⚠️ simulateFormProgress is deprecated - using real-time progress tracking');
        }

        function startCountdown() {
            // This function is now deprecated - use startSubmissionCountdown for real-time progress
            console.log('⚠️ startCountdown is deprecated - use startSubmissionCountdown instead');
            startSubmissionCountdown();
        }

        function hideProgressLoader() {
            const loadingOverlay = document.getElementById('formLoaderOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
                
                // Restore header z-index when hiding overlay
                const header = document.querySelector('header');
                if (header) {
                    header.style.zIndex = '';  // Remove the inline style to restore original
                }
            }
        }

        function previewLoader() {
            showProgressLoader();
            
            // Simulate the actual submission flow for preview
            setTimeout(() => updateFormProgress(25, 'Validating security credentials...'), 500);
            setTimeout(() => updateFormProgress(40, 'Security validation complete...'), 1500);
            setTimeout(() => updateFormProgress(50, 'Preparing account creation...'), 2000);
            setTimeout(() => updateFormProgress(60, 'Submitting your information...'), 2500);
            setTimeout(() => updateFormProgress(75, 'Processing your registration...'), 3500);
            setTimeout(() => updateFormProgress(90, 'Finalizing your account...'), 4500);
            setTimeout(() => {
                updateFormProgress(100, 'Account created successfully!');
                setTimeout(() => startSubmissionCountdown(), 500);
            }, 5000);
        }

        // Debug: Log current form state and values
        function debugFormState() {
            console.log('🔍 [DEBUG] Current form state:');
            console.log('🔍 [DEBUG] Current step:', currentStep);
            console.log('🔍 [DEBUG] Total steps:', totalSteps);
            console.log('🔍 [DEBUG] Dev mode active:', devModeActive);
            
            // Log all form field values
            const fields = ['title', 'firstName', 'lastName', 'email', 'employer', 'phone', 'jobTitle', 'country', 'city', 'postcode', 'password'];
            fields.forEach(fieldId => {
                const element = document.getElementById(fieldId);
                console.log(`🔍 [DEBUG] ${fieldId}:`, element ? element.value : 'NOT FOUND');
            });
            
            // Log checkbox states
            console.log('🔍 [DEBUG] Marketing Preferences:');
            const marketingIds = ['dtr_marketing_1', 'dtr_marketing_2', 'dtr_marketing_3', 'dtr_marketing_4'];
            const marketingLabels = ['Newsletter', 'Events', 'Third Party', 'Webinar'];
            marketingIds.forEach((fieldId, index) => {
                const element = document.getElementById(fieldId);
                console.log(`🔍 [DEBUG] ${marketingLabels[index]} (${fieldId}):`, {
                    found: !!element,
                    checked: element ? element.checked : 'N/A',
                    value: element ? element.value : 'N/A',
                    type: element ? element.type : 'N/A'
                });
            });
            
            console.log('🔍 [DEBUG] Topics of Interest:');
            const toiIds = ['dtr_interest_1', 'dtr_interest_2', 'dtr_interest_3', 'dtr_interest_4', 'dtr_interest_5', 'dtr_interest_6', 'dtr_interest_7'];
            const toiLabels = ['Business', 'Diseases', 'Drugs & Therapies', 'Genomics', 'Research & Development', 'Technology', 'Tools & Techniques'];
            toiIds.forEach((fieldId, index) => {
                const element = document.getElementById(fieldId);
                console.log(`🔍 [DEBUG] ${toiLabels[index]} (${fieldId}):`, {
                    found: !!element,
                    checked: element ? element.checked : 'N/A',
                    value: element ? element.value : 'N/A',
                    type: element ? element.type : 'N/A'
                });
            });
            
            const termsElement = document.getElementById('terms');
            console.log(`🔍 [DEBUG] terms:`, {
                found: !!termsElement,
                checked: termsElement ? termsElement.checked : 'N/A',
                value: termsElement ? termsElement.value : 'N/A',
                type: termsElement ? termsElement.type : 'N/A'
            });
            
            // Also check what form elements actually exist
            console.log('🔍 [DEBUG] ALL CHECKBOXES ON PAGE:');
            const allCheckboxes = document.querySelectorAll('input[type="checkbox"]');
            console.log(`🔍 [DEBUG] Found ${allCheckboxes.length} checkboxes total`);
            allCheckboxes.forEach((cb, i) => {
                console.log(`🔍 [DEBUG] Checkbox ${i+1}: ID="${cb.id}", NAME="${cb.name}", CHECKED=${cb.checked}, VALUE="${cb.value}"`);
            });
        }

        function submitForm() {
            console.log('🔥 [DEBUG] ===== FORM SUBMISSION START =====');
            debugFormState();
            
            if (!validateCurrentStep()) {
                console.log('🔥 [DEBUG] Form validation failed');
                return;
            }
            if (!validateCurrentStep()) {
                return;
            }

            // Check if development mode is active
            if (devModeActive) {
                alert('🛠️ Development Mode Active\n\nForm submission is disabled for testing purposes.\nAll form validation and styling can be tested without affecting the live system.\n\nToggle off Development Mode to enable live form submission.');
                return;
            }

            // Debug: Log that we're attempting form submission
            console.log('🔥 [DEBUG] Form submission started');
            console.log('🔥 [DEBUG] Development mode active:', devModeActive);

            // Show loading overlay with progress
            showProgressLoader();

            // Collect form data
            const formData = new FormData();
            
            // Add WordPress AJAX action
            formData.append('action', 'dtr_html_form_submit');
            
            // Personal information
            formData.append('title', document.getElementById('title')?.value || '');
            formData.append('firstName', document.getElementById('firstName')?.value || '');
            formData.append('lastName', document.getElementById('lastName')?.value || '');
            formData.append('email', document.getElementById('email')?.value || '');
            formData.append('claimed_employer', document.getElementById('employer')?.value || '');
            formData.append('telephone', document.getElementById('phone')?.value || '');
            formData.append('jobTitle', document.getElementById('jobTitle')?.value || '');
            formData.append('country', document.getElementById('country')?.value || '');
            formData.append('town', document.getElementById('city')?.value || '');
            formData.append('postcode', document.getElementById('postcode')?.value || '');
            formData.append('password', document.getElementById('password')?.value || '');
            formData.append('terms', document.getElementById('terms')?.checked ? '1' : '');

            // Communication preferences
            console.log('📊 Collecting Marketing Preferences:');
            const newsletterField = document.getElementById('dtr_marketing_1');
            const eventsField = document.getElementById('dtr_marketing_2');
            const thirdPartyField = document.getElementById('dtr_marketing_3');
            const webinarField = document.getElementById('dtr_marketing_4');
            
            const newsletterValue = newsletterField?.checked ? '1' : '';
            const eventsValue = eventsField?.checked ? '1' : '';
            const thirdPartyValue = thirdPartyField?.checked ? '1' : '';
            const webinarValue = webinarField?.checked ? '1' : '';
            
            console.log(`Newsletter = ${newsletterValue}`);
            console.log(`Event = ${eventsValue}`);
            console.log(`Third Party = ${thirdPartyValue}`);
            console.log(`Webinar = ${webinarValue}`);
            
            formData.append('newsletter', newsletterValue);
            formData.append('events', eventsValue);
            formData.append('thirdParty', thirdPartyValue);
            formData.append('webinar', webinarValue);

            // Topics of interest
            console.log('📊 Collecting Topics of Interest:');
            const businessField = document.getElementById('dtr_interest_1');
            const diseasesField = document.getElementById('dtr_interest_2');
            const drugsField = document.getElementById('dtr_interest_3');
            const genomicsField = document.getElementById('dtr_interest_4');
            const researchField = document.getElementById('dtr_interest_5');
            const technologyField = document.getElementById('dtr_interest_6');
            const toolsField = document.getElementById('dtr_interest_7');
            
            const businessValue = businessField?.checked ? '1' : '';
            const diseasesValue = diseasesField?.checked ? '1' : '';
            const drugsValue = drugsField?.checked ? '1' : '';
            const genomicsValue = genomicsField?.checked ? '1' : '';
            const researchValue = researchField?.checked ? '1' : '';
            const technologyValue = technologyField?.checked ? '1' : '';
            const toolsValue = toolsField?.checked ? '1' : '';
            
            console.log(`Business = ${businessValue}`);
            console.log(`Diseases = ${diseasesValue}`);
            console.log(`Drugs & Therapies = ${drugsValue}`);
            console.log(`Genomics = ${genomicsValue}`);
            console.log(`Research & Development = ${researchValue}`);
            console.log(`Technology = ${technologyValue}`);
            console.log(`Tools & Techniques = ${toolsValue}`);
            
            formData.append('business', businessValue);
            formData.append('diseases', diseasesValue);
            formData.append('drugs', drugsValue);
            formData.append('genomics', genomicsValue);
            formData.append('research', researchValue);
            formData.append('technology', technologyValue);
            formData.append('tools', toolsValue);
            
            // Log selected TOIs for AOI mapping
            const selectedTOIs = [];
            if (businessValue === '1') selectedTOIs.push('business');
            if (diseasesValue === '1') selectedTOIs.push('diseases');
            if (drugsValue === '1') selectedTOIs.push('drugs_therapies');
            if (genomicsValue === '1') selectedTOIs.push('genomics_3774');
            if (researchValue === '1') selectedTOIs.push('research_development');
            if (technologyValue === '1') selectedTOIs.push('technology');
            if (toolsValue === '1') selectedTOIs.push('tools_techniques');
            
            console.log('📊 Selected TOIs for AOI mapping:', selectedTOIs);
            
            // Log predicted AOI mappings (based on TOI selections)
            if (selectedTOIs.length > 0) {
                console.log('📊 Predicted AOI mappings:');
                selectedTOIs.forEach(toi => {
                    console.log(`  TOI: ${toi} → Will map to corresponding AOI fields`);
                });
            } else {
                console.log('📊 No TOIs selected - no AOI mappings will be applied');
            }

            // Test the AJAX endpoint first
            console.log('Testing AJAX endpoint: <?php echo admin_url('admin-ajax.php'); ?>');
            
            // Get WordPress nonce first, then submit
            updateFormProgress(25, 'Validating security credentials...');
            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=dtr_get_form_nonce', {
                method: 'GET'
            })
            .then(response => {
                console.log('Nonce response status:', response.status);
                updateFormProgress(40, 'Security validation complete...');
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('🔥 [DEBUG] Nonce data received:', data);
                updateFormProgress(50, 'Preparing account creation...');
                if (data.success && data.data && data.data.nonce) {
                    formData.append('nonce', data.data.nonce);
                } else if (data.nonce) {
                    // Fallback for older format
                    formData.append('nonce', data.nonce);
                } else {
                    throw new Error('No nonce received from server');
                }
                
                console.log('🔥 [DEBUG] About to submit form data');
                console.log('🔥 [DEBUG] FormData contents:', Array.from(formData.entries()));
                
                updateFormProgress(60, 'Submitting your information...');
                // Submit form data
                return fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                });
            })
            .then(response => {
                console.log('Form submission response status:', response.status);
                updateFormProgress(75, 'Processing your registration...');
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                updateFormProgress(90, 'Finalizing your account...');
                if (data.success) {
                    updateFormProgress(100, 'Account created successfully!');
                    
                    // Start countdown immediately after success
                    setTimeout(() => {
                        startSubmissionCountdown();
                    }, 500); // Brief delay to show 100% completion
                } else {
                    // For errors, hide immediately
                    hideProgressLoader();
                    alert('Registration failed: ' + (data.data ? data.data.message : data.message || 'Please check your details and try again.'));
                }
            })
            .catch(error => {
                console.error('Form submission error:', error);
                
                // Hide loading overlay immediately for errors
                hideProgressLoader();
                
                alert('An error occurred. Please try again.');
            });
        }

        // Test AJAX endpoint function
        function testAjaxEndpoint() {
            console.log('🔧 Testing AJAX endpoint...');
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=dtr_html_test'
            })
            .then(response => response.json())
            .then(data => {
                console.log('🔧 Test response:', data);
                if (data.success) {
                    alert('✅ AJAX Test Successful!\n\nEndpoint: WORKING\nMessage: ' + data.data.message + '\nTime: ' + data.data.timestamp);
                } else {
                    alert('❌ AJAX Test Failed!\n\nResponse: ' + JSON.stringify(data));
                }
            })
            .catch(error => {
                console.error('🔧 Test error:', error);
                alert('❌ AJAX Test Error!\n\n' + error.message);
            });
        }

        // Auto-fill form with test data
        function fillTestData() {
            document.getElementById('title').value = 'Mr';
            document.getElementById('firstName').value = 'John';
            document.getElementById('lastName').value = 'Doe';
            document.getElementById('email').value = 'john.doe@example.com';
            document.getElementById('employer').value = 'Test Company';
            document.getElementById('phone').value = '123-456-7890';
            document.getElementById('jobTitle').value = 'Developer';
            document.getElementById('country').value = 'United States';
            document.getElementById('city').value = 'Test City';
            document.getElementById('postcode').value = '12345';
            document.getElementById('password').value = 'TestPassword123!';
            
            // Check some boxes
            document.getElementById('dtr_marketing_1').checked = true; // Newsletter
            document.getElementById('dtr_marketing_2').checked = true; // Events
            document.getElementById('dtr_interest_1').checked = true;  // Business
            document.getElementById('dtr_interest_3').checked = true;  // Drugs & Therapies
            document.getElementById('terms').checked = true;
            
            // Trigger floating label updates
            initFloatingLabels();
            
            alert('✅ Test data filled in! You can now test form submission.');
        }

        // Add click handler for breadcrumbs
        document.querySelectorAll('.breadcrumbs li').forEach((li, index) => {
            li.addEventListener('click', () => {
                // Allow going to any step for better UX
                goToStep(index + 1);
            });
        });

        // Add click handler for blue container when in sidebar mode
        document.getElementById('blueContainer').addEventListener('click', () => {
            if (document.getElementById('blueContainer').classList.contains('sidebar')) {
                goToStep(1);
            }
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

        // Initialize
        updateProgress();
        updateBreadcrumbs();
        showStep(currentStep);
        initFloatingLabels();
        initPasswordStrength();
        initPasswordMatch();
        initDevModeToggle();

        // Debug logging
        console.log('%cHTML Form Registration System Ready', 'background: #4CAF50; color: white; padding: 8px 12px; border-radius: 4px; font-weight: bold;');
        
        // Check if DTR plugin is active by testing for WordPress actions
        fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=test_dtr_plugin', { method: 'GET' })
            .then(response => response.json())
            .then(data => {
                console.log('DTR Plugin Status:', data);
                if (!data.plugin_active) {
                    console.error('DTR Workbooks CRM plugin is not active or not loaded properly');
                }
                if (!data.actions_registered.html_form_submit) {
                    console.error('dtr_html_form_submit AJAX action is not registered');
                }
                if (!data.actions_registered.get_form_nonce) {
                    console.error('dtr_get_form_nonce AJAX action is not registered');
                }
            })
            .catch((error) => console.error('DTR plugin test failed:', error));

        // Login Modal Function - matches the gated-content.php functionality
        if (typeof openLoginModal === 'undefined') {
            window.openLoginModal = function() {
                console.log('🔓 Opening login modal...');
                
                // Try to find the theme's login modal
                const themeModal = document.getElementById('login-modal-container');
                if (themeModal) {
                    // Use theme's modal system
                    if (typeof themeModal.style !== 'undefined') {
                        themeModal.style.display = 'block';
                        themeModal.classList.add('active', 'show');
                        document.body.classList.add('modal-open');
                        console.log('✅ Theme login modal opened');
                        return;
                    }
                }
                
                // Fallback: Try to trigger any modal click handlers
                const modalTriggers = document.querySelectorAll('[data-toggle="modal"][data-target="#login-modal-container"], .login-modal-trigger, .modal-login-trigger');
                if (modalTriggers.length > 0) {
                    modalTriggers[0].click();
                    console.log('✅ Login modal triggered via click handler');
                    return;
                }
                
                // Last resort: redirect to login page
                console.log('⚠️ No login modal found, redirecting to login page');
                window.location.href = '<?php echo wp_login_url(get_permalink()); ?>';
            };
            console.log('✅ Login modal function defined');
        }
    </script>
    <?php
    return ob_get_clean();
}
?>