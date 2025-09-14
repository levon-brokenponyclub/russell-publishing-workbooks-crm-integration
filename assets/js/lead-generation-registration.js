// Lead Generation Registration JavaScript
// Handles form completion detection, ACF integration, and UI interactions

jQuery(document).ready(function($) {
    
    // Check for content unlocked status and show toast
    function checkForContentUnlockedToast() {
        // Check if URL has the fire-toast-message parameter for testing
        const urlParams = new URLSearchParams(window.location.search);
        const fireToast = urlParams.get('fire-toast-message');
        
        // Check if we're on a page with completed form (has the form-completion-notice in PHP or the testing parameter)
        const hasFormCompletion = document.querySelector('.form-completion-notice') || fireToast === '1';
        
        if (hasFormCompletion) {
            // Show the toast notification instead of the static message
            setTimeout(function() {
                showToastNotification(
                    'Content Unlocked!',
                    'You have completed the required form and can now access this content.',
                    'success',
                    7000 // Show for 7 seconds
                );
                
                // Hide the static message if it exists
                const staticNotice = document.querySelector('.form-completion-notice');
                if (staticNotice) {
                    staticNotice.style.display = 'none';
                }
            }, 500); // Small delay to ensure page is fully loaded
        }
    }
    
    // Run the content unlocked check
    checkForContentUnlockedToast();
    
    // Check for Ninja Forms success message on page load
    function checkForSuccessMessage() {
        var successMessage = $(".nf-response-msg:visible, .ninja-forms-success-msg:visible, .nf-success:visible").first();
        if (successMessage.length > 0 && successMessage.text().trim().length > 0) {
            console.log("[Form Detection] Success message found, marking form as completed");
            
            // Mark form as completed via AJAX
            $.ajax({
                url: ajax_object.ajax_url,
                type: "POST",
                data: {
                    action: "mark_ninja_form_completed",
                    user_id: ajax_object.user_id,
                    form_id: ajax_object.form_id,
                    post_id: ajax_object.post_id,
                    nonce: ajax_object.nonce
                },
                success: function(response) {
                    console.log("[Form Detection] Form marked as completed, reloading...");
                    
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                },
                error: function(xhr, status, error) {
                    console.error("[Form Detection] AJAX Error:", error);
                    
                    setTimeout(function() {
                        window.location.reload();
                    }, 3000);
                }
            });
        }
    }
    
    // Run success message check
    checkForSuccessMessage();
    
    // Save to Collection functionality
    $(".save-to-collection").on("click", function(e) {
        e.preventDefault();
        var button = $(this);
        var postId = button.data("post-id");

        button.prop("disabled", true).text("Saving...");

        $.ajax({
            url: ajax_object.ajax_url,
            type: "POST",
            data: {
                action: "save_to_collection",
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    // Generate unique ID for split button menu
                    var uid = 'ks' + Math.random().toString(36).substr(2, 9);
                    
                    // Create split button HTML
                    var splitButtonHtml = '<div class="ks-split-btn">' +
                        '<a href="/my-account/?page-view=my-collection" class="ks-main-btn" role="button">Saved to collection</a>' +
                        '<button type="button" class="ks-toggle-btn" aria-haspopup="true" aria-expanded="false" aria-controls="' + uid + '-menu" title="Open menu">' +
                            '<i class="fa-solid fa-chevron-down"></i>' +
                        '</button>' +
                        '<ul id="' + uid + '-menu" class="ks-menu" role="menu">' +
                            '<li role="none"><a role="menuitem" href="#" class="no-decoration remove-from-collection-btn">Remove</a></li>' +
                            '<li role="none"><a role="menuitem" href="/my-account/?page-view=my-collection">View My Collection</a></li>' +
                        '</ul>' +
                    '</div>';
                    
                    // Replace button with split button and update reveal text
                    button.replaceWith(splitButtonHtml);
                    var nextRevealText = $(".reveal-text").last();
                    if (nextRevealText.length > 0) {
                        nextRevealText.text("Event has been saved to collection");
                    } else {
                        $(".event-registration").append('<div class="reveal-text">Event has been saved to collection</div>');
                    }
                    
                    // Initialize split button functionality for the new element
                    initializeSplitButton();
                } else {
                    button.prop("disabled", false).text("Save to Collection");
                    alert("Error saving to collection: " + (response.data.message || "Unknown error"));
                }
            },
            error: function() {
                button.prop("disabled", false).text("Save to Collection");
                alert("Error saving to collection. Please try again.");
            }
        });
    });
    
    // Remove from Collection functionality
    $(document).on("click", ".remove-from-collection-btn", function(e) {
        e.preventDefault();
        var button = $(this);
        var postId = ajax_object.post_id;
        
        // Create and show custom confirmation dialog
        showRemoveConfirmationDialog(function(confirmed) {
            if (!confirmed) {
                return;
            }

            // Update both the remove button and main split button text
            button.text("Removing...");
            var mainButton = button.closest('.ks-split-btn').find('.ks-main-btn');
            if (mainButton.length > 0) {
                mainButton.text("Removing...");
            }

            $.ajax({
                url: ajax_object.ajax_url,
                type: "POST",
                data: {
                    action: "remove_from_collection",
                    post_id: postId,
                    nonce: ajax_object.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to update the UI
                        window.location.reload();
                    } else {
                        button.text("Remove");
                        if (mainButton.length > 0) {
                            mainButton.text("Saved to collection");
                        }
                        alert("Error removing from collection: " + (response.data.message || "Unknown error"));
                    }
                },
                error: function() {
                    button.text("Remove");
                    if (mainButton.length > 0) {
                        mainButton.text("Saved to collection");
                    }
                    alert("Error removing from collection. Please try again.");
                }
            });
        });
    });
    
    // ACF Questions Integration
    if (typeof ajax_object !== 'undefined' && ajax_object.has_acf_questions === '1') {
        
        // Debug console logs for form state
        console.log("User: Logged In");
        console.log("Event Status: Not Registered");
        console.log("Form ID: 31 - Lead Generation");
        
        // Function to get ACF answers
        function getAcfAnswers() {
            var answers = {};
            $("[id^='acf_question_']").each(function() {
                var fieldId = $(this).attr("id");
                var fieldName = $(this).attr("name");
                var value = "";

                if ($(this).is(":checkbox")) {
                    var checkedValues = [];
                    $("[name='" + fieldName + "']:checked").each(function() {
                        checkedValues.push($(this).val());
                    });
                    value = checkedValues.join(", ");
                } else if ($(this).is(":radio")) {
                    value = $("[name='" + fieldName + "']:checked").val() || "";
                } else {
                    value = $(this).val() || "";
                }

                if (value) {
                    answers[fieldName] = value;
                }
            });
            return answers;
        }

        // Function to inject ACF answers into Ninja Form
        function injectAcfAnswersToNinjaForm(ninjaForm) {
            var acfAnswers = getAcfAnswers();
            console.log("ACF Questions Debug:", acfAnswers);

            // Create hidden fields for each ACF answer
            Object.keys(acfAnswers).forEach(function(fieldName) {
                var existingHidden = ninjaForm.querySelector("[name='" + fieldName + "']");
                if (existingHidden) {
                    existingHidden.value = acfAnswers[fieldName];
                } else {
                    var hiddenField = document.createElement("input");
                    hiddenField.type = "hidden";
                    hiddenField.name = fieldName;
                    hiddenField.value = acfAnswers[fieldName];
                    ninjaForm.appendChild(hiddenField);
                }
            });
        }

        // Wait for Ninja Form to be ready
        function setupNinjaFormIntegration() {
            var ninjaForm = document.querySelector(".ninja-forms-form");
            if (!ninjaForm) {
                setTimeout(setupNinjaFormIntegration, 300);
                return;
            }

            // Avoid double-binding
            if (ninjaForm.getAttribute("data-acf-inject-listener")) return;
            ninjaForm.setAttribute("data-acf-inject-listener", "1");

            // Listen for form submission
            ninjaForm.addEventListener("submit", function(e) {
                console.log("[Form ID 31] Form submission detected!");
                
                injectAcfAnswersToNinjaForm(ninjaForm);
                
                // Set up success detection after form submission
                setTimeout(function() {
                    detectFormSuccess();
                }, 2000);
            });
        }

        // Initialize when page loads
        setupNinjaFormIntegration();
        
        // Form success detection function
        function detectFormSuccess() {
            console.log("[Form ID 31] Checking for success message...");
            
            var successElements = document.querySelectorAll(".nf-response-msg, .ninja-forms-success-msg, .nf-success");
            var hasSuccess = false;
            
            successElements.forEach(function(element) {
                // Check if element is visible (offsetParent !== null means it's visible)
                if (element.offsetParent !== null && element.textContent.trim() !== "") {
                    console.log("[Form ID 31] Success element found:", element.textContent.trim());
                    hasSuccess = true;
                }
            });
            
            if (hasSuccess) {
                console.log("[Form ID 31] Form submission successful!");
                
                // Mark form as completed via AJAX before reloading
                $.ajax({
                    url: ajax_object.ajax_url,
                    type: "POST",
                    data: {
                        action: "mark_ninja_form_completed",
                        user_id: ajax_object.user_id,
                        form_id: ajax_object.form_id,
                        post_id: ajax_object.post_id,
                        nonce: ajax_object.nonce
                    },
                    success: function(response) {
                        console.log("[Form ID 31] Form marked as completed successfully");
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    },
                    error: function(xhr, status, error) {
                        console.error("[Form ID 31] Error marking form as completed:", error);
                        // Still reload even if AJAX fails
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    }
                });
                
                return true;
            }
            
            return false;
        }
        
        // Ninja Forms event listeners for Form ID 31
        jQuery(document).on("nfFormSubmitResponse", function(e, response) {
            console.log("[Form ID 31] Ninja Forms response received:", response);
            
            // Check if this is form ID 31
            var isForm31 = false;
            if (response && response.data && response.data.form_id) {
                isForm31 = (response.data.form_id == 31 || response.data.form_id == "31");
            }
            
            if (isForm31) {
                console.log("[Form ID 31] Form 31 response confirmed");
                
                // Check for successful submission
                if (!response.errors || (Array.isArray(response.errors) && response.errors.length === 0)) {
                    console.log("[Form ID 31] No errors detected - form successful");
                    
                    // Mark form as completed via AJAX before reloading
                    $.ajax({
                        url: ajax_object.ajax_url,
                        type: "POST",
                        data: {
                            action: "mark_ninja_form_completed",
                            user_id: ajax_object.user_id,
                            form_id: ajax_object.form_id,
                            post_id: ajax_object.post_id,
                            nonce: ajax_object.nonce
                        },
                        success: function(response) {
                            console.log("[Form ID 31] Form marked as completed via Ninja Forms event");
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        },
                        error: function(xhr, status, error) {
                            console.error("[Form ID 31] Error marking form as completed:", error);
                            // Still reload even if AJAX fails
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        }
                    });
                } else {
                    console.log("[Form ID 31] Errors detected:", response.errors);
                }
            }
        });
        
        // Periodic success check
        var successCheckInterval = setInterval(function() {
            if (detectFormSuccess()) {
                clearInterval(successCheckInterval);
            }
        }, 3000);
        
        // Button click event listener for Form ID 31
        jQuery(document).on("click", "input[type='submit'], button[type='submit']", function(e) {
            var form31Parent = jQuery(this).closest(".ninja-forms-form[data-form-id='31'], #ninja_forms_form_31, .nf-form-31");
            if (form31Parent.length > 0) {
                console.log("[Form ID 31] Submit button clicked for Form 31");
                
                // Start monitoring for success after button click
                setTimeout(function() {
                    var successFound = false;
                    var attempts = 0;
                    var maxAttempts = 10;
                    
                    var checkInterval = setInterval(function() {
                        attempts++;
                        console.log("[Form ID 31] Success check attempt " + attempts);
                        
                        if (detectFormSuccess()) {
                            successFound = true;
                            clearInterval(checkInterval);
                        } else if (attempts >= maxAttempts) {
                            clearInterval(checkInterval);
                            console.log("[Form ID 31] Max attempts reached, checking for success message manually");
                            
                            // Final check - look for any success indicators
                            var successMsg = document.querySelector(".nf-response-msg");
                            if (successMsg && successMsg.style.display !== "none" && successMsg.textContent.trim()) {
                                console.log("[Form ID 31] Success message found on final check");
                                
                                // Mark form as completed via AJAX before reloading
                                $.ajax({
                                    url: ajax_object.ajax_url,
                                    type: "POST",
                                    data: {
                                        action: "mark_ninja_form_completed",
                                        user_id: ajax_object.user_id,
                                        form_id: ajax_object.form_id,
                                        post_id: ajax_object.post_id,
                                        nonce: ajax_object.nonce
                                    },
                                    success: function(response) {
                                        console.log("[Form ID 31] Form marked as completed via final check");
                                        setTimeout(function() {
                                            window.location.reload();
                                        }, 1000);
                                    },
                                    error: function(xhr, status, error) {
                                        console.error("[Form ID 31] Error marking form as completed:", error);
                                        // Still reload even if AJAX fails
                                        setTimeout(function() {
                                            window.location.reload();
                                        }, 2000);
                                    }
                                });
                            }
                        }
                    }, 1000);
                }, 1000);
            }
        });

        // Also try when Ninja Forms fires its ready event
        $(document).on("nfFormReady", function(e) {
            setTimeout(setupNinjaFormIntegration, 100);
        });
    }
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

