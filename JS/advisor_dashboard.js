// advisor_dashboard.js
document.addEventListener("DOMContentLoaded", () => {
    // Activate the current tab immediately on page load
    activateCurrentTab();
    
    // Initialize navigation
    initNavigation();
    
    // Show welcome message
    // showMessage("Welcome to your dashboard!", "info");
    
    // Initialize UI components
    initUI();
});

function activateCurrentTab() {
    const currentPage = window.location.pathname.split('/').pop();
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    
    navItems.forEach(item => {
        if (item.getAttribute("href").includes(currentPage)) {
            // Activate the tab immediately
            item.classList.add("active");
            const tabId = item.getAttribute("data-tab");
            if (tabId) {
                const tabContent = document.getElementById(tabId);
                if (tabContent) tabContent.classList.add("active");
            }
        }
    });
}

function initNavigation() {
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    
    navItems.forEach(item => {
        item.addEventListener("click", function(e) {
            const tabId = this.getAttribute("data-tab");
            const tabContent = document.getElementById(tabId);
            const href = this.getAttribute("href");
            
            // If it's a tab on current page
            if (tabContent) {
                e.preventDefault();
                
                // Update active states
                document.querySelectorAll(".nav-item").forEach(nav => nav.classList.remove("active"));
                document.querySelectorAll(".tab-content").forEach(tab => tab.classList.remove("active"));
                
                this.classList.add("active");
                tabContent.classList.add("active");
            }
            // If it's a link to another page, allow default behavior
        });
    });
}

function initUI() {
    // User dropdown
    const userAvatar = document.getElementById('userAvatar');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userAvatar && userDropdown) {
        userAvatar.addEventListener('click', () => {
            userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
        });
        
        document.addEventListener('click', (e) => {
            if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.style.display = 'none';
            }
        });
    }
    
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
};


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



function showMessage(text, type = "info") {
    const existingMessages = document.querySelectorAll(".message");
    existingMessages.forEach(msg => msg.remove());
    
    const message = document.createElement("div");
    message.className = `message ${type}`;
    message.textContent = text;
    
    const mainContent = document.querySelector(".main-content");
    if (mainContent) {
        const header = mainContent.querySelector(".main-header");
        if (header) {
            header.insertAdjacentElement("afterend", message);
        } else {
            mainContent.prepend(message);
        }
    }
    
    setTimeout(() => message.remove(), 5000);
}

function toggleSidebar() {
    document.querySelector(".sidebar")?.classList.toggle("open");
}

// Responsive sidebar
window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar");
    if (sidebar && window.innerWidth > 768) {
        sidebar.classList.remove("open");
    }
});


// ==================== Start of V7 UPDATE ====================

 // Global variables
        let selectedFile = null;
        
        // Open modal function
        function openUploadModal() {
            document.getElementById('uploadModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
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
                if (!file.type.match('image.*')) {
                    showFlashMessage('Please select an image file (JPEG, PNG, etc.)', 'error');
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
    formData.append('profileImage', selectedFile);
    
    const uploadBtn = document.getElementById('uploadBtn');
    uploadBtn.disabled = true;
    uploadBtn.textContent = 'Uploading...';
    
    fetch('advisor_upload_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // First check if the response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error('Server returned non-JSON response: ' + text);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Update all avatar images on the page
            const newSrc = '../' + data.filePath + '?t=' + new Date().getTime();
            
            // Update sidebar avatar
            const sidebarAvatar = document.querySelector('.image-sidebar-avatar');
            if (sidebarAvatar) sidebarAvatar.src = newSrc;
            
            // Update header avatar
            const headerAvatar = document.querySelector('.user-avatar');
            if (headerAvatar) headerAvatar.src = newSrc;
            
            showFlashMessage(data.message, 'success');
            closeUploadModal();
        } else {
            throw new Error(data.message || 'Upload failed');
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        let errorMessage = error.message;
        
        // Handle common error cases
        if (errorMessage.includes('<br />') || errorMessage.includes('<!DOCTYPE')) {
            errorMessage = 'Server error occurred. Please check the console for details.';
        }
        
        showFlashMessage(errorMessage, 'error');
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
            document.querySelector('.upload-area p').textContent = 'Click to select an image';
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
            
            // Remove message after 3 seconds
            setTimeout(() => {
                flashDiv.remove();
            }, 3000);
        }
        
        // Close modal when clicking outside
        document.getElementById('uploadModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUploadModal();
            }
        });
        
        // Add click event to your avatar to open the modal
        document.querySelector('.sidebar-user img').addEventListener('click', function() {
            openUploadModal();
        });


        // ==================== END OF V7 UPDATE ====================

// =============== Start of version 11 update =============== 


        // Notification functionality
        document.addEventListener('DOMContentLoaded', function() {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationMenu = document.getElementById('notificationMenu');
            const markAllReadBtn = document.getElementById('markAllRead');
            const notificationList = document.getElementById('notificationList');
            const notificationBadge = document.getElementById('notificationBadge');

            // Toggle notification dropdown
            if (notificationBtn && notificationMenu) {
                notificationBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationMenu.classList.toggle('show');
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

            // Mark individual notification as read
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
        });

        function markNotificationAsRead(notificationId, element) {
            fetch('advisor_dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_as_read&notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    element.classList.remove('unread');
                    updateNotificationBadge();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function markAllNotificationsAsRead() {
            fetch('advisor_dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_as_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove unread class from all notifications
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    updateNotificationBadge();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function refreshNotifications() {
            fetch('advisor_dashboard.php', {
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
            .catch(error => console.error('Error:', error));
        }

        function updateNotificationDisplay(notifications, unreadCount) {
            // Update notification badge
            const badge = document.getElementById('notificationBadge');
            if (unreadCount > 0) {
                if (!badge) {
                    const notificationBtn = document.getElementById('notificationBtn');
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge';
                    newBadge.id = 'notificationBadge';
                    newBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                    notificationBtn.appendChild(newBadge);
                } else {
                    badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                }
            } else if (badge) {
                badge.remove();
            }

            // Update notification list (you can implement this if needed)
            // This would require more complex DOM manipulation
        }

        function updateNotificationBadge() {
            // Count remaining unread notifications
            const unreadCount = document.querySelectorAll('.notification-item.unread').length;
            const badge = document.getElementById('notificationBadge');
            
            if (unreadCount > 0) {
                if (!badge) {
                    const notificationBtn = document.getElementById('notificationBtn');
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge';
                    newBadge.id = 'notificationBadge';
                    newBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                    notificationBtn.appendChild(newBadge);
                } else {
                    badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                }
            } else if (badge) {
                badge.remove();
                
                // Remove mark all read button if no unread notifications
                const markAllReadBtn = document.getElementById('markAllRead');
                if (markAllReadBtn) {
                    markAllReadBtn.remove();
                }
            }
        }


       
// ===============  End of version 11 update ===============
