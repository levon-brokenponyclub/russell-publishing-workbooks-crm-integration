// Webinar Class Form Handling
(function($) {
    'use strict';

    // Make submitWebinarClassForm globally accessible
    window.submitWebinarClassForm = function() {
        if (!validateWebinarClassForm()) {
            return;
        }

        const formData = new FormData(document.getElementById('webinarClassForm'));
        
        // Add submission timestamp
        const submitTimeField = document.getElementById('submitTime');
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

        // Add required form data for class-based submission
        formData.append('action', 'dtr_submit_webinar_class');
        formData.append('_wpnonce', dtrWebinarClassAjax.nonce);
        formData.append('submit_webinar_registration', '1');

        // Ensure critical fields are included
        const postId = document.getElementById('postId')?.value;
        const firstName = document.getElementById('firstName')?.value || '';
        const lastName = document.getElementById('lastName')?.value || '';
        const email = document.getElementById('email')?.value || '';
        const personId = document.getElementById('personId')?.value || '';
        const workbooksReference = document.getElementById('workbooksReference')?.value || '';
        const speakerQuestion = document.getElementById('speakerQuestion')?.value || '';
        const sponsorOptin = document.getElementById('sponsor_optin')?.checked ? '1' : '0';

        // Add form fields explicitly
        formData.append('post_id', postId);
        formData.append('first_name', firstName);
        formData.append('last_name', lastName);
        formData.append('email', email);
        formData.append('person_id', personId);
        formData.append('workbooks_reference', workbooksReference);
        formData.append('speaker_question', speakerQuestion);
        formData.append('sponsor_optin', sponsorOptin);

        // Get post title for logging
        const postTitle = document.getElementById('postTitle')?.value || 
                         document.querySelector('h1')?.textContent || 
                         document.title || 'Unknown';
        
        // Get additional questions data
        const additionalQuestions = [];
        const questionElements = document.querySelectorAll('[id^="question_"]');
        questionElements.forEach(element => {
            const questionTitle = element.getAttribute('name')?.replace('additional_question[', '').replace(']', '') || 'Unknown Question';
            const answer = element.value || 'No answer';
            additionalQuestions.push({ title: questionTitle, answer: answer });
        });

        // Comprehensive form submission logging
        const userStatus = dtrWebinarClassAjax.is_user_logged_in ? 'Logged In' : 'Logged Out';
        console.log('User Status:', userStatus);
        console.log('Registered: No (submitting registration)');
        console.log('Post Title:', postTitle);
        console.log('Post ID:', postId);
        console.log('Workbooks Reference:', workbooksReference);
        console.log('First Name:', firstName);
        console.log('Last Name:', lastName);
        console.log('Email Address:', email);
        console.log('Person ID:', personId);
        console.log('Speaker Question:', speakerQuestion || 'No question');
        
        if (additionalQuestions.length > 0) {
            console.log('Additional Questions:', additionalQuestions.length);
            additionalQuestions.forEach(q => {
                console.log(q.title + ':', q.answer);
            });
        } else {
            console.log('Additional Questions: None');
        }

        // Show loading overlay
        showProgressLoader();
        updateFormProgress('start');

        // Submit form
        fetch(dtrWebinarClassAjax.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateFormProgress('completed');
                
                // Start countdown and redirect
                setTimeout(() => {
                    startSubmissionCountdown();
                }, 500);
                
            } else {
                hideProgressLoader();
                alert(data.data?.message || data.message || 'Registration failed. Please try again.');
            }
        })
        .catch(error => {
            hideProgressLoader();
            alert('An error occurred. Please try again.');
        });
    };

    // Form validation helper
    window.validateWebinarClassForm = function() {
        const requiredFields = document.querySelectorAll('#webinarClassForm input[required], #webinarClassForm select[required], #webinarClassForm input[type="checkbox"][required]');
        for (let field of requiredFields) {
            if (field.type === 'checkbox') {
                if (!field.checked) {
                    alert('Please agree to the privacy policy to continue.');
                    field.focus();
                    return false;
                }
            } else if (!field.value.trim()) {
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

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Display comprehensive user and form status
        if (typeof dtrWebinarClassAjax !== 'undefined') {
            const userStatus = dtrWebinarClassAjax.is_user_logged_in ? 'Logged In' : 'Logged Out';
            const postTitle = dtrWebinarClassAjax.post_data?.title || 'Unknown';
            const postId = dtrWebinarClassAjax.post_data?.id || 'Unknown';
            
            // Get form data if available
            const firstName = dtrWebinarClassAjax.user_data?.first_name || '';
            const lastName = dtrWebinarClassAjax.user_data?.last_name || '';
            const email = dtrWebinarClassAjax.user_data?.email || '';
            const personId = dtrWebinarClassAjax.person_id || '';
            
            // Get workbooks reference from form
            const workbooksRef = document.getElementById('workbooksReference')?.value || 'Not Set';
            
            // Check if user is registered (based on form presence)
            const classForm = document.getElementById('webinarClassForm');
            const registeredStatus = classForm ? 'No' : 'Yes';
            
            console.log('User Status:', userStatus);
            console.log('Registered:', registeredStatus);
            console.log('Post Title:', postTitle);
            console.log('Post ID:', postId);
            console.log('Workbooks Reference:', workbooksRef);
            console.log('First Name:', firstName);
            console.log('Last Name:', lastName);
            console.log('Email Address:', email);
            console.log('Person ID:', personId);
            console.log('Speaker Question: Not Available (form not submitted)');
            console.log('Additional Questions: Not Available (form not submitted)');
        }
        
        // Attach submit handler to class-based form
        $(document).on('click', '#submitWebinarClassBtn', function(e) {
            e.preventDefault();
            submitWebinarClassForm();
        });
    });

})(jQuery);