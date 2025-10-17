// Webinar Form Handling
(function($) {
    'use strict';

    // Helper function to reset submit buttons (handles multiple buttons)
    function resetSubmitButton() {
        const $buttons = $('#submitWebinarBtn');
        $buttons.prop('disabled', false)
                .removeClass('submitting')
                .text('Register');
        console.log('[Webinar Form] 🔓 All buttons re-enabled (' + $buttons.length + ' buttons)');
    }

    // Make submitWebinarForm globally accessible
    window.submitWebinarForm = function() {
        console.log('[Webinar Form] 🚀 submitWebinarForm() called');
        
        if (!validateWebinarForm()) {
            console.log('[Webinar Form] ❌ Validation failed');
            resetSubmitButton();
            return;
        }

        console.log('[Webinar Form] ✅ Validation passed');

        // Handle multiple forms - use the first one found
        const webinarForms = document.querySelectorAll('#webinarForm');
        console.log('[Webinar Form] Found forms:', webinarForms.length);
        
        if (webinarForms.length === 0) {
            console.error('[Webinar Form] ❌ No webinar form found!');
            resetSubmitButton();
            return;
        }
        
        const formToUse = webinarForms[0];
        console.log('[Webinar Form] Using form:', formToUse);
        const formData = new FormData(formToUse);
        
        // Add submission timestamp
        const submitTimeField = formToUse.querySelector('#submitTime');
        if (submitTimeField) {
            submitTimeField.value = new Date().toISOString();
        }
        
        // Add tracking cookie if it exists
        const trackingCookie = getCookie('dtr_visitor_id');
        if (trackingCookie) {
            formData.append('visitor_id', trackingCookie);
        }

        // Add UTM parameters if they exist
        const urlParams = new URLSearchParams(window.location.search);
        const utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        utmParams.forEach(param => {
            if (urlParams.has(param)) {
                formData.append(param, urlParams.get(param));
            }
        });

        // Add required form data
        formData.append('action', 'dtr_handle_webinar_submission');
        formData.append('_wpnonce', dtrWebinarAjax.nonce);
        formData.append('submit_webinar_registration', '1');

        // Add form fields using the selected form
        const postTitle = formToUse.querySelector('#postTitle');
        const postId = formToUse.querySelector('#postId');
        const workbooksReference = formToUse.querySelector('#workbooksReference');
        const speakerQuestion = formToUse.querySelector('#speakerQuestion');
        const optinCheckbox = formToUse.querySelector('#cf_mailing_list_member_sponsor_1_optin');
        
        if (postTitle) formData.append('post_title', postTitle.value);
        if (postId) formData.append('post_id', postId.value);
        if (workbooksReference) formData.append('workbooks_reference', workbooksReference.value);
        if (speakerQuestion) formData.append('speaker_question', speakerQuestion.value || '');
        if (optinCheckbox) formData.append('cf_mailing_list_member_sponsor_1_optin', optinCheckbox.checked ? '1' : '0');
        
        // User data is extracted server-side for logged-in users - only add person_id if available
        const personIdField = formToUse.querySelector('#personId');
        const personId = personIdField?.value || '';
        
        if (personId) {
            formData.append('person_id', personId);
        }
        
        // Debug: Log the actual field values being sent
        console.log('[Webinar Form] 🚀 FORM SUBMISSION DEBUG - Field values being sent:');
        console.log('- Person ID:', personId);
        console.log('- Post ID:', postId?.value || 'Not found');
        console.log('- Post Title:', postTitle?.value || 'Not found');
        console.log('- Speaker Question:', speakerQuestion?.value || 'None');
        console.log('- Opt-in:', optinCheckbox?.checked || false);
        
        // Log all form data entries
        console.log('[Webinar Form] All FormData entries:');
        for (let [key, value] of formData.entries()) {
            console.log(`- ${key}:`, value);
        }

        // Show loading overlay - use the function from the shortcode
        if (typeof window.showProgressLoader === 'function') {
            console.log('[Webinar Form] 🎯 Calling showProgressLoader from shortcode');
            window.showProgressLoader();
        } else {
            console.error('[Webinar Form] ❌ showProgressLoader function not available');
        }

        // Add progressive updates during submission with enhanced messaging
        setTimeout(() => {
            if (typeof window.updateFormProgress === 'function') {
                window.updateFormProgress(25, 'Validating data...');
            }
        }, 500);
        
        setTimeout(() => {
            if (typeof window.updateFormProgress === 'function') {
                window.updateFormProgress(50, 'Processing registration...');
            }
        }, 1500);
        
        setTimeout(() => {
            if (typeof window.updateFormProgress === 'function') {
                window.updateFormProgress(75, 'Syncing with CRM...');
            }
        }, 2500);

        // Submit form
        console.log('[Webinar Form] 📤 Sending AJAX request to:', dtrWebinarAjax.ajaxurl);
        
        fetch(dtrWebinarAjax.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            console.log('[Webinar Form] 📥 Response received:', response.status, response.statusText);
            return response.json();
        })
        .then(data => {
            console.log('[Webinar Form] 📋 Response data:', data);
            if (data.success) {
                // Update progress to 100%
                if (typeof window.updateFormProgress === 'function') {
                    window.updateFormProgress(90, 'Finalizing access...');
                    setTimeout(() => {
                        window.updateFormProgress(100, 'Complete!');
                    }, 500);
                }
                
                // At 100%, fade out and redirect
                setTimeout(() => {
                    if (typeof window.slideOutLoader === 'function') {
                        window.slideOutLoader();
                        
                        // Redirect after fade animation completes (500ms)
                        setTimeout(() => {
                            if (data.redirect_url) {
                                window.location.href = data.redirect_url;
                            } else {
                                // Default redirect to thank you page
                                window.location.href = '/thank-you-for-registering-webinars/';
                            }
                        }, 500);
                    } else {
                        // Fallback if slideOutLoader not available
                        if (data.redirect_url) {
                            window.location.href = data.redirect_url;
                        } else {
                            window.location.href = '/thank-you-for-registering-webinars/';
                        }
                    }
                }, 500);
                
            } else {
                if (typeof window.hideProgressLoader === 'function') {
                    window.hideProgressLoader();
                }
                resetSubmitButton(); // Re-enable button on failure
                alert(data.message || 'Registration failed. Please try again.');
            }
        })
        .catch(error => {
            console.error('[Webinar Form] ❌ Form submission error:', error);
            if (typeof window.hideProgressLoader === 'function') {
                window.hideProgressLoader();
            }
            resetSubmitButton(); // Re-enable button on error
            alert('An error occurred. Please try again.');
        });
    };

    // Form validation helper
    window.validateWebinarForm = function() {
        console.log('[Webinar Form] 🔍 Validating form...');
        
        // Get the first webinar form
        const webinarForms = document.querySelectorAll('#webinarForm');
        if (webinarForms.length === 0) {
            console.error('[Webinar Form] ❌ No webinar form found for validation');
            return false;
        }
        
        const formToValidate = webinarForms[0];
        const requiredFields = formToValidate.querySelectorAll('input[required], select[required], textarea[required]');
        
        console.log('[Webinar Form] Found required fields:', requiredFields.length);
        
        for (let field of requiredFields) {
            console.log('[Webinar Form] Checking field:', field.name || field.id, 'Value:', field.value);
            if (!field.value.trim()) {
                console.log('[Webinar Form] ❌ Required field is empty:', field.name || field.id);
                alert('Please fill in all required fields marked with *');
                field.focus();
                return false;
            }
        }
        
        console.log('[Webinar Form] ✅ All required fields validated');
        return true;
    };

    // Helper function to get cookies
    window.getCookie = function(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
    };

    // No duplicate loader functions - use the ones from the shortcode

    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log('[Webinar Form] Initializing...');
        
        // Debug: Check what forms are available
        const webinarForm = document.getElementById('webinarForm');
        const allForms = document.querySelectorAll('form');
        console.log('[Webinar Form] webinarForm found:', webinarForm ? 'YES' : 'NO');
        console.log('[Webinar Form] Total forms on page:', allForms.length);
        if (allForms.length > 0) {
            allForms.forEach((form, index) => {
                console.log(`[Webinar Form] Form ${index}: ID="${form.id}", class="${form.className}"`);
            });
        }

        // Attach submit handler with double-click prevention - handle duplicate forms
        const submitButtons = $('#submitWebinarBtn');
        console.log('[Webinar Form] Found submit buttons:', submitButtons.length);
        
        submitButtons.each(function(index) {
            console.log(`[Webinar Form] Button ${index}: ID="${this.id}", classes="${this.className}"`);
        });
        
        // Use event delegation to handle all submit buttons
        $(document).off('click', '#submitWebinarBtn').on('click', '#submitWebinarBtn', function(e) {
            console.log('[Webinar Form] 🎯 Submit button clicked via delegation');
            e.preventDefault();
            e.stopPropagation();
            
            const $button = $(this);
            console.log('[Webinar Form] Button element:', $button[0]);
            
            // Prevent double submissions across all buttons
            const allButtons = $('#submitWebinarBtn');
            if (allButtons.prop('disabled') || allButtons.hasClass('submitting')) {
                console.log('[Webinar Form] ⚠️ Submission already in progress - ignoring click');
                return false;
            }
            
            // Disable all buttons immediately (without changing text - overlay loader handles feedback)
            allButtons.prop('disabled', true)
                      .addClass('submitting');
            
            console.log('[Webinar Form] 🔒 All buttons disabled - calling submitWebinarForm()');
            submitWebinarForm();
        });

        // Log initialization data
        console.log('[Webinar Form] Form initialized with data:', {
            'Post Title': $('#postTitle').val(),
            'Post ID': $('#postId').val(),
            'Person ID': $('#personId').val(),
            'Workbooks Reference': $('#workbooksReference').val(),
            'Speaker Question Field': $('#speakerQuestion').length > 0 ? 'Present' : 'Not present',
            'Consent Checkbox': $('#cf_mailing_list_member_sponsor_1_optin').length > 0 ? 'Present' : 'Not present'
        });

        // Verify dtrWebinarAjax is available
        if (typeof dtrWebinarAjax === 'undefined') {
            console.error('[Webinar Form] dtrWebinarAjax not defined - script not properly localized');
        } else {
            console.log('[Webinar Form] dtrWebinarAjax available:', dtrWebinarAjax);
        }

        // Verify jQuery is available
        if (typeof jQuery === 'undefined') {
            console.error('[Webinar Form] jQuery not loaded');
        } else {
            console.log('[Webinar Form] jQuery version:', jQuery.fn.jquery);
        }
    });

})(jQuery);