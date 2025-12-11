// student_kanban-progress.js 
document.addEventListener("DOMContentLoaded", function() {
    // Initialize tab system
    initTabSystem();
    
    // Initialize user interface components
    initUI();
    
    // Initialize kanban functionality
    initKanbanDragDrop();
    
    // Show welcome message
    showMessage("Viewing your chapter progress", "info");
});

function initTabSystem() {
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    const currentPage = window.location.pathname.split('/').pop();
    
    navItems.forEach(item => {
        item.addEventListener("click", function(e) {
            const tabId = this.getAttribute("data-tab");
            const targetContent = document.getElementById(tabId);
            
            // If this is a link to another page
            if (!targetContent) {
                return; // Allow default navigation
            }
            
            // If this is a tab on current page
            e.preventDefault();
            
            // Remove active classes from all
            document.querySelectorAll(".nav-item").forEach(nav => nav.classList.remove("active"));
            document.querySelectorAll(".tab-content").forEach(tab => tab.classList.remove("active"));
            
            // Add active class to clicked tab
            this.classList.add("active");
            targetContent.classList.add("active");
        });
        
        // Set initial active tab based on current page
        const href = item.getAttribute("href");
        if (href && href.includes(currentPage)) {
            item.classList.add("active");
            const tabId = item.getAttribute("data-tab");
            if (tabId) {
                const tabElement = document.getElementById(tabId);
                if (tabElement) {
                    tabElement.classList.add("active");
                }
            }
        }
    });
}

function initUI() {
    // User dropdown toggle
    const userAvatar = document.getElementById('userAvatar');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userAvatar && userDropdown) {
        userAvatar.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent event bubbling
            userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.style.display = 'none';
            }
        });
    }
    
    // Logout functionality
    const logoutBtn = document.getElementById("logoutBtn");
    const logoutLink = document.getElementById("logoutLink");
    const logoutModal = document.getElementById("logoutModal");

    if (logoutModal) {
        const showModal = (e) => {
            if (e) e.preventDefault();
            logoutModal.classList.add("show");
            // Add focus trap for accessibility
            logoutModal.focus();
        };

        const hideModal = () => {
            logoutModal.classList.remove("show");
        };

        // Add event listeners for logout triggers
        if (logoutBtn) {
            logoutBtn.addEventListener("click", showModal);
        }
        
        if (logoutLink) {
            logoutLink.addEventListener("click", showModal);
        }

        // Close modal when clicking outside
        logoutModal.addEventListener("click", (e) => {
            if (e.target === logoutModal) {
                hideModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && logoutModal.classList.contains("show")) {
                hideModal();
            }
        });

        // Make functions globally available
        window.closeLogoutModal = hideModal;
        window.confirmLogout = () => {
            window.location.href = "../logout.php";
        };
    } else {
        console.warn("Logout modal not found");
    }
}

function initKanbanDragDrop() {
    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-column');
    
    // Check if kanban elements exist
    if (cards.length === 0 || columns.length === 0) {
        console.info("Kanban elements not found - drag/drop not initialized");
        return;
    }
    
    cards.forEach(card => {
        card.setAttribute('draggable', 'true');
        
        card.addEventListener('dragstart', (e) => {
            card.classList.add('dragging');
            // Store the card data for accessibility
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', card.outerHTML);
        });
        
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
        });
    });
    
    columns.forEach(column => {
        column.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });
        
        column.addEventListener('drop', (e) => {
            e.preventDefault();
            const draggingCard = document.querySelector('.dragging');
            const kanbanCards = column.querySelector('.kanban-cards');
            
            if (draggingCard && kanbanCards) {
                kanbanCards.appendChild(draggingCard);
                // Optional: Save the new position to localStorage or send to server
                saveKanbanState();
            }
        });
        
        // Add visual feedback during drag
        column.addEventListener('dragenter', (e) => {
            e.preventDefault();
            column.classList.add('drag-over');
        });
        
        column.addEventListener('dragleave', (e) => {
            if (!column.contains(e.relatedTarget)) {
                column.classList.remove('drag-over');
            }
        });
    });
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


function saveKanbanState() {
    // Optional: Save kanban state to localStorage or send to server
    // This is a placeholder function - implement based on your needs
    console.info("Kanban state changed - implement saveKanbanState() to persist changes");
}

function showMessage(text, type = "info") {
    // Remove existing messages
    document.querySelectorAll(".message").forEach(msg => msg.remove());
    
    // Create new message
    const message = document.createElement("div");
    message.className = `message ${type}`;
    message.textContent = text;
    
    // Add ARIA attributes for accessibility
    message.setAttribute("role", type === "error" ? "alert" : "status");
    message.setAttribute("aria-live", "polite");
    
    // Insert message
    const mainContent = document.querySelector(".main-content");
    if (mainContent) {
        const header = mainContent.querySelector("header");
        if (header) {
            header.insertAdjacentElement("afterend", message);
        } else {
            mainContent.prepend(message);
        }
    } else {
        // Fallback: append to body if main-content not found
        document.body.prepend(message);
    }
    
    // Auto-remove after 5 seconds with fade effect
    setTimeout(() => {
        message.style.opacity = "0";
        message.style.transition = "opacity 0.3s ease";
        setTimeout(() => message.remove(), 300);
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

function toggleSidebar() {
    const sidebar = document.querySelector(".sidebar");
    if (sidebar) {
        sidebar.classList.toggle("open");
        
        // Update ARIA attributes for accessibility
        const isOpen = sidebar.classList.contains("open");
        sidebar.setAttribute("aria-expanded", isOpen.toString());
    }
}

// Responsive sidebar handling
window.addEventListener("resize", function() {
    const sidebar = document.querySelector(".sidebar");
    if (sidebar && window.innerWidth > 768) {
        sidebar.classList.remove("open");
        sidebar.setAttribute("aria-expanded", "false");
    }
});

// Add error handling for uncaught errors
window.addEventListener("error", function(e) {
    console.error("JavaScript error:", e.error);
    showMessage("An error occurred. Please refresh the page.", "error");
});

// Add unhandled promise rejection handling
window.addEventListener("unhandledrejection", function(e) {
    console.error("Unhandled promise rejection:", e.reason);
    showMessage("An error occurred. Please try again.", "error");
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
    
    fetch('student_kanban-progress.php', {
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
            
            // Success - no message shown
        } else {
            throw new Error(data.message || 'Failed to mark all as read');
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
        // Error - no message shown
    });
}

function markNotificationAsRead(notificationId, element) {
    fetch('student_kanban-progress.php', {
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

function refreshNotifications() {
    fetch('student_kanban-progress.php', {
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
