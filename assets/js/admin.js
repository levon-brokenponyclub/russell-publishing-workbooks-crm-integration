(function($){
    // Debug: indicate the admin script has loaded
    if (window.console && console.log) {
        console.log('DTR Workbooks admin.js loaded');
    }
    // Function to get active tab from URL hash or localStorage
    function getActiveTab(){
        var hash = window.location.hash || localStorage.getItem('dtr_workbooks_active_tab') || '#settings';
        return hash.replace('#','');
    }

    // Function to set active tab
    function setActiveTab(tabId){
        // Update UI
        $('.nav-tab').removeClass('nav-tab-active');
        $('a[href="#' + tabId + '"]').addClass('nav-tab-active');
        $('.tab-content').hide();
        $('#' + tabId).show();

        // Store state
        localStorage.setItem('dtr_workbooks_active_tab', '#' + tabId);
        history.replaceState(null, null, '#' + tabId);
    }

    $(document).ready(function(){
        // Tab click handler
        $(document).on('click', '.nav-tab', function(e){
            if (window.console && console.log) {
                console.log('nav-tab clicked:', this, $(this).attr('href'));
            }
            e.preventDefault();
            setActiveTab($(this).attr('href').substring(1));
        });

        // Initialize active tab
        var activeTab = getActiveTab();
        setActiveTab(activeTab);

        // Handle browser back/forward
        $(window).on('hashchange', function(){
            setActiveTab(getActiveTab());
        });

        // Form submit confirmation + remember tab
        $(document).on('submit', '#workbooks_update_user_form', function(){
            if (!confirm('Are you sure you want to update this Workbooks record?')) {
                return false;
            }
            localStorage.setItem('dtr_workbooks_form_submitted', 'true');
            localStorage.setItem('dtr_workbooks_last_active_tab', getActiveTab());
            return true;
        });

        // Auto-dismiss notices
        setTimeout(function(){ $('.notice.is-dismissible').fadeOut('slow'); }, 5000);

        // Checkbox styling
        $(document).on('change', 'input[type=checkbox]', function(){
            $(this).closest('label').css('font-weight', $(this).is(':checked') ? 'bold' : 'normal');
        }).trigger('change');
    });
})(jQuery);
