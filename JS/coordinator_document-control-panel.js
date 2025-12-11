// Document Control Panel JavaScript - Fixed Version

// Global state management
// Global state management with specific margins
let currentFormatting = {
    fontStyle: 'Times New Roman',
    fontSize: 12,
    alignment: 'justify',
    margins: { 
        top: 0.89,     // 0.89 inch - slightly less than standard
        bottom: 0.19,  // 0.19 inch - very narrow
        left: 1.00,    // 1.00 inch - standard
        right: 1.00    // 1.00 inch - standard
    },
    indent: 0.5,
    border: false,
    logoPosition: 'none',
    lineSpacing: 1.5
};

// Initialize on page load
document.addEventListener("DOMContentLoaded", () => {
    initializeEventListeners();
    loadInitialData();
});

async function loadInitialData() {
    try {
        console.log('🔄 Loading initial data...');
        await loadFormatConfig();
        setupSectionToggles();
        console.log('✅ All data loaded successfully');
    } catch (error) {
        console.error('❌ Error loading initial data:', error);
        showMessage('Error loading configuration data', 'error');
    }
}

async function loadFormatConfig() {
    try {
        console.log('📥 Loading format configuration...');
        const response = await fetch('load_format_config.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        console.log('📄 Raw response:', text);
        
        const result = JSON.parse(text);

        if (!result.success) {
            console.warn('No saved configuration found, using defaults');
            return;
        }

        console.log('📊 Loaded configuration:', result.data);

        // Update UI based on loaded configuration
        if (result.data && typeof result.data === 'object') {
            Object.entries(result.data).forEach(([chapter, settings]) => {
                updateChapterUI(chapter, settings);
            });

            // Update formatting controls with first chapter's settings
            updateFormattingControls(result.data);
        }

    } catch (err) {
        console.error('Error loading format config:', err);
        showMessage('Error loading saved configuration: ' + err.message, 'error');
    }
}

function updateChapterUI(chapter, settings) {
    const chapterNumber = chapter.replace('Chapter ', '');
    const chapterCard = document.querySelector(`.chapter-card[data-chapter="${chapterNumber}"]`);
    
    if (!chapterCard) {
        console.warn(`Chapter card not found for: ${chapter}`);
        return;
    }

    // Update section toggles based on active_sections
    if (settings.active_sections && Array.isArray(settings.active_sections)) {
        chapterCard.querySelectorAll('.section-item').forEach(sectionItem => {
            const sectionName = sectionItem.querySelector('.section-name').textContent.trim();
            const checkbox = sectionItem.querySelector('.section-checkbox');
            const isActive = settings.active_sections.includes(sectionName);
            
            checkbox.checked = isActive;
            updateSectionStatus(sectionItem, isActive);
        });
    }
}

function updateFormattingControls(configData) {
    if (!configData || Object.keys(configData).length === 0) {
        console.log('No configuration data found, using defaults');
        return;
    }

    // Use first available chapter's settings
    const firstChapter = Object.values(configData)[0];
    if (!firstChapter) return;

    console.log('🎛️ Updating formatting controls with:', firstChapter);

    // Update formatting controls
    if (firstChapter.font_family) {
        const fontSelect = document.getElementById("fontStyle");
        if (fontSelect) {
            fontSelect.value = firstChapter.font_family;
            currentFormatting.fontStyle = firstChapter.font_family;
        }
    }

    if (firstChapter.font_size) {
        const sizeSelect = document.getElementById("fontSize");
        if (sizeSelect) {
            sizeSelect.value = firstChapter.font_size;
            currentFormatting.fontSize = firstChapter.font_size;
        }
    }

    // Update alignment
    if (firstChapter.alignment) {
        const alignment = firstChapter.alignment;
        document.querySelectorAll(".align-btn").forEach(btn => {
            if (btn.dataset.align === alignment) {
                btn.classList.add("active");
            } else {
                btn.classList.remove("active");
            }
        });
        currentFormatting.alignment = alignment;
    }

    // Update margins
    if (firstChapter.margins) {
        const marginTop = document.getElementById("marginTop");
        const marginBottom = document.getElementById("marginBottom");
        const marginLeft = document.getElementById("marginLeft");
        const marginRight = document.getElementById("marginRight");
        
        if (marginTop) marginTop.value = firstChapter.margins.top || 1;
        if (marginBottom) marginBottom.value = firstChapter.margins.bottom || 1;
        if (marginLeft) marginLeft.value = firstChapter.margins.left || 1;
        if (marginRight) marginRight.value = firstChapter.margins.right || 1;
        
        currentFormatting.margins = {
            top: firstChapter.margins.top || 1,
            bottom: firstChapter.margins.bottom || 1,
            left: firstChapter.margins.left || 1,
            right: firstChapter.margins.right || 1
        };
    }

    // Update other controls
    if (firstChapter.indentation !== undefined) {
        const indentInput = document.getElementById("indent");
        if (indentInput) {
            indentInput.value = firstChapter.indentation;
            currentFormatting.indent = firstChapter.indentation;
        }
    }

    if (firstChapter.border_style) {
        const borderToggle = document.getElementById("borderToggle");
        if (borderToggle) {
            borderToggle.checked = firstChapter.border_style === 'solid';
            currentFormatting.border = firstChapter.border_style === 'solid';
        }
    }

    if (firstChapter.logo_position) {
        const logoSelect = document.getElementById("logoPosition");
        if (logoSelect) {
            logoSelect.value = firstChapter.logo_position;
            currentFormatting.logoPosition = firstChapter.logo_position;
        }
    }

    if (firstChapter.line_spacing) {
        currentFormatting.lineSpacing = firstChapter.line_spacing;
    }

    console.log('✅ Formatting controls updated:', currentFormatting);
}

function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('open');
  document.body.classList.toggle('sidebar-open');
}

