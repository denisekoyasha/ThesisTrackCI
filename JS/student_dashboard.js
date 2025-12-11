// Student Dashboard JavaScript - Simplified Version
document.addEventListener("DOMContentLoaded", () => {
    // Tab navigation functionality
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    const tabContents = document.querySelectorAll(".tab-content");

    navItems.forEach((item) => {
        item.addEventListener("click", function (e) {
            const tabId = this.getAttribute("data-tab");
            const tabContent = document.getElementById(tabId);
            
            // Only handle as tab if the tab content exists on this page
            if (tabContent) {
                e.preventDefault();
                navItems.forEach((nav) => nav.classList.remove("active"));
                tabContents.forEach((tab) => tab.classList.remove("active"));
                this.classList.add("active");
                tabContent.classList.add("active");
            }
            // Otherwise allow default link behavior
        });
    });

    // Welcome message
    showMessage("Welcome to your CICT thesis dashboard!", "info");

    // Logout functionality
  const logoutBtn = document.getElementById("logoutBtn")
  const logoutLink = document.getElementById("logoutLink")
  const logoutModal = document.getElementById("logoutModal")

  if (logoutModal) {
    const showModal = (e) => {
      if (e) e.preventDefault()
      logoutModal.classList.add("show")
    }

    const hideModal = () => {
      logoutModal.classList.remove("show")
    }

    if (logoutBtn) logoutBtn.addEventListener("click", showModal)
    if (logoutLink) logoutLink.addEventListener("click", showModal)

    // Close modal when clicking outside
    logoutModal.addEventListener("click", (e) => {
      if (e.target === logoutModal) {
        hideModal()
      }
    })

    // Make closeLogoutModal and confirmLogout globally available
    window.closeLogoutModal = hideModal
    window.confirmLogout = () => {
      window.location.href = "../logout.php"
    }
  }
});

// Show notification messages
function showMessage(text, type = "info") {
    const existingMessages = document.querySelectorAll(".message");
    existingMessages.forEach((msg) => msg.remove());

    const message = document.createElement("div");
    message.className = `message ${type}`;
    message.textContent = text;

    const mainContent = document.querySelector(".main-content");
    const header = document.querySelector(".main-header");
    mainContent.insertBefore(message, header.nextSibling);

    setTimeout(() => {
        message.remove();
    }, 5000);
}

// Toggle sidebar visibility
function toggleSidebar() {
    const sidebar = document.querySelector(".sidebar");
    sidebar.classList.toggle("open");
}

// Handle window resize
window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar");
    if (window.innerWidth > 768) {
        sidebar.classList.remove("open");
    }
});

// User dropdown functionality
const userAvatar = document.getElementById('userAvatar');
const userDropdown = document.getElementById('userDropdown');

if (userAvatar && userDropdown) {
    userAvatar.addEventListener('click', () => {
        const isVisible = userDropdown.style.display === 'block';
        userDropdown.style.display = isVisible ? 'none' : 'block';
    });

    document.addEventListener('click', (e) => {
        if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
            userDropdown.style.display = 'none';
        }
    });
}

// Initialize logout functionality
function initLogout() {
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutLink = document.getElementById('logoutLink'); 
    const logoutModal = document.getElementById('logoutModal');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');

    if (!logoutBtn || !logoutModal) return;

    const showLogoutModal = (e) => {
        if (e) e.preventDefault();
        logoutModal.style.display = 'flex';
    };

    const hideLogoutModal = () => {
        logoutModal.style.display = 'none';
    };

    if (logoutBtn) logoutBtn.addEventListener('click', showLogoutModal);
    if (logoutLink) logoutLink.addEventListener('click', showLogoutModal);
    if (cancelLogout) cancelLogout.addEventListener('click', hideLogoutModal);
    if (confirmLogout) confirmLogout.addEventListener('click', () => {
        window.location.href = '../logout.php';
    });
}

// ==================== Start of V7 UPDATE ====================
// Global variables
let selectedFile = null;

// Open modal function
function openUploadModal() {
    document.getElementById('uploadModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    resetUploadForm(); // Reset form when opening
}

// Close modal function
function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    resetUploadForm();
}

// Preview image function
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            showFlashMessage('Please select a JPG, PNG, or GIF image', 'error');
            resetUploadForm();
            return;
        }
        
        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            showFlashMessage('Image must be less than 2MB', 'error');
            resetUploadForm();
            return;
        }
        
        selectedFile = file;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            uploadBtn.disabled = false;
            
            // Update the upload area text
            const uploadArea = document.querySelector('.upload-area p');
            uploadArea.textContent = 'Selected: ' + file.name;
            uploadArea.style.color = '#333';
        }
        reader.readAsDataURL(file);
    }
}

