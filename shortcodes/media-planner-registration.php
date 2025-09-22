<?php
if (!defined('ABSPATH')) exit;

// Add shortcode for media planner registration form
add_shortcode('dtr_media_planner_registration', 'dtr_media_planner_registration_shortcode');

// Enqueue custom stylesheet for shortcodes
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('media-planner-registration-form', plugin_dir_url(__FILE__) . '../assets/css/membership-registration-form.css', [], null);
});

function dtr_media_planner_registration_shortcode($atts) {
    // Debug: Log that shortcode is being called
    error_log('DTR: media-planner-registration-shortcode called with attributes: ' . print_r($atts, true));
    
    // Parse shortcode attributes with proper defaults
    $atts = shortcode_atts(array(
        'title' => 'Download our media planner',
        'description' => 'Get access to our comprehensive media planning guide and industry insights.',
        'development_mode' => 'false',
    ), $atts, 'dtr_media_planner_registration');
    
    $development_mode = filter_var($atts['development_mode'], FILTER_VALIDATE_BOOLEAN);

    ob_start();
    ?>
    <!-- Development Mode Indicator -->
    <div class="dev-mode-indicator" id="devModeIndicator">
        🛠️ DEVELOPMENT MODE - Form Submission Disabled
    </div>

    <div class="full-page form-container vertical-half-margin" id="media-planner-form">
        <h2><?php echo esc_html($atts['title']); ?></h2>
        
        <!-- Development Mode Toggle -->
        <div class="dev-mode-toggle">
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
        </div>
        
        <div class="form-container media-planner-registration-form">
            <form id="mediaPlannerForm">
                <!-- First Name -->
                <div class="form-row">
                    <div class="form-field floating-label one-half first">
                        <input type="text" id="firstName" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                        <label for="firstName">First Name <span class="required">*</span></label>
                    </div>
                    
                    <!-- Last Name -->
                    <div class="form-field floating-label one-half">
                        <input type="text" id="lastName" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                        <label for="lastName">Last Name <span class="required">*</span></label>
                    </div>
                </div>

                <!-- Email -->
                <div class="form-row">
                    <div class="form-field floating-label one-half first">
                        <input type="email" id="email" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                        <label for="email">Email <span class="required">*</span></label>
                    </div>
                    
                    <!-- Job Title -->
                    <div class="form-field floating-label one-half">
                        <input type="text" id="jobTitle" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                        <label for="jobTitle">Job Title <span class="required">*</span></label>
                    </div>
                </div>

                <!-- Organisation -->
                <div class="form-row">
                    <div class="form-field floating-label one-half first">
                        <input type="text" id="organisation" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                        <label for="organisation">Organisation <span class="required">*</span></label>
                    </div>
                    
                    <!-- Town/City -->
                    <div class="form-field floating-label one-half">
                        <input type="text" id="city" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
                        <label for="city">Town/City <span class="required">*</span></label>
                    </div>
                </div>

                <!-- Country -->
                <div class="form-row">
                    <div class="form-field floating-label one-half first">
                        <select id="country" required uuid="<?php echo wp_generate_uuid4(); ?>">
                            <option value="">- Select Country -</option>
                            <option value="Afghanistan">Afghanistan</option>
                            <option value="Åland Islands">Åland Islands</option>
                            <option value="Albania">Albania</option>
                            <option value="Algeria">Algeria</option>
                            <option value="American Samoa">American Samoa</option>
                            <option value="Andorra">Andorra</option>
                            <option value="Angola">Angola</option>
                            <option value="Anguilla">Anguilla</option>
                            <option value="Antarctica">Antarctica</option>
                            <option value="Antigua and Barbuda">Antigua and Barbuda</option>
                            <option value="Argentina">Argentina</option>
                            <option value="Armenia">Armenia</option>
                            <option value="Aruba">Aruba</option>
                            <option value="Australia">Australia</option>
                            <option value="Austria">Austria</option>
                            <option value="Azerbaijan">Azerbaijan</option>
                            <option value="Bahamas">Bahamas</option>
                            <option value="Bahrain">Bahrain</option>
                            <option value="Bangladesh">Bangladesh</option>
                            <option value="Barbados">Barbados</option>
                            <option value="Belarus">Belarus</option>
                            <option value="Belgium">Belgium</option>
                            <option value="Belize">Belize</option>
                            <option value="Benin">Benin</option>
                            <option value="Bermuda">Bermuda</option>
                            <option value="Bhutan">Bhutan</option>
                            <option value="Bolivia (Plurinational State of)">Bolivia (Plurinational State of)</option>
                            <option value="Bonaire, Sint Eustatius and Saba">Bonaire, Sint Eustatius and Saba</option>
                            <option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option>
                            <option value="Botswana">Botswana</option>
                            <option value="Bouvet Island">Bouvet Island</option>
                            <option value="Brazil">Brazil</option>
                            <option value="British Indian Ocean Territory">British Indian Ocean Territory</option>
                            <option value="Brunei Darussalam">Brunei Darussalam</option>
                            <option value="Bulgaria">Bulgaria</option>
                            <option value="Burkina Faso">Burkina Faso</option>
                            <option value="Burundi">Burundi</option>
                            <option value="Cabo Verde">Cabo Verde</option>
                            <option value="Cambodia">Cambodia</option>
                            <option value="Cameroon">Cameroon</option>
                            <option value="Canada">Canada</option>
                            <option value="Cayman Islands">Cayman Islands</option>
                            <option value="Central African Republic">Central African Republic</option>
                            <option value="Chad">Chad</option>
                            <option value="Chile">Chile</option>
                            <option value="China">China</option>
                            <option value="Christmas Island">Christmas Island</option>
                            <option value="Cocos (Keeling) Islands">Cocos (Keeling) Islands</option>
                            <option value="Colombia">Colombia</option>
                            <option value="Comoros">Comoros</option>
                            <option value="Congo">Congo</option>
                            <option value="Congo (the Democratic Republic of the)">Congo (the Democratic Republic of the)</option>
                            <option value="Cook Islands">Cook Islands</option>
                            <option value="Costa Rica">Costa Rica</option>
                            <option value="Côte d'Ivoire">Côte d'Ivoire</option>
                            <option value="Croatia">Croatia</option>
                            <option value="Cuba">Cuba</option>
                            <option value="Curaçao">Curaçao</option>
                            <option value="Cyprus">Cyprus</option>
                            <option value="Czechia">Czechia</option>
                            <option value="Denmark">Denmark</option>
                            <option value="Djibouti">Djibouti</option>
                            <option value="Dominica">Dominica</option>
                            <option value="Dominican Republic">Dominican Republic</option>
                            <option value="Ecuador">Ecuador</option>
                            <option value="Egypt">Egypt</option>
                            <option value="El Salvador">El Salvador</option>
                            <option value="EN">England</option>
                            <option value="Equatorial Guinea">Equatorial Guinea</option>
                            <option value="Eritrea">Eritrea</option>
                            <option value="Estonia">Estonia</option>
                            <option value="Eswatini">Eswatini</option>
                            <option value="Ethiopia">Ethiopia</option>
                            <option value="Falkland Islands (Malvinas)">Falkland Islands (Malvinas)</option>
                            <option value="Faroe Islands">Faroe Islands</option>
                            <option value="Fiji">Fiji</option>
                            <option value="Finland">Finland</option>
                            <option value="France">France</option>
                            <option value="FX">France, Metropolitan</option>
                            <option value="French Guiana">French Guiana</option>
                            <option value="French Polynesia">French Polynesia</option>
                            <option value="French Southern Territories">French Southern Territories</option>
                            <option value="Gabon">Gabon</option>
                            <option value="Gambia">Gambia</option>
                            <option value="Georgia">Georgia</option>
                            <option value="Germany">Germany</option>
                            <option value="Ghana">Ghana</option>
                            <option value="Gibraltar">Gibraltar</option>
                            <option value="Greece">Greece</option>
                            <option value="Greenland">Greenland</option>
                            <option value="Grenada">Grenada</option>
                            <option value="Guadeloupe">Guadeloupe</option>
                            <option value="Guam">Guam</option>
                            <option value="Guatemala">Guatemala</option>
                            <option value="Guinea">Guinea</option>
                            <option value="Guinea-Bissau">Guinea-Bissau</option>
                            <option value="Guyana">Guyana</option>
                            <option value="Haiti">Haiti</option>
                            <option value="Heard Island and McDonald Islands">Heard Island and McDonald Islands</option>
                            <option value="Holy See">Holy See</option>
                            <option value="Honduras">Honduras</option>
                            <option value="Hong Kong">Hong Kong</option>
                            <option value="Hungary">Hungary</option>
                            <option value="Iceland">Iceland</option>
                            <option value="India">India</option>
                            <option value="Indonesia">Indonesia</option>
                            <option value="Iran (Islamic Republic of)">Iran (Islamic Republic of)</option>
                            <option value="Iraq">Iraq</option>
                            <option value="Ireland">Ireland</option>
                            <option value="Isle of Man">Isle of Man</option>
                            <option value="Israel">Israel</option>
                            <option value="Italy">Italy</option>
                            <option value="Jamaica">Jamaica</option>
                            <option value="Japan">Japan</option>
                            <option value="Jersey">Jersey</option>
                            <option value="Jordan">Jordan</option>
                            <option value="Kazakhstan">Kazakhstan</option>
                            <option value="Kenya">Kenya</option>
                            <option value="Kiribati">Kiribati</option>
                            <option value="Korea (the Democratic People's Republic of)">Korea (the Democratic People's Republic of)</option>
                            <option value="Korea (the Republic of)">Korea (the Republic of)</option>
                            <option value="Kuwait">Kuwait</option>
                            <option value="Kyrgyzstan">Kyrgyzstan</option>
                            <option value="Lao People's Democratic Republic">Lao People's Democratic Republic</option>
                            <option value="Latvia">Latvia</option>
                            <option value="Lebanon">Lebanon</option>
                            <option value="Lesotho">Lesotho</option>
                            <option value="Liberia">Liberia</option>
                            <option value="Libya">Libya</option>
                            <option value="Liechtenstein">Liechtenstein</option>
                            <option value="Lithuania">Lithuania</option>
                            <option value="Luxembourg">Luxembourg</option>
                            <option value="Macao">Macao</option>
                            <option value="Madagascar">Madagascar</option>
                            <option value="Malawi">Malawi</option>
                            <option value="Malaysia">Malaysia</option>
                            <option value="Maldives">Maldives</option>
                            <option value="Mali">Mali</option>
                            <option value="Malta">Malta</option>
                            <option value="Marshall Islands">Marshall Islands</option>
                            <option value="Martinique">Martinique</option>
                            <option value="Mauritania">Mauritania</option>
                            <option value="Mauritius">Mauritius</option>
                            <option value="Mayotte">Mayotte</option>
                            <option value="Mexico">Mexico</option>
                            <option value="Micronesia (Federated States of)">Micronesia (Federated States of)</option>
                            <option value="Moldova (the Republic of)">Moldova (the Republic of)</option>
                            <option value="Monaco">Monaco</option>
                            <option value="Mongolia">Mongolia</option>
                            <option value="Montenegro">Montenegro</option>
                            <option value="Montserrat">Montserrat</option>
                            <option value="Morocco">Morocco</option>
                            <option value="Mozambique">Mozambique</option>
                            <option value="Myanmar">Myanmar</option>
                            <option value="Namibia">Namibia</option>
                            <option value="Nauru">Nauru</option>
                            <option value="Nepal">Nepal</option>
                            <option value="Netherlands">Netherlands</option>
                            <option value="AN">Netherlands Antilles</option>
                            <option value="New Caledonia">New Caledonia</option>
                            <option value="New Zealand">New Zealand</option>
                            <option value="Nicaragua">Nicaragua</option>
                            <option value="Niger">Niger</option>
                            <option value="Nigeria">Nigeria</option>
                            <option value="Niue">Niue</option>
                            <option value="Norfolk Island">Norfolk Island</option>
                            <option value="North Macedonia">North Macedonia</option>
                            <option value="Northern Mariana Islands">Northern Mariana Islands</option>
                            <option value="Norway">Norway</option>
                            <option value="Oman">Oman</option>
                            <option value="Pakistan">Pakistan</option>
                            <option value="Palau">Palau</option>
                            <option value="Palestine, State of">Palestine, State of</option>
                            <option value="Panama">Panama</option>
                            <option value="Papua New Guinea">Papua New Guinea</option>
                            <option value="Paraguay">Paraguay</option>
                            <option value="Peru">Peru</option>
                            <option value="Philippines">Philippines</option>
                            <option value="Pitcairn">Pitcairn</option>
                            <option value="Poland">Poland</option>
                            <option value="Portugal">Portugal</option>
                            <option value="Puerto Rico">Puerto Rico</option>
                            <option value="Qatar">Qatar</option>
                            <option value="RK">Republic of Kosovo</option>
                            <option value="Réunion">Réunion</option>
                            <option value="Romania">Romania</option>
                            <option value="Russian Federation">Russian Federation</option>
                            <option value="Rwanda">Rwanda</option>
                            <option value="Saint Martin (French part)">Saint Martin (French part)</option>
                            <option value="Saint Barthélemy">Saint Barthélemy</option>
                            <option value="Saint Helena, Ascension and Tristan da Cunha">Saint Helena, Ascension and Tristan da Cunha</option>
                            <option value="Saint Kitts and Nevis">Saint Kitts and Nevis</option>
                            <option value="Saint Lucia">Saint Lucia</option>
                            <option value="Saint Pierre and Miquelon">Saint Pierre and Miquelon</option>
                            <option value="Saint Vincent and the Grenadines">Saint Vincent and the Grenadines</option>
                            <option value="Samoa">Samoa</option>
                            <option value="San Marino">San Marino</option>
                            <option value="Sao Tome and Principe">Sao Tome and Principe</option>
                            <option value="Saudi Arabia">Saudi Arabia</option>
                            <option value="Senegal">Senegal</option>
                            <option value="Serbia">Serbia</option>
                            <option value="Seychelles">Seychelles</option>
                            <option value="Sierra Leone">Sierra Leone</option>
                            <option value="Singapore">Singapore</option>
                            <option value="Sint Maarten (Dutch part)">Sint Maarten (Dutch part)</option>
                            <option value="Slovakia">Slovakia</option>
                            <option value="Slovenia">Slovenia</option>
                            <option value="Solomon Islands">Solomon Islands</option>
                            <option value="Somalia">Somalia</option>
                            <option value="South Africa">South Africa</option>
                            <option value="South Georgia and the South Sandwich Islands">South Georgia and the South Sandwich Islands</option>
                            <option value="South Sudan">South Sudan</option>
                            <option value="Spain">Spain</option>
                            <option value="Sri Lanka">Sri Lanka</option>
                            <option value="Sudan">Sudan</option>
                            <option value="Suriname">Suriname</option>
                            <option value="Svalbard and Jan Mayen">Svalbard and Jan Mayen</option>
                            <option value="Sweden">Sweden</option>
                            <option value="Switzerland">Switzerland</option>
                            <option value="Syrian Arab Republic">Syrian Arab Republic</option>
                            <option value="Taiwan, Province of China">Taiwan, Province of China</option>
                            <option value="Tajikistan">Tajikistan</option>
                            <option value="Tanzania, United Republic of">Tanzania, United Republic of</option>
                            <option value="Thailand">Thailand</option>
                            <option value="Timor-Leste">Timor-Leste</option>
                            <option value="Timor-Leste">Timor-Leste</option>
                            <option value="Togo">Togo</option>
                            <option value="Tokelau">Tokelau</option>
                            <option value="Tonga">Tonga</option>
                            <option value="Trinidad and Tobago">Trinidad and Tobago</option>
                            <option value="Tunisia">Tunisia</option>
                            <option value="Türkiye">Türkiye</option>
                            <option value="Turkmenistan">Turkmenistan</option>
                            <option value="Turks and Caicos Islands">Turks and Caicos Islands</option>
                            <option value="Tuvalu">Tuvalu</option>
                            <option value="Uganda">Uganda</option>
                            <option value="Ukraine">Ukraine</option>
                            <option value="United Arab Emirates">United Arab Emirates</option>
                            <option value="United Kingdom" selected="selected">United Kingdom</option>
                            <option value="United States">United States</option>
                            <option value="United States Minor Outlying Islands">United States Minor Outlying Islands</option>
                            <option value="Uruguay">Uruguay</option>
                            <option value="Uzbekistan">Uzbekistan</option>
                            <option value="Vanuatu">Vanuatu</option>
                            <option value="Venezuela (Bolivarian Republic of)">Venezuela (Bolivarian Republic of)</option>
                            <option value="Viet Nam">Viet Nam</option>
                            <option value="Virgin Islands (British)">Virgin Islands (British)</option>
                            <option value="Virgin Islands (U.S.)">Virgin Islands (U.S.)</option>
                            <option value="Wallis and Futuna">Wallis and Futuna</option>
                            <option value="Western Sahara">Western Sahara</option>
                            <option value="Yemen">Yemen</option>
                            <option value="Zambia">Zambia</option>
                            <option value="Zimbabwe">Zimbabwe</option>
                        </select>
                        <label for="country">Country <span class="required">*</span></label>
                    </div>
                    
                    <!-- Phone Number -->
                    <div class="form-field floating-label one-half">
                        <input type="tel" id="phone" required uuid="<?php echo wp_generate_uuid4(); ?>" placeholder=" ">
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
                <div class="form-row">
                    <div class="form-field">
                        <p>By clicking download, you consent to Drug Target Review's <a href="<?php echo home_url('/terms-conditions'); ?>" target="_blank">terms and conditions</a> and <a href="<?php echo home_url('/privacy-policy'); ?>" target="_blank">privacy policy</a>. Your information will be processed in accordance with GDPR and you can unsubscribe at any time.</p>
                    </div>
                </div>

                <!-- Consent Checkbox -->
                <div class="checkbox-group consent-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="consent" required uuid="<?php echo wp_generate_uuid4(); ?>">
                        <label for="consent" class="checkbox-label">
                            I consent to Drug Target Review collecting my data. <span class="required">*</span>
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

                <!-- Debug Test Buttons -->
                <div style="margin-top: 15px; text-align: center;">
                    <button type="button" onclick="testAjaxEndpoint()" class="button" style="background: #ff6600; color: white; padding: 8px 16px; font-size: 12px; border-radius: 4px; margin-right: 10px;">
                        🔧 Test AJAX Connection
                    </button>
                    <button type="button" onclick="fillTestDataMediaPlanner()" class="button" style="background: #28a745; color: white; padding: 8px 16px; font-size: 12px; border-radius: 4px;">
                        📝 Fill Test Data
                    </button>
                </div>
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
        let devModeActive = false;

        // Development mode toggle
        function initDevModeToggle() {
            var toggle = document.getElementById('devModeToggle');
            var indicator = document.getElementById('devModeIndicator');

            if (toggle && indicator) {
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
                { progress: 'progress-100', text: 'Almost done...', delay: 1500 }
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
                    // Start countdown before completion
                    startCountdown();
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
            loaderIcon.style.opacity = '0';
            countdownContainer.classList.add('active');
            
            let count = 3;
            
            function showNextCount() {
                if (count > 0) {
                    countdownNumber.textContent = count;
                    countdownMessage.textContent = '';
                    count--;
                    setTimeout(showNextCount, 1000);
                } else {
                    // Show final message
                    countdownNumber.textContent = '';
                    countdownMessage.textContent = 'Download Ready!';
                    
                    // Hide loader after message is shown
                    setTimeout(() => {
                        hideProgressLoader();
                    }, 1500);
                }
            }
            
            showNextCount();
        }

        function hideProgressLoader() {
            const loadingOverlay = document.getElementById('formLoaderOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
            }
        }

        function previewLoader() {
            showProgressLoader();
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
            formData.append('organisation', document.getElementById('organisation')?.value || '');
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
                    // Don't hide loader immediately - let countdown animation complete
                    setTimeout(() => {
                        // Redirect to thank you page or download URL
                        if (data.data && data.data.download_url) {
                            window.location.href = data.data.download_url;
                        } else {
                            // Default redirect
                            window.location.href = '/thank-you-media-planner/';
                        }
                    }, 12500); // Wait for countdown to complete
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
        });

        // Debug logging
        console.log('%cMedia Planner Registration System Ready', 'background: #4CAF50; color: white; padding: 8px 12px; border-radius: 4px; font-weight: bold;');
        
    </script>
    <?php
    return ob_get_clean();
}
?>