// Global variables
let currentGroupId = null;
let currentStudentGroupId = null;
let isCreatingGroup = false;

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", function() {
    console.log("Initializing UI...");
    initializeUI();
    setupEventListeners();
    initNotificationSystem();
});

function initializeUI() {
    console.log("Initializing user interface...");
    // Initialize user dropdown
    initUserDropdown();
    
    // Setup logout handlers
    setupLogoutHandlers();
}

function setupEventListeners() {
    console.log("Setting up event listeners...");
    
    // Close dropdowns when clicking outside
    document.addEventListener("click", function(e) {
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

    // Modal close events
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape") {
            closeCreateGroupModal();
            closeEditGroupModal();
            closeConfirmModal();
            closeNotificationMenu();
        }
    });

    // Event listeners for member selection and role assignment
    document.addEventListener('change', function(e) {
        // Handle student checkbox changes
        if (e.target.classList.contains('student-checkbox')) {
            const roleSelector = e.target.closest('.student-select-item')?.querySelector('.role-selector');
            if (roleSelector) {
                roleSelector.disabled = !e.target.checked;
                
                if (roleSelector.value === 'leader' && e.target.checked) {
                    // Ensure only one leader
                    document.querySelectorAll('.role-selector').forEach(selector => {
                        if (selector !== roleSelector && selector.value === 'leader') {
                            selector.value = 'member';
                        }
                    });
                }
            }
        }
        
        // Handle role selector changes
        if (e.target.classList.contains('role-selector') && e.target.value === 'leader') {
            document.querySelectorAll('.role-selector').forEach(selector => {
                if (selector !== e.target && selector.value === 'leader') {
                    selector.value = 'member';
                }
            });
        }
        
        // Handle maximum member limit (use modal-specific configured value if present)
        // Figure out which modal is open and count only its student-checkboxes
        let activeModal = null;
        const createModal = document.getElementById('createGroupModal');
        const editModal = document.getElementById('editGroupModal');
        if (editModal && window.getComputedStyle(editModal).display !== 'none') activeModal = editModal;
        else if (createModal && window.getComputedStyle(createModal).display !== 'none') activeModal = createModal;

        const scope = activeModal || document;
        const selectedCount = scope.querySelectorAll('.student-checkbox:checked').length;

        // Determine max allowed from active modal input first, then fall back to any input on page
        let configuredMax = 4;
        const modalMaxInput = scope.querySelector('#maxMembers, #editMaxMembers');
        const globalMaxInput = document.getElementById('maxMembers') || document.getElementById('editMaxMembers');
        const maxInput = modalMaxInput || globalMaxInput;
        if (maxInput) {
            const v = parseInt(maxInput.value, 10);
            if (!isNaN(v) && v > 0) configuredMax = v;
        }

        // Debug logging (disabled in production by default)
        // console.debug('selectedCount=', selectedCount, 'configuredMax=', configuredMax, 'activeModal=', activeModal ? activeModal.id : 'none');

        if (selectedCount > configuredMax) {
            // Revert the change
            if (e.target.classList.contains('student-checkbox')) {
                e.target.checked = false;
            }
            const roleSelector = e.target.closest('.student-select-item')?.querySelector('.role-selector');
            if (roleSelector) {
                roleSelector.disabled = true;
            }
            showMessage('Maximum ' + configuredMax + ' members allowed per group', 'error');
        }
    });

    // Search functionality
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('input', performSearch);
    }
}

function performSearch() {
    const searchInput = document.querySelector('.search-input');
    const table = document.getElementById('groupsTable');
    
    if (!searchInput || !table) return;
    
    const searchTerm = searchInput.value.toLowerCase();
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        let rowMatches = false;
        
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

// User Dropdown Functionality
function initUserDropdown() {
    const userAvatar = document.getElementById('userAvatar');
    const userDropdown = document.getElementById('userDropdown');
    const headerLogoutLink = document.getElementById('headerLogoutLink');
    
    if (userAvatar && userDropdown) {
        userAvatar.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleUserDropdown();
        });
    }
    
    if (headerLogoutLink) {
        headerLogoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            showLogoutModal();
        });
    }
    
    // Close dropdown when clicking anywhere else
    document.addEventListener('click', function() {
        closeUserDropdown();
    });
}

