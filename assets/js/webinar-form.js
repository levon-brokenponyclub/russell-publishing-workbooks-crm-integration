// Webinar Form Handling
(function($) {
    'use strict';

    // Helper function to reset submit button
    function resetSubmitButton() {
        const $button = $('#submitWebinarBtn');
        $button.prop('disabled', false)
               .removeClass('submitting')
               .text('Register');
        console.log('[Webinar Form] 🔓 Button re-enabled');
    }

    // Make submitWebinarForm globally accessible
    window.submitWebinarForm = function() {
        if (!validateWebinarForm()) {
            resetSubmitButton();
            return;
        }

        const formData = new FormData(document.getElementById('webinarForm'));
        
        // Add submission timestamp
        document.getElementById('submitTime').value = new Date().toISOString();
        
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
        formData.append('action', 'dtr_submit_webinar_shortcode');
        formData.append('_wpnonce', dtrWebinarAjax.nonce);
        formData.append('submit_webinar_registration', '1');

        // Add form fields - CRITICAL: Include email, first name, last name for guest users
        formData.append('post_title', document.getElementById('postTitle').value);
        formData.append('post_id', document.getElementById('postId').value);
        formData.append('workbooks_reference', document.getElementById('workbooksReference').value);
        formData.append('speaker_question', document.getElementById('speakerQuestion')?.value || '');
        formData.append('cf_mailing_list_member_sponsor_1_optin', 
            document.getElementById('cf_mailing_list_member_sponsor_1_optin').checked ? '1' : '0');
        
        // ESSENTIAL: Add user fields that were missing from form submission
        const email = document.getElementById('email')?.value || '';
        const firstName = document.getElementById('firstName')?.value || '';
        const lastName = document.getElementById('lastName')?.value || '';
        const personId = document.getElementById('personId')?.value || '';
        
        formData.append('email', email);
        formData.append('firstName', firstName);
        formData.append('lastName', lastName);
        formData.append('personId', personId);
        
        // Debug: Log the actual field values being sent
        console.log('[Webinar Form] 🚀 FORM SUBMISSION DEBUG - Field values being sent:');
        console.log('- Email:', email);
        console.log('- First Name:', firstName);
        console.log('- Last Name:', lastName);
        console.log('- Person ID:', personId);
        console.log('- Post ID:', document.getElementById('postId')?.value);
        console.log('- Post Title:', document.getElementById('postTitle')?.value);

        // Show loading overlay - use the function from the shortcode
        if (typeof window.showProgressLoader === 'function') {
            console.log('[Webinar Form] 🎯 Calling showProgressLoader from shortcode');
            window.showProgressLoader();
        } else {
            console.error('[Webinar Form] ❌ showProgressLoader function not available');
        }

        // Add progressive updates during submission
        setTimeout(() => {
            if (typeof window.updateFormProgress === 'function') {
                window.updateFormProgress(25, 'Validating security credentials...');
            }
        }, 500);
        
        setTimeout(() => {
            if (typeof window.updateFormProgress === 'function') {
                window.updateFormProgress(60, 'Processing webinar registration...');
            }
        }, 1500);

        // Submit form
        fetch(dtrWebinarAjax.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update progress to 100%
                if (typeof window.updateFormProgress === 'function') {
                    window.updateFormProgress(100, 'Registration successful!');
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
            console.error('Form submission error:', error);
            if (typeof window.hideProgressLoader === 'function') {
                window.hideProgressLoader();
            }
            resetSubmitButton(); // Re-enable button on error
            alert('An error occurred. Please try again.');
        });
    };

    // Form validation helper
    window.validateWebinarForm = function() {
        const requiredFields = document.querySelectorAll('#webinarForm input[required], #webinarForm select[required]');
        for (let field of requiredFields) {
            if (!field.value.trim()) {
                alert('Please fill in all required fields marked with *');
                field.focus();
                return false;
            }
        }
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

        // Attach submit handler with double-click prevention
        $('#submitWebinarBtn').on('click', function(e) {
            console.log('[Webinar Form] Submit button clicked');
            e.preventDefault();
            
            const $button = $(this);
            
            // Prevent double submissions
            if ($button.prop('disabled') || $button.hasClass('submitting')) {
                console.log('[Webinar Form] ⚠️ Submission already in progress - ignoring click');
                return false;
            }
            
            // Disable button immediately
            $button.prop('disabled', true)
                   .addClass('submitting')
                   .text('Submitting...');
            
            console.log('[Webinar Form] 🔒 Button disabled - calling submitWebinarForm()');
            submitWebinarForm();
        });

        // Log initialization data
        console.log('[Webinar Form] Form initialized with data:', {
            'First Name': $('#firstName').val(),
            'Last Name': $('#lastName').val(),
            'Email Address': $('#email').val(),
            'Post Title': $('#postTitle').val(),
            'Post ID': $('#postId').val(),
            'Person ID': $('#personId').val(),
            'Workbooks Reference': $('#workbooksReference').val()
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