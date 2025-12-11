// ✅ Fill demo credentials (used only for login form)
function fillCredentials(email, password) {
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    if (emailInput && passwordInput) {
        emailInput.value = email;
        passwordInput.value = password;
    }
}

// ✅ Toggle password visibility for login password
const loginToggle = document.getElementById('togglePassword');
const loginPassword = document.getElementById('password');

if (loginToggle && loginPassword) {
    loginToggle.addEventListener('click', function () {
        const isPassword = loginPassword.type === 'password';
        loginPassword.type = isPassword ? 'text' : 'password';

        this.classList.toggle('fa-eye-slash');
        this.classList.toggle('fa-eye');
    });
}

// ✅ Toggle visibility for new password (in password change form)
const newPassInput = document.querySelector('input[name="new_password"]');
const confirmPassInput = document.querySelector('input[name="confirm_password"]');

function addToggleVisibility(input) {
    if (!input) return;

    const wrapper = document.createElement('div');
    wrapper.classList.add('password-wrapper');

    const toggle = document.createElement('i');
    toggle.className = 'fas fa-eye-slash toggle-password';
    toggle.style.cursor = 'pointer';
    toggle.style.marginLeft = '10px';

    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    wrapper.appendChild(toggle);

    toggle.addEventListener('click', () => {
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        toggle.classList.toggle('fa-eye');
        toggle.classList.toggle('fa-eye-slash');
    });
}

addToggleVisibility(newPassInput);
addToggleVisibility(confirmPassInput);

// ======================= Login Attempt Limiter Countdown =======================
function startCountdownTimer() {
    const countdownTimer = document.getElementById('countdown-timer');
    const lockedEmailInput = document.getElementById('locked-email');
    
    if (!countdownTimer || !lockedEmailInput) {
        return;
    }

    const lockedEmail = lockedEmailInput.value;
    let timeLeft = 300; // 5 minutes in seconds

    // Store countdown data in localStorage
    const countdownData = {
        lockedEmail: lockedEmail,
        endTime: Math.floor(Date.now() / 1000) + timeLeft
    };
    localStorage.setItem('loginCountdown', JSON.stringify(countdownData));

    function updateCountdown() {
        if (timeLeft <= 0) {
            // Countdown finished - clear failed attempts from database
            clearFailedAttempts(lockedEmail);
            
            // Remove error message
            const errorMessage = document.getElementById('error-message');
            if (errorMessage) {
                errorMessage.remove();
            }
            localStorage.removeItem('loginCountdown');
            
            // Show success message
            showCountdownCompleteMessage();
            return;
        }

        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        const displayText = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        countdownTimer.textContent = displayText;
        
        timeLeft--;

        // Update every second
        setTimeout(updateCountdown, 1000);
    }

    // Start the countdown
    updateCountdown();
}

// Function to clear failed attempts from database
function clearFailedAttempts(email) {
    const formData = new FormData();
    formData.append('clear_attempts', 'true');
    formData.append('email_to_clear', email);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Failed attempts cleared for:', email, data);
    })
    .catch(error => {
        console.error('Error clearing failed attempts:', error);
    });
}

// Function to show countdown completion message
function showCountdownCompleteMessage() {
    const loginContainer = document.querySelector('.login-container');
    const existingAlert = document.querySelector('.alert-success');
    
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const successMessage = document.createElement('div');
    successMessage.className = 'alert alert-success';
    successMessage.id = 'success-message';
    successMessage.innerHTML = 'Login restrictions have been cleared. You can now try to login again.';
    
    const logo = document.querySelector('.logo');
    loginContainer.insertBefore(successMessage, logo.nextSibling);
    
    // Remove success message after 5 seconds
    setTimeout(() => {
        if (successMessage.parentNode) {
            successMessage.remove();
        }
    }, 5000);
}