function toggleUserDropdown() {
    const dropdown = document.getElementById("userDropdown");
    if (dropdown) {
        const isVisible = dropdown.style.display === "block";
        dropdown.style.display = isVisible ? "none" : "block";
    }
}

function closeUserDropdown() {
    const dropdown = document.getElementById("userDropdown");
    if (dropdown) {
        dropdown.style.display = "none";
    }
}

// Logout Functionality
function setupLogoutHandlers() {
    const logoutBtn = document.getElementById("logoutBtn");
    const logoutModal = document.getElementById("logoutModal");
    const confirmLogout = document.getElementById("confirmLogout");
    const cancelLogout = document.getElementById("cancelLogout");

    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showLogoutModal();
        });
    }

    if (confirmLogout) {
        confirmLogout.addEventListener('click', function() {
            window.location.href = "../logout.php";
        });
    }

    if (cancelLogout) {
        cancelLogout.addEventListener('click', function() {
            hideLogoutModal();
        });
    }

    // Close modal when clicking outside
    if (logoutModal) {
        logoutModal.addEventListener('click', function(e) {
            if (e.target === logoutModal) {
                hideLogoutModal();
            }
        });
    }

    // Close with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const logoutModal = document.getElementById("logoutModal");
            if (logoutModal && logoutModal.style.display === 'flex') {
                hideLogoutModal();
            }
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

