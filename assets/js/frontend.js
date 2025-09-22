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

// Global function to toggle dropdown menus with CSS transitions
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

// Alternative direct toggle function for testing
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

// Close dropdown when clicking outside
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

// Split Button Functionality
(function () {
    function closeAllExcept(exceptMenu) {
        document.querySelectorAll('.ks-menu.ks-open').forEach(function (m) {
            if (m !== exceptMenu) {
                m.classList.remove('ks-open');
                var t = document.querySelector('[aria-controls="' + m.id + '"]');
                if (t) {
                    t.setAttribute('aria-expanded', 'false');
                    // Remove toggle-open class from buttons when closing
                    if (t.classList.contains('is-toggle')) {
                        t.classList.remove('toggle-open');
                    }
                }
            }
        });
    }

    function initializeSplitButton() {
        document.querySelectorAll('.ks-split-btn').forEach(function (container) {
            var mainBtn = container.querySelector('.ks-main-btn, .ks-main-btn-global');
            var menu = container.querySelector('.ks-menu');
            if (!menu) return;

            // Handle main button clicks (if it has is-toggle class)
            if (mainBtn && mainBtn.classList.contains('is-toggle') && !mainBtn.hasAttribute('data-initialized') && !mainBtn.hasAttribute('data-toggle-initialized')) {
                mainBtn.setAttribute('data-initialized', 'true');
                mainBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var isOpen = menu.classList.toggle('ks-open');
                    
                    // Toggle the toggle-open class on the button
                    if (isOpen) {
                        mainBtn.classList.add('toggle-open');
                    } else {
                        mainBtn.classList.remove('toggle-open');
                    }
                    
                    mainBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    closeAllExcept(isOpen ? menu : null);
                    console.log('Main button clicked, menu classes:', menu.className, 'button classes:', mainBtn.className);
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
        // Check if clicked element is a .ks-main-btn or .ks-main-btn-global
        if (e.target.classList.contains('ks-main-btn') || e.target.classList.contains('ks-main-btn-global')) {
            var button = e.target;
            var container = button.closest('.ks-split-btn');
            if (container) {
                var menu = container.querySelector('.ks-menu');
                
                // Only handle toggle functionality if button has is-toggle class AND is not handled by another script
                if (menu && button.classList.contains('is-toggle') && !button.hasAttribute('data-toggle-initialized')) {
                    console.log('Frontend.js handling button - no data-toggle-initialized found');
                    e.stopPropagation();
                    var isOpen = menu.classList.toggle('ks-open');
                    
                    // Toggle the toggle-open class on the button
                    if (isOpen) {
                        button.classList.add('toggle-open');
                    } else {
                        button.classList.remove('toggle-open');
                    }
                    
                    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    closeAllExcept(isOpen ? menu : null);
                    console.log('Global .ks-main-btn/.ks-main-btn-global.is-toggle clicked, menu classes:', menu.className, 'button classes:', button.className);
                } else if (menu && button.classList.contains('is-toggle') && button.hasAttribute('data-toggle-initialized')) {
                    console.log('Frontend.js SKIPPING button - data-toggle-initialized found:', button.getAttribute('data-toggle-initialized'));
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