function uploadProfilePicture() {
    if (!selectedFile) {
        showFlashMessage('Please select an image first', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('profile_picture', selectedFile);
    
    const uploadBtn = document.getElementById('uploadBtn');
    uploadBtn.disabled = true;
    uploadBtn.textContent = 'Uploading...';
    
    fetch('student_upload_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add timestamp to prevent caching
            const newSrc = '../' + data.image_path + '?t=' + Date.now();
            
            // Update all profile images on the page
            document.querySelectorAll('.sidebar-avatar, .user-avatar, #sidebarProfileImage, #userAvatar').forEach(img => {
                img.src = newSrc;
            });
            
            showFlashMessage('Profile picture updated!', 'success');
            closeUploadModal();
        } else {
            throw new Error(data.message || 'Upload failed');
        }
    })
    .catch(error => {
        showFlashMessage(error.message, 'error');
    })
    .finally(() => {
        uploadBtn.textContent = 'Upload';
        uploadBtn.disabled = false;
    });
}

// Reset upload form function
function resetUploadForm() {
    document.getElementById('fileInput').value = '';
    document.getElementById('imagePreview').src = '';
    document.getElementById('imagePreview').style.display = 'none';
    const uploadText = document.querySelector('.upload-area p');
    if (uploadText) {
        uploadText.textContent = 'Click to select an image';
        uploadText.style.color = '#777';
    }
    document.getElementById('uploadBtn').disabled = true;
    selectedFile = null;
}

// Show flash message function
function showFlashMessage(message, type) {
    // Remove any existing flash messages
    const existingMessages = document.querySelectorAll('.flash-message');
    existingMessages.forEach(msg => msg.remove());
    
    // Create new message element
    const flashDiv = document.createElement('div');
    flashDiv.className = `flash-message ${type}`;
    flashDiv.textContent = message;
    document.body.appendChild(flashDiv);
    
    // Position the message (centered at top)
    flashDiv.style.position = 'fixed';
    flashDiv.style.top = '20px';
    flashDiv.style.left = '50%';
    flashDiv.style.transform = 'translateX(-50%)';
    flashDiv.style.padding = '10px 20px';
    flashDiv.style.borderRadius = '4px';
    flashDiv.style.zIndex = '10000';
    flashDiv.style.animation = 'fadeIn 0.3s ease-in-out';
    
    // Style based on type
    if (type === 'success') {
        flashDiv.style.backgroundColor = '#4CAF50';
        flashDiv.style.color = 'white';
    } else {
        flashDiv.style.backgroundColor = '#f44336';
        flashDiv.style.color = 'white';
    }
    
    // Remove message after 3 seconds
    setTimeout(() => {
        flashDiv.style.animation = 'fadeOut 0.3s ease-in-out';
        setTimeout(() => {
            flashDiv.remove();
        }, 300);
    }, 3000);
}

// Close modal when clicking outside
document.getElementById('uploadModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeUploadModal();
    }
});

// Add click event to your avatar to open the modal (if exists)
document.querySelectorAll('.sidebar-user img, .profile-picture').forEach(el => {
    el.addEventListener('click', function() {
        openUploadModal();
    });
});

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        to { opacity: 1; transform: translateX(-50%) translateY(0); }
    }
    @keyframes fadeOut {
        from { opacity: 1; transform: translateX(-50%) translateY(0); }
        to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
    }
