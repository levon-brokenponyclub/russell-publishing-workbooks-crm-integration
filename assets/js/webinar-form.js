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

        // Add progressive updates during submission
        setTimeout(() => updateProgressStep('validating'), 1000);
        setTimeout(() => updateProgressStep('submitting'), 2000);

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

    // Loading overlay helpers - Use HeroUI-style overlay structure from shortcode
    window.showProgressLoader = function() {
        const loadingOverlay = document.getElementById('formLoaderOverlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'flex';
            
            // Set header z-index to ensure overlay appears above it
            const header = document.querySelector('header');
            if (header) {
                header.style.zIndex = '1';
            }
            
            // Reset progress elements for new HeroUI design
            const progressCircle = document.getElementById('progressCircle');
            const progressValue = document.getElementById('progressValue');
            
            if (progressCircle) progressCircle.style.strokeDashoffset = '283'; // 0%
            if (progressValue) progressValue.textContent = '0%';
            
            // Trigger fade-in animation
            setTimeout(() => {
                loadingOverlay.classList.add('show');
            }, 10);
        } else {
            console.error('[Webinar Form] formLoaderOverlay not found');
        }
    };

    window.hideProgressLoader = function() {
        const loadingOverlay = document.getElementById('formLoaderOverlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'none';
            loadingOverlay.classList.remove('show', 'fade-out');
            
            // Restore header z-index when hiding overlay
            const header = document.querySelector('header');
            if (header) {
                header.style.zIndex = '';
            }
        }
    };

    window.updateProgressStep = function(step) {
        switch(step) {
            case 'start':
                updateFormProgress(25, 'Processing your registration...');
                break;
            case 'validating':
                updateFormProgress(50, 'Validating information...');
                break;
            case 'submitting':
                updateFormProgress(75, 'Submitting to Workbooks...');
                break;
            case 'completed':
                updateFormProgress(100, 'Registration successful!');
                break;
            default:
                updateFormProgress(10, 'Processing...');
        }
    };

    // Real-time progress updater that matches actual submission stages
    window.updateFormProgress = function(stage, message) {
        const progressCircle = document.getElementById('progressCircle');
        const progressValue = document.getElementById('progressValue');
        
        if (progressCircle && progressValue) {
            // Calculate stroke offset (283 is full circle, 0 is 100%)
            const offset = 283 - (stage / 100) * 283;
            progressCircle.style.strokeDashoffset = offset.toString();
            progressValue.textContent = stage + '%';
            console.log(`🔄 Progress Update: ${stage}% - ${message}`);
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