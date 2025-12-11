// Coordinator Dashboard JavaScript

document.addEventListener("DOMContentLoaded", () => {
  // Initialize tab switching
  initializeTabs()

  // Initialize user tabs
  initializeUserTabs()

  // Initialize filters
  initializeFilters()

  // Initialize charts
  initializeCharts()

  showMessage("Welcome to the coordinator dashboard!", "info")
})

function initializeTabs() {
  const navItems = document.querySelectorAll(".nav-item[data-tab]")
  const tabContents = document.querySelectorAll(".tab-content")

  navItems.forEach((item) => {
    item.addEventListener("click", function (e) {
      e.preventDefault()

      // Remove active class from all nav items and tab contents
      navItems.forEach((nav) => nav.classList.remove("active"))
      tabContents.forEach((tab) => tab.classList.remove("active"))

      // Add active class to clicked nav item
      this.classList.add("active")

      // Show corresponding tab content
      const tabId = this.getAttribute("data-tab")
      const tabContent = document.getElementById(tabId)
      if (tabContent) {
        tabContent.classList.add("active")
      }
    })
  })
}

function initializeUserTabs() {
  const tabButtons = document.querySelectorAll(".tab-btn[data-user-tab]")
  const userTabContents = document.querySelectorAll(".user-tab-content")

  tabButtons.forEach((button) => {
    button.addEventListener("click", function () {
      // Remove active class from all tab buttons and contents
      tabButtons.forEach((btn) => btn.classList.remove("active"))
      userTabContents.forEach((content) => content.classList.remove("active"))

      // Add active class to clicked button
      this.classList.add("active")

      // Show corresponding tab content
      const tabId = this.getAttribute("data-user-tab")
      const tabContent = document.getElementById(tabId)
      if (tabContent) {
        tabContent.classList.add("active")
      }
    })
  })
}

function initializeFilters() {
  const departmentFilter = document.getElementById("departmentFilter")
  const statusFilter = document.getElementById("statusFilter")

  if (departmentFilter) {
    departmentFilter.addEventListener("change", () => {
      filterProjects()
    })
  }

  if (statusFilter) {
    statusFilter.addEventListener("change", () => {
      filterProjects()
    })
  }
}

function filterProjects() {
  const departmentFilter = document.getElementById("departmentFilter").value
  const statusFilter = document.getElementById("statusFilter").value
  const projectCards = document.querySelectorAll(".project-card")

  projectCards.forEach((card) => {
    const department = card.getAttribute("data-department")
    const status = card.getAttribute("data-status")

    const departmentMatch = departmentFilter === "all" || department === departmentFilter
    const statusMatch = statusFilter === "all" || status === statusFilter

    if (departmentMatch && statusMatch) {
      card.style.display = "block"
    } else {
      card.style.display = "none"
    }
  })

  showMessage("Projects filtered successfully!", "info")
}

function initializeCharts() {
  // Animate progress bars
  const progressBars = document.querySelectorAll(".progress-fill")

  progressBars.forEach((bar) => {
    const width = bar.style.width
    bar.style.width = "0%"

    setTimeout(() => {
      bar.style.width = width
    }, 500)
  })
}

// User management functions
function viewUserDetails(userId) {
  const userData = {
    "michael-chen": {
      name: "Dr. Michael Chen",
      role: "Professor",
      department: "Computer Science",
      email: "michael.chen@university.edu",
      phone: "+1 (555) 111-2222",
      students: 3,
      projects: 2,
      joinDate: "January 2020",
    },
    "amanda-rodriguez": {
      name: "Dr. Amanda Rodriguez",
      role: "Professor",
      department: "Engineering",
      email: "amanda.rodriguez@university.edu",
      phone: "+1 (555) 333-4444",
      students: 5,
      projects: 4,
      joinDate: "March 2019",
    },
    "sarah-johnson": {
      name: "Sarah Johnson",
      role: "Student",
      department: "Computer Science",
      email: "sarah.johnson@university.edu",
      phone: "+1 (555) 123-4567",
      advisor: "Dr. Michael Chen",
      progress: 75,
      thesis: "Machine Learning in Healthcare",
    },
  }

  const user = userData[userId]
  if (user) {
    showUserDetailsModal(user)
  }
}