// CodyHouse style confirmation dialog for remove collection
function showRemoveConfirmationDialog(callback) {
    // Create dialog HTML
    const dialogHTML = `
        <div class="cd-dialog" id="remove-confirmation-dialog">
            <div class="cd-dialog__container">
                <div class="cd-dialog__content">
                    <h2>Remove from Collection</h2>
                    <p>Are you sure you want to remove this item from your collection?</p>
                    <div class="cd-dialog__buttons">
                        <button class="cd-dialog__action cd-dialog__action--secondary" data-action="cancel">
                            Cancel
                        </button>
                        <button class="cd-dialog__action cd-dialog__action--primary" data-action="confirm">
                            Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Add dialog to DOM
    document.body.insertAdjacentHTML('beforeend', dialogHTML);
    const dialog = document.getElementById('remove-confirmation-dialog');
    
    // Show dialog with animation
    setTimeout(() => {
        dialog.classList.add('cd-dialog--visible');
    }, 10);
    
    // Handle button clicks
    dialog.addEventListener('click', function(e) {
        if (e.target.matches('[data-action="confirm"]')) {
            // User confirmed removal
            closeDialog();
            callback(true);
        } else if (e.target.matches('[data-action="cancel"]') || e.target === dialog) {
            // User cancelled or clicked outside
            closeDialog();
            callback(false);
        }
    });
    
    // Handle ESC key
    const escKeyHandler = function(e) {
        if (e.key === 'Escape') {
            closeDialog();
            callback(false);
        }
    };
    document.addEventListener('keydown', escKeyHandler);
    
    // Close dialog function
    function closeDialog() {
        dialog.classList.remove('cd-dialog--visible');
        document.removeEventListener('keydown', escKeyHandler);
        
        // Remove from DOM after animation
        setTimeout(() => {
            if (dialog && dialog.parentNode) {
                dialog.parentNode.removeChild(dialog);
            }
        }, 300);
    }
}

// CodyHouse Toast Notification Function
function showToastNotification(title, message, type = 'success', duration = 5000) {
    // Remove any existing toast
    const existingToast = document.querySelector('.cd-toast');
    if (existingToast) {
        existingToast.remove();
    }
    
    // Create toast HTML with proper icons
    const toastHTML = `
        <div class="cd-toast cd-toast--${type}">
            <div class="cd-toast__icon">
                ${getToastIcon(type)}
            </div>
            <div class="cd-toast__content">
                <div class="cd-toast__title">${title}</div>
                ${message ? `<div class="cd-toast__message">${message}</div>` : ''}
            </div>
            <button class="cd-toast__close" onclick="this.parentElement.remove()">
                <svg viewBox="0 0 24 24">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
            </button>
        </div>
    `;
    
    // Add toast to DOM
    document.body.insertAdjacentHTML('beforeend', toastHTML);
    const toast = document.querySelector('.cd-toast');
    
    // Show toast with animation
    setTimeout(() => {
        toast.classList.add('cd-toast--visible');
    }, 10);
    
    // Auto-hide toast after duration
    if (duration > 0) {
        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.classList.remove('cd-toast--visible');
                setTimeout(() => {
                    if (toast && toast.parentNode) {
                        toast.remove();
                    }
                }, 300);
            }
        }, duration);
    }
}

// Helper function to get appropriate icon for toast type
function getToastIcon(type) {
    switch (type) {
        case 'success':
            return `<svg viewBox="0 0 24 24">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
            </svg>`;
        case 'error':
            return `<svg viewBox="0 0 24 24">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>`;
        case 'warning':
            return `<svg viewBox="0 0 24 24">
                <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
            </svg>`;
        case 'info':
            return `<svg viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
            </svg>`;
        default:
            return '';
    }
}

// Split Button Functionality
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

    function initializeSplitButton() {
        document.querySelectorAll('.ks-split-btn').forEach(function (container) {
            var toggle = container.querySelector('.ks-toggle-btn');
            var menu = container.querySelector('.ks-menu');
            if (!toggle || !menu) return;

            // Skip if already initialized
            if (toggle.hasAttribute('data-initialized')) return;
            toggle.setAttribute('data-initialized', 'true');

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
    }

    document.addEventListener('DOMContentLoaded', function () {
        initializeSplitButton();

        // Clicking outside closes all menus
        document.addEventListener('click', function () {
            closeAllExcept(null);
        });
    });

    // Make initializeSplitButton globally available for dynamic content
    window.initializeSplitButton = initializeSplitButton;
})();