<?php
/* --------------------------------------------------------------------------
 * Workbooks Webinar Registration Shortcode Logic
 * -------------------------------------------------------------------------- */

/**
 * Renders the webinar registration UI with all logic for user state, event type, and form display.
 *
 * Usage: [workbooks-webinar-registration]
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output for the registration UI.
 */
function workbooks_webinar_registration_shortcode($atts) {
    // --- BEGIN: Inject User & Event Data for JS (Always, if logged in) ---
    $post_id = get_the_ID();
    $user_is_logged_in = is_user_logged_in();
    // Always load webinar_fields for event ID!
    $webinar_fields = get_field('webinar_fields', $post_id);
    $event_id = '';
    if (!empty($webinar_fields['workbooks_reference'])) {
        $event_id = preg_replace('/\D+/', '', $webinar_fields['workbooks_reference']);
    }

    if ($user_is_logged_in) {
        $current_user = wp_get_current_user();
        $person_id = get_user_meta($current_user->ID, 'workbooks_person_id', true);
        $person_email = $current_user->user_email;

        echo '<script>';
        echo 'window.dtr_workbooks_ajax = window.dtr_workbooks_ajax || {};';
        echo 'window.dtr_workbooks_ajax.current_user_id = ' . json_encode($person_id) . ';';
        echo 'window.dtr_workbooks_ajax.current_user_email = ' . json_encode($person_email) . ';';
        echo 'window.dtr_workbooks_ajax.current_event_id = ' . json_encode($event_id) . ';';
        // --- JS console logs as requested ---
        echo 'var personId = window.dtr_workbooks_ajax.current_user_id;';
        echo 'var personEmail = window.dtr_workbooks_ajax.current_user_email;';
        echo 'var eventId = window.dtr_workbooks_ajax.current_event_id;';
        echo 'console.log("Person ID:", personId);';
        echo 'console.log("Person Email Address:", personEmail);';
        echo 'console.log("Event ID:", eventId);';
        // Match lead generation template: Always log user login state
        echo 'console.log("User: Logged In");';
        echo '</script>';
    }
    // --- END: Inject User & Event Data for JS ---

    ob_start();
    
    // Add global styles and scripts for split buttons
    echo <<<HTML
    <style>
    /* Wrapper full width */
    .ks-split-btn {
        display: flex;
        width: 100%;
        position: relative;
        font-size: 0.95rem;
        line-height: 1.2;
        min-height: 48px;
    }

    /* Main + toggle buttons */
    .ks-main-btn,
    .ks-toggle-btn {
        display: flex;
        align-items: center;
        justify-content: left;
        padding: 0.6rem 0.95rem;
        border: 0;
        cursor: pointer;
        font-weight: 600;
    }

    .ks-main-btn {
        flex: 1;
        background: #009fe3;
        color: #fff;
        border-radius: 3px 0 0 0;
        text-decoration: none;
    }

    .ks-toggle-btn {
        flex: 0 0 60px;
        background: #009fe3;
        color: #fff;
        border-radius: 0 3px 0 0;
        justify-content: center;
    }

    .ks-toggle-btn:hover {
        background: #007bbf;
    }

    .ks-toggle-btn[aria-expanded="true"] {
        background: #007bbf;
    }

    .ks-toggle-btn i {
        font-family: 'Font Awesome 6 Pro';
        font-weight: 400;
        content: '\\f078'; /* Unicode for fa-chevron-down */
    }

    .ks-toggle-btn[aria-expanded="true"] i {
        content: '\\f077'; /* Unicode for fa-chevron-up */
    }

    /* Dropdown menu (hidden by default, animated slide) */
    .ks-menu {
        position: absolute;
        top: calc(100% + 0px);
        left: 50%;
        transform: translateX(-50%);
        width: 50%;
        background-color:#871f80;
        /* border: 1px solid rgba(0,0,0,0.1); */
        border-radius: 0 0 3px 3px !important;
        margin: 0;
        padding: 0;
        list-style: none;
        /* box-shadow: 0 6px 16px rgba(0,0,0,0.08); */
        z-index: 9999;

        display: flex;
        justify-content: space-around;

        /* transition */
        max-height: 0;
        opacity: 0;
        overflow: hidden;
        transition: max-height 0.35s ease, 
                    opacity 0.35s ease, 
                    padding 0.35s ease,
                    width 0.35s ease;
        padding: 0; /* collapse spacing when closed */
    }

    /* Open state: slide, fade + expand to full width */
    .ks-menu.ks-open {
        max-height: 200px; /* large enough for contents */
        opacity: 1;
        padding: 0; /* spacing appears smoothly */
        width: 100%;
        border-radius: 0 0 3px 3px !important;
    }

    .ks-menu li {
        flex: 1;
        text-align: center;
        margin-bottom:0;
        list-style: none;
    }

    .ks-menu a {
        display: block;
        padding: 12px;
        color: #fff;
        text-decoration: none;
        font-size:0.75rem;
        font-weight: bold;
        border-radius: 0 0 3px 3px;
        background: #009fe3;
    }

    .ks-menu a.login-button {
        display: block;
        padding: 12px;
        color: #fff;
        text-decoration: none;
        font-size:0.75rem;
        font-weight: bold;
        border-radius: 0 0 3px 3px;
        background: #871f80;
    }

    .ks-menu a.login-button:hover {
        background: #6e1a6e;
    }

    .ks-menu a.calendar-btn {
        display: block;
        padding: 12px;
        color: #fff;
        text-decoration: none;
        font-size:0.75rem;
        font-weight: bold;
        border-radius: 0 0 3px 3px;
        background: #871f80;
    }

    .ks-menu a.calendar-btn:hover {
        background: #6e1a6e;
    }

    .ks-menu a:hover {
        background: #007bbf;
    }

    .event-registration .reveal-text {
        padding:0.45rem 0.85rem;
        margin-top:4px;
    }

    .is-registered .ks-main-btn {
        background: #871f80;
    }

    .is-registered .ks-toggle-btn {
        flex: 0 0 60px;
        background: #871f80;
        color: #fff;
        border-radius: 0 3px 0 0;
        justify-content: center;
    }

    .is-registered .ks-toggle-btn:hover {
        background: #6e1a6e;
    }

    .ks-toggle-btn[aria-expanded="true"] {
        background: #6e1a6e;
    }

    /* Modal Styles */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10000;
    }

    .modal-content {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        position: relative;
    }

    .modal-close {
        position: absolute;
        top: 10px;
        right: 15px;
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #666;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-close:hover {
        color: #000;
    }

    .modal-body {
        margin-top: 10px;
    }

    .modal-body .nf-form-wrap {
        margin: 0;
    }

    .modal-body .nf-form-title h3 {
        margin-top: 0;
        text-align: center;
    }
    </style>

    <div id="nf-login-modal-form" style="display:none;">
