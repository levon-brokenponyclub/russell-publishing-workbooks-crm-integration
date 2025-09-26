/**
 * DTR Frontend JavaScript
 * Handles split button functionality, login modal, and webinar form interactions
 */

// ===== USER STATUS AND INITIALIZATION =====

// Log updated JS status and user info on load
console.log('UPDATED JS: Active');
if (window.dtr_workbooks_ajax && window.dtr_workbooks_ajax.current_user_first_name) {
    console.log('First Name:', window.dtr_workbooks_ajax.current_user_first_name);
    console.log('Last Name:', window.dtr_workbooks_ajax.current_user_last_name || 'Not available');
} else {
    // Try to get user info from global WordPress variables if available
    if (typeof wp !== 'undefined' && wp.user && wp.user.data) {
        console.log('First Name:', wp.user.data.display_name || 'Not available');
        console.log('Last Name:', 'Not available from WP user data');
    } else {
        console.log('First Name: User not logged in or data not available');
        console.log('Last Name: User not logged in or data not available');
    }
}

// ===== LOGIN MODAL FUNCTIONALITY =====

/**
 * Opens the theme's login modal with enhanced functionality
 * Provides modern styling, animations, and form handling
 */
function openLoginModal() {
    // Find the existing theme modal
    const themeModal = document.getElementById('login-modal-container');
    if (!themeModal) {
        console.error('Theme login modal not found');
        return;
    }
    
    // Prevent scrolling on body
    document.body.style.overflow = 'hidden';
    
    // Add modern modal styles to the theme modal
    themeModal.classList.add('modal', 'modal-open');
    
    // Show the modal with modern styling
    themeModal.style.display = 'flex';
    
    // Trigger opening animation after showing
    requestAnimationFrame(() => {
        themeModal.classList.add('modal-open');
    });
    
    // Find the close button and add modern functionality
    const closeBtn = themeModal.querySelector('.close-btn');
    if (closeBtn) {
        // Modern close modal function
        function closeModal() {
            themeModal.classList.remove('modal-open');
            
            // Restore scrolling
            document.body.style.overflow = '';
            
            // Wait for animation to complete before hiding
            setTimeout(() => {
                themeModal.style.display = 'none';
            }, 300);
        }

        // Remove any existing event listeners and add new ones
        closeBtn.replaceWith(closeBtn.cloneNode(true));
        const newCloseBtn = themeModal.querySelector('.close-btn');
        newCloseBtn.addEventListener('click', closeModal);

        // Close modal when clicking outside (with smooth animation)
        themeModal.addEventListener('click', function (e) {
            if (e.target === themeModal) {
                closeModal();
            }
        });

        // Close modal with Escape key
        function handleKeyDown(e) {
            if (e.key === 'Escape') {
                closeModal();
                document.removeEventListener('keydown', handleKeyDown);
            }
        }
        document.addEventListener('keydown', handleKeyDown);
    }

    // Add loading state to form submission buttons
    setTimeout(() => {
        const submitButtons = themeModal.querySelectorAll('input[type="submit"], button[type="submit"], .nf-element input[type="button"]');
        submitButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (!this.classList.contains('submitting')) {
                    this.classList.add('submitting');
                    // Remove loading state after 5 seconds as fallback
                    setTimeout(() => {
                        this.classList.remove('submitting');
                    }, 5000);
                }
            });
        });
    }, 500);

    // Listen for Ninja Forms submission responses - ONLY handle Form ID 3 (login)
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('nfFormSubmitResponse', function(e, response, formId) {
            // ONLY handle login form (ID 3) - do not interfere with webinar form (ID 2)
            if (formId != 3) {
                console.log('Ninja Forms response for Form ID', formId, '- not login form, ignoring');
                return; // Let other handlers process non-login forms
            }
            
            console.log('Ninja Forms LOGIN response received:', response, 'Form ID:', formId);
            
            // Remove loading states from login modal
            const submitButtons = themeModal.querySelectorAll('.submitting');
            submitButtons.forEach(button => button.classList.remove('submitting'));
            
            if (response && response.success) {
                console.log('Login successful!');
                
                // Show success animation within the modal
                const modalContent = themeModal.querySelector('.login-modal');
                const originalContent = modalContent.innerHTML;
                
                modalContent.style.background = 'linear-gradient(135deg, #48bb78 0%, #38a169 100%)';
                modalContent.style.color = 'white';
                modalContent.style.textAlign = 'center';
                modalContent.style.padding = '40px';
                modalContent.style.borderRadius = '16px';
                modalContent.innerHTML = `
                    <div style="text-align: center;">
                        <div style="font-size: 48px; margin-bottom: 16px;">✓</div>
                        <h2 style="color: white; margin-bottom: 8px;">Success!</h2>
                        <p style="color: rgba(255,255,255,0.9); margin-bottom: 24px;">You've been logged in successfully.</p>
                        <div style="font-size: 14px; opacity: 0.8;">Redirecting...</div>
                    </div>
                `;
                
                // Close modal and reload page after showing success
                setTimeout(() => {
                    themeModal.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    setTimeout(() => {
                        themeModal.style.display = 'none';
                        // Restore original content
                        modalContent.innerHTML = originalContent;
                        modalContent.style.background = '';
                        modalContent.style.color = '';
                        modalContent.style.textAlign = '';
                        modalContent.style.padding = '';
                        modalContent.style.borderRadius = '';
                        window.location.reload();
                    }, 300);
                }, 1500);
            } else if (response && !response.success) {
                // Handle error cases
                console.log('Login failed:', response);
            }
        });
    }
}

