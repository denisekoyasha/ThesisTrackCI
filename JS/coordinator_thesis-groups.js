// coordinator_thesis-groups.js
document.addEventListener("DOMContentLoaded", function() {
    // Initialize components
    initializeTabs();
    initUserDropdown();
    initLogout();
    initializeNotifications();
    initGroupFilters();
    
    // Show welcome message
    showMessage("Welcome to Thesis Groups management!", "info");
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

function initGroupFilters() {
    // Initialize filter functionality
    const programFilter = document.getElementById('programFilter');
    const sectionFilter = document.getElementById('sectionFilter');
    const advisorFilter = document.getElementById('advisorFilter');
    const statusFilter = document.getElementById('statusFilter');

    if (programFilter) {
        programFilter.addEventListener('change', filterGroups);
    }
    if (sectionFilter) {
        sectionFilter.addEventListener('change', filterGroups);
    }
    if (advisorFilter) {
        advisorFilter.addEventListener('change', filterGroups);
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', filterGroups);
    }

    // Initialize search functionality
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    }
}

function filterGroups() {
    const programFilter = document.getElementById("programFilter")?.value || '';
    const sectionFilter = document.getElementById("sectionFilter")?.value || '';
    const advisorFilter = document.getElementById("advisorFilter")?.value || '';
    const statusFilter = document.getElementById("statusFilter")?.value || '';

    const rows = document.querySelectorAll("#groupsTable tbody tr");

    let visibleCount = 0;

    rows.forEach(row => {
        let show = true;

        if (programFilter && row.dataset.program !== programFilter) {
            show = false;
        }

        if (sectionFilter && row.dataset.section !== sectionFilter) {
            show = false;
        }

        if (advisorFilter && row.dataset.advisor !== advisorFilter) {
            show = false;
        }

        if (statusFilter && row.dataset.status !== statusFilter) {
            show = false;
        }

        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    // Show message if filters are applied
    const activeFilters = [programFilter, sectionFilter, advisorFilter, statusFilter].filter(Boolean);
    if (activeFilters.length > 0) {
        showMessage(`Showing ${visibleCount} groups with applied filters`, "info");
    }
}

function viewGroupDetails(groupId) {
    showMessage(`Loading details for group ${groupId}...`, "info");
    // In a real implementation, this would load group details
    setTimeout(() => {
        showMessage(`Group ${groupId} details loaded successfully!`, "success");
        // Redirect to group details page
        window.location.href = `coordinator_group-details.php?id=${groupId}`;
    }, 1000);
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

// Handle window resize for sidebar (if you have a toggle button)
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

    fetch('coordinator_thesis-groups.php', {
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

    fetch('coordinator_thesis-groups.php', {
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

    fetch('coordinator_thesis-groups.php', {
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

// Make functions globally available
window.viewGroupDetails = viewGroupDetails;
window.filterGroups = filterGroups;

// Initialize jQuery functionality when DOM is ready
$(document).ready(function() {
    // Initialize the table without DataTables features
    var table = $('#groupsTable');
    var tbody = table.find('tbody');
    var rows = tbody.find('tr');
    
    // Create no data row
    var noDataRow = $('<tr class="no-data-row"><td colspan="8" class="text-center">No data available in table</td></tr>');
    tbody.append(noDataRow);
    noDataRow.hide(); // Hide initially
    
    // Filter function for the dropdowns
    function filterGroupsJQuery() {
        var program = $('#programFilter').val().toLowerCase();
        var section = $('#sectionFilter').val().toLowerCase();
        var advisor = $('#advisorFilter').val().toLowerCase();
        var status = $('#statusFilter').val().toLowerCase();
        
        var visibleRows = 0;
        
        rows.each(function() {
            var row = $(this);
            var rowProgram = row.data('program')?.toString().toLowerCase() || '';
            var rowSection = row.data('section')?.toString().toLowerCase() || '';
            var rowAdvisor = row.data('advisor')?.toString().toLowerCase() || '';
            var rowStatus = row.data('status')?.toString().toLowerCase() || '';
            
            var showRow = true;
            
            if (program && !rowProgram.includes(program)) {
                showRow = false;
            }
            if (section && rowSection !== section) {
                showRow = false;
            }
            if (advisor && rowAdvisor !== advisor) {
                showRow = false;
            }
            if (status && rowStatus !== status) {
                showRow = false;
            }
            
            row.toggle(showRow);
            if (showRow) visibleRows++;
        });
        
        // Show/hide no data message
        if (visibleRows === 0) {
            noDataRow.show();
        } else {
            noDataRow.hide();
        }
    }
    
    // Apply filters when dropdowns change
    $('#programFilter, #sectionFilter, #advisorFilter, #statusFilter').on('change', filterGroupsJQuery);
    
    // Initial filter check
    filterGroupsJQuery();
});
