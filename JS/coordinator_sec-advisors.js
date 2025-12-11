// coordinator_sec-advisors.js 
document.addEventListener("DOMContentLoaded", function() {
    // Initialize components
    initializeTabs();
    initUserDropdown();
    initLogout();
    initializeNotifications();
    
    // Show welcome message
    showMessage("Welcome to CICT Sections & Advisors management!", "info");

    // Initialize DataTable functionality
    initDataTable();
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

        document.addEventListener('click', (e) => {
            if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.style.display = 'none';
            }
        });
    }
}

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

    // Close modal when clicking outside
    logoutModal.addEventListener('click', (e) => {
        if (e.target === logoutModal) {
            hideLogoutModal();
        }
    });

    // Close modals when pressing Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
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
    setTimeout(() => message.remove(), 5000);
}

function viewSectionDetails(sectionId) {
    showMessage(`Loading details for ${sectionId}...`, "info");
    // In a real implementation, this would load section details
    setTimeout(() => {
        showMessage(`${sectionId} details loaded successfully!`, "success");
        // Here you would typically redirect or show a modal with the details
        // window.location.href = `section-details.php?id=${sectionId}`;
    }, 1000);
}

// Handle window resize for sidebar (if you have a toggle button)
window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar");
    if (window.innerWidth > 768 && sidebar) {
        sidebar.classList.remove("open");
    }
});

// ==================== DATA TABLE FUNCTIONALITY ====================
function initDataTable() {
    const table = document.getElementById("sectionsTable");
    if (!table) return;

    const searchInput = document.querySelector(".modern-search .search-input");
    const headers = table.querySelectorAll("thead th");
    let sortOrder = {}; // track order per column

    // ===== SEARCH FILTER =====
    if (searchInput) {
        searchInput.addEventListener("keyup", function () {
            const filter = this.value.toLowerCase();
            const rows = table.querySelectorAll("tbody tr");

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        });
    }

    // ===== SORTING WITH CARET =====
    headers.forEach((header, index) => {
        // skip actions column
        if (header.innerText.trim() === "Actions") return;

        const caret = header.querySelector("i");
        if (!caret) return;

        header.style.cursor = "pointer";

        header.addEventListener("click", () => {
            const rows = Array.from(table.querySelectorAll("tbody tr"));
            const ascending = !sortOrder[index];
            sortOrder[index] = ascending;

            // reset all carets
            headers.forEach(h => {
                const i = h.querySelector("i");
                if (i) i.className = "fas fa-sort neutral-arrow";
            });

            // set active caret
            caret.className = ascending ? "fas fa-caret-up active-arrow" : "fas fa-caret-down active-arrow";

            rows.sort((a, b) => {
                let valA = a.children[index].innerText.trim().toLowerCase();
                let valB = b.children[index].innerText.trim().toLowerCase();

                // numeric sort if number
                if (!isNaN(valA) && !isNaN(valB)) {
                    valA = Number(valA);
                    valB = Number(valB);
                }

                if (valA < valB) return ascending ? -1 : 1;
                if (valA > valB) return ascending ? 1 : -1;
                return 0;
            });

            const tbody = table.querySelector("tbody");
            rows.forEach(r => tbody.appendChild(r));
        });
    });
}

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

    fetch('coordinator_sec-advisors.php', {
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

    fetch('coordinator_sec-advisors.php', {
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

    fetch('coordinator_sec-advisors.php', {
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

// Make viewSectionDetails globally available
window.viewSectionDetails = viewSectionDetails;