function setupSectionToggles() {
        document.querySelectorAll('.section-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', async (e) => {
                const sectionItem = e.target.closest('.section-item');
                const chapterCard = sectionItem.closest('.chapter-card');
                const chapterNumber = chapterCard.dataset.chapter;
                const isEnabled = e.target.checked;

                updateSectionStatus(sectionItem, isEnabled);
                
                // Save changes immediately
                await saveChapterSections(`Chapter ${chapterNumber}`);
            });
        });

        // Chapter expand/collapse
        document.querySelectorAll('.chapter-header').forEach(header => {
            header.addEventListener('click', function() {
                const chapterCard = this.closest('.chapter-card');
                chapterCard.classList.toggle('expanded');
            });
        });

        // Add scroll detection for mobile scroll indicator
        if (window.innerWidth <= 768) {
            document.querySelectorAll('.chapter-content').forEach(content => {
                content.addEventListener('scroll', function() {
                    const isScrolledToBottom = this.scrollHeight - this.scrollTop <= this.clientHeight + 5;
                    if (isScrolledToBottom) {
                        this.classList.add('scrolled-bottom');
                    } else {
                        this.classList.remove('scrolled-bottom');
                    }
                });
            });
        }
    }

function setupSectionToggles() {
    document.querySelectorAll('.section-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', async (e) => {
            const sectionItem = e.target.closest('.section-item');
            const chapterCard = sectionItem.closest('.chapter-card');
            const chapterNumber = chapterCard.dataset.chapter;
            const isEnabled = e.target.checked;

            updateSectionStatus(sectionItem, isEnabled);
            
            // Save changes immediately
            await saveChapterSections(`Chapter ${chapterNumber}`);
        });
    });
}

function updateSectionStatus(sectionItem, isEnabled) {
    const statusBadge = sectionItem.querySelector('.section-status');
    
    if (isEnabled) {
        statusBadge.textContent = "Enabled";
        statusBadge.classList.remove("disabled");
        statusBadge.classList.add("enabled");
        sectionItem.classList.remove("disabled");
    } else {
        statusBadge.textContent = "Disabled";
        statusBadge.classList.remove("enabled");
        statusBadge.classList.add("disabled");
        sectionItem.classList.add("disabled");
    }
}

