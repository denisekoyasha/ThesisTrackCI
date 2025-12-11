// Professor Dashboard JavaScript

document.addEventListener("DOMContentLoaded", () => {
  // Initialize tab switching
  initializeTabs()

  // Initialize filter functionality
  initializeFilters()

  // Initialize rating system
  initializeRating()

  // Initialize user tabs
  function initializeUserTabs() {
    // Placeholder for user tabs initialization logic
    console.log("User tabs initialized")
  }
  initializeUserTabs()

  showMessage("Welcome to your professor dashboard!", "info")
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

function initializeFilters() {
  const filterButtons = document.querySelectorAll(".filter-btn")

  filterButtons.forEach((button) => {
    button.addEventListener("click", function () {
      // Remove active class from all filter buttons
      filterButtons.forEach((btn) => btn.classList.remove("active"))

      // Add active class to clicked button
      this.classList.add("active")

      // Filter submissions
      const filter = this.getAttribute("data-filter")
      filterSubmissions(filter)
    })
  })
}

function filterSubmissions(filter) {
  const submissions = document.querySelectorAll(".submission-item")

  submissions.forEach((submission) => {
    const status = submission.getAttribute("data-status")

    if (filter === "all" || status === filter) {
      submission.style.display = "flex"
    } else {
      submission.style.display = "none"
    }
  })

  showMessage(`Filtered submissions by: ${filter}`, "info")
}

function initializeRating() {
  const ratingInputs = document.querySelectorAll('.rating-input input[type="radio"]')

  ratingInputs.forEach((input) => {
    input.addEventListener("change", function () {
      const rating = this.value
      updateRatingDisplay(rating)
    })
  })
}

function updateRatingDisplay(rating) {
  const labels = document.querySelectorAll(".rating-input label")

  labels.forEach((label, index) => {
    if (index < rating) {
      label.style.color = "#f39c12"
    } else {
      label.style.color = "#ddd"
    }
  })
}

// Student management functions
function viewStudent(studentId) {
  const studentData = {
    sarah: {
      name: "Sarah Johnson",
      thesis: "Machine Learning in Healthcare",
      progress: 75,
      status: "Methodology Under Review",
      email: "sarah.johnson@university.edu",
      phone: "+1 (555) 123-4567",
      advisor: "Dr. Michael Chen",
    },
    james: {
      name: "James Wilson",
      thesis: "Blockchain Security Protocols",
      progress: 45,
      status: "Literature Review Pending",
      email: "james.wilson@university.edu",
      phone: "+1 (555) 234-5678",
      advisor: "Dr. Michael Chen",
    },
    emily: {
      name: "Emily Davis",
      thesis: "Sustainable Energy Systems",
      progress: 90,
      status: "Final Review",
      email: "emily.davis@university.edu",
      phone: "+1 (555) 345-6789",
      advisor: "Dr. Amanda Rodriguez",
    },
  }

  const student = studentData[studentId]
  if (student) {
    showStudentDetails(student)
  }
}

function showStudentDetails(student) {
  const modal = document.createElement("div")
  modal.className = "modal show"
  modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Student Details</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="student-details">
                    <h4>${student.name}</h4>
                    <p><strong>Thesis:</strong> ${student.thesis}</p>
                    <p><strong>Progress:</strong> ${student.progress}%</p>
                    <p><strong>Status:</strong> ${student.status}</p>
                    <p><strong>Email:</strong> ${student.email}</p>
                    <p><strong>Phone:</strong> ${student.phone}</p>
                    <p><strong>Advisor:</strong> ${student.advisor}</p>
                    
                    <div class="progress-bar" style="margin-top: 1rem;">
                        <div class="progress-fill" style="width: ${student.progress}%"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-primary" onclick="sendMessage('${student.name}')">Send Message</button>
                <button class="btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    `

  document.body.appendChild(modal)
}

function reviewSubmission(studentId, chapter) {
  const modal = document.getElementById("reviewModal")
  const title = document.getElementById("reviewTitle")
  const date = document.getElementById("reviewDate")

  // Set modal content
  title.textContent = `${studentId.charAt(0).toUpperCase() + studentId.slice(1)} - ${chapter.charAt(0).toUpperCase() + chapter.slice(1)}`
  date.textContent = `Submitted: ${new Date().toLocaleDateString()}`

  // Store current review data
  modal.setAttribute("data-student", studentId)
  modal.setAttribute("data-chapter", chapter)

  modal.classList.add("show")
}

function submitReview() {
  const modal = document.getElementById("reviewModal")
  const score = document.getElementById("scoreInput").value
  const status = document.getElementById("statusSelect").value
  const comments = document.getElementById("reviewComments").value

  const student = modal.getAttribute("data-student")
  const chapter = modal.getAttribute("data-chapter")

  if (!score || !comments) {
    showMessage("Please fill in all required fields.", "error")
    return
  }

  // Simulate review submission
  showMessage(`Review submitted for ${student}'s ${chapter} chapter. Score: ${score}/100`, "success")

  // Update submission status in the list
  updateSubmissionStatus(student, chapter, status)

  closeModal()
  clearReviewForm()
}

function updateSubmissionStatus(student, chapter, status) {
  const submissions = document.querySelectorAll(".submission-item")

  submissions.forEach((submission) => {
    const submissionText = submission.querySelector(".submission-info h4").textContent.toLowerCase()
    if (submissionText.includes(student) && submissionText.includes(chapter)) {
      const statusElement = submission.querySelector(".submission-status")
      statusElement.textContent = status.charAt(0).toUpperCase() + status.slice(1)
      statusElement.className = `submission-status ${status}`
      submission.setAttribute("data-status", "reviewed")
    }
  })
}

function clearReviewForm() {
  document.getElementById("scoreInput").value = ""
  document.getElementById("statusSelect").value = "approved"
  document.getElementById("reviewComments").value = ""
}

function downloadFile(filename) {
  // Simulate file download
  showMessage(`Downloading ${filename}...`, "info")

  // In a real application, this would trigger an actual download
  setTimeout(() => {
    showMessage(`${filename} downloaded successfully!`, "success")
  }, 2000)
}

function viewFeedback(studentId, chapter) {
  showMessage(`Viewing feedback for ${studentId}'s ${chapter} chapter`, "info")

  // Switch to feedback tab
  const feedbackTab = document.querySelector('.nav-item[data-tab="feedback"]')
  if (feedbackTab) {
    feedbackTab.click()
  }
}

// Feedback form functions
function submitFeedback(event) {
  event.preventDefault()

  const student = document.getElementById("studentSelect").value
  const chapter = document.getElementById("chapterSelect").value
  const rating = document.querySelector('input[name="rating"]:checked')?.value
  const feedback = document.getElementById("feedbackText").value

  if (!student || !chapter || !rating || !feedback) {
    showMessage("Please fill in all fields.", "error")
    return
  }

  // Simulate feedback submission
  showMessage(`Feedback submitted for ${student}'s ${chapter} chapter!`, "success")

  // Clear form
  clearForm()
}

function clearForm() {
  document.getElementById("studentSelect").value = ""
  document.getElementById("chapterSelect").value = ""
  document.querySelectorAll('input[name="rating"]').forEach((input) => (input.checked = false))
  document.getElementById("feedbackText").value = ""

  // Reset rating display
  document.querySelectorAll(".rating-input label").forEach((label) => {
    label.style.color = "#ddd"
  })
}

function sendMessage(studentName) {
  showMessage(`Message sent to ${studentName}!`, "success")
  closeModal()
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
