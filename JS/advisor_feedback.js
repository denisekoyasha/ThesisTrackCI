// advisor_feedback.js - Complete fixed version
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

function initLogout() {
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutLink = document.getElementById('logoutLink'); 
    const logoutModal = document.getElementById('logoutModal'); // Specific logout modal
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');

    // Show only the logout modal
    const showLogoutModal = (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // First hide all other modals
        document.querySelectorAll('.modal').forEach(m => {
            if (m !== logoutModal) m.style.display = 'none';
        });
        
        // Then show the logout modal
        if (logoutModal) {
            logoutModal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }
    };

    const hideLogoutModal = () => {
        if (logoutModal) {
            logoutModal.style.display = 'none';
            document.body.style.overflow = ''; // Restore scrolling
        }
    };

    // Attach event listeners
    if (logoutBtn) logoutBtn.addEventListener('click', showLogoutModal);
    if (logoutLink) logoutLink.addEventListener('click', showLogoutModal);

    if (cancelLogout) {
        cancelLogout.addEventListener('click', hideLogoutModal);
    }

    if (confirmLogout) {
        confirmLogout.addEventListener('click', () => {
            window.location.href = '../logout.php';
        });
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

function editFeedback(commentId) {
    const btn = document.getElementById('editBtn-' + commentId);
    const existing = btn ? btn.getAttribute('data-comment') : '';

    // Populate modal
    const commentInput = document.getElementById('commentId');
    const feedbackText = document.getElementById('editFeedbackText');
    if (commentInput) commentInput.value = commentId;
    if (feedbackText) feedbackText.value = existing || '';

    // Open modal
    const modal = document.getElementById('editModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function submitEdit() {
    console.log('submitEdit called');
    const form = document.getElementById('editFeedbackForm');
    if (!form) { console.error('editFeedbackForm not found'); return; }

    // Prevent multiple simultaneous submissions
    if (form.dataset.submitting === '1') { console.warn('Already submitting'); return; }
    form.dataset.submitting = '1';

    const submitBtn = form.querySelector('.btn-primary');
    if (submitBtn) submitBtn.disabled = true;

    const formData = new FormData(form);

    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.json())
    .then(data => {
        console.log('process_feedback_edit response', data);
        if (data.success) {
            showMessage('Feedback updated successfully', 'success');
            closeModal();
            setTimeout(() => window.location.reload(), 800);
        } else {
            showMessage(data.message || 'Error updating feedback', 'error');
        }
    })
    .catch(err => {
        console.error('submitEdit fetch error', err);
        showMessage('Network error while updating feedback', 'error');
    })
    .finally(() => {
        form.dataset.submitting = '0';
        if (submitBtn) submitBtn.disabled = false;
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
    setTimeout(() => message.remove(), 5000);
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

// Modal functions (for the review modal that might be used for editing)
function closeModal() {
    const modals = document.querySelectorAll(".modal");
    modals.forEach(modal => modal.style.display = 'none');
}

function submitReview() {
    const score = document.getElementById("scoreInput").value;
    const feedback = document.getElementById("feedbackText").value;

    if (!score || !feedback) {
        showMessage("Please fill in all required fields.", "error");
        return;
    }

    if (score < 0 || score > 100) {
        showMessage("Score must be between 0 and 100", "error");
        return;
    }

    // In a real app, this would save the edited feedback
    showMessage("Feedback updated successfully!", "success");
    closeModal();
}


 // Notification functionality
        function initNotificationSystem() {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationMenu = document.getElementById('notificationMenu');
            const markAllReadBtn = document.getElementById('markAllRead');
            const notificationList = document.getElementById('notificationList');
            
            if (notificationBtn && notificationMenu) {
                // Toggle notification dropdown
                notificationBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggleNotificationMenu();
                });
                
                // Mark all as read
                if (markAllReadBtn) {
                    markAllReadBtn.addEventListener('click', markAllAsRead);
                }
                
                // Close notification menu when clicking outside
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.notification-dropdown')) {
                        closeNotificationMenu();
                    }
                });
                
                // Handle notification item clicks
                if (notificationList) {
                    notificationList.addEventListener('click', (e) => {
                        const notificationItem = e.target.closest('.notification-item');
                        if (notificationItem && notificationItem.classList.contains('unread')) {
                            const notificationId = notificationItem.dataset.id;
                            markAsRead(notificationId);
                        }
                    });
                }
            }
        }

        function toggleNotificationMenu() {
            const menu = document.getElementById('notificationMenu');
            if (menu) {
                const isVisible = menu.style.display === 'block';
                menu.style.display = isVisible ? 'none' : 'block';
                
                if (!isVisible) {
                    // Refresh notifications when opening
                    refreshNotifications();
                }
            }
        }

        function closeNotificationMenu() {
            const menu = document.getElementById('notificationMenu');
            if (menu) {
                menu.style.display = 'none';
            }
        }

        function markAsRead(notificationId) {
            const formData = new FormData();
            formData.append('notification_action', 'mark_as_read');
            formData.append('notification_id', notificationId);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                    if (notificationItem) {
                        notificationItem.classList.remove('unread');
                    }
                    updateNotificationBadge();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function markAllAsRead() {
            const formData = new FormData();
            formData.append('notification_action', 'mark_all_read');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
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

        function updateNotificationBadge() {
            const badge = document.getElementById('notificationBadge');
            const unreadCount = document.querySelectorAll('.notification-item.unread').length;
            
            if (badge) {
                if (unreadCount > 0) {
                    badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                    // badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        function refreshNotifications() {
            const formData = new FormData();
            formData.append('notification_action', 'get_notifications');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationList(data.notifications);
                    updateNotificationBadge();
                }
            })
            .catch(error => console.error('Error refreshing notifications:', error));
        }

        function updateNotificationList(notifications) {
            const notificationList = document.getElementById('notificationList');
            if (!notificationList) return;
            
            if (notifications.length === 0) {
                notificationList.innerHTML = `
                    <div class="no-notifications">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications</p>
                    </div>
                `;
                return;
            }
            
            notificationList.innerHTML = notifications.map(notification => `
                <div class="notification-item ${notification.is_read ? '' : 'unread'}" 
                     data-id="${notification.id}">
                    <span class="notification-type type-${notification.type || 'info'}">
                        ${(notification.type || 'info').charAt(0).toUpperCase() + (notification.type || 'info').slice(1)}
                    </span>
                    <div class="notification-title">
                        ${escapeHtml(notification.title)}
                    </div>
                    <div class="notification-message">
                        ${escapeHtml(notification.message)}
                    </div>
                    <div class="notification-time">
                        ${formatTime(notification.created_at)}
                    </div>
                </div>
            `).join('');
        }

        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            if (diffDays < 7) return `${diffDays}d ago`;
            
            return date.toLocaleDateString();
        }

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Initialize notification system when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initNotificationSystem();
        });
