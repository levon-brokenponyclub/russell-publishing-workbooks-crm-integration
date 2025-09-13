/* (function($) {
    'use strict';

    $(function() {
        // Only log user login state
        var loggedIn = false;
        if (window.dtr_workbooks_ajax && window.dtr_workbooks_ajax.current_user_id) {
            loggedIn = true;
        }
        console.log('User: ' + (loggedIn ? 'Logged In' : 'Logged Out'));
    });
})(jQuery); */