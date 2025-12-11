// Helper function to decode HTML entities
function decodeHTMLEntities(text) {
    if (!text) return '';
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
}

// Helper function to escape special characters for use in RegExp
function escapeRegExp(string) {
    if (!string || typeof string !== 'string') return '';
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}


// Expose a global `switchIssueTab` identifier so inline `onclick="switchIssueTab(...)"`
// calls from server-rendered HTML don't throw if the named handler hasn't been
// attached yet. This forwards to the more robust `window.switchIssueTab` when
// available, otherwise it logs a warning.
function switchIssueTab(issueType) {
    if (typeof window.switchIssueTab === 'function' && window.switchIssueTab !== switchIssueTab) {
        return window.switchIssueTab(issueType);
    }
    console.warn('switchIssueTab handler not ready yet:', issueType);
}

// Add this function to extract word count from various data sources
function extractWordCount(data) {
    console.log('🔍 extractWordCount called with data:', data);
    
    try {
        // First, try to get word count from spelling_report
        if (data.spelling_report) {
            console.log('📝 Found spelling_report, parsing...');
            
            let report;
            if (typeof data.spelling_report === 'string') {
                try {
                    report = JSON.parse(data.spelling_report);
                    console.log('✅ Successfully parsed spelling_report JSON');
                } catch (parseError) {
                    console.error('❌ Failed to parse spelling_report JSON:', parseError);
                    console.log('Raw spelling_report string:', data.spelling_report.substring(0, 500) + '...');
                    // Try to extract word count using string methods as fallback
                    const wordCountMatch = data.spelling_report.match(/"word_count":\s*(\d+)/);
                    if (wordCountMatch) {
                        console.log('📊 Found word_count via regex:', wordCountMatch[1]);
                        return parseInt(wordCountMatch[1]);
                    }
                    return 0;
                }
            } else {
                report = data.spelling_report;
            }
            
            console.log('📋 Parsed report structure:', report);
            
            // Try multiple possible locations for word count in the spelling report
            const possiblePaths = [
                report?.spelling_analysis?.statistics?.word_count,
                report?.statistics?.word_count,
                report?.spelling_analysis?.total_words,
                report?.analysis_details?.statistics?.word_count,
                report?.word_count,
                report?.spelling_analysis?.word_count,
                // NEW: Check for analysis_details structure
                report?.analysis_details?.word_count,
                report?.spelling_analysis?.analysis_details?.statistics?.word_count
            ];
            
            console.log('🔍 Checking possible paths:', possiblePaths);
            
            for (const wordCount of possiblePaths) {
                if (wordCount && !isNaN(wordCount)) {
                    console.log('✅ Found word count:', wordCount, 'at path');
                    return parseInt(wordCount);
                }
            }
            
            // NEW: Try to extract from spelling_analysis directly
            if (report.spelling_analysis) {
                const analysis = report.spelling_analysis;
                // Check if we have analysis_details with statistics
                if (analysis.analysis_details && analysis.analysis_details.statistics) {
                    const wordCount = analysis.analysis_details.statistics.word_count;
                    if (wordCount && !isNaN(wordCount)) {
                        console.log('✅ Found word count in analysis_details.statistics:', wordCount);
                        return parseInt(wordCount);
                    }
                }
                // Check if we have statistics directly
                if (analysis.statistics && analysis.statistics.word_count) {
                    console.log('✅ Found word count in spelling_analysis.statistics:', analysis.statistics.word_count);
                    return parseInt(analysis.statistics.word_count);
                }
            }
            
            // If we have the report but no word count, log the structure for debugging
            console.log('❌ No word count found in spelling_report. Available keys:', Object.keys(report || {}));
            if (report.spelling_analysis) {
                console.log('📊 spelling_analysis keys:', Object.keys(report.spelling_analysis));
                if (report.spelling_analysis.statistics) {
                    console.log('📈 statistics keys:', Object.keys(report.spelling_analysis.statistics));
                }
                if (report.spelling_analysis.analysis_details) {
                    console.log('📊 analysis_details keys:', Object.keys(report.spelling_analysis.analysis_details));
                    if (report.spelling_analysis.analysis_details.statistics) {
                        console.log('📈 analysis_details.statistics keys:', Object.keys(report.spelling_analysis.analysis_details.statistics));
                    }
                }
            }
        }
        
        // Try from formatting analysis
        if (data.formatting_analysis?.statistics?.word_count) {
            console.log('✅ Found word count in formatting_analysis:', data.formatting_analysis.statistics.word_count);
            return data.formatting_analysis.statistics.word_count;
        }
        
        // Try from main data
        if (data.word_count) {
            console.log('✅ Found word count in main data:', data.word_count);
            return data.word_count;
        }
        if (data.words_analyzed) {
            console.log('✅ Found words_analyzed in main data:', data.words_analyzed);
            return data.words_analyzed;
        }
        
        console.log('❌ No word count found in any location');
        return 0;
        
    } catch (e) {
        console.error('💥 Error extracting word count:', e);
        return data.word_count || data.words_analyzed || 0;
    }
}

// DEBUG: Comprehensive function to see what's in each chapter's spelling data
function debugAllSpellingData(data) {
    console.log('🔍 DEBUG ALL SPELLING DATA STRUCTURES');
    
    if (!data) {
        console.log('❌ No data provided');
        return;
    }
    
    // Check different possible locations for spelling data
    const possiblePaths = [
        'spelling_report',
        'formatting_analysis.spelling_report', 
        'formatting_analysis.spelling_issues',
        'spelling_issues',
        'spelling_analysis'
    ];
    
    possiblePaths.forEach(path => {
        const value = getNestedValue(data, path);
        if (value) {
            console.log(`📍 Found data at ${path}:`, value);
            
            if (typeof value === 'string') {
                try {
                    const parsed = JSON.parse(value);
                    console.log(`📊 Parsed ${path}:`, parsed);
                    
                    if (parsed.spelling_analysis && parsed.spelling_analysis.spelling_issues) {
                        console.log(`📝 Spelling issues in ${path}:`, parsed.spelling_analysis.spelling_issues);
                        if (parsed.spelling_analysis.spelling_issues.length > 0) {
                            console.log(`🔍 First issue in ${path}:`, parsed.spelling_analysis.spelling_issues[0]);
                        }
                    }
                } catch (e) {
                    console.log(`❌ Could not parse ${path} as JSON`);
                }
            }
        }
    });
}

// Call this when loading comprehensive reports
// debugAllSpellingData(yourData);

// Helper function to get nested values
function getNestedValue(obj, path) {
    return path.split('.').reduce((current, key) => current && current[key], obj);
}

document.addEventListener("DOMContentLoaded", function() {
    // Initialize components
    initializeTabs();
    initUserDropdown();
    initLogout();
    initModals();
    initReviewForm();
    initNotifications();
    initCompletedFilters();
    initIssueTabs();
    
    // Show welcome message
    showMessage("Viewing your pending chapters that needed to review.", "info");
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

// Initialize completed section filters
function initCompletedFilters() {
    const pills = document.querySelectorAll('#completed-reviews .status-pill');
    if (!pills || pills.length === 0) return;

    pills.forEach(p => p.addEventListener('click', function() {
        const filter = this.getAttribute('data-filter');
        // Toggle active state
        const isActive = this.classList.contains('active');
        // Clear active from all
        pills.forEach(x => x.classList.remove('active'));
        if (isActive) {
            // show all
            showCompletedItems(null);
        } else {
            this.classList.add('active');
            showCompletedItems(filter);
        }
    }));
}

function showCompletedItems(status) {
    const items = document.querySelectorAll('#completed-reviews .chapter-item');
    items.forEach(it => {
        const s = it.getAttribute('data-status');
        if (!status || s === status) {
            it.style.display = '';
        } else {
            it.style.display = 'none';
        }
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

        document.addEventListener('click', () => {
            userDropdown.style.display = 'none';
        });
    }
}

function initNotifications() {
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
}

// Initialize issue tab click handlers (spelling / grammar)
function initIssueTabs() {
    // Use event delegation so dynamically-inserted issue tabs (e.g. inside modals)
    // will still trigger tab switching.
    if (!window.__issueTabDelegationAdded) {
        document.addEventListener('click', function(e) {
            const tab = e.target.closest && e.target.closest('.issue-tab');
            if (!tab) return;
            e.preventDefault();
            const issueType = tab.dataset.issue ? String(tab.dataset.issue).toLowerCase() : ((tab.textContent || '').toLowerCase().includes('spelling') ? 'spelling' : 'grammar');
            if (window.switchIssueTab) window.switchIssueTab(issueType);
        });
        window.__issueTabDelegationAdded = true;
    }
}

function markNotificationAsRead(notificationId, element) {
    fetch('advisor_reviews.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_notification_read&notification_id=' + notificationId
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
    fetch('advisor_reviews.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_notifications_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove unread class from all notifications
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            updateNotificationBadge();
        }
    })
    .catch(error => console.error('Error:', error));
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
        
        // Remove mark all read button if no unread notifications
        const markAllReadBtn = document.getElementById('markAllRead');
        if (markAllReadBtn) {
            markAllReadBtn.remove();
        }
    }
}

function initLogout() {
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutLink = document.getElementById('logoutLink'); 
    const logoutModal = document.getElementById('logoutModal');
    const confirmLogout = document.getElementById('confirmLogout');
    const cancelLogout = document.getElementById('cancelLogout');

    // Show only the logout modal
    const showLogoutModal = (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // First hide all other modals
        document.querySelectorAll('.modal').forEach(m => {
            if (m !== logoutModal) m.style.display = 'none';
        });
        
        // Then show the logout modal
        if (logoutModal) {
            logoutModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };

    const hideLogoutModal = () => {
        if (logoutModal) {
            logoutModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    // Attach event listeners
    if (logoutBtn) logoutBtn.addEventListener('click', showLogoutModal);
    if (logoutLink) logoutLink.addEventListener('click', showLogoutModal);

    if (cancelLogout) {
        cancelLogout.addEventListener('click', hideLogoutModal);
    }

    if (confirmLogout) {
        confirmLogout.addEventListener('click', () => {
            window.location.href = '../logout.php';
        });
    }

    // Close when clicking outside modal
    if (logoutModal) {
        logoutModal.addEventListener('click', (e) => {
            if (e.target === logoutModal) {
                hideLogoutModal();
            }
        });
    }

    // Close with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && logoutModal.style.display === 'flex') {
            hideLogoutModal();
        }
    });
}

function initModals() {
    // Review modal handling
    const reviewModal = document.getElementById('reviewModal');
    const closeBtn = document.querySelector('#reviewModal .close');
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() { closeModal('reviewModal'); restoreLastReviewButton(); });
    }

    window.addEventListener('click', (e) => {
        if (reviewModal && e.target === reviewModal) {
            closeModal('reviewModal'); restoreLastReviewButton();
        }
    });
}

// Modal functionality
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    // If the review modal was closed, attempt to restore the last clicked Review Now button
    if (modalId === 'reviewModal') {
        restoreLastReviewButton();
    }
}

// Restore the last clicked Review Now button if the modal was cancelled (not submitted)
function restoreLastReviewButton() {
    try {
        const lastBtn = window._lastClickedReviewBtn;
        const wasSubmitted = !!window._reviewFormSubmitted;
        if (lastBtn && !wasSubmitted) {
            // Restore original HTML and enabled state
            if (lastBtn.dataset && lastBtn.dataset.origHtml) {
                lastBtn.innerHTML = lastBtn.dataset.origHtml;
                delete lastBtn.dataset.origHtml;
            }
            lastBtn.disabled = false;
            lastBtn.classList.remove('btn-disabled');
            lastBtn.setAttribute('aria-disabled', 'false');
        }
        // Clear tracking
        window._lastClickedReviewBtn = null;
        window._reviewFormSubmitted = false;
    } catch (e) {
        console.warn('Error restoring last review button', e);
    }
}

// Review Chapter Function - FIXED VERSION
function reviewChapter(chapterId, chapterName) {
    console.log('Review button clicked for chapter:', chapterId, chapterName);

    // Client-side guard: ensure we target the actual Review Now button and check its status
    const clickedBtnSelector = `button[onclick*="reviewChapter(${chapterId},"]`;
    let btn = document.querySelector(clickedBtnSelector) || document.querySelector(`button[data-chapter-id="${chapterId}"]`);
    if (btn) {
        const status = (btn.dataset.status || '').toLowerCase().trim();
        // allow review if status is one of the actionable states
        const actionable = ['pending', 'under_review', 'uploaded'];
        if (btn.disabled || actionable.indexOf(status) === -1) {
            console.log('Review action aborted: chapter not actionable', { chapterId, status });
            // Show the user-friendly message requested
            showMessage("You can't review it again because it's already done.", 'error');
            // Disable the button client-side to prevent further attempts
            try {
                btn.disabled = true;
                btn.classList.add('btn-disabled');
                btn.setAttribute('aria-disabled', 'true');
            } catch (e) {
                console.warn('Unable to disable button client-side', e);
            }
            return;
        }
    }

    // Start review on server: set status to under_review and assign reviewer
    const payload = new URLSearchParams();
    payload.append('action', 'begin_review');
    payload.append('chapter_id', chapterId);

    // Disable the clicked button to prevent double clicks
    const clickedBtn = document.querySelector(`button[onclick*="reviewChapter(${chapterId},"]`);
    if (clickedBtn) {
        clickedBtn.disabled = true;
        clickedBtn.dataset.origHtml = clickedBtn.innerHTML;
        clickedBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';
    }

    fetch('advisor_reviews.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: payload.toString()
    })
    .then(response => response.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Begin review - non-JSON response from server:', text);
            showMessage('Server returned unexpected response. Check console for details.', 'error');
            if (clickedBtn) {
                clickedBtn.disabled = false;
                clickedBtn.innerHTML = clickedBtn.dataset.origHtml || 'Review Now';
            }
            return;
        }

        if (data.success) {
            showMessage('Review started. Opening review dialog...', 'success');

            // Do not change server-side status here — open modal so advisor can submit score/feedback

            // Now open the modal to allow submitting review
            // Reset form
            const reviewForm = document.getElementById('reviewForm');
            if (reviewForm) reviewForm.reset();
            // Set chapter ID
            const chapterIdInput = document.getElementById('chapterId');
            if (chapterIdInput) chapterIdInput.value = chapterId;
            // Update title
            const reviewTitle = document.getElementById('reviewTitle');
            if (reviewTitle) reviewTitle.textContent = 'Review Chapter: ' + chapterName;
            // Open modal
            openModal('reviewModal');

            // Track the clicked button so we can restore it if the user cancels the modal
            try {
                window._lastClickedReviewBtn = clickedBtn || btn || null;
                window._reviewFormSubmitted = false; // will be set true when form is submitted
            } catch (e) {
                console.warn('Unable to track last clicked review button', e);
            }

            // Focus first input
            setTimeout(() => {
                const scoreInput = document.getElementById('scoreInput');
                if (scoreInput) scoreInput.focus();
            }, 300);
        } else {
            console.error('Begin review error response:', data);
            showMessage(data.error || 'Unable to start review. Please try again.', 'error');
            // Re-enable button
            if (clickedBtn) {
                clickedBtn.disabled = false;
                clickedBtn.innerHTML = clickedBtn.dataset.origHtml || 'Review Now';
            }
        }
    })
    .catch(err => {
        console.error('Begin review error', err);
        showMessage('Network error while starting review.', 'error');
        // Re-enable button
        if (clickedBtn) {
            clickedBtn.disabled = false;
            clickedBtn.innerHTML = clickedBtn.dataset.origHtml || 'Review Now';
        }
    });
}

