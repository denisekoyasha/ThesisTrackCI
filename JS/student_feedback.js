// student_feedback.js - Fixed version
document.addEventListener("DOMContentLoaded", function() {
    // Initialize components
    initializeTabs();
    initUserDropdown();
    initLogout();
    
    // Show welcome message
    showMessage("Viewing your feedback history.", "info");
});

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

function initUserDropdown() {
    const userAvatar = document.getElementById('userAvatar');
    const userDropdown = document.getElementById('userDropdown');

    if (userAvatar && userDropdown) {
        userAvatar.addEventListener('click', (e) => {
            e.stopPropagation();
            const isVisible = userDropdown.style.display === 'block';
            userDropdown.style.display = isVisible ? 'none' : 'block';
        });

        document.addEventListener('click', () => {
            userDropdown.style.display = 'none';
        });
    }
}

// Responsive sidebar toggle
function toggleSidebar() {
    const sidebar = document.querySelector(".sidebar");
    if (sidebar) sidebar.classList.toggle("open");
}

// Handle window resize for sidebar
window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar");
    if (window.innerWidth > 768 && sidebar) {
        sidebar.classList.remove("open");
    }
});


function initLogout() {
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutLink = document.getElementById('logoutLink'); 
    const logoutModal = document.getElementById('logoutModal');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');

    // Show logout modal
    const showLogoutModal = (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        if (logoutModal) {
            logoutModal.style.display = 'flex';
            logoutModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    };

    // Hide logout modal
    const hideLogoutModal = () => {
        if (logoutModal) {
            logoutModal.style.display = 'none';
            logoutModal.classList.remove('show');
            document.body.style.overflow = '';
        }
    };

    // Close logout modal function for inline onclick
    window.closeLogoutModal = hideLogoutModal;

    // Confirm logout function for inline onclick
    window.confirmLogout = () => {
        window.location.href = '../logout.php';
    };

    // Attach event listeners
    if (logoutBtn) logoutBtn.addEventListener('click', showLogoutModal);
    if (logoutLink) logoutLink.addEventListener('click', showLogoutModal);

    if (cancelLogout) {
        cancelLogout.addEventListener('click', hideLogoutModal);
    }

    if (confirmLogout) {
        confirmLogout.addEventListener('click', window.confirmLogout);
    }

    // Close when clicking outside modal
    if (logoutModal) {
        logoutModal.addEventListener('click', (e) => {
            if (e.target === logoutModal) {
                hideLogoutModal();
            }
        });
    }

    // Close with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && logoutModal.style.display === 'flex') {
            hideLogoutModal();
        }
    });
}

function showMessage(text, type = "info") {
    // Remove any existing messages first
    const existingMessages = document.querySelectorAll(".message");
    existingMessages.forEach(msg => msg.remove());

    // Create new message element
    const message = document.createElement("div");
    message.className = `message ${type}`;
    message.textContent = text;

    // Insert message in the appropriate location
    const mainContent = document.querySelector(".main-content");
    if (mainContent) {
        const header = mainContent.querySelector("header");
        if (header) {
            header.insertAdjacentElement("afterend", message);
        } else {
            mainContent.prepend(message);
        }
    }

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (message.parentNode) {
            message.remove();
        }
    }, 5000);
}

// Responsive sidebar toggle
function toggleSidebar() {
    const sidebar = document.querySelector(".sidebar");
    if (sidebar) sidebar.classList.toggle("open");
}

// Handle window resize for sidebar
window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar");
    if (window.innerWidth > 768 && sidebar) {
        sidebar.classList.remove("open");
    }
});

// ===============  Start of version 11 update =============== 

 // =============== Notification functionality ===============
document.addEventListener('DOMContentLoaded', function() {
    initializeNotificationSystem();
});

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

function markNotificationAsRead(notificationId, element) {
    fetch('student_feedback.php', {
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
    .catch(error => console.error('Error marking notification as read:', error));
}

function markAllNotificationsAsRead() {
    console.log('Marking all notifications as read...');
    
    fetch('student_feedback.php', {
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
