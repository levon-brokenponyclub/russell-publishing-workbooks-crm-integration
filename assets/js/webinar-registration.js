// Single initialization function for toggle buttons
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

// Initialize buttons immediately when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing webinar toggle buttons immediately');
    initializeToggleButtons();
    
    // Re-initialize any buttons that might have been missed during DOMContentLoaded
    setTimeout(function() {
        initializeToggleButtons();
        console.log("Webinar toggle buttons re-initialized");
    }, 100);
});

// Add custom CSS to override any redirect behavior in login form
document.addEventListener('DOMContentLoaded', function() {
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
});

// Define openLoginModal function globally - only uses theme modal
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

    // Listen for Ninja Forms submission responses
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('nfFormSubmitResponse', function(e, response, formId) {
            console.log('Ninja Forms response received:', response, 'Form ID:', formId);
            
            // Remove loading states
            const submitButtons = themeModal.querySelectorAll('.submitting');
            submitButtons.forEach(button => button.classList.remove('submitting'));
            
            if (formId == 3 && response && response.success) {
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

// Click outside to close menus and ESC key handler
document.addEventListener('DOMContentLoaded', function() {
    // Clicking outside closes all menus
    document.addEventListener('click', function(e) {
        // Close all menus if clicking outside
        if (!e.target.closest('.ks-split-btn')) {
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
    
    // ESC key closes all menus
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
});
