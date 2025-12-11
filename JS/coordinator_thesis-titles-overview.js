// Thesis Titles Overview - Interactive Features with Enhanced Duplicate Detection

let currentPage = 1;
const entriesPerPage = 10;
let allRows = [];
let filteredRows = [];
let showDuplicatesOnly = false;

// Initialize on page load
document.addEventListener("DOMContentLoaded", () => {
    initializeTable();
    setupEventListeners();
    setupNotificationSystem();
    setupLogoutFunctionality();
    setupVerificationButtons();
});

// Initialize table data
function initializeTable() {
    const tableBody = document.getElementById("tableBody");
    if (tableBody) {
        allRows = Array.from(tableBody.querySelectorAll("tr"));
        filteredRows = [...allRows];
        updatePagination();
    }
}

// Setup event listeners
function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
        searchInput.addEventListener("keyup", () => {
            filterTable();
        });
    }

    // Toggle duplicates only
    const toggleDuplicatesBtn = document.getElementById("toggleDuplicatesBtn");
    if (toggleDuplicatesBtn) {
        toggleDuplicatesBtn.addEventListener("click", function () {
            showDuplicatesOnly = !showDuplicatesOnly;
            this.classList.toggle("active");
            this.innerHTML = showDuplicatesOnly 
                ? '<i class="fas fa-filter"></i> Show All Titles'
                : '<i class="fas fa-filter"></i> Show Duplicates Only';
            filterTable();
        });
    }

    // Export to CSV
    const exportBtn = document.getElementById("exportBtn");
    if (exportBtn) {
        exportBtn.addEventListener("click", () => {
            exportToCSV();
        });
    }

    // Pagination buttons
    const prevBtn = document.getElementById("prevBtn");
    if (prevBtn) {
        prevBtn.addEventListener("click", () => {
            if (currentPage > 1) {
                currentPage--;
                updatePagination();
            }
        });
    }

    const nextBtn = document.getElementById("nextBtn");
    if (nextBtn) {
        nextBtn.addEventListener("click", () => {
            const maxPages = Math.ceil(filteredRows.length / entriesPerPage);
            if (currentPage < maxPages) {
                currentPage++;
                updatePagination();
            }
        });
    }

    // Threshold slider
    const thresholdSlider = document.getElementById("thresholdSlider");
    if (thresholdSlider) {
        thresholdSlider.addEventListener("input", function () {
            document.getElementById("thresholdValue").textContent = this.value + "%";
        });

        thresholdSlider.addEventListener("change", function () {
            updateSimilarityThreshold(this.value);
        });
    }

    // Tooltip functionality for duplicate badges
    document.addEventListener("mouseover", (e) => {
        if (e.target.classList.contains("duplicate-badge") || e.target.closest('.duplicate-badge')) {
            const badge = e.target.classList.contains("duplicate-badge") ? e.target : e.target.closest('.duplicate-badge');
            const tooltip = badge.querySelector(".duplicate-tooltip");
            if (tooltip) {
                tooltip.style.display = "block";
            }
        }
    });

    document.addEventListener("mouseout", (e) => {
        if (e.target.classList.contains("duplicate-badge") || e.target.closest('.duplicate-badge')) {
            const badge = e.target.classList.contains("duplicate-badge") ? e.target : e.target.closest('.duplicate-badge');
            const tooltip = badge.querySelector(".duplicate-tooltip");
            if (tooltip) {
                tooltip.style.display = "none";
            }
        }
    });
}

// Filter table based on search and duplicate filter
function filterTable() {
    const searchInput = document.getElementById("searchInput");
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';

    filteredRows = allRows.filter((row) => {
        // Check if row matches search term
        const text = row.textContent.toLowerCase();
        const matchesSearch = text.includes(searchTerm);

        // Check if row is duplicate (if filter is active)
        const isDuplicate = row.classList.contains("duplicate-row");
        const matchesDuplicateFilter = !showDuplicatesOnly || isDuplicate;

        return matchesSearch && matchesDuplicateFilter;
    });

    currentPage = 1;
    updatePagination();
}