function showUserDetailsModal(user) {
  const modal = document.createElement("div")
  modal.className = "modal show"

  let content = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>User Details</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="user-details">
                    <h4>${user.name}</h4>
                    <p><strong>Role:</strong> ${user.role}</p>
                    <p><strong>Department:</strong> ${user.department}</p>
                    <p><strong>Email:</strong> ${user.email}</p>
                    <p><strong>Phone:</strong> ${user.phone}</p>
    `

  if (user.role === "Professor") {
    content += `
            <p><strong>Students:</strong> ${user.students}</p>
            <p><strong>Active Projects:</strong> ${user.projects}</p>
            <p><strong>Join Date:</strong> ${user.joinDate}</p>
        `
  } else if (user.role === "Student") {
    content += `
            <p><strong>Advisor:</strong> ${user.advisor}</p>
            <p><strong>Thesis:</strong> ${user.thesis}</p>
            <p><strong>Progress:</strong> ${user.progress}%</p>
            <div class="progress-bar" style="margin-top: 1rem;">
                <div class="progress-fill" style="width: ${user.progress}%"></div>
            </div>
        `
  }

  content += `
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    `

  modal.innerHTML = content
  document.body.appendChild(modal)
}

function approveUser(userId) {
  showMessage(`User ${userId} has been approved!`, "success")

  // Remove from pending list
  const userItem = document.querySelector(`[onclick*="${userId}"]`).closest(".user-item")
  if (userItem) {
    userItem.remove()
  }

  // Update statistics
  updateStatistics()
}

function rejectUser(userId) {
  showMessage(`User ${userId} has been rejected.`, "info")

  // Remove from pending list
  const userItem = document.querySelector(`[onclick*="${userId}"]`).closest(".user-item")
  if (userItem) {
    userItem.remove()
  }
}

function suspendUser(userId) {
  const confirmSuspend = confirm(`Are you sure you want to suspend ${userId}?`)

  if (confirmSuspend) {
    showMessage(`User ${userId} has been suspended.`, "info")

    // Update user status
    const userItem = document.querySelector(`[onclick*="${userId}"]`).closest(".user-item")
    if (userItem) {
      const statusElement = userItem.querySelector(".status")
      statusElement.textContent = "Suspended"
      statusElement.className = "status pending"
    }
  }
}

function updateStatistics() {
  // Update active students count
  const studentsCount = document.querySelector(".stat-number")
  if (studentsCount) {
    const currentCount = Number.parseInt(studentsCount.textContent)
    studentsCount.textContent = currentCount + 1
  }
}

// Report functions
function generateReport() {
  showMessage("Generating new report...", "info")

  // Simulate report generation
  setTimeout(() => {
    const reportCard = createReportCard()
    const reportsGrid = document.querySelector(".reports-grid")
    reportsGrid.insertBefore(reportCard, reportsGrid.firstChild)

    showMessage("Report generated successfully!", "success")
  }, 3000)
}

function createReportCard() {
  const reportCard = document.createElement("div")
  reportCard.className = "card report-card"

  const currentDate = new Date().toLocaleDateString()

  reportCard.innerHTML = `
        <h4>Custom Report</h4>
        <p>Generated report based on current system data</p>
        <div class="report-meta">
            <span>Generated: ${currentDate}</span>
            <span>All Data Included</span>
        </div>
        <div class="report-actions">
            <button class="btn-secondary" onclick="viewReport('custom-${Date.now()}')">View</button>
            <button class="btn-secondary" onclick="downloadReport('custom-${Date.now()}')">Download</button>
        </div>
    `

  return reportCard
}

function viewReport(reportId) {
  showMessage(`Viewing report: ${reportId}`, "info")

  // In a real application, this would open the report in a new window or modal
  setTimeout(() => {
    showMessage("Report opened successfully!", "success")
  }, 1000)
}

function downloadReport(reportId) {
  showMessage(`Downloading report: ${reportId}...`, "info")

  // Simulate download
  setTimeout(() => {
    showMessage("Report downloaded successfully!", "success")
  }, 2000)
}

// Modal functions
function closeModal() {
  const modals = document.querySelectorAll(".modal")
  modals.forEach((modal) => {
    modal.classList.remove("show")
    if (!modal.id) {
      modal.remove()
    }
  })
}

// Sidebar toggle for mobile
function toggleSidebar() {
  const sidebar = document.querySelector(".sidebar")
  sidebar.classList.toggle("open")
}

// Utility functions
function showMessage(text, type = "info") {
  // Remove existing messages
  const existingMessages = document.querySelectorAll(".message")
  existingMessages.forEach((msg) => msg.remove())

  // Create new message
  const message = document.createElement("div")
  message.className = `message ${type}`
  message.textContent = text

  // Insert at top of main content
  const mainContent = document.querySelector(".main-content")
  const header = document.querySelector(".main-header")
  mainContent.insertBefore(message, header.nextSibling)

  // Auto remove after 5 seconds
  setTimeout(() => {
    message.remove()
  }, 5000)
}

// Simulate real-time updates
function simulateRealTimeUpdates() {
  setInterval(() => {
    // Update statistics randomly
    const statNumbers = document.querySelectorAll(".stat-number")
    statNumbers.forEach((stat) => {
      const currentValue = Number.parseInt(stat.textContent)
      const change = Math.random() > 0.5 ? 1 : -1
      const newValue = Math.max(0, currentValue + change)
      stat.textContent = newValue
    })

    // Add new activity
    addRandomActivity()
  }, 60000) // Update every minute
}

function addRandomActivity() {
  const activities = [
    { icon: "👤", text: "New user registration", time: "Just now" },
    { icon: "📋", text: "Project status updated", time: "Just now" },
    { icon: "✅", text: "Thesis chapter approved", time: "Just now" },
    { icon: "📝", text: "Feedback submitted", time: "Just now" },
  ]

  const randomActivity = activities[Math.floor(Math.random() * activities.length)]
  const activityList = document.querySelector(".activity-list")

  if (activityList) {
    const activityItem = document.createElement("div")
    activityItem.className = "activity-item"
    activityItem.innerHTML = `
            <div class="activity-icon">${randomActivity.icon}</div>
            <div class="activity-content">
                <p>${randomActivity.text}</p>
                <span class="activity-time">${randomActivity.time}</span>
            </div>
        `

    activityList.insertBefore(activityItem, activityList.firstChild)

    // Remove oldest activity if more than 5
    const activities = activityList.querySelectorAll(".activity-item")
    if (activities.length > 5) {
      activities[activities.length - 1].remove()
    }
  }
}

// Initialize real-time updates
setTimeout(simulateRealTimeUpdates, 10000)

// Close modal when clicking outside
window.addEventListener("click", (event) => {
  const modals = document.querySelectorAll(".modal.show")
  modals.forEach((modal) => {
    if (event.target === modal) {
      closeModal()
    }
  })
})

// Handle window resize
window.addEventListener("resize", () => {
  const sidebar = document.querySelector(".sidebar")
  if (window.innerWidth > 768) {
    sidebar.classList.remove("open")
  }
})
