// coordinator_advisor-mngt.js
// Global variables
let currentAdvisorId = null;
let advisorDropdownTimeout = null;
let credentialsTimeout = null;
let currentAdvisorToDelete = null;

const CREDENTIALS_DISPLAY_TIMEOUT = 120000;

document.addEventListener("DOMContentLoaded", () => {
  initializeTabs();
  initializeEventListeners();
  initializeDropdowns();
  initializeTooltips();
  initializeNotifications();

  // Add smooth scrolling
  document.documentElement.style.scrollBehavior = "smooth";

  // Add loading states to buttons
  const buttons = document.querySelectorAll("button");
  buttons.forEach((button) => {
    button.addEventListener("click", function () {
      if (this.type === "submit" || this.classList.contains("btn-primary")) {
        this.style.position = "relative";
      }
    });
  });
});

function initializeTabs() {
  const navItems = document.querySelectorAll(".nav-item[data-tab]");
  const tabContents = document.querySelectorAll(".tab-content");

  // Activate current tab based on URL
  const currentPage = window.location.pathname.split("/").pop();
  navItems.forEach((item) => {
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
  navItems.forEach((item) => {
    item.addEventListener("click", function (e) {
      const tabId = this.getAttribute("data-tab");
      const tabContent = document.getElementById(tabId);

      if (tabContent) {
        e.preventDefault();
        navItems.forEach((nav) => nav.classList.remove("active"));
        tabContents.forEach((tab) => tab.classList.remove("active"));
        this.classList.add("active");
        tabContent.classList.add("active");
      }
    });
  });
}

function initializeEventListeners() {
  // Handle edit advisor clicks
  document.addEventListener("click", function (e) {
    // Handle edit advisor clicks
    if (
      e.target.classList.contains("edit-advisor") ||
      (e.target.parentElement &&
        e.target.parentElement.classList.contains("edit-advisor"))
    ) {
      e.preventDefault();
      const dropdown = e.target.closest(".advisor-dropdown");
      const advisorId = dropdown.id.replace("advisor-dropdown-", "");
      editAdvisor(advisorId);
      closeAdvisorDropdown(dropdown.id);
    }

    // Add section
    if (e.target.closest(".add-section")) {
      e.preventDefault();
      const advisorId = e.target
        .closest(".add-section")
        .getAttribute("data-advisor-id");
      openAddSectionModal(advisorId, e);
    }

    // Handle remove advisor clicks
    if (
      e.target.classList.contains("remove-advisor") ||
      (e.target.parentElement &&
        e.target.parentElement.classList.contains("remove-advisor"))
    ) {
      e.preventDefault();
      const dropdown = e.target.closest(".advisor-dropdown");
      const advisorId = dropdown.id.replace("advisor-dropdown-", "");
      confirmRemoveAdvisor(advisorId, e);
      closeAdvisorDropdown(dropdown.id);
    }
  });

  // Logout functionality
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

  // Close logout modal when clicking outside
  const logoutModal = document.getElementById("logoutModal");
  if (logoutModal) {
    logoutModal.addEventListener("click", (e) => {
      if (e.target === logoutModal) {
        closeLogoutModal();
      }
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

  // Form submission
  const advisorForm = document.getElementById("advisorForm");
  if (advisorForm) {
    advisorForm.addEventListener("submit", (e) => {
      e.preventDefault();
      saveAdvisor();
    });
  }

  // Close modals with Escape key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeModal();
      closePasswordModal();
      closeLogoutModal();
      closeAddSectionModal();
      closeConfirmModal();
    }
  });

  // Close advisor dropdowns when clicking outside
  document.addEventListener("click", (e) => {
    const dropdowns = document.querySelectorAll(".advisor-dropdown-menu");
    dropdowns.forEach((dropdown) => {
      if (!dropdown.closest(".advisor-dropdown").contains(e.target)) {
        dropdown.classList.remove("show");
      }
    });
  });

  // Handle Enter key in forms
  document.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && e.target.tagName !== "TEXTAREA") {
      const advisorModal = document.getElementById("advisorModal");
      const addSectionModal = document.getElementById("addSectionModal");

      if (advisorModal && advisorModal.style.display === "block") {
        e.preventDefault();
        saveAdvisor();
      } else if (addSectionModal && addSectionModal.style.display === "block") {
        e.preventDefault();
        saveNewSection();
      }
    }
  });

  // Handle Tab navigation in modals
  document.addEventListener("keydown", (e) => {
    if (e.key === "Tab") {
      const modal = document.querySelector(".modal[style*='block']");
      if (modal) {
        const focusableElements = modal.querySelectorAll(
          "button, [href], input, select, textarea, [tabindex]:not([tabindex='-1'])"
        );
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (e.shiftKey) {
          if (document.activeElement === firstElement) {
            lastElement.focus();
            e.preventDefault();
          }
        } else {
          if (document.activeElement === lastElement) {
            firstElement.focus();
            e.preventDefault();
          }
        }
      }
    }
  });
}

