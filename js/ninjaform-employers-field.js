(function($) {
    console.log('ninjaform-employers-field.js: Script loaded at ' + new Date().toISOString());

    if (typeof $ === 'undefined') {
        console.error('jQuery is not loaded');
        return;
    }
    if (typeof $.fn.select2 === 'undefined') {
        console.error('Select2 is not loaded');
        return;
    }
    if (typeof workbooks_ajax === 'undefined') {
        console.error('workbooks_ajax is not defined');
        return;
    }

    function initializeEmployerDropdown() {
        console.log('ninjaform-employers-field.js: Attempting to initialize dropdown');
        var $select = $('#nf-field-218');
        if ($select.length === 0) {
            console.warn('Ninja Forms employer field with ID nf-field-218 not found');
            setTimeout(initializeEmployerDropdown, 1000);
            return;
        }

        $select.empty().append('<option value="">Select an employer</option>');

        $select.select2({
            placeholder: 'Select an employer or type to add new',
            allowClear: true,
            width: '100%',
            tags: true,
            createTag: function (params) {
                var term = $.trim(params.term);
                if (term === '') {
                    return null;
                }
                return {
                    id: term,
                    text: term + ' (New)',
                    newTag: true
                };
            },
            ajax: {
                url: workbooks_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'fetch_workbooks_organisations',
                        nonce: workbooks_ajax.nonce,
                        term: params.term || '',
                        page: params.page || 1
                    };
                },
                processResults: function(response, params) {
                    params.page = params.page || 1;
                    if (!response.success || !Array.isArray(response.data)) {
                        return { results: [] };
                    }
                    return {
                        results: response.data.map(function(org) {
                            return { id: org.name, text: org.name };
                        }),
                        pagination: {
                            more: response.more || false
                        }
                    };
                },
                cache: true
            },
            minimumInputLength: 2
        }).on('select2:open', function() {
            console.log('Select2 dropdown opened for #nf-field-218');
        }).on('select2:select', function(e) {
            var selectedData = e.params.data;
            if (selectedData.newTag) {
                console.log('New employer created: ' + selectedData.id);
            } else {
                console.log('Selected existing employer: ' + selectedData.id);
            }
        });
    }

    $(document).ready(function() {
        console.log('ninjaform-employers-field.js: Document ready');
        if ($('#nf-form-15-cont').length > 0) {
            initializeEmployerDropdown();
        } else {
            console.log('Form ID 15 not found on this page, skipping employer dropdown init.');
        }
    });

    $(document).on('ninjaFormsLoaded', function() {
        console.log('ninjaform-employers-field.js: ninjaFormsLoaded event fired');
        if ($('#nf-form-15-cont').length > 0) {
            initializeEmployerDropdown();
        } else {
            console.log('Form ID 15 not found on this page, skipping employer dropdown init.');
        }
    });
})(jQuery);