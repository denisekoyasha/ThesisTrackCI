let currentAdvisorId = null;

document.addEventListener("DOMContentLoaded", () => {
  // Initialize all components
  initializeTabs();
  initUserDropdown();
  initLogout();
  initializeNotifications();
  
  // Show welcome message
  showMessage("Welcome CICT Reseach Coordinator!", "info");
});

// ==================== TAB MANAGEMENT ====================
function initializeTabs() {
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    const tabContents = document.querySelectorAll(".tab-content");

    // Activate current tab based on URL
    const currentPage = window.location.pathname.split('/').pop();
    navItems.forEach(item => {
        if (item.getAttribute("href").includes(currentPage)) {
            item.classList.add("active");
            const tabId = item.getAttribute("data-tab");
            if (tabId) {
                const tabContent = document.getElementById(tabId);
                if (tabContent) tabContent.classList.add("active");
            }
        }
    });

    // Add click handlers
    navItems.forEach(item => {
        item.addEventListener("click", function(e) {
            const tabId = this.getAttribute("data-tab");
            const tabContent = document.getElementById(tabId);
            
            if (tabContent) {
                e.preventDefault();
                navItems.forEach(nav => nav.classList.remove("active"));
                tabContents.forEach(tab => tab.classList.remove("active"));
                this.classList.add("active");
                tabContent.classList.add("active");
            }
        });
    });
}

// ==================== USER DROPDOWN ====================
function initUserDropdown() {
  const userAvatar = document.getElementById("userAvatar");
  const userDropdown = document.getElementById("userDropdown");

  if (userAvatar && userDropdown) {
    userAvatar.addEventListener("click", (e) => {
      e.stopPropagation();
      userDropdown.style.display = userDropdown.style.display === "block" ? "none" : "block";
    });

    document.addEventListener("click", (e) => {
      if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
        userDropdown.style.display = "none";
      }
    });
  }
}

// ==================== LOGOUT MANAGEMENT ====================
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

    // Make functions globally available
    window.closeLogoutModal = hideLogoutModal;
    window.confirmLogout = () => {
        window.location.href = '../logout.php';
    };
}

// ==================== SIDEBAR MANAGEMENT ====================
function toggleSidebar() {
  const sidebar = document.querySelector(".sidebar");
  if (sidebar) {
    sidebar.classList.toggle("open");
  }
}

// Handle window resize for sidebar
window.addEventListener("resize", () => {
  const sidebar = document.querySelector(".sidebar");
  if (window.innerWidth > 768 && sidebar) {
    sidebar.classList.remove("open");
  }
});

// ==================== NOTIFICATION SYSTEM ====================
function initializeNotifications() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationMenu = document.getElementById('notificationMenu');
    const markAllReadBtn = document.getElementById('markAllRead');
    const notificationList = document.getElementById('notificationList');

    // Toggle notification dropdown
    if (notificationBtn && notificationMenu) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationMenu.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationBtn.contains(e.target) && !notificationMenu.contains(e.target)) {
                notificationMenu.classList.remove('show');
            }
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
}

function markNotificationAsRead(notificationId, element) {
    const formData = new FormData();
    formData.append('action', 'mark_as_read');
    formData.append('notification_id', notificationId);

    fetch('coordinator_dashboard.php', {
        method: 'POST',
        body: formData
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
    const formData = new FormData();
    formData.append('action', 'mark_as_read');

    fetch('coordinator_dashboard.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove unread class from all notifications
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            updateNotificationBadge();
            
            // Hide mark all read button
            const markAllReadBtn = document.getElementById('markAllRead');
            if (markAllReadBtn) {
                markAllReadBtn.style.display = 'none';
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

function refreshNotifications() {
    const formData = new FormData();
    formData.append('action', 'get_notifications');

    fetch('coordinator_dashboard.php', {
        method: 'POST',
        body: formData
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

    // Show/hide mark all read button
    const markAllReadBtn = document.getElementById('markAllRead');
    if (markAllReadBtn) {
        markAllReadBtn.style.display = unreadCount > 0 ? 'block' : 'none';
    }
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
    }
    
    // Update mark all read button visibility
    const markAllReadBtn = document.getElementById('markAllRead');
    if (markAllReadBtn) {
        markAllReadBtn.style.display = unreadCount > 0 ? 'block' : 'none';
    }
}

// ==================== PROFILE PICTURE UPLOAD ====================
function openUploadModal() {
    document.getElementById('uploadModal').style.display = 'flex';
}

function closeUploadModal() {
    const modal = document.getElementById('uploadModal');
    const preview = document.getElementById('imagePreview');
    modal.style.display = 'none';
    preview.style.display = 'none';
    preview.src = '';
    document.getElementById('fileInput').value = '';
}

// Image preview
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

async function uploadProfilePicture() {
    const fileInput = document.getElementById('fileInput');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (!fileInput.files || fileInput.files.length === 0) {
        showMessage('Please select an image first', 'error');
        return;
    }

    const file = fileInput.files[0];
    const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
    const maxSize = 2 * 1024 * 1024; // 2MB

    if (!validTypes.includes(file.type)) {
        showMessage('Only JPG, PNG or GIF images are allowed', 'error');
        return;
    }

    if (file.size > maxSize) {
        showMessage('Image must be less than 2MB', 'error');
        return;
    }

    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

    try {
        const formData = new FormData();
        formData.append('profile_picture', file);

        const response = await fetch('../Coordinator/coordinator_upload_profile.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Upload failed');
        }

        // Update profile pictures with cache buster
        const newSrc = '../' + data.filePath + '?t=' + Date.now();
        
        // Update sidebar avatar
        const sidebarAvatar = document.getElementById('currentProfilePicture');
        if (sidebarAvatar) sidebarAvatar.src = newSrc;
        
        // Update header avatar
        const headerAvatar = document.getElementById('userAvatar');
        if (headerAvatar) headerAvatar.src = newSrc;
        
        showMessage('Profile picture updated successfully!', 'success');
        closeUploadModal();
        
    } catch (error) {
        console.error('Upload error:', error);
        showMessage(error.message || 'An error occurred. Please try again.', 'error');
    } finally {
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Upload';
    }
}

// ==================== MESSAGE SYSTEM ====================
function showMessage(message, type = 'info') {
    const messageContainer = document.getElementById('messageContainer') || createMessageContainer();
    const messageEl = document.createElement('div');
    messageEl.className = `message ${type}`;
    messageEl.textContent = message;
    messageContainer.appendChild(messageEl);
    
    setTimeout(() => messageEl.remove(), 5000);
}

function createMessageContainer() {
    const container = document.createElement('div');
    container.id = 'messageContainer';
    container.style.position = 'fixed';
    container.style.top = '20px';
    container.style.right = '20px';
    container.style.zIndex = '1000';
    document.body.appendChild(container);
    return container;
}

// ==================== UTILITY FUNCTIONS ====================
function initializeDropdowns() {
    // Initialize any other dropdowns if needed
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('uploadModal');
    if (event.target == modal) {
        closeUploadModal();
    }
}

// Ensure DOM is fully loaded before attaching events
document.addEventListener('DOMContentLoaded', function() {
    // Get elements safely
    const uploadBtn = document.getElementById('uploadBtn');
    const fileInput = document.getElementById('fileInput');
    
    if (!uploadBtn || !fileInput) {
        console.error('Required elements not found! Check your HTML IDs.');
        return;
    }
    
    // Alternative way to attach event listener (better than onclick in HTML)
    uploadBtn.addEventListener('click', uploadProfilePicture);
});