// Make openLoginModal globally available
window.openLoginModal = openLoginModal;

// ===== SPLIT BUTTON MENU FUNCTIONALITY =====

/**
 * Global function to toggle dropdown menus with CSS transitions
 * @param {string} menuId - The ID of the menu to toggle
 */
window.toggleKsMenu = function(menuId) {
    console.log('toggleKsMenu called with:', menuId);
    const menu = document.getElementById(menuId);
    const buttons = document.querySelectorAll(`[aria-controls="${menuId}"]`);
    
    console.log('Menu element found:', menu);
    console.log('Buttons found:', buttons.length);
    
    if (menu) {
        const isOpen = menu.classList.contains('ks-open');
        console.log('Menu found, currently open:', isOpen);
        console.log('Current classes:', menu.className);
        
        if (isOpen) {
            // Close menu
            menu.classList.remove('ks-open');
            buttons.forEach(btn => {
                btn.setAttribute('aria-expanded', 'false');
                // Remove toggle-open class from buttons with is-toggle class
                if (btn.classList.contains('is-toggle')) {
                    btn.classList.remove('toggle-open');
                }
            });
            console.log('Menu closed, new classes:', menu.className);
        } else {
            // Close any other open menus first
            document.querySelectorAll('.ks-menu.ks-open').forEach(openMenu => {
                openMenu.classList.remove('ks-open');
                const openMenuButtons = document.querySelectorAll(`[aria-controls="${openMenu.id}"]`);
                openMenuButtons.forEach(btn => {
                    btn.setAttribute('aria-expanded', 'false');
                    // Remove toggle-open class from other buttons
                    if (btn.classList.contains('is-toggle')) {
                        btn.classList.remove('toggle-open');
                    }
                });
            });
            
            // Open this menu
            menu.classList.add('ks-open');
            buttons.forEach(btn => {
                btn.setAttribute('aria-expanded', 'true');
                // Add toggle-open class to buttons with is-toggle class
                if (btn.classList.contains('is-toggle')) {
                    btn.classList.add('toggle-open');
                }
            });
            console.log('Menu opened, new classes:', menu.className);
        }
    } else {
        console.error('Menu not found with ID:', menuId);
    }
};

/**
 * Alternative direct toggle function for testing purposes
 * @param {string} menuId - The ID of the menu to toggle
 */
window.directToggleMenu = function(menuId) {
    const menu = document.getElementById(menuId);
    if (menu) {
        if (menu.classList.contains('ks-open')) {
            menu.classList.remove('ks-open');
            console.log('Removed ks-open class');
        } else {
            menu.classList.add('ks-open');
            console.log('Added ks-open class');
        }
        console.log('Final classes:', menu.className);
    }
};

/**
 * Single initialization function for toggle buttons
 * Handles webinar and other toggle button functionality
 */