async function saveChapterSections(chapter) {
    const chapterCard = document.querySelector(`.chapter-card[data-chapter="${chapter.replace('Chapter ', '')}"]`);
    if (!chapterCard) {
        console.error('Chapter card not found:', chapter);
        return false;
    }

    const activeSections = [];
    
    chapterCard.querySelectorAll('.section-item').forEach(item => {
        const name = item.querySelector('.section-name').textContent.trim();
        const checkbox = item.querySelector('.section-checkbox');
        
        if (checkbox.checked) {
            activeSections.push(name);
        }
    });

    const payload = {
        chapter: chapter,
        active_sections: activeSections,
        font_family: currentFormatting.fontStyle,
        font_size: parseInt(currentFormatting.fontSize),
        alignment: currentFormatting.alignment,
        line_spacing: parseFloat(currentFormatting.lineSpacing),
        indentation: parseFloat(currentFormatting.indent),
        border_style: currentFormatting.border ? 'solid' : 'none',
        logo_position: currentFormatting.logoPosition,
        margin_top: parseFloat(currentFormatting.margins.top),
        margin_bottom: parseFloat(currentFormatting.margins.bottom),
        margin_left: parseFloat(currentFormatting.margins.left),
        margin_right: parseFloat(currentFormatting.margins.right)
    };

    console.log('💾 Saving payload for', chapter, ':', payload);

    try {
        const response = await fetch('save_format_config.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'Unknown server error');
        }
        
        console.log(`✅ Saved ${chapter} configuration`);
        return true;
    } catch (err) {
        console.error('❌ Save failed for', chapter, ':', err);
        showMessage(`Error saving ${chapter}: ${err.message}`, 'error');
        return false;
    }
}

// Formatting functions
function updateFormatting(type, value) {
    switch (type) {
        case 'fontStyle':
            currentFormatting.fontStyle = value;
            break;
        case 'fontSize':
            currentFormatting.fontSize = value;
            break;
        case 'alignment':
            currentFormatting.alignment = value;
            document.querySelectorAll(".align-btn").forEach(btn => {
                btn.classList.toggle("active", btn.dataset.align === value);
            });
            break;
        case 'margins':
            currentFormatting.margins = {
                top: document.getElementById("marginTop").value,
                bottom: document.getElementById("marginBottom").value,
                left: document.getElementById("marginLeft").value,
                right: document.getElementById("marginRight").value
            };
            break;
        case 'indent':
            currentFormatting.indent = value;
            break;
        case 'border':
            currentFormatting.border = document.getElementById("borderToggle").checked;
            break;
        case 'logoPosition':
            currentFormatting.logoPosition = value;
            break;
    }
    
    console.log('📝 Formatting updated:', type, value, currentFormatting);
}

async function applyAllFormatting() {
    showConfirmation(
        "Apply Formatting?",
        "This will apply the selected formatting settings to all chapters. Continue?",
        "info",
        async () => {
            const chapters = document.querySelectorAll('.chapter-card');
            let successCount = 0;
            let errorCount = 0;
            
            for (const chapterCard of chapters) {
                const chapterNumber = chapterCard.dataset.chapter;
                const success = await saveChapterSections(`Chapter ${chapterNumber}`);
                if (success) {
                    successCount++;
                } else {
                    errorCount++;
                }
            }
            
            if (errorCount === 0) {
                showMessage(`All ${successCount} chapters saved successfully!`, "success");
            } else {
                showMessage(`Saved ${successCount} chapters, ${errorCount} failed. Check console for details.`, "warning");
            }
        }
    );
}

// Quick Actions
async function enableAllSections() {
    showConfirmation(
        "Enable All Sections?",
        "This will enable all thesis document sections. Continue?",
        "success",
        async () => {
            const chapters = new Set();
            
            document.querySelectorAll(".section-checkbox").forEach((checkbox) => {
                if (!checkbox.checked) {
                    checkbox.checked = true;
                    const sectionItem = checkbox.closest('.section-item');
                    updateSectionStatus(sectionItem, true);
                    
                    const chapterCard = sectionItem.closest('.chapter-card');
                    chapters.add(`Chapter ${chapterCard.dataset.chapter}`);
                }
            });
            
            // Save all modified chapters
            let successCount = 0;
            for (const chapter of chapters) {
                const success = await saveChapterSections(chapter);
                if (success) successCount++;
            }
            
            showMessage(`Enabled all sections across ${successCount} chapters`, "success");
        }
    );
}