`;
document.head.appendChild(style);

// ==================== END OF V7 UPDATE ====================


// =============== Start of version 11 update =============== 


    // =============== Notification functionality ===============
document.addEventListener('DOMContentLoaded', function() {
    initializeNotificationSystem();
});

    // Session keep-alive: ping server when the user is active to prevent session timeout
    (function() {
        let activity = false;
        const KEEP_ALIVE_INTERVAL = 4 * 60 * 1000; // 4 minutes

        function markActivity() {
            activity = true;
        }

        ['mousemove', 'keydown', 'scroll', 'click', 'touchstart'].forEach(evt => {
            window.addEventListener(evt, markActivity, {passive: true});
        });

        setInterval(() => {
            if (!activity) return;
            activity = false;
            fetch('Student/student_dashboard.php?keep_alive=1', { credentials: 'same-origin' })
                .then(resp => resp.json())
                .then(data => {
                    // optionally handle keep-alive response
                    // console.log('keep-alive', data);
                })
                .catch(err => {
                    // ignore network errors
                });
        }, KEEP_ALIVE_INTERVAL);
    })();

function initializeNotificationSystem() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationMenu = document.getElementById('notificationMenu');
    const markAllReadBtn = document.getElementById('markAllRead');
    const notificationList = document.getElementById('notificationList');

    // Toggle notification dropdown
    if (notificationBtn && notificationMenu) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationMenu.classList.toggle('show');
            
            // Mark as read when opening (optional)
            if (notificationMenu.classList.contains('show')) {
                // You can auto-mark as read when opening, or leave it manual
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            notificationMenu.classList.remove('show');
        });

        // Prevent dropdown from closing when clicking inside
        notificationMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // Mark all as read
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            markAllNotificationsAsRead();
        });
    }

    // Mark individual notification as read when clicked
    if (notificationList) {
        notificationList.addEventListener('click', function(e) {
            const notificationItem = e.target.closest('.notification-item');
            if (notificationItem && notificationItem.classList.contains('unread')) {
                const notificationId = notificationItem.getAttribute('data-id');
                markNotificationAsRead(notificationId, notificationItem);
            }
        });
    }

    // Auto-refresh notifications every 30 seconds
    setInterval(refreshNotifications, 30000);
}

function markAllNotificationsAsRead() {
    console.log('Marking all notifications as read...');
    
    fetch('student_dashboard.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_read'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        console.log('Mark all read response:', data);
        if (data.success) {
            // Remove unread class from all notifications
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            
            // Hide the mark all read button
            const markAllReadBtn = document.getElementById('markAllRead');
            if (markAllReadBtn) {
                markAllReadBtn.style.display = 'none';
            }
            
            // Remove notification badge completely
            const badge = document.getElementById('notificationBadge');
            if (badge) {
                badge.remove();
            }
            
            // Show success message
            showFlashMessage('All notifications marked as read', 'success');
        } else {
            throw new Error(data.message || 'Failed to mark all as read');
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
        showFlashMessage('Error marking notifications as read: ' + error.message, 'error');
    });
}

function refreshNotifications() {
    fetch('student_dashboard.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_notifications'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNotificationDisplay(data.notifications, data.unread_count);
        }
    })
    .catch(error => console.error('Error refreshing notifications:', error));
}

function updateNotificationDisplay(notifications, unreadCount) {
    // Update notification badge
    updateNotificationBadgeCount(unreadCount);
    
    // You can implement full notification list update here if needed
    // For now, we'll just update the badge count
}

function updateNotificationBadgeCount(unreadCount) {
    const badge = document.getElementById('notificationBadge');
    const notificationBtn = document.getElementById('notificationBtn');
    
    if (unreadCount > 0) {
        if (!badge) {
            const newBadge = document.createElement('span');
            newBadge.className = 'notification-badge';
            newBadge.id = 'notificationBadge';
            newBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            notificationBtn.appendChild(newBadge);
        } else {
            badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
        }
        
        // Show mark all read button if there are unread notifications
        const markAllReadBtn = document.getElementById('markAllRead');
        if (markAllReadBtn) {
            markAllReadBtn.style.display = 'block';
        }
    } else {
        // Remove badge if no unread notifications
        if (badge) {
            badge.remove();
        }
        
        // Hide mark all read button
        const markAllReadBtn = document.getElementById('markAllRead');
        if (markAllReadBtn) {
            markAllReadBtn.style.display = 'none';
        }
    }
}

function updateNotificationBadge() {
    // Count remaining unread notifications in the DOM
    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
    updateNotificationBadgeCount(unreadCount);
}


       
// ===============  End of version 11 update =============== 

// // Session Timeout for Testing (5 minutes total, 1 minute warning)
// class SessionTimeout {
//     constructor() {
//         this.timeoutMinutes = 5; // 5 minutes for testing
//         this.warningMinutes = 1; // Show warning 1 minute before logout
//         this.timeoutMs = this.timeoutMinutes * 60 * 1000;
//         this.warningMs = this.warningMinutes * 60 * 1000;
//         this.logoutTimer = null;
//         this.warningTimer = null;
//         this.isWarningShown = false;
//         this.keepAliveInterval = null;
        
//         console.log(`Session timeout initialized: ${this.timeoutMinutes} minutes total, ${this.warningMinutes} minute warning`);
//         this.init();
//     }
    
//     init() {
//         this.resetTimers();
//         this.setupActivityListeners();
//         this.setupAjaxInterceptor();
//         this.startKeepAlive();
        
//         // Log for testing
//         console.log('Session timers started. Remain inactive to test timeout.');
//     }
    
//     resetTimers() {
//         // Clear existing timers
//         if (this.logoutTimer) {
//             clearTimeout(this.logoutTimer);
//             console.log('Previous logout timer cleared');
//         }
//         if (this.warningTimer) {
//             clearTimeout(this.warningTimer);
//             console.log('Previous warning timer cleared');
//         }
//         this.isWarningShown = false;
        
//         // Set new timers
//         this.warningTimer = setTimeout(() => {
//             console.log('Warning timer triggered - showing warning modal');
//             this.showWarning();
//         }, this.timeoutMs - this.warningMs);
        
//         this.logoutTimer = setTimeout(() => {
//             console.log('Logout timer triggered - redirecting to login');
//             this.redirectToLogin();
//         }, this.timeoutMs);
        
//         console.log(`Timers reset: Warning in ${(this.timeoutMs - this.warningMs)/1000}s, Logout in ${this.timeoutMs/1000}s`);
//     }
    
//     setupActivityListeners() {
//         // Reset timers on user activity
//         const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
//         events.forEach(event => {
//             document.addEventListener(event, () => {
//                 console.log(`Activity detected: ${event} - resetting timers`);
//                 this.resetTimers();
//             }, { passive: true });
//         });
        
//         // Also reset on form interactions
//         document.addEventListener('input', () => this.resetTimers());
//         document.addEventListener('change', () => this.resetTimers());
//         document.addEventListener('focus', () => this.resetTimers(), true);
//     }
    
//     setupAjaxInterceptor() {
//         // Intercept AJAX requests to check for session expiration
//         const originalFetch = window.fetch;
//         const self = this;
        
//         window.fetch = function(...args) {
//             return originalFetch.apply(this, args).then(response => {
//                 if (response.status === 401) {
//                     return response.json().then(data => {
//                         if (data.session_expired) {
//                             console.log('Session expired detected in AJAX request - redirecting');
//                             self.redirectToLogin();
//                             return Promise.reject(new Error('Session expired'));
//                         }
//                         return response;
//                     });
//                 }
//                 return response;
//             }).catch(error => {
//                 if (error.message === 'Session expired') {
//                     throw error;
//                 }
//                 return Promise.reject(error);
//             });
//         };
//     }
    
//     startKeepAlive() {
//         // Send keep-alive request every 2 minutes for testing
//         this.keepAliveInterval = setInterval(() => {
//             console.log('Sending keep-alive request');
//             this.sendKeepAlive();
//         }, 2 * 60 * 1000);
//     }
    
//     sendKeepAlive() {
//         fetch('?keep_alive=1', {
//             method: 'GET',
//             headers: {
//                 'X-Requested-With': 'XMLHttpRequest'
//             }
//         })
//         .then(response => response.json())
//         .then(data => {
//             if (data.success) {
//                 console.log('Keep-alive successful at:', new Date().toLocaleTimeString());
//             }
//         })
//         .catch(error => {
//             console.log('Keep-alive request failed:', error);
//         });
//     }
    
//     showWarning() {
//         if (this.isWarningShown) return;
        
//         this.isWarningShown = true;
//         console.log('Showing session warning modal');
//         this.createWarningModal();
//     }
    
//     createWarningModal() {
//         // Remove any existing modals
//         const existingModal = document.getElementById('sessionWarningModal');
//         if (existingModal) existingModal.remove();
        
//         // Create warning modal
//         const modal = document.createElement('div');
//         modal.id = 'sessionWarningModal';
//         modal.className = 'session-modal';
//         modal.innerHTML = `
//             <div class="session-modal-content">
//                 <div class="session-modal-header">
//                     <h3><i class="fas fa-clock"></i> Session Timeout Warning</h3>
//                 </div>
//                 <div class="session-modal-body">
//                     <div class="warning-icon">
//                         <i class="fas fa-exclamation-triangle"></i>
//                     </div>
//                     <p>Your session will expire in <strong>${this.warningMinutes} minute</strong> due to inactivity.</p>
//                     <p><small>Testing Mode: Session timeout is set to ${this.timeoutMinutes} minutes for testing.</small></p>
//                     <p>Would you like to continue your session?</p>
//                 </div>
//                 <div class="session-modal-actions">
//                     <button id="continueSession" class="btn-modal btn-primary">
//                         <i class="fas fa-sync-alt"></i> Continue Session
//                     </button>
//                     <button id="logoutNow" class="btn-modal btn-secondary">
//                         <i class="fas fa-sign-out-alt"></i> Logout Now
//                     </button>
//                 </div>
//             </div>
//         `;
        