function initializeToggleButtons() {
    console.log('Initializing webinar toggle buttons...');
    
    // Find all toggle buttons
    document.querySelectorAll('.is-toggle').forEach(function(button) {
        // Skip if already initialized
        if (button.hasAttribute('data-toggle-initialized')) {
            console.log('Skipping already initialized webinar button:', button);
            return;
        }
        
        const menuId = button.getAttribute('aria-controls');
        const menu = document.getElementById(menuId);
        
        console.log('Found uninitialized webinar toggle button:', button, 'Menu:', menu);
        
        if (menu) {
            // Mark as initialized to prevent double initialization
            button.setAttribute('data-toggle-initialized', 'true');
            
            // IMPORTANT: Mark as initialized for frontend.js too to prevent conflicts
            button.setAttribute('data-initialized', 'true');
            
            // Remove any existing onclick to avoid conflicts
            button.onclick = null;
            
            // Add click event listener (not onclick to avoid conflicts with other code)
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Webinar toggle button clicked!', this, menu);
                
                const isCurrentlyOpen = menu.classList.contains('ks-open');
                
                // Close all other menus first
                document.querySelectorAll('.ks-menu').forEach(function(m) {
                    if (m !== menu) {
                        m.classList.remove('ks-open');
                        const btn = document.querySelector('[aria-controls="' + m.id + '"]');
                        if (btn) {
                            btn.setAttribute('aria-expanded', 'false');
                            btn.classList.remove('toggle-open');
                        }
                    }
                });
                
                // Toggle this menu
                if (!isCurrentlyOpen) {
                    menu.classList.add('ks-open');
                    this.setAttribute('aria-expanded', 'true');
                    this.classList.add('toggle-open');
                    console.log('Webinar menu opened');
                } else {
                    menu.classList.remove('ks-open');
                    this.setAttribute('aria-expanded', 'false');
                    this.classList.remove('toggle-open');
                    console.log('Webinar menu closed');
                }
            });
            
            console.log('Webinar toggle handler added to button');
        }
    });
}

// ===== EVENT HANDLERS =====

/**
 * Close dropdowns when clicking outside or pressing ESC
 */
document.addEventListener('click', function(event) {
    const splitButtons = document.querySelectorAll('.ks-split-btn');
    let clickedInsideAnyButton = false;
    
    splitButtons.forEach(splitBtn => {
        if (splitBtn.contains(event.target)) {
            clickedInsideAnyButton = true;
        }
    });
    
    if (!clickedInsideAnyButton) {
        document.querySelectorAll('.ks-menu.ks-open').forEach(menu => {
            menu.classList.remove('ks-open');
            const buttons = document.querySelectorAll(`[aria-controls="${menu.id}"]`);
            buttons.forEach(btn => {
                btn.setAttribute('aria-expanded', 'false');
                // Remove toggle-open class from buttons when closing
                if (btn.classList.contains('is-toggle')) {
                    btn.classList.remove('toggle-open');
                }
            });
        });
    }
});

/**
 * Handle keyboard interactions (ESC key)
 */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.ks-menu.ks-open').forEach(function(menu) {
            menu.classList.remove('ks-open');
            const button = document.querySelector('[aria-controls="' + menu.id + '"]');
            if (button) {
                button.setAttribute('aria-expanded', 'false');
                button.classList.remove('toggle-open');
            }
        });
    }
});