// View Chapter File
function viewChapterFile(chapterId) {
    console.log('Viewing chapter file:', chapterId);
    window.open(`advisor_reviews.php?action=view_chapter&chapter_id=${chapterId}`, '_blank');
}

// Initialize review form
function initReviewForm() {
    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        // Avoid attaching multiple handlers if initReviewForm is called more than once
        if (reviewForm.dataset.handlerAttached === 'true') return;
        reviewForm.dataset.handlerAttached = 'true';

        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const score = document.getElementById('scoreInput').value;
            const status = document.getElementById('statusSelect').value;
            const feedback = document.getElementById('feedbackText').value;
            const chapterId = document.getElementById('chapterId').value;
            
            console.log('Submitting review:', { score, status, feedback, chapterId });
            
            // Validate inputs
            if (!status) {
                showMessage("Please select a status for the chapter.", "error");
                return;
            }
            
            if (score && (score < 0 || score > 100)) {
                showMessage("Please enter a valid score between 0 and 100.", "error");
                return;
            }
            
            if (!feedback.trim()) {
                showMessage("Please provide feedback for the chapter.", "error");
                return;
            }
            
            if (!chapterId) {
                showMessage("Chapter ID is missing. Please try again.", "error");
                return;
            }
            
            // Show loading state
            const submitBtn = reviewForm.querySelector('button[type="submit"]');
            // Prevent double-submits by disabling immediately and keeping a disabled flag
            if (submitBtn.disabled) return; // already submitting
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            submitBtn.disabled = true;
            
            // Submit form
            const formData = new FormData(reviewForm);
            
            fetch('advisor_reviews.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response received', data);
                if (data && data.success) {
                    // Mark form as submitted so cancel behavior doesn't restore the button
                    try { window._reviewFormSubmitted = true; } catch (e) {}
                    showMessage(data.message || "Review submitted successfully!", "success");
                    closeModal('reviewModal');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showMessage(data.error || "There was an error submitting your review. Please try again.", "error");
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage("Network or server error. Check console for details.", "error");
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
}

// Show message function
function showMessage(message, type) {
    // Create message element if it doesn't exist
    let messageDiv = document.getElementById('dynamicMessage');
    if (!messageDiv) {
        messageDiv = document.createElement('div');
        messageDiv.id = 'dynamicMessage';
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            z-index: 10000;
            font-weight: bold;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        document.body.appendChild(messageDiv);
    }
    
    // Set style based on type
    if (type === 'success') {
        messageDiv.style.backgroundColor = '#28a745';
    } else if (type === 'error') {
        messageDiv.style.backgroundColor = '#dc3545';
    } else {
        messageDiv.style.backgroundColor = '#17a2b8';
    }
    
    messageDiv.textContent = message;
    messageDiv.style.display = 'block';
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, 5000);
}

// =============== COMPREHENSIVE REPORT FUNCTIONS ===============

// Define as global function
window.viewComprehensiveReport = async function(chapterNumber, version) {
    showMessage(`Loading comprehensive analysis for Chapter ${chapterNumber} v${version}...`, "info");

    const groupId = window.currentGroupId;

    if (!groupId) {
        showMessage("Error: Could not determine group ID", "error");
        return;
    }

    console.log("🔍 Fetching comprehensive report for:", { chapterNumber, version, groupId });

    // Show loading state
    const modal = showLoadingModal(chapterNumber, version);

    try {
        // Use only the advisor's endpoint which includes all chapter data
        const response = await fetch(`advisor_reviews.php?get_thesis_report=true&chapter=${chapterNumber}&version=${version}&group=${groupId}`);
    
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
    
        const data = await response.json();
    
        // Remove loading modal
        if (modal) modal.remove();
        
        // Check for authorization errors
        if (data.success === false) {
            if (data.error && (data.error.includes('authorized') || data.error.includes('Session expired'))) {
                showMessage("Session expired. Please log in again.", "error");
                setTimeout(() => {
                    window.location.href = 'advisor_login.php';
                }, 2000);
                return;
            }
            throw new Error(data.error || "Unknown error");
        }
        
        if (data.success) {
            // Transform the data to match the expected format for the modal
            const transformedData = transformAdvisorReportData(data, chapterNumber, version);
            
            // Log the transformed data structure for debugging
            console.log('📊 Transformed data for modal:', {
                hasFormattingData: !!transformedData.formatting_analysis,
                formattingStructure: transformedData.formatting_analysis
            });
            
            showCombinedReportModal(transformedData, transformedData, transformedData);
        } else {
            throw new Error(data.error || "Failed to load analysis");
        }
    } catch (error) {
        console.error("Error loading comprehensive report:", error);
        // Remove loading modal if it exists
        if (modal) modal.remove();
        showMessage("Error loading comprehensive report: " + error.message, "error");
    }
};
function getFormattingScoreClass(score) {
    if (score >= 90) return 'excellent';
    if (score >= 80) return 'good';
    if (score >= 70) return 'fair';
    return 'poor';
}

// Safe array access helper for formatting analysis
function safeMap(array, callback) {
    if (!Array.isArray(array)) return '';
    return array.map(callback).join('');
}

// Formatting analysis page navigation functions
function showPageDetails(pageNumber) {
    console.log('Showing details for page:', pageNumber);
    showMessage(`Detailed analysis for Page ${pageNumber} would be shown here. This feature can display specific formatting issues, margin measurements, and compliance status for this page.`, 'info');
}

function showAllPages() {
    const pages = window.currentFormattingData?.page_by_page_analysis || [];
    showMessage(`Viewing all ${pages.length} pages in console. Implement modal for full view.`, 'info');
    console.log('All pages analysis:', pages);
}

// Formatting pagination functions
let currentFormattingPage = 1;
const PAGES_PER_VIEW = 20;

function changeFormattingPage(direction) {
    const totalPages = window.currentFormattingData?.page_by_page_analysis?.length || 0;
    const totalViews = Math.ceil(totalPages / PAGES_PER_VIEW);
    
    if (direction === 'next' && currentFormattingPage < totalViews) {
        currentFormattingPage++;
    } else if (direction === 'prev' && currentFormattingPage > 1) {
        currentFormattingPage--;
    }
    
    updateFormattingPagination();
}

function updateFormattingPagination() {
    const pages = window.currentFormattingData?.page_by_page_analysis || [];
    const startIndex = (currentFormattingPage - 1) * PAGES_PER_VIEW;
    const endIndex = startIndex + PAGES_PER_VIEW;
    const currentPages = pages.slice(startIndex, endIndex);
    
    const grid = document.getElementById('formattingPagesGrid');
    const indicator = document.querySelector('.page-indicator');
    const footer = document.querySelector('.pagination-footer span');
    
    if (grid) {
        grid.innerHTML = renderPagesGrid(currentPages);
    }
    if (indicator) {
        indicator.textContent = `Pages ${startIndex + 1}-${Math.min(endIndex, pages.length)}`;
    }
    if (footer) {
        footer.textContent = `Showing ${startIndex + 1}-${Math.min(endIndex, pages.length)} of ${pages.length} pages`;
    }
}

function renderPagesGrid(pages) {
    return pages.map(page => `
        <div class="page-card" onclick="showPageDetails(${page.page})">
            <div class="page-card-header">
                <span class="page-number">Page ${page.page}</span>
                <span class="page-score ${getFormattingScoreClass(page.overall_score)}">${page.overall_score}%</span>
            </div>
            <div class="page-priority ${page.priority}">
                <i class="fas fa-${page.priority === 'high' ? 'exclamation-circle' : page.priority === 'medium' ? 'exclamation-triangle' : 'info-circle'}"></i>
                ${page.priority} priority
            </div>
            <div class="page-issues">
                <ul class="issue-list">
                    ${page.main_issues.map(issue => `
                        <li class="issue-item">${issue}</li>
                    `).join('')}
                </ul>
            </div>
            <div class="page-status ${page.compliance_status}">
                ${page.compliance_status === 'compliant' ? '✓ Compliant' : 
                  page.compliance_status === 'minor_issues' ? '⚠ Minor Issues' :
                  page.compliance_status === 'needs_attention' ? '🔧 Needs Attention' : '❌ Critical Issues'}
            </div>
        </div>
    `).join('');
}