HTML;
    echo do_shortcode('[ninja_form id=3]');
    echo <<<HTML
    </div>

    <script>
    // Add custom CSS to override any redirect behavior in login form
    document.addEventListener('DOMContentLoaded', function() {
        // Check for any login forms and add current page URL as redirect
        const hiddenForm = document.querySelector('#nf-login-modal-form form');
        if (hiddenForm) {
            // Add current URL as hidden field to prevent redirection
            let redirectField = hiddenForm.querySelector('input[name="redirect_to"]');
            if (!redirectField) {
                redirectField = document.createElement('input');
                redirectField.type = 'hidden';
                redirectField.name = 'redirect_to';
                hiddenForm.appendChild(redirectField);
            }
            redirectField.value = window.location.href;
            console.log('Added redirect field to login form:', redirectField.value);
        }
    });
    </script>

    <script>
    // Define openLoginModal function globally
    function openLoginModal() {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <button class="modal-close">&times;</button>
                <div class="modal-body">
                    <h2>Login</h2>
                    <div id="modal-form-container"></div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Instead of moving the form, let's make the original form visible in the modal
        const formDiv = document.getElementById('nf-login-modal-form');
        const modalFormContainer = modal.querySelector('#modal-form-container');
        
        if (formDiv) {
            // Move the actual form div (not just innerHTML) to preserve event handlers
            formDiv.style.display = 'block';
            modalFormContainer.appendChild(formDiv);
            console.log('Original form div moved to modal with preserved handlers');
        } else {
            modalFormContainer.innerHTML = '<p>Login form could not be loaded. Please refresh the page and try again.</p>';
        }

        // Close modal functionality
        const closeButton = modal.querySelector('.modal-close');
        closeButton.addEventListener('click', function () {
            // Move the form back to its original location before closing
            if (formDiv && formDiv.parentNode === modalFormContainer) {
                document.body.appendChild(formDiv);
                formDiv.style.display = 'none';
            }
            document.body.removeChild(modal);
        });

        // Close modal when clicking outside
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                // Move the form back to its original location before closing
                if (formDiv && formDiv.parentNode === modalFormContainer) {
                    document.body.appendChild(formDiv);
                    formDiv.style.display = 'none';
                }
                document.body.removeChild(modal);
            }
        });

        // Listen for Ninja Forms submission responses on the original form
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('nfFormSubmitResponse', function(e, response, formId) {
                console.log('Ninja Forms response received:', response, 'Form ID:', formId);
                if (formId == 3 && response && response.success) {
                    console.log('Login successful!');
                    // Close modal and reload page
                    if (formDiv && formDiv.parentNode === modalFormContainer) {
                        document.body.appendChild(formDiv);
                        formDiv.style.display = 'none';
                    }
                    document.body.removeChild(modal);
                    window.location.reload();
                }
            });
        }
    }

    (function () {
        function closeAllExcept(exceptMenu) {
            document.querySelectorAll('.ks-menu.ks-open').forEach(function (m) {
                if (m !== exceptMenu) {
                    m.classList.remove('ks-open');
                    var t = document.querySelector('[aria-controls="' + m.id + '"]');
                    if (t) t.setAttribute('aria-expanded', 'false');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.ks-split-btn').forEach(function (container) {
                var toggle = container.querySelector('.ks-toggle-btn');
                var menu = container.querySelector('.ks-menu');
                if (!toggle || !menu) return;

                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var isOpen = menu.classList.toggle('ks-open');
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    closeAllExcept(isOpen ? menu : null);
                });

                // Close when menu item clicked
                menu.querySelectorAll('a').forEach(function (a) {
                    a.addEventListener('click', function () {
                        menu.classList.remove('ks-open');
                        toggle.setAttribute('aria-expanded', 'false');
                    });
                });

                // Keyboard: Esc to close
                container.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        menu.classList.remove('ks-open');
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                });
            });

            // Clicking outside closes all menus
            document.addEventListener('click', function () {
                closeAllExcept(null);
            });
        });
    })();
    </script>