// Split Button Functionality
(function () {
    function closeAllExcept(exceptMenu) {
        document.querySelectorAll('.ks-menu.ks-open').forEach(function (m) {
            if (m !== exceptMenu) {
                m.classList.remove('ks-open');
                var t = document.querySelector('[aria-controls="' + m.id + '"]');
                if (t) {
                    t.setAttribute('aria-expanded', 'false');
                    if (t.classList.contains('is-toggle')) {
                        t.classList.remove('toggle-open');
                    }
                }
            }
        });
    }

    function handleButtonClick(button, menu, isFromInitialize = false) {
        if (!button || !menu) return;

        // If this is from initialize and the button is already handled, skip
        if (isFromInitialize && button.hasAttribute('data-toggle-initialized')) {
            console.log('Button already initialized:', button);
            return;
        }

        // Toggle menu state
        const isOpen = menu.classList.contains('ks-open');
        if (isOpen) {
            menu.classList.remove('ks-open');
            button.classList.remove('toggle-open');
            button.setAttribute('aria-expanded', 'false');
        } else {
            closeAllExcept(menu);
            menu.classList.add('ks-open');
            button.classList.add('toggle-open');
            button.setAttribute('aria-expanded', 'true');
        }

        // Mark as initialized
        button.setAttribute('data-toggle-initialized', 'true');
        console.log('Button clicked, menu state:', !isOpen, 'Button:', button, 'Menu:', menu);
    }

    function initializeSplitButton() {
        document.querySelectorAll('.ks-split-btn').forEach(function (container) {
            var mainBtn = container.querySelector('.ks-main-btn, .ks-main-btn-global');
            var menu = container.querySelector('.ks-menu');
            if (!menu || !mainBtn) return;

            // Ensure menu has an ID
            if (!menu.id) {
                menu.id = 'ks-menu-' + Math.random().toString(36).substr(2, 9);
            }
            
            // Update button attributes
            mainBtn.setAttribute('aria-controls', menu.id);
            mainBtn.setAttribute('aria-haspopup', 'true');
            mainBtn.setAttribute('aria-expanded', menu.classList.contains('ks-open') ? 'true' : 'false');

            // Handle main button clicks
            if (mainBtn.classList.contains('is-toggle')) {
                mainBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    handleButtonClick(mainBtn, menu, true);
                });
            }

            // Close when menu item clicked
            menu.querySelectorAll('a').forEach(function (a) {
                a.addEventListener('click', function () {
                    menu.classList.remove('ks-open');
                    if (mainBtn) {
                        mainBtn.setAttribute('aria-expanded', 'false');
                        mainBtn.classList.remove('toggle-open');
                    }
                });
            });

            // Keyboard: Esc to close
            container.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    menu.classList.remove('ks-open');
                    if (mainBtn) {
                        mainBtn.setAttribute('aria-expanded', 'false');
                        mainBtn.classList.remove('toggle-open');
                    }
                }
            });
        });
    }

    // Global click handler for any .ks-main-btn or .ks-main-btn-global
    document.addEventListener('click', function(e) {
        const button = e.target.closest('.ks-main-btn, .ks-main-btn-global');
        if (button && button.classList.contains('is-toggle')) {
            e.preventDefault();
            e.stopPropagation();
            const container = button.closest('.ks-split-btn');
            if (container) {
                const menu = container.querySelector('.ks-menu');
                if (menu) {
                    handleButtonClick(button, menu);
                }
            }
        }
        // Close all menus when clicking outside
        else if (!e.target.closest('.ks-split-btn')) {
            // Remove toggle-open class from all buttons when closing menus
            document.querySelectorAll('.ks-main-btn.toggle-open, .ks-main-btn-global.toggle-open').forEach(function(btn) {
                btn.classList.remove('toggle-open');
            });
            closeAllExcept(null);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        initializeSplitButton();
    });

    // Make initializeSplitButton globally available for dynamic content
    window.initializeSplitButton = initializeSplitButton;
})();

// ===== DOM INITIALIZATION =====

/**
 * Initialize all functionality when DOM is ready
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing all functionality');
    
    // Initialize toggle buttons immediately
    initializeToggleButtons();
    
    // Re-initialize any buttons that might have been missed during DOMContentLoaded
    setTimeout(function() {
        initializeToggleButtons();
        console.log("Toggle buttons re-initialized");
    }, 100);
    
    // Setup login form redirect handling
    setupLoginFormRedirect();
    
    // Setup webinar form submission
    setupWebinarFormSubmission();
});

/**
 * Setup login form to prevent unwanted redirects
 */
function setupLoginFormRedirect() {
    // Check for any login forms and add current page URL as redirect
    const hiddenForm = document.querySelector('#nf-login-modal-form form');
    if (hiddenForm) {
        // Add current URL as hidden field to prevent redirection
        let redirectField = hiddenForm.querySelector('input[name="redirect_to"]');
        if (!redirectField) {
            redirectField = document.createElement('input');
            redirectField.type = 'hidden';
            redirectField.name = 'redirect_to';
            hiddenForm.appendChild(redirectField);
        }
        redirectField.value = window.location.href;
        console.log('Added redirect field to login form:', redirectField.value);
    }
}