// Update pagination display
function updatePagination() {
    const tableBody = document.getElementById("tableBody");
    if (!tableBody) return;

    const totalEntries = filteredRows.length;
    const maxPages = Math.ceil(totalEntries / entriesPerPage);

    // Hide all rows
    allRows.forEach((row) => (row.style.display = "none"));

    // Show rows for current page
    const startIndex = (currentPage - 1) * entriesPerPage;
    const endIndex = Math.min(startIndex + entriesPerPage, totalEntries);

    for (let i = startIndex; i < endIndex; i++) {
        if (filteredRows[i]) {
            filteredRows[i].style.display = "";
        }
    }

    // Update pagination info
    const startEntry = document.getElementById("startEntry");
    const endEntry = document.getElementById("endEntry");
    const totalEntriesSpan = document.getElementById("totalEntries");
    const pageInfo = document.getElementById("pageInfo");
    const prevBtn = document.getElementById("prevBtn");
    const nextBtn = document.getElementById("nextBtn");

    if (startEntry) startEntry.textContent = totalEntries === 0 ? 0 : startIndex + 1;
    if (endEntry) endEntry.textContent = endIndex;
    if (totalEntriesSpan) totalEntriesSpan.textContent = totalEntries;
    if (pageInfo) pageInfo.textContent = `Page ${currentPage} of ${maxPages || 1}`;

    // Update button states
    if (prevBtn) prevBtn.disabled = currentPage === 1;
    if (nextBtn) nextBtn.disabled = currentPage >= maxPages;
}

