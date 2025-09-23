<?php
if (!defined('ABSPATH')) exit;

// Add shortcode for media planner registration form
add_shortcode('dtr_media_planner_registration', 'dtr_media_planner_registration_shortcode');

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

function dtr_media_planner_registration_shortcode($atts) {
    // Debug: Log that shortcode is being called
    error_log('DTR: media-planner-registration-shortcode called with attributes: ' . print_r($atts, true));
    
    // Parse shortcode attributes with proper defaults
    $atts = shortcode_atts(array(
        'title' => 'Download our media planner',
        'description' => 'Get access to our comprehensive media planning guide and industry insights.',
        'development_mode' => 'false',
    ), $atts, 'dtr_media_planner_registration');
    
    // Get form configuration from plugin settings
    $form_config = DTR_Workbooks_Integration::get_shortcode_form_config('media_planner');
    $development_mode = $form_config['dev_mode'] ?? false;
    
    // Check if form is enabled
    if (!$form_config['enabled']) {
        return '<div class="dtr-form-disabled">Media Planner form is currently disabled in plugin settings.</div>';
    }
    
    // Get current user data for pre-population if logged in
    $user_data = array();
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        
        $user_data = array(
            'first_name' => get_user_meta($user_id, 'first_name', true),
            'last_name' => get_user_meta($user_id, 'last_name', true),
            'email' => $current_user->user_email,
            'job_title' => get_user_meta($user_id, 'job_title', true),
            'organisation' => get_user_meta($user_id, 'employer_name', true),
            'city' => get_user_meta($user_id, 'town', true),
            'country' => get_user_meta($user_id, 'country', true),
            'phone' => get_user_meta($user_id, 'telephone', true),
        );
        
        // Debug log user data retrieval
        error_log('DTR: Retrieved user data for pre-population: ' . print_r($user_data, true));
    }

    ob_start();
    ?>
    <?php if ($development_mode): ?>
    <!-- Development Mode Indicator -->
    <div class="dev-mode-indicator active" id="devModeIndicator">
        🛠️ DEVELOPMENT MODE - Form Submission Disabled
    </div>
    <?php endif; ?>

    <div class="full-page form-container vertical-half-margin" id="media-planner-form">
        <h2><?php echo esc_html($atts['title']); ?></h2>
        
        <?php if ($development_mode): ?>
        <!-- Development Mode Toggle -->
        <div class="dev-mode-toggle">
            <h4>🛠️ Development Mode</h4>
            <label class="toggle-switch">
                <input type="checkbox" id="devModeToggle" checked>
                <span class="toggle-slider"></span>
            </label>
            <div class="toggle-labels">
                <span class="live">Live Form</span>
                <span class="dev">Dev Mode</span>
            </div>
            <button type="button" class="preview-loader-btn" onclick="previewLoader()">👁️ Preview Loader</button>
        </div>
        <?php endif; ?>
        
        <div class="form-container media-planner-registration-form">
            <form id="mediaPlannerForm">
                <!-- First Name -->
                <div class="form-row">
                    <div class="form-field floating-label one-half first">
                        <input type="text" id="firstName" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" " value="<?php echo esc_attr($user_data['first_name'] ?? ''); ?>">
                        <label for="firstName">First Name <span class="required">*</span></label>
                    </div>
                    
                    <!-- Last Name -->
                    <div class="form-field floating-label one-half">
                        <input type="text" id="lastName" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" " value="<?php echo esc_attr($user_data['last_name'] ?? ''); ?>">
                        <label for="lastName">Last Name <span class="required">*</span></label>
                    </div>
                </div>

                <!-- Email -->
                <div class="form-row">
                    <div class="form-field floating-label one-half first">
                        <input type="email" id="email" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" " value="<?php echo esc_attr($user_data['email'] ?? ''); ?>">
                        <label for="email">Email <span class="required">*</span></label>
                    </div>
                    
                    <!-- Job Title -->
                    <div class="form-field floating-label one-half">
                        <input type="text" id="jobTitle" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" " value="<?php echo esc_attr($user_data['job_title'] ?? ''); ?>">
                        <label for="jobTitle">Job Title <span class="required">*</span></label>
                    </div>
                </div>

                <!-- Organisation -->
                <div class="form-row">
                    <div class="form-field floating-label one-half first">
                        <?php if (is_user_logged_in()): ?>
                            <input type="text" id="organisation" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" " value="<?php echo esc_attr($user_data['organisation'] ?? ''); ?>">
                            <label for="organisation">Organisation <span class="required">*</span></label>
                        <?php else: ?>
                            <?php echo do_shortcode('[workbooks_employer_select]'); ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Town/City -->
                    <div class="form-field floating-label one-half">
                        <input type="text" id="city" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" " value="<?php echo esc_attr($user_data['city'] ?? ''); ?>">
                        <label for="city">Town/City <span class="required">*</span></label>
                    </div>
                </div>

                <!-- Country -->
                <div class="form-row">
                    <div class="form-field floating-label one-half first">
                            <select id="country" required uuid="<?php echo wp_generate_uuid4(); ?>">
                                <option value="">- Select Country -</option>
                                <?php
                                $countries = array(
                                    "Afghanistan","Åland Islands","Albania","Algeria","American Samoa","Andorra","Angola","Anguilla","Antarctica","Antigua and Barbuda","Argentina","Armenia","Aruba","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bermuda","Bhutan","Bolivia (Plurinational State of)","Bonaire, Sint Eustatius and Saba","Bosnia and Herzegovina","Botswana","Bouvet Island","Brazil","British Indian Ocean Territory","Brunei Darussalam","Bulgaria","Burkina Faso","Burundi","Cabo Verde","Cambodia","Cameroon","Canada","Cayman Islands","Central African Republic","Chad","Chile","China","Christmas Island","Cocos (Keeling) Islands","Colombia","Comoros","Congo","Congo (the Democratic Republic of the)","Cook Islands","Costa Rica","Côte d'Ivoire","Croatia","Cuba","Curaçao","Cyprus","Czechia","Denmark","Djibouti","Dominica","Dominican Republic","Ecuador","Egypt","El Salvador","EN","Equatorial Guinea","Eritrea","Estonia","Eswatini","Ethiopia","Falkland Islands (Malvinas)","Faroe Islands","Fiji","Finland","France","FX","French Guiana","French Polynesia","French Southern Territories","Gabon","Gambia","Georgia","Germany","Ghana","Gibraltar","Greece","Greenland","Grenada","Guadeloupe","Guam","Guatemala","Guinea","Guinea-Bissau","Guyana","Haiti","Heard Island and McDonald Islands","Holy See","Honduras","Hong Kong","Hungary","Iceland","India","Indonesia","Iran (Islamic Republic of)","Iraq","Ireland","Isle of Man","Israel","Italy","Jamaica","Japan","Jersey","Jordan","Kazakhstan","Kenya","Kiribati","Korea (the Democratic People's Republic of)","Korea (the Republic of)","Kuwait","Kyrgyzstan","Lao People's Democratic Republic","Latvia","Lebanon","Lesotho","Liberia","Libya","Liechtenstein","Lithuania","Luxembourg","Macao","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Marshall Islands","Martinique","Mauritania","Mauritius","Mayotte","Mexico","Micronesia (Federated States of)","Moldova (the Republic of)","Monaco","Mongolia","Montenegro","Montserrat","Morocco","Mozambique","Myanmar","Namibia","Nauru","Nepal","Netherlands","AN","New Caledonia","New Zealand","Nicaragua","Niger","Nigeria","Niue","Norfolk Island","North Macedonia","Northern Mariana Islands","Norway","Oman","Pakistan","Palau","Palestine, State of","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Pitcairn","Poland","Portugal","Puerto Rico","Qatar","RK","Réunion","Romania","Russian Federation","Rwanda","Saint Martin (French part)","Saint Barthélemy","Saint Helena, Ascension and Tristan da Cunha","Saint Kitts and Nevis","Saint Lucia","Saint Pierre and Miquelon","Saint Vincent and the Grenadines","Samoa","San Marino","Sao Tome and Principe","Saudi Arabia","Senegal","Serbia","Seychelles","Sierra Leone","Singapore","Sint Maarten (Dutch part)","Slovakia","Slovenia","Solomon Islands","Somalia","South Africa","South Georgia and the South Sandwich Islands","South Sudan","Spain","Sri Lanka","Sudan","Suriname","Svalbard and Jan Mayen","Sweden","Switzerland","Syrian Arab Republic","Taiwan, Province of China","Tajikistan","Tanzania, United Republic of","Thailand","Timor-Leste","Timor-Leste","Togo","Tokelau","Tonga","Trinidad and Tobago","Tunisia","Türkiye","Turkmenistan","Turks and Caicos Islands","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States","United States Minor Outlying Islands","Uruguay","Uzbekistan","Vanuatu","Venezuela (Bolivarian Republic of)","Viet Nam","Virgin Islands (British)","Virgin Islands (U.S.)","Wallis and Futuna","Western Sahara","Yemen","Zambia","Zimbabwe"
                                );
                                $selected_country = esc_attr($user_data['country'] ?? '');
                                foreach ($countries as $country) {
                                    $selected = ($country === $selected_country) ? 'selected="selected"' : '';
                                    echo "<option value=\"$country\" $selected>$country</option>\n";
                                }
                                ?>
                            </select>
                            <label for="country">Country <span class="required">*</span></label>
                    </div>
                    
                    <!-- Phone Number -->
                    <div class="form-field floating-label one-half">
                        <input type="tel" id="phone" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" " value="<?php echo esc_attr($user_data['phone'] ?? ''); ?>">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                    </div>
                </div>

                <!-- Can we help further? -->
                <div class="form-row">
                    <div class="form-field floating-label one-half first">
                        <select id="canWeHelpFurther" required uuid="<?php echo wp_generate_uuid4(); ?>">
                            <option value="Speak to a representative" selected="selected">Speak to a representative</option>
                            <option value="Request a quote">Request a quote</option>
                            <option value="Ready to book my campaign">Ready to book my campaign</option>
                        </select>
                        <label for="canWeHelpFurther">Can we help further? <span class="required">*</span></label>
                    </div>
                </div>

                <!-- Privacy Policy -->
                <!-- <div class="form-row">
                    <div class="form-field">
                        <p>By clicking download, you consent to Drug Target Review's <a href="<?php echo home_url('/terms-conditions'); ?>" target="_blank">terms and conditions</a> and <a href="<?php echo home_url('/privacy-policy'); ?>" target="_blank">privacy policy</a>. Your information will be processed in accordance with GDPR and you can unsubscribe at any time.</p>
                    </div>
                </div> -->

                <!-- Consent Checkbox -->
                <div class="checkbox-group consent-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="consent" required uuid="<?php echo wp_generate_uuid4(); ?>">
                        <label for="consent" class="checkbox-label">
                            By clicking download, you consent to Drug Target Review's <a href="<?php echo home_url('/terms-conditions'); ?>" target="_blank">terms and conditions</a> and <a href="<?php echo home_url('/privacy-policy'); ?>" target="_blank">privacy policy</a>. Your information will be processed in accordance with GDPR and you can unsubscribe at any time. <span class="required">*</span>
                        </label>
                    </div>
                </div>

                <!-- Hidden Fields (matching the Ninja Form) -->
                <input type="hidden" id="eventId" name="event_id" value="5137">
                <input type="hidden" id="dataSourceDetail" name="data_source_detail" value="DTR-MEDIA-PLANNER-2025">
                <input type="hidden" id="downloadName" name="download_name" value="DTR-MEDIA-PLANNER-2025">
                <input type="hidden" id="type" name="type" value="Event Registration">
                <input type="hidden" id="leadSourceType" name="lead_source_type" value="Event Registration">
                <input type="hidden" id="cfCustomerOrderBrandForPdf" name="cf_customer_order_brand_for_pdf" value="Drug Target Review">
                <input type="hidden" id="campaignName" name="campaign_name" value="Media Planner 2025">
                <input type="hidden" id="cfCustomerOrderLineItemBrand" name="cf_customer_order_line_item_brand" value="DTR">
                <input type="hidden" id="cfCustomerOrderLineItemRpProductDelegate" name="cf_customer_order_line_item_rp_product_delegate" value="Media Planner 2025">
                <input type="hidden" id="cfCustomerOrderLineItemSubproductEvent" name="cf_customer_order_line_item_subproduct_event" value="FOC">
                <input type="hidden" id="cfCustomerOrderLineItemStreams" name="cf_customer_order_line_item_streams" value="N/A">
                <input type="hidden" id="cfCustomerOrderLineItemCampaignDelegate" name="cf_customer_order_line_item_campaign_delegate" value="Media Planner 2025">
                <input type="hidden" id="cfCustomerOrderLineItemCampaignReference2" name="cf_customer_order_line_item_campaign_reference_2" value="CAMP-41496">
                <input type="hidden" id="cfCustomerOrderLineItemDelegateType" name="cf_customer_order_line_item_delegate_type" value="Primary">
                <input type="hidden" id="cfCustomerOrderLineItemDelegateType608" name="cf_customer_order_line_item_delegate_type_608" value="Delegate">
                <input type="hidden" id="cfCustomerOrderLineItemDelegateTicketType" name="cf_customer_order_line_item_delegate_ticket_type" value="VIP">
                <input type="hidden" id="cfCustomerOrderLineItemAttended" name="cf_customer_order_line_item_attended" value="No">
                <input type="hidden" id="cfCustomerOrderLineItemDinner" name="cf_customer_order_line_item_dinner" value="N/A">
                <input type="hidden" id="assignedTo" name="assigned_to" value="Unassigned">
                <input type="hidden" id="webKey" name="web_key" value="663d4d9f011e521baf6fc92150976b453f3b0a72">
                <input type="hidden" id="successUrl" name="success_url" value="https://www.drugtargetreview.com">
                <input type="hidden" id="failureUrl" name="failure_url" value="https://www.drugtargetreview.com">
                <input type="hidden" id="salesLeadRating" name="sales_lead_rating" value="Warm">
                <input type="hidden" id="leadType" name="lead_type" value="Reader">
                <input type="hidden" id="dtrSubscriberType" name="dtr_subscriber_type" value="Prospect">
                <input type="hidden" id="productMix" name="product_mix" value="">
                <input type="hidden" id="name1" name="name1" value="">
                <input type="hidden" id="name2" name="name2" value="">
                <input type="hidden" id="orgLeadPartyEmail" name="org_lead_party_email" value="">

                <!-- Submit Button -->
                <div class="form-row">
                    <div class="form-field">
                        <button type="button" class="button btn-small global btn-rounded btn-blue shimmer-effect shimmer-slow text-left chevron right" onclick="submitMediaPlannerForm()">Download</button>
                    </div>
                </div>

                <?php if ($development_mode): ?>
                <!-- Debug Test Buttons -->
                <div style="margin-top: 15px; text-align: center;">
                    <button type="button" onclick="testAjaxEndpoint()" class="button" style="background: #ff6600; color: white; padding: 8px 16px; font-size: 12px; border-radius: 4px; margin-right: 10px;">
                        🔧 Test AJAX Connection
                    </button>
                    <button type="button" onclick="fillTestDataMediaPlanner()" class="button" style="background: #28a745; color: white; padding: 8px 16px; font-size: 12px; border-radius: 4px;">
                        📝 Fill Test Data
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="form-loader-overlay" id="formLoaderOverlay" style="display: none;">
        <div class="loader-content">
            <h2>Processing Your Request</h2>
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
            <p id="loaderStatusText">Preparing your media planner...</p>
        </div>
    </div>

    <script>
        // Development mode is controlled by plugin settings
        let devModeActive = <?php echo $development_mode ? 'true' : 'false'; ?>;

        <?php if ($development_mode): ?>
        // Development mode toggle (only available in dev mode)
        function initDevModeToggle() {
            var toggle = document.getElementById('devModeToggle');
            var indicator = document.getElementById('devModeIndicator');

            if (toggle && indicator) {
                // Start with dev mode active since we're in development mode
                devModeActive = true;
                toggle.checked = true;
                indicator.classList.add('active');
                removeRequiredFields();
                
                toggle.addEventListener('change', function() {
                    devModeActive = toggle.checked;
                    if (devModeActive) {
                        indicator.classList.add('active');
                        removeRequiredFields();
                        console.log('🛠️ Development Mode: ON - Form submissions disabled, required fields removed');
                    } else {
                        indicator.classList.remove('active');
                        restoreRequiredFields();
                        console.log('🟢 Live Mode: ON - Form submissions enabled, required fields restored');
                    }
                });
            }
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
            originalRequiredFields = [];
            const requiredFields = document.querySelectorAll('#mediaPlannerForm input[required], #mediaPlannerForm select[required]');
            requiredFields.forEach(field => {
                originalRequiredFields.push(field);
                field.removeAttribute('required');
                field.classList.add('dev-mode-optional');
            });
        }

        function restoreRequiredFields() {
            originalRequiredFields.forEach(field => {
                field.setAttribute('required', 'required');
                field.classList.remove('dev-mode-optional');
            });
        }

        function validateMediaPlannerForm() {
            if (devModeActive) {
                return true;
            }

            const requiredFields = document.querySelectorAll('#mediaPlannerForm input[required], #mediaPlannerForm select[required]');
            for (let field of requiredFields) {
                if (!field.value.trim()) {
                    alert('Please fill in all required fields marked with *');
                    field.focus();
                    return false;
                }
            }

            // Validate email format
            const emailField = document.getElementById('email');
            if (emailField && emailField.value && !isValidEmail(emailField.value)) {
                alert('Please enter a valid email address');
                emailField.focus();
                return false;
            }

            return true;
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
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
                statusText.textContent = 'Preparing your media planner...';
                countdownContainer.classList.remove('active');
                
                // Start progress simulation
                simulateFormProgress();
            }
        }

        function simulateFormProgress() {
            const progressFill = document.getElementById('progressCircleFill');
            const statusText = document.getElementById('loaderStatusText');
            
            const stages = [
                { progress: 'progress-25', text: 'Validating your information...', delay: 2000 },
                { progress: 'progress-50', text: 'Processing request...', delay: 2500 },
                { progress: 'progress-75', text: 'Preparing media planner...', delay: 2000 },
                { progress: 'progress-100', text: 'Submission Successful!', delay: 1500 }
            ];
            
            let currentStage = 0;
            
            function nextStage() {
                if (currentStage < stages.length) {
                    const stage = stages[currentStage];
                    progressFill.className = `progress-circle-fill ${stage.progress}`;
                    statusText.textContent = stage.text;
                    currentStage++;
                    
                    setTimeout(nextStage, stage.delay);
                } else {
                    // Start countdown after completion
                    setTimeout(() => startCountdown(), 500);
                }
            }
            
            // Start first stage
            setTimeout(nextStage, 500);
        }

        function startCountdown() {
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
                    if (countdownMessage) countdownMessage.textContent = 'Download Ready!';
                    
                    // Keep overlay visible - do not hide until redirect happens
                    // The overlay will naturally disappear when the page redirects
                }
            }
            
            showNextCount();
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

        function previewLoader() {
            // Show overlay without triggering simulateFormProgress()
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
                statusText.textContent = 'Preparing your media planner...';
                countdownContainer.classList.remove('active');
            }
            
            // Simulate the actual submission flow for preview using updateFormProgress
            setTimeout(() => updateFormProgress(25, 'Validating security credentials...'), 500);
            setTimeout(() => updateFormProgress(40, 'Security validation complete...'), 1500);
            setTimeout(() => updateFormProgress(50, 'Preparing media planner request...'), 2000);
            setTimeout(() => updateFormProgress(60, 'Submitting your information...'), 2500);
            setTimeout(() => updateFormProgress(75, 'Processing your request...'), 3500);
            setTimeout(() => updateFormProgress(90, 'Finalizing media planner...'), 4500);
            setTimeout(() => {
                updateFormProgress(100, 'Submission Successful!');
                setTimeout(() => {
                    startCountdown();
                    // Add redirect after countdown completes (same timing as real submission)
                    setTimeout(() => {
                        window.location.href = '/download-media-planner/';
                    }, 5000); // Redirect 5 seconds after countdown starts
                }, 500);
            }, 5000);
        }

        function submitMediaPlannerForm() {
            console.log('🔥 [DEBUG] ===== MEDIA PLANNER FORM SUBMISSION START =====');
            
            if (!validateMediaPlannerForm()) {
                console.log('🔥 [DEBUG] Form validation failed');
                return;
            }

            // Check if development mode is active
            if (devModeActive) {
                alert('🛠️ Development Mode Active\n\nForm submission is disabled for testing purposes.\nAll form validation and styling can be tested without affecting the live system.\n\nToggle off Development Mode to enable live form submission.');
                return;
            }

            // Show loading overlay with progress
            showProgressLoader();

            // Collect form data
            const formData = new FormData();
            
            // Add WordPress AJAX action (you'll need to create this handler)
            formData.append('action', 'dtr_media_planner_form_submit');
            
            // Personal information
            formData.append('firstName', document.getElementById('firstName')?.value || '');
            formData.append('lastName', document.getElementById('lastName')?.value || '');
            formData.append('email', document.getElementById('email')?.value || '');
            formData.append('jobTitle', document.getElementById('jobTitle')?.value || '');
            
            // Handle organisation field - either from input (logged in) or workbooks select (not logged in)
            const organisationInput = document.getElementById('organisation');
            const workbooksSelect = document.querySelector('select[name="employer_name"]');
            let organisationValue = '';
            
            if (organisationInput) {
                // Logged in - use text input value
                organisationValue = organisationInput.value || '';
            } else if (workbooksSelect) {
                // Not logged in - use workbooks employer select text
                const selectedOption = workbooksSelect.options[workbooksSelect.selectedIndex];
                organisationValue = (selectedOption && selectedOption.text !== '- Select Employer -') ? selectedOption.text : '';
            }
            
            formData.append('organisation', organisationValue);
            formData.append('city', document.getElementById('city')?.value || '');
            formData.append('country', document.getElementById('country')?.value || '');
            formData.append('phone', document.getElementById('phone')?.value || '');
            formData.append('canWeHelpFurther', document.getElementById('canWeHelpFurther')?.value || '');
            formData.append('consent', document.getElementById('consent')?.checked ? '1' : '');

            // Hidden fields
            formData.append('event_id', document.getElementById('eventId')?.value || '');
            formData.append('data_source_detail', document.getElementById('dataSourceDetail')?.value || '');
            formData.append('download_name', document.getElementById('downloadName')?.value || '');
            formData.append('type', document.getElementById('type')?.value || '');
            formData.append('lead_source_type', document.getElementById('leadSourceType')?.value || '');
            formData.append('cf_customer_order_brand_for_pdf', document.getElementById('cfCustomerOrderBrandForPdf')?.value || '');
            formData.append('campaign_name', document.getElementById('campaignName')?.value || '');
            formData.append('cf_customer_order_line_item_brand', document.getElementById('cfCustomerOrderLineItemBrand')?.value || '');
            formData.append('cf_customer_order_line_item_rp_product_delegate', document.getElementById('cfCustomerOrderLineItemRpProductDelegate')?.value || '');
            formData.append('cf_customer_order_line_item_subproduct_event', document.getElementById('cfCustomerOrderLineItemSubproductEvent')?.value || '');
            formData.append('cf_customer_order_line_item_streams', document.getElementById('cfCustomerOrderLineItemStreams')?.value || '');
            formData.append('cf_customer_order_line_item_campaign_delegate', document.getElementById('cfCustomerOrderLineItemCampaignDelegate')?.value || '');
            formData.append('cf_customer_order_line_item_campaign_reference_2', document.getElementById('cfCustomerOrderLineItemCampaignReference2')?.value || '');
            formData.append('cf_customer_order_line_item_delegate_type', document.getElementById('cfCustomerOrderLineItemDelegateType')?.value || '');
            formData.append('cf_customer_order_line_item_delegate_type_608', document.getElementById('cfCustomerOrderLineItemDelegateType608')?.value || '');
            formData.append('cf_customer_order_line_item_delegate_ticket_type', document.getElementById('cfCustomerOrderLineItemDelegateTicketType')?.value || '');
            formData.append('cf_customer_order_line_item_attended', document.getElementById('cfCustomerOrderLineItemAttended')?.value || '');
            formData.append('cf_customer_order_line_item_dinner', document.getElementById('cfCustomerOrderLineItemDinner')?.value || '');
            formData.append('assigned_to', document.getElementById('assignedTo')?.value || '');
            formData.append('web_key', document.getElementById('webKey')?.value || '');
            formData.append('success_url', document.getElementById('successUrl')?.value || '');
            formData.append('failure_url', document.getElementById('failureUrl')?.value || '');
            formData.append('sales_lead_rating', document.getElementById('salesLeadRating')?.value || '');
            formData.append('lead_type', document.getElementById('leadType')?.value || '');
            formData.append('dtr_subscriber_type', document.getElementById('dtrSubscriberType')?.value || '');
            formData.append('product_mix', document.getElementById('productMix')?.value || '');
            formData.append('name1', document.getElementById('name1')?.value || '');
            formData.append('name2', document.getElementById('name2')?.value || '');
            formData.append('org_lead_party_email', document.getElementById('orgLeadPartyEmail')?.value || '');
            
            console.log('🔥 [DEBUG] About to submit form data');
            console.log('🔥 [DEBUG] FormData contents:', Array.from(formData.entries()));

            // Get WordPress nonce first, then submit
            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=dtr_get_form_nonce', {
                method: 'GET'
            })
            .then(response => {
                console.log('Nonce response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('🔥 [DEBUG] Nonce data received:', data);
                if (data.success && data.data && data.data.nonce) {
                    formData.append('nonce', data.data.nonce);
                } else if (data.nonce) {
                    formData.append('nonce', data.nonce);
                } else {
                    throw new Error('No nonce received from server');
                }
                
                // Submit form data
                return fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                });
            })
            .then(response => {
                console.log('Form submission response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Start countdown immediately after success
                    startCountdown();
                    
                    // Keep overlay visible and redirect after countdown completes
                    setTimeout(() => {
                        // Redirect to media planner download page
                        window.location.href = '/download-media-planner/';
                    }, 5000); // Redirect 5 seconds after countdown starts (3s countdown + 2s for final message)
                } else {
                    hideProgressLoader();
                    alert('Request failed: ' + (data.data ? data.data.message : data.message || 'Please check your details and try again.'));
                }
            })
            .catch(error => {
                console.error('Form submission error:', error);
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
        function fillTestDataMediaPlanner() {
            document.getElementById('firstName').value = 'Jane';
            document.getElementById('lastName').value = 'Smith';
            document.getElementById('email').value = 'jane.smith@example.com';
            document.getElementById('jobTitle').value = 'Marketing Manager';
            document.getElementById('organisation').value = 'Test Pharma Company';
            document.getElementById('city').value = 'London';
            document.getElementById('country').value = 'United Kingdom';
            document.getElementById('phone').value = '+44-1234-567890';
            document.getElementById('canWeHelpFurther').value = 'Request a quote';
            document.getElementById('consent').checked = true;
            
            // Trigger floating label updates
            initFloatingLabels();
            
            alert('✅ Test data filled in! You can now test form submission.');
        }

        // Initialize floating labels
        function initFloatingLabels() {
            const floatingFields = document.querySelectorAll('.floating-label input, .floating-label select');
            
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

        // Initialize workbooks employer select mapping
        function initWorkbooksEmployerMapping() {
            // For not logged in users, the workbooks employer select is shown instead of text input
            const workbooksSelect = document.querySelector('select[name="employer_name"]');
            const organisationInput = document.getElementById('organisation');
            
            if (workbooksSelect) {
                // If there's an organisation input (logged in user), this shouldn't happen
                if (organisationInput) {
                    console.log('🏢 Both workbooks select and organisation input found - this should not happen');
                } else {
                    // If there's no organisation input (not logged in), we'll capture the employer from the select
                    console.log('🏢 Workbooks employer select detected for non-logged-in user');
                }
            } else if (organisationInput) {
                // Logged in user - just the text input, no mapping needed
                console.log('🏢 Organisation text input detected for logged-in user');
            }
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
                // For regular inputs
                if (hasValue || isFocused) {
                    fieldContainer.classList.add('floating-active');
                } else {
                    fieldContainer.classList.remove('floating-active');
                }
            }
        }

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initFloatingLabels();
            initDevModeToggle();
            initWorkbooksEmployerMapping();
        });

        // Debug logging
        console.log('%cMedia Planner Registration System Ready', 'background: #4CAF50; color: white; padding: 8px 12px; border-radius: 4px; font-weight: bold;');
        
    </script>
    <?php
    return ob_get_clean();
}
?>