// Check for existing countdown on page load
function checkExistingCountdown() {
    const countdownData = localStorage.getItem('loginCountdown');
    
    if (countdownData) {
        const data = JSON.parse(countdownData);
        const currentTime = Math.floor(Date.now() / 1000);
        let timeLeft = data.endTime - currentTime;
        
        // If timeLeft is negative, countdown has expired - clear everything
        if (timeLeft <= 0) {
            clearFailedAttempts(data.lockedEmail);
            localStorage.removeItem('loginCountdown');
            const errorMessage = document.getElementById('error-message');
            if (errorMessage) {
                errorMessage.remove();
            }
            return;
        }

        // If there's no countdown timer on page but we have data in localStorage,
        // it means the page was refreshed - recreate the error message
        const existingCountdownTimer = document.getElementById('countdown-timer');
        if (!existingCountdownTimer) {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            const displayText = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            // Create error message
            const errorMessage = document.createElement('div');
            errorMessage.className = 'alert alert-error';
            errorMessage.id = 'error-message';
            errorMessage.innerHTML = `Too many login attempts for email: ${data.lockedEmail}. Please try again in <span id="countdown-timer">${displayText}</span> or try a different email.`;
            
            const loginContainer = document.querySelector('.login-container');
            const logo = document.querySelector('.logo');
            loginContainer.insertBefore(errorMessage, logo.nextSibling);

            // Add locked email hidden input
            const lockedEmailInput = document.createElement('input');
            lockedEmailInput.type = 'hidden';
            lockedEmailInput.id = 'locked-email';
            lockedEmailInput.value = data.lockedEmail;
            document.body.appendChild(lockedEmailInput);

            // Start countdown from remaining time
            startCountdownFromTime(timeLeft, data.lockedEmail);
        }
    }
}

function startCountdownFromTime(initialTimeLeft, lockedEmail) {
    let timeLeft = initialTimeLeft;
    const countdownTimer = document.getElementById('countdown-timer');
    
    if (!countdownTimer) return;

    function updateCountdown() {
        if (timeLeft <= 0) {
            // Countdown finished - clear failed attempts
            clearFailedAttempts(lockedEmail);
            
            const errorMessage = document.getElementById('error-message');
            if (errorMessage) {
                errorMessage.remove();
            }
            localStorage.removeItem('loginCountdown');
            
            showCountdownCompleteMessage();
            return;
        }

        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        const displayText = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        countdownTimer.textContent = displayText;
        
        timeLeft--;

        setTimeout(updateCountdown, 1000);
    }

    updateCountdown();
}

// Smart form validation
function setupSmartValidation() {
    const emailInput = document.getElementById('email');
    const lockedEmailInput = document.getElementById('locked-email');
    
    if (emailInput && lockedEmailInput) {
        const lockedEmail = lockedEmailInput.value;
        
        emailInput.addEventListener('input', function() {
            if (this.value === lockedEmail) {
                this.style.borderColor = '#dc3545';
                this.style.backgroundColor = '#fff5f5';
            } else {
                this.style.borderColor = '';
                this.style.backgroundColor = '';
            }
        });
        
        // Check initial value
        if (emailInput.value === lockedEmail) {
            emailInput.style.borderColor = '#dc3545';
            emailInput.style.backgroundColor = '#fff5f5';
        }
    }
}

// Start everything when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // First check for existing countdown in localStorage
    checkExistingCountdown();
    
    // Then start any new countdown from server
    const lockedEmailInput = document.getElementById('locked-email');
    const countdownTimer = document.getElementById('countdown-timer');
    
    if (lockedEmailInput && countdownTimer) {
        startCountdownTimer();
    }
    
    // Setup smart validation
    setupSmartValidation();
});

// Clean up expired countdowns periodically
setInterval(function() {
    const countdownData = localStorage.getItem('loginCountdown');
    if (countdownData) {
        const data = JSON.parse(countdownData);
        const currentTime = Math.floor(Date.now() / 1000);
        
        if (data.endTime <= currentTime) {
            // Clear failed attempts and clean up
            clearFailedAttempts(data.lockedEmail);
            localStorage.removeItem('loginCountdown');
            const errorMessage = document.getElementById('error-message');
            if (errorMessage) {
                errorMessage.remove();
            }
        }
    }
}, 30000);