// Export to CSV
function exportToCSV() {
    const form = document.createElement("form");
    form.method = "POST";
    form.style.display = "none";

    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "action";
    input.value = "export_excel";

    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function updateSimilarityThreshold(threshold) {
    console.log("Updating threshold to:", threshold);
    
    // Show loading state
    const thresholdValue = document.getElementById("thresholdValue");
    const originalText = thresholdValue.textContent;
    thresholdValue.textContent = "Updating...";
    
    const exportBtn = document.getElementById("exportBtn");
    const toggleBtn = document.getElementById("toggleDuplicatesBtn");
    const originalExportText = exportBtn.innerHTML;
    
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    exportBtn.disabled = true;
    
    if (toggleBtn) {
        toggleBtn.disabled = true;
    }

    const formData = new FormData();
    formData.append("action", "update_threshold");
    formData.append("threshold", threshold);

    fetch(window.location.href, {
        method: "POST",
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData,
    })
    .then((response) => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then((data) => {
        if (data.success) {
            console.log("Threshold updated successfully:", threshold);
            
            // Show success feedback
            thresholdValue.textContent = threshold + "% ✓";
            exportBtn.innerHTML = '<i class="fas fa-check"></i> Updated!';
            
            // Use AJAX to reload just the content instead of full page reload
            reloadTableContent(threshold);
            
        } else {
            throw new Error(data.error || 'Unknown error occurred');
        }
    })
    .catch((error) => {
        console.error("Error updating threshold:", error);
        
        // Show error message
        showErrorMessage("Error updating threshold: " + error.message);
        
        // Reset UI to previous state
        resetUIState(originalText, originalExportText);
    });
}

// AJAX function to reload table content without full page refresh
function reloadTableContent(newThreshold) {
    const contentCard = document.querySelector('.content-card');
    const originalContent = contentCard.innerHTML;
    
    // Show loading state
    contentCard.innerHTML = `
        <div class="loading-state" style="text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin fa-2x" style="color: #3b82f6; margin-bottom: 20px;"></i>
            <p>Recalculating duplicates with ${newThreshold}% threshold...</p>
        </div>
    `;

    // Create form to submit threshold and reload content
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const thresholdInput = document.createElement('input');
    thresholdInput.type = 'hidden';
    thresholdInput.name = 'similarity_threshold';
    thresholdInput.value = newThreshold;
    
    form.appendChild(thresholdInput);
    document.body.appendChild(form);
    
    // Submit form to reload with new threshold
    setTimeout(() => {
        form.submit();
    }, 1000);
}

// Reset UI state on error
function resetUIState(originalText, originalExportText) {
    const thresholdValue = document.getElementById("thresholdValue");
    const exportBtn = document.getElementById("exportBtn");
    const toggleBtn = document.getElementById("toggleDuplicatesBtn");
    const thresholdSlider = document.getElementById("thresholdSlider");
    
    thresholdValue.textContent = originalText;
    exportBtn.innerHTML = originalExportText;
    exportBtn.disabled = false;
    
    if (toggleBtn) {
        toggleBtn.disabled = false;
    }
    
    if (thresholdSlider) {
        thresholdSlider.value = originalText.replace('%', '').replace(' ✓', '');
    }
}

// =============== Custom Toast Messages ===============
function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-width: 400px;
    `;
    document.body.appendChild(container);
    return container;
}

function showSuccessMessage(message, duration = 5000) {
    showToast(message, 'success', duration);
}

function showErrorMessage(message, duration = 5000) {
    showToast(message, 'error', duration);
}

function showToast(message, type = 'info', duration = 5000) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = createToastContainer();
    }
    
    const toast = document.createElement('div');
    toast.className = `toast-message toast-${type}`;
    toast.style.cssText = `
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideIn 0.3s ease-out;
        max-width: 400px;
        word-wrap: break-word;
    `;
    
    const icon = type === 'success' ? 'fa-check-circle' : 
                 type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
    
    toast.innerHTML = `
        <i class="fas ${icon}" style="font-size: 20px; flex-shrink: 0;"></i>
        <div style="flex: 1;">
            <div style="font-weight: 600; margin-bottom: 4px;">
                ${type === 'success' ? 'Success' : type === 'error' ? 'Error' : 'Info'}
            </div>
            <div style="font-size: 14px; line-height: 1.4;">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()" style="
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            flex-shrink: 0;
        ">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    // Auto remove after duration
    if (duration > 0) {
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.animation = 'slideOut 0.3s ease-in forwards';
                setTimeout(() => toast.remove(), 300);
            }
        }, duration);
    }
    
    // Add CSS animations
    if (!document.getElementById('toastStyles')) {
        const style = document.createElement('style');
        style.id = 'toastStyles';
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
            .toast-message {
                transition: all 0.3s ease;
            }
            .toast-close:hover {
                background: rgba(255, 255, 255, 0.2) !important;
            }
        `;
        document.head.appendChild(style);
    }
}

// =============== Custom Confirmation Modal ===============
function createConfirmationModal() {
    const modal = document.createElement('div');
    modal.id = 'confirmationModal';
    modal.className = 'vmodal';
    modal.style.cssText = `
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(2px);
        justify-content: center;
        align-items: center;
    `;
    
    modal.innerHTML = `
        <div class="modal-content confirmation-modal" style="
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
            margin: auto;
        ">
            <div class="modal-header" style="padding: 20px; border-bottom: 1px solid #e5e7eb;">
                <h3 style="margin: 0; color: #374151; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i>
                    <span id="confirmationTitle">Confirmation</span>
                </h3>
                <button class="close-modal" onclick="closeConfirmationModal()" style="
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: #6b7280;
                ">&times;</button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div style="display: flex; align-items: flex-start; gap: 15px; margin-bottom: 20px;">
                    <div style="
                        background: #fef3c7;
                        border-radius: 50%;
                        width: 40px;
                        height: 40px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                    ">
                        <i class="fas fa-exclamation" style="color: #d97706;"></i>
                    </div>
                    <div>
                        <p id="confirmationMessage" style="margin: 0 0 10px 0; color: #374151; line-height: 1.5;"></p>
                        <p id="confirmationDetails" style="margin: 0; color: #6b7280; font-size: 14px; line-height: 1.4;"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 20px; border-top: 1px solid #e5e7eb; display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn btn-secondary" onclick="closeConfirmationModal()" style="
                    padding: 10px 20px;
                    border: 1px solid #d1d5db;
                    background: #f9fafb;
                    color: #374151;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 500;
                ">Cancel</button>
                <button class="btn btn-primary" id="confirmActionBtn" style="
                    padding: 10px 20px;
                    border: 1px solid #dc2626;
                    background: #dc2626;
                    color: white;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 500;
                ">Confirm</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close modal when clicking outside
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeConfirmationModal();
        }
    });
    
    return modal;
}

function showConfirmationModal(title, message, details = '', confirmCallback) {
    let modal = document.getElementById('confirmationModal');
    if (!modal) {
        modal = createConfirmationModal();
    }
    
    document.getElementById('confirmationTitle').textContent = title;
    document.getElementById('confirmationMessage').textContent = message;
    document.getElementById('confirmationDetails').textContent = details;
    
    const confirmBtn = document.getElementById('confirmActionBtn');
    confirmBtn.onclick = function() {
        confirmCallback();
        closeConfirmationModal();
    };
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Add event listener for ESC key
    document.addEventListener('keydown', handleConfirmationEscKey);
}

function closeConfirmationModal() {
    const modal = document.getElementById('confirmationModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        
        // Remove ESC key listener
        document.removeEventListener('keydown', handleConfirmationEscKey);
    }
}

