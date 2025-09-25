// Webinar Form Handling
(function($) {
    'use strict';

    // Make submitWebinarForm globally accessible
    window.submitWebinarForm = function() {
        if (!validateWebinarForm()) {
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

        // Show loading overlay
        showProgressLoader();
        updateProgressStep('start');

        // Submit form
        fetch(dtrWebinarAjax.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateProgressStep('completed');
                // Show success message
                const successMessage = document.createElement('div');
                successMessage.className = 'webinar-success-message';
                successMessage.innerHTML = `
                    <h3>Registration Successful!</h3>
                    <p>You have been registered for ${data.webinar_title}</p>
                    <p>A confirmation email has been sent to ${data.email_address}</p>
                `;
                
                // Replace form with success message
                const form = document.getElementById('webinarForm');
                if (form && form.parentNode) {
                    form.parentNode.replaceChild(successMessage, form);
                } else {
                    console.error('[Webinar Form] Could not find webinarForm element or its parent');
                    // Fallback: try to find any form in the page
                    const anyForm = document.querySelector('form');
                    if (anyForm && anyForm.parentNode) {
                        anyForm.parentNode.replaceChild(successMessage, anyForm);
                    } else {
                        // Last resort: append to body
                        document.body.appendChild(successMessage);
                    }
                }
                
                // Hide loader after showing message
                setTimeout(hideProgressLoader, 1000);
                
                // Redirect after delay if URL provided
                if (data.redirect_url) {
                    setTimeout(() => {
                        window.location.href = data.redirect_url;
                    }, 3000);
                }
            } else {
                hideProgressLoader();
                alert(data.message || 'Registration failed. Please try again.');
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            hideProgressLoader();
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

    // Loading overlay helpers
    window.showProgressLoader = function() {
        const loader = document.createElement('div');
        loader.id = 'dtr-form-loader';
        loader.className = 'dtr-form-loader';
        loader.innerHTML = '<div class="loader-content"><div class="spinner"></div><div class="progress-text">Processing...</div></div>';
        document.body.appendChild(loader);
    };

    window.hideProgressLoader = function() {
        const loader = document.getElementById('dtr-form-loader');
        if (loader) {
            loader.remove();
        }
    };

    window.updateProgressStep = function(step) {
        const loader = document.getElementById('dtr-form-loader');
        if (!loader) return;

        const progressText = loader.querySelector('.progress-text');
        if (!progressText) return;

        switch(step) {
            case 'start':
                progressText.textContent = 'Processing your registration...';
                break;
            case 'completed':
                progressText.textContent = 'Registration successful!';
                break;
            default:
                progressText.textContent = 'Processing...';
        }
    };

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

        // Attach submit handler
        $('#submitWebinarBtn').on('click', function(e) {
            console.log('[Webinar Form] Submit button clicked');
            e.preventDefault();
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