HTML;

    // ----------------------------------------------------------------------
    // Step 1: Gather Event & User Data
    // ----------------------------------------------------------------------
    $registration_link = $webinar_fields['webinar_link'] ?? '';
    $start_date = $webinar_fields['webinar_date_and_time'] ?? '';
    $webinar_form = $webinar_fields['webinar_registration_form'] ?? '';
    $acf_questions = !empty($webinar_fields['add_questions']) ? $webinar_fields['add_questions'] : [];
    $add_additional_questions = !empty($webinar_fields['add_additional_questions']) ? $webinar_fields['add_additional_questions'] : false;
    $add_speaker_question = !empty($webinar_fields['add_speaker_question']) ? $webinar_fields['add_speaker_question'] : false;
    $is_on_demand = !empty($registration_link);
    $user_id = get_current_user_id();
    $user_is_registered = $user_is_logged_in ? is_user_registered_for_event($user_id, $post_id) : false;
    $ics_url = add_query_arg('ics', '1', get_permalink($post_id));

    
    // ----------------------------------------------------------------------
    // Section: Not Logged In (Split Button with 50% inline dropdown + Slide Up/Down)
    // ----------------------------------------------------------------------
    if ( ! $user_is_logged_in ) {
        $uid = 'ks' . uniqid(); // unique id for this instance
        echo <<<HTML
        <div style="font-size:2rem;font-weight:bold;color:#b00;text-align:center;margin:2em 0;">STEP: Not Logged In</div>
            <div class="full-page vertical-half-margin event-registration">

            <!-- split button -->
            <div class="ks-split-btn">
                <a href="/free-membership" class="ks-main-btn" role="button">Login or Register Now</a>
                <button type="button" class="ks-toggle-btn" aria-haspopup="true" aria-expanded="false" aria-controls="{$uid}-menu" title="Open menu">
                    <i class="fa-solid fa-chevron-down"></i>
                </button>

                <ul id="{$uid}-menu" class="ks-menu" role="menu">
                    <li role="none"><a role="menuitem" href="#" class="login-button" onclick="event.preventDefault(); openLoginModal();">Login</a></li>
                    <li role="none"><a role="menuitem" href="/free-membership">Become a Member</a></li>
                </ul>
            </div>

            <div class="reveal-text">Login or Register for this event</div>
        </div>
        HTML;

        return ob_get_clean();
    }

    /* ----------------------------------------------------------------------
     * Section: On Demand Webinar (Video Link Present)
     * ---------------------------------------------------------------------- */
    if ($is_on_demand) {
        echo '<div style="font-size:2rem;font-weight:bold;color:#b00;text-align:center;margin:2em 0;">STEP: On Demand Webinar (Video Link Present)</div>';
        echo "<!-- LOG: Webinar Type: On Demand Webinar -->\n";
        echo "<script>console.log('[LOG] Webinar Type: On Demand Webinar');</script>\n";
        echo "<!-- LOG: User: Logged In - Additional Questions - Post Not Saved (On Demand Logic) -->\n";
        echo "<script>console.log('[LOG] User: Logged In - Additional Questions - Post Not Saved (On Demand Logic)');</script>\n";
        echo '<div class="full-page vertical-half-margin event-registration">';
        echo '<button class="event-register-button event-passed">On Demand - Register Now</button>';
        echo '</div>';
        if (!empty($webinar_form['id'])) {
            $extra_fields_markup = '';
            // Note: Speaker question is handled by Ninja Form field 387 (question_for_speaker)
            // We don't need to add it here as it would create duplicates
            if ($add_additional_questions && !empty($acf_questions)) {
                $extra_fields_markup .= '<form id="acf-questions-form" style="margin-bottom:15px;" onsubmit="return false;">';
                foreach ($acf_questions as $i => $question) {
                    $type = isset($question['type_of_question']) ? $question['type_of_question'] : 'text';
                    $title = isset($question['question_title']) ? $question['question_title'] : '';
                    $extra_fields_markup .= '<div style="margin-bottom:10px;">';
                    $extra_fields_markup .= '<label class="question-label" for="acf_question_' . $i . '">' . esc_html($title) . '</label><br />';
                    if ($type === 'dropdown' && !empty($question['dropdown_options'])) {
                        $extra_fields_markup .= '<select name="acf_question_' . $i . '" id="acf_question_' . $i . '">';
                        foreach ($question['dropdown_options'] as $opt) {
                            $extra_fields_markup .= '<option value="' . esc_attr($opt['option']) . '">' . esc_html($opt['option']) . '</option>';
                        }
                        $extra_fields_markup .= '</select>';
                    } elseif ($type === 'checkbox' && !empty($question['checkbox_options'])) {
                        foreach ($question['checkbox_options'] as $j => $opt) {
                            $extra_fields_markup .= '<label class="answer-label"><input type="checkbox" name="acf_question_' . $i . '[]" value="' . esc_attr($opt['checkbox']) . '"> ' . esc_html($opt['checkbox']) . '</label> ';
                        }
                    } elseif ($type === 'radio' && !empty($question['radio_options'])) {
                        foreach ($question['radio_options'] as $j => $opt) {
                            $extra_fields_markup .= '<label class="answer-label"><input type="radio" name="acf_question_' . $i . '" value="' . esc_attr($opt['radio']) . '"> ' . esc_html($opt['radio']) . '</label> ';
                        }
                    } elseif ($type === 'textarea') {
                        $extra_fields_markup .= '<textarea name="acf_question_' . $i . '" id="acf_question_' . $i . '" rows="4" style="width:100%"></textarea>';
                    } else {
                        $extra_fields_markup .= '<input type="text" name="acf_question_' . $i . '" id="acf_question_' . $i . '" value="" />';
                    }
                    $extra_fields_markup .= '</div>';
                }
                $extra_fields_markup .= '</form>';
            }
            echo '<div class="gated-lead-form-content webinars">';
            if (!empty($extra_fields_markup)) {
                echo $extra_fields_markup;
            }
            echo do_shortcode("[ninja_form id='" . esc_attr($webinar_form['id']) . "']");
            echo '</div>';
        }
        return ob_get_clean();
    }

    /* ----------------------------------------------------------------------
     * Section: Upcoming Event (Not On Demand)
     * ---------------------------------------------------------------------- */
    if (strtotime($start_date) > time()) {
        // Track user return with a cookie
        $step_status = $user_is_registered ? 'Registered' : 'Not Registered';
        $user_return = false;
        if ($user_is_registered && isset($_COOKIE['dtr_webinar_registered_' . $post_id])) {
            $user_return = true;
        }
        if ($user_is_registered && !$user_return) {
            setcookie('dtr_webinar_registered_' . $post_id, '1', time() + 60*60*24*30, "/");
        }
        $return_text = $user_return ? ' - User Return' : '';
        $form_ready = !$user_is_registered ? ' - Form Ready for Registration' : '';
        $step_message = 'STEP: Upcoming Event (Not On Demand) - ' . $step_status . $return_text . $form_ready;
        echo "<script>console.log('" . addslashes($step_message) . "');</script>\n";
        echo "<!-- LOG: Webinar Type: Live Webinar -->\n";
        echo "<script>console.log('[LOG] Webinar Type: Live Webinar');</script>\n";
        echo '<div class="full-page vertical-half-margin event-registration is-registered">';
        if ($user_is_registered) {
            $uid = 'ks' . uniqid(); // unique id for this instance
            echo <<<HTML
            <!-- split button for registered users -->
            <div class="ks-split-btn">
                <a href="/free-membership" class="ks-main-btn" role="button">You have registered for this Webinar</a>
                <button type="button" class="ks-toggle-btn" aria-haspopup="true" aria-expanded="false" aria-controls="{$uid}-menu" title="Open menu">
                    <i class="fa-solid fa-chevron-down"></i>
                </button>

                <ul id="{$uid}-menu" class="ks-menu" role="menu">
                    <li role="none"><a role="menuitem" href="/my-account/?page-view=overview&ics=1&calendar-post-id={$post_id}" class="no-decoration calendar-btn">Add to Calendar</a></li>
                    <li role="none"><a role="menuitem" href="/my-account/?page-view=events-and-webinars">Events & Webinars</a></li>
                </ul>
            </div>

            <div class="reveal-text">Webinar has been added to Events & Webinars</div>
            HTML;
        } else {
            echo '<button class="webinar-register-button webinar-registration not-registered" data-event-id="' . esc_attr($post_id) . '">Register Now</button>';
        }
        echo '</div>';
        
        // Only show form if user is NOT registered
        if (!$user_is_registered && !empty($webinar_form['id'])) {
            $extra_fields_markup = '';
            // Note: Speaker question is handled by Ninja Form field 387 (question_for_speaker)
            // We don't need to add it here as it would create duplicates
            if ($add_additional_questions && !empty($acf_questions)) {
                $extra_fields_markup .= '<form id="acf-questions-form" style="margin-bottom:15px;" onsubmit="return false;">';
                foreach ($acf_questions as $i => $question) {
                    $type = isset($question['type_of_question']) ? $question['type_of_question'] : 'text';
                    $title = isset($question['question_title']) ? $question['question_title'] : '';
                    $extra_fields_markup .= '<div style="margin-bottom:10px;">';
                    $extra_fields_markup .= '<label class="question-label" for="acf_question_' . $i . '">' . esc_html($title) . '</label><br />';
                    if ($type === 'dropdown' && !empty($question['dropdown_options'])) {
                        $extra_fields_markup .= '<select name="acf_question_' . $i . '" id="acf_question_' . $i . '">';
                        foreach ($question['dropdown_options'] as $opt) {
                            $extra_fields_markup .= '<option value="' . esc_attr($opt['option']) . '">' . esc_html($opt['option']) . '</option>';
                        }
                        $extra_fields_markup .= '</select>';
                    } elseif ($type === 'checkbox' && !empty($question['checkbox_options'])) {
                        foreach ($question['checkbox_options'] as $j => $opt) {
                            $extra_fields_markup .= '<label class="answer-label"><input type="checkbox" name="acf_question_' . $i . '[]" value="' . esc_attr($opt['checkbox']) . '"> ' . esc_html($opt['checkbox']) . '</label> ';
                        }
                    } elseif ($type === 'radio' && !empty($question['radio_options'])) {
                        foreach ($question['radio_options'] as $j => $opt) {
                            $extra_fields_markup .= '<label class="answer-label"><input type="radio" name="acf_question_' . $i . '" value="' . esc_attr($opt['radio']) . '"> ' . esc_html($opt['radio']) . '</label> ';
                        }
                    } elseif ($type === 'textarea') {
                        $extra_fields_markup .= '<textarea name="acf_question_' . $i . '" id="acf_question_' . $i . '" rows="4" style="width:100%"></textarea>';
                    } else {
                        $extra_fields_markup .= '<input type="text" name="acf_question_' . $i . '" id="acf_question_' . $i . '" value="" />';
                    }
                    $extra_fields_markup .= '</div>';
                }
                $extra_fields_markup .= '</form>';
            }
            echo '<div class="gated-lead-form-content webinars">';
            if (!empty($extra_fields_markup)) {
                echo $extra_fields_markup;
            }
            echo do_shortcode("[ninja_form id='" . esc_attr($webinar_form['id']) . "']");
            echo '</div>';
        }
        return ob_get_clean();
    }

    /* ----------------------------------------------------------------------
     * Section: Event Has Passed (Not On Demand)
     * ---------------------------------------------------------------------- */
    echo '<div style="font-size:2rem;font-weight:bold;color:#b00;text-align:center;margin:2em 0;">STEP: Event Has Passed (Not On Demand)</div>';
    echo "<!-- LOG: Webinar Type: Live Webinar (Event Passed, Not On Demand) -->\n";
    echo "<script>console.log('[LOG] Webinar Type: Live Webinar (Event Passed, Not On Demand)');</script>\n";
    echo "<!-- LOG: User: Logged In - Registered - Post Saved To Collection (Live & On Demand Logic) -->\n";
    echo "<script>console.log('[LOG] User: Logged In - Registered - Post Saved To Collection (Live & On Demand Logic)');</script>\n";
    echo '<div class="full-page vertical-half-margin event-registration">';
    echo '<button class="event-register-button event-passed">On Demand - Register Now</button>';
    echo '</div>';
    if (!empty($webinar_form['id'])) {
        $extra_fields_markup = '';
        if ($add_speaker_question) {
            $extra_fields_markup .= '<div class="webinar-speaker-question" style="margin-bottom:15px;">';
            $extra_fields_markup .= '<label for="webinar_speaker_question">Do you have a question for our speakers?</label><br />';
            $extra_fields_markup .= '<textarea name="webinar_speaker_question" id="webinar_speaker_question" rows="3" style="width:100%"></textarea>';
            $extra_fields_markup .= '</div>';
        }
        if ($add_additional_questions && !empty($acf_questions)) {
            $extra_fields_markup .= '<form id="acf-questions-form" style="margin-bottom:15px;" onsubmit="return false;">';
            foreach ($acf_questions as $i => $question) {
                $type = isset($question['type_of_question']) ? $question['type_of_question'] : 'text';
                $title = isset($question['question_title']) ? $question['question_title'] : '';
                $extra_fields_markup .= '<div style="margin-bottom:10px;">';
                $extra_fields_markup .= '<label class="question-label" for="acf_question_' . $i . '">' . esc_html($title) . '</label><br />';
                if ($type === 'dropdown' && !empty($question['dropdown_options'])) {
                    $extra_fields_markup .= '<select name="acf_question_' . $i . '" id="acf_question_' . $i . '">';
                    foreach ($question['dropdown_options'] as $opt) {
                        $extra_fields_markup .= '<option value="' . esc_attr($opt['option']) . '">' . esc_html($opt['option']) . '</option>';
                    }
                    $extra_fields_markup .= '</select>';
                } elseif ($type === 'checkbox' && !empty($question['checkbox_options'])) {
                    foreach ($question['checkbox_options'] as $j => $opt) {
                        $extra_fields_markup .= '<label class="answer-label"><input type="checkbox" name="acf_question_' . $i . '[]" value="' . esc_attr($opt['checkbox']) . '"> ' . esc_html($opt['checkbox']) . '</label> ';
                    }
                } elseif ($type === 'radio' && !empty($question['radio_options'])) {
                    foreach ($question['radio_options'] as $j => $opt) {
                        $extra_fields_markup .= '<label class="answer-label"><input type="radio" name="acf_question_' . $i . '" value="' . esc_attr($opt['radio']) . '"> ' . esc_html($opt['radio']) . '</label> ';
                    }
                } elseif ($type === 'textarea') {
                    $extra_fields_markup .= '<textarea name="acf_question_' . $i . '" id="acf_question_' . $i . '" rows="4" style="width:100%"></textarea>';
                } else {
                    $extra_fields_markup .= '<input type="text" name="acf_question_' . $i . '" id="acf_question_' . $i . '" value="" />';
                }
                $extra_fields_markup .= '</div>';
            }
            $extra_fields_markup .= '</form>';
        }
        echo '<div class="gated-lead-form-content webinars">';
        if (!empty($extra_fields_markup)) {
            echo $extra_fields_markup;
        }
        echo do_shortcode("[ninja_form id='" . esc_attr($webinar_form['id']) . "']");
        echo '</div>';
    }
    return ob_get_clean();
}

// Add modal functionality for login button
?>