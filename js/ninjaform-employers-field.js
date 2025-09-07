/**
 * Enhanced employer Select2 field logic with server-side paginated search
 * Ported from release plugin: uses fetch_workbooks_employers_select2 endpoint.
 */
(function($){
    if(typeof $==='undefined'||typeof $.fn.select2==='undefined'||typeof workbooks_ajax==='undefined'){return;}

    var CACHE = {}; // key: term|page -> results
    var PENDING = {};
    var initAttempts = 0;
    var MAX_INIT_ATTEMPTS = 15; // ~9s
    var totalLogged = false;

    function cacheKey(term,page){return (term||'')+'|'+page;}

    function locateEmployerField(){
        // Primary known field ID
        var $el = $('#nf-field-218');
        if ($el.length) return $el;
        // Fallback: look for a select with placeholder containing 'Employer' or label text
        var candidates = $('select').filter(function(){
            var ph = $(this).attr('placeholder')||'';
            return /employer/i.test(ph) || /employer/i.test($(this).attr('aria-label')||'');
        });
        if (candidates.length === 1) return candidates.first();
        // Try by label association
        $('label').each(function(){
            var txt = $(this).text();
            if(/employer/i.test(txt)){
                var forId = $(this).attr('for');
                if(forId){
                    var $f = $('#'+forId);
                    if($f.is('select')) { $el = $f; return false; }
                }
            }
        });
        return $el;
    }

    function initSelect(){
        var $el = locateEmployerField();
        if(!$el.length){
            initAttempts++;
            if(initAttempts === 1) {
                console.warn('[DTR Employers] Employer field not found yet (looking for #nf-field-218 or labelled select), waiting...');
            } else if (initAttempts % 5 === 0) {
                console.warn('[DTR Employers] Still waiting (#'+initAttempts+' attempts). Candidate select IDs:', $('select').map(function(){return this.id || '(no id)';}).get());
            }
            if(initAttempts < MAX_INIT_ATTEMPTS){
                setTimeout(initSelect,600);
            } else {
                console.error('[DTR Employers] Gave up initializing employer Select2 after '+MAX_INIT_ATTEMPTS+' attempts.');
            }
            return; 
        }
        console.log('[DTR Employers] Initializing employer Select2 on', $el.attr('id')||'(no id)');
        $el.empty();
        $el.select2({
            placeholder:'Select an employer or type to add new',
            allowClear:true,
            width:'100%',
            tags:true,
            minimumInputLength:0,
            ajax:{
                transport:function(params,success,failure){
                    var term=params.data.term||''; var page=params.data.page||1; var key=cacheKey(term,page);
                    if(CACHE[key]){ success(CACHE[key]); return; }
                    if(PENDING[key]){ PENDING[key].push({success:success,failure:failure}); return; }
                    PENDING[key]=[{success:success,failure:failure}];
                    $.ajax({
                        url: workbooks_ajax.ajax_url,
                        type:'GET',
                        data:{ action:'fetch_workbooks_employers_select2', nonce:workbooks_ajax.nonce, term:term, page:page },
                        dataType:'json'
                    }).done(function(data){
                        CACHE[key]=data;
                        (PENDING[key]||[]).forEach(function(cb){ cb.success(data); });
                        delete PENDING[key];
                    }).fail(function(jqXHR,textStatus,error){
                        var status = jqXHR.status;
                        var raw = jqXHR.responseText;
                        console.error('[DTR Employers] AJAX failure status='+status+' term="'+term+'" page='+page, raw);
                        // Provide empty result structure to Select2 to avoid JS exception
                        var empty = { results: [], pagination: { more:false } };
                        (PENDING[key]||[]).forEach(function(cb){ try{ cb.success(empty); }catch(e){} });
                        delete PENDING[key];
                    });
                },
                delay:250,
                data:function(params){ return { term:params.term||'', page:params.page||1 }; },
                processResults:function(data,params){ params.page=params.page||1; return data; },
                cache:true
            },
            createTag:function(params){ var t=$.trim(params.term); if(!t) return null; return { id:t, text:t+' (New)', newTag:true }; }
        });

        // Prefetch first page for snappier UX
        $.get(workbooks_ajax.ajax_url,{action:'fetch_workbooks_employers_select2',nonce:workbooks_ajax.nonce,term:'',page:1});

            // Optional debug: log dataset stats if debug query flag present
            if (window.location.search.indexOf('dtr_employers_debug=1') !== -1) {
                logEmployerDatasetDebug();
            }

        if(!totalLogged){
            fetchEmployerTotal();
        }

    // Signal that the dynamic employer field is ready and user can proceed with submission
    console.log('%cREADY TO SUBMIT - GO FOR IT...','color:#2e7d32;font-weight:bold');
    }

    $(document).ready(function(){ 
        if($('#nf-form-15-cont').length){ 
            console.log('Form ID 15 - Loaded');
            initSelect(); 
        } 
    });
    $(document).on('ninjaFormsLoaded', function(){ 
        if($('#nf-form-15-cont').length){ 
            console.log('Form ID 15 - Loaded (ninjaFormsLoaded event)');
            initSelect(); 
        } 
    });

    function fetchEmployerTotal(){
        // Uses paged endpoint (now public with nonce) to fetch just the count
        $.post(workbooks_ajax.ajax_url, {
            action: 'fetch_workbooks_employers_paged',
            nonce: workbooks_ajax.nonce,
            offset: 0,
            limit: 1,
            search: ''
        }).done(function(resp){
            if(resp && resp.success && resp.data && typeof resp.data.total !== 'undefined'){
                totalLogged = true;
                console.log('Employers - Total '+ resp.data.total +' Employers');
            } else {
                console.warn('[DTR Employers] Could not determine total employers from response', resp);
            }
        }).fail(function(jq,x,e){
            console.error('[DTR Employers] Failed to fetch employer total', x||e);
        });
    }

    function logEmployerDatasetDebug(){
            // Fetch a paged request to get total count
            $.post(workbooks_ajax.ajax_url, {
                action: 'fetch_workbooks_employers_paged',
                nonce: workbooks_ajax.nonce,
                offset: 0,
                limit: 25,
                search: ''
            }).done(function(resp){
                if(resp && resp.success){
                    var employers = resp.data && resp.data.employers ? resp.data.employers : resp.employers || [];
                    var total = (resp.data && resp.data.total) ? resp.data.total : resp.total;
                    console.log('%c[DTR Employers Debug] Total employers (approx): '+ total, 'color:#4caf50;font-weight:bold');
                    console.log('%c[DTR Employers Debug] First '+ employers.length +' sample rows:', 'color:#2196f3');
                    console.table(employers);
                } else {
                    console.warn('[DTR Employers Debug] Failed to retrieve paged employers response', resp);
                }
            }).fail(function(jq,x,e){
                console.error('[DTR Employers Debug] AJAX error fetching employers paged:', x, e);
            });

            // Attempt JSON file (legacy) if present for comparison
            if (workbooks_ajax.plugin_url) {
                var newPath = workbooks_ajax.plugin_url + 'assets/json/employers.json';
                var legacyPath = workbooks_ajax.plugin_url + 'employers.json';
                fetch(newPath, { cache:'no-cache' })
                    .then(function(r){ return r.ok ? r.json() : Promise.reject(r.status); })
                    .then(function(json){
                        if(Array.isArray(json)){
                            console.log('%c[DTR Employers Debug] employers.json found at assets/json ('+json.length+' entries).','color:#9c27b0');
                        }
                    })
                    .catch(function(err){
                        // Fallback to legacy root location quietly
                        if(err === 404){
                            fetch(legacyPath, { cache:'no-cache' })
                                .then(function(r){ return r.ok ? r.json() : Promise.reject(r.status); })
                                .then(function(json){
                                    if(Array.isArray(json)){
                                        console.log('%c[DTR Employers Debug] Legacy employers.json (root) present with '+json.length+' entries (may be stale).','color:#9c27b0');
                                    }
                                })
                                .catch(function(){ /* silent */ });
                        }
                    });
            }
        }
})(jQuery);