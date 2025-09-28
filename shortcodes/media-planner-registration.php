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
        <!-- <div class="dev-mode-toggle">
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
        </div> -->
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
                    
                            <input type="text" id="organisation" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" " value="<?php echo esc_attr($user_data['organisation'] ?? ''); ?>">
                            <label for="organisation">Organisation <span class="required">*</span></label>
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
                <!-- <div style="margin-top: 15px; text-align: center;">
                    <button type="button" onclick="testAjaxEndpoint()" class="button" style="background: #ff6600; color: white; padding: 8px 16px; font-size: 12px; border-radius: 4px; margin-right: 10px;">
                        🔧 Test AJAX Connection
                    </button>
                    <button type="button" onclick="fillTestDataMediaPlanner()" class="button" style="background: #28a745; color: white; padding: 8px 16px; font-size: 12px; border-radius: 4px;">
                        📝 Fill Test Data
                    </button>
                </div> -->
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="form-loader-overlay" id="formLoaderOverlay" style="display: none;">
        <div class="progress-card">
            <div class="progress-body">
                <div class="circular-progress">
                    <svg class="progress-svg" viewBox="0 0 100 100">
                        <circle class="progress-track" cx="50" cy="50" r="45" />
                        <circle class="progress-indicator" cx="50" cy="50" r="45" id="progressCircle" />
                    </svg>
                    <div class="progress-value" id="progressValue">0%</div>
                </div>
            </div>
            <div class="progress-footer">
                <div class="progress-chip">
                    Processing your request...
                </div>
            </div>
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
            console.log('🚀 showProgressLoader called');
            
            const loadingOverlay = document.getElementById('formLoaderOverlay');
            const progressCircle = document.getElementById('progressCircle');
            const progressValue = document.getElementById('progressValue');
            
            console.log('Loader elements found:', {
                loadingOverlay: !!loadingOverlay,
                progressCircle: !!progressCircle,
                progressValue: !!progressValue
            });
            
            if (loadingOverlay) {
                console.log('✅ Showing loading overlay');
                loadingOverlay.style.display = 'flex';
                
                // Set header z-index to ensure overlay appears above it
                const header = document.querySelector('header');
                if (header) {
                    header.style.zIndex = '1';
                }
                
                // Reset progress
                if (progressCircle) {
                    progressCircle.style.strokeDashoffset = '283'; // 0%
                    console.log('✅ Progress circle reset to 0%');
                }
                if (progressValue) {
                    progressValue.textContent = '0%';
                    console.log('✅ Progress value reset to 0%');
                }
                
                // Trigger fade-in animation
                setTimeout(() => {
                    loadingOverlay.classList.add('show');
                    console.log('✅ Loading overlay show class added');
                }, 10);
            } else {
                console.error('❌ Loading overlay element not found!');
            }
        }

        // Real-time progress updater that matches actual submission stages
        function updateFormProgress(stage, message) {
            console.log('🔄 updateFormProgress ENTRY:', stage, message);
            
            const progressCircle = document.getElementById('progressCircle');
            const progressValue = document.getElementById('progressValue');
            
            console.log('Elements found:', !!progressCircle, !!progressValue);
            
            if (progressCircle && progressValue) {
                // Calculate stroke offset (283 is full circle, 0 is 100%)
                const offset = 283 - (stage / 100) * 283;
                console.log('Calculated offset:', offset, 'for', stage + '%');
                
                // Apply the changes
                progressCircle.style.strokeDashoffset = offset.toString();
                progressValue.textContent = stage + '%';
                
                console.log('✅ Progress updated to', stage + '%');
            } else {
                console.error('❌ Progress elements not found!');
            }
            
            console.log('🔄 updateFormProgress EXIT');
        }

        // Make function globally accessible for debugging
        window.updateFormProgress = updateFormProgress;
        
        // Create a simple test version to isolate issues
        window.testUpdateProgress = function(stage, message) {
            console.log('🧪 testUpdateProgress called:', stage, message);
            
            const progressCircle = document.getElementById('progressCircle');
            const progressValue = document.getElementById('progressValue');
            
            if (progressCircle && progressValue) {
                const offset = 283 - (stage / 100) * 283;
                progressCircle.style.strokeDashoffset = offset.toString();
                progressValue.textContent = stage + '%';
                console.log('✅ Test function completed:', stage + '%');
                return true;
            } else {
                console.error('❌ Test function: elements not found');
                return false;
            }
        };
        
        // Create a minimal version of the original function for debugging
        window.minimalUpdateProgress = function(stage, message) {
            console.log('🔍 minimalUpdateProgress ENTRY:', stage, message);
            
            const progressCircle = document.getElementById('progressCircle');
            const progressValue = document.getElementById('progressValue');
            
            console.log('🔍 Elements found:', !!progressCircle, !!progressValue);
            
            if (progressCircle && progressValue) {
                const offset = 283 - (stage / 100) * 283;
                console.log('🔍 Calculated offset:', offset);
                
                progressCircle.style.strokeDashoffset = offset.toString();
                progressValue.textContent = stage + '%';
                
                console.log('🔍 Changes applied');
            }
            
            console.log('🔍 minimalUpdateProgress EXIT');
        };

        // Progressive steps for form submission process (legacy support)
        function updateProgressStep(step) {
            const steps = {
                start: { progress: 10, text: 'Starting submission...' },
                validation: { progress: 25, text: 'Validating your information...' },
                processing: { progress: 50, text: 'Processing request...' },
                preparing: { progress: 75, text: 'Preparing media planner...' },
                completed: { progress: 100, text: 'Submission Successful!' }
            };
            
            const currentStep = steps[step] || steps.start;
            updateFormProgress(currentStep.progress, currentStep.text);
        }

        function slideOutLoader() {
            const loadingOverlay = document.getElementById('formLoaderOverlay');
            if (loadingOverlay) {
                loadingOverlay.classList.add('fade-out');
                
                // Hide completely after wipe animation completes (1s + 100ms buffer)
                setTimeout(() => {
                    loadingOverlay.style.display = 'none';
                    loadingOverlay.classList.remove('show', 'fade-out');
                    
                    // Restore header z-index
                    const header = document.querySelector('header');
                    if (header) {
                        header.style.zIndex = '';
                    }
                }, 1100);
            }
        }

        function startCountdown() {
            // Show wipe animation
            slideOutLoader();
            setTimeout(() => {
                // Redirect to download page
                window.location.href = '/download-media-planner/';
            }, 1100);
        }
        
        // Function to stop countdown if needed (e.g., on errors)
        function stopCountdown() {
            // Legacy function - no longer needed with new loader
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
                
                // Reset any active flags
                window.submissionInProgress = false;
                
                // Clear any pending timeouts from the preview function
                if (window.previewTimeouts) {
                    window.previewTimeouts.forEach(timeout => clearTimeout(timeout));
                    window.previewTimeouts = [];
                }
            }
        }

        // Make all functions globally accessible for debugging
        window.showProgressLoader = showProgressLoader;
        window.hideProgressLoader = hideProgressLoader;



        function previewLoader() {
            showProgressLoader();
            
            // Clear any existing timeouts to prevent duplicate animations
            if (window.previewTimeouts) {
                window.previewTimeouts.forEach(timeout => clearTimeout(timeout));
            }
            window.previewTimeouts = [];
            
            // Simulate the actual submission flow for preview
            const addTimeout = (fn, delay) => {
                const id = setTimeout(fn, delay);
                window.previewTimeouts.push(id);
                return id;
            };
            
            addTimeout(() => updateFormProgress(25, 'Validating your information...'), 500);
            addTimeout(() => updateFormProgress(40, 'Security validation complete...'), 1000);
            addTimeout(() => updateFormProgress(60, 'Processing request...'), 1500);
            addTimeout(() => updateFormProgress(75, 'Preparing media planner...'), 2500);
            addTimeout(() => updateFormProgress(90, 'Finalizing download...'), 3500);
            addTimeout(() => {
                updateFormProgress(100, 'Submission Successful!');
                
                // Wait a moment before starting countdown
                addTimeout(() => {
                    startCountdown();
                }, 500);
            }, 4500);
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
            updateProgressStep('start');

            // Collect form data
            const formData = new FormData();
            
            // Add WordPress AJAX action
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
            
            // Track submission to prevent duplicate attempts
            if (window.submissionInProgress) {
                console.log('Submission already in progress, ignoring duplicate attempt');
                return;
            }
            window.submissionInProgress = true;

            // Stage 1: Initial validation
            setTimeout(() => updateFormProgress(25, 'Processing your request...'), 500);

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
                // Stage 2: Security validation complete
                setTimeout(() => updateFormProgress(40, 'Processing your request...'), 1000);
                
                if (data.success && data.data && data.data.nonce) {
                    formData.append('nonce', data.data.nonce);
                } else if (data.nonce) {
                    formData.append('nonce', data.nonce);
                } else {
                    throw new Error('No nonce received from server');
                }
                
                // Stage 3: Processing request
                setTimeout(() => updateFormProgress(60, 'Processing your request...'), 1500);
                
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
                // Stage 4: Preparing download
                updateFormProgress(75, 'Processing your request...');
                
                setTimeout(() => {
                    if (data.success) {
                        // Stage 5: Finalizing
                        updateFormProgress(90, 'Processing your request...');
                        
                        setTimeout(() => {
                            // Stage 6: Complete
                            updateFormProgress(100, 'Processing your request...');
                            
                            setTimeout(() => {
                                // Start countdown (which now triggers wipe animation and redirect)
                                startCountdown();
                            }, 500);
                        }, 500);
                    } else {
                        hideProgressLoader();
                        window.submissionInProgress = false;
                        alert('Request failed: ' + (data.data ? data.data.message : data.message || 'Please check your details and try again.'));
                    }
                }, 1000);
            })
            .catch(error => {
                console.error('Form submission error:', error);
                hideProgressLoader();
                window.submissionInProgress = false;
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
    
    <style>
    /* Overlay CSS Styles - Matching Lead Generation and Webinar Forms */
    .form-loader-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #871f80 0%, #4f074aff 100%);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        backdrop-filter: blur(8px);
        opacity: 1;
        transition: opacity 0.5s ease-in;
    }

    .form-loader-overlay.show {
        opacity: 1;
    }

    .form-loader-overlay.fade-out {
        animation: wipeOut 1s ease-out forwards;
    }
    
    @keyframes wipeOut {
        0% {
            opacity: 1;
            transform: translateY(0);
        }
        100% {
            opacity: 0;
            transform: translateY(-100%);
        }
    }

    .progress-card {
        width: 320px;
        height: 320px;
        border-radius: 20px;
        border: none;
        display: flex;
        flex-direction: column;
        transform: scale(0.8);
        opacity: 0;
        transition: all 0.6s ease-out;
    }

    .form-loader-overlay.show .progress-card {
        transform: scale(1);
        opacity: 1;
    }

    .progress-body {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        padding-bottom: 0;
    }

    .circular-progress {
        position: relative;
        width: 144px;
        height: 144px;
    }

    .progress-svg {
        width: 144px;
        height: 144px;
        transform: rotate(-90deg);
        filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
    }

    .progress-track {
        fill: none;
        stroke: rgba(255, 255, 255, 0.1);
        stroke-width: 4;
    }

    .progress-indicator {
        fill: none;
        stroke: white;
        stroke-width: 4;
        stroke-linecap: round;
        stroke-dasharray: 283; /* 2π × 45 */
        stroke-dashoffset: 283; /* Initial state - will be overridden by JavaScript */
        transition: stroke-dashoffset 0.5s ease;
        transform-origin: center;
        transform: rotate(-90deg); /* Start progress from top */
    }

    .progress-value {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 24px;
        font-weight: 600;
        color: white;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .progress-footer {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 16px;
        padding-top: 0;
    }

    .progress-chip {
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        padding: 8px 16px;
        background: rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.9);
        font-size: 12px;
        font-weight: 600;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }
    </style>
    <?php
    return ob_get_clean();
}
?>