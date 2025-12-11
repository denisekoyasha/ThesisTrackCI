// Message display function
function showMessage(message, type = 'error') {
    // Remove any existing messages
    const existingMessage = document.querySelector('.message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    // Create message element
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    messageDiv.textContent = message;
    
    // Insert message after the section title
    const sectionTitle = document.querySelector('.settings-section h2.section-title');
    if (sectionTitle) {
        sectionTitle.parentNode.insertBefore(messageDiv, sectionTitle.nextSibling);
    } else {
        // Fallback: insert at the top of the form
        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.parentNode.insertBefore(messageDiv, passwordForm);
        }
    }
    
    // Auto-remove success messages after 5 seconds
    if (type === 'success') {
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }
}

// Toggle switch functionality
function toggleSwitch(element) {
    element.classList.toggle('active');
}

// Password form handling
const passwordForm = document.getElementById('passwordForm');
if (passwordForm) {
    passwordForm.addEventListener('submit', function(e) {
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const saveBtn = this.querySelector('.save-btn');
        
        // Basic client-side validation
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            showMessage('New passwords do not match!', 'error');
            return;
        }
        
        if (newPassword.length < 6) {
            e.preventDefault();
            showMessage('New password must be at least 6 characters long!', 'error');
            return;
        }
        
        // Show loading state
        saveBtn.classList.add('loading');
        saveBtn.innerHTML = '<i class="fas fa-spinner"></i> Changing Password...';
    });
}

// Separate toggle functions for each password field
function createCurrentPasswordToggle() {
    const passwordInput = document.getElementById('currentPassword');
    if (!passwordInput) return;
    
    const formGroup = passwordInput.closest('.form-group');
    
    // Create toggle button
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'password-toggle current-password-toggle';
    toggleBtn.innerHTML = '<img src="../images/eye-closed.png" alt="Show password" class="eye-icon">';
    
    // Insert toggle button after the input
    formGroup.appendChild(toggleBtn);
    
    // Toggle functionality
    toggleBtn.addEventListener('click', function() {
        const isPassword = passwordInput.type === 'password';
        
        // Toggle input type
        passwordInput.type = isPassword ? 'text' : 'password';
        
        // Toggle eye icon
        const eyeIcon = this.querySelector('.eye-icon');
        if (isPassword) {
            eyeIcon.src = '../images/eye-open.png';
            eyeIcon.alt = 'Hide password';
        } else {
            eyeIcon.src = '../images/eye-closed.png';
            eyeIcon.alt = 'Show password';
        }
    });
}

function createNewPasswordToggle() {
    const passwordInput = document.getElementById('newPassword');
    if (!passwordInput) return;
    
    const formGroup = passwordInput.closest('.form-group');
    
    // Create toggle button
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'password-toggle new-password-toggle';
    toggleBtn.innerHTML = '<img src="../images/eye-closed.png" alt="Show password" class="eye-icon">';
    
    // Insert toggle button after the input
    formGroup.appendChild(toggleBtn);
    
    // Toggle functionality
    toggleBtn.addEventListener('click', function() {
        const isPassword = passwordInput.type === 'password';
        
        // Toggle input type
        passwordInput.type = isPassword ? 'text' : 'password';
        
        // Toggle eye icon
        const eyeIcon = this.querySelector('.eye-icon');
        if (isPassword) {
            eyeIcon.src = '../images/eye-open.png';
            eyeIcon.alt = 'Hide password';
        } else {
            eyeIcon.src = '../images/eye-closed.png';
            eyeIcon.alt = 'Show password';
        }
    });
}