async function disableAllSections() {
    showConfirmation(
        "Disable All Sections?",
        "This will disable all thesis document sections. Continue?",
        "warning",
        async () => {
            const chapters = new Set();
            
            document.querySelectorAll(".section-checkbox").forEach((checkbox) => {
                if (checkbox.checked) {
                    checkbox.checked = false;
                    const sectionItem = checkbox.closest('.section-item');
                    updateSectionStatus(sectionItem, false);
                    
                    const chapterCard = sectionItem.closest('.chapter-card');
                    chapters.add(`Chapter ${chapterCard.dataset.chapter}`);
                }
            });
            
            // Save all modified chapters
            let successCount = 0;
            for (const chapter of chapters) {
                const success = await saveChapterSections(chapter);
                if (success) successCount++;
            }
            
            showMessage(`Disabled all sections across ${successCount} chapters`, "success");
        }
    );
}
function resetToDefaults() {
    showConfirmation(
        "Reset to Defaults?",
        "This will reset all sections and formatting to TCU thesis standards. Continue?",
        "info",
        async () => {
            // Reset formatting to TCU thesis standards
            currentFormatting = {
                fontStyle: 'Times New Roman',
                fontSize: 12,
                alignment: 'justify',
                margins: { 
                    top: 0.89,     // TCU standard: 0.89 inch
                    bottom: 0.19,  // TCU standard: 0.19 inch  
                    left: 1.00,    // TCU standard: 1.00 inch
                    right: 1.00    // TCU standard: 1.00 inch
                },
                indent: 0.5,
                border: false,
                logoPosition: 'none',
                lineSpacing: 1.5
            };
            
            // Update UI controls
            document.getElementById("fontStyle").value = currentFormatting.fontStyle;
            document.getElementById("fontSize").value = currentFormatting.fontSize;
            document.querySelectorAll(".align-btn").forEach(btn => {
                btn.classList.toggle("active", btn.dataset.align === currentFormatting.alignment);
            });
            document.getElementById("marginTop").value = currentFormatting.margins.top;
            document.getElementById("marginBottom").value = currentFormatting.margins.bottom;
            document.getElementById("marginLeft").value = currentFormatting.margins.left;
            document.getElementById("marginRight").value = currentFormatting.margins.right;
            document.getElementById("indent").value = currentFormatting.indent;
            document.getElementById("borderToggle").checked = currentFormatting.border;
            document.getElementById("logoPosition").value = currentFormatting.logoPosition;
            
            // Enable all sections
            document.querySelectorAll(".section-checkbox").forEach((checkbox) => {
                checkbox.checked = true;
                const sectionItem = checkbox.closest('.section-item');
                updateSectionStatus(sectionItem, true);
            });
            
            showMessage("Reset to TCU thesis standards completed", "success");
        }
    );
}