// Helper function to show loading modal
function showLoadingModal(chapterNumber, version) {
    const modal = document.createElement("div");
    modal.className = "analysis-report-modal show";
    modal.innerHTML = `
        <div class="analysis-report-content">
            <div class="analysis-report-header">
                <h3 class="analysis-report-title">
                    <i class="fas fa-spinner fa-spin"></i>
                    Loading Analysis - Chapter ${chapterNumber} v${version}
                </h3>
            </div>
            <div class="analysis-report-body">
                <div class="loading-analysis">
                    <div class="loading-spinner"></div>
                    <p>Loading comprehensive analysis report...</p>
                    <p class="loading-subtext">This may take a few moments</p>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    return modal;
}

// NEW: Helper function to format AI scores with decimals
function formatAIScore(score) {
    if (score === null || score === undefined) return "0";
    
    const numScore = parseFloat(score);
    
    // Check if the score has decimal places
    if (numScore % 1 !== 0) {
        // Format to 2 decimal places
        return numScore.toFixed(2);
    } else {
        // Whole number, return as is
        return numScore.toString();
    }
}

// Helper function to transform advisor report data to match modal expectations
function transformAdvisorReportData(data, chapterNumber, version) {
    console.log('🔄 Transforming advisor report data:', data);
    
    // Extract formatting data from student upload system structure
    const extractFormattingData = () => {
        // If we have direct formatting analysis data, use it
        if (data.formatting_analysis) {
            return {
                ...data.formatting_analysis,
                formatting_score: data.formatting_analysis.formatting_score || data.formatting_score || 0,
                grammar_score: data.formatting_analysis.grammar_score || data.grammar_score || 0,
                spelling_score: data.formatting_analysis.spelling_score || data.spelling_score || 0,
                words_analyzed: data.formatting_analysis.words_analyzed || extractWordCount(data),
                formatting_feedback: data.formatting_analysis.formatting_feedback || data.formatting_feedback || 'No formatting feedback available',
                grammar_feedback: data.formatting_analysis.grammar_feedback || data.grammar_feedback || 'No grammar feedback available',
                spelling_feedback: data.formatting_analysis.spelling_feedback || data.spelling_feedback || 'No spelling feedback available'
            };
        }
        
        // Try to extract from student upload system structure
        const formattingCompliance = data.formatting_compliance || (function() {
            // Try multiple possible locations for formatting data
            if (data.formatting_report && typeof data.formatting_report === 'string') {
                try {
                    const parsed = JSON.parse(data.formatting_report);
                    return parsed.formatting_compliance || null;
                } catch (e) {
                    console.warn('Could not parse formatting_report:', e);
                }
            } else if (data.formatting_report && typeof data.formatting_report === 'object') {
                return data.formatting_report.formatting_compliance || null;
            }
            return null;
        })();

        // Extract summary statistics from student upload data
        const extractSummaryStats = () => {
            // Try to get from direct data first
            if (data.summary_statistics) {
                return data.summary_statistics;
            }
            
            // Try to extract from formatting_compliance breakdown
            if (formattingCompliance && formattingCompliance.detailed_compliance_breakdown) {
                const breakdown = formattingCompliance.detailed_compliance_breakdown;
                let pagesFullyCompliant = 0;
                let totalPagesAnalyzed = 0;
                
                // Calculate from breakdown data
                Object.values(breakdown).forEach(category => {
                    if (category.pages_analyzed) {
                        totalPagesAnalyzed = Math.max(totalPagesAnalyzed, category.pages_analyzed);
                    }
                    if (category.fully_compliant) {
                        pagesFullyCompliant = Math.max(pagesFullyCompliant, category.fully_compliant);
                    }
                });
                
                return {
                    pages_fully_compliant: pagesFullyCompliant,
                    pages_analyzed: totalPagesAnalyzed,
                    overall_assessment: formattingCompliance.overall_assessment || 'Good',
                    priority_level: formattingCompliance.priority_level || 'Medium'
                };
            }
            
            // Fallback to basic data
            return {
                pages_fully_compliant: data.pages_fully_compliant || 0,
                pages_analyzed: data.total_pages_analyzed || data.pages_analyzed || 0,
                overall_assessment: data.overall_assessment || 'Good',
                priority_level: data.priority_level || 'Medium'
            };
        };

        const summaryStats = extractSummaryStats();
        
        return {
            formatting_score: data.formatting_score || 0,
            grammar_score: data.grammar_score || 0,
            spelling_score: data.spelling_score || 0,
            words_analyzed: extractWordCount(data),
            formatting_feedback: data.formatting_feedback || 'No formatting feedback available',
            grammar_feedback: data.grammar_feedback || 'No grammar feedback available',
            spelling_feedback: data.spelling_feedback || 'No spelling feedback available',
            spelling_issues: data.spelling_issues || [],
            grammar_issues: data.grammar_issues || [],
            spelling_report: data.spelling_report || null,
            grammar_report: data.grammar_report || null,
            formatting_report: data.formatting_report || null,
            
            // Formatting compliance data (from student upload system)
            formatting_compliance: formattingCompliance,
            
            overall_score: data.overall_score || data.formatting_score || 0,
            recommendations: data.recommendations || [],
            summary_statistics: summaryStats,
            page_by_page_analysis: data.page_by_page_analysis || [],
            total_pages_analyzed: data.total_pages_analyzed || summaryStats.pages_analyzed || 0
        };
    };

    const transformed = {
        success: true,
        chapter_number: chapterNumber,
        version: version,
        // FIX: Preserve decimal values for AI score
        overall_ai_percentage: data.ai_score || data.overall_ai_percentage || 0,
        total_sentences_analyzed: data.sentences_analyzed || 0,
        sentences_flagged_as_ai: data.sentences_flagged || 0,
        analysis: data.analysis || [],
        completeness_score: data.completeness_score || 0,
        relevance_score: data.relevance_score || (data.chapter_scores ? data.chapter_scores.chapter_relevance_score : 0) || 0,
        citation_score: data.citation_score || 0,
        total_citations: data.total_citations || 0,
        correct_citations: data.correct_citations || 0,
        corrected_citations: data.corrected_citations || [],
        ai_feedback: data.ai_feedback || '',
        completeness_feedback: data.completeness_feedback || '',
        
        // FIX: Directly use the scores from the main data object
        formatting_score: data.formatting_score || 0,
        grammar_score: data.grammar_score || 0,
        spelling_score: data.spelling_score || 0,
        words_analyzed: extractWordCount(data),
        formatting_feedback: data.formatting_feedback || 'No formatting feedback available',
        grammar_feedback: data.grammar_feedback || 'No grammar feedback available',
        spelling_feedback: data.spelling_feedback || 'No spelling feedback available',
        // Normalize spelling and grammar issues across all chapter structures
spelling_issues: (() => {
    if (data.spelling_issues) return data.spelling_issues;
    if (data.spelling_analysis && data.spelling_analysis.spelling_issues)
        return data.spelling_analysis.spelling_issues;
    if (data.spelling_report) {
        try {
            const rep = typeof data.spelling_report === 'string'
                ? JSON.parse(data.spelling_report)
                : data.spelling_report;
            return rep.spelling_analysis?.spelling_issues || rep.spelling_issues || [];
        } catch {
            return [];
        }
    }
    return [];
})(),
grammar_issues: (() => {
    if (data.grammar_issues) return data.grammar_issues;
    if (data.grammar_analysis && data.grammar_analysis.grammar_issues)
        return data.grammar_analysis.grammar_issues;
    if (data.grammar_report) {
        try {
            const rep = typeof data.grammar_report === 'string'
                ? JSON.parse(data.grammar_report)
                : data.grammar_report;
            return rep.grammar_analysis?.grammar_issues || rep.grammar_issues || [];
        } catch {
            return [];
        }
    }
    return [];
})(),
spelling_report: data.spelling_report || null,
grammar_report: data.grammar_report || null,

        formatting_report: data.formatting_report || null,
        
        // COMPREHENSIVE FORMATTING ANALYSIS DATA
        formatting_analysis: extractFormattingData(),
        
        // Process and transform the spelling report
        statistics: (function() {
            try {
                if (data.spelling_report) {
                    const report = typeof data.spelling_report === 'string' ? JSON.parse(data.spelling_report) : data.spelling_report;
                    
                    const stats = report.spelling_analysis?.statistics || report.statistics;
                    if (stats) {
                        return {
                            word_count: stats.word_count || 0,
                            character_count: stats.character_count || 0,
                            sentence_count: stats.sentence_count || 0,
                            issue_count: stats.issue_count || 
                                        report.spelling_analysis?.total_spelling_issues || 
                                        (report.spelling_analysis?.spelling_issues?.length || 0)
                        };
                    }
                }
                // Fallback to direct data if spelling_report is not available
                return {
                    word_count: data.word_count || 0,
                    character_count: data.character_count || 0,
                    sentence_count: data.sentence_count || 0,
                    issue_count: (data.spelling_issues || []).length
                };
            } catch (e) {
                console.warn('Error extracting statistics:', e);
                return {
                    word_count: 0,
                    character_count: 0,
                    sentence_count: 0,
                    issue_count: (data.spelling_issues || []).length
                };
            }
        })()
    };

    // Add chapter_scores data if available
    if (data.chapter_scores && typeof data.chapter_scores === 'object') {
        transformed.chapter_scores = data.chapter_scores;
        // Ensure relevance_score is taken from chapter_scores if available
        if (data.chapter_scores.chapter_relevance_score !== undefined) {
            transformed.relevance_score = data.chapter_scores.chapter_relevance_score;
        }
    }

    // Add AI analysis data if available
    if (data.ai_report && typeof data.ai_report === 'object') {
        const aiReport = data.ai_report;
        if (aiReport.overall_ai_percentage) {
            // FIX: Preserve decimal values from AI report
            transformed.overall_ai_percentage = aiReport.overall_ai_percentage;
        }
        if (aiReport.sentences_analyzed) {
            transformed.total_sentences_analyzed = aiReport.sentences_analyzed;
        }
        if (aiReport.sentences_flagged) {
            transformed.sentences_flagged_as_ai = aiReport.sentences_flagged;
        }
        if (aiReport.analysis) {
            transformed.analysis = aiReport.analysis;
        }
    }

    // Add completeness data if available
    if (data.completeness_report && typeof data.completeness_report === 'object') {
        const completenessReport = data.completeness_report;
        if (completenessReport.sections_analysis) {
            transformed.sections = completenessReport.sections_analysis;
        }
        if (completenessReport.chapter_scores) {
            transformed.chapter_scores = completenessReport.chapter_scores;
            // Ensure relevance_score is taken from completeness report if available
            if (completenessReport.chapter_scores.chapter_relevance_score !== undefined) {
                transformed.relevance_score = completenessReport.chapter_scores.chapter_relevance_score;
            }
        }
    }

 // Add citation data if available (applies to any chapter with citation data)
if (data.citation_report && typeof data.citation_report === 'object') {
    const citationReport = data.citation_report;
    transformed.total_citations = citationReport.total_citations || 0;
    transformed.correct_citations = citationReport.correct_citations || 0;
    transformed.corrected_citations = citationReport.corrected_citations || [];
}



    // DIRECT FORMATTING DATA MERGE
    // If we have direct formatting data at the root level, merge it into formatting_analysis
    if (data.formatting_compliance || data.overall_score || data.recommendations) {
        transformed.formatting_analysis = {
            ...transformed.formatting_analysis,
            formatting_compliance: data.formatting_compliance || transformed.formatting_analysis.formatting_compliance,
            overall_score: data.overall_score || transformed.formatting_analysis.overall_score,
            recommendations: data.recommendations || transformed.formatting_analysis.recommendations,
            summary_statistics: data.summary_statistics || transformed.formatting_analysis.summary_statistics,
            page_by_page_analysis: data.page_by_page_analysis || transformed.formatting_analysis.page_by_page_analysis,
            total_pages_analyzed: data.total_pages_analyzed || transformed.formatting_analysis.total_pages_analyzed
        };
    }

    // ENSURE CRITICAL FORMATTING DATA
    // Make sure we have pages analyzed and fully compliant data
    if (!transformed.formatting_analysis.summary_statistics.pages_analyzed && data.total_pages_analyzed) {
        transformed.formatting_analysis.summary_statistics.pages_analyzed = data.total_pages_analyzed;
    }
    
    if (!transformed.formatting_analysis.summary_statistics.pages_fully_compliant && data.pages_fully_compliant) {
        transformed.formatting_analysis.summary_statistics.pages_fully_compliant = data.pages_fully_compliant;
    }

    console.log('✅ Transformed data structure:', {
        hasFormattingAnalysis: !!transformed.formatting_analysis,
        hasFormattingCompliance: !!transformed.formatting_analysis?.formatting_compliance,
        hasOverallScore: !!transformed.formatting_analysis?.overall_score,
        pagesAnalyzed: transformed.formatting_analysis?.summary_statistics?.pages_analyzed,
        pagesFullyCompliant: transformed.formatting_analysis?.summary_statistics?.pages_fully_compliant,
        totalPagesAnalyzed: transformed.formatting_analysis?.total_pages_analyzed,
        formattingAnalysis: transformed.formatting_analysis
    });

    return transformed;
}

function showCombinedReportModal(aiData, thesisData, citationData) {
    const modal = document.createElement("div");
    modal.className = "analysis-report-modal show";
    
    const chapterNumber = aiData.chapter_number || "N/A";

    modal.innerHTML = `
        <div class="analysis-report-content">
            <div class="analysis-report-header">
                <h3 class="analysis-report-title">
                    <i class="fas fa-chart-bar"></i>
                    Comprehensive Analysis - Chapter ${chapterNumber} v${aiData.version || "1"}
                </h3>
                <button class="analysis-close-btn" onclick="this.closest('.analysis-report-modal').remove()">&times;</button>
            </div>
            <div class="analysis-report-body">
                <div class="comprehensive-tabs">
                    <div class="tab-headers">
                        <button class="tab-header active" data-tab="overview">Overview</button>
                        <button class="tab-header" data-tab="ai-analysis">AI Analysis</button>
                        <button class="tab-header" data-tab="thesis-analysis">Structure Analysis</button>
                        <button class="tab-header" data-tab="spelling-grammar">Spelling &amp; Grammar</button>
                        <button class="tab-header" data-tab="citation-analysis">Citation Analysis</button>
                        <button class="tab-header" data-tab="formatting-analysis">Formatting Analysis</button>
                        <button class="tab-header" data-tab="document-view">Document View</button>
                    </div>
                    
                    <div class="tab-content active" id="overview-tab">
                        ${generateOverviewTab(aiData, thesisData, citationData)}
                    </div>
                    
                    <div class="tab-content" id="ai-analysis-tab">
                        ${generateAIAnalysisTab(aiData)}
                    </div>
                    
                    <div class="tab-content" id="thesis-analysis-tab">
                        ${generateThesisAnalysisTab(thesisData)}
                    </div>
                    
                    <div class="tab-content" id="spelling-grammar-tab">
                        ${generateSpellingGrammarTab(aiData)}
                    </div>
                    
                    <div class="tab-content" id="citation-analysis-tab">
                        ${generateCitationAnalysisTab(citationData, chapterNumber)}
                    </div>

                    <div class="tab-content" id="formatting-analysis-tab">
                        ${generateFormattingAnalysisTab(aiData.formatting_analysis || aiData)}
                    </div>
                    
                    <div class="tab-content" id="document-view-tab">
                        ${generateDocumentViewTab(aiData)}
                    </div>
                </div>
            </div>
            <div class="analysis-report-actions">
                <button class="btn-analysis-action" onclick="exportCombinedReport(${aiData.chapter_number}, ${aiData.version})">
                    <i class="fas fa-download"></i> Export Analysis Report
                </button>
                <button class="btn-analysis-action btn-primary" onclick="this.closest('.analysis-report-modal').remove()">
                    Close
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Store modal data for export
    modal.dataset.aiData = JSON.stringify(aiData);
    modal.dataset.thesisData = JSON.stringify(thesisData);
    modal.dataset.citationData = JSON.stringify(citationData);

    // Add tab switching functionality
    modal.querySelectorAll(".tab-header").forEach((header) => {
        header.addEventListener("click", () => {
            const tabName = header.getAttribute("data-tab");

            // Update active tab header
            modal.querySelectorAll(".tab-header").forEach((h) => h.classList.remove("active"));
            header.classList.add("active");

            // Update active tab content
            modal.querySelectorAll(".tab-content").forEach((content) => content.classList.remove("active"));
            modal.querySelector(`#${tabName}-tab`).classList.add("active");
        });
    });
}



function generateOverviewTab(aiData, thesisData, citationData) {
    // FIX: Format AI score to show decimals
    const aiScore = formatAIScore(aiData.overall_ai_percentage || 0);
    const completenessScore = thesisData.completeness_score || 0;
    const relevanceScore = thesisData.relevance_score || 0;
    
    // Only calculate citation score for Chapter 5 if we have valid citation data
    let citationScore = 0;
    let citationsAnalyzed = 0;
    
    if (citationData.citation_score > 0 || citationData.total_citations > 0) {
        citationScore = citationData.citation_score || Math.round((citationData.correct_citations / citationData.total_citations) * 100);
        citationsAnalyzed = citationData.total_citations;
    } else {
        // For non-Chapter 5 or failed citation analysis
        citationScore = 0;
        citationsAnalyzed = 0;
    }

    return `
        <div class="overview-content">
            <div class="overview-scores">
                <div class="score-card ai-score">
                    <div class="score-value ${getAIScoreClass(parseFloat(aiScore))}">${aiScore}%</div>
                    <div class="score-label">AI Content Probability</div>
                    <div class="score-description">${getAIScoreDescription(parseFloat(aiScore))}</div>
                </div>
                <div class="score-card completeness-score">
                    <div class="score-value ${getCompletenessClass(completenessScore)}">${completenessScore}%</div>
                    <div class="score-label">Structure Completeness</div>
                    <div class="score-description">${getCompletenessDescription(completenessScore)}</div>
                </div>
                <div class="score-card relevance-score">
                    <div class="score-value ${getRelevanceClass(relevanceScore)}">${relevanceScore}%</div>
                    <div class="score-label">Content Relevance</div>
                    <div class="score-description">${getRelevanceDescription(relevanceScore)}</div>
                </div>
                <div class="score-card citation-score">
                    <div class="score-value ${getCitationScoreClass(citationScore)}">${citationScore}%</div>
                    <div class="score-label">APA Citation Score</div>
                    <div class="score-description">${citationsAnalyzed > 0 ? citationsAnalyzed + ' citations analyzed' : 'Not available for this chapter'}</div>
                </div>
            </div>
            
            <div class="recommendations">
                <h4>Recommendations</h4>
                <div class="recommendation-list">
                    ${generateRecommendations(parseFloat(aiScore), completenessScore, relevanceScore, citationScore, citationsAnalyzed)}
                </div>
            </div>
        </div>
    `;
}