function initializeDropdowns() {
  // Close all dropdowns initially
  const dropdowns = document.querySelectorAll(".advisor-dropdown-menu");
  dropdowns.forEach((dropdown) => {
    dropdown.classList.remove("show");
  });
}

function initializeTooltips() {
  const tooltipElements = document.querySelectorAll("[title]");
  tooltipElements.forEach((element) => {
    element.addEventListener("mouseenter", showTooltip);
    element.addEventListener("mouseleave", hideTooltip);
  });
}

function showTooltip(e) {
  // Tooltip implementation for better UX
  const tooltip = document.createElement("div");
  tooltip.className = "tooltip";
  tooltip.textContent = e.target.getAttribute("title");
  document.body.appendChild(tooltip);

  const rect = e.target.getBoundingClientRect();
  tooltip.style.left =
    rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + "px";
  tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + "px";
}

function hideTooltip() {
  const tooltip = document.querySelector(".tooltip");
  if (tooltip) {
    tooltip.remove();
  }
}

// ==================== Logout Modal Functions ====================

function showLogoutModal() {
  const modal = document.getElementById("logoutModal");
  if (modal) {
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";

    // Add event listener for ESC key
    document.addEventListener("keydown", handleLogoutEscKey);
  }
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

// ==================== Advisor Dropdown Functions ====================

function toggleAdvisorDropdown(dropdownId, event) {
  event.stopPropagation(); // Prevent the click from bubbling up

  const dropdown = document.getElementById(dropdownId);
  if (!dropdown) return;

  const menu = dropdown.querySelector(".advisor-dropdown-menu");
  if (!menu) return;

  // Close all other dropdowns first
  document.querySelectorAll(".advisor-dropdown-menu").forEach((otherMenu) => {
    if (otherMenu !== menu && otherMenu.style.display === "block") {
      otherMenu.style.display = "none";
    }
  });

  // Toggle the clicked dropdown
  menu.style.display = menu.style.display === "block" ? "none" : "block";
}

function closeAdvisorDropdown(dropdownId) {
  const dropdown = document.getElementById(dropdownId);
  if (!dropdown) return;

  const menu = dropdown.querySelector(".advisor-dropdown-menu");
  if (menu) {
    menu.classList.remove("show");
  }

  if (advisorDropdownTimeout) {
    clearTimeout(advisorDropdownTimeout);
  }
}

// ==================== Advisor CRUD Functions ====================

/**
 * Opens modal to add a new advisor
 */
function addNewAdvisor() {
  currentAdvisorId = null;
  document.getElementById("advisorModalTitle").innerHTML =
    '<i class="fas fa-user-plus"></i> Add New Advisor';
  document.getElementById("advisorForm").reset();
  document.getElementById("advisorId").value = "";
  showModal("advisorModal");
}

function editAdvisor(advisorId, event) {
  if (event) event.preventDefault();

  currentAdvisorId = advisorId;

  const editButton = document.querySelector(
    `a.edit-advisor[data-advisor-id="${advisorId}"]`
  );

  if (!editButton) {
    console.error("Edit button not found for advisor ID:", advisorId);
    showMessage("Advisor button not found.", "error");
    return;
  }

  const originalText = editButton.innerHTML;
  editButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  editButton.style.pointerEvents = "none";

  fetch("coordinator_advisor-mngt.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `action=get_advisor&id=${advisorId}`,
  })
    .then(async (response) => {
      const text = await response.text();

      try {
        const data = JSON.parse(text);

        if (data.success) {
          // Set modal title
          document.getElementById("advisorModalTitle").innerHTML =
            '<i class="fas fa-user-edit"></i> Edit Advisor';

          // Fill form fields
          document.getElementById("advisorFirstName").value =
            data.data.first_name || "";
          document.getElementById("advisorMiddleName").value =
            data.data.middle_name || "";
          document.getElementById("advisorLastName").value =
            data.data.last_name || "";
          document.getElementById("advisorEmail").value = data.data.email || "";
          document.getElementById("advisorSpecialization").value =
            data.data.specialization || "";
          document.getElementById("advisorId").value = advisorId;

          // Handle section dropdown: re-enable current section if disabled
          const sectionDropdown = document.getElementById("advisorSection");
          const currentSection = data.data.section_handled;

          // Re-enable current section if it's in the list
          const option = Array.from(sectionDropdown.options).find(
            (opt) => opt.value === currentSection
          );
          if (option) {
            option.disabled = false; // In case it's disabled
            option.selected = true;
          } else {
            // If the current section is not listed, append it
            const fallbackOption = new Option(
              currentSection,
              currentSection,
              true,
              true
            );
            sectionDropdown.add(fallbackOption);
          }

          // Show modal
          showModal("advisorModal");
        } else {
          showMessage(
            data.message || "Advisor data could not be loaded.",
            "error"
          );
        }
      } catch (err) {
        console.error("Invalid JSON response:", text);
        showMessage(
          "Unexpected server response. Please check PHP output or errors.",
          "error"
        );
      }
    })
    .catch((error) => {
      console.error("Fetch error:", error);
      showMessage("Error loading advisor data. Try again.", "error");
    })
    .finally(() => {
      editButton.innerHTML = originalText;
      editButton.style.pointerEvents = "auto";
    });
}