//         document.body.appendChild(modal);
        
//         // Add event listeners
//         document.getElementById('continueSession').addEventListener('click', () => {
//             console.log('User chose to continue session');
//             this.continueSession();
//         });
        
//         document.getElementById('logoutNow').addEventListener('click', () => {
//             console.log('User chose to logout immediately');
//             this.redirectToLogin();
//         });
        
//         // Show modal with animation
//         setTimeout(() => modal.classList.add('show'), 100);
        
//         // Close modal when clicking outside
//         modal.addEventListener('click', (e) => {
//             if (e.target === modal) {
//                 this.continueSession();
//             }
//         });
//     }
    
//     redirectToLogin() {
//         console.log('Redirecting to student_login.php');
        
//         // Clear all timers and intervals
//         if (this.logoutTimer) clearTimeout(this.logoutTimer);
//         if (this.warningTimer) clearTimeout(this.warningTimer);
//         if (this.keepAliveInterval) clearInterval(this.keepAliveInterval);
        
//         // Direct redirect to login page
//         window.location.href = '../student_login.php?timeout=1';
//     }
    
//     continueSession() {
//         console.log('Continuing session - resetting timers');
        
//         // Hide warning modal
//         const modal = document.getElementById('sessionWarningModal');
//         if (modal) {
//             modal.classList.remove('show');
//             setTimeout(() => {
//                 if (modal.parentNode) {
//                     modal.parentNode.removeChild(modal);
//                 }
//             }, 300);
//         }
        
