// Global variables
let currentStudentId = null;
let isEditMode = false;
let confirmCallback = null;

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
    initializeUI();
    setupEventListeners();
    showMessage("Welcome to CICT Student Management!", "info");
});

function initializeUI() {
    // Initialize user dropdown
    initUserDropdown();
    
    // Initialize notification system
    initNotificationSystem();
    
    // Setup logout handlers
    setupLogoutHandlers();
    
    // Setup confirmation modal
    setupConfirmationModal();
}

function setupEventListeners() {
    // Close dropdowns when clicking outside
    document.addEventListener("click", (e) => {
        if (!e.target.closest(".action-dropdown")) {
            closeAllActionDropdowns();
        }
        if (!e.target.closest(".user-info")) {
            closeUserDropdown();
        }
        if (!e.target.closest(".notification-dropdown")) {
            closeNotificationMenu();
        }
    });

    // Form submission
    const studentForm = document.getElementById("studentForm");
    if (studentForm) {
        studentForm.addEventListener("submit", (e) => {
            e.preventDefault();
            saveStudent();
        });
    }

    // Modal close events
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            closeStudentModal();
            closeCredentialsModal();
            hideLogoutModal();
            closeConfirmModal();
            closeImportModal();
            closeNotificationMenu();
        }
    });
}

function initUserDropdown() {
    const userAvatar = document.getElementById('userAvatar');
    const userDropdown = document.getElementById('userDropdown');
    const headerLogoutLink = document.getElementById('headerLogoutLink');
    
    if (userAvatar && userDropdown) {
        userAvatar.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleUserDropdown();
        });
        
        // Also make the entire user-info area clickable
        const userInfo = document.querySelector('.user-info');
        if (userInfo) {
            userInfo.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleUserDropdown();
            });
        }
    }
    
    if (headerLogoutLink) {
        headerLogoutLink.addEventListener('click', (e) => {
            e.preventDefault();
            showLogoutModal();
        });
    }
    
    // Close dropdown when clicking anywhere else
    document.addEventListener('click', () => {
        closeUserDropdown();
    });
}

function toggleUserDropdown() {
    const dropdown = document.getElementById("userDropdown");
    if (dropdown) {
        const isVisible = dropdown.style.display === "block";
        dropdown.style.display = isVisible ? "none" : "block";
        
        // Add animation class
        if (!isVisible) {
            dropdown.classList.add('dropdown-show');
        } else {
            dropdown.classList.remove('dropdown-show');
        }
    }
}

function closeUserDropdown() {
    const dropdown = document.getElementById("userDropdown");
    if (dropdown) {
        dropdown.style.display = "none";
        dropdown.classList.remove('dropdown-show');
    }
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
            badge.style.display = 'inline-block';
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

function setupLogoutHandlers() {
    // Get elements
    const logoutBtn = document.getElementById("logoutBtn");
    const logoutModal = document.getElementById("logoutModal");
    const confirmLogout = document.getElementById("confirmLogout");
    const cancelLogout = document.getElementById("cancelLogout");

    // Show modal function
    const showModal = (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        if (logoutModal) {
            logoutModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };

    // Hide modal function
    const hideModal = () => {
        if (logoutModal) {
            logoutModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    // Global functions for onclick attributes
    window.closeLogoutModal = hideModal;
    window.confirmLogout = () => {
        window.location.href = "../logout.php";
    };

    // Attach event listeners
    if (logoutBtn) {
        logoutBtn.addEventListener('click', showModal);
    }

    if (confirmLogout) {
        confirmLogout.addEventListener('click', window.confirmLogout);
    }

    if (cancelLogout) {
        cancelLogout.addEventListener('click', hideModal);
    }

    // Close modal when clicking outside
    if (logoutModal) {
        logoutModal.addEventListener('click', (e) => {
            if (e.target === logoutModal) {
                hideModal();
            }
        });
    }

    // Close with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && logoutModal && logoutModal.style.display === 'flex') {
            hideModal();
        }
    });
}

function showLogoutModal() {
    const modal = document.getElementById("logoutModal");
    if (modal) {
        modal.style.display = "flex";
        document.body.style.overflow = 'hidden';
    }
}

function hideLogoutModal() {
    const modal = document.getElementById("logoutModal");
    if (modal) {
        modal.style.display = "none";
        document.body.style.overflow = '';
    }
}

function setupConfirmationModal() {
    const confirmBtn = document.getElementById("confirmActionBtn");
    if (confirmBtn) {
        confirmBtn.addEventListener('click', handleConfirmAction);
    }
    
    const modal = document.getElementById("confirmModal");
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeConfirmModal();
            }
        });
    }
}