/**
 * Assign another section to advisor
 * @param {number} advisorId - ID of the advisor to delete
 * @param {Event} event - The click event
 */

function openAddSectionModal(advisorId, event) {
  if (event) event.preventDefault();

  currentAdvisorId = advisorId;
  const addSectionButton = document.querySelector(
    `a.add-section[data-advisor-id="${advisorId}"]`
  );

  if (addSectionButton) {
    const originalText = addSectionButton.innerHTML;
    addSectionButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    addSectionButton.style.pointerEvents = "none";

    // Fetch advisor data to show current sections
    fetch("coordinator_advisor-mngt.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `action=get_advisor&id=${advisorId}`,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          document.getElementById("addSectionAdvisorId").value = advisorId;
          document.getElementById("newSection").value = "";
          showModal("addSectionModal");
        } else {
          showMessage(
            data.message || "Failed to load advisor data",
            "error"
          );
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showMessage(
          "An error occurred while loading advisor data",
          "error"
        );
      })
      .finally(() => {
        if (addSectionButton) {
          addSectionButton.innerHTML = originalText;
          addSectionButton.style.pointerEvents = "auto";
        }
      });
  }
}

function saveNewSection() {
  const advisorId = document.getElementById("addSectionAdvisorId").value;
  const section = document.getElementById("newSection").value;
  const sectionSelect = document.getElementById("newSection");

  if (sectionSelect.options[sectionSelect.selectedIndex].disabled) {
    showMessage(
      "This section is already assigned to another advisor",
      "error"
    );
    return;
  }

  if (!section) {
    showMessage("Please select a section to add", "error");
    return;
  }

  const saveButton = document.querySelector("#addSectionModal .btn-primary");
  const originalText = saveButton.innerHTML;
  saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  saveButton.disabled = true;

  fetch("coordinator_advisor-mngt.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `action=add_section&advisor_id=${advisorId}&section=${encodeURIComponent(
      section
    )}`,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showMessage(data.message || "Section added successfully", "success");
        closeAddSectionModal();
        setTimeout(() => window.location.reload(), 1500);
      } else {
        showMessage(data.message || "Failed to add section", "error");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showMessage("An error occurred while adding the section", "error");
    })
    .finally(() => {
      saveButton.innerHTML = originalText;
      saveButton.disabled = false;
    });
}

function closeAddSectionModal() {
  const modal = document.getElementById("addSectionModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
    document.getElementById("addSectionForm").reset();
  }
}

/**
 * Shows confirmation modal for advisor deletion
 * @param {number} advisorId - ID of the advisor to delete
 * @param {Event} event - The click event
 */
function confirmRemoveAdvisor(advisorId, event) {
  if (event) {
    event.preventDefault();
    event.stopPropagation(); // Add this to prevent event bubbling
  }

  currentAdvisorToDelete = advisorId;

  // Set modal content
  document.getElementById("confirmModalTitle").textContent =
    "Confirm Advisor Removal";
  document.getElementById("confirmModalMessage").textContent =
    "Are you sure you want to remove this advisor? This action cannot be undone.";

  // Clear any previous click handlers
  const confirmBtn = document.getElementById("confirmActionBtn");
  confirmBtn.onclick = null;

  // Set new click handler
  confirmBtn.addEventListener("click", function confirmHandler() {
    removeAdvisor(currentAdvisorToDelete);
    closeConfirmModal();
    // Remove this event listener after use
    confirmBtn.removeEventListener("click", confirmHandler);
  });

  // Show modal
  showModal("confirmModal");
}

/**
 * Deletes an advisor after confirmation
 * @param {number} advisorId - ID of the advisor to delete
 */
function removeAdvisor(advisorId) {
  if (!advisorId) return; // Additional safety check

  const deleteButton = document.querySelector(
    `a.remove-advisor[data-advisor-id="${advisorId}"]`
  );

  if (!deleteButton) {
    console.error("Delete button not found for advisor ID:", advisorId);
    showMessage("An error occurred while removing advisor", "error");
    return;
  }

  // Show loading state
  const originalText = deleteButton.innerHTML;
  deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  deleteButton.style.pointerEvents = "none";

  fetch("coordinator_advisor-mngt.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `action=delete_advisor&id=${advisorId}`,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showMessage(data.message || "Advisor deleted successfully", "success");
        const advisorRow = document.querySelector(
          `tr[data-advisor-id="${advisorId}"]`
        );
        if (advisorRow) {
          advisorRow.style.opacity = "0.5";
          setTimeout(() => advisorRow.remove(), 500);
        } else {
          setTimeout(() => window.location.reload(), 1500);
        }
      } else {
        throw new Error(data.message || "Failed to delete advisor");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showMessage(
        error.message || "An error occurred while removing the advisor.",
        "error"
      );
    })
    .finally(() => {
      if (deleteButton) {
        deleteButton.innerHTML = originalText;
        deleteButton.style.pointerEvents = "auto";
      }
    });
}

function closeConfirmModal() {
  const modal = document.getElementById("confirmModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
    currentAdvisorToDelete = null;
  }
}

function showModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.style.display = "block";
    document.body.style.overflow = "hidden";

    // Add event listener for ESC key
    document.addEventListener("keydown", handleEscKey);

    // Focus on first input field
    setTimeout(() => {
      const firstInput = modal.querySelector(
        'input:not([type="hidden"]), select, textarea'
      );
      if (firstInput) firstInput.focus();
    }, 100);
  }
}

/**
 * Closes the advisor modal
 */
function closeModal() {
  const modal = document.getElementById("advisorModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
    currentAdvisorId = null;

    // Remove ESC key listener
    document.removeEventListener("keydown", handleEscKey);
  }
}

/**
 * Handles ESC key press to close modal
 */
function handleEscKey(event) {
  if (event.key === "Escape") {
    closeModal();
    closeAddSectionModal();
    closeConfirmModal();
  }
}

/**
 * Saves advisor data (both add and edit)
 */
function saveAdvisor() {
  if (!validateAdvisorForm()) {
    return;
  }

  const form = document.getElementById("advisorForm");
  const formData = new FormData(form);
  const action = currentAdvisorId ? "edit_advisor" : "add_advisor";

  // Show loading state
  const saveButton = document.querySelector("#advisorModal .btn-primary");
  const originalText = saveButton.innerHTML;
  saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  saveButton.disabled = true;

  fetch("coordinator_advisor-mngt.php", {
    method: "POST",
    body: new URLSearchParams([...formData]).toString() + `&action=${action}`,
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showMessage(data.message || "Advisor saved successfully", "success");
        closeModal();

        if (action === "add_advisor" && data.temp_password) {
          // Show password modal for new advisor
          showPasswordModal(data.temp_password, data.employee_id, data.email);

          // Clear any existing timeout
          if (credentialsTimeout) {
            clearTimeout(credentialsTimeout);
          }

          // Set new timeout
          credentialsTimeout = setTimeout(() => {
            closePasswordModal();
            window.location.reload();
          }, CREDENTIALS_DISPLAY_TIMEOUT);
        } else {
          // For edits or if no password needed, reload immediately
          setTimeout(() => window.location.reload(), 1500);
        }
      } else {
        showMessage(data.message || "Failed to save advisor", "error");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showMessage("An error occurred while saving the advisor.", "error");
    })
    .finally(() => {
      saveButton.innerHTML = originalText;
      saveButton.disabled = false;
    });
}

// ==================== Modal Functions ====================

/**
 * Shows the password display modal
 * @param {string} tempPassword - Temporary password generated
 * @param {string} employeeId - Employee ID assigned
 * @param {string} email - Advisor's email
 */
function showPasswordModal(tempPassword, employeeId, email) {
  const modal = document.getElementById("passwordModal");
  if (modal) {
    document.getElementById("tempPassword").textContent = tempPassword;
    document.getElementById("employeeId").textContent = employeeId;
    document.getElementById("createdEmail").textContent = email;

    modal.style.display = "block";
    document.body.style.overflow = "hidden";
  }
}

/**
 * Closes the password modal
 */
function closePasswordModal() {
  const modal = document.getElementById("passwordModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";

    // Clear the timeout if modal is closed manually
    if (credentialsTimeout) {
      clearTimeout(credentialsTimeout);
      credentialsTimeout = null;
    }

    // Reload the page to show the new advisor
    window.location.reload();
  }
}

// ==================== Utility Functions ====================

/**
 * Validates the advisor form
 * @returns {boolean} True if form is valid, false otherwise
 */
function validateAdvisorForm() {
  const firstName = document.getElementById("advisorFirstName").value.trim();
  const lastName = document.getElementById("advisorLastName").value.trim();
  const email = document.getElementById("advisorEmail").value.trim();
  const specialization = document.getElementById("advisorSpecialization").value;
  const section = document.getElementById("advisorSection").value;

  if (!firstName) {
    showMessage("Please enter the advisor's first name.", "error");
    document.getElementById("advisorFirstName").focus();
    return false;
  }

  if (!lastName) {
    showMessage("Please enter the advisor's last name.", "error");
    document.getElementById("advisorLastName").focus();
    return false;
  }

  if (!email) {
    showMessage("Please enter the email address.", "error");
    document.getElementById("advisorEmail").focus();
    return false;
  }

  if (!isValidEmail(email)) {
    showMessage("Please enter a valid email address.", "error");
    document.getElementById("advisorEmail").focus();
    return false;
  }

  if (!specialization) {
    showMessage("Please select a specialization.", "error");
    document.getElementById("advisorSpecialization").focus();
    return false;
  }

  if (!section) {
    showMessage("Please select a section.", "error");
    document.getElementById("advisorSection").focus();
    return false;
  }

  return true;
}

/**
 * Validates an email address
 * @param {string} email - Email to validate
 * @returns {boolean} True if email is valid
 */
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

/**
 * Copies credentials to clipboard
 */
function copyCredentials() {
  const email = document.getElementById("createdEmail").textContent;
  const password = document.getElementById("tempPassword").textContent;
  const employeeId = document.getElementById("employeeId").textContent;

  const credentials = `Email: ${email}\nTemporary Password: ${password}\nEmployee ID: ${employeeId}`;

  navigator.clipboard
    .writeText(credentials)
    .then(() => showMessage("Credentials copied to clipboard!", "success"))
    .catch((err) => {
      console.error("Failed to copy: ", err);
      fallbackCopyTextToClipboard(credentials);
    });
}

/**
 * Fallback method for copying text to clipboard
 * @param {string} text - Text to copy
 */
function fallbackCopyTextToClipboard(text) {
  const textArea = document.createElement("textarea");
  textArea.value = text;

  // Style the textarea to be invisible and not affect layout
  textArea.style.position = "fixed";
  textArea.style.top = "0";
  textArea.style.left = "0";
  textArea.style.width = "1px";
  textArea.style.height = "1px";
  textArea.style.padding = "0";
  textArea.style.border = "none";
  textArea.style.outline = "none";
  textArea.style.boxShadow = "none";
  textArea.style.background = "transparent";
  textArea.style.opacity = "0";

  document.body.appendChild(textArea);

  try {
    // Select the text without focusing
    textArea.select();
    textArea.setSelectionRange(0, textArea.value.length); // For mobile devices

    const successful = document.execCommand("copy");
    if (successful) {
      showMessage("Credentials copied to clipboard!", "success");
    } else {
      showMessage("Failed to copy credentials. Please copy manually.", "error");
    }
  } catch (err) {
    console.error("Fallback copy failed:", err);
    showMessage("Failed to copy credentials. Please copy manually.", "error");
  } finally {
    // Ensure we always remove the textarea
    document.body.removeChild(textArea);
  }
}

/**
 * Shows a message to the user
 * @param {string} message - Message to display
 * @param {string} type - Type of message (success, error, warning, info)
 */
function showMessage(message, type = "info") {
  const container = document.getElementById("messageContainer");
  if (!container) return;

  // Remove any existing messages
  container.innerHTML = "";

  const messageDiv = document.createElement("div");
  messageDiv.className = `message ${type}`;

  const icon = getMessageIcon(type);
  messageDiv.innerHTML = `
    <i class="${icon}"></i>
    <span>${message}</span>
  `;

  container.appendChild(messageDiv);

  // Auto-remove after 5 seconds
  setTimeout(() => {
    messageDiv.style.opacity = "0";
    setTimeout(() => {
      if (messageDiv.parentNode) {
        messageDiv.parentNode.removeChild(messageDiv);
      }
    }, 300);
  }, 5000);
}

/**
 * Gets the appropriate icon for a message type
 * @param {string} type - Message type
 * @returns {string} Icon class
 */
function getMessageIcon(type) {
  switch (type) {
    case "success":
      return "fas fa-check-circle";
    case "error":
      return "fas fa-exclamation-circle";
    case "warning":
      return "fas fa-exclamation-triangle";
    default:
      return "fas fa-info-circle";
  }
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

    fetch('coordinator_advisor-mngt.php', {
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

    fetch('coordinator_advisor-mngt.php', {
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

    fetch('coordinator_advisor-mngt.php', {
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

// ==================== Event Listeners ====================

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
  // Close dropdowns when clicking outside
  document.addEventListener("click", function (e) {
    if (!e.target.closest(".advisor-dropdown")) {
      document
        .querySelectorAll(".advisor-dropdown-menu")
        .forEach((menu) => {
          menu.style.display = "none";
        });
    }
  });

  // Close modals when clicking outside
  document.addEventListener("click", function (event) {
    const advisorModal = document.getElementById("advisorModal");
    if (event.target === advisorModal) {
      closeModal();
    }

    const addSectionModal = document.getElementById("addSectionModal");
    if (event.target === addSectionModal) {
      closeAddSectionModal();
    }

    const confirmModal = document.getElementById("confirmModal");
    if (event.target === confirmModal) {
      closeConfirmModal();
    }

    const passwordModal = document.getElementById("passwordModal");
    if (event.target === passwordModal) {
      closePasswordModal();
    }
  });

  // Global error handling
  window.addEventListener("error", function (e) {
    console.error("JavaScript Error:", e.error);
    showMessage("An unexpected error occurred. Please try again.", "error");
  });

  window.addEventListener("unhandledrejection", function (e) {
    console.error("Unhandled Promise Rejection:", e.reason);
    showMessage("A network error occurred. Please check your connection.", "error");
  });
});

// Make functions available globally
window.addNewAdvisor = addNewAdvisor;
window.editAdvisor = editAdvisor;
window.removeAdvisor = removeAdvisor;
window.saveAdvisor = saveAdvisor;
window.closeModal = closeModal;
window.closePasswordModal = closePasswordModal;
window.copyCredentials = copyCredentials;
window.toggleAdvisorDropdown = toggleAdvisorDropdown;

window.openAddSectionModal = openAddSectionModal;
window.saveNewSection = saveNewSection;
window.closeAddSectionModal = closeAddSectionModal;

window.showLogoutModal = showLogoutModal;
window.closeLogoutModal = closeLogoutModal;
window.confirmLogout = confirmLogout;