function handleConfirmationEscKey(event) {
    if (event.key === 'Escape') {
        closeConfirmationModal();
    }
}

// =============== Verification System ===============
function setupVerificationButtons() {
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-verify') || e.target.closest('.btn-verify')) {
            const btn = e.target.classList.contains('btn-verify') ? e.target : e.target.closest('.btn-verify');
            
            const thesisId = btn.getAttribute('data-thesis-id');
            const thesisTitle = btn.getAttribute('data-thesis-title');
            const groupName = btn.getAttribute('data-group-name');
            const advisorName = btn.getAttribute('data-advisor-name');
            const advisorId = btn.getAttribute('data-advisor-id');
            const isDuplicate = btn.getAttribute('data-is-duplicate') === 'true';
            
            // Get duplicate data if exists
            let duplicateData = null;
            if (isDuplicate) {
                const row = btn.closest('tr');
                const duplicateItems = row.querySelectorAll('.duplicate-tooltip li');
                duplicateData = Array.from(duplicateItems).map(li => {
                    const text = li.textContent;
                    const similarityMatch = text.match(/\((\d+\.?\d*)% similar\)/);
                    return {
                        title: text.replace(/\s*\(\d+\.?\d*% similar\)/, '').trim(),
                        similarity: similarityMatch ? parseFloat(similarityMatch[1]) : 0
                    };
                });
            }
            
            openVerificationModal(thesisId, thesisTitle, groupName, advisorName, advisorId, isDuplicate, duplicateData);
        }
    });
}