// Group Management Functions
function showCreateGroupModal() {
    const modal = document.getElementById('createGroupModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeCreateGroupModal() {
    if (isCreatingGroup) {
        showMessage('Please wait while the group is being created', 'warning');
        return;
    }
    
    const modal = document.getElementById('createGroupModal');
    if (modal) {
        modal.style.display = 'none';
    }
    
    const form = document.getElementById('createGroupForm');
    if (form) {
        form.reset();
    }
    
    // Reset checkboxes and role selectors
    document.querySelectorAll('.student-checkbox').forEach(checkbox => {
        checkbox.checked = false;
        const roleSelector = checkbox.closest('.student-select-item')?.querySelector('.role-selector');
        if (roleSelector) {
            roleSelector.disabled = true;
            roleSelector.value = 'member';
        }
    });
    
    // Reset loading state
    setCreateGroupLoading(false);
}

function createGroup() {
    if (isCreatingGroup) {
        console.log('Group creation already in progress');
        return;
    }

    console.log('Starting group creation...');

    // Get selected students and their roles
    const studentIds = [];
    const studentRoles = [];
    
    document.querySelectorAll('.student-checkbox:checked').forEach(checkbox => {
        studentIds.push(checkbox.value);
        const roleSelector = checkbox.closest('.student-select-item')?.querySelector('.role-selector');
        if (roleSelector) {
            studentRoles.push(roleSelector.value);
        }
    });
    
    // Validate inputs
    const groupNameInput = document.getElementById('groupName');
    const thesisTitleInput = document.getElementById('thesisTitle');
    const sectionInput = document.getElementById('groupSection');
    
    if (!groupNameInput || !thesisTitleInput || !sectionInput) {
        showMessage('Form elements not found', 'error');
        return;
    }
    
    const groupName = groupNameInput.value.trim();
    const thesisTitle = thesisTitleInput.value.trim();
    const section = sectionInput.value;
    
    if (!groupName) {
        showMessage('Group name is required', 'error');
        return;
    }
    
    if (!thesisTitle) {
        showMessage('Thesis title is required', 'error');
        return;
    }
    
    if (studentIds.length === 0) {
        showMessage('Please select at least one student', 'error');
        return;
    }
    


    // Read max members from the UI (if provided)
    const maxMembersInput = document.getElementById('maxMembers');
    const maxMembers = maxMembersInput ? parseInt(maxMembersInput.value, 10) || 4 : 4;
    if (studentIds.length > maxMembers) {
        showMessage(`A group can have maximum ${maxMembers} members`, 'error');
        return;
    }
    
    // Validate exactly 1 leader
    const leaderCount = studentRoles.filter(role => role === 'leader').length;
    if (leaderCount !== 1) {
        showMessage('Each group must have exactly 1 Leader', 'error');
        return;
    }
    
    // Set loading state
    setCreateGroupLoading(true);
    
    // Prepare FormData for submission so arrays are sent correctly as repeated keys
    const formData = new FormData();
    formData.append('action', 'create_group');
    formData.append('group_name', groupName);
    formData.append('thesis_title', thesisTitle);
    formData.append('section', section);
    formData.append('max_members', String(maxMembers));

    // Append each student id and its corresponding role as repeated fields
    studentIds.forEach((id, idx) => {
        formData.append('student_ids[]', id);
        const role = studentRoles[idx] || 'member';
        formData.append('student_roles[]', role);
    });

    console.log('Sending FormData for create_group (keys):', Array.from(formData.keys()));

    // Send AJAX request with FormData
    fetch('advisor_thesis-group.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response received:', response);
        
        // First, check if the response is JSON
        const contentType = response.headers.get('content-type');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        if (!contentType || !contentType.includes('application/json')) {
            // If not JSON, get the text and see what's wrong
            return response.text().then(text => {
                console.error('Non-JSON response:', text);
                throw new Error('Server returned non-JSON response. The page might have errors.');
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            showMessage(data.message, 'success');
            closeCreateGroupModal();
            // Reload the page after a short delay to show the new group
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showMessage(data.message || 'Operation failed', 'error');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showMessage('An error occurred while creating the group: ' + error.message, 'error');
    })
    .finally(() => {
        // Reset loading state
        setCreateGroupLoading(false);
    });
}

function setCreateGroupLoading(loading) {
    isCreatingGroup = loading;
    
    const createBtn = document.getElementById('createGroupBtn');
    const loadingIndicator = document.getElementById('createGroupLoading');
    
    if (loading) {
        // Show loading state
        if (createBtn) {
            createBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            createBtn.disabled = true;
        }
        if (loadingIndicator) {
            loadingIndicator.style.display = 'flex';
        }
        
        // Disable all form elements
        const formElements = document.querySelectorAll('#createGroupForm input, #createGroupForm select, #createGroupForm button');
        formElements.forEach(element => {
            element.disabled = true;
        });
    } else {
        // Hide loading state
        if (createBtn) {
            createBtn.innerHTML = '<i class="fas fa-save"></i> Create Group';
            createBtn.disabled = false;
        }
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
        }
        
        // Enable all form elements
        const formElements = document.querySelectorAll('#createGroupForm input, #createGroupForm select, #createGroupForm button');
        formElements.forEach(element => {
            element.disabled = false;
        });
    }
}

function editGroup(groupId, studentGroupId) {
    console.log('Editing group:', groupId, studentGroupId);
    currentGroupId = groupId;
    currentStudentGroupId = studentGroupId;
    
    const formData = new FormData();
    formData.append('action', 'get_group_data');
    formData.append('group_id', groupId);
    
    fetch('advisor_thesis-group.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Check for JSON response
        const contentType = response.headers.get('content-type');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Non-JSON response:', text);
                throw new Error('Server returned non-JSON response');
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Group data received:', data);
        if (data.success) {
            showEditGroupModal(data);
        } else {
            showMessage(data.message || 'Failed to load group data', 'error');
        }
    })
    .catch(error => {
        console.error('Error loading group data:', error);
        showMessage('An error occurred while loading group data: ' + error.message, 'error');
    });
}

