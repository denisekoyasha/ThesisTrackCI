// start of change for version 14
// created JavaScript for audit log page functionality
// handles search, filters, pagination, and dynamic table updates
// end of change for version 14

document.addEventListener("DOMContentLoaded", () => {
  // Logout functionality
  const logoutBtn = document.getElementById("logoutBtn")
  const logoutLink = document.getElementById("logoutLink")
  const logoutModal = document.getElementById("logoutModal")
  const confirmLogout = document.getElementById("confirmLogout")

  if (logoutBtn) {
    logoutBtn.addEventListener("click", (e) => {
      e.preventDefault()
      logoutModal.style.display = "flex"
    })
  }

  if (logoutLink) {
    logoutLink.addEventListener("click", (e) => {
      e.preventDefault()
      logoutModal.style.display = "flex"
    })
  }

  if (confirmLogout) {
    confirmLogout.addEventListener("click", () => {
      window.location.href = "../logout.php"
    })
  }

  window.closeLogoutModal = () => {
    logoutModal.style.display = "none"
  }

  window.confirmLogout = () => {
    window.location.href = "../logout.php"
  }

  // Audit log functionality
  let currentPage = 1
  let currentLimit = 25

  // Load audit logs
  function loadAuditLogs() {
    const search = document.getElementById("searchInput").value
    const roleFilter = document.getElementById("roleFilter").value
    const actionFilter = document.getElementById("actionFilter").value
    const dateFrom = document.getElementById("dateFrom").value
    const dateTo = document.getElementById("dateTo").value
    const entriesPerPage = document.getElementById("entriesPerPage").value

    currentLimit = Number.parseInt(entriesPerPage)

    const params = new URLSearchParams({
      search: search,
      role: roleFilter,
      action: actionFilter,
      date_from: dateFrom,
      date_to: dateTo,
      page: currentPage,
      limit: currentLimit,
    })

    fetch(`get_audit_logs.php?${params}`)
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          renderAuditLogs(data.logs)
          renderPagination(data.total, data.page, data.total_pages)
          updateResultsInfo(data.total, data.page, data.limit)
        }
      })
      .catch((error) => {
        console.error("Error loading audit logs:", error)
      })
  }

  // Render audit logs table
  function renderAuditLogs(logs) {
    const tbody = document.getElementById("auditLogsTableBody")

    if (logs.length === 0) {
      tbody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2rem; color: #666;">
                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                        No audit logs found
                    </td>
                </tr>
            `
      return
    }

    tbody.innerHTML = logs
      .map((log) => {
        const date = new Date(log.created_at)
        const formattedDate = date.toLocaleDateString("en-US", {
          year: "numeric",
          month: "short",
          day: "numeric",
        })
        const formattedTime = date.toLocaleTimeString("en-US", {
          hour: "2-digit",
          minute: "2-digit",
        })

        return `
                <tr>
                    <td>${escapeHtml(log.user_name)}</td>
                    <td><span class="role-badge role-${log.role}">${log.role}</span></td>
                    <td><span class="action-badge action-${log.action.replace("_", "-")}">${formatAction(log.action)}</span></td>
                    <td>${log.details ? escapeHtml(log.details) : "-"}</td>
                    <td>${log.ip_address || "-"}</td>
                    <td>
                        <div class="timestamp">
                            <div class="date">${formattedDate}</div>
                            <div class="time">${formattedTime}</div>
                        </div>
                    </td>
                </tr>
            `
      })
      .join("")
  }

  // Render pagination
  function renderPagination(total, currentPage, totalPages) {
    const pagination = document.getElementById("pagination")

    if (totalPages <= 1) {
      pagination.innerHTML = ""
      return
    }

    let html = ""

    // Previous button
    if (currentPage > 1) {
      html += `<button class="page-btn" onclick="goToPage(${currentPage - 1})">
                <i class="fas fa-chevron-left"></i>
            </button>`
    }

    // Page numbers
    const maxVisible = 5
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2))
    const endPage = Math.min(totalPages, startPage + maxVisible - 1)

    if (endPage - startPage < maxVisible - 1) {
      startPage = Math.max(1, endPage - maxVisible + 1)
    }

    if (startPage > 1) {
      html += `<button class="page-btn" onclick="goToPage(1)">1</button>`
      if (startPage > 2) {
        html += `<span class="page-ellipsis">...</span>`
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      html += `<button class="page-btn ${i === currentPage ? "active" : ""}" 
                onclick="goToPage(${i})">${i}</button>`
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        html += `<span class="page-ellipsis">...</span>`
      }
      html += `<button class="page-btn" onclick="goToPage(${totalPages})">${totalPages}</button>`
    }

    // Next button
    if (currentPage < totalPages) {
      html += `<button class="page-btn" onclick="goToPage(${currentPage + 1})">
                <i class="fas fa-chevron-right"></i>
            </button>`
    }

    pagination.innerHTML = html
  }

  // Update results info
  function updateResultsInfo(total, page, limit) {
    const start = (page - 1) * limit + 1
    const end = Math.min(page * limit, total)
    const info = document.getElementById("resultsInfo")
    info.textContent = `Showing ${start} to ${end} of ${total} entries`
  }

  // Go to page
  window.goToPage = (page) => {
    currentPage = page
    loadAuditLogs()
  }

  // Format action text
  function formatAction(action) {
    return action
      .split("_")
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(" ")
  }

  // Escape HTML
  function escapeHtml(text) {
    const div = document.createElement("div")
    div.textContent = text
    return div.innerHTML
  }

  // Search functionality
  const searchInput = document.getElementById("searchInput")
  let searchTimeout

  searchInput.addEventListener("input", () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
      currentPage = 1
      loadAuditLogs()
    }, 500)
  })

  // Filter change handlers
  document.getElementById("roleFilter").addEventListener("change", () => {
    currentPage = 1
    loadAuditLogs()
  })

  document.getElementById("actionFilter").addEventListener("change", () => {
    currentPage = 1
    loadAuditLogs()
  })

  document.getElementById("dateFrom").addEventListener("change", () => {
    currentPage = 1
    loadAuditLogs()
  })

  document.getElementById("dateTo").addEventListener("change", () => {
    currentPage = 1
    loadAuditLogs()
  })

  document.getElementById("entriesPerPage").addEventListener("change", () => {
    currentPage = 1
    loadAuditLogs()
  })

  // Clear filters
  window.clearFilters = () => {
    document.getElementById("searchInput").value = ""
    document.getElementById("roleFilter").value = ""
    document.getElementById("actionFilter").value = ""
    document.getElementById("dateFrom").value = ""
    document.getElementById("dateTo").value = ""
    document.getElementById("entriesPerPage").value = "25"
    currentPage = 1
    loadAuditLogs()
  }

  // Initial load
  loadAuditLogs()
})
