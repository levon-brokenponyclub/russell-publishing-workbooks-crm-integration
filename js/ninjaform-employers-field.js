(function($) {
    console.log('ninjaform-employers-field.js: Script loaded at ' + new Date().toISOString());

    // Check dependencies
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

    // Function to initialize the employer dropdown
    function initializeEmployerDropdown() {
        console.log('ninjaform-employers-field.js: Attempting to initialize dropdown');
        var $select = $('#nf-field-218');
        if ($select.length === 0) {
            console.warn('Ninja Forms employer field with ID nf-field-218 not found');
            setTimeout(initializeEmployerDropdown, 1000); // Retry after 1s
            return;
        }

        // Clear existing options to prevent static interference
        $select.empty().append('<option value="">Select an employer</option>');

        console.log('ninjaform-employers-field.js: Found #nf-field-218, initializing Select2');
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
                url: workbooks_ajax.plugin_url + 'employers.json',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        term: params.term || '' // Search term for filtering (optional server-side)
                    };
                },
                processResults: function(data) {
                    console.log('Employers loaded from JSON: ' + (data ? data.length : 0) + ' entries');
                    return {
                        results: $.map(data || [], function(org) {
                            return { id: org.name, text: org.name };
                        })
                    };
                },
                cache: true,
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Failed to load employers.json: ' + textStatus + ' - ' + errorThrown);
                    fetchEmployersFromAjax($select);
                }
            },
            minimumInputLength: 1
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

    // Fallback function to fetch employers via AJAX
    function fetchEmployersFromAjax($select) {
        console.log('Falling back to AJAX for employer data');
        $.post(workbooks_ajax.ajax_url, {
            action: 'fetch_workbooks_organisations',
            nonce: workbooks_ajax.nonce
        }, function(response) {
            $select.empty().append('<option value="">Select an employer</option>');
            if (response.success && response.data && Array.isArray(response.data)) {
                $.each(response.data, function(index, org) {
                    $select.append('<option value="' + org.name + '">' + org.name + '</option>');
                });
                console.log('Employers loaded from AJAX: ' + response.data.length + ' entries');
            } else {
                console.error('Error loading organisations from AJAX:', response.data);
                $select.append('<option value="">No employers found</option>');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX request failed for organisations: ' + textStatus + ' - ' + errorThrown);
            $select.append('<option value="">Error loading employers</option>');
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        console.log('ninjaform-employers-field.js: Document ready');
        if ($('#nf-form-15-cont').length > 0) {
            initializeEmployerDropdown();
        } else {
            console.log('Form ID 15 not found on this page, skipping employer dropdown init.');
        }
    });

    // Initialize on ninjaFormsLoaded
    $(document).on('ninjaFormsLoaded', function() {
        console.log('ninjaform-employers-field.js: ninjaFormsLoaded event fired');
        if ($('#nf-form-15-cont').length > 0) {
            initializeEmployerDropdown();
        } else {
            console.log('Form ID 15 not found on this page, skipping employer dropdown init.');
        }
    });
})(jQuery);