// UI Helper Functions
function initializeEventListeners() {
    // Chapter headers - toggle expand/collapse
    document.querySelectorAll(".chapter-header").forEach((header) => {
        header.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleChapter(this);
        });
    });

    // Formatting controls
    const fontStyle = document.getElementById("fontStyle");
    const fontSize = document.getElementById("fontSize");
    const marginTop = document.getElementById("marginTop");
    const marginBottom = document.getElementById("marginBottom");
    const marginLeft = document.getElementById("marginLeft");
    const marginRight = document.getElementById("marginRight");
    const indent = document.getElementById("indent");
    const borderToggle = document.getElementById("borderToggle");
    const logoPosition = document.getElementById("logoPosition");

    if (fontStyle) fontStyle.addEventListener("change", (e) => updateFormatting('fontStyle', e.target.value));
    if (fontSize) fontSize.addEventListener("change", (e) => updateFormatting('fontSize', e.target.value));
    if (marginTop) marginTop.addEventListener("change", () => updateFormatting('margins'));
    if (marginBottom) marginBottom.addEventListener("change", () => updateFormatting('margins'));
    if (marginLeft) marginLeft.addEventListener("change", () => updateFormatting('margins'));
    if (marginRight) marginRight.addEventListener("change", () => updateFormatting('margins'));
    if (indent) indent.addEventListener("change", (e) => updateFormatting('indent', e.target.value));
    if (borderToggle) borderToggle.addEventListener("change", () => updateFormatting('border'));
    if (logoPosition) logoPosition.addEventListener("change", (e) => updateFormatting('logoPosition', e.target.value));

    // Alignment buttons
    document.querySelectorAll(".align-btn").forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            const alignment = this.dataset.align;
            updateFormatting('alignment', alignment);
        });
    });

    // Logout functionality
    const logoutBtn = document.getElementById("logoutBtn");
    const logoutLink = document.getElementById("logoutLink");

    if (logoutBtn) logoutBtn.addEventListener("click", (e) => {
        e.preventDefault();
        openLogoutModal();
    });

    if (logoutLink) logoutLink.addEventListener("click", (e) => {
        e.preventDefault();
        openLogoutModal();
    });

    // User dropdown
    const userAvatar = document.getElementById("userAvatar");
    if (userAvatar) userAvatar.addEventListener("click", toggleUserDropdown);

    // Close dropdowns when clicking outside
    document.addEventListener("click", (e) => {
        if (!e.target.closest(".user-info")) {
            const dropdown = document.getElementById("userDropdown");
            if (dropdown) dropdown.style.display = "none";
        }
    });
}

function toggleChapter(headerElement) {
    const card = headerElement.closest(".chapter-card");
    if (card) {
        card.classList.toggle("expanded");
    }
}