function generateAIAnalysisTab(aiData) {
    console.log("🎨 Generating AI Analysis Tab with data:", aiData);

    // FIX: Format AI score to show decimals
    const aiScore = formatAIScore(aiData.overall_ai_percentage || 0);
    const totalAnalyzed = aiData.total_sentences_analyzed || 0;
    const totalFlagged = aiData.sentences_flagged_as_ai || 0;
    const analysisDetails = aiData.analysis || [];

    return `
        <div class="ai-analysis-content">
            <div class="ai-summary">
                <h4>AI Content Analysis</h4>
                <div class="ai-stats">
                    <div class="stat">
                        <span class="stat-label">Overall AI Probability:</span>
                        <span class="stat-value ${getAIScoreClass(parseFloat(aiScore))}">
                            ${aiScore}%
                        </span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Text Chunks Analyzed:</span>
                        <span class="stat-value">${totalAnalyzed}</span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">AI Chunks Flagged:</span>
                        <span class="stat-value">${totalFlagged}</span>
                    </div>
                </div>
            </div>
            
            <div class="confidence-meter">
                <div class="meter-label">AI Content Confidence</div>
                <div class="meter-bar">
                    <div class="meter-fill" style="width: ${Math.min(100, Math.max(0, parseFloat(aiScore)))}%"></div>
                </div>
                <div class="meter-labels">
                    <span>Low (0-49%)</span>
                    <span>Medium (50-74%)</span>
                    <span>High (75-100%)</span>
                </div>
            </div>
            
            ${
                analysisDetails.length > 0
                ? `
            <div class="ai-details">
                <h5>Detailed Text Analysis (${analysisDetails.length} chunks analyzed)</h5>
                <div class="analysis-controls">
                    <button class="filter-btn active" onclick="filterAnalysis('all')">All Content</button>
                    <button class="filter-btn" onclick="filterAnalysis('ai')">AI Content Only</button>
                    <button class="filter-btn" onclick="filterAnalysis('human')">Human Content Only</button>
                </div>
                <div class="ai-sections" id="aiSectionsContainer">
                    ${analysisDetails
                        .map((section, index) => {
                            const sectionText =
                            section.text || section.content || section.sentence || "No content available";
                            const isAI =
                            section.is_ai !== undefined
                                ? section.is_ai
                                : section.ai_probability >= 50 || section.flag === "ai" || false;
                            // FIX: Format individual section probabilities with decimals
                            const aiProbability = formatAIScore(
                            section.ai_probability !== undefined ? section.ai_probability : (isAI ? 100 : 0)
                            );
                            const sectionType = section.type || section.category || "paragraph";
                            const pageNumber = section.page || section.page_number || "N/A";

                            const displayText = sectionText
                            ? escapeHtml(truncateText(sectionText, 200))
                            : "No content available";

                            return `
                            <div class="ai-section ${isAI ? "ai-flagged" : "human-content"}" data-type="${isAI ? "ai" : "human"}">
                                <div class="section-header">
                                    <span class="section-type">${sectionType} ${index + 1}</span>
                                    <span class="section-status ${isAI ? "ai" : "human"}">
                                        ${isAI ? "🤖 AI Content" : "👤 Human Content"}
                                    </span>
                                    <span class="section-probability">${aiProbability}% AI</span>
                                </div>
                                <div class="section-text">${displayText}</div>
                                <div class="section-meta">
                                    <span class="section-page">Page ${pageNumber}</span>
                                    ${section.is_heading ? `<span class="section-heading-indicator">📝 Heading</span>` : ""}
                                    ${section.block_id ? `<span class="section-id">#${section.block_id}</span>` : ""}
                                </div>
                            </div>
                            `;
                        })
                        .join("")}
                </div>
                ${
                    analysisDetails.length > 10
                    ? `
                <div class="analysis-footer">
                    <p class="more-sections">+ ${analysisDetails.length - 10} more content sections analyzed</p>
                    <div class="analysis-summary">
                        <strong>Summary:</strong> ${totalFlagged} AI chunks, ${totalAnalyzed - totalFlagged} human chunks
                    </div>
                </div>
                `
                    : ""
                }
            </div>
            `
                : `
            <div class="no-analysis-data">
                <div class="no-data-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h5>Summary Analysis Available</h5>
                <p>The AI analysis detected <strong>${aiScore}% AI content probability</strong>, but detailed chunk-by-chunk analysis is not available.</p>
                
                <div class="basic-analysis-info">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">AI Probability Score:</span>
                            <span class="info-value ${getAIScoreClass(parseFloat(aiScore))}">${aiScore}%</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Analysis Level:</span>
                            <span class="info-value">Summary Only</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Content Assessment:</span>
                            <span class="info-value">${getAIScoreDescription(parseFloat(aiScore))}</span>
                        </div>
                    </div>
                </div>
                
                ${aiData.ai_feedback ? `
                <div class="ai-feedback-section">
                    <h6>AI Analysis Feedback:</h6>
                    <p>${aiData.ai_feedback}</p>
                </div>
                ` : ''}
                
                <div class="suggestions">
                    <h6>About This Analysis:</h6>
                    <ul>
                        <li>The overall AI probability score of <strong>${aiScore}%</strong> indicates ${getAIScoreDescription(parseFloat(aiScore)).toLowerCase()}</li>
                        <li>For detailed analysis, ensure your PDF contains extractable text (not scanned images)</li>
                        <li>Try re-uploading the file or contact support if you need chunk-level analysis</li>
                    </ul>
                </div>
                
                ${
                    parseFloat(aiScore) > 50
                    ? `
                <div class="ai-warning">
                    <div class="warning-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Recommendation</span>
                    </div>
                    <p>This document shows significant AI content probability. Consider reviewing and revising content for originality.</p>
                </div>
                `
                    : ""
                }
            </div>
            `
            }
        </div>
    `;
}

function generateFormattingAnalysisTab(formattingData) {
    console.log('📋 Formatting data for advisor:', formattingData);
    
    // Store formatting data globally for pagination
    window.currentFormattingData = formattingData;
    
    // Handle different data structures - look for formatting_analysis or use root data
    const analysisData = formattingData.formatting_analysis || formattingData;
    
    if (!analysisData || (!analysisData.formatting_compliance && !analysisData.overall_score)) {
        return `
            <div class="no-formatting-data">
                <div class="no-data-icon">
                    <i class="fas fa-ruler-combined"></i>
                </div>
                <h5>Formatting Analysis Not Available</h5>
                <p>Document formatting analysis data is not available for this document.</p>
                ${formattingData && formattingData.error ? 
                    `<div class="error-message">
                        <strong>Note:</strong> ${formattingData.error}
                    </div>` : ''}
            </div>
        `;
    }

    // Extract data from the analysis structure (like student side)
    const overallScore = analysisData.overall_score || analysisData.formatting_score || 0;
    const recommendations = analysisData.recommendations || [];
    const compliance = analysisData.formatting_compliance || {};
    const documentType = analysisData.document_type || 'PDF';
    
    // Calculate stats from the actual data (like student side)
    const margins = compliance.margins || [];
    const totalPages = margins.length || analysisData.total_pages_analyzed || 0;
    const compliantMargins = margins.filter(m => m.compliance === 'compliant').length;
    
    const spacing = compliance.spacing || [];
    const estimatedSpacing = spacing.filter(s => s.compliance === 'estimated_check_required').length;

    const pageLayout = compliance.page_layout || [];
    const pagesWithHeaders = pageLayout.filter(p => p.header_detected).length;
    const pagesWithNumbers = pageLayout.filter(p => p.page_number_detected).length;
    const pagesWithTitles = pageLayout.filter(p => p.chapter_title_detected).length;

    const threeLines = compliance.three_lines_right || [];
    const pagesWithThreeLines = threeLines.filter(t => t.three_lines_detected).length;

    const fontStyle = compliance.font_style || {};
    const fontPageAnalysis = fontStyle.page_analysis || [];
    const compliantFontPages = fontPageAnalysis.filter(p => p.primary_font_compliance?.status === 'compliant').length;

    // Safe array access helper
    const safeMap = (array, callback) => {
        if (!Array.isArray(array)) return '';
        return array.map(callback).join('');
    };

    return `
        <div class="formatting-analysis-content">
            <!-- Header Section (Like Student Side) -->
            <div class="formatting-header">
                <div class="header-main">
                    <h3>
                        <i class="fas fa-ruler-combined"></i>
                        Document Formatting Analysis
                    </h3>
                    <div class="overall-score-badge ${getFormattingScoreClass(overallScore)}">
                        ${overallScore}% Overall
                    </div>
                </div>
                <div class="header-subtitle">
                    Comprehensive formatting compliance analysis for your document
                </div>
            </div>

            <!-- Quick Stats Overview (Like Student Side) -->
            <div class="stats-overview-grid">
                <div class="stat-card primary-stat">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">${totalPages}</div>
                        <div class="stat-label">Total Pages Analyzed</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">${compliantMargins}</div>
                        <div class="stat-label">Margin Compliant Pages</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-font"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">${compliantFontPages}/${totalPages}</div>
                        <div class="stat-label">Font Compliant Pages</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">${pagesWithThreeLines}/${totalPages}</div>
                        <div class="stat-label">3-Lines Compliant</div>
                    </div>
                </div>
            </div>

            <!-- Overall Score Visualization (Like Student Side) -->
            <div class="score-visualization-section">
                <div class="score-header">
                    <h4>Overall Formatting Score</h4>
                    <div class="score-badge-large ${getFormattingScoreClass(overallScore)}">
                        ${overallScore}%
                    </div>
                </div>
                <div class="score-meter-large">
                    <div class="meter-track">
                        <div class="meter-progress ${getFormattingScoreClass(overallScore)}" 
                             style="width: ${overallScore}%"></div>
                    </div>
                    <div class="meter-labels">
                        <span>0%</span>
                        <span>50%</span>
                        <span>100%</span>
                    </div>
                </div>
                <div class="score-description">
                    <div class="description-text">
                        <i class="fas fa-${getScoreIcon(overallScore)}"></i>
                        ${getFormattingDescription(overallScore)}
                    </div>
                </div>
            </div>

            <!-- Detailed Analysis Sections (Like Student Side) -->
            <div class="analysis-sections">
                <!-- Margins Analysis -->
                <div class="analysis-section">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-arrows-alt"></i>
                            <h5>Margins Analysis</h5>
                        </div>
                        <div class="section-status ${compliantMargins === totalPages ? 'compliant' : 'warning'}">
                            ${compliantMargins === totalPages ? 
                                '<i class="fas fa-check-circle"></i> Fully Compliant' : 
                                '<i class="fas fa-exclamation-triangle"></i> Needs Attention'
                            }
                        </div>
                    </div>
                    <div class="section-content">
                        <div class="compliance-details">
                            <div class="detail-item">
                                <span class="detail-label">Pages with Compliant Margins:</span>
                                <span class="detail-value">${compliantMargins} of ${totalPages}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Standard Margin:</span>
                                <span class="detail-value">1.5" Left, 1" Other Sides</span>
                            </div>
                        </div>
                        <div class="pages-preview">
                            <div class="pages-grid compact">
                                ${margins.slice(0, 6).map(margin => `
                                    <div class="page-indicator ${margin.compliance === 'compliant' ? 'compliant' : 'non-compliant'}">
                                        <span class="page-number">${margin.page}</span>
                                        <i class="fas ${margin.compliance === 'compliant' ? 'fa-check' : 'fa-exclamation'}"></i>
                                    </div>
                                `).join('')}
                                ${totalPages > 6 ? `<div class="page-indicator more">+${totalPages - 6}</div>` : ''}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Font Analysis -->
                <div class="analysis-section">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-font"></i>
                            <h5>Font Analysis</h5>
                        </div>
                        <div class="section-status ${compliantFontPages === totalPages ? 'compliant' : 'warning'}">
                            ${compliantFontPages === totalPages ? 
                                '<i class="fas fa-check-circle"></i> Fully Compliant' : 
                                '<i class="fas fa-exclamation-triangle"></i> Needs Improvement'
                            }
                        </div>
                    </div>
                    <div class="section-content">
                        <div class="font-overview-grid">
                            <div class="font-metrics">
                                <div class="metric">
                                    <span class="metric-label">Primary Font Compliance:</span>
                                    <span class="metric-value ${fontStyle.overall_font_usage?.primary_font_compliance?.status === 'compliant' ? 'compliant' : 'non-compliant'}">
                                        ${fontStyle.overall_font_usage?.primary_font_compliance?.status === 'compliant' ? 'Compliant' : 'Non-Compliant'}
                                    </span>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Font Consistency:</span>
                                    <span class="metric-value ${fontStyle.overall_font_usage?.font_consistency === 'good' ? 'good' : 'poor'}">
                                        ${formatFontConsistency(fontStyle.overall_font_usage?.font_consistency)}
                                    </span>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Compliant Pages:</span>
                                    <span class="metric-value">${compliantFontPages} of ${totalPages}</span>
                                </div>
                            </div>
                            
                            ${fontStyle.overall_font_usage?.fonts_detected ? `
                            <div class="fonts-detected-section">
                                <h6>Detected Fonts</h6>
                                <div class="fonts-tags">
                                    ${fontStyle.overall_font_usage.fonts_detected.map(font => `
                                        <span class="font-tag ${font === 'Times New Roman' ? 'primary' : 'secondary'}">
                                            ${font}
                                            ${font === 'Times New Roman' ? '<i class="fas fa-star"></i>' : ''}
                                        </span>
                                    `).join('')}
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>

                <!-- Font Size Analysis -->
                ${compliance.font_size ? `
                <div class="analysis-section">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-text-height"></i>
                            <h5>Font Size Analysis</h5>
                        </div>
                        <div class="section-status ${compliance.font_size.compliance === 'compliant' ? 'compliant' : 'warning'}">
                            ${compliance.font_size.compliance === 'compliant' ? 
                                '<i class="fas fa-check-circle"></i> Compliant' : 
                                '<i class="fas fa-exclamation-triangle"></i> Needs Attention'
                            }
                        </div>
                    </div>
                    <div class="section-content">
                        <div class="font-size-details">
                            <div class="size-metrics">
                                <div class="size-metric">
                                    <span class="metric-label">Primary Size:</span>
                                    <span class="metric-value">${compliance.font_size.primary_size}pt</span>
                                </div>
                                <div class="size-metric">
                                    <span class="metric-label">Compliance Rate:</span>
                                    <span class="metric-value ${compliance.font_size.compliance_rate >= 90 ? 'excellent' : compliance.font_size.compliance_rate >= 80 ? 'good' : 'poor'}">
                                        ${compliance.font_size.compliance_rate}%
                                    </span>
                                </div>
                            </div>
                            
                            ${compliance.font_size.detected_sizes ? `
                            <div class="size-distribution">
                                <h6>Size Distribution</h6>
                                <div class="size-bars">
                                    ${Array.from(new Set(compliance.font_size.detected_sizes))
                                        .sort((a, b) => a - b)
                                        .map(size => {
                                            const count = compliance.font_size.detected_sizes.filter(s => s === size).length;
                                            const percentage = (count / compliance.font_size.detected_sizes.length * 100).toFixed(1);
                                            return `
                                            <div class="size-bar-item">
                                                <span class="size-label">${size}pt</span>
                                                <div class="size-bar-track">
                                                    <div class="size-bar-fill ${size === 12 ? 'standard' : 'non-standard'}" 
                                                         style="width: ${percentage}%"></div>
                                                </div>
                                                <span class="size-count">${count}</span>
                                            </div>
                                        `}).join('')}
                                </div>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Layout Analysis -->
                <div class="analysis-section">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-layer-group"></i>
                            <h5>Document Layout</h5>
                        </div>
                        <div class="section-status ${pagesWithHeaders === totalPages && pagesWithNumbers === totalPages ? 'compliant' : 'warning'}">
                            ${pagesWithHeaders === totalPages && pagesWithNumbers === totalPages ? 
                                '<i class="fas fa-check-circle"></i> Good' : 
                                '<i class="fas fa-exclamation-circle"></i> Review Needed'
                            }
                        </div>
                    </div>
                    <div class="section-content">
                        <div class="layout-metrics-grid">
                            <div class="layout-metric">
                                <div class="metric-value">${pagesWithHeaders}/${totalPages}</div>
                                <div class="metric-label">Headers Present</div>
                            </div>
                            <div class="layout-metric">
                                <div class="metric-value">${pagesWithNumbers}/${totalPages}</div>
                                <div class="metric-label">Page Numbers</div>
                            </div>
                            <div class="layout-metric">
                                <div class="metric-value">${pagesWithTitles}/${totalPages}</div>
                                <div class="metric-label">Chapter Titles</div>
                            </div>
                            <div class="layout-metric">
                                <div class="metric-value">${pagesWithThreeLines}/${totalPages}</div>
                                <div class="metric-label">3-Lines Rule</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Spacing Analysis -->
                ${compliance.spacing && compliance.spacing.length > 0 ? `
                <div class="analysis-section">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-arrows-alt-v"></i>
                            <h5>Spacing Analysis</h5>
                        </div>
                        <div class="section-status warning">
                            <i class="fas fa-search"></i> Manual Verification Required
                        </div>
                    </div>
                    <div class="section-content">
                        <div class="spacing-details">
                            <div class="spacing-summary">
                                <p><strong>Note:</strong> Spacing analysis requires manual verification for accurate assessment.</p>
                                <div class="spacing-preview">
                                    <div class="spacing-type">
                                        <i class="fas fa-grip-lines-vertical"></i>
                                        <span>Line Spacing: Double (Estimated)</span>
                                    </div>
                                    <div class="spacing-type">
                                        <i class="fas fa-paragraph"></i>
                                        <span>Paragraph Spacing: Adequate</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}
            </div>

            <!-- Recommendations Section -->
            ${recommendations.length > 0 ? `
            <div class="recommendations-section">
                <div class="recommendations-header">
                    <i class="fas fa-lightbulb"></i>
                    <h4>Formatting Recommendations</h4>
                </div>
                <div class="recommendations-grid">
                    ${recommendations.map((rec, index) => `
                        <div class="recommendation-card">
                            <div class="rec-number">${index + 1}</div>
                            <div class="rec-content">
                                <div class="rec-text">${rec}</div>
                                <div class="rec-priority ${index < 2 ? 'high' : 'medium'}">
                                    ${index < 2 ? 'High Priority' : 'Medium Priority'}
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
            ` : ''}
        </div>
    `;
}

function generateThesisAnalysisTab(thesisData) {
    console.log("📚 Generating Thesis Analysis Tab with data:", thesisData);

    if (!thesisData || !thesisData.chapter_scores) {
        return `
            <div class="no-thesis-data">
                <div class="no-data-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h5>Thesis Structure Analysis</h5>
                <p>Structure completeness score: <strong>${thesisData.completeness_score || 0}%</strong></p>
                ${thesisData.completeness_feedback ? `<p>${thesisData.completeness_feedback}</p>` : ''}
                <p class="info-note">Detailed section-by-section analysis data is not available for this document.</p>
            </div>
        `;
    }

    const completenessScore = thesisData.completeness_score || 0;
    const relevanceScore = thesisData.relevance_score || 0;

    // Check if we have chapter_scores data
    const hasChapterScores = thesisData.chapter_scores && typeof thesisData.chapter_scores === 'object';
    const presentSections = thesisData.chapter_scores.present_sections || 0;
    const totalSections = thesisData.chapter_scores.total_sections || 0;
    const missingSections = thesisData.chapter_scores.missing_sections || [];

    const sections = thesisData.sections || {};

    return `
        <div class="thesis-analysis-content">
            <div class="thesis-summary">
                <h4>Chapter Structure Analysis</h4>
                <div class="thesis-stats">
                    <div class="stat">
                        <span class="stat-label">Structure Completeness:</span>
                        <span class="stat-value ${getCompletenessClass(completenessScore)}">
                            ${completenessScore}%
                        </span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Content Relevance:</span>
                        <span class="stat-value ${getRelevanceClass(relevanceScore)}">
                            ${relevanceScore}%
                        </span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Sections Found:</span>
                        <span class="stat-value">${presentSections}/${totalSections}</span>
                    </div>
                </div>
            </div>
            
            <div class="sections-breakdown">
                <h5>Section Analysis</h5>
                ${generateSectionsList(sections)}
            </div>
            
            ${
                missingSections.length > 0
                ? `
            <div class="missing-sections">
                <h5>Missing Sections</h5>
                <ul class="missing-sections-list">
                    ${missingSections.map((section) => `<li>${section}</li>`).join("")}
                </ul>
            </div>
            `
                : ""
            }
            
            <div class="structure-recommendations">
                <h5>Structure Recommendations</h5>
                <div class="recommendation-list">
                    ${generateStructureRecommendations(completenessScore, relevanceScore, missingSections)}
                </div>
            </div>
        </div>
    `;
}

function generateCitationAnalysisTab(citationData, chapterNumber) {
    console.log("📊 Citation Data for Chapter", chapterNumber, ":", citationData);
    
    // If it's not Chapter 5, show a message that citation analysis is only for Chapter 5
    if (chapterNumber !== 5) {
        return `
            <div class="no-citation-data">
                <div class="no-data-icon">
                    <i class="fas fa-quote-right"></i>
                </div>
                <h5>APA Citation Analysis</h5>
                <p>Citation analysis is only available for Chapter 5 (References/Bibliography).</p>
                <p class="info-message">This analysis checks APA formatting in your bibliography section.</p>
            </div>
        `;
    }
    
    if (!citationData || citationData.error || !citationData.success) {
        return `
            <div class="no-citation-data">
                <div class="no-data-icon">
                    <i class="fas fa-quote-right"></i>
                </div>
                <h5>APA Citation Analysis Not Available</h5>
                <p>Citation analysis data is not available for this document.</p>
                ${citationData && citationData.error ? `<p class="error-message">Error: ${citationData.error}</p>` : ''}
                ${citationData && citationData.message ? `<p class="info-message">${citationData.message}</p>` : ''}
            </div>
        `;
    }

    const totalCitations = citationData.total_citations || 0;
    const correctCitations = citationData.correct_citations || 0;
    const citationScore = citationData.citation_score || (totalCitations > 0 ? Math.round((correctCitations / totalCitations) * 100) : 0);
    const correctedCitations = citationData.corrected_citations || [];

    // Check if we have any meaningful data
    const hasCitationData = totalCitations > 0 || citationScore > 0 || correctedCitations.length > 0;

    if (!hasCitationData) {
        return `
            <div class="no-citation-data">
                <div class="no-data-icon">
                    <i class="fas fa-quote-right"></i>
                </div>
                <h5>APA Citation Analysis Not Available</h5>
                <p>No citation data was found for this document. The citation analysis may not have run successfully.</p>
            </div>
        `;
    }

    return `
        <div class="citation-analysis-content">
            <div class="citation-summary">
                <h4>APA Citation Analysis - Chapter 5</h4>
                <div class="citation-stats">
                    <div class="stat">
                        <span class="stat-label">Overall Citation Score:</span>
                        <span class="stat-value ${getCitationScoreClass(citationScore)}">
                            ${citationScore}%
                        </span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Total Citations:</span>
                        <span class="stat-value">${totalCitations}</span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Properly Formatted:</span>
                        <span class="stat-value">${correctCitations}/${totalCitations}</span>
                    </div>
                </div>
            </div>
            
            ${correctedCitations.length > 0 ? `
            <div class="corrected-citations">
                <h5>Citation Corrections (${correctedCitations.length} citations analyzed)</h5>
                <div class="citations-list">
                    ${correctedCitations.map((citation, index) => `
                        <div class="citation-item ${citation.is_correct ? 'correct' : 'needs-correction'}">
                            <div class="citation-header">
                                <span class="citation-number">Citation ${index + 1}</span>
                                <span class="citation-status ${citation.is_correct ? 'correct' : 'incorrect'}">
                                    ${citation.is_correct ? '✓ Correct' : '⚠ Needs Correction'}
                                </span>
                            </div>
                            ${!citation.is_correct ? `
                            <div class="citation-comparison">
                                <div class="citation-original">
                                    <strong>Original:</strong> ${citation.original || 'No original text available'}
                                </div>
                                <div class="citation-corrected">
                                    <strong>Corrected:</strong> ${citation.corrected || 'No correction available'}
                                </div>
                                ${citation.reasoning ? `
                                <div class="citation-reasoning">
                                    <strong>Changes:</strong> ${citation.reasoning}
                                </div>
                                ` : ''}
                            </div>
                            ` : `
                            <div class="citation-content">
                                ${citation.corrected || citation.original || 'No citation text available'}
                            </div>
                            `}
                        </div>
                    `).join('')}
                </div>
            </div>
            ` : totalCitations > 0 ? `
            <div class="citations-overview">
                <h5>Citations Overview</h5>
                <div class="overview-message">
                    <p>All ${totalCitations} citations were analyzed and ${correctCitations} were found to be properly formatted in APA style.</p>
                    ${correctCitations === totalCitations ? 
                        '<p class="success-message">🎉 Excellent! All citations are properly formatted.</p>' : 
                        `<p class="warning-message">⚠️ ${totalCitations - correctCitations} citations need formatting corrections.</p>`
                    }
                </div>
            </div>
            ` : ''}
            
            <div class="citation-recommendations">
                <h5>APA Formatting Recommendations</h5>
                <div class="recommendation-list">
                    ${generateCitationRecommendations(citationScore, totalCitations, correctCitations)}
                </div>
            </div>
        </div>
    `;
}

// Helper functions for formatting analysis
function getScoreIcon(score) {
    if (score >= 90) return 'trophy';
    if (score >= 80) return 'check-circle';
    if (score >= 70) return 'exclamation-circle';
    return 'exclamation-triangle';
}

function getFormattingDescription(score) {
    if (score >= 90) return 'Excellent formatting compliance';
    if (score >= 80) return 'Good formatting with minor issues';
    if (score >= 70) return 'Moderate formatting issues';
    return 'Significant formatting improvements needed';
}

function formatFontConsistency(consistency) {
    const mappings = {
        'too_many_fonts': 'Too Many Fonts',
        'good': 'Good',
        'excellent': 'Excellent',
        'poor': 'Poor',
        'consistent': 'Consistent',
        'inconsistent': 'Inconsistent'
    };
    return mappings[consistency] || consistency;
}
function generateSpellingGrammarTab(formattingData) {
    console.log('🔧 generateSpellingGrammarTab called with:', formattingData);
    
    const analysisData = formattingData || {};
    
    // Check if we have spelling/grammar data in different locations
    const hasSpellingData = analysisData.spelling_score !== undefined || 
                           analysisData.formatting_analysis?.spelling_score !== undefined;
    const hasGrammarData = analysisData.grammar_score !== undefined || 
                          analysisData.formatting_analysis?.grammar_score !== undefined;

    if (!hasSpellingData && !hasGrammarData) {
        return `
            <div class="spelling-grammar-content">
                <div class="no-data-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>No spelling or grammar analysis data available for this chapter.</p>
                </div>
            </div>
        `;
    }

    // Extract data from multiple possible locations
    const {
        spelling_score,
        grammar_score,
        words_analyzed,
        spelling_issues,
        grammar_issues,
        spelling_feedback,
        grammar_feedback
    } = extractSpellingGrammarData(analysisData);

    return `
        <div class="spelling-grammar-content">
            <!-- Statistics Overview -->
            <div class="analysis-overview">
                <div class="stat-card white-card">
                    <h3>SPELLING ACCURACY</h3>
                    <div class="stat-value ${getScoreClass(spelling_score)}">${spelling_score}%</div>
                    <div class="stat-description">Overall Score</div>
                </div>
                <div class="stat-card white-card">
                    <h3>GRAMMAR ACCURACY</h3>
                    <div class="stat-value ${getScoreClass(grammar_score)}">${grammar_score}%</div>
                    <div class="stat-description">Overall Score</div>
                </div>
                <div class="stat-card white-card">
                    <h3>WORDS ANALYZED</h3>
                    <div class="stat-value">${words_analyzed.toLocaleString()}</div>
                    <div class="stat-description">Total words analyzed</div>
                </div>
                
            </div>

            <!-- Feedback Section -->
            <div class="feedback-section">
                <div class="feedback-card white-card">
                    <h4><i class="fas fa-spell-check"></i> Spelling Analysis</h4>
                    <div class="feedback-content">
                        <div class="feedback-score ${getScoreClass(spelling_score)}">
                            ${spelling_score}% Accuracy
                        </div>
                        <p>${escapeHtml(spelling_feedback)}</p>
                        ${spelling_issues.length > 0 ? 
                            `<div class="issues-count">${spelling_issues.length} spelling issue${spelling_issues.length !== 1 ? 's' : ''} identified</div>` : 
                            '<div class="success-badge">✓ Perfect spelling</div>'
                        }
                    </div>
                </div>
                <div class="feedback-card white-card">
                    <h4><i class="fas fa-language"></i> Grammar Analysis</h4>
                    <div class="feedback-content">
                        <div class="feedback-score ${getScoreClass(grammar_score)}">
                            ${grammar_score}% Accuracy
                        </div>
                        <p>${escapeHtml(grammar_feedback)}</p>
                        ${grammar_issues.length > 0 ? 
                            `<div class="issues-count">${grammar_issues.length} grammar issue${grammar_issues.length !== 1 ? 's' : ''} identified</div>` : 
                            '<div class="success-badge">✓ Perfect grammar</div>'
                        }
                    </div>
                </div>
            </div>

            <!-- Detailed Issues Section -->
            <div class="detailed-issues-section">
                <div class="section-header">
                    <h3><i class="fas fa-search"></i> Detailed Issues Analysis</h3>
                    <div class="issues-summary">
                        <span class="total-issues">${spelling_issues.length + grammar_issues.length} total issues</span>
                    </div>
                </div>
                
                <div class="issues-tabs">
                    <button class="issue-tab active" data-issue="spelling" onclick="switchIssueTab('spelling')">
                        <i class="fas fa-spell-check"></i>
                        Spelling Issues
                        <span class="tab-badge ${spelling_issues.length === 0 ? 'success' : 'warning'}">${spelling_issues.length}</span>
                    </button>
                    <button class="issue-tab" data-issue="grammar" onclick="switchIssueTab('grammar')">
                        <i class="fas fa-language"></i>
                        Grammar Issues
                        <span class="tab-badge ${grammar_issues.length === 0 ? 'success' : 'warning'}">${grammar_issues.length}</span>
                    </button>
                </div>

                <!-- Spelling Issues Content -->
                <div class="issues-content active" id="spelling-issues-content">
                    ${spelling_issues.length > 0 ? `
                        <div class="issues-container">
                            ${spelling_issues.map((issue, index) => {
                                const context = issue.context || issue.text || issue.sentence || 'No context available';
                                const suggestion = issue.suggestion || issue.correction || issue.replacements?.[0] || 'No suggestion available';
                                const mistake = extractMistakeFromIssue(issue);
                                
                                return `
                                <div class="issue-card-detailed white-card">
                                    <div class="issue-header">
                                        <div class="issue-number">Spelling Issue #${index + 1}</div>
                                        <div class="issue-severity ${(issue.severity || 'moderate').toLowerCase()}">
                                            <i class="fas fa-${getSeverityIcon(issue.severity)}"></i>
                                            ${issue.severity || 'MODERATE'}
                                        </div>
                                    </div>
                                    
                                    <div class="issue-content">
                                        <div class="context-section">
                                            <h5>In Context</h5>
                                            ${createWordCorrectionDisplay(context, mistake, suggestion)}
                                        </div>
                                        
                                        ${issue.explanation ? `
                                        <div class="explanation-section">
                                            <h5>Explanation</h5>
                                            <div class="explanation-text">${decodeHTMLEntities(issue.explanation)}</div>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                `;
                            }).join('')}
                        </div>
                    ` : `
                        <div class="no-issues white-card">
                            <div class="no-issues-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4>Excellent Spelling!</h4>
                            <p>No spelling issues were detected in your document.</p>
                            <div class="quality-score">
                                <span class="score">${spelling_score}%</span>
                                <span class="label">Spelling Accuracy</span>
                            </div>
                        </div>
                    `}
                </div>

                <!-- Grammar Issues Content -->
                <div class="issues-content" id="grammar-issues-content">
                    ${grammar_issues.length > 0 ? `
                        <div class="issues-container">
                            ${grammar_issues.map((issue, index) => {
                                const context = issue.context || issue.text || issue.sentence || 'No context available';
                                const suggestion = issue.suggestion || issue.correction || issue.replacements?.[0] || 'No suggestion available';
                                const message = issue.message || issue.issue || '';
                                const mistake = extractMistakeFromIssue(issue);
                                
                                // Extract the problematic part from the context based on the mistake
                                let problematicText = mistake;
                                if (!problematicText && context) {
                                    // Try to find the text that needs correction by comparing with suggestion
                                    const words = (context || '').split(/\s+/);
                                    problematicText = words.find(word => 
                                        (suggestion || '').toLowerCase().includes(word.toLowerCase())
                                    ) || '';
                                }
                                
                                // Highlight the problematic text in context if found
                                let highlightedContext = context || '';
                                if (problematicText && typeof problematicText === 'string') {
                                    const escapedText = escapeRegExp(problematicText);
                                    if (escapedText) {
                                        try {
                                            highlightedContext = context.replace(
                                                new RegExp(`(${escapedText})`, 'gi'), 
                                                '<span class="grammar-highlight">$1</span>'
                                            );
                                        } catch (e) {
                                            console.error('Error highlighting context:', e);
                                            highlightedContext = context;
                                        }
                                    }
                                }
                                
                                return `
                                <div class="issue-card-detailed white-card">
                                    <div class="issue-header">
                                        <div class="issue-number">Grammar Issue #${index + 1}</div>
                                        <div class="issue-severity ${(issue.severity || 'moderate').toLowerCase()}">
                                            <i class="fas fa-${getSeverityIcon(issue.severity)}"></i>
                                            ${issue.severity || 'MODERATE'}
                                        </div>
                                    </div>
                                    
                                    <div class="issue-content">
                                        <div class="context-section">
                                            <h5>In Context</h5>
                                            ${createWordCorrectionDisplay(highlightedContext, mistake, suggestion)}
                                        </div>
                                        
                                        <div class="grammar-issue-section">
                                            <h5>Grammar Issue</h5>
                                            <div class="issue-text">${decodeHTMLEntities(message)}</div>
                                            ${suggestion ? `
                                                <div class="suggestion-text">
                                                    <strong>Suggestion:</strong> ${decodeHTMLEntities(suggestion)}
                                                </div>
                                            ` : ''}
                                        </div>
                                        
                                        ${issue.explanation ? `
                                        <div class="explanation-section">
                                            <h5>Explanation</h5>
                                            <div class="explanation-text">${decodeHTMLEntities(issue.explanation)}</div>
                                        </div>
                                        ` : ''}
                                        
                                        ${issue.rule ? `
                                        <div class="rule-section">
                                            <h5>Grammar Rule</h5>
                                            <div class="rule-text">${decodeHTMLEntities(issue.rule)}</div>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                `;
                            }).join('')}
                        </div>
                    ` : `
                        <div class="no-issues white-card">
                            <div class="no-issues-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4>Excellent Grammar!</h4>
                            <p>No grammar issues were detected in your document.</p>
                            <div class="quality-score">
                                <span class="score">${grammar_score}%</span>
                                <span class="label">Grammar Accuracy</span>
                            </div>
                        </div>
                    `}
                </div>
            </div>
        </div>
    `;
}

// ULTRA SIMPLE: Highlight and use whatever word is in context
function createWordCorrectionDisplay(context, mistake, correction) {
    console.log('🔧 createWordCorrectionDisplay called with:', { context, mistake, correction });

    // Normalize inputs
    context = context == null ? '' : String(context).trim();
    correction = correction == null ? '' : String(correction).trim();

    // Extract the word to highlight from the mistake object
    let wordToHighlight = '';
    if (mistake && typeof mistake === 'object') {
        wordToHighlight = mistake.mistake || mistake.word || mistake.text || '';
    } else {
        wordToHighlight = mistake == null ? '' : String(mistake).trim();
    }

    let highlightedContext = escapeHtml(context);
    let displayWord = '';

    // If we have a word to highlight, find and highlight it in context
    if (wordToHighlight) {
        const safeWord = escapeForRegex(wordToHighlight);
        const regex = new RegExp(`\\b${safeWord}\\b`, 'gi');
        const match = regex.exec(context);
        
        if (match) {
            displayWord = match[0]; // Use the actual matched word from context
            highlightedContext = highlightMistakeInText(context, displayWord);
        }
    }

    // If no specific word found but we have correction, try to find a related word
    if (!displayWord && correction) {
        const words = context.split(/\s+/);
        for (let word of words) {
            const cleanWord = word.replace(/[.,!?;:()'"\n-]/g, '');
            if (cleanWord.length > 2 && 
                levenshteinDistance(cleanWord.toLowerCase(), correction.toLowerCase()) <= 3) {
                displayWord = cleanWord;
                highlightedContext = highlightMistakeInText(context, displayWord);
                break;
            }
        }
    }

    return `
        <div class="simple-correction-display">
            <div class="context-text">${highlightedContext}</div>
            <div class="correction-instruction">
                ${displayWord ? `Change <span class="incorrect-word">"${escapeHtml(displayWord)}"</span> to ` : 'Change to '}
                <span class="correct-word">"${escapeHtml(correction)}"</span>
            </div>
        </div>
    `;
}

// DEBUG: Function to inspect the actual spelling report structure
function debugSpellingReportStructure(spellingReport) {
    console.log('🔍 DEBUG Spelling Report Structure:');
    
    try {
        let report = spellingReport;
        if (typeof spellingReport === 'string') {
            report = JSON.parse(spellingReport);
        }
        
        console.log('📊 Full report:', report);
        
        if (report.spelling_analysis) {
            console.log('📝 Spelling analysis exists');
            console.log('📋 Spelling issues array:', report.spelling_analysis.spelling_issues);
            
            if (report.spelling_analysis.spelling_issues && report.spelling_analysis.spelling_issues.length > 0) {
                console.log('🔍 First spelling issue details:', report.spelling_analysis.spelling_issues[0]);
                console.log('📝 First issue word field:', report.spelling_analysis.spelling_issues[0].word);
                console.log('📝 First issue word type:', typeof report.spelling_analysis.spelling_issues[0].word);
                console.log('📝 First issue word value:', report.spelling_analysis.spelling_issues[0].word);
            }
        }
    } catch (e) {
        console.error('❌ Error debugging spelling report:', e);
    }
}

// Call this function when you load the spelling data to see what's happening
// debugSpellingReportStructure(yourSpellingReportData);


// SIMPLIFIED spelling/grammar data extraction
function extractSpellingGrammarData(analysisData) {
    console.log('🔍 extractSpellingGrammarData called with:', analysisData);
    
    const fa = analysisData.formatting_analysis || {};
    
    const spelling_score = analysisData.spelling_score || fa.spelling_score || 0;
    const grammar_score = analysisData.grammar_score || fa.grammar_score || 0;
    const words_analyzed = extractWordCount(analysisData) || 0;
    
    const spelling_issues = extractSpellingIssuesWithContext(analysisData.spelling_issues || fa.spelling_issues || analysisData.spelling_report);
    const grammar_issues = extractGrammarIssuesWithContext(analysisData.grammar_issues || fa.grammar_issues || analysisData.grammar_report);
    
    const spelling_feedback = analysisData.spelling_feedback || fa.spelling_feedback || 'No spelling feedback available';
    const grammar_feedback = analysisData.grammar_feedback || fa.grammar_feedback || 'No grammar feedback available';

    return {
        spelling_score,
        grammar_score,
        words_analyzed,
        spelling_issues,
        grammar_issues,
        spelling_feedback,
        grammar_feedback
    };
}

// FIXED: Better spelling issues extraction that handles the actual database structure
function extractSpellingIssuesWithContext(issuesData) {
    if (!issuesData) return [];
    
    let issues = [];
    
    try {
        // If it's a string (JSON from database), parse it
        if (typeof issuesData === 'string') {
            issuesData = JSON.parse(issuesData);
        }
        
        // Extract from the actual database structure
        if (issuesData.spelling_analysis && Array.isArray(issuesData.spelling_analysis.spelling_issues)) {
            issues = issuesData.spelling_analysis.spelling_issues;
        } else if (Array.isArray(issuesData.spelling_issues)) {
            issues = issuesData.spelling_issues;
        } else if (Array.isArray(issuesData)) {
            issues = issuesData;
        }
    } catch (e) {
        console.warn('Error parsing spelling issues:', e);
        return [];
    }
    
    return issues.map((issue, index) => {
        // Extract context - handle different possible structures
        let context = '';
        if (typeof issue.context === 'string') {
            context = issue.context;
        } else if (issue.context && typeof issue.context === 'object') {
            context = issue.context.text || issue.context.value || issue.context.context || '';
        } else {
            context = issue.text || issue.sentence || issue.message || '';
        }
        
        // Extract the actual mistake word
        let mistake = '';
        if (typeof issue.word === 'string') {
            mistake = issue.word;
        } else if (issue.word && typeof issue.word === 'object') {
            // Handle nested word object
            mistake = issue.word.text || issue.word.value || issue.word.word || 
                     (issue.word.context && issue.word.context.text) || '';
        } else if (issue.mistake) {
            if (typeof issue.mistake === 'string') {
                mistake = issue.mistake;
            } else if (typeof issue.mistake === 'object') {
                mistake = issue.mistake.text || issue.mistake.value || issue.mistake.word || '';
            }
        }
        
        // Extract suggestion
        let suggestion = '';
        if (typeof issue.suggestion === 'string') {
            suggestion = issue.suggestion;
        } else if (issue.suggestion && typeof issue.suggestion === 'object') {
            suggestion = issue.suggestion.text || issue.suggestion.value || issue.suggestion.correction || '';
        } else if (issue.replacements && Array.isArray(issue.replacements)) {
            suggestion = issue.replacements[0] || '';
        }
        
        // If we still don't have a mistake, try to extract from message
        if (!mistake && issue.message) {
            const quotedMatch = issue.message.match(/"([^"]+)"\s+should\s+be\s+"([^"]+)"/i);
            if (quotedMatch) {
                mistake = quotedMatch[1];
                if (!suggestion) suggestion = quotedMatch[2];
            } else {
                // Look for patterns like "Unknown word: 'usefuled'"
                const wordMatch = issue.message.match(/'([^']+)'/);
                if (wordMatch) mistake = wordMatch[1];
            }
        }
        
        // Clean up the data
        context = context.replace(/\.\.\.$/, '').trim();
        mistake = mistake ? String(mistake).trim() : '';
        suggestion = suggestion ? String(suggestion).trim() : '';
        
        console.log(`📝 Processed spelling issue #${index + 1}:`, { 
            originalIssue: issue,
            finalContext: context, 
            mistake, 
            suggestion 
        });
        
        return {
            ...issue,
            context: context,
            mistake: mistake,
            suggestion: suggestion,
            severity: issue.severity || 'moderate',
            explanation: issue.explanation || ''
        };
    });
}

// NEW: Extract mistake from context using various strategies
function extractMistakeFromContext(issue) {
    if (issue.word) return issue.word;
    if (issue.mistake) return issue.mistake;
    
    // Try to extract from message
    if (issue.message) {
        // Look for quoted words in messages like "Unknown word: 'usefuled'"
        const quotedMatch = issue.message.match(/['"]([^'"]+)['"]/);
        if (quotedMatch) return quotedMatch[1];
        
        // Look for patterns like "Unknown word: usefuled"
        const wordMatch = issue.message.match(/(?:word|spelling|mistake)[: ]\s*(\w+)/i);
        if (wordMatch) return wordMatch[1];
    }
    
    // Try to find the unusual word in context
    if (issue.context) {
        const words = issue.context.split(/\s+/);
        for (let word of words) {
            const cleanWord = word.replace(/[.,!?;:()'"-]/g, '').toLowerCase();
            if (cleanWord.length > 3 && !commonWords.has(cleanWord) && !/^[A-Z]/.test(word)) {
                return cleanWord;
            }
        }
    }
    
    return 'unknown';

}

// SIMPLIFIED grammar issues extraction
function extractGrammarIssuesWithContext(issuesData) {
    if (!issuesData) return [];
    
    let issues = [];
    
    if (Array.isArray(issuesData)) {
        issues = issuesData;
    } else if (typeof issuesData === 'object' && issuesData.grammar_analysis) {
        issues = issuesData.grammar_analysis.grammar_issues || [];
    }
    
    return issues.map(issue => {
        const context = issue.context || issue.text || issue.sentence || '';
        const mistake = issue.mistake || issue.original || 'grammar issue';
        const suggestion = issue.suggestion || issue.correction || issue.replacements?.[0] || '';
        
        return {
            ...issue,
            context: context,
            mistake: mistake,
            suggestion: suggestion
        };
    });
}

// NEW: Extract grammar mistakes
function extractGrammarMistake(issue) {
    if (issue.mistake) return issue.mistake;
    if (issue.original) return issue.original;
    
    // For grammar, look for the problematic phrase in context
    if (issue.context && issue.suggestion) {
        // Simple approach: find words that are different between context and suggestion
        const contextWords = issue.context.toLowerCase().split(/\s+/);
        const suggestionWords = issue.suggestion.toLowerCase().split(/\s+/);
        
        for (let i = 0; i < Math.min(contextWords.length, suggestionWords.length); i++) {
            if (contextWords[i] !== suggestionWords[i]) {
                return contextWords[i];
            }
        }
    }
    
    return 'grammar issue';
}

// Extract a single-word mistake and correction safely from the issue object
function extractMistakeFromIssue(issue) {
    console.log('extractMistakeFromIssue called with:', issue);

    if (!issue || typeof issue !== 'object') {
        return { context: '', mistake: '', correction: '' };
    }

    // Get context reliably
    let context = '';
    if (typeof issue.context === 'string') context = issue.context;
    else if (issue.context && typeof issue.context === 'object') context = issue.context.text || issue.context.value || '';
    else context = issue.context || '';

    // Attempt to extract known fields
    let mistake = '';
    let correction = '';

    // Try message pattern: "X" should be "Y"
    const msg = issue.message || '';
    const m = msg.match(/"([^"]+)"\s+should\s+be\s+"([^"]+)"/i);
    if (m) {
        mistake = m[1];
        correction = m[2];
    } else {
        // word field
        if (issue.word) {
            if (typeof issue.word === 'string') mistake = issue.word;
            else if (typeof issue.word === 'object') mistake = issue.word.text || issue.word.value || issue.word.word || '';
        }
        // fallback mistake field
        if (!mistake && issue.mistake) {
            if (typeof issue.mistake === 'string') mistake = issue.mistake;
            else if (typeof issue.mistake === 'object') mistake = issue.mistake.text || issue.mistake.value || issue.mistake.word || '';
        }

        // suggestion/correction
        if (issue.suggestion) {
            if (typeof issue.suggestion === 'string') correction = issue.suggestion;
            else if (typeof issue.suggestion === 'object') correction = issue.suggestion.text || issue.suggestion.value || issue.suggestion.correction || '';
        } else if (issue.correction) {
            if (typeof issue.correction === 'string') correction = issue.correction;
            else if (typeof issue.correction === 'object') correction = issue.correction.text || issue.correction.value || '';
        } else if (issue.replacements && Array.isArray(issue.replacements)) {
            correction = issue.replacements[0] || '';
        }
    }

    // Normalize strings
    mistake = mistake ? String(mistake).trim() : '';
    correction = correction ? String(correction).trim() : '';
    context = context ? String(context).trim() : '';

    // If the extracted mistake is clearly invalid (too short or garbage), attempt fuzzy extraction
    const isBadMistake = !mistake || mistake.length < 2 || mistake === '[object Object]' || /[^A-Za-z0-9\-']/.test(mistake);
    if (isBadMistake && context) {
        // If we have a correction, try to find the nearest token in context to the correction
        const target = correction || mistake || '';
        if (target) {
            const words = context.split(/\s+/).map(w => w.replace(/[.,!?;:()"'“”‘’\[\]\-]/g,'')).filter(Boolean);
            let best = { word: '', dist: Infinity, idx: -1 };
            for (let i = 0; i < words.length; i++) {
                const w = words[i];
                // avoid too-common short words
                if (!w || w.length < 2) continue;
                const dist = levenshteinDistance(w.toLowerCase(), target.toLowerCase());
                if (dist < best.dist) {
                    best = { word: w, dist, idx: i };
                }
            }
            if (best.word) {
                mistake = mistake || best.word;
                // If no correction provided, leave correction blank or use correction provided earlier
                if (!correction && typeof issue.suggestion === 'string') correction = issue.suggestion;
                console.log('extractMistakeFromIssue: fuzzy-picked', best.word, 'for target', target);
            }
        }
    }

    // If mistake is a phrase, pick first token for highlighting (like original)
    if (mistake) {
        const tokenMatch = mistake.match(/\b[^\s"'.,;:()?!]+\b/);
        if (tokenMatch) mistake = tokenMatch[0];
    }

    console.log('✅ Extracted:', { context, mistake, correction });
    return { context: context || '', mistake: mistake || '', correction: correction || '' };
}


// NEW: Levenshtein distance for similarity comparison
function levenshteinDistance(a, b) {
    const matrix = [];
    for (let i = 0; i <= b.length; i++) {
        matrix[i] = [i];
    }
    for (let j = 0; j <= a.length; j++) {
        matrix[0][j] = j;
    }
    for (let i = 1; i <= b.length; i++) {
        for (let j = 1; j <= a.length; j++) {
            if (b.charAt(i - 1) === a.charAt(j - 1)) {
                matrix[i][j] = matrix[i - 1][j - 1];
            } else {
                matrix[i][j] = Math.min(
                    matrix[i - 1][j - 1] + 1,
                    matrix[i][j - 1] + 1,
                    matrix[i - 1][j] + 1
                );
            }
        }
    }
    return matrix[b.length][a.length];
}

// Helper function to highlight mistake in text
function highlightMistakeInText(text, mistake) {
    if (!mistake || mistake === 'unknown word') return escapeHtml(text);
    
    const escapedMistake = escapeHtml(mistake);
    const escapedText = escapeHtml(text);
    
    // Create a case-insensitive regex to find the mistake
    const regex = new RegExp(`\\b${escapedMistake}\\b`, 'gi');
    return escapedText.replace(regex, `<span class="highlighted-mistake">$&</span>`);
}

// Helper function to get severity icon
function getSeverityIcon(severity) {
    const severityLower = (severity || '').toLowerCase();
    switch (severityLower) {
        case 'high': return 'exclamation-triangle';
        case 'critical': return 'exclamation-circle';
        case 'medium': return 'exclamation';
        case 'low': return 'info-circle';
        default: return 'info';
    }
}

// Common words for filtering (basic list)
const commonWords = new Set([
    'the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i',
    'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at',
    'this', 'but', 'his', 'by', 'from', 'they', 'we', 'say', 'her', 'she',
    'or', 'an', 'will', 'my', 'one', 'all', 'would', 'there', 'their', 'what',
    'so', 'up', 'out', 'if', 'about', 'who', 'get', 'which', 'go', 'me'
]);

function generateDocumentViewTab(aiData) {
    return `
        <div class="document-view-content">
            <div class="document-preview">
                <h4>Document Content Analysis</h4>
                ${generateDocumentPreview(aiData)}
            </div>
        </div>
    `;
}

// ========== HELPER FUNCTIONS ==========

function generateSectionsList(sections) {
    if (!sections || Object.keys(sections).length === 0) {
        return '<p class="no-sections">No section data available.</p>';
    }

    const sectionsArray = Object.entries(sections);

    return `
        <div class="sections-grid">
            ${sectionsArray
                .map(([sectionName, sectionData]) => {
                    const isPresent = sectionData.present || false;
                    const relevance = sectionData.relevance_percent || 0;

                    return `
                        <div class="section-item ${isPresent ? "present" : "missing"}">
                            <div class="section-header">
                                <span class="section-name">${formatSectionName(sectionName)}</span>
                                <span class="section-status ${isPresent ? "present" : "missing"}">
                                    ${isPresent ? "✓ Present" : "✗ Missing"}
                                </span>
                            </div>
                            ${
                                isPresent
                                ? `
                            <div class="section-details">
                                <div class="detail-row">
                                    <span class="detail-label">Relevance:</span>
                                    <span class="detail-value ${getRelevanceClass(relevance)}">${relevance}%</span>
                                </div>
                            </div>
                            `
                                : ""
                            }
                        </div>
                    `;
                })
                .join("")}
        </div>
    `;
}

function generateStructureRecommendations(completenessScore, relevanceScore, missingSections) {
    const recommendations = [];

    if (completenessScore < 60) {
        recommendations.push("Add missing sections to improve chapter completeness");
    }

    if (relevanceScore < 60) {
        recommendations.push("Improve content relevance to the chapter topic");
    }

    if (missingSections.length > 0) {
        recommendations.push(
            `Focus on adding these sections: ${missingSections.slice(0, 3).join(", ")}${missingSections.length > 3 ? "..." : ""}`,
        );
    }

    if (completenessScore >= 80 && relevanceScore >= 80) {
        recommendations.push("Excellent chapter structure. Maintain current approach.");
    } else if (recommendations.length === 0) {
        recommendations.push("Good structure. Consider minor improvements for better organization.");
    }

    return recommendations.map((rec) => `<div class="recommendation-item">• ${rec}</div>`).join("");
}

function formatSectionName(sectionName) {
    return sectionName
        .split("_")
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// escape regex special chars
function escapeForRegex(s) {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function truncateText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + "...";
}

function getAIScoreClass(score) {
    if (score >= 75) return "high-risk";
    if (score >= 50) return "medium-risk";
    return "low-risk";
}

function getCompletenessClass(score) {
    if (score >= 80) return "excellent";
    if (score >= 60) return "good";
    if (score >= 40) return "fair";
    return "poor";
}

function getRelevanceClass(score) {
    if (score >= 80) return "excellent";
    if (score >= 60) return "good";
    if (score >= 40) return "fair";
    return "poor";
}

function getCitationScoreClass(score) {
    if (score >= 80) return "excellent";
    if (score >= 60) return "good";
    if (score >= 40) return "fair";
    return "poor";
}

function getScoreClass(score) {
    if (score >= 90) return "score-excellent";
    if (score >= 75) return "score-good";
    if (score >= 60) return "score-fair";
    return "score-poor";
}

function getAIScoreDescription(score) {
    if (score >= 75) return "High probability of AI-generated content";
    if (score >= 50) return "Moderate AI content detected";
    return "Low AI content probability";
}

function getCompletenessDescription(score) {
    if (score >= 80) return "Well-structured chapter";
    if (score >= 60) return "Adequate structure";
    if (score >= 40) return "Needs structural improvement";
    return "Poor structure - significant sections missing";
}

function getRelevanceDescription(score) {
    if (score >= 80) return "Highly relevant content";
    if (score >= 60) return "Mostly relevant content";
    if (score >= 40) return "Some relevance issues";
    return "Significant relevance problems";
}

function generateRecommendations(aiScore, completenessScore, relevanceScore, citationScore, citationsAnalyzed) {
    const recommendations = [];

    if (aiScore > 50) {
        recommendations.push("Consider revising sections with high AI probability for more original content");
    }

    if (completenessScore < 60) {
        recommendations.push("Add missing sections to improve chapter structure");
    }

    if (relevanceScore < 60) {
        recommendations.push("Improve content relevance to the chapter topic");
    }

    if (citationsAnalyzed > 0 && citationScore < 60) {
        recommendations.push("Review and correct APA formatting for citations");
    } else if (citationsAnalyzed === 0) {
        recommendations.push("APA citation analysis is only available for Chapter 5");
    }

    if (recommendations.length === 0) {
        recommendations.push("Good overall quality. Continue with current approach.");
    }

    return recommendations.map(rec => `<div class="recommendation-item">• ${rec}</div>`).join('');
}

function generateCitationRecommendations(score, total, correct) {
    const recommendations = [];
    
    if (score < 60) {
        recommendations.push("Review and correct APA formatting for citations");
    }
    
    if (total === 0) {
        recommendations.push("Add a bibliography/references section to your document");
    } else if (correct === 0) {
        recommendations.push("All citations need APA formatting corrections");
    }
    
    if (score >= 80) {
        recommendations.push("Excellent APA formatting. Maintain current practices.");
    } else if (recommendations.length === 0) {
        recommendations.push("Good citation formatting. Minor improvements possible.");
    }
    
    return recommendations.map(rec => `<div class="recommendation-item">• ${rec}</div>`).join('');
}

function generateDocumentPreview(reportData) {
    if (!reportData.analysis || reportData.analysis.length === 0) {
        return `
            <div class="no-content-message">
                <i class="fas fa-file-alt"></i>
                <h4>No structured content available for analysis</h4>
                <p>The document may be empty, contain only images, or the text extraction failed.</p>
            </div>
        `;
    }

    let previewHTML = '<div class="document-content-preview">';
    let currentPage = 1;

    reportData.analysis.slice(0, 15).forEach((section, index) => {
        const isAIContent = section.is_ai;

        // Add page break indicator
        if (section.page && section.page !== currentPage) {
            previewHTML += `
                <div class="page-break-indicator">
                    <i class="fas fa-file"></i> Page ${section.page}
                </div>
            `;
            currentPage = section.page;
        }

        previewHTML += `
            <div class="document-section-preview ${isAIContent ? "ai-flagged" : "human-content"}" id="preview-section-${index}">
                <div class="section-header-preview">
                    <div class="section-type-info">
                        <span class="section-marker">${section.type || "paragraph"} ${index + 1}</span>
                        ${
                            isAIContent
                            ? `
                            <span class="ai-indicator">
                                <i class="fas fa-robot"></i>
                                AI Content
                            </span>
                        `
                            : `
                            <span class="human-indicator">
                                <i class="fas fa-user"></i>
                                Human Content
                            </span>
                        `
                        }
                    </div>
                </div>
                <div class="section-content-preview">
                    ${section.text ? section.text.substring(0, 200) + (section.text.length > 200 ? "..." : "") : "No content available"}
                </div>
            </div>
        `;
    });

    previewHTML += "</div>";

    if (reportData.analysis.length > 15) {
        previewHTML += `<p class="more-content">+ ${reportData.analysis.length - 15} more content sections</p>`;
    }

    return previewHTML;
}

// Analysis filtering function
function filterAnalysis(type) {
    const sections = document.querySelectorAll(".ai-section");
    const filterBtns = document.querySelectorAll(".filter-btn");

    // Update active button
    filterBtns.forEach((btn) => btn.classList.remove("active"));
    event.target.classList.add("active");

    // Filter sections
    sections.forEach((section) => {
        if (type === "all") {
            section.style.display = "";
        } else {
            const sectionType = section.getAttribute("data-type");
            section.style.display = sectionType === type ? "" : "none";
        }
    });
}

// Export function (placeholder)
function exportCombinedReport(chapterNumber, version) {
    showMessage(`Preparing Excel export for Chapter ${chapterNumber}...`, "info");

    // Get the modal data
    const modal = document.querySelector(".analysis-report-modal");
    if (!modal) {
        showMessage("Error: Modal data not found", "error");
        return;
    }

    const aiData = JSON.parse(modal.dataset.aiData || "{}");
    const thesisData = JSON.parse(modal.dataset.thesisData || "{}");

    // Prepare export data
    const exportData = {
        chapter_number: chapterNumber,
        version: version,
        ai_data: aiData,
        thesis_data: thesisData,
        export_timestamp: new Date().toISOString(),
    };

    // Create form and submit
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "export_analysis_report.php";
    form.target = "_blank";

    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "export_data";
    input.value = JSON.stringify(exportData);

    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    showMessage("Excel export started. Check your downloads.", "success");
}

// Global functions
function showDeleteConfirmation(message, onConfirm) {
    const confirmDialog = document.createElement('div');
    confirmDialog.className = 'confirm-dialog';
    confirmDialog.innerHTML = `
        <div class="confirm-dialog-content">
            <h3>Confirm Delete</h3>
            <p>${message}</p>
            <div class="confirm-dialog-buttons">
                <button class="btn btn-danger confirm-yes">Delete</button>
                <button class="btn btn-secondary confirm-no">Cancel</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(confirmDialog);
    
    // Add event listeners
    confirmDialog.querySelector('.confirm-yes').addEventListener('click', () => {
        onConfirm();
        confirmDialog.remove();
    });
    
    confirmDialog.querySelector('.confirm-no').addEventListener('click', () => {
        confirmDialog.remove();
    });
}

window.showDeleteConfirmation = showDeleteConfirmation;

// Safe fallbacks in case these functions are not defined elsewhere.
function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        // remove the modal if it's a dynamically created element
        if (modal.classList && modal.classList.contains('show')) {
            modal.classList.remove('show');
        }
        // also remove from DOM if present
        if (modal.parentNode) modal.parentNode.removeChild(modal);
    }
}

function confirmDelete() {
    // default no-op. Implementers should override this to perform deletion.
    console.warn('confirmDelete() called but not implemented.');
}

window.closeDeleteModal = closeDeleteModal;
window.confirmDelete = confirmDelete;
// Provide a temporary no-op so pages that call window.closeLogoutModal early won't throw.
window.closeLogoutModal = function() {
    console.warn('closeLogoutModal not available yet');
};
window.confirmLogout = confirmLogout;

// Provide safe fallbacks for optional functions that may be missing on some pages
if (typeof exportReport !== 'function') {
    window.exportReport = function() {
        console.warn('exportReport() called but not implemented');
        showMessage('Export not available on this page.', 'error');
    };
}

if (typeof filterUploadHistory !== 'function') {
    window.filterUploadHistory = function() {
        console.warn('filterUploadHistory() called but not implemented');
    };
}

if (typeof triggerFileUpload !== 'function') {
    window.triggerFileUpload = function() {
        console.warn('triggerFileUpload() called but not implemented');
        showMessage('File upload not configured here.', 'error');
    };
}

if (typeof viewValidationReportBtn !== 'function') {
    window.viewValidationReportBtn = function() {
        console.warn('viewValidationReportBtn() called but not implemented');
    };
}

if (typeof viewValidationReport !== 'function') {
    window.viewValidationReport = function() {
        console.warn('viewValidationReport() called but not implemented');
        showMessage('Validation report not available.', 'error');
    };
}

if (typeof viewThesisReport !== 'function') {
    window.viewThesisReport = function() {
        console.warn('viewThesisReport() called but not implemented');
        showMessage('Thesis report not available.', 'error');
    };
}

if (typeof viewAIReport !== 'function') {
    window.viewAIReport = function(chapterNumber, version) {
        console.warn('viewAIReport() called but not implemented');
        viewComprehensiveReport(chapterNumber, version);
    };
}

window.exportCombinedReport = exportCombinedReport;
window.filterAnalysis = filterAnalysis;

// Modal closing functionality
document.addEventListener("click", (e) => {
    if (e.target.classList.contains("modal")) {
        e.target.classList.remove("show");
    }
    if (e.target.classList.contains("analysis-report-modal")) {
        e.target.remove();
    }
});

document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
        document.querySelectorAll(".modal.show").forEach((modal) => {
            modal.classList.remove("show");
        });
        document.querySelectorAll(".analysis-report-modal.show").forEach((modal) => {
            modal.remove();
        });
    }
});

// Add these global functions for tab switching
window.switchMainTab = function(tabName) {
    // Update active main tab header
    const mainTabHeaders = document.querySelectorAll('.main-tab-header');
    mainTabHeaders.forEach(header => {
        header.classList.remove('active');
    });
    
    // Find and activate the clicked tab
    const activeHeader = Array.from(mainTabHeaders).find(header => 
        header.textContent.includes(tabName === 'detailed-issues' ? 'Detailed Issues' : 'Analysis Reports')
    );
    if (activeHeader) {
        activeHeader.classList.add('active');
    }
    
    // Update active main tab content
    const mainTabContents = document.querySelectorAll('.main-tab-content');
    mainTabContents.forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(tabName + '-tab').classList.add('active');
};

window.switchIssueTab = function(issueType) {
    // Update active issue tab
    const issueTabs = document.querySelectorAll('.issue-tab');
    issueTabs.forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Find and activate the clicked tab. Prefer a data-issue attribute when present.
    const activeTab = Array.from(issueTabs).find(tab => {
        const dataIssue = tab.dataset.issue;
        if (dataIssue) return dataIssue.toLowerCase() === String(issueType).toLowerCase();
        return (tab.textContent || '').toLowerCase().includes(String(issueType).toLowerCase());
    });
    if (activeTab) activeTab.classList.add('active');
    
    // Update active issue content
    const issueContents = document.querySelectorAll('.issues-content');
    issueContents.forEach(content => content.classList.remove('active'));
    const target = document.getElementById(String(issueType) + '-issues-content');
    if (target) target.classList.add('active');
};

window.switchReportTab = function(reportType) {
    // Update active report tab
    const reportTabs = document.querySelectorAll('.report-tab');
    reportTabs.forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Find and activate the clicked tab
    const activeTab = Array.from(reportTabs).find(tab => 
        tab.textContent.toLowerCase().includes(reportType)
    );
    if (activeTab) {
        activeTab.classList.add('active');
    }
    
    // Update active report content
    const reportContents = document.querySelectorAll('.report-content');
    reportContents.forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(reportType + '-report-content').classList.add('active');
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - initializing review system');
    initReviewForm();
    
    // Close buttons
    const closeButtons = document.querySelectorAll('.close');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                closeModal(modal.id);
                // Restore the Review Now button if the modal was cancelled
                restoreLastReviewButton();
            }
        });
    });
    
    // Set current group ID from first available chapter
    const firstChapter = document.querySelector('.chapter-item');
    if (firstChapter) {
        const reviewBtn = firstChapter.querySelector('.btn-primary');
        if (reviewBtn) {
            const onclick = reviewBtn.getAttribute('onclick');
            const match = onclick.match(/reviewChapter\((\d+),/);
            if (match) {
                // We need to get group ID from a different approach
                // For now, we'll set it when the analysis button is clicked
            }
        }
    }
});

// Notification functionality
// Notification functionality is handled by initNotifications() and shared helper functions above

// Logout functionality
document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutLink = document.getElementById('logoutLink');
    const logoutModal = document.getElementById('logoutModal');
    const cancelLogout = document.getElementById('cancelLogout');
    const confirmLogout = document.getElementById('confirmLogout');
    
    function openLogoutModal() {
        if (logoutModal) {
            logoutModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeLogoutModal() {
        if (logoutModal) {
            logoutModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }
    
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openLogoutModal();
        });
    }
    
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            openLogoutModal();
        });
    }
    
    if (cancelLogout) {
        cancelLogout.addEventListener('click', closeLogoutModal);
    }
    
    if (logoutModal) {
        logoutModal.addEventListener('click', function(e) {
            if (e.target === logoutModal) {
                closeLogoutModal();
            }
        });
    }
    
    // Escape key to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && logoutModal.style.display === 'flex') {
            closeLogoutModal();
        }
    });
});

function confirmLogout() {
    window.location.href = '../logout.php';
}

// View Chapter File
function viewChapterFile(chapterId) {
    console.log('Viewing chapter file:', chapterId);
    window.open(`advisor_reviews.php?action=view_chapter&chapter_id=${chapterId}`, '_blank');
}

// Show Chapter Details
function showChapterDetails(chapterId) {
    showMessage(`Viewing details for Chapter ID: ${chapterId}`, 'info');
    // You can implement a detailed view modal here
}

// Close Modal (unified) - accepts optional modalId
function closeModal(modalId) {
    try {
        let modal = null;
        if (modalId) {
            modal = document.getElementById(modalId);
        } else {
            modal = document.getElementById('reviewModal');
        }

        if (modal) {
            modal.style.display = 'none';
        }

        // Restore scrolling
        document.body.style.overflow = 'auto';

        // If review modal was closed, restore the Review Now button if needed
        if (!modalId || modalId === 'reviewModal') {
            restoreLastReviewButton();
        }
    } catch (e) {
        console.warn('closeModal error', e);
    }
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
        const pageTitle = mainContent.querySelector(".page-title-section");
        if (pageTitle) {
            pageTitle.insertAdjacentElement("afterend", message);
        } else {
            mainContent.prepend(message);
        }
    }

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (message.parentNode) {
            message.style.opacity = '0';
            message.style.transform = 'translateY(-10px)';
            setTimeout(() => message.remove(), 300);
        }
    }, 5000);
}

// Responsive sidebar toggle
function toggleSidebar() {
    const sidebar = document.querySelector(".sidebar");
    if (sidebar) sidebar.classList.toggle("open");
}

// Handle window resize for sidebar
window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar");
    if (window.innerWidth > 768 && sidebar) {
        sidebar.classList.remove("open");
    }
});

// Make logout functions globally available
window.closeLogoutModal = function() {
    const logoutModal = document.getElementById('logoutModal');
    if (logoutModal) {
        logoutModal.style.display = 'none';
        document.body.style.overflow = '';
    }
};

window.confirmLogout = function() {
    window.location.href = '../logout.php';
};

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Close modals with Escape key
    if (e.key === 'Escape') {
        closeModal();
        window.closeLogoutModal();
    }
    
    // Refresh data with F5
    if (e.key === 'F5') {
        e.preventDefault();
        window.location.reload();
    }
});

window.getFormattingScoreClass = getFormattingScoreClass;
window.safeMap = safeMap;
window.showPageDetails = showPageDetails;
window.showAllPages = showAllPages;
window.changeFormattingPage = changeFormattingPage;
window.updateFormattingPagination = updateFormattingPagination;
window.renderPagesGrid = renderPagesGrid;
window.viewComprehensiveReport = viewComprehensiveReport;
window.reviewChapter = reviewChapter;
window.viewChapterFile = viewChapterFile;
window.showChapterDetails = showChapterDetails;
