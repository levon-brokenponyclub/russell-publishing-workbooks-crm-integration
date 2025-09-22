/**
 * Gravity Forms Enhanced Employer Select Field Logic
 * Integrates with workbooks employers database via AJAX
 * Supports both Select2 and Chosen enhanced selects
 */

(function($) {
    'use strict';
    
    // Immediate load test
    jQuery(document).ready(function($) {
    'use strict';
    
    console.log('[GF Employers] Script loaded successfully - timestamp:', new Date().toISOString());
    
    if (typeof $ === 'undefined' || typeof workbooks_ajax === 'undefined') {
        console.warn('[GF Employers] jQuery or workbooks_ajax not available');
        console.log('[GF Employers] jQuery available:', typeof $);
        console.log('[GF Employers] workbooks_ajax available:', typeof workbooks_ajax);
        return;
    }

    var initAttempts = 0;
    var MAX_INIT_ATTEMPTS = 20; // ~12s
    var totalLogged = false;

    /**
     * Initialize employer field with dynamic data loading
     */
    function initEmployerField($el) {
        if (!$el || !$el.length) return;
        
        console.log('[GF Employers] Initializing employer field:', $el.attr('id'));
        
        // Determine if field is using Chosen or Select2
        var isChosen = $el.siblings('.chosen-container').length > 0;
        var isSelect2 = $el.hasClass('select2-hidden-accessible');
        
        if (isChosen) {
            initChosenEmployerField($el);
        } else if (isSelect2) {
            initSelect2EmployerField($el);
        } else {
            // Try to enhance with Select2 if available
            if (typeof $.fn.select2 !== 'undefined') {
                initSelect2EmployerField($el);
            } else {
                console.warn('[GF Employers] No enhancement library found, loading basic functionality');
                initBasicEmployerField($el);
            }
        }

        // Log total employers if not done yet
        if (!totalLogged) {
            fetchEmployerTotal();
        }

        console.log('%c[GF Employers] READY - Employer field initialized', 'color:#2e7d32;font-weight:bold');
    }

    /**
     * Initialize field using Chosen library
     */
    function initChosenEmployerField($el) {
        console.log('[GF Employers] Initializing with Chosen');
        
        // Load employers and populate the select
        loadEmployersForSelect($el, function() {
            // Refresh Chosen after options are loaded
            if ($el.data('chosen')) {
                $el.trigger('chosen:updated');
            } else {
                // Initialize Chosen if not already done
                $el.chosen({
                    placeholder_text_single: 'Select an employer or type to search',
                    allow_single_deselect: true,
                    search_contains: true,
                    width: '100%'
                });
            }
        });
        
        // Add search capability for Chosen
        $el.next('.chosen-container').on('keyup', '.chosen-search input', function() {
            var searchTerm = $(this).val();
            if (searchTerm.length >= 2) {
                searchAndUpdateEmployers($el, searchTerm, function() {
                    $el.trigger('chosen:updated');
                });
            }
        });
    }

    /**
     * Initialize field using Select2 library
     */
    function initSelect2EmployerField($el) {
        console.log('[GF Employers] Initializing with Select2');
        
        $el.empty();
        $el.select2({
            placeholder: 'Select an employer or type to search',
            allowClear: true,
            width: '100%',
            tags: true,
            minimumInputLength: 0,
            ajax: {
                url: workbooks_ajax.ajax_url,
                type: 'GET',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    var term = params.term || '';
                    var page = params.page || 1;
                    console.log('[GF Employers] AJAX request - term:', term, 'page:', page);
                    return {
                        action: 'fetch_workbooks_employers_select2',
                        nonce: workbooks_ajax.nonce,
                        term: term,
                        page: page
                    };
                },
                processResults: function(data, params) {
                    params.page = params.page || 1;
                    console.log('[GF Employers] Processing results:', data);
                    
                    var results = data.results || [];
                    var pagination = data.pagination || { more: false };
                    
                    return {
                        results: results,
                        pagination: {
                            more: pagination.more
                        }
                    };
                },
                cache: true
            },
            templateResult: function(item) {
                if (item.loading) return item.text;
                return item.text;
            },
            createTag: function(params) { 
                var term = $.trim(params.term); 
                if (!term) return null; 
                return { 
                    id: term, 
                    text: term + ' (New)', 
                    newTag: true 
                }; 
            }
        });
    }

    /**
     * Basic field initialization without enhancement libraries
     */
    function initBasicEmployerField($el) {
        console.log('[GF Employers] Initializing basic field');
        loadEmployersForSelect($el);
    }

    /**
     * Load employers from server and populate select field
     */
    function loadEmployersForSelect($el, callback) {
        $.post(workbooks_ajax.ajax_url, {
            action: 'fetch_workbooks_employers_paged',
            nonce: workbooks_ajax.nonce,
            offset: 0,
            limit: 500, // Load more for initial population
            search: ''
        }).done(function(resp) {
            if (resp && resp.success && resp.data && resp.data.employers) {
                var employers = resp.data.employers;
                console.log('[GF Employers] Loaded', employers.length, 'employers');
                
                // Clear existing options (keep first if it's a placeholder)
                var $firstOption = $el.find('option:first');
                var keepFirst = $firstOption.length && ($firstOption.val() === '' || $firstOption.text().toLowerCase().includes('select'));
                
                if (keepFirst) {
                    $el.find('option:not(:first)').remove();
                } else {
                    $el.empty();
                    $el.append('<option value="">Select an employer...</option>');
                }
                
                // Add employer options
                $.each(employers, function(index, employer) {
                    var optionText = employer.name || employer.text || employer.title;
                    var optionValue = employer.id || employer.value || optionText;
                    $el.append('<option value="' + optionValue + '">' + optionText + '</option>');
                });
                
                if (callback) callback();
            } else {
                console.error('[GF Employers] Failed to load employers:', resp);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('[GF Employers] AJAX error loading employers:', textStatus, errorThrown);
        });
    }

    /**
     * Search employers and update field options
     */
    function searchAndUpdateEmployers($el, searchTerm, callback) {
        $.post(workbooks_ajax.ajax_url, {
            action: 'fetch_workbooks_employers_select2',
            nonce: workbooks_ajax.nonce,
            term: searchTerm,
            page: 1
        }).done(function(resp) {
            if (resp && resp.results) {
                // Clear and repopulate options
                var selectedValue = $el.val();
                $el.empty();
                $el.append('<option value="">Select an employer...</option>');
                
                $.each(resp.results, function(index, item) {
                    var selected = item.id === selectedValue ? 'selected' : '';
                    $el.append('<option value="' + item.id + '" ' + selected + '>' + item.text + '</option>');
                });
                
                if (callback) callback();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('[GF Employers] Search failed:', textStatus, errorThrown);
        });
    }

    /**
     * Locate the employer field in the form
     */
    function locateEmployerField() {
        console.log('[GF Employers] Searching for employer field...');
        console.log('[GF Employers] All select elements:', $('select').map(function() { return this.id || this.name || 'unnamed'; }).get());
        console.log('[GF Employers] All input elements:', $('input[type!="hidden"]').map(function() { return this.id || this.name || 'unnamed'; }).get().slice(0, 10));
        
        // Primary target - field ID 37 in form 1
        var $el = $('#input_1_37');
        if ($el.length) {
            console.log('[GF Employers] Found target field: input_1_37');
            return $el;
        } else {
            console.log('[GF Employers] Primary target #input_1_37 not found');
        }
        
        // Fallback: look for select fields with employer-related attributes
        var candidates = $('select').filter(function() {
            var $this = $(this);
            var id = $this.attr('id') || '';
            var name = $this.attr('name') || '';
            var label = $this.siblings('label').text() || $this.closest('.gfield').find('label').text() || '';
            
            return /employer/i.test(id) || 
                   /employer/i.test(name) || 
                   /employer/i.test(label) ||
                   id.includes('input_1_37'); // Specific field ID pattern
        });
        
        if (candidates.length === 1) {
            console.log('[GF Employers] Found employer field by attribute matching:', candidates.first().attr('id'));
            return candidates.first();
        } else if (candidates.length > 1) {
            console.log('[GF Employers] Multiple employer field candidates found, using first:', candidates.map(function() { return this.id; }).get());
            return candidates.first();
        }
        
        return $();
    }

    /**
     * Initialize employer field with retry logic
     */
    function initWithRetry() {
        var $el = locateEmployerField();
        
        if (!$el.length) {
            initAttempts++;
            
            if (initAttempts === 1) {
                console.warn('[GF Employers] Employer field not found yet, waiting... (looking for #input_1_37)');
            } else if (initAttempts % 5 === 0) {
                console.warn('[GF Employers] Still waiting (#' + initAttempts + ' attempts). Available select IDs:', 
                    $('select').map(function() { return this.id || '(no id)'; }).get());
            }
            
            if (initAttempts < MAX_INIT_ATTEMPTS) {
                setTimeout(initWithRetry, 600);
            } else {
                console.error('[GF Employers] Gave up initializing after ' + MAX_INIT_ATTEMPTS + ' attempts.');
            }
            return;
        }
        
        console.log('[GF Employers] Initializing employer field on:', $el.attr('id') || '(no id)');
        initEmployerField($el);
    }

    /**
     * Fetch total employer count for logging
     */
    function fetchEmployerTotal() {
        $.post(workbooks_ajax.ajax_url, {
            action: 'fetch_workbooks_employers_paged',
            nonce: workbooks_ajax.nonce,
            offset: 0,
            limit: 1,
            search: ''
        }).done(function(resp) {
            if (resp && resp.success && resp.data && typeof resp.data.total !== 'undefined') {
                totalLogged = true;
                console.log('[GF Employers] Total employers available:', resp.data.total);
            }
        }).fail(function() {
            console.warn('[GF Employers] Could not fetch employer total');
        });
    }

    /**
     * Public API for manual initialization
     */
    window.initGFEmployerField = function(selectorOrElement) {
        var $el = $(selectorOrElement);
        if ($el.length) {
            initEmployerField($el);
        }
    };

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        console.log('[GF Employers] DOM ready - checking for Gravity Form 1');
        console.log('[GF Employers] Forms found:', $('form').length);
        console.log('[GF Employers] Form IDs:', $('form').map(function() { return this.id; }).get());
        
        // Look for Gravity Form 1
        if ($('#gform_1').length || $('form[id*="gform_1"]').length) {
            console.log('[GF Employers] Gravity Form 1 detected, initializing employer field');
            setTimeout(initWithRetry, 500); // Small delay to ensure form is fully rendered
        } else {
            console.log('[GF Employers] Gravity Form 1 not found on page');
        }
    });

    /**
     * Initialize on Gravity Forms specific events if available
     */
    $(document).on('gform_post_render', function(event, form_id, current_page) {
        if (form_id == 1) {
            console.log('[GF Employers] Gravity Form 1 rendered, initializing employer field');
            setTimeout(initWithRetry, 100);
        }
    });

    /**
     * Fallback initialization for late-loading forms
     */
    $(window).on('load', function() {
        if ($('#input_1_37').length && !totalLogged) {
            console.log('[GF Employers] Window load fallback initialization');
            initWithRetry();
        }
    });

}); // Close $(document).ready function

})(jQuery);