function showMessage(message, type = "info") {
    const container = document.getElementById("messageContainer");
    if (!container) {
        console.error('Message container not found');
        return;
    }

    const messageEl = document.createElement("div");
    messageEl.className = `message ${type} animated fadeIn`;
    
    const iconMap = {
        success: "check-circle",
        error: "exclamation-circle",
        warning: "exclamation-triangle",
        info: "info-circle"
    };

    messageEl.innerHTML = `
        <i class="fas fa-${iconMap[type]}"></i>
        <span>${message}</span>
        <button class="message-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

    container.appendChild(messageEl);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (messageEl.parentElement) {
            messageEl.style.animation = "fadeOut 0.3s ease";
            setTimeout(() => messageEl.remove(), 300);
        }
    }, 5000);
}

// Confirmation modal functions
function showConfirmation(title, message, type = "info", onConfirm, onCancel) {
    const modal = document.getElementById("confirmationModal");
    const titleEl = document.getElementById("confirmTitle");
    const messageEl = document.getElementById("confirmMessage");
    const iconEl = document.getElementById("confirmIcon");
    const confirmBtn = document.getElementById("confirmBtn");

    if (!modal || !titleEl || !messageEl || !iconEl || !confirmBtn) {
        console.error('Confirmation modal elements not found');
        if (onConfirm) onConfirm();
        return;
    }

    titleEl.textContent = title;
    messageEl.textContent = message;

    // Update icon based on type
    iconEl.className = "confirmation-icon " + type;
    const iconMap = {
        success: "fa-check-circle",
        warning: "fa-exclamation-circle",
        danger: "fa-trash-alt",
        info: "fa-info-circle",
    };
    iconEl.innerHTML = `<i class="fas ${iconMap[type]}"></i>`;

    // Store callbacks
    window.confirmCallback = onConfirm;
    window.cancelCallback = onCancel;

    modal.classList.add("show");
}

function executeConfirmation() {
    if (window.confirmCallback) {
        window.confirmCallback();
    }
    closeConfirmationModal();
}

function closeConfirmationModal() {
    const modal = document.getElementById("confirmationModal");
    if (modal) {
        modal.classList.remove("show");
    }
    if (window.cancelCallback) {
        window.cancelCallback();
        window.cancelCallback = null;
    }
}

function toggleUserDropdown() {
    const dropdown = document.getElementById("userDropdown");
    if (dropdown) {
        dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
    }
}

function openLogoutModal() {
    const modal = document.getElementById("logoutModal");
    if (modal) {
        modal.style.display = "flex";
    }
}

function closeLogoutModal() {
    const modal = document.getElementById("logoutModal");
    if (modal) {
        modal.style.display = "none";
    }
}

function confirmLogout() {
    window.location.href = "../logout.php";
}





// =============== Start of version 15 update =============== 


      // =============== Fixed Notification functionality =============== 
document.addEventListener('DOMContentLoaded', function() {
    initializeNotifications();
});

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
            // Refresh notifications when dropdown is opened
            if (notificationMenu.classList.contains('show')) {
                refreshNotifications();
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
    // Use the correct endpoint for coordinator
    fetch('coordinator_document-control-panel.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_as_read&notification_id=' + notificationId
    })
    .then(response => {
        // First check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error('Expected JSON, got: ' + text.substring(0, 100));
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            element.classList.remove('unread');
            updateNotificationBadge();
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
        // Don't show error to user for background operations
    });
}

function markAllNotificationsAsRead() {
    fetch('coordinator_document-control-panel.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_as_read'
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                throw new Error('Expected JSON, got: ' + text.substring(0, 100));
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Remove unread class from all notifications
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            updateNotificationBadge();
            showMessage('All notifications marked as read', 'success');
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
        showMessage('Error updating notifications', 'error');
    });
}

function refreshNotifications() {
    fetch('coordinator_document-control-panel.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_notifications'
    })
    .then(response => {
        // Check if response is JSON before parsing
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.warn('Expected JSON but got:', text.substring(0, 200));
                return { success: false, notifications: [], unread_count: 0 };
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            updateNotificationDisplay(data.notifications, data.unread_count);
        }
    })
    .catch(error => {
        console.error('Error refreshing notifications:', error);
        // Silent fail for background refresh
    });
}

function updateNotificationDisplay(notifications, unreadCount) {
    // Update notification badge
    updateNotificationBadge(unreadCount);

    // Update notification list if needed
    // This is optional - you can choose to keep the server-rendered list
}

function updateNotificationBadge(unreadCount = null) {
    if (unreadCount === null) {
        // Count from DOM if not provided
        unreadCount = document.querySelectorAll('.notification-item.unread').length;
    }
    
    const badge = document.getElementById('notificationBadge');
    const notificationBtn = document.getElementById('notificationBtn');
    
    if (unreadCount > 0) {
        if (!badge && notificationBtn) {
            const newBadge = document.createElement('span');
            newBadge.className = 'notification-badge';
            newBadge.id = 'notificationBadge';
            newBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            notificationBtn.appendChild(newBadge);
        } else if (badge) {
            badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
        }
    } else if (badge) {
        badge.remove();
        
        // Remove mark all read button if no unread notifications
        const markAllReadBtn = document.getElementById('markAllRead');
        if (markAllReadBtn && unreadCount === 0) {
            markAllReadBtn.remove();
        }
    }
}
// =============== End of Fixed Notification functionality ================

// ===============  End of version 15 update ==============================

// Auto-save when formatting changes
function setupAutoSave() {
    // Formatting controls
    const formattingElements = [
        'fontStyle', 'fontSize', 'lineSpacing', 'marginTop', 'marginBottom',
        'marginLeft', 'marginRight', 'indent', 'borderToggle', 'logoPosition'
    ];
    
    formattingElements.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', () => {
                saveAllChapters();
            });
        }
    });
    
    // Alignment buttons
    document.querySelectorAll('.align-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            setTimeout(saveAllChapters, 100);
        });
    });
    
    // Section toggles
    document.querySelectorAll('.section-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            const chapterCard = checkbox.closest('.chapter-card');
            const chapterNumber = chapterCard.dataset.chapter;
            saveChapterSections(`Chapter ${chapterNumber}`);
        });
    });
}

async function saveAllChapters() {
    const chapters = document.querySelectorAll('.chapter-card');
    let successCount = 0;
    
    for (const chapterCard of chapters) {
        const chapterNumber = chapterCard.dataset.chapter;
        const success = await saveChapterSections(`Chapter ${chapterNumber}`);
        if (success) successCount++;
    }
    
    if (successCount > 0) {
        console.log(`✅ Saved ${successCount} chapters`);
    }
}

// Chapter preview functionality
function openChapterPreview(chapterNumber) {
    // Get enabled sections for this chapter
    const enabledSections = getEnabledSectionsForChapter(chapterNumber);
    
    // Collect formatting values
    const params = new URLSearchParams({
        chapter: chapterNumber,
        fontFamily: getFontFamilyValue(currentFormatting.fontStyle),
        fontSize: currentFormatting.fontSize,
        textAlign: currentFormatting.alignment,
        marginTop: currentFormatting.margins.top,
        marginBottom: currentFormatting.margins.bottom,
        marginLeft: currentFormatting.margins.left,
        marginRight: currentFormatting.margins.right,
        indent: currentFormatting.indent,
        borderEnabled: currentFormatting.border,
        logoPosition: currentFormatting.logoPosition,
        lineSpacing: currentFormatting.lineSpacing,
        enabledSections: JSON.stringify(enabledSections),
        showBorders: true
    });
    
    // Open chapter-specific preview
    window.open(`chapter-preview.php?${params.toString()}`, '_blank');
}

function getEnabledSectionsForChapter(chapterNumber) {
    const chapterCard = document.querySelector(`.chapter-card[data-chapter="${chapterNumber}"]`);
    if (!chapterCard) return [];
    
    const enabledSections = [];
    
    chapterCard.querySelectorAll('.section-item').forEach(item => {
        const checkbox = item.querySelector('.section-checkbox');
        const sectionName = item.querySelector('.section-name').textContent.trim();
        
        if (checkbox.checked) {
            enabledSections.push(sectionName);
        }
    });
    
    return enabledSections;
}

// Document preview functionality

function openPreview() {
    // Collect all formatting values from the control panel
    const fontFamily = document.getElementById('fontStyle').value;
    const fontSize = document.getElementById('fontSize').value;
    
    // Get active alignment
    const activeAlignBtn = document.querySelector('.align-btn.active');
    const textAlign = activeAlignBtn ? activeAlignBtn.dataset.align : 'left';
    
    // Get margin values
    const marginTop = document.getElementById('marginTop').value || '1';
    const marginBottom = document.getElementById('marginBottom').value || '1';
    const marginLeft = document.getElementById('marginLeft').value || '1';
    const marginRight = document.getElementById('marginRight').value || '1';
    
    // Get other formatting values
    const indent = document.getElementById('indent').value || '0.5';
    const borderEnabled = document.getElementById('borderToggle').checked;
    const logoPosition = document.getElementById('logoPosition').value || 'none';
    
    // Build URL with all formatting parameters
    const params = new URLSearchParams({
        fontFamily: getFontFamilyValue(fontFamily),
        fontSize: fontSize,
        textAlign: textAlign,
        marginTop: marginTop,
        marginBottom: marginBottom,
        marginLeft: marginLeft,
        marginRight: marginRight,
        indent: indent,
        borderEnabled: borderEnabled,
        logoPosition: logoPosition,
        showMargins: true
    });
    
    // Open preview in new tab
    window.open('document-preview.html?' + params.toString(), '_blank');
}

// Helper function to convert font IDs to CSS values
function getFontFamilyValue(fontId) {
    const fontMap = {
        'arial': 'Arial, sans-serif',
        'times': 'Times New Roman, serif',
        'calibri': 'Calibri, sans-serif',
        'georgia': 'Georgia, serif'
    };
    return fontMap[fontId] || 'Times New Roman, serif';
}