function openVerificationModal(thesisId, thesisTitle, groupName, advisorName, advisorId, isDuplicate, duplicateData = null) {
    const modal = document.getElementById('verificationModal');
    const modalTitle = document.getElementById('modalThesisTitle');
    const modalGroupName = document.getElementById('modalGroupName');
    const modalAdvisorName = document.getElementById('modalAdvisorName');
    const duplicateInfo = document.getElementById('duplicateInfo');
    const duplicateList = document.getElementById('duplicateList');
    
    // Set basic information
    modalTitle.textContent = thesisTitle;
    modalGroupName.textContent = groupName;
    modalAdvisorName.textContent = advisorName;
    
    // Handle duplicate information
    if (isDuplicate && duplicateData) {
        duplicateInfo.style.display = 'block';
        duplicateList.innerHTML = '';
        
        duplicateData.forEach(dup => {
            const li = document.createElement('li');
            li.innerHTML = `
                ${dup.title} 
                <span class="similarity-score">(${dup.similarity}% similar)</span>
            `;
            duplicateList.appendChild(li);
        });
        
        // Enable both buttons for duplicates
        document.getElementById('informAdvisorBtn').disabled = false;
        document.getElementById('verifyUniqueBtn').disabled = false;
    } else {
        duplicateInfo.style.display = 'none';
        
        // For non-duplicates, only show verify button
        document.getElementById('informAdvisorBtn').style.display = 'none';
        document.getElementById('verifyUniqueBtn').disabled = false;
    }
    
    // Store current thesis data for later use
    modal.currentThesisId = thesisId;
    modal.currentThesisData = {
        id: thesisId,
        title: thesisTitle,
        groupName: groupName,
        advisorName: advisorName,
        advisorId: advisorId,
        isDuplicate: isDuplicate,
        duplicateData: duplicateData
    };
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeVerificationModal() {
    const modal = document.getElementById('verificationModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // Reset button states
    document.getElementById('informAdvisorBtn').style.display = 'block';
    document.getElementById('informAdvisorBtn').disabled = false;
    document.getElementById('verifyUniqueBtn').disabled = false;
}

function informAdvisor() {
    const modal = document.getElementById('verificationModal');
    const thesisData = modal.currentThesisData;
    
    // Use custom confirmation modal instead of native confirm()
    showConfirmationModal(
        'Email Advisor',
        `Send duplicate notification email to ${thesisData.advisorName}?`,
        'The advisor will receive a professional email with details about the duplicate titles and required actions.',
        () => {
            // Add loading state
            const btn = document.getElementById('informAdvisorBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending Email...';
            btn.disabled = true;
            
            // Prepare duplicate data for the server
            const duplicateData = thesisData.duplicateData ? thesisData.duplicateData.map(dup => ({
                title: dup.title,
                similarity: dup.similarity
            })) : [];
            
            // Make API call to report duplicate and send email
            const formData = new FormData();
            formData.append('action', 'report_duplicate');
            formData.append('thesis_id', thesisData.id);
            formData.append('advisor_id', thesisData.advisorId);
            formData.append('reason', 'Potential duplicate title detected during verification');
            formData.append('duplicate_data', JSON.stringify(duplicateData));
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    updateThesisVerificationStatus(thesisData.id, 'reported');
                    
                    if (data.email_sent) {
                        showSuccessMessage(`✅ Email sent to ${thesisData.advisorName} successfully!`);
                    } else {
                        showSuccessMessage(`⚠️ Duplicate reported, but email failed to send. Status updated.`);
                    }
                } else {
                    showErrorMessage('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('Error sending email to advisor');
            })
            .finally(() => {
                // Reset button
                btn.innerHTML = originalText;
                btn.disabled = false;
                closeVerificationModal();
            });
        }
    );
}

function verifyAsUnique() {
    const modal = document.getElementById('verificationModal');
    const thesisData = modal.currentThesisData;
    
    const message = thesisData.isDuplicate 
        ? 'Are you sure you want to mark this title as unique despite detected duplicates?'
        : 'Mark this title as verified and unique?';
    
    const details = thesisData.isDuplicate 
        ? 'This title has been flagged as potentially similar to other titles. Please ensure it is truly unique before verifying.'
        : 'This title will be marked as verified and will no longer appear in duplicate checks.';
    
    // Use custom confirmation modal instead of native confirm()
    showConfirmationModal(
        'Verify Thesis Title',
        message,
        details,
        () => {
            // Add loading state
            const btn = document.getElementById('verifyUniqueBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
            btn.disabled = true;
            
            // Make API call to verify thesis
            const formData = new FormData();
            formData.append('action', 'verify_thesis');
            formData.append('thesis_id', thesisData.id);
            formData.append('notes', 'Manually verified by coordinator');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    updateThesisVerificationStatus(thesisData.id, 'verified');
                    showSuccessMessage('Thesis title verified as unique successfully!');
                } else {
                    showErrorMessage('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('Error verifying thesis title');
            })
            .finally(() => {
                // Reset button
                btn.innerHTML = originalText;
                btn.disabled = false;
                closeVerificationModal();
            });
        }
    );
}

function updateThesisVerificationStatus(thesisId, status) {
    const row = document.querySelector(`tr[data-id="${thesisId}"]`);
    if (row) {
        // Update verification status cell
        const statusCell = row.querySelector('.verification-status');
        const actionCell = row.querySelector('.action-cell');
        const duplicateStatusCell = row.querySelector('.duplicate-indicator, .unique-indicator');
        
        if (status === 'verified') {
            // Remove duplicate styling
            row.classList.remove('duplicate-row');
            
            // Update verification status
            if (statusCell) {
                statusCell.className = 'verification-status verified-indicator';
                statusCell.innerHTML = '<i class="fas fa-check-double"></i> Verified';
            }
            
            // Update duplicate status to "Verified"
            if (duplicateStatusCell) {
                duplicateStatusCell.className = 'unique-indicator verified';
                duplicateStatusCell.innerHTML = '<i class="fas fa-check-circle"></i> Verified';
            }
            
            // Update action button
            if (actionCell) {
                actionCell.innerHTML = `
                    <button class="btn-verified" disabled>
                        <i class="fas fa-check-double"></i> Verified
                    </button>
                `;
            }
            
            // Remove duplicate badge
            const duplicateBadge = row.querySelector('.duplicate-badge');
            if (duplicateBadge) {
                duplicateBadge.remove();
            }
            
            // Update title cell
            const titleCell = row.querySelector('.title-cell');
            if (titleCell) {
                titleCell.classList.remove('duplicate-title');
            }
            
        } else if (status === 'reported') {
            // Update status to reported
            if (statusCell) {
                statusCell.className = 'verification-status reported-indicator';
                statusCell.innerHTML = '<i class="fas fa-flag"></i> Reported';
            }
            
            // Update action button
            if (actionCell) {
                actionCell.innerHTML = `
                    <button class="btn-verified" disabled>
                        <i class="fas fa-flag"></i> Reported
                    </button>
                `;
            }
        }
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('verificationModal');
    if (e.target === modal) {
        closeVerificationModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeVerificationModal();
        closeConfirmationModal();
    }
});

// =============== Notification System ===============
function setupNotificationSystem() {
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
            if (!notificationMenu.contains(e.target) && !notificationBtn.contains(e.target)) {
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

    fetch(window.location.href, {
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

    fetch(window.location.href, {
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
            
            // Remove mark all read button if no unread notifications
            const markAllReadBtn = document.getElementById('markAllRead');
            if (markAllReadBtn) {
                markAllReadBtn.remove();
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

function refreshNotifications() {
    const formData = new FormData();
    formData.append('action', 'get_notifications');

    fetch(window.location.href, {
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
        
        // Show mark all read button if not present
        const markAllReadBtn = document.getElementById('markAllRead');
        const notificationHeader = document.querySelector('.notification-header');
        if (!markAllReadBtn && notificationHeader) {
            const newMarkAllBtn = document.createElement('button');
            newMarkAllBtn.className = 'mark-all-read';
            newMarkAllBtn.id = 'markAllRead';
            newMarkAllBtn.textContent = 'Mark all as read';
            newMarkAllBtn.addEventListener('click', markAllNotificationsAsRead);
            notificationHeader.appendChild(newMarkAllBtn);
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

function updateNotificationBadge() {
    // Count remaining unread notifications
    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
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
    }
}

// =============== Logout Functionality ===============
function setupLogoutFunctionality() {
    const logoutBtn = document.getElementById("logoutBtn");
    const logoutLink = document.getElementById("logoutLink");

    if (logoutBtn) {
        logoutBtn.addEventListener("click", (e) => {
            e.preventDefault();
            showLogoutModal();
        });
    }

    if (logoutLink) {
        logoutLink.addEventListener("click", (e) => {
            e.preventDefault();
            showLogoutModal();
        });
    }

    // User dropdown functionality
    const userAvatar = document.getElementById("userAvatar");
    const userDropdown = document.getElementById("userDropdown");

    if (userAvatar && userDropdown) {
        userAvatar.addEventListener("click", (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle("show");
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", (e) => {
            if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove("show");
            }
        });
    }

    // Close modals with Escape key
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            closeLogoutModal();
            closeVerificationModal();
            closeConfirmationModal();
        }
    });
}

// ==================== Logout Modal Functions ====================
function showLogoutModal() {
    // Create modal if it doesn't exist
    let modal = document.getElementById("logoutModal");
    if (!modal) {
        modal = document.createElement("div");
        modal.id = "logoutModal";
        modal.className = "modal";
        modal.style.cssText = `
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        `;
        
        modal.innerHTML = `
            <div class="modal-content" style="
                background: white;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                max-width: 400px;
                width: 90%;
            ">
                <div class="modal-header" style="margin-bottom: 1rem;">
                    <h3 style="margin: 0; color: #333;">Confirm Logout</h3>
                </div>
                <div class="modal-body" style="margin-bottom: 1.5rem;">
                    <p style="margin: 0; color: #666;">Are you sure you want to logout?</p>
                </div>
                <div class="modal-footer" style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button class="btn btn-secondary" onclick="closeLogoutModal()" style="
                        padding: 0.5rem 1rem;
                        border: 1px solid #ddd;
                        background: #f8f9fa;
                        border-radius: 4px;
                        cursor: pointer;
                    ">Cancel</button>
                    <button class="btn btn-primary" onclick="confirmLogout()" style="
                        padding: 0.5rem 1rem;
                        border: 1px solid #dc3545;
                        background: #dc3545;
                        color: white;
                        border-radius: 4px;
                        cursor: pointer;
                    ">Logout</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close modal when clicking outside
        modal.addEventListener("click", (e) => {
            if (e.target === modal) {
                closeLogoutModal();
            }
        });
    }
    
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";

    // Add event listener for ESC key
    document.addEventListener("keydown", handleLogoutEscKey);
}

function closeLogoutModal() {
    const modal = document.getElementById("logoutModal");
    if (modal) {
        modal.style.display = "none";
        document.body.style.overflow = "auto";

        // Remove ESC key listener
        document.removeEventListener("keydown", handleLogoutEscKey);
    }
}

function handleLogoutEscKey(event) {
    if (event.key === "Escape") {
        closeLogoutModal();
    }
}

function confirmLogout() {
    window.location.href = "../logout.php";
}

// Utility function to handle page resizing
window.addEventListener('resize', () => {
    // Recalculate pagination on resize
    updatePagination();
});

// Error boundary for unhandled errors
window.addEventListener('error', (e) => {
    console.error('Application error:', e.error);
});