function createConfirmPasswordToggle() {
    const passwordInput = document.getElementById('confirmPassword');
    if (!passwordInput) return;
    
    const formGroup = passwordInput.closest('.form-group');
    
    // Create toggle button
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'password-toggle confirm-password-toggle';
    toggleBtn.innerHTML = '<img src="../images/eye-closed.png" alt="Show password" class="eye-icon">';
    
    // Insert toggle button after the input
    formGroup.appendChild(toggleBtn);
    
    // Toggle functionality
    toggleBtn.addEventListener('click', function() {
        const isPassword = passwordInput.type === 'password';
        
        // Toggle input type
        passwordInput.type = isPassword ? 'text' : 'password';
        
        // Toggle eye icon
        const eyeIcon = this.querySelector('.eye-icon');
        if (isPassword) {
            eyeIcon.src = '../images/eye-open.png';
            eyeIcon.alt = 'Hide password';
        } else {
            eyeIcon.src = '../images/eye-closed.png';
            eyeIcon.alt = 'Show password';
        }
    });
}

// Initialize password toggles for all password fields
createCurrentPasswordToggle();
createNewPasswordToggle();
createConfirmPasswordToggle();

// Real-time password confirmation check with messages
const newPasswordInput = document.getElementById('newPassword');
const confirmPasswordInput = document.getElementById('confirmPassword');

if (newPasswordInput && confirmPasswordInput) {
    let confirmMessage = null;
    
    confirmPasswordInput.addEventListener('input', function() {
        const newPassword = newPasswordInput.value;
        const confirmPassword = this.value;
        
        // Remove existing confirmation message
        if (confirmMessage) {
            confirmMessage.remove();
            confirmMessage = null;
        }
        
        if (confirmPassword && newPassword !== confirmPassword) {
            this.style.borderColor = '#dc3545';
            // Show inline error message
            confirmMessage = document.createElement('div');
            confirmMessage.className = 'message error';
            confirmMessage.textContent = 'Passwords do not match';
            confirmMessage.style.marginTop = '5px';
            confirmMessage.style.marginBottom = '0';
            confirmMessage.style.fontSize = '0.875rem';
            confirmMessage.style.padding = '8px 12px';
            this.parentNode.appendChild(confirmMessage);
        } else {
            this.style.borderColor = '#ced4da';
            if (confirmPassword && newPassword === confirmPassword) {
                // Show success message when passwords match
                confirmMessage = document.createElement('div');
                confirmMessage.className = 'message success';
                confirmMessage.textContent = 'Passwords match';
                confirmMessage.style.marginTop = '5px';
                confirmMessage.style.marginBottom = '0';
                confirmMessage.style.fontSize = '0.875rem';
                confirmMessage.style.padding = '8px 12px';
                this.parentNode.appendChild(confirmMessage);
            }
        }
    });
    
    newPasswordInput.addEventListener('input', function() {
        const confirmPassword = confirmPasswordInput.value;
        if (confirmPassword && this.value !== confirmPassword) {
            confirmPasswordInput.style.borderColor = '#dc3545';
        } else {
            confirmPasswordInput.style.borderColor = '#ced4da';
        }
        
        // Update confirmation message if it exists
        if (confirmMessage) {
            confirmPasswordInput.dispatchEvent(new Event('input'));
        }
    });
}

// Password strength indicator
function checkPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    return strength;
}

