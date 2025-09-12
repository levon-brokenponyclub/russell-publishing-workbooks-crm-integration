
// DTR Workbooks Frontend Scripts (Simplified)
(function($) {
    'use strict';

    $(function() {
        // Post Type: Live Webinar
        console.log('Post Type: Live Webinar');

        // Form ID {2} Present
        const form2Present = $('#nf-form-2-cont').length > 0;
        console.log('Form ID {2} Present:', form2Present ? 'Yes' : 'No');

        // Person ID and Email Address (check global and form fields)
        let personId = '';
        let personEmail = '';
        let eventId = '';
        let questionForSpeakerPresent = 'Not Present';

        // Try global object first
        if (window.dtr_workbooks_ajax) {
            personId = window.dtr_workbooks_ajax.current_user_id || '';
            personEmail = window.dtr_workbooks_ajax.current_user_email || '';
        }

        // If form is present, check form fields for overrides or missing values
        if (form2Present) {
            const form = $('#nf-form-2-cont form');
            if (form.length) {
                // Event ID
                const eventField = form.find('[name="event_id"], [name="post_id"]');
                if (eventField.length && eventField.val()) {
                    eventId = eventField.val();
                }
                // Person ID
                const personIdField = form.find('[name="person_id"]');
                if (personIdField.length && personIdField.val()) {
                    personId = personIdField.val();
                }
                // Email
                const emailField = form.find('[name="email"], [name="user_email"], [name="email_address"]');
                if (emailField.length && emailField.val()) {
                    personEmail = emailField.val();
                }
                // Question for Speaker
                const qfsField = form.find('[name="question_for_speaker"]');
                if (qfsField.length) {
                    questionForSpeakerPresent = 'Present';
                }
            }
        }

        console.log('Person ID:', personId);
        console.log('Person Email Address:', personEmail);
        console.log('Event ID:', eventId);
        console.log('Question For Speaker:', questionForSpeakerPresent);
    });
})(jQuery);
