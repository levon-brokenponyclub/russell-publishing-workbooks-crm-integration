<?php
/**
 * Workbooks Lead Generation Registration Shortcode Logic
 * Usage: [workbooks-lead-generation-registration control_content="true"]
 * Renders the lead generation registration UI with all logic for user state, ACF-powered questions, and form display.
 * When control_content="true", it controls the entire page content display logic.
 */
function workbooks_lead_generation_registration_shortcode($atts = []) {
    // Parse shortcode attributes
    $atts = shortcode_atts([
        'control_content' => 'false',
        'lead_generation_id' => '31'
    ], $atts);

    $control_content = filter_var($atts['control_content'], FILTER_VALIDATE_BOOLEAN);
    $form_id = intval($atts['lead_generation_id']);

    ob_start();
    $post_id = get_the_ID();
    $lead_fields = function_exists('get_field') ? get_field('lead_fields', $post_id) : [];
    $lead_form = $lead_fields['lead_generation_form'] ?? ['id' => $form_id];

    // Get ACF questions from restricted_content_fields (not lead_fields)
    $restricted = function_exists('get_field') ? get_field('restricted_content_fields', $post_id) : [];
    $acf_questions = !empty($restricted['add_questions']) ? $restricted['add_questions'] : [];
    $add_additional_questions = !empty($restricted['add_additional_questions']) ? $restricted['add_additional_questions'] : false;

    // --- BEGIN: Only essential logging for person/event/acf ---
    // Use the new field: restricted['workbooks_reference'] for event ID (from Gated Content ACF group)
    $event_id = '';
    if (!empty($restricted['workbooks_reference'])) {
        $event_id = preg_replace('/\D+/', '', $restricted['workbooks_reference']);
    }
    $user_is_logged_in = is_user_logged_in();
    if ($user_is_logged_in) {
        $current_user = wp_get_current_user();
        $person_id = get_user_meta($current_user->ID, 'workbooks_person_id', true);
        $person_email = $current_user->user_email;

        echo '<script>';
        echo 'console.log("Person ID:", ' . json_encode($person_id) . ');';
        echo 'console.log("Person Email Address:", ' . json_encode($person_email) . ');';
        echo 'console.log("Event ID:", ' . json_encode($event_id) . ');';
        if (!empty($acf_questions)) {
            echo 'console.log("ACF Questions Debug:", ' . json_encode($acf_questions) . ');';
        }
        echo '</script>';
    }
    // --- END essential logging only ---

    $user_id = get_current_user_id();
    $has_completed_form = $user_is_logged_in && function_exists('user_has_completed_form')
        ? user_has_completed_form($user_id, $lead_form['id'], $post_id)
        : false;

    // Check if user has saved to collection
    $saved_to_collection = false;
    if ($user_is_logged_in) {
        $saved = get_user_meta($user_id, 'saved_collection', true);
        if (is_array($saved)) {
            $saved_to_collection = in_array($post_id, $saved);
        }
    }

    // If control_content is true, handle the main content logic
    if ($control_content) {
        global $post;
        $restrict_post = get_field('restrict_post', $post->ID);

        if (!$restrict_post) {
            // Not gated, show full content
            get_template_part('components/global/main-content');
            return ob_get_clean();
        }

        if (!$user_is_logged_in) {
            // Show preview content for guests
            get_template_part('components/single-content/gated-content');
            return ob_get_clean();
        } else {
            if ($has_completed_form) {
                // Show full content for users who completed form
                echo '<div class="form-completion-notice" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                    <strong>✓ Content Unlocked:</strong> You have completed the required form and can now access this content.
                </div>';
                get_template_part('components/global/main-content');
                return ob_get_clean();
            } else {
                // Show logged-in user content before form submission
                $logged_in_content = !empty($restricted['logged_in_user_-_before_form_submission']) 
                    ? $restricted['logged_in_user_-_before_form_submission'] 
                    : '';
                
                if (!empty($logged_in_content)) {
                    // Display the custom logged-in user content before form submission
                    echo '<div class="gated-logged-in-content full-page main-body-content" style="margin-bottom:65px;">';
                    echo wpautop($logged_in_content);
                    echo '</div>';
                } else {
                    // Fallback to existing gated content template if no custom content
                    get_template_part('components/single-content/gated-content-logged-in');
                }
                
                return ob_get_clean();

                $extra_fields_markup = '';
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
                if (!empty($extra_fields_markup)) {
                    echo $extra_fields_markup;
                }
                echo do_shortcode('[ninja_form id="' . esc_attr($lead_form['id']) . '"]');
                echo '</div>'; // close .gated-lead-form-content
                
                // Add JavaScript to inject ACF answers into Ninja Form on submission
                if (!empty($acf_questions)) {
                    echo '<script>
                    jQuery(document).ready(function($) {
                        // Function to get ACF answers
                        function getAcfAnswers() {
                            var answers = {};
                            $("[id^=\'acf_question_\']").each(function() {
                                var fieldId = $(this).attr("id");
                                var fieldName = $(this).attr("name");
                                var value = "";

                                if ($(this).is(":checkbox")) {
                                    var checkedValues = [];
                                    $("[name=\'" + fieldName + "\']:checked").each(function() {
                                        checkedValues.push($(this).val());
                                    });
                                    value = checkedValues.join(", ");
                                } else if ($(this).is(":radio")) {
                                    value = $("[name=\'" + fieldName + "\']:checked").val() || "";
                                } else {
                                    value = $(this).val() || "";
                                }

                                if (value) {
                                    answers[fieldName] = value;
                                }
                            });
                            return answers;
                        }

                        // Function to inject ACF answers into Ninja Form
                        function injectAcfAnswersToNinjaForm(ninjaForm) {
                            var acfAnswers = getAcfAnswers();
                            console.log("ACF Questions Debug:", acfAnswers);

                            // Create hidden fields for each ACF answer
                            Object.entries(acfAnswers).forEach(function(entry) {
                                var fieldName = entry[0];
                                var fieldValue = entry[1];

                                // Check if field already exists
                                var existingField = ninjaForm.querySelector("[name=\'" + fieldName + "\']");
                                if (existingField) {
                                    existingField.value = fieldValue;
                                } else {
                                    // Create new hidden field
                                    var hiddenField = document.createElement("input");
                                    hiddenField.type = "hidden";
                                    hiddenField.name = fieldName;
                                    hiddenField.value = fieldValue;
                                    ninjaForm.appendChild(hiddenField);
                                }
                            });
                        }

                        // Wait for Ninja Form to be ready
                        function setupNinjaFormIntegration() {
                            var ninjaForm = document.querySelector(".ninja-forms-form");
                            if (!ninjaForm) {
                                setTimeout(setupNinjaFormIntegration, 300);
                                return;
                            }

                            // Avoid double-binding
                            if (ninjaForm.getAttribute("data-acf-inject-listener")) return;
                            ninjaForm.setAttribute("data-acf-inject-listener", "1");

                            // Listen for form submission
                            ninjaForm.addEventListener("submit", function(e) {
                                injectAcfAnswersToNinjaForm(ninjaForm);
                            });
                        }

                        // Initialize when page loads
                        setupNinjaFormIntegration();

                        // Also try when Ninja Forms fires its ready event
                        $(document).on("nfFormReady", function(e) {
                            setTimeout(setupNinjaFormIntegration, 100);
                        });
                    });
                    </script>';
                }
                
                return ob_get_clean();
            }
        }
        
        return ob_get_clean();
    }

    // Original sidebar logic (when control_content is false or not set)
    // Generate dynamic button based on user state
    $button_html = '';
    $reveal_text = '';

    if (!$user_is_logged_in) {
        // Not logged in: "Login or Register Now" (with link) + "Login or Register for this event"
        $button_html = '<a href="/free-membership" class="event-register-button">Login or Register Now</a>';
        $reveal_text = '<div class="reveal-text">Login or Register for this event</div>';
    } elseif (!$has_completed_form) {
        // Logged in, no form submission: "Register Now" (no link, triggers form)
        $button_html = '<button class="event-register-button" onclick="document.querySelector(\'.gated-lead-form-content\').scrollIntoView({behavior: \'smooth\'});">Register Now</button>';
        $reveal_text = '';
    } elseif (!$saved_to_collection) {
        // Logged in, form submitted: "Save to Collection"
        $button_html = '<button class="event-register-button save-to-collection" data-post-id="' . esc_attr($post_id) . '">Save to Collection</button>';
        $reveal_text = '';
    } else {
        // Logged in, saved to collection: "Saved to Collection" + "Click to view your collection"
        $button_html = '<button class="event-register-button saved-to-collection" disabled>Saved to Collection</button>';
        $reveal_text = '<div class="reveal-text"><a href="/my-collection">Click to view your collection</a></div>';
    }

    // Add JavaScript for Save to Collection functionality
    if (!$has_completed_form || !$saved_to_collection) {
        echo '<script>
        jQuery(document).ready(function($) {
            $(".save-to-collection").on("click", function(e) {
                e.preventDefault();
                var button = $(this);
                var postId = button.data("post-id");

                button.prop("disabled", true).text("Saving...");

                $.ajax({
                    url: "' . admin_url('admin-ajax.php') . '",
                    type: "POST",
                    data: {
                        action: "save_to_collection",
                        post_id: postId
                    },
                    success: function(response) {
                        if (response.success) {
                            button.removeClass("save-to-collection").addClass("saved-to-collection").text("Saved to Collection").prop("disabled", true);
                            button.after(\'<div class="reveal-text"><a href="/my-collection">Click to view your collection</a></div>\');
                        } else {
                            button.prop("disabled", false).text("Save to Collection");
                            alert("Error saving to collection: " + (response.data.message || "Unknown error"));
                        }
                    },
                    error: function() {
                        button.prop("disabled", false).text("Save to Collection");
                        alert("Error saving to collection. Please try again.");
                    }
                });
            });
        });
        </script>';
    }

    // Not logged in: show login/register CTA with split button
    if (!$user_is_logged_in) {
        $uid = 'ks' . uniqid(); // unique id for this instance
        echo <<<HTML
        <div class="full-page vertical-half-margin event-registration lead-generation">
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
            background: #871f80;
            color: #fff;
            border-radius: 3px 0 0 0;
            text-decoration: none;
        }

        .ks-toggle-btn {
            flex: 0 0 60px;
            background: #871f80;
            color: #fff;
            border-radius: 0 3px 0 0;
            justify-content: center;
        }

        .ks-toggle-btn:hover {
            background: #6e1a6e;
        }

        .ks-toggle-btn[aria-expanded="true"] {
            background: #6e1a6e;
        }

        .ks-toggle-btn i {
            font-family: 'Font Awesome 6 Pro';
            font-weight: 400;
            content: '\f078'; /* Unicode for fa-chevron-down */
        }

        .ks-toggle-btn[aria-expanded="true"] i {
            content: '\f077'; /* Unicode for fa-chevron-up */
        }

        /* Dropdown menu (hidden by default, animated slide) */
        .ks-menu {
            position: absolute;
            top: calc(100% + 0px);
            left: 50%;
            transform: translateX(-50%);
            width: 50%;
            background-color:#871f80;
            border-radius: 0 0 3px 3px !important;
            margin: 0;
            padding: 0;
            list-style: none;
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

        .ks-menu a:hover {
            background: #007bbf;
        }

        .event-registration .reveal-text {
            padding:0.45rem 0.85rem;
            margin-top:4px;
        }
        </style>

        <div id="nf-login-modal-form" style="display:none;">
HTML;
        echo do_shortcode('[ninja_form id="3"]');
        echo <<<HTML
        </div>

        <script>
        // Define openLoginModal function globally
        function openLoginModal() {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <button class="modal-close">&times;</button>
                    <div class="modal-body"></div>
                </div>
            `;
            document.body.appendChild(modal);

            // Move the form HTML into the modal body
            const formDiv = document.getElementById('nf-login-modal-form');
            if (formDiv && formDiv.firstElementChild) {
                modal.querySelector('.modal-body').appendChild(formDiv.firstElementChild.cloneNode(true));
            } else {
                modal.querySelector('.modal-body').innerHTML = '<p>Form could not be loaded. Please try again later.</p>';
            }

            // Close modal functionality
            const closeButton = modal.querySelector('.modal-close');
            closeButton.addEventListener('click', function () {
                document.body.removeChild(modal);
            });

            // Close modal when clicking outside
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    document.body.removeChild(modal);
                }
            });
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
        return ob_get_clean();
    }

    // Show form and button for logged-in users
    echo '<div class="full-page vertical-half-margin event-registration">';
    echo $button_html;
    echo $reveal_text;
    
    // Show form for logged-in users who haven't completed it
    if ($user_is_logged_in && !$has_completed_form) {
        echo '<div class="gated-lead-form-content">';
        
        $extra_fields_markup = '';
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
        if (!empty($extra_fields_markup)) {
            echo $extra_fields_markup;
        }
        echo do_shortcode('[ninja_form id="' . esc_attr($lead_form['id']) . '"]');
        echo '</div>'; // close .gated-lead-form-content
        
        // Add JavaScript to inject ACF answers into Ninja Form on submission
        if (!empty($acf_questions)) {
            echo '<script>
            jQuery(document).ready(function($) {
                // Function to get ACF answers
                function getAcfAnswers() {
                    var answers = {};
                    $("[id^=\'acf_question_\']").each(function() {
                        var fieldId = $(this).attr("id");
                        var fieldName = $(this).attr("name");
                        var value = "";

                        if ($(this).is(":checkbox")) {
                            var checkedValues = [];
                            $("[name=\'" + fieldName + "\']:checked").each(function() {
                                checkedValues.push($(this).val());
                            });
                            value = checkedValues.join(", ");
                        } else if ($(this).is(":radio")) {
                            value = $("[name=\'" + fieldName + "\']:checked").val() || "";
                        } else {
                            value = $(this).val() || "";
                        }

                        if (value) {
                            answers[fieldName] = value;
                        }
                    });
                    return answers;
                }

                // Function to inject ACF answers into Ninja Form
                function injectAcfAnswersToNinjaForm(ninjaForm) {
                    var acfAnswers = getAcfAnswers();
                    console.log("ACF Questions Debug:", acfAnswers);

                    // Create hidden fields for each ACF answer
                    Object.keys(acfAnswers).forEach(function(fieldName) {
                        var existingHidden = ninjaForm.querySelector("[name=\'" + fieldName + "\']");
                        if (existingHidden) {
                            existingHidden.value = acfAnswers[fieldName];
                        } else {
                            var hiddenField = document.createElement("input");
                            hiddenField.type = "hidden";
                            hiddenField.name = fieldName;
                            hiddenField.value = acfAnswers[fieldName];
                            ninjaForm.appendChild(hiddenField);
                        }
                    });
                }

                // Wait for Ninja Form to be ready
                function setupNinjaFormIntegration() {
                    var ninjaForm = document.querySelector(".ninja-forms-form");
                    if (!ninjaForm) {
                        setTimeout(setupNinjaFormIntegration, 300);
                        return;
                    }

                    // Avoid double-binding
                    if (ninjaForm.getAttribute("data-acf-inject-listener")) return;
                    ninjaForm.setAttribute("data-acf-inject-listener", "1");

                    // Listen for form submission
                    ninjaForm.addEventListener("submit", function(e) {
                        injectAcfAnswersToNinjaForm(ninjaForm);
                    });
                }

                // Initialize when page loads
                setupNinjaFormIntegration();

                // Also try when Ninja Forms fires its ready event
                $(document).on("nfFormReady", function(e) {
                    setTimeout(setupNinjaFormIntegration, 100);
                });
            });
            </script>';
        }
    }
    
    echo '</div>';

    return ob_get_clean();
}
add_shortcode('workbooks-lead-generation-registration', 'workbooks_lead_generation_registration_shortcode');