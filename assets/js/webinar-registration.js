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

// Define openLoginModal function globally
function openLoginModal() {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <button class="modal-close">&times;</button>
            <div class="modal-body">
                <h2>Login</h2>
                <div id="modal-form-container"></div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    // Instead of moving the form, let's make the original form visible in the modal
    const formDiv = document.getElementById('nf-login-modal-form');
    const modalFormContainer = modal.querySelector('#modal-form-container');
    
    if (formDiv) {
        // Move the actual form div (not just innerHTML) to preserve event handlers
        formDiv.style.display = 'block';
        modalFormContainer.appendChild(formDiv);
        console.log('Original form div moved to modal with preserved handlers');
    } else {
        modalFormContainer.innerHTML = '<p>Login form could not be loaded. Please refresh the page and try again.</p>';
    }

    // Close modal functionality
    const closeButton = modal.querySelector('.modal-close');
    closeButton.addEventListener('click', function () {
        // Move the form back to its original location before closing
        if (formDiv && formDiv.parentNode === modalFormContainer) {
            document.body.appendChild(formDiv);
            formDiv.style.display = 'none';
        }
        document.body.removeChild(modal);
    });

    // Close modal when clicking outside
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            // Move the form back to its original location before closing
            if (formDiv && formDiv.parentNode === modalFormContainer) {
                document.body.appendChild(formDiv);
                formDiv.style.display = 'none';
            }
            document.body.removeChild(modal);
        }
    });

    // Listen for Ninja Forms submission responses on the original form
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('nfFormSubmitResponse', function(e, response, formId) {
            console.log('Ninja Forms response received:', response, 'Form ID:', formId);
            if (formId == 3 && response && response.success) {
                console.log('Login successful!');
                // Close modal and reload page
                if (formDiv && formDiv.parentNode === modalFormContainer) {
                    document.body.appendChild(formDiv);
                    formDiv.style.display = 'none';
                }
                document.body.removeChild(modal);
                window.location.reload();
            }
        });
    }
}

(function () {
    function closeAllExcept(exceptMenu) {
        document.querySelectorAll('.ks-menu.ks-open').forEach(function (m) {
            if (m !== exceptMenu) {
                m.classList.remove('ks-open');
                var t = document.querySelector('[aria-controls="' + m.id + '"]');
                if (t) t.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.ks-split-btn').forEach(function (container) {
            var toggle = container.querySelector('.ks-toggle-btn');
            var menu = container.querySelector('.ks-menu');
            if (!toggle || !menu) return;

            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                var isOpen = menu.classList.toggle('ks-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                closeAllExcept(isOpen ? menu : null);
            });

            // Close when menu item clicked
            menu.querySelectorAll('a').forEach(function (a) {
                a.addEventListener('click', function () {
                    menu.classList.remove('ks-open');
                    toggle.setAttribute('aria-expanded', 'false');
                });
            });

            // Keyboard: Esc to close
            container.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    menu.classList.remove('ks-open');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
        });

        // Clicking outside closes all menus
        document.addEventListener('click', function () {
            closeAllExcept(null);
        });
    });
})();