/**
 * Setup webinar form submission handling
 */
function setupWebinarFormSubmission() {
    const submitButton = document.getElementById('submitWebinarBtn');
    const webinarForm = document.getElementById('webinarForm');
    
    if (!submitButton || !webinarForm) {
        console.log('Webinar form elements not found on this page');
        return;
    }
    
    console.log('Setting up webinar form submission handler');
    
    submitButton.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Webinar form submit button clicked');
        
        // Validate required fields
        const requiredFields = webinarForm.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#dc3545';
                field.focus();
            } else {
                field.style.borderColor = '';
            }
        });
        
        if (!isValid) {
            console.log('Form validation failed');
            return;
        }
        
        // Show loading overlay
        const loadingOverlay = document.getElementById('formLoaderOverlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'flex';
            
            // Update loading text and progress
            const statusText = document.getElementById('loaderStatusText');
            const progressFill = document.getElementById('progressCircleFill');
            
            if (statusText) statusText.textContent = 'Submitting your registration...';
            if (progressFill) progressFill.className = 'progress-circle-fill progress-25';
        }
        
        // Disable submit button and show loading
        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';
        submitButton.classList.add('submitting');
        
        // Collect form data
        const formData = new FormData();
        
        // Add WordPress security nonce
        if (typeof dtrWebinarAjax !== 'undefined' && dtrWebinarAjax.nonce) {
            formData.append('_wpnonce', dtrWebinarAjax.nonce);
        }
        
        // Add action
        formData.append('action', 'dtr_submit_webinar_shortcode');
        
        // Collect form fields (user data will be fetched server-side from logged-in user)
        const formFields = webinarForm.querySelectorAll('input, textarea, select');
        formFields.forEach(field => {
            if (field.type === 'checkbox') {
                if (field.checked) {
                    formData.append(field.name, field.value);
                }
            } else if (field.type === 'radio') {
                if (field.checked) {
                    formData.append(field.name, field.value);
                }
            } else if (field.type !== 'hidden' || field.name === 'post_id' || field.name === 'workbooks_reference') {
                // Only include non-hidden fields, except for essential hidden fields
                formData.append(field.name, field.value);
            }
        });
        
        console.log('Submitting webinar form data to:', dtrWebinarAjax.ajaxurl);
        
        // Submit form via AJAX
        fetch(dtrWebinarAjax.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Webinar form response:', data);
            const loadingOverlay = document.getElementById('formLoaderOverlay');
            const statusText = document.getElementById('loaderStatusText');
            const progressFill = document.getElementById('progressCircleFill');
            
            if (data.success) {
                console.log('Registration successful, redirecting to:', data.data.redirect_url);
                
                // Update loading overlay to show success
                if (statusText) statusText.textContent = 'Registration successful! Redirecting...';
                if (progressFill) progressFill.className = 'progress-circle-fill progress-100';
                
                // Show brief success message before redirect
                submitButton.textContent = 'Registration Successful!';
                submitButton.style.backgroundColor = '#28a745';
                
                // Redirect after short delay
                setTimeout(() => {
                    window.location.href = data.data.redirect_url;
                }, 2000);
                
            } else {
                console.error('Registration failed:', data.data);
                
                // Hide loading overlay immediately for errors
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'none';
                }
                
                // Show error message
                submitButton.textContent = 'Registration Failed - Try Again';
                submitButton.style.backgroundColor = '#dc3545';
                submitButton.disabled = false;
                submitButton.classList.remove('submitting');
                
                // Reset button after 3 seconds
                setTimeout(() => {
                    submitButton.textContent = 'Register';
                    submitButton.style.backgroundColor = '';
                }, 3000);
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            
            // Hide loading overlay immediately for errors
            const loadingOverlay = document.getElementById('formLoaderOverlay');
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
            }
            
            // Show error message
            submitButton.textContent = 'Network Error - Try Again';
            submitButton.style.backgroundColor = '#dc3545';
            submitButton.disabled = false;
            submitButton.classList.remove('submitting');
            
            // Reset button after 3 seconds
            setTimeout(() => {
                submitButton.textContent = 'Register';
                submitButton.style.backgroundColor = '';
            }, 3000);
        });
    });
}

