(function($) {
    'use strict';

    $(document).ready(function() {
        // Check if any Ninja Forms exist on the page
        if ($('.nf-form-cont').length > 0) {
            console.log('HEY I AM HERE');
            console.log('Forms found:', $('.nf-form-cont').length);
        }

        // Also check when Ninja Forms is fully loaded
        $(document).on('nfFormReady', function() {
            console.log('HEY I AM HERE');
            console.log('Ninja Forms Ready Event Triggered');
        });
    });

})(jQuery);