function showEditGroupModal(data) {
    const editFormHTML = `
        <form id="editGroupForm">
            <input type="hidden" id="editGroupId" name="group_id" value="${currentGroupId}">
            <input type="hidden" id="editStudentGroupId" name="student_group_id" value="${data.data.student_group_id}">
            <div class="form-group">
                <label for="editGroupName">Group Name *</label>
                <input type="text" id="editGroupName" name="group_name" value="${escapeHtml(data.data.group_name)}" required>
            </div>
            <div class="form-group">
                <label for="editThesisTitle">Thesis Title *</label>
                <input type="text" id="editThesisTitle" name="thesis_title" value="${escapeHtml(data.data.thesis_title)}" required>
            </div>
            <div class="form-group">
                <label>Section</label>
                <div class="section-display">
                    <strong>${escapeHtml(data.data.section)}</strong>
                    <small>(Section cannot be changed)</small>
                </div>
            </div>
                <div class="form-group">
                    <label for="editMaxMembers">Max Members *</label>
                    <input type="number" id="editMaxMembers" name="max_members" value="${data.data.max_members || 4}" min="1" max="10">
                    <small>Set the maximum number of members allowed for this group.</small>
                </div>
            <div class="form-group">
                <label>Group Members (check to include)</label>
                <div id="currentMembersList">
                    ${data.members.map(member => `
                        <div class="student-select-item">
                            <input type="checkbox" name="student_ids[]" value="${member.id}" 
                                   id="member_${member.id}" class="student-checkbox" checked>
                            <label for="member_${member.id}">
                                ${escapeHtml(member.name)}
                            </label>
                            <select name="student_roles[]" class="role-selector">
                                <option value="member" ${member.role === 'member' ? 'selected' : ''}>Member</option>
                                <option value="leader" ${member.role === 'leader' ? 'selected' : ''}>Leader</option>
                            </select>
                        </div>
                    `).join('')}
                </div>
            </div>
            <div class="form-group">
                <label>Available Students to Add</label>
                <div class="student-selector" id="editStudentSelector">
                    ${data.available_students.length > 0 ? 
                        data.available_students.map(student => `
                            <div class="student-select-item">
                                <input type="checkbox" name="student_ids[]" value="${student.id}" 
                                       id="new_student_${student.id}" class="student-checkbox">
                                <label for="new_student_${student.id}">
                                    ${escapeHtml(student.name)} (${escapeHtml(student.section)})
                                </label>
                                <select name="student_roles[]" class="role-selector" disabled>
                                    <option value="member">Member</option>
                                    <option value="leader">Leader</option>
                                </select>
                            </div>
                        `).join('') : 
                        '<p>No additional students available in this section.</p>'}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-primary" onclick="updateGroup()">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <button type="button" class="btn-secondary" onclick="closeEditGroupModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    `;
    
    const modalBody = document.getElementById('editGroupModalBody');
    if (modalBody) {
        modalBody.innerHTML = editFormHTML;
    }
    
    const modal = document.getElementById('editGroupModal');
    if (modal) {
        modal.style.display = 'flex';
    }
    
    // Set up event listeners for the edit form
    setupEditFormEventListeners();
}

function setupEditFormEventListeners() {
    // Enable role selectors for checked members
    document.querySelectorAll('#editGroupForm .student-checkbox').forEach(checkbox => {
        const roleSelector = checkbox.closest('.student-select-item')?.querySelector('.role-selector');
        if (roleSelector) {
            roleSelector.disabled = !checkbox.checked;
            
            checkbox.addEventListener('change', function() {
                if (roleSelector) {
                    roleSelector.disabled = !this.checked;
                    if (!this.checked && roleSelector.value === 'leader') {
                        // If unchecking a leader, find another member to make leader
                        const firstChecked = document.querySelector('#editGroupForm .student-checkbox:checked');
                        if (firstChecked) {
                            const firstRoleSelector = firstChecked.closest('.student-select-item')?.querySelector('.role-selector');
                            if (firstRoleSelector) {
                                firstRoleSelector.value = 'leader';
                            }
                        }
                    }
                }
            });
        }
    });
    
    // Add leader validation
    document.querySelectorAll('#editGroupForm .role-selector').forEach(selector => {
        selector.addEventListener('change', function() {
            if (this.value === 'leader') {
                document.querySelectorAll('#editGroupForm .role-selector').forEach(otherSelector => {
                    if (otherSelector !== this && otherSelector.value === 'leader') {
                        otherSelector.value = 'member';
                    }
                });
            }
        });
    });
}

