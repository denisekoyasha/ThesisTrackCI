// Student Dashboard JavaScript

// Tab switching functionality
document.addEventListener("DOMContentLoaded", () => {
  // Initialize tab switching
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

  // Initialize file upload functionality
  initializeFileUpload()

  // Initialize drag and drop for Kanban
  initializeKanban()

  // Show welcome message
  showMessage("Welcome to your thesis dashboard!", "info")
})

// File upload functionality
function initializeFileUpload() {
  const uploadArea = document.getElementById("uploadArea")
  const fileInput = document.getElementById("fileInput")
  const uploadedFiles = document.getElementById("uploadedFiles")

  if (!uploadArea || !fileInput) return

  // Drag and drop events
  uploadArea.addEventListener("dragover", (e) => {
    e.preventDefault()
    uploadArea.classList.add("dragover")
  })

  uploadArea.addEventListener("dragleave", (e) => {
    e.preventDefault()
    uploadArea.classList.remove("dragover")
  })

  uploadArea.addEventListener("drop", (e) => {
    e.preventDefault()
    uploadArea.classList.remove("dragover")

    const files = e.dataTransfer.files
    handleFiles(files)
  })

  // File input change event
  fileInput.addEventListener("change", (e) => {
    const files = e.target.files
    handleFiles(files)
  })

  // Click to upload
  uploadArea.addEventListener("click", () => {
    fileInput.click()
  })
}

function handleFiles(files) {
  const uploadedFiles = document.getElementById("uploadedFiles")

  Array.from(files).forEach((file) => {
    // Simulate file upload
    const fileItem = createFileItem(file.name, formatFileSize(file.size))
    uploadedFiles.appendChild(fileItem)

    // Show success message
    showMessage(`File "${file.name}" uploaded successfully!`, "success")
  })
}

function createFileItem(fileName, fileSize) {
  const fileItem = document.createElement("div")
  fileItem.className = "file-item"
  fileItem.innerHTML = `
        <span class="file-icon">📄</span>
        <span class="file-name">${fileName}</span>
        <span class="file-size">${fileSize}</span>
        <button class="btn-danger-small" onclick="removeFile(this)">×</button>
    `
  return fileItem
}

function formatFileSize(bytes) {
  if (bytes === 0) return "0 Bytes"
  const k = 1024
  const sizes = ["Bytes", "KB", "MB", "GB"]
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Number.parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i]
}

function removeFile(button) {
  const fileItem = button.parentElement
  const fileName = fileItem.querySelector(".file-name").textContent
  fileItem.remove()
  showMessage(`File "${fileName}" removed.`, "info")
}

// Kanban functionality
function initializeKanban() {
  const cards = document.querySelectorAll(".kanban-card")
  const columns = document.querySelectorAll(".kanban-cards")

  cards.forEach((card) => {
    card.addEventListener("dragstart", handleDragStart)
    card.addEventListener("dragend", handleDragEnd)
  })

  columns.forEach((column) => {
    column.addEventListener("dragover", handleDragOver)
    column.addEventListener("drop", handleDrop)
    column.addEventListener("dragenter", handleDragEnter)
    column.addEventListener("dragleave", handleDragLeave)
  })
}

let draggedElement = null

function handleDragStart(e) {
  draggedElement = this
  this.classList.add("dragging")
  e.dataTransfer.effectAllowed = "move"
  e.dataTransfer.setData("text/html", this.outerHTML)
}

function handleDragEnd(e) {
  this.classList.remove("dragging")
  draggedElement = null
}

function handleDragOver(e) {
  e.preventDefault()
  e.dataTransfer.dropEffect = "move"
}

function handleDragEnter(e) {
  e.preventDefault()
  this.parentElement.classList.add("drag-over")
}

function handleDragLeave(e) {
  e.preventDefault()
  this.parentElement.classList.remove("drag-over")
}

function handleDrop(e) {
  e.preventDefault()
  this.parentElement.classList.remove("drag-over")

  if (draggedElement && draggedElement !== this) {
    this.appendChild(draggedElement)

    // Update task status based on column
    const columnStatus = this.parentElement.getAttribute("data-status")
    updateTaskStatus(draggedElement, columnStatus)

    showMessage("Task moved successfully!", "success")
  }
}

function updateTaskStatus(card, status) {
  const statusMap = {
    todo: "To Do",
    "in-progress": "In Progress",
    review: "Under Review",
    done: "Completed",
  }

  // Update card appearance based on status
  const priority = card.querySelector(".priority")
  if (status === "done" && priority) {
    priority.textContent = "Completed"
    priority.className = "priority low"
  }
}

// Drag and drop functions (for compatibility)
function allowDrop(ev) {
  ev.preventDefault()
}

function drag(ev) {
  ev.dataTransfer.setData("text", ev.target.id)
}

function drop(ev) {
  ev.preventDefault()
  var data = ev.dataTransfer.getData("text")
  var draggedElement = document.getElementById(data)

  if (draggedElement && ev.target.classList.contains("kanban-cards")) {
    ev.target.appendChild(draggedElement)
    showMessage("Task moved successfully!", "success")
  }
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
    // Randomly update progress
    const progressFills = document.querySelectorAll(".progress-fill")
    progressFills.forEach((fill) => {
      const currentWidth = Number.parseInt(fill.style.width) || 0
      const newWidth = Math.min(currentWidth + Math.random() * 2, 100)
      fill.style.width = newWidth + "%"
    })
  }, 30000) // Update every 30 seconds
}

// Initialize real-time updates
setTimeout(simulateRealTimeUpdates, 5000)

// Handle window resize
window.addEventListener("resize", () => {
  const sidebar = document.querySelector(".sidebar")
  if (window.innerWidth > 768) {
    sidebar.classList.remove("open")
  }
})