// Add password strength indicator for new password
if (newPasswordInput) {
    const newPasswordGroup = newPasswordInput.closest('.form-group');
    if (newPasswordGroup) {
        // Create container for strength indicator
        const strengthContainer = document.createElement('div');
        strengthContainer.className = 'password-strength-container';
        
        const strengthIndicator = document.createElement('div');
        strengthIndicator.className = 'password-strength';
        strengthIndicator.innerHTML = '<div class="password-strength-fill"></div>';
        
        // Add strength text label
        const strengthText = document.createElement('div');
        strengthText.className = 'password-strength-text';
        
        strengthContainer.appendChild(strengthIndicator);
        strengthContainer.appendChild(strengthText);
        newPasswordGroup.appendChild(strengthContainer);
        
        newPasswordInput.addEventListener('input', function() {
            const strength = checkPasswordStrength(this.value);
            const strengthFill = strengthIndicator.querySelector('.password-strength-fill');
            
            strengthFill.className = 'password-strength-fill';
            let strengthLabel = '';
            
            if (this.value.length === 0) {
                strengthFill.style.width = '0%';
                strengthText.textContent = '';
                strengthText.style.color = '#6c757d';
            } else {
                switch (strength) {
                    case 0:
                    case 1:
                        strengthFill.classList.add('strength-weak');
                        strengthLabel = 'Weak password';
                        strengthText.style.color = '#dc3545';
                        break;
                    case 2:
                        strengthFill.classList.add('strength-fair');
                        strengthLabel = 'Fair password';
                        strengthText.style.color = '#fd7e14';
                        break;
                    case 3:
                        strengthFill.classList.add('strength-good');
                        strengthLabel = 'Good password';
                        strengthText.style.color = '#ffc107';
                        break;
                    case 4:
                        strengthFill.classList.add('strength-strong');
                        strengthLabel = 'Strong password';
                        strengthText.style.color = '#20c997';
                        break;
                    case 5:
                        strengthFill.classList.add('strength-very-strong');
                        strengthLabel = 'Very strong password';
                        strengthText.style.color = '#198754';
                        break;
                }
                strengthText.textContent = strengthLabel;
            }
        });
    }
}

// Enhanced back button functionality with improved referrer handling
document.addEventListener('DOMContentLoaded', function() {
    const backButton = document.getElementById('backButton');
    
    if (backButton) {
        backButton.addEventListener('click', function() {
            // Get the stored previous page from PHP session
            const previousPage = document.body.getAttribute('data-previous-page');
            
            if (previousPage && previousPage !== window.location.href) {
                // Use the stored previous page
                window.location.href = previousPage;
            } else {
                // Fallback to browser history or dashboard
                const referrer = document.referrer;
                if (referrer && referrer !== window.location.href && referrer.includes(window.location.origin)) {
                    history.back();
                } else {
                    window.location.href = 'student_dashboard.php';
                }
            }
        });
    }
    
    // Fix history state to prevent double-back issue
    if (window.history.replaceState) {
        // Remove any query parameters to clean up the URL
        const cleanUrl = window.location.origin + window.location.pathname;
        window.history.replaceState(null, null, cleanUrl);
    }
});

// Slider functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize slider functionality
    const reminderSlider = document.getElementById('reminderSlider');
    const reminderLabel = document.getElementById('reminderLabel');
    const quickButtons = document.querySelectorAll('.quick-btn');
    const sliderFill = document.querySelector('.slider-fill');

    // Check if slider elements exist
    if (!reminderSlider || !reminderLabel || !sliderFill) {
        console.log('Slider elements not found - make sure HTML is properly uncommented');
        return;
    }

    function updateSliderValue(value) {
        reminderLabel.textContent = `Remind after ${value} day${value > 1 ? 's' : ''}`;
        
        // Update slider fill
        const percentage = ((value - 1) / 29) * 100;
        sliderFill.style.width = percentage + '%';
        
        // Update active button
        quickButtons.forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.value == value) {
                btn.classList.add('active');
            }
        });
    }

    // Slider input event
    reminderSlider.addEventListener('input', function() {
        updateSliderValue(this.value);
    });

    // Quick select button events
    quickButtons.forEach(button => {
        button.addEventListener('click', function() {
            const value = this.dataset.value;
            reminderSlider.value = value;
            updateSliderValue(value);
        });
    });

    // Initialize slider fill
    updateSliderValue(reminderSlider.value);

    // Add smooth animations on page load
    const sections = document.querySelectorAll('.settings-section');
    sections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        setTimeout(() => {
            section.style.transition = 'all 0.6s ease';
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, index * 200);
    });
});

// Make toggleSwitch globally available
window.toggleSwitch = toggleSwitch;
