/* DTR Workbooks Frontend Scripts */
(function($) {
    'use strict';

    // Debug flag
    const DEBUG = window.dtr_workbooks_ajax?.debug_mode || false;

    function log(message, data = '') {
        if (DEBUG) {
            console.log('[DEBUG]', message, data ? data : '');
        }
    }

    // Initialize once DOM is ready
    $(function() {
        log('Frontend script loaded');

        // Speaker question handling only if elements present AND likely webinar context
        const speakerQuestions = $('.speaker-question');
        if (speakerQuestions.length > 0 || $('.gated-lead-form-content.webinars').length) {
            log('.speaker-question fields found:', speakerQuestions.length);
            speakerQuestions.each(function(index) {
                const field = $(this);
                field.addClass('show');
                log('Show Class Added to field #' + index, ' Current classList: ' + field.attr('class'));
            });
        }

        // Add form submission monitoring if webinar form is present
        if ($('.gated-lead-form-content.webinars').length) {
            log('🎥 WEBINAR FORM DETECTED');
            monitorWebinarForm();
        }
    });

    // Monitor webinar form submissions
    function monitorWebinarForm() {
        let attempts = 0;
        const maxAttempts = 5;

        function detectForm() {
            log('🔄 Webinar form detection attempt #' + (attempts + 1));
            
            const form = $('.nf-form-cont');
            if (form.length) {
                log('✅ Webinar form detection complete!');
                setupFormHandlers(form);
                return;
            }

            attempts++;
            if (attempts < maxAttempts) {
                setTimeout(detectForm, 1000);
            }
        }

        detectForm();
    }

    // Setup form submission handlers
    function setupFormHandlers(form) {
        const formFields = form.find('.nf-field-container');
        
        // Log form fields for debugging
        log('📝 Form fields found:', formFields.length);
        formFields.each(function(index) {
            const field = $(this);
            const fieldId = field.find('.nf-field').attr('id');
            const fieldType = field.attr('class').split(' ')[1].replace('-container', '');
            console.log(`    ${index + 1}. ${fieldType} - ${fieldId}`);
        });
    }

})(jQuery);