function updateGroup() {
    const form = document.getElementById('editGroupForm');
    if (!form) {
        showMessage('Edit form not found', 'error');
        return;
    }
    
    const groupNameInput = document.getElementById('editGroupName');
    const thesisTitleInput = document.getElementById('editThesisTitle');
    
    if (!groupNameInput || !thesisTitleInput) {
        showMessage('Form fields not found', 'error');
        return;
    }
    
    const groupName = groupNameInput.value.trim();
    const thesisTitle = thesisTitleInput.value.trim();
    const studentCheckboxes = form.querySelectorAll('.student-checkbox:checked');
    
    if (!groupName) {
        showMessage('Group name is required', 'error');
        return;
    }
    
    if (!thesisTitle) {
        showMessage('Thesis title is required', 'error');
        return;
    }
    
    if (studentCheckboxes.length === 0) {
        showMessage('Please select at least one student', 'error');
        return;
    }
    
    // Respect configured max members if present
    const editMaxInput = document.getElementById('editMaxMembers');
    const editMax = editMaxInput ? parseInt(editMaxInput.value, 10) || 4 : 4;
    if (studentCheckboxes.length > editMax) {
        showMessage(`A group can have maximum ${editMax} members`, 'error');
        return;
    }
    
    let leaderCount = 0;
    const selectedRoles = [];
    
    studentCheckboxes.forEach(checkbox => {
        const roleSelector = checkbox.closest('.student-select-item')?.querySelector('.role-selector');
        if (roleSelector) {
            const role = roleSelector.value;
            selectedRoles.push(role);
            if (role === 'leader') leaderCount++;
        }
    });
    
    if (leaderCount !== 1) {
        showMessage('Each group must have exactly 1 Leader', 'error');
        return;
    }
    
    // Set loading state for edit
    const updateBtn = document.querySelector('#editGroupModal .btn-primary');
    if (updateBtn) {
        const originalText = updateBtn.innerHTML;
        updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        updateBtn.disabled = true;
        
        const formData = new FormData();
        formData.append('action', 'update_group');
        formData.append('group_id', currentGroupId);
        formData.append('student_group_id', currentStudentGroupId);
        formData.append('group_name', groupName);
        formData.append('thesis_title', thesisTitle);
    const editMaxMembersInput = document.getElementById('editMaxMembers');
    const editMaxMembers = editMaxMembersInput ? parseInt(editMaxMembersInput.value, 10) || 4 : 4;
    formData.append('max_members', editMaxMembers);
        
        studentCheckboxes.forEach((checkbox, index) => {
            formData.append('student_ids[]', checkbox.value);
            formData.append('student_roles[]', selectedRoles[index]);
        });
        
        fetch('advisor_thesis-group.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Check for JSON response
            const contentType = response.headers.get('content-type');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Non-JSON response:', text);
                    throw new Error('Server returned non-JSON response');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                closeEditGroupModal();
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showMessage(data.message || 'Update failed', 'error');
            }
        })
        .catch(error => {
            console.error('Update error:', error);
            showMessage('An error occurred while updating the group: ' + error.message, 'error');
        })
        .finally(() => {
            // Reset loading state
            updateBtn.innerHTML = originalText;
            updateBtn.disabled = false;
        });
    }
}

function closeEditGroupModal() {
    const modal = document.getElementById('editGroupModal');
    if (modal) {
        modal.style.display = 'none';
    }
    currentGroupId = null;
    currentStudentGroupId = null;
}

