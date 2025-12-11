// advisor_group.js 
document.addEventListener("DOMContentLoaded", function() {
    // Check if user is logged in
    if (!isUserLoggedIn()) {
        window.location.href = 'login.php';
        return;
    }

    // Initialize all components
    activateCurrentTab();
    initNavigation();
    initUI();
    initModals();
    initNotifications();
    
    // Show welcome message
    showMessage("Viewing your supervised groups.", "info");
});

function isUserLoggedIn() {
    // In real implementation, this would check with server
    return true;
}

function activateCurrentTab() {
    const currentPage = window.location.pathname.split('/').pop();
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    
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
}

function initNavigation() {
    const navItems = document.querySelectorAll(".nav-item[data-tab]");
    
    navItems.forEach(item => {
        item.addEventListener("click", function(e) {
            const tabId = this.getAttribute("data-tab");
            const tabContent = document.getElementById(tabId);
            const href = this.getAttribute("href");
            
            if (tabContent) {
                e.preventDefault();
                document.querySelectorAll(".nav-item").forEach(nav => nav.classList.remove("active"));
                document.querySelectorAll(".tab-content").forEach(tab => tab.classList.remove("active"));
                this.classList.add("active");
                tabContent.classList.add("active");
            }
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
    
    // View Details functionality
    document.querySelectorAll('.btn-expand').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent the click from bubbling up
            
            // Get group ID from onclick attribute
            const groupId = this.getAttribute('onclick').match(/'([^']+)'/)[1];
            
            // Get the details element
            const details = document.getElementById(`${groupId}-details`);
            
            if (details) {
                // Toggle the expanded class
                const isExpanded = details.classList.toggle('expanded');
                
                // Update button text
                this.textContent = isExpanded ? 'Hide Details' : 'View Details';
                
                // Scroll to show the expanded content if needed
                if (isExpanded) {
                    details.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        });
    });
    
    // Add click handlers for chapter items to prevent event bubbling
    document.querySelectorAll('.clickable-chapter').forEach(chapter => {
        chapter.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent click from reaching parent elements
            window.location.href = 'advisor_reviews.php';
        });
    });
}

function initModals() {
    // Logout functionality
    const logoutBtn = document.getElementById("logoutBtn");
    const logoutLink = document.getElementById("logoutLink");
    const logoutModal = document.getElementById("logoutModal");

    if (logoutModal) {
        const showModal = (e) => {
            if (e) e.preventDefault();
            logoutModal.classList.add("show");
        };

        const hideModal = () => {
            logoutModal.classList.remove("show");
        };

        if (logoutBtn) logoutBtn.addEventListener("click", showModal);
        if (logoutLink) logoutLink.addEventListener("click", showModal);

        // Close modal when clicking outside
        logoutModal.addEventListener("click", (e) => {
            if (e.target === logoutModal) {
                hideModal();
            }
        });

        // Make closeLogoutModal and confirmLogout globally available
        window.closeLogoutModal = hideModal;
        window.confirmLogout = () => {
            window.location.href = "../logout.php";
        };
    }
}

function initNotifications() {
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
}

function markNotificationAsRead(notificationId, element) {
    fetch('advisor_group.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_notification_read&notification_id=' + notificationId
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
    fetch('advisor_group.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_notifications_read'
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

function showMessage(text, type = "info") {
    const existingMessages = document.querySelectorAll(".message");
    existingMessages.forEach(msg => msg.remove());
    
    const message = document.createElement("div");
    message.className = `message ${type}`;
    message.textContent = text;
    
    const mainContent = document.querySelector(".main-content");
    if (mainContent) {
        const header = mainContent.querySelector("header");
        if (header) {
            header.insertAdjacentElement("afterend", message);
        } else {
            mainContent.prepend(message);
        }
    }
    
    setTimeout(() => message.remove(), 5000);
}

// Modal functions
function openReviewModal(groupId, chapterId) {
    const modal = document.getElementById('reviewModal');
    if (modal) {
        document.getElementById('reviewTitle').textContent = `Review ${chapterId} for ${groupId}`;
        modal.style.display = 'flex';
    }
}

function closeModal() {
    const modal = document.getElementById('reviewModal');
    if (modal) modal.style.display = 'none';
}

function submitReview() {
    const score = document.getElementById('scoreInput').value;
    const status = document.getElementById('statusSelect').value;
    const feedback = document.getElementById('feedbackText').value;
    
    if (!score || score < 0 || score > 100) {
        showMessage('Please enter a valid score between 0 and 100', 'error');
        return;
    }
    
    if (!feedback) {
        showMessage('Please provide feedback', 'error');
        return;
    }
    
    showMessage('Review submitted successfully!', 'success');
    closeModal();
}

// Group management functions
function reviewChapter(groupId, chapterId, chapterDbId) {
    currentChapterId = chapterDbId;
    const modal = document.getElementById('reviewModal');
    const title = document.getElementById('reviewTitle');

    if (modal && title) {
        title.textContent = `Review ${groupId} - ${chapterId}`;
        document.getElementById('chapterId').value = chapterDbId;
        modal.style.display = 'flex';
    }
}

function viewChapterFile(groupId, chapterId) {
    showMessage(`Opening ${chapterId} file for ${groupId}`, "info");
}

function editFeedback(groupId, chapterId, chapterDbId) {
    // For now, just open the review modal
    reviewChapter(groupId, chapterId, chapterDbId);
}

// Enhanced review functionality
let currentChapterId = null;

function submitReview() {
    const form = document.getElementById('reviewForm');
    const formData = new FormData(form);

    // Add action parameter
    formData.append('action', 'submit_review');

    const score = document.getElementById('scoreInput').value;
    const feedback = document.getElementById('feedbackText').value;

    if (!score || score < 0 || score > 100) {
        showMessage('Please enter a valid score between 0 and 100', 'error');
        return;
    }

    if (!feedback.trim()) {
        showMessage('Please provide feedback', 'error');
        return;
    }

    // Submit the review
    fetch('advisor_group.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            closeModal();
            // Reload the page to reflect changes
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error submitting review', 'error');
    });
}

function closeModal() {
    const modal = document.getElementById('reviewModal');
    if (modal) {
        modal.style.display = 'none';
        // Reset form
        document.getElementById('reviewForm').reset();
        currentChapterId = null;
    }
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('reviewModal');
    if (event.target === modal) {
        closeModal();
    }
});

// Responsive sidebar
window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar");
    if (window.innerWidth > 768) {
        sidebar.classList.remove("open");
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('.search-input');
    const table = document.getElementById('groupsTable');
    const rows = table ? table.querySelectorAll('tbody tr') : [];
    
    // Function to perform search
    function performSearch() {
        const searchTerm = searchInput.value.toLowerCase();
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            let rowMatches = false;
            
            // Skip the first column (Group ID) and last column (Actions)
            for (let i = 1; i < cells.length - 1; i++) {
                const cellText = cells[i].textContent.toLowerCase();
                if (cellText.includes(searchTerm)) {
                    rowMatches = true;
                    break;
                }
            }
            
            row.style.display = rowMatches ? '' : 'none';
        });
    }
    
    // Event listener for input changes
    if (searchInput) {
        searchInput.addEventListener('input', performSearch);
        
        // If there's an initial search term, filter immediately
        if (searchInput.value) {
            performSearch();
        }
    }
});
