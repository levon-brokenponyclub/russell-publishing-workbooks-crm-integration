(function($) {
    'use strict';

    // Wait for document ready
    $(document).ready(function() {
        // First check if this is actually a webinar page
        const isWebinarPage = function() {
            // Check for webinar-specific class on the container
            return $('.gated-lead-form-content.webinars').length > 0 
                || $('body').hasClass('webinar-template') 
                || window.location.href.toLowerCase().includes('webinar');
        };

        // Only proceed with webinar detection if we're on a webinar page
        if (!isWebinarPage()) {
            console.log('📄 Not a webinar page - skipping webinar form detection');
            return;
        }

        // If we are on a webinar page, then proceed with form detection
        console.log('🎥 WEBINAR PAGE DETECTED - Starting form detection');
        
        const detectWebinarForm = function() {
            const form = $('.nf-form-cont');
            if (!form.length) {
                console.log('❌ No Ninja Forms found on page');
                return;
            }

            // Check if it's specifically form ID 2 (webinar form)
            const formId = form.attr('id');
            if (formId && formId === 'nf-form-2-cont') {
                console.log('✅ WEBINAR FORM DETECTED (Form ID 2)');
                console.log('📁 Handler File: /wp-content/plugins/dtr-workbooks-crm-integration/includes/gated-content-reveal.php');
                console.log('   ↳ Routes to: webinar-handler.php');
            } else {
                console.log('❌ Form found but not a webinar form (ID: ' + formId + ')');
            }
        };

        // Run initial detection
        detectWebinarForm();

        // Also detect on Ninja Forms ready event
        $(document).on('nfFormReady', function() {
            console.log('🔄 Ninja Forms Ready - Checking for webinar form');
            detectWebinarForm();
        });
    });

})(jQuery);