function deleteGroup(groupId, studentGroupId) {
    currentGroupId = groupId;
    currentStudentGroupId = studentGroupId;
    
    const confirmModalTitle = document.getElementById('confirmModalTitle');
    const confirmModalMessage = document.getElementById('confirmModalMessage');
    const confirmActionBtn = document.getElementById('confirmActionBtn');
    const confirmModal = document.getElementById('confirmModal');
    
    if (confirmModalTitle && confirmModalMessage && confirmActionBtn && confirmModal) {
        confirmModalTitle.textContent = 'Delete Group';
        confirmModalMessage.textContent = 'Are you sure you want to delete this group and all its associated data? This action cannot be undone.';
        confirmActionBtn.onclick = confirmDeleteGroup;
        confirmModal.style.display = 'flex';
    }
}

function confirmDeleteGroup() {
    const formData = new FormData();
    formData.append('action', 'delete_group');
    formData.append('group_id', currentGroupId);
    formData.append('student_group_id', currentStudentGroupId);
    
    fetch('advisor_thesis-group.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Check for JSON response
        const contentType = response.headers.get('content-type');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Non-JSON response:', text);
                throw new Error('Server returned non-JSON response');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage(data.message || 'Delete failed', 'error');
        }
        closeConfirmModal();
    })
    .catch(error => {
        console.error('Delete error:', error);
        showMessage('An error occurred while deleting the group: ' + error.message, 'error');
        closeConfirmModal();
    });
}

function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.style.display = 'none';
    }
    currentGroupId = null;
    currentStudentGroupId = null;
}

// UI Helper Functions
function toggleActionDropdown(id) {
    const menu = document.getElementById(`actionMenu${id}`);
    if (menu) {
        const isVisible = menu.style.display === 'block';
        
        // Close all other menus
        document.querySelectorAll('.action-menu').forEach(el => {
            el.style.display = 'none';
        });
        
        // Toggle current menu
        menu.style.display = isVisible ? 'none' : 'block';
    }
}

function closeAllActionDropdowns() {
    document.querySelectorAll('.action-menu').forEach(menu => {
        menu.style.display = 'none';
    });
}

function showMessage(message, type) {
    const messageContainer = document.getElementById('messageContainer');
    if (!messageContainer) {
        console.log('Message container not found');
        return;
    }
    
    const icon = type === 'success' ? 'check-circle' : 
                 type === 'error' ? 'exclamation-circle' : 
                 type === 'warning' ? 'exclamation-triangle' : 'info-circle';
    
    messageContainer.innerHTML = `
        <div class="alert alert-${type}">
            <i class="fas fa-${icon}"></i>
            ${message}
        </div>
    `;
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        messageContainer.innerHTML = '';
    }, 5000);
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

// Notification functionality
function initNotificationSystem() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationMenu = document.getElementById('notificationMenu');
    const markAllReadBtn = document.getElementById('markAllRead');
    const notificationList = document.getElementById('notificationList');
    
    if (notificationBtn && notificationMenu) {
        // Toggle notification dropdown
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleNotificationMenu();
        });
        
        // Mark all as read
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', markAllAsRead);
        }
        
        // Close notification menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-dropdown')) {
                closeNotificationMenu();
            }
        });
        
        // Handle notification item clicks
        if (notificationList) {
            notificationList.addEventListener('click', function(e) {
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

    fetch('advisor_thesis-group.php', {
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

    fetch('advisor_thesis-group.php', {
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

    fetch('advisor_thesis-group.php', {
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

// Make functions globally available
window.showCreateGroupModal = showCreateGroupModal;
window.closeCreateGroupModal = closeCreateGroupModal;
window.createGroup = createGroup;
window.editGroup = editGroup;
window.updateGroup = updateGroup;
window.closeEditGroupModal = closeEditGroupModal;
window.deleteGroup = deleteGroup;
window.confirmDeleteGroup = confirmDeleteGroup;
window.closeConfirmModal = closeConfirmModal;
window.toggleActionDropdown = toggleActionDropdown;
window.closeLogoutModal = hideLogoutModal;
window.confirmLogout = function() {
    window.location.href = "../logout.php";
};