function showConfirmModal(title, message, callback) {
    const modal = document.getElementById("confirmModal");
    const titleElement = document.getElementById("confirmModalTitle");
    const messageElement = document.getElementById("confirmModalMessage");
    
    if (modal && titleElement && messageElement) {
        titleElement.textContent = title;
        messageElement.textContent = message;
        confirmCallback = callback;
        
        modal.style.display = "flex";
        document.body.style.overflow = 'hidden';
        
        setTimeout(() => {
            const confirmBtn = document.getElementById("confirmActionBtn");
            if (confirmBtn) confirmBtn.focus();
        }, 100);
    }
}

function closeConfirmModal() {
    const modal = document.getElementById("confirmModal");
    if (modal) {
        modal.style.display = "none";
        document.body.style.overflow = '';
        confirmCallback = null;
    }
}

function handleConfirmAction() {
    if (typeof confirmCallback === 'function') {
        confirmCallback();
    }
    closeConfirmModal();
}

function toggleActionDropdown(studentId) {
    const menu = document.getElementById("actionMenu" + studentId);
    const allMenus = document.querySelectorAll(".action-menu");

    // Close all other menus
    allMenus.forEach((m) => {
        if (m !== menu) {
            m.classList.remove("show");
        }
    });

    // Toggle current menu
    if (menu) {
        menu.classList.toggle("show");
        
        // Position adjustment if needed
        const menuRect = menu.getBoundingClientRect();
        const spaceBelow = window.innerHeight - menuRect.bottom;
        const spaceAbove = menuRect.top;

        if (spaceBelow < 120 && spaceAbove > 120) {
            menu.classList.add("flip-up");
        } else {
            menu.classList.remove("flip-up");
        }
    }
}

function closeAllActionDropdowns() {
    const allMenus = document.querySelectorAll(".action-menu");
    allMenus.forEach((menu) => {
        menu.classList.remove("show");
    });
}

function addNewStudent() {
    isEditMode = false;
    currentStudentId = null;

    // Reset form
    document.getElementById("studentForm").reset();
    document.getElementById("studentId").value = "";
    document.getElementById("studentModalTitle").textContent = "Add New Student";

    // Show modal
    showStudentModal();
}

function editStudent(studentId) {
    isEditMode = true;
    currentStudentId = studentId;

    // Find student data from table
    const row = document.querySelector(`button[onclick="toggleActionDropdown(${studentId})"]`).closest("tr");
    const cells = row.querySelectorAll("td");

    // Extract data from table cells
    const studentIdText = cells[0].textContent.trim();
    const fullName = cells[1].textContent.trim();
    const email = cells[2].textContent.trim();
    const section = cells[3].textContent.trim();

    // Parse name parts - handle cases where middle name might be present
    const nameParts = fullName.split(" ");
    
    let firstName = nameParts[0] || "";
    let lastName = nameParts[nameParts.length - 1] || "";
    let middleName = "";

    // If there are more than 2 parts, the middle parts are the middle name
    if (nameParts.length > 2) {
        middleName = nameParts.slice(1, -1).join(" ");
    }

    // Populate form
    document.getElementById("studentId").value = studentId;
    document.getElementById("firstName").value = firstName;
    document.getElementById("lastName").value = lastName;
    document.getElementById("middleName").value = middleName;
    document.getElementById("email").value = email;
    
    // Set the section dropdown value
    const sectionSelect = document.getElementById("section");
    if (sectionSelect) {
        sectionSelect.value = section;
    }
    
    document.getElementById("studentModalTitle").textContent = "Edit Student";

    // Show modal
    showStudentModal();

    // Close action dropdown
    closeAllActionDropdowns();
}

function deleteStudent(studentId) {
    showConfirmModal(
        "Delete Student", 
        "Are you sure you want to delete this student? This action cannot be undone.",
        () => {
            const formData = new FormData();
            formData.append("action", "delete_student");
            formData.append("student_id", studentId);

            fetch("advisor_student-management.php", {
                method: "POST",
                body: formData,
            })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    showMessage(data.message, "success");
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage(data.message, "error");
                }
            })
            .catch((error) => {
                console.error("Error:", error);
                showMessage("An error occurred while deleting the student.", "error");
            });
        }
    );

    // Close action dropdown
    closeAllActionDropdowns();
}

