<?php
/**
 * Shortcode: [workbooks_employer_select]
 * Outputs a dynamic employer autocomplete field with AJAX search.
 * Based on CodyHouse Select Autocomplete component.
 */
function workbooks_employer_select_shortcode($atts = array(), $content = null) {
    $atts = shortcode_atts([
        'id' => 'employer',
        'name' => 'employer',
        'class' => '',
        'placeholder' => 'Search for your employer...',
        'value' => '',
    ], $atts);
    
    $id = esc_attr($atts['id']);
    $name = esc_attr($atts['name']);
    $class = esc_attr(trim('autocomplete js-autocomplete ' . $atts['class']));
    $placeholder = esc_attr($atts['placeholder']);
    $selected_value = esc_attr($atts['value']);
    
    // Generate unique UUID for the input field
    $uuid = wp_generate_uuid4();

    // Enqueue the autocomplete styles and scripts
    wp_enqueue_style('workbooks-autocomplete-css');
    wp_enqueue_script('workbooks-autocomplete-js');

    ob_start();
    ?>
    <div class="autocomplete js-autocomplete" data-autocomplete-dropdown-visible-class="autocomplete--dropdown-visible">
        <div class="autocomplete__wrapper">
            <input 
                id="<?php echo $id; ?>" 
                name="<?php echo $name; ?>" 
                class="autocomplete__input js-autocomplete__input" 
                type="text" 
                placeholder="<?php echo $placeholder; ?>" 
                value="<?php echo $selected_value; ?>"
                autocomplete="off"
                data-search-url="<?php echo admin_url('admin-ajax.php'); ?>"
                data-action="search_workbooks_employers"
                data-nonce="<?php echo wp_create_nonce('workbooks_employer_search'); ?>"
                data-uuid="<?php echo $uuid; ?>"
                uuid="<?php echo $uuid; ?>"
                required
            >
            
            <button type="button" class="autocomplete__btn js-autocomplete__btn" aria-label="Toggle autocomplete dropdown">
                <svg class="icon autocomplete__icon-arrow" viewBox="0 0 12 12" aria-hidden="true">
                    <polyline fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" points="0.5,3.5 6,9.5 11.5,3.5 "></polyline>
                </svg>
            </button>
        </div>

        <ul class="autocomplete__list js-autocomplete__list" role="listbox">
            <!-- Results will be populated via AJAX -->
        </ul>
    </div>

    <style>
        /* CodyHouse Autocomplete Styles */
        .autocomplete {
            position: relative;
        }

        .autocomplete__wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .autocomplete__input {
            width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 0.75rem;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 1rem;
            background: white;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .autocomplete__input:focus {
            outline: none;
            border-color: #3182ce;
            box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
        }

        .autocomplete__btn {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.25rem;
            color: #4a5568;
            transition: color 0.2s ease;
        }

        .autocomplete__btn:hover {
            color: #2d3748;
        }

        .autocomplete__icon-arrow {
            width: 16px;
            height: 16px;
            transition: transform 0.2s ease;
        }

        .autocomplete--dropdown-visible .autocomplete__icon-arrow {
            transform: rotate(180deg);
        }

        .autocomplete__list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            margin: 0;
            padding: 0;
            list-style: none;
            display: none;
        }

        .autocomplete--dropdown-visible .autocomplete__list {
            display: block;
        }

        .autocomplete__item {
            padding: 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid #f7fafc;
            transition: background-color 0.15s ease;
        }

        .autocomplete__item:last-child {
            border-bottom: none;
        }

        .autocomplete__item:hover,
        .autocomplete__item[aria-selected="true"] {
            background-color: #ebf8ff;
            color: #2c5282;
        }

        .autocomplete__item--no-results {
            color: #718096;
            font-style: italic;
            cursor: default;
        }

        .autocomplete__item--no-results:hover {
            background-color: transparent;
            color: #718096;
        }

        .autocomplete__item--loading {
            color: #718096;
            cursor: default;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .autocomplete__item--loading:hover {
            background-color: transparent;
            color: #718096;
        }

        .loading-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #e2e8f0;
            border-top: 2px solid #3182ce;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const autocomplete = document.querySelector('.js-autocomplete');
            if (!autocomplete) return;

            const input = autocomplete.querySelector('.js-autocomplete__input');
            const btn = autocomplete.querySelector('.js-autocomplete__btn');
            const list = autocomplete.querySelector('.js-autocomplete__list');
            
            let searchTimeout;
            let currentFocus = -1;

            // Initialize
            input.addEventListener('input', handleInput);
            input.addEventListener('keydown', handleKeydown);
            input.addEventListener('focus', handleFocus);
            btn.addEventListener('click', toggleDropdown);
            
            // Click outside to close
            document.addEventListener('click', function(e) {
                if (!autocomplete.contains(e.target)) {
                    hideDropdown();
                }
            });

            function handleInput() {
                const query = input.value.trim();
                currentFocus = -1;
                
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (query.length >= 2) {
                        searchEmployers(query);
                    } else if (query.length === 0) {
                        showTopEmployers();
                    } else {
                        hideDropdown();
                    }
                }, 300);
            }

            function handleFocus() {
                const query = input.value.trim();
                if (query.length >= 2) {
                    searchEmployers(query);
                } else {
                    showTopEmployers();
                }
            }

            function handleKeydown(e) {
                const items = list.querySelectorAll('.autocomplete__item:not(.autocomplete__item--no-results):not(.autocomplete__item--loading)');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    currentFocus++;
                    if (currentFocus >= items.length) currentFocus = 0;
                    setActiveFocus(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    currentFocus--;
                    if (currentFocus < 0) currentFocus = items.length - 1;
                    setActiveFocus(items);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (currentFocus >= 0 && items[currentFocus]) {
                        selectItem(items[currentFocus].textContent);
                    }
                } else if (e.key === 'Escape') {
                    hideDropdown();
                    input.blur();
                }
            }

            function setActiveFocus(items) {
                items.forEach((item, index) => {
                    item.setAttribute('aria-selected', index === currentFocus ? 'true' : 'false');
                });
                
                if (items[currentFocus]) {
                    items[currentFocus].scrollIntoView({ block: 'nearest' });
                }
            }

            function toggleDropdown() {
                if (autocomplete.classList.contains('autocomplete--dropdown-visible')) {
                    hideDropdown();
                } else {
                    handleFocus();
                }
            }

            function showDropdown() {
                autocomplete.classList.add('autocomplete--dropdown-visible');
            }

            function hideDropdown() {
                autocomplete.classList.remove('autocomplete--dropdown-visible');
                currentFocus = -1;
            }

            function searchEmployers(query) {
                console.log('DTR Debug: Searching for:', query);
                console.log('DTR Debug: Search URL:', input.dataset.searchUrl);
                console.log('DTR Debug: Nonce:', input.dataset.nonce);
                
                showLoading();
                
                const requestData = {
                    action: input.dataset.action,
                    query: query,
                    nonce: input.dataset.nonce
                };
                
                console.log('DTR Debug: Request data:', requestData);
                
                fetch(input.dataset.searchUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(requestData)
                })
                .then(response => {
                    console.log('DTR Debug: Response status:', response.status);
                    console.log('DTR Debug: Response headers:', response.headers);
                    return response.text(); // Get raw text first
                })
                .then(text => {
                    console.log('DTR Debug: Raw response:', text);
                    try {
                        const data = JSON.parse(text);
                        console.log('DTR Debug: Parsed data:', data);
                        
                        if (data.success && data.data && data.data.employers) {
                            displayResults(data.data.employers);
                        } else if (data.success && Array.isArray(data.data)) {
                            // Fallback for simple array response
                            displayResults(data.data);
                        } else {
                            console.error('DTR Debug: Unexpected data format:', data);
                            displayError();
                        }
                    } catch (e) {
                        console.error('DTR Debug: JSON parse error:', e);
                        console.error('DTR Debug: Response was:', text);
                        displayError();
                    }
                })
                .catch(error => {
                    console.error('Employer search error:', error);
                    displayError();
                });
            }

            function showTopEmployers() {
                console.log('DTR Debug: Getting top employers');
                console.log('DTR Debug: URL:', input.dataset.searchUrl);
                console.log('DTR Debug: Nonce:', input.dataset.nonce);
                
                showLoading();
                
                const requestData = {
                    action: 'get_top_employers',
                    nonce: input.dataset.nonce
                };
                
                console.log('DTR Debug: Top employers request data:', requestData);
                
                fetch(input.dataset.searchUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(requestData)
                })
                .then(response => {
                    console.log('DTR Debug: Top employers response status:', response.status);
                    return response.text();
                })
                .then(text => {
                    console.log('DTR Debug: Top employers raw response:', text);
                    try {
                        const data = JSON.parse(text);
                        console.log('DTR Debug: Top employers parsed data:', data);
                        
                        if (data.success && data.data && data.data.employers) {
                            displayResults(data.data.employers);
                        } else if (data.success && Array.isArray(data.data)) {
                            // Fallback for simple array response
                            displayResults(data.data);
                        } else {
                            console.error('DTR Debug: Unexpected top employers data:', data);
                            displayError();
                        }
                    } catch (e) {
                        console.error('DTR Debug: Top employers JSON parse error:', e);
                        console.error('DTR Debug: Response was:', text);
                        displayError();
                    }
                })
                .catch(error => {
                    console.error('Top employers error:', error);
                    displayError();
                });
            }

            function showLoading() {
                list.innerHTML = '<li class="autocomplete__item autocomplete__item--loading"><div class="loading-spinner"></div> Searching employers...</li>';
                showDropdown();
            }

            function displayResults(employers) {
                if (employers.length === 0) {
                    displayNoResults();
                    return;
                }

                list.innerHTML = employers.map(employer => 
                    `<li class="autocomplete__item" data-value="${escapeHtml(employer)}">${escapeHtml(employer)}</li>`
                ).join('');

                // Add click handlers to items
                list.querySelectorAll('.autocomplete__item').forEach(item => {
                    if (!item.classList.contains('autocomplete__item--no-results') && 
                        !item.classList.contains('autocomplete__item--loading')) {
                        item.addEventListener('click', () => selectItem(item.dataset.value));
                    }
                });

                showDropdown();
            }

            function displayNoResults() {
                list.innerHTML = '<li class="autocomplete__item autocomplete__item--no-results">No employers found</li>';
                showDropdown();
            }

            function displayError() {
                list.innerHTML = '<li class="autocomplete__item autocomplete__item--no-results">Error loading employers</li>';
                showDropdown();
            }

            function selectItem(value) {
                input.value = value;
                hideDropdown();
                input.focus();
                
                // Trigger change event for form handling
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('workbooks_employer_select', 'workbooks_employer_select_shortcode');