//         // Reset timers
//         this.resetTimers();
        
//         // Send keep-alive request
//         this.sendKeepAlive();
//     }
    
//     logout() {
//         console.log('Logging out user');
//         this.redirectToLogin();
//     }
    
//     // Method to manually trigger warning for testing
//     testWarning() {
//         console.log('Manual test: triggering warning');
//         this.showWarning();
//     }
    
//     // Method to manually trigger expiration for testing
//     testExpiration() {
//         console.log('Manual test: triggering expiration and redirect');
//         this.redirectToLogin();
//     }
// }

// // Initialize session timeout when DOM is loaded
// let sessionTimeout;
// document.addEventListener('DOMContentLoaded', function() {
//     sessionTimeout = new SessionTimeout();
    
//     // Handle page visibility changes (tab switching)
//     document.addEventListener('visibilitychange', function() {
//         if (!document.hidden) {
//             console.log('Page became visible - resetting timers');
//             sessionTimeout.resetTimers();
//         }
//     });
    
//     // Add testing buttons to the page for easy testing
//     addTestingButtons();
// });

// function addTestingButtons() {
//     // Create testing buttons container
//     const testContainer = document.createElement('div');
//     testContainer.style.position = 'fixed';
//     testContainer.style.bottom = '10px';
//     testContainer.style.right = '10px';
//     testContainer.style.zIndex = '9999';
//     testContainer.style.background = 'rgba(0,0,0,0.8)';
//     testContainer.style.padding = '10px';
//     testContainer.style.borderRadius = '5px';
//     testContainer.style.color = 'white';
//     testContainer.style.fontSize = '12px';
    
//     testContainer.innerHTML = `
//         <div style="margin-bottom: 5px;"><strong>Session Timeout Test</strong></div>
//         <button onclick="sessionTimeout.testWarning()" style="background: #e53e3e; color: white; border: none; padding: 5px 10px; margin: 2px; border-radius: 3px; cursor: pointer;">Test Warning</button>
//         <button onclick="sessionTimeout.testExpiration()" style="background: #718096; color: white; border: none; padding: 5px 10px; margin: 2px; border-radius: 3px; cursor: pointer;">Test Redirect</button>
//         <button onclick="sessionTimeout.resetTimers()" style="background: #38a169; color: white; border: none; padding: 5px 10px; margin: 2px; border-radius: 3px; cursor: pointer;">Reset Timers</button>
//     `;
    
//     document.body.appendChild(testContainer);
// }

// // Export for potential use in other scripts
// window.SessionTimeout = SessionTimeout;

// console.log('Session timeout script loaded - 5 minute timeout enabled');