function saveStudent() {
    const form = document.getElementById("studentForm");
    const formData = new FormData(form);

    // Add action
    if (isEditMode) {
        formData.append("action", "edit_student");
    } else {
        formData.append("action", "add_student");
    }

    // Disable form during submission
    const submitBtn = document.querySelector(".modal-footer .btn-primary");
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;

    fetch("advisor_student-management.php", {
        method: "POST",
        body: formData,
    })
    .then((response) => response.json())
    .then((data) => {
        if (data.success) {
            showMessage(data.message, "success");
            closeStudentModal();

            if (!isEditMode && data.student_data) {
                // Show credentials modal for new students
                showCredentialsModal(data.student_data);
            } else {
                // Reload page for edits
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        } else {
            showMessage(data.message, "error");
        }
    })
    .catch((error) => {
        console.error("Error:", error);
        showMessage("An error occurred while saving the student.", "error");
    })
    .finally(() => {
        // Re-enable form
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function showStudentModal() {
    const modal = document.getElementById("studentModal");
    if (modal) {
        modal.style.display = "flex";
        document.body.style.overflow = 'hidden';
        // Focus on first input
        setTimeout(() => {
            const firstNameInput = document.getElementById("firstName");
            if (firstNameInput) firstNameInput.focus();
        }, 100);
    }
}

function closeStudentModal() {
    const modal = document.getElementById("studentModal");
    if (modal) {
        modal.style.display = "none";
        document.body.style.overflow = '';
    }
}

function showCredentialsModal(studentData) {
    // This function requires a credentials modal in your HTML
    // You might need to add this modal structure
    console.log("Show credentials for:", studentData);
    // For now, just reload the page
    setTimeout(() => {
        location.reload();
    }, 1000);
}

function closeCredentialsModal() {
    // Implementation depends on your credentials modal structure
    location.reload();
}

function showMessage(message, type = "info") {
    const container = document.getElementById("messageContainer");
    if (!container) return;

    const messageDiv = document.createElement("div");
    messageDiv.className = `message ${type}`;
    messageDiv.innerHTML = `
        <i class="fas ${getMessageIcon(type)}"></i>
        ${message}
    `;

    container.appendChild(messageDiv);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.parentNode.removeChild(messageDiv);
        }
    }, 5000);

    // Scroll to top to show message
    window.scrollTo({ top: 0, behavior: "smooth" });
}

function getMessageIcon(type) {
    switch (type) {
        case "success":
            return "fa-check-circle";
        case "error":
            return "fa-exclamation-circle";
        case "warning":
            return "fa-exclamation-triangle";
        default:
            return "fa-info-circle";
    }
}

// CSV Import/Export functions
function exportCSV(type) {
    window.location.href = '?action=export_csv&type=' + type;
}

function showImportModal() {
    document.getElementById('importModal').style.display = 'block';
    document.getElementById('importResults').style.display = 'none';
    document.getElementById('importForm').reset();
}

function closeImportModal() {
    document.getElementById('importModal').style.display = 'none';
}

function submitImport() {
    const fileInput = document.getElementById('csvFile');
    if (!fileInput.files.length) {
        alert('Please select a CSV file to import.');
        return;
    }

    // Show loading + disable button
    document.getElementById('importLoading').style.display = 'flex';
    document.getElementById('importButton').disabled = true;

    const formData = new FormData();
    formData.append('action', 'import_csv');
    formData.append('csv_file', fileInput.files[0]);

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('importLoading').style.display = 'none';
        document.getElementById('importButton').disabled = false;

        const resultsDiv = document.getElementById('importResults');
        const successDiv = document.getElementById('importSuccess');
        const errorsDiv = document.getElementById('importErrors');

        resultsDiv.style.display = 'block';

        if (data.success) {
            successDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message}`;
            successDiv.style.display = 'block';

            if (data.errors && data.errors.length > 0) {
                errorsDiv.innerHTML = '<strong>Errors:</strong><ul>' +
                    data.errors.map(error => `<li>${error}</li>`).join('') +
                    '</ul>';
                errorsDiv.style.display = 'block';
            } else {
                errorsDiv.style.display = 'none';
            }

            // Auto reload after success
            setTimeout(() => { location.reload(); }, 3000);
        } else {
            successDiv.style.display = 'none';
            errorsDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message}`;
            if (data.errors && data.errors.length > 0) {
                errorsDiv.innerHTML += '<ul>' +
                    data.errors.map(error => `<li>${error}</li>`).join('') +
                    '</ul>';
            }
            errorsDiv.style.display = 'block';
        }
    })
    .catch(error => {
        document.getElementById('importLoading').style.display = 'none';
        document.getElementById('importButton').disabled = false;
        console.error('Error:', error);
        alert('An error occurred during import.');
    });
}

// Export functions for global access
window.addNewStudent = addNewStudent;
window.editStudent = editStudent;
window.deleteStudent = deleteStudent;
window.saveStudent = saveStudent;
window.closeStudentModal = closeStudentModal;
window.closeCredentialsModal = closeCredentialsModal;
window.toggleActionDropdown = toggleActionDropdown;
window.closeConfirmModal = closeConfirmModal;
window.showConfirmModal = showConfirmModal;
window.exportCSV = exportCSV;
window.showImportModal = showImportModal;
window.closeImportModal = closeImportModal;
window.submitImport = submitImport;
