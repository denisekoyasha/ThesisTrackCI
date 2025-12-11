document.addEventListener("DOMContentLoaded", () => {
  // Initialize all systems
  initTabSystem()
  initFileUploads()
  initUI()
  initAnimations()
  initUploadChart()
})

function initTabSystem() {
  const navItems = document.querySelectorAll(".nav-item[data-tab]")
  const currentPage = window.location.pathname.split("/").pop()

  navItems.forEach((item) => {
    item.addEventListener("click", function (e) {
      const tabId = this.getAttribute("data-tab")
      const targetContent = document.getElementById(tabId)

      if (!targetContent) {
        return
      }

      e.preventDefault()

      document.querySelectorAll(".nav-item").forEach((nav) => nav.classList.remove("active"))
      document.querySelectorAll(".tab-content").forEach((tab) => tab.classList.remove("active"))

      this.classList.add("active")
      targetContent.classList.add("active")
    })

    if (item.getAttribute("href").includes(currentPage)) {
      item.classList.add("active")
      const tabId = item.getAttribute("data-tab")
      if (tabId) {
        document.getElementById(tabId)?.classList.add("active")
      }
    }
  })
}

function initFileUploads() {
  // Add a small delay to ensure DOM is fully loaded
  setTimeout(() => {
    // Add event listeners to all file inputs
    for (let i = 1; i <= 5; i++) {
      const fileInput = document.getElementById(`chapter${i}`)
      if (fileInput) {
        fileInput.addEventListener("change", function (e) {
          if (this.files && this.files[0]) {
            uploadAndAnalyze(this.files[0], i)
          }
        })
        console.log(`File input chapter${i} found and initialized`)
      } else {
        console.warn(`File input chapter${i} not found - this is normal for empty chapters`)
      }
    }

    // Initialize drag and drop for upload areas
    const uploadAreas = document.querySelectorAll(".upload-area")
    uploadAreas.forEach((area) => {
      area.addEventListener("dragover", function (e) {
        e.preventDefault()
        this.classList.add("drag-over")
      })

      area.addEventListener("dragleave", function (e) {
        e.preventDefault()
        this.classList.remove("drag-over")
      })

      area.addEventListener("drop", function (e) {
        e.preventDefault()
        this.classList.remove("drag-over")

        const files = e.dataTransfer.files
        if (files.length > 0) {
          const chapterInput = this.closest(".chapter-card")?.querySelector('input[type="file"]')
          const chapterNumber = chapterInput ? chapterInput.id.replace("chapter", "") : null

          if (chapterNumber) {
            uploadAndAnalyze(files[0], Number.parseInt(chapterNumber))
          }
        }
      })
    })
  }, 100)
}

function uploadAndAnalyze(file, chapterNumber) {
  // Validate file type
  const allowedTypes = [
    "application/pdf",
    "application/msword",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
  ];

  if (!allowedTypes.includes(file.type)) {
    showMessage("Error: Only PDF, DOC, and DOCX files are allowed.", "error")
    return
  }

  // Check file size (10MB limit)
  const maxSize = 10 * 1024 * 1024
  if (file.size > maxSize) {
    showMessage("Error: File size must be less than 10MB.", "error")
    return
  }

  showUploadingState(chapterNumber, file.name)

  const formData = new FormData()
  formData.append("file", file)
  formData.append("chapter_number", chapterNumber.toString())

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      // First, check if response is HTML (error)
      const contentType = response.headers.get("content-type")
      if (contentType && contentType.includes("text/html")) {
        throw new Error("Server returned HTML instead of JSON. Check for PHP errors.")
      }

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      return response.text() // Get as text first
    })
     .then((text) => {
      try {
        const data = JSON.parse(text)
        console.log("Upload response:", data)

         if (data.success) {
          // Debug: log server-side notification creation flags when present
          if (data.debug_notifications) {
            console.log('Server debug_notifications:', data.debug_notifications)
          }
          let message = "File uploaded successfully!"

          if (data.ai_score !== null && data.ai_score !== undefined) {
            message += ` AI Analysis: ${Number.parseFloat(data.ai_score).toFixed(2)}% AI content probability.`

            const analyzed = data.sentences_analyzed || 0
            const flagged = data.sentences_flagged || 0

            if (analyzed > 0) {
              message += ` ${flagged} out of ${analyzed} text chunks flagged.`
            }
          } else {
            message += " (AI analysis not available for this file type)"
          }

           // Check thesis analysis
          if (data.has_thesis_analysis) {
            message += ` Thesis Structure: ${data.completeness_score || 0}% complete.`
          }

            // Add formatting analysis to message
                if (data.formatting_score !== null && data.formatting_score !== undefined) {
                    message += ` Formatting Compliance: ${Number.parseFloat(data.formatting_score).toFixed(2)}%.`;
                }

                showMessage(message, 'success');
                updateChapterUI(chapterNumber, data);
                setTimeout(() => {
                    window.location.reload();
                }, 2000);


          // Check citation analysis - ONLY for Chapter 5
          if (data.has_citation_analysis && chapterNumber === 5) {
            message += ` APA Citations: ${data.citation_score || 0}% properly formatted.`
          } else if (chapterNumber === 5) {
            // Only mention citation analysis for Chapter 5 if it's not available
            message += " (Citation analysis not available)"
          }

          showMessage(message, "success")
          updateChapterUI(chapterNumber, data)
          setTimeout(() => {
            window.location.reload()
          }, 2000)
        } else {
          showMessage("Upload failed: " + (data.error || "Unknown error"), "error")
          resetUploadState(chapterNumber)
        }
       } catch (e) {
        console.error("JSON parse error:", e)
        console.error("Response text:", text)
        throw new Error("Invalid JSON response from server")
      }
    })
    .catch((error) => {
      console.error("Upload error:", error)
      showMessage("Upload failed: " + error.message, "error")
      resetUploadState(chapterNumber)
    })
}

function showUploadingState(chapterNumber, filename) {
  const fileInput = document.getElementById(`chapter${chapterNumber}`)
  const uploadArea = fileInput ? fileInput.closest(".chapter-card")?.querySelector(".upload-area") : null

  if (uploadArea) {
    uploadArea.innerHTML = `
            <div class="uploading-state">
                <div class="spinner"></div>
                <div class="uploading-text">
                    <p>Uploading: ${filename}</p>
                    <p class="upload-hint">AI analysis in progress...</p>
                </div>
            </div>
        `
  }
}

function resetUploadState(chapterNumber) {
  const fileInput = document.getElementById(`chapter${chapterNumber}`)
  if (!fileInput) {
    console.log(`File input for chapter ${chapterNumber} not found during reset - this is normal`)
    return
  }

  const uploadArea = fileInput.closest(".chapter-card")?.querySelector(".upload-area")
  if (uploadArea) {
    uploadArea.innerHTML = `
            <div class="upload-icon">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <div class="upload-text">
                <p class="upload-prompt">Click to upload or drag and drop</p>
                <p class="upload-hint">PDF, DOC, DOCX files only (Max 10MB)</p>
            </div>
        `
  }
}

function updateChapterUI(chapterNumber, data) {
  const fileInput = document.getElementById(`chapter${chapterNumber}`)
  const chapterCard = fileInput ? fileInput.closest(".chapter-card") : null

  if (chapterCard) {
    const fileNameElement = chapterCard.querySelector(".file-name")
    if (fileNameElement) {
      fileNameElement.textContent = data.filename
    }

    const statusElement = chapterCard.querySelector(".chapter-status")
    if (statusElement) {
      statusElement.textContent = "Uploaded"
      statusElement.className = "chapter-status status-badge uploaded"
    }

    updateAIValidation(chapterCard, data)
  }
}

function updateAIValidation(chapterCard, data) {
  let validationSection = chapterCard.querySelector(".ai-validation")

  if (!validationSection) {
    validationSection = document.createElement("div")
    validationSection.className = "ai-validation"
    chapterCard.appendChild(validationSection)
  }

  // Check if we have valid AI data
  const hasAIData = data.ai_score !== null && data.ai_score !== undefined
  const hasAnalysisData = data.sentences_analyzed > 0
  const hasCitationData = data.citation_score !== null && data.citation_score !== undefined

  let validationHTML = ""

  if (hasAIData && hasAnalysisData) {
    const aiScore = Number.parseFloat(data.ai_score)
    const scoreClass = aiScore >= 75 ? "high" : aiScore >= 50 ? "medium" : "low"
    const scoreText = aiScore >= 75 ? "High AI Content" : aiScore >= 50 ? "Moderate AI Content" : "Low AI Content"

    validationHTML = `
            <div class="validation-header">
                <i class="fas fa-robot"></i>
                <span>AI Content Analysis</span>
            </div>
            <div class="validation-score">
                <span class="score-label">AI Probability Score:</span>
                <span class="score-badge score-${scoreClass}" title="${scoreText}">${aiScore.toFixed(2)}%</span>
            </div>
            <div class="validation-details">
                <div class="detail-item">
                    <span class="detail-label">Text Chunks Analyzed:</span>
                    <span class="detail-value">${data.sentences_analyzed || 0}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">AI Chunks Flagged:</span>
                    <span class="detail-value">${data.sentences_flagged || 0}</span>
                </div>
            </div>
            <div class="validation-issues">
                <p>${data.ai_feedback || "Analysis completed"}</p>
            </div>
        `
  } else if (hasAIData && !hasAnalysisData) {
    // Has score but no analysis details (chapters 1-3 case)
    const aiScore = Number.parseFloat(data.ai_score)
    const scoreClass = aiScore >= 75 ? "high" : aiScore >= 50 ? "medium" : "low"

    validationHTML = `
            <div class="validation-header">
                <i class="fas fa-robot"></i>
                <span>AI Content Analysis</span>
            </div>
            <div class="validation-score">
                <span class="score-label">AI Probability Score:</span>
                <span class="score-badge score-${scoreClass}">${aiScore.toFixed(2)}%</span>
            </div>
            <div class="validation-issues">
                <p>${data.ai_feedback || "Basic analysis completed"}</p>
                <p class="warning-text">Detailed chunk analysis not available for this chapter</p>
            </div>
        `
  } else {
    validationHTML = `
            <div class="validation-header">
                <i class="fas fa-robot"></i>
                <span>AI Analysis</span>
            </div>
            <div class="validation-issues">
                <p>${data.ai_feedback || "AI analysis not available for this file type"}</p>
            </div>
        `
  }

  // Add Citation Analysis Section if available
  if (hasCitationData) {
    const citationScore = Number.parseFloat(data.citation_score)
    const citationClass = citationScore >= 80 ? "high" : citationScore >= 60 ? "medium" : "low"
    const totalCitations = data.total_citations || 0
    const correctCitations = data.correct_citations || 0

    validationHTML += `
            <div class="citation-analysis-section">
                <div class="validation-header citation-header">
                    <i class="fas fa-quote-right"></i>
                    <span>APA Citation Analysis</span>
                </div>
                
                <div class="citation-scores-row">
                    <div class="score-item">
                        <span class="score-label">Citation Score:</span>
                        <span class="score-badge score-${citationClass}">${citationScore.toFixed(2)}%</span>
                    </div>
                    <div class="score-item">
                        <span class="score-label">Citations Checked:</span>
                        <span class="score-badge score-neutral">${totalCitations}</span>
                    </div>
                </div>
                
                <div class="validation-details">
                    <div class="detail-item">
                        <span class="detail-label">Properly Formatted:</span>
                        <span class="detail-value">${correctCitations}/${totalCitations}</span>
                    </div>
                </div>
                
                ${data.citation_feedback ? `
                <div class="citation-feedback">
                    <p><strong>Citation Feedback:</strong> ${data.citation_feedback}</p>
                </div>
                ` : ''}
            </div>
        `
  } else {
    // Show citation analysis unavailable message
    validationHTML += `
            <div class="citation-analysis-section">
                <div class="validation-header citation-header">
                    <i class="fas fa-quote-right"></i>
                    <span>APA Citation Analysis</span>
                </div>
                <div class="validation-issues">
                    <p>${data.citation_feedback || "Citation analysis not available for this file type"}</p>
                </div>
            </div>
        `
  }

  // Thesis Analysis Section - ONLY if we have thesis data
  if (data.completeness_score !== null && data.completeness_score !== undefined) {
    const completenessScore = Number.parseFloat(data.completeness_score)
    const completenessClass = completenessScore >= 80 ? "high" : completenessScore >= 60 ? "medium" : "low"
    const completenessText =
      completenessScore >= 80
        ? "Well Structured"
        : completenessScore >= 60
          ? "Moderately Structured"
          : "Needs Improvement"

    const relevanceScore = Number.parseFloat(data.relevance_score || 0)
    const relevanceClass = relevanceScore >= 80 ? "high" : relevanceScore >= 60 ? "medium" : "low"

    validationHTML += `
            <div class="thesis-analysis-section">
                <div class="validation-header thesis-header">
                    <i class="fas fa-file-alt"></i>
                    <span>Chapter Structure Analysis</span>
                </div>
                
                <div class="thesis-scores-row">
                    <div class="score-item">
                        <span class="score-label">Completeness Score:</span>
                        <span class="score-badge score-${completenessClass}" title="${completenessText}">${completenessScore.toFixed(2)}%</span>
                    </div>
                    <div class="score-item">
                        <span class="score-label">Relevance Score:</span>
                        <span class="score-badge score-${relevanceClass}">${relevanceScore.toFixed(2)}%</span>
                    </div>
                </div>
                
                <div class="validation-details">
                    <div class="detail-item">
                        <span class="detail-label">Sections Found:</span>
                        <span class="detail-value">${data.thesis_sections_found || 0}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Missing Sections:</span>
                        <span class="detail-value">${data.thesis_missing_sections || 0}</span>
                    </div>
                </div>
                
                ${
                  data.completeness_feedback
                    ? `
                <div class="thesis-feedback">
                    <p><strong>Structure Feedback:</strong> ${data.completeness_feedback}</p>
                </div>
                `
                    : ""
                }
            </div>
        `
  }

  // In your viewComprehensiveReport function, add this debug log:
console.log("🔧 Formatting data structure:", {
    success: formattingData.success,
    hasAnalysis: !!formattingData.formatting_analysis,
    overallScore: formattingData.formatting_analysis?.overall_score,
    recommendations: formattingData.formatting_analysis?.recommendations?.length
});

  // Single button for combined comprehensive report
  validationHTML += `
        <div class="analysis-actions">
            <button class="btn-primary btn-small" onclick="viewComprehensiveReport(${data.chapter_number}, ${data.version})">
                <i class="fas fa-chart-bar"></i> View Full Analysis Report
            </button>
        </div>
    `

  validationSection.innerHTML = validationHTML
}

 
// Update the citation analysis tab to show proper messages for non-Chapter 5
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
        `
  }
  
  if (!citationData || citationData.error || !citationData.success) {
    return `
            <div class="no-citation-data">
                <div class="no-data-icon">
                    <i class="fas fa-quote-right"></i>
                </div>
                <h5>APA Citation Analysis Not Available</h5>
                <p>Citation analysis data is not available for this document.</p>
                ${citationData && citationData.error ? `<p class="error-message">${citationData.error}</p>` : ''}
                ${citationData && citationData.message ? `<p class="info-message">${citationData.message}</p>` : ''}
            </div>
        `
  }

  const totalCitations = citationData.total_citations || 0
  const correctCitations = citationData.correct_citations || 0
  const citationScore = citationData.citation_score || (totalCitations > 0 ? Math.round((correctCitations / totalCitations) * 100) : 0)
  const correctedCitations = citationData.corrected_citations || []
  const citationFeedback = citationData.citation_feedback || 'No citation feedback available'

  // Check if we have any meaningful data
  const hasCitationData = totalCitations > 0 || citationScore > 0 || correctedCitations.length > 0

  if (!hasCitationData) {
    return `
            <div class="no-citation-data">
                <div class="no-data-icon">
                    <i class="fas fa-quote-right"></i>
                </div>
                <h5>APA Citation Analysis Not Available</h5>
                <p>No citation data was found for this document. The citation analysis may not have run successfully.</p>
                ${citationFeedback ? `<p class="citation-feedback-preview">${citationFeedback}</p>` : ''}
            </div>
        `
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
            
            ${citationFeedback && citationFeedback !== 'No citation feedback available' ? `
            <div class="citation-feedback-section">
                <h5>Analysis Summary</h5>
                <div class="feedback-message">
                    <p>${citationFeedback}</p>
                </div>
            </div>
            ` : ''}
            
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
    `
}


function getCitationScoreClass(score) {
  if (score >= 80) return 'excellent'
  if (score >= 60) return 'good'
  if (score >= 40) return 'fair'
  return 'poor'
}

function generateCitationRecommendations(score, total, correct) {
  const recommendations = []
  
  if (score < 60) {
    recommendations.push("Review and correct APA formatting for citations")
  }
  
  if (total === 0) {
    recommendations.push("Add a bibliography/references section to your document")
  } else if (correct === 0) {
    recommendations.push("All citations need APA formatting corrections")
  }
  
  if (score >= 80) {
    recommendations.push("Excellent APA formatting. Maintain current practices.")
  } else if (recommendations.length === 0) {
    recommendations.push("Good citation formatting. Minor improvements possible.")
  }
  
  return recommendations.map(rec => `<div class="recommendation-item">• ${rec}</div>`).join('')
}

function showBasicCombinedReport(chapterNumber, version, groupId) {
  // Try to get data from the current page UI
  const chapterCard = document.querySelector(`#chapter${chapterNumber}`)?.closest(".chapter-card")

  if (!chapterCard) {
    showMessage("No chapter data found. The chapter may not exist or you may need to refresh the page.", "warning")
    return
  }

  // Extract basic data from UI with better error handling
  let aiScore = 0
  let fileName = "Unknown"

  try {
    const scoreElement = chapterCard.querySelector(".score-badge")
    const fileNameElement = chapterCard.querySelector(".file-name")

    aiScore = scoreElement ? Number.parseFloat(scoreElement.textContent) : 0
    fileName = fileNameElement ? fileNameElement.textContent : "Unknown"
  } catch (error) {
    console.warn("Error extracting UI data:", error)
  }

  // Create enhanced mock data with proper structure
  const mockAIData = {
    success: true,
    chapter_number: chapterNumber,
    version: version,
    overall_ai_percentage: aiScore,
    total_sentences_analyzed: 0,
    sentences_flagged_as_ai: 0,
    analysis: [],
    generated_on: new Date().toISOString(),
    data_status: "missing",
    warning: "Showing basic data only. Detailed analysis may not be available due to database limitations.",
  }

  const mockThesisData = {
    success: true,
    chapter_number: chapterNumber,
    version: version,
    sections: {},
    chapter_scores: {
      chapter_completeness_score: 0,
      chapter_relevance_score: 0,
      present_sections: 0,
      total_sections: 0,
      missing_sections: ["Detailed structure analysis not available"],
    },
    data_status: "missing",
  }

  showCombinedReportModal(mockAIData, mockThesisData)
  showMessage("Showing basic report data. For full analysis, ensure database columns are properly configured.", "info")
}



// Add this new function for citation analysis tab
function generateCitationAnalysisTab(citationData) {
  if (!citationData || citationData.error) {
    return `
            <div class="no-citation-data">
                <div class="no-data-icon">
                    <i class="fas fa-quote-right"></i>
                </div>
                <h5>APA Citation Analysis Not Available</h5>
                <p>Citation analysis data is not available for this document.</p>
                ${citationData && citationData.error ? `<p class="error-message">${citationData.error}</p>` : ''}
            </div>
        `
  }

  const totalCitations = citationData.total_citations || 0
  const correctCitations = citationData.correct_citations || 0
  const citationScore = totalCitations > 0 ? Math.round((correctCitations / totalCitations) * 100) : 0
  const correctedCitations = citationData.corrected_citations || []

  return `
        <div class="citation-analysis-content">
            <div class="citation-summary">
                <h4>APA Citation Analysis</h4>
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
                                    <strong>Original:</strong> ${citation.original}
                                </div>
                                <div class="citation-corrected">
                                    <strong>Corrected:</strong> ${citation.corrected}
                                </div>
                                <div class="citation-reasoning">
                                    <strong>Changes:</strong> ${citation.reasoning}
                                </div>
                            </div>
                            ` : `
                            <div class="citation-content">
                                ${citation.corrected}
                            </div>
                            `}
                        </div>
                    `).join('')}
                </div>
            </div>
            ` : `
            <div class="no-citations-found">
                <p>No citations were found in the bibliography section of this document.</p>
            </div>
            `}
            
            <div class="citation-recommendations">
                <h5>APA Formatting Recommendations</h5>
                <div class="recommendation-list">
                    ${generateCitationRecommendations(citationScore, totalCitations, correctCitations)}
                </div>
            </div>
        </div>
    `
}

function getCitationScoreClass(score) {
  if (score >= 80) return 'excellent'
  if (score >= 60) return 'good'
  if (score >= 40) return 'fair'
  return 'poor'
}

function generateCitationRecommendations(score, total, correct) {
  const recommendations = []
  
  if (score < 60) {
    recommendations.push("Review and correct APA formatting for citations")
  }
  
  if (total === 0) {
    recommendations.push("Add a bibliography/references section to your document")
  } else if (correct === 0) {
    recommendations.push("All citations need APA formatting corrections")
  }
  
  if (score >= 80) {
    recommendations.push("Excellent APA formatting. Maintain current practices.")
  } else if (recommendations.length === 0) {
    recommendations.push("Good citation formatting. Minor improvements possible.")
  }
  
  return recommendations.map(rec => `<div class="recommendation-item">• ${rec}</div>`).join('')
}

// Update the generateOverviewTab function to include spelling and grammar scores
function generateOverviewTab(aiData, thesisData, citationData, spellingGrammarData) {
    const aiScore = aiData.overall_ai_percentage || 0;
    const completenessScore = thesisData.chapter_scores?.chapter_completeness_score || 0;
    const relevanceScore = thesisData.chapter_scores?.chapter_relevance_score || 0;
    const spellingScore = spellingGrammarData?.spelling_score || 0;
    const grammarScore = spellingGrammarData?.grammar_score || 0;
    
    // Citation score calculation (only for Chapter 5)
    let citationScore = 0;
    let citationsAnalyzed = 0;
    if (citationData.success && citationData.total_citations > 0) {
        citationScore = citationData.citation_score || Math.round((citationData.correct_citations / citationData.total_citations) * 100);
        citationsAnalyzed = citationData.total_citations;
    }

    return `
        <div class="overview-content">
            <div class="overview-scores">
                <div class="score-card ai-score">
                    <div class="score-value ${getAIScoreClass(aiScore)}">${aiScore}%</div>
                    <div class="score-label">AI Content Probability</div>
                    <div class="score-description">${getAIScoreDescription(aiScore)}</div>
                </div>
                <div class="score-card completeness-score">
                    <div class="score-value ${getCompletenessClass(completenessScore)}">${completenessScore}%</div>
                    <div class="score-label">Structure Completeness</div>
                    <div class="score-description">${getCompletenessDescription(completenessScore)}</div>
                </div>
                <div class="score-card spelling-score">
                    <div class="score-value ${getSpellingClass(spellingScore)}">${spellingScore}%</div>
                    <div class="score-label">Spelling Accuracy</div>
                    <div class="score-description">${getSpellingDescription(spellingScore)}</div>
                </div>
                <div class="score-card grammar-score">
                    <div class="score-value ${getGrammarClass(grammarScore)}">${grammarScore}%</div>
                    <div class="score-label">Grammar Accuracy</div>
                    <div class="score-description">${getGrammarDescription(grammarScore)}</div>
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
                    ${generateRecommendations(aiScore, completenessScore, relevanceScore, citationScore, citationsAnalyzed, spellingScore, grammarScore)}
                </div>
            </div>
        </div>
    `;
}

// Add helper functions for spelling and grammar
function getSpellingClass(score) {
    if (score >= 95) return 'excellent';
    if (score >= 85) return 'good';
    if (score >= 70) return 'fair';
    return 'poor';
}

function getGrammarClass(score) {
    if (score >= 95) return 'excellent';
    if (score >= 85) return 'good';
    if (score >= 70) return 'fair';
    return 'poor';
}

function getSpellingDescription(score) {
    if (score >= 95) return 'Excellent spelling';
    if (score >= 85) return 'Good spelling';
    if (score >= 70) return 'Moderate spelling issues';
    return 'Needs spelling improvement';
}

function getGrammarDescription(score) {
    if (score >= 95) return 'Excellent grammar';
    if (score >= 85) return 'Good grammar';
    if (score >= 70) return 'Moderate grammar issues';
    return 'Needs grammar improvement';
}

// Update recommendations to include formatting
function generateRecommendations(aiScore, completenessScore, relevanceScore, citationScore, citationsAnalyzed, spellingScore, grammarScore, formattingScore) {
    const recommendations = [];

    if (aiScore > 50) {
        recommendations.push('Consider revising sections with high AI probability for more original content');
    }

    if (completenessScore < 60) {
        recommendations.push('Add missing sections to improve chapter structure');
    }

    if (relevanceScore < 60) {
        recommendations.push('Improve content relevance to the chapter topic');
    }

    if (citationsAnalyzed > 0 && citationScore < 60) {
        recommendations.push('Review and correct APA formatting for citations');
    } else if (citationsAnalyzed === 0) {
        recommendations.push('APA citation analysis is only available for Chapter 5');
    }

    if (formattingScore < 60) {
        recommendations.push('Review document formatting guidelines and make necessary adjustments');
    }

    if (spellingScore < 85) {
        recommendations.push('Review and correct spelling errors throughout the document');
    }

    if (grammarScore < 85) {
        recommendations.push('Review and correct grammatical errors in the document');
    }

    if (recommendations.length === 0) {
        recommendations.push('Good overall quality. Continue with current approach.');
    }

    return recommendations.map(rec => `<div class="recommendation-item">• ${rec}</div>`).join('');
}
function generateSpellingGrammarTab(spellingGrammarData) {
    if (!spellingGrammarData || !spellingGrammarData.success) {
        return `
            <div class="no-spelling-grammar-data">
                <div class="no-data-icon">
                   
                </div>
                
                <p>Spelling and grammar analysis is not available for this document.</p>
                ${spellingGrammarData && spellingGrammarData.error ? 
                    `<div class="error-message">
                        <strong>Note:</strong> ${spellingGrammarData.error}
                    </div>` : ''}
            </div>
        `;
    }

    const spellingScore = spellingGrammarData.spelling_score || 0;
    const grammarScore = spellingGrammarData.grammar_score || 0;
    const spellingIssues = spellingGrammarData.spelling_analysis?.total_spelling_issues || 0;
    const grammarIssues = spellingGrammarData.grammar_analysis?.total_grammar_issues || 0;
    const wordCount = spellingGrammarData.spelling_analysis?.analysis_details?.statistics?.word_count || 0;

    return `
        <div class="spelling-grammar-content">
            <!-- Clean Summary Header -->
            <div class="spelling-grammar-summary">
                
                
                <div class="spelling-grammar-stats">
                    <div class="stat">
                        <span class="stat-label">Spelling Accuracy</span>
                        <span class="stat-value ${getSpellingClass(spellingScore)}">
                            ${spellingScore}%
                        </span>
                    </div>
                    
                    <div class="stat">
                        <span class="stat-label">Grammar Accuracy</span>
                        <span class="stat-value ${getGrammarClass(grammarScore)}">
                            ${grammarScore}%
                        </span>
                    </div>
                    
                    <div class="stat">
                        <span class="stat-label">Words Analyzed</span>
                        <span class="stat-value">${wordCount.toLocaleString()}</span>
                    </div>
                </div>
                
                <!-- Issues Overview -->
                <div class="issues-breakdown">
                    <div class="issues-row">
                        <div class="issue-type spelling">
                            <span class="issue-count">${spellingIssues}</span>
                            <span class="issue-label">Spelling Issues</span>
                        </div>
                        <div class="issue-type grammar">
                            <span class="issue-count">${grammarIssues}</span>
                            <span class="issue-label">Grammar Issues</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Feedback Section -->
            <div class="feedback-section">
                <div class="feedback-item">
                    <h5>Spelling Feedback</h5>
                    <p>${spellingGrammarData.spelling_feedback || 'No spelling feedback available.'}</p>
                </div>
                <div class="feedback-item">
                    <h5>Grammar Feedback</h5>
                    <p>${spellingGrammarData.grammar_feedback || 'No grammar feedback available.'}</p>
                </div>
            </div>
            
            <!-- Detailed Analysis -->
            ${generateSpellingGrammarDetails(spellingGrammarData)}
        </div>
    `;
}

function generateSpellingGrammarDetails(spellingGrammarData) {
    const spellingIssues = spellingGrammarData.spelling_analysis?.spelling_issues || [];
    const grammarIssues = spellingGrammarData.grammar_analysis?.grammar_issues || [];
    
    let detailsHTML = '';
    
    if (spellingIssues.length > 0 || grammarIssues.length > 0) {
        detailsHTML = `
            <div class="detailed-issues">
                <h5>Detailed Issues</h5>
                <div class="issues-tabs">
                    <button class="issue-tab active" data-tab="spelling">Spelling Issues (${spellingIssues.length})</button>
                    <button class="issue-tab" data-tab="grammar">Grammar Issues (${grammarIssues.length})</button>
                </div>
                
                <div class="issues-content">
                    <div class="issue-tab-content active" id="spelling-issues">
                        ${generateIssuesList(spellingIssues, 'spelling')}
                    </div>
                    <div class="issue-tab-content" id="grammar-issues">
                        ${generateIssuesList(grammarIssues, 'grammar')}
                    </div>
                </div>
            </div>
        `;
    }
    
    return detailsHTML;
}

function generateIssuesList(issues, type) {
    if (issues.length === 0) {
        return `<p class="no-issues">No ${type} issues found. Excellent work!</p>`;
    }
    
    return `
        <div class="issues-list">
            ${issues.map((issue, index) => `
                <div class="issue-item ${type}">
                    <div class="issue-header">
                        <span class="issue-number">${type === 'spelling' ? 'Spelling' : 'Grammar'} Issue ${index + 1}</span>
                        <span class="issue-severity">${getIssueSeverity(issue)}</span>
                    </div>
                    <div class="issue-context">
                        <strong>Context:</strong> ${issue.context || issue.sentence || 'No context available'}
                    </div>
                    <div class="issue-suggestion">
                        <strong>Suggestion:</strong> ${issue.replacements ? issue.replacements.join(', ') : 'No suggestion available'}
                    </div>
                    ${issue.message ? `<div class="issue-message">${issue.message}</div>` : ''}
                </div>
            `).join('')}
        </div>
    `;
}

function getIssueSeverity(issue) {
    // You can customize this based on your needs
    return 'Moderate';
}

function generateRecommendations(aiScore, completenessScore, citationScore) {
  const recommendations = []

  if (aiScore > 50) {
    recommendations.push("Consider revising sections with high AI probability for more original content")
  }

  if (completenessScore < 60) {
    recommendations.push("Add missing sections to improve chapter structure")
  }

  if (citationScore < 60) {
    recommendations.push("Review and correct APA formatting for citations")
  }

  if (recommendations.length === 0) {
    recommendations.push("Good overall quality. Continue with current approach.")
  }

  return recommendations.map(rec => `<div class="recommendation-item">• ${rec}</div>`).join('')
}

function viewComprehensiveReport(chapterNumber, version) {
    showMessage(`Loading comprehensive analysis for Chapter ${chapterNumber} v${version}...`, "info");

    const groupId = window.currentGroupId;

    if (!groupId) {
        showMessage("Error: Could not determine group ID", "error");
        return;
    }

    console.log("🔍 Fetching reports for:", { chapterNumber, version, groupId });

    // Fetch all reports including spelling & grammar
    const fetchPromises = [
        // AI Report
        fetch(`get_validation_report.php?chapter=${chapterNumber}&version=${version}&group=${groupId}`)
            .then(response => response.json())
            .catch(error => {
                console.error("AI report fetch error:", error);
                return { success: false, error: error.message };
            }),
        
        // Thesis Report
        fetch(`get_thesis_report.php?chapter=${chapterNumber}&version=${version}&group=${groupId}`)
            .then(response => response.json())
            .catch(error => {
                console.error("Thesis report fetch error:", error);
                return { success: false, error: error.message };
            }),

        // Citation Report (handle Chapter 5 vs non-Chapter 5)
        chapterNumber === 5 ? 
            fetch(`get_citation_report.php?chapter=${chapterNumber}&version=${version}&group=${groupId}&get_citation_report=true`)
                .then(response => response.json())
                .catch(error => {
                    console.error("Citation report fetch error:", error);
                    return { 
                        success: false, 
                        error: error.message,
                        message: "Citation analysis is only available for Chapter 5"
                    }
                })
        : Promise.resolve({ 
            success: false, 
            error: "Citation analysis is only available for Chapter 5.",
            message: "APA citation analysis is only performed on Chapter 5 (References/Bibliography)"
        }),

       // Formatting Report - Use the correct endpoint
fetch(`student_chap-upload.php?chapter=${chapterNumber}&version=${version}&group=${groupId}&get_formatting_report=true`)    
    .then(response => {
        // First check if response is HTML instead of JSON
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("Formatting report - Invalid JSON response:", text.substring(0, 200));
                return { 
                    success: false, 
                    error: 'Formatting analysis not available',
                    message: 'The formatting analysis data could not be loaded.'
                };
            }
        });
    })
    .catch(error => {
        console.error("Formatting report fetch error:", error);
        return { 
            success: false, 
            error: error.message,
            message: 'Failed to fetch formatting analysis'
        };
    }),

        // Spelling & Grammar Report
        fetch(`student_chap-upload.php?chapter=${chapterNumber}&version=${version}&group=${groupId}&get_spelling_grammar_report=true`)
            .then(response => response.json())
            .then(data => {
                console.log("Spelling & Grammar report:", data);
                return data;
            })
            .catch(error => {
                console.error("Spelling & Grammar report fetch error:", error);
                return { 
                    success: false, 
                    error: error.message,
                    message: "Spelling & Grammar analysis not available"
                };
            })
    ];

    Promise.all(fetchPromises)
    .then(([aiData, thesisData, citationData, formattingData, spellingGrammarData]) => {
        console.log("All reports loaded:", { 
            aiData, 
            thesisData, 
            citationData,
            formattingData, 
            spellingGrammarData 
        });
        
        if (aiData.success || thesisData.success || spellingGrammarData.success) {
            // CALL THE FUNCTION WITH CORRECT PARAMETER ORDER
            showCombinedReportModalWithSpellingGrammar(
                aiData, 
                thesisData, 
                citationData, 
                formattingData, 
                spellingGrammarData
            );
        } else {
            showMessage("Failed to load analysis: " + (aiData.error || "Unknown error"), "error");
        }
    })
    .catch((error) => {
        console.error("Error loading comprehensive report:", error);
        showMessage("Error loading comprehensive report: " + error.message, "error");
    });
}

// Update the comprehensive report to include formatting analysis
function showCombinedReportModalWithSpellingGrammar(aiData, thesisData, citationData, formattingData, spellingGrammarData) {
    const modal = document.createElement('div');
    modal.className = 'analysis-report-modal show';
    
    const chapterNumber = aiData.chapter_number || 'N/A';

    modal.innerHTML = `
        <div class="analysis-report-content">
            <div class="analysis-report-header">
                <h3 class="analysis-report-title">
                    <i class="fas fa-chart-bar"></i>
                    Comprehensive Analysis - Chapter ${chapterNumber} v${aiData.version || '1'}
                </h3>
                <button class="analysis-close-btn" onclick="this.closest('.analysis-report-modal').remove()">&times;</button>
            </div>
            <div class="analysis-report-body">
                <div class="comprehensive-tabs">
                    <div class="tab-headers">
                        <button class="tab-header active" data-tab="overview">Overview</button>
                        <button class="tab-header" data-tab="ai-analysis">AI Analysis</button>
                        <button class="tab-header" data-tab="thesis-analysis">Structure Analysis</button>
                        <button class="tab-header" data-tab="spelling-grammar">Spelling & Grammar</button>
                        <button class="tab-header" data-tab="citation-analysis">Citation Analysis</button>
                        <button class="tab-header" data-tab="formatting-analysis">Formatting Analysis</button>
                        <button class="tab-header" data-tab="document-view">Document View</button>
                    </div>
                    
                    <div class="tab-content active" id="overview-tab">
                        ${generateOverviewTab(aiData, thesisData, citationData, spellingGrammarData, formattingData)}
                    </div>
                    
                    <div class="tab-content" id="ai-analysis-tab">
                        ${generateAIAnalysisTab(aiData)}
                    </div>
                    
                    <div class="tab-content" id="thesis-analysis-tab">
                        ${generateThesisAnalysisTab(thesisData)}
                    </div>
                    
                    <div class="tab-content" id="spelling-grammar-tab">
                        ${generateSpellingGrammarTab(spellingGrammarData)}
                    </div>
                    
                    <div class="tab-content" id="citation-analysis-tab">
                        ${generateCitationAnalysisTab(citationData, chapterNumber)}
                    </div>

                    <div class="tab-content" id="formatting-analysis-tab">
                        ${generateFormattingAnalysisTab(formattingData)}
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
    modal.dataset.formattingData = JSON.stringify(formattingData);
    modal.dataset.spellingGrammarData = JSON.stringify(spellingGrammarData);

    // Add tab switching functionality
    modal.querySelectorAll('.tab-header').forEach(header => {
        header.addEventListener('click', () => {
            const tabName = header.getAttribute('data-tab');

            // Update active tab header
            modal.querySelectorAll('.tab-header').forEach(h => h.classList.remove('active'));
            header.classList.add('active');

            // Update active tab content
            modal.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            modal.querySelector(`#${tabName}-tab`).classList.add('active');
        });
    });
}

// Update the overview tab to include formatting score
function generateOverviewTab(aiData, thesisData, citationData, spellingGrammarData, formattingData) {
    const aiScore = aiData.overall_ai_percentage || 0;
    const completenessScore = thesisData.chapter_scores?.chapter_completeness_score || 0;
    const relevanceScore = thesisData.chapter_scores?.chapter_relevance_score || 0;
    const spellingScore = spellingGrammarData?.spelling_score || 0;
    const grammarScore = spellingGrammarData?.grammar_score || 0;
    const formattingScore = formattingData?.formatting_score || formattingData?.formatting_analysis?.overall_score || 0;
    
    // Citation score calculation (only for Chapter 5)
    let citationScore = 0;
    let citationsAnalyzed = 0;
    if (citationData.success && citationData.total_citations > 0) {
        citationScore = citationData.citation_score || Math.round((citationData.correct_citations / citationData.total_citations) * 100);
        citationsAnalyzed = citationData.total_citations;
    }

    return `
        <div class="overview-content">
            <div class="overview-scores">
                <div class="score-card ai-score">
                    <div class="score-value ${getAIScoreClass(aiScore)}">${aiScore}%</div>
                    <div class="score-label">AI Content Probability</div>
                    <div class="score-description">${getAIScoreDescription(aiScore)}</div>
                </div>
                <div class="score-card completeness-score">
                    <div class="score-value ${getCompletenessClass(completenessScore)}">${completenessScore}%</div>
                    <div class="score-label">Structure Completeness</div>
                    <div class="score-description">${getCompletenessDescription(completenessScore)}</div>
                </div>
                <div class="score-card formatting-score">
                    <div class="score-value ${getFormattingScoreClass(formattingScore)}">${formattingScore}%</div>
                    <div class="score-label">Formatting Compliance</div>
                    <div class="score-description">${getFormattingDescription(formattingScore)}</div>
                </div>
                <div class="score-card spelling-score">
                    <div class="score-value ${getSpellingClass(spellingScore)}">${spellingScore}%</div>
                    <div class="score-label">Spelling Accuracy</div>
                    <div class="score-description">${getSpellingDescription(spellingScore)}</div>
                </div>
                <div class="score-card grammar-score">
                    <div class="score-value ${getGrammarClass(grammarScore)}">${grammarScore}%</div>
                    <div class="score-label">Grammar Accuracy</div>
                    <div class="score-description">${getGrammarDescription(grammarScore)}</div>
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
                    ${generateRecommendations(aiScore, completenessScore, relevanceScore, citationScore, citationsAnalyzed, spellingScore, grammarScore, formattingScore)}
                </div>
            </div>
        </div>
    `;
}

// Add helper functions for formatting
function getFormattingDescription(score) {
    if (score >= 80) return 'Excellent formatting compliance';
    if (score >= 60) return 'Good formatting with minor issues';
    if (score >= 40) return 'Moderate formatting issues';
    return 'Significant formatting improvements needed';
}

function generateOverviewTab(aiData, thesisData, citationData) {
  const aiScore = aiData.overall_ai_percentage || 0
  const completenessScore = thesisData.chapter_scores?.chapter_completeness_score || 0
  const relevanceScore = thesisData.chapter_scores?.chapter_relevance_score || 0
  
  // Only calculate citation score for Chapter 5 if we have valid citation data
  let citationScore = 0
  let citationsAnalyzed = 0
  
  if (citationData.success && citationData.total_citations > 0) {
    citationScore = citationData.citation_score || Math.round((citationData.correct_citations / citationData.total_citations) * 100)
    citationsAnalyzed = citationData.total_citations
  } else {
    // For non-Chapter 5 or failed citation analysis
    citationScore = 0
    citationsAnalyzed = 0
  }

  return `
        <div class="overview-content">
            <div class="overview-scores">
                <div class="score-card ai-score">
                    <div class="score-value ${getAIScoreClass(aiScore)}">${aiScore}%</div>
                    <div class="score-label">AI Content Probability</div>
                    <div class="score-description">${getAIScoreDescription(aiScore)}</div>
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
                    ${generateRecommendations(aiScore, completenessScore, relevanceScore, citationScore, citationsAnalyzed)}
                </div>
            </div>
        </div>
    `
}


function generateAIAnalysisTab(aiData) {
  console.log("🎨 Generating AI Analysis Tab with data:", aiData)

  const aiScore = aiData.overall_ai_percentage || 0
  const totalAnalyzed = aiData.total_sentences_analyzed || 0
  const totalFlagged = aiData.sentences_flagged_as_ai || 0
  const analysisDetails = aiData.analysis || []
  const dataStatus = aiData.data_status || "complete"

  // Data status indicator
  const statusIndicator =
    dataStatus !== "complete"
      ? `
        <div class="data-status-indicator ${dataStatus}">
            <i class="fas fa-${getStatusIcon(dataStatus)}"></i>
            <span>${getStatusMessage(dataStatus)}</span>
        </div>
    `
      : ""

  return `
        <div class="ai-analysis-content">
            ${statusIndicator}
            
            <div class="ai-summary">
                <h4>AI Content Analysis</h4>
                <div class="ai-stats">
                    <div class="stat">
                        <span class="stat-label">Overall AI Probability:</span>
                        <span class="stat-value ${getAIScoreClass(aiScore)}">
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
                    <div class="meter-fill" style="width: ${Math.min(100, Math.max(0, aiScore))}%"></div>
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
                          section.text || section.content || section.sentence || "No content available"
                        const isAI =
                          section.is_ai !== undefined
                            ? section.is_ai
                            : section.ai_probability >= 50 || section.flag === "ai" || false
                        const aiProbability =
                          section.ai_probability !== undefined ? Math.round(section.ai_probability) : isAI ? 100 : 0
                        const sectionType = section.type || section.category || "paragraph"
                        const pageNumber = section.page || section.page_number || "N/A"

                        const displayText = sectionText
                          ? escapeHtml(truncateText(sectionText, 200))
                          : "No content available"

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
                        `
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
                            <span class="info-value ${getAIScoreClass(aiScore)}">${aiScore}%</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Analysis Level:</span>
                            <span class="info-value">Summary Only</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Content Assessment:</span>
                            <span class="info-value">${getAIScoreDescription(aiScore)}</span>
                        </div>
                    </div>
                </div>
                
                <div class="suggestions">
                    <h6>About This Analysis:</h6>
                    <ul>
                        <li>The overall AI probability score of <strong>${aiScore}%</strong> indicates ${getAIScoreDescription(aiScore).toLowerCase()}</li>
                        <li>For detailed analysis, ensure your PDF contains extractable text (not scanned images)</li>
                        <li>Try re-uploading the file or contact support if you need chunk-level analysis</li>
                    </ul>
                </div>
                
                ${
                  aiScore > 50
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
    `
}

function generateThesisAnalysisTab(thesisData) {
  console.log("📚 Generating Thesis Analysis Tab with data:", thesisData)

  if (!thesisData || !thesisData.chapter_scores) {
    return `
            <div class="no-thesis-data">
                <div class="no-data-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h5>Thesis Structure Analysis Not Available</h5>
                <p>Detailed chapter structure analysis data is not available for this document.</p>
            </div>
        `
  }

  const completenessScore = thesisData.chapter_scores.chapter_completeness_score || 0
  const relevanceScore = thesisData.chapter_scores.chapter_relevance_score || 0
  const presentSections = thesisData.chapter_scores.present_sections || 0
  const totalSections = thesisData.chapter_scores.total_sections || 0
  const missingSections = thesisData.chapter_scores.missing_sections || []

  const sections = thesisData.sections || {}

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
    `
}

function generateDocumentViewTab(aiData) {
  return `
        <div class="document-view-content">
            <div class="document-preview">
                <h4>Document Content Analysis</h4>
                ${generateDocumentPreview(aiData)}
            </div>
        </div>
    `
}

// Helper functions
function getStatusIcon(status) {
  const icons = {
    truncated: "exclamation-triangle",
    possibly_truncated: "exclamation-circle",
    corrupted: "times-circle",
    missing: "question-circle",
    repaired: "tools",
    complete: "check-circle",
  }
  return icons[status] || "info-circle"
}

function getStatusMessage(status) {
  const messages = {
    truncated: "Analysis data was truncated due to size limits",
    possibly_truncated: "Analysis data may be incomplete",
    corrupted: "Analysis data is corrupted",
    missing: "Analysis data not available",
    repaired: "Analysis data was repaired",
    complete: "Complete analysis data",
  }
  return messages[status] || "Analysis data status: " + status
}

function generateSectionsList(sections) {
  if (!sections || Object.keys(sections).length === 0) {
    return '<p class="no-sections">No section data available.</p>'
  }

  const sectionsArray = Object.entries(sections)

  return `
        <div class="sections-grid">
            ${sectionsArray
              .map(([sectionName, sectionData]) => {
                const isPresent = sectionData.present || false
                const relevance = sectionData.relevance_percent || 0

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
                `
              })
              .join("")}
        </div>
    `
}

function generateStructureRecommendations(completenessScore, relevanceScore, missingSections) {
  const recommendations = []

  if (completenessScore < 60) {
    recommendations.push("Add missing sections to improve chapter completeness")
  }

  if (relevanceScore < 60) {
    recommendations.push("Improve content relevance to the chapter topic")
  }

  if (missingSections.length > 0) {
    recommendations.push(
      `Focus on adding these sections: ${missingSections.slice(0, 3).join(", ")}${missingSections.length > 3 ? "..." : ""}`,
    )
  }

  if (completenessScore >= 80 && relevanceScore >= 80) {
    recommendations.push("Excellent chapter structure. Maintain current approach.")
  } else if (recommendations.length === 0) {
    recommendations.push("Good structure. Consider minor improvements for better organization.")
  }

  return recommendations.map((rec) => `<div class="recommendation-item">• ${rec}</div>`).join("")
}

function formatSectionName(sectionName) {
  return sectionName
    .split("_")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ")
}

function escapeHtml(text) {
  if (!text) return ""
  const div = document.createElement("div")
  div.textContent = text
  return div.innerHTML
}

function truncateText(text, maxLength) {
  if (text.length <= maxLength) return text
  return text.substring(0, maxLength) + "..."
}

function getAIScoreClass(score) {
  if (score >= 75) return "high-risk"
  if (score >= 50) return "medium-risk"
  return "low-risk"
}

function getCompletenessClass(score) {
  if (score >= 80) return "excellent"
  if (score >= 60) return "good"
  if (score >= 40) return "fair"
  return "poor"
}

function getRelevanceClass(score) {
  if (score >= 80) return "excellent"
  if (score >= 60) return "good"
  if (score >= 40) return "fair"
  return "poor"
}

function getAIScoreDescription(score) {
  if (score >= 75) return "High probability of AI-generated content"
  if (score >= 50) return "Moderate AI content detected"
  return "Low AI content probability"
}

function getCompletenessDescription(score) {
  if (score >= 80) return "Well-structured chapter"
  if (score >= 60) return "Adequate structure"
  if (score >= 40) return "Needs structural improvement"
  return "Poor structure - significant sections missing"
}

function getRelevanceDescription(score) {
  if (score >= 80) return "Highly relevant content"
  if (score >= 60) return "Mostly relevant content"
  if (score >= 40) return "Some relevance issues"
  return "Significant relevance problems"
}

function generateRecommendations(aiScore, completenessScore, relevanceScore, citationScore, citationsAnalyzed) {
  const recommendations = []

  if (aiScore > 50) {
    recommendations.push("Consider revising sections with high AI probability for more original content")
  }

  if (completenessScore < 60) {
    recommendations.push("Add missing sections to improve chapter structure")
  }

  if (relevanceScore < 60) {
    recommendations.push("Improve content relevance to the chapter topic")
  }

  if (citationsAnalyzed > 0 && citationScore < 60) {
    recommendations.push("Review and correct APA formatting for citations")
  } else if (citationsAnalyzed === 0) {
    recommendations.push("APA citation analysis is only available for Chapter 5")
  }

  if (recommendations.length === 0) {
    recommendations.push("Good overall quality. Continue with current approach.")
  }

  return recommendations.map(rec => `<div class="recommendation-item">• ${rec}</div>`).join('')
}


function generateDocumentPreview(reportData) {
  if (!reportData.analysis || reportData.analysis.length === 0) {
    return `
            <div class="no-content-message">
                <i class="fas fa-file-alt"></i>
                <h4>No structured content available for analysis</h4>
                <p>The document may be empty, contain only images, or the text extraction failed.</p>
            </div>
        `
  }

  let previewHTML = '<div class="document-content-preview">'
  let currentPage = 1

  reportData.analysis.slice(0, 15).forEach((section, index) => {
    const isAIContent = section.is_ai

    // Add page break indicator
    if (section.page && section.page !== currentPage) {
      previewHTML += `
                <div class="page-break-indicator">
                    <i class="fas fa-file"></i> Page ${section.page}
                </div>
            `
      currentPage = section.page
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
        `
  })

  previewHTML += "</div>"

  if (reportData.analysis.length > 15) {
    previewHTML += `<p class="more-content">+ ${reportData.analysis.length - 15} more content sections</p>`
  }

  return previewHTML
}

// Update the updateChapterUI function to include formatting analysis
function updateChapterUI(chapterNumber, data) {
    const fileInput = document.getElementById(`chapter${chapterNumber}`);
    const chapterCard = fileInput ? fileInput.closest('.chapter-card') : null;

    if (chapterCard) {
        const fileNameElement = chapterCard.querySelector('.file-name');
        if (fileNameElement) {
            fileNameElement.textContent = data.filename;
        }

        const statusElement = chapterCard.querySelector('.chapter-status');
        if (statusElement) {
            statusElement.textContent = 'Uploaded';
            statusElement.className = 'chapter-status status-badge uploaded';
        }

        updateAIValidation(chapterCard, data, chapterNumber);
        updateFormattingAnalysis(chapterCard, data, chapterNumber);
    }
}

function updateAIValidation(chapterCard, data, chapterNumber) {
  let validationSection = chapterCard.querySelector(".ai-validation")

  if (!validationSection) {
    validationSection = document.createElement("div")
    validationSection.className = "ai-validation"
    chapterCard.appendChild(validationSection)
  }

  // Check if we have valid AI data
  const hasAIData = data.ai_score !== null && data.ai_score !== undefined
  const hasAnalysisData = data.sentences_analyzed > 0
  // Only check citation data for Chapter 5
  const hasCitationData = chapterNumber === 5 && data.citation_score !== null && data.citation_score !== undefined

  let validationHTML = ""

  if (hasAIData && hasAnalysisData) {
    const aiScore = Number.parseFloat(data.ai_score)
    const scoreClass = aiScore >= 75 ? "high" : aiScore >= 50 ? "medium" : "low"
    const scoreText = aiScore >= 75 ? "High AI Content" : aiScore >= 50 ? "Moderate AI Content" : "Low AI Content"

    validationHTML = `
            <div class="validation-header">
                <i class="fas fa-robot"></i>
                <span>AI Content Analysis</span>
            </div>
            <div class="validation-score">
                <span class="score-label">AI Probability Score:</span>
                <span class="score-badge score-${scoreClass}" title="${scoreText}">${aiScore.toFixed(2)}%</span>
            </div>
            <div class="validation-details">
                <div class="detail-item">
                    <span class="detail-label">Text Chunks Analyzed:</span>
                    <span class="detail-value">${data.sentences_analyzed || 0}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">AI Chunks Flagged:</span>
                    <span class="detail-value">${data.sentences_flagged || 0}</span>
                </div>
            </div>
            <div class="validation-issues">
                <p>${data.ai_feedback || "Analysis completed"}</p>
            </div>
        `
  } else if (hasAIData && !hasAnalysisData) {
    // Has score but no analysis details (chapters 1-3 case)
    const aiScore = Number.parseFloat(data.ai_score)
    const scoreClass = aiScore >= 75 ? "high" : aiScore >= 50 ? "medium" : "low"

    validationHTML = `
            <div class="validation-header">
                <i class="fas fa-robot"></i>
                <span>AI Content Analysis</span>
            </div>
            <div class="validation-score">
                <span class="score-label">AI Probability Score:</span>
                <span class="score-badge score-${scoreClass}">${aiScore.toFixed(2)}%</span>
            </div>
            <div class="validation-issues">
                <p>${data.ai_feedback || "Basic analysis completed"}</p>
                <p class="warning-text">Detailed chunk analysis not available for this chapter</p>
            </div>
        `
  } else {
    validationHTML = `
            <div class="validation-header">
                <i class="fas fa-robot"></i>
                <span>AI Analysis</span>
            </div>
            <div class="validation-issues">
                <p>${data.ai_feedback || "AI analysis not available for this file type"}</p>
            </div>
        `
  }

  // Add Citation Analysis Section if available - ONLY FOR CHAPTER 5
  if (hasCitationData) {
    const citationScore = Number.parseFloat(data.citation_score)
    const citationClass = citationScore >= 80 ? "high" : citationScore >= 60 ? "medium" : "low"
    const totalCitations = data.total_citations || 0
    const correctCitations = data.correct_citations || 0

    validationHTML += `
            <div class="citation-analysis-section">
                <div class="validation-header citation-header">
                    <i class="fas fa-quote-right"></i>
                    <span>APA Citation Analysis</span>
                </div>
                
                <div class="citation-scores-row">
                    <div class="score-item">
                        <span class="score-label">Citation Score:</span>
                        <span class="score-badge score-${citationClass}">${citationScore.toFixed(2)}%</span>
                    </div>
                    <div class="score-item">
                        <span class="score-label">Citations Checked:</span>
                        <span class="score-badge score-neutral">${totalCitations}</span>
                    </div>
                </div>
                
                <div class="validation-details">
                    <div class="detail-item">
                        <span class="detail-label">Properly Formatted:</span>
                        <span class="detail-value">${correctCitations}/${totalCitations}</span>
                    </div>
                </div>
                
                ${data.citation_feedback ? `
                <div class="citation-feedback">
                    <p><strong>Citation Feedback:</strong> ${data.citation_feedback}</p>
                </div>
                ` : ''}
            </div>
        `
  } else if (chapterNumber === 5) {
    // Show citation analysis unavailable message only for Chapter 5
    validationHTML += `
            <div class="citation-analysis-section">
                <div class="validation-header citation-header">
                    <i class="fas fa-quote-right"></i>
                    <span>APA Citation Analysis</span>
                </div>
                <div class="validation-issues">
                    <p>${data.citation_feedback || "Citation analysis not available for this file type"}</p>
                </div>
            </div>
        `
  }

  // Thesis Analysis Section - ONLY if we have thesis data
  if (data.completeness_score !== null && data.completeness_score !== undefined) {
    const completenessScore = Number.parseFloat(data.completeness_score)
    const completenessClass = completenessScore >= 80 ? "high" : completenessScore >= 60 ? "medium" : "low"
    const completenessText =
      completenessScore >= 80
        ? "Well Structured"
        : completenessScore >= 60
          ? "Moderately Structured"
          : "Needs Improvement"

    const relevanceScore = Number.parseFloat(data.relevance_score || 0)
    const relevanceClass = relevanceScore >= 80 ? "high" : relevanceScore >= 60 ? "medium" : "low"

    validationHTML += `
            <div class="thesis-analysis-section">
                <div class="validation-header thesis-header">
                    <i class="fas fa-file-alt"></i>
                    <span>Chapter Structure Analysis</span>
                </div>
                
                <div class="thesis-scores-row">
                    <div class="score-item">
                        <span class="score-label">Completeness Score:</span>
                        <span class="score-badge score-${completenessClass}" title="${completenessText}">${completenessScore.toFixed(2)}%</span>
                    </div>
                    <div class="score-item">
                        <span class="score-label">Relevance Score:</span>
                        <span class="score-badge score-${relevanceClass}">${relevanceScore.toFixed(2)}%</span>
                    </div>
                </div>
                
                <div class="validation-details">
                    <div class="detail-item">
                        <span class="detail-label">Sections Found:</span>
                        <span class="detail-value">${data.thesis_sections_found || 0}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Missing Sections:</span>
                        <span class="detail-value">${data.thesis_missing_sections || 0}</span>
                    </div>
                </div>
                
                ${
                  data.completeness_feedback
                    ? `
                <div class="thesis-feedback">
                    <p><strong>Structure Feedback:</strong> ${data.completeness_feedback}</p>
                </div>
                `
                    : ""
                }
            </div>
        `
  }

  // Single button for combined comprehensive report
  validationHTML += `
        <div class="analysis-actions">
            <button class="btn-primary btn-small" onclick="viewComprehensiveReport(${data.chapter_number}, ${data.version})">
                <i class="fas fa-chart-bar"></i> View Full Analysis Report
            </button>
        </div>
    `

  validationSection.innerHTML = validationHTML
}

// Analysis filtering function
function filterAnalysis(type) {
  const sections = document.querySelectorAll(".ai-section")
  const filterBtns = document.querySelectorAll(".filter-btn")

  // Update active button
  filterBtns.forEach((btn) => btn.classList.remove("active"))
  event.target.classList.add("active")

  // Filter sections
  sections.forEach((section) => {
    if (type === "all") {
      section.style.display = ""
    } else {
      const sectionType = section.getAttribute("data-type")
      section.style.display = sectionType === type ? "" : "none"
    }
  })
}

// LEGACY FUNCTIONS (keep for backward compatibility)
function viewValidationReportBtn(chapterNum, version, groupId) {
  viewComprehensiveReport(chapterNum, version)
}

function viewValidationReport(chapterNumber, version, groupId) {
  viewComprehensiveReport(chapterNumber, version)
}

function viewThesisReport(chapterNumber, version) {
  viewComprehensiveReport(chapterNumber, version)
}

function viewAIReport(chapterNumber, version) {
  viewComprehensiveReport(chapterNumber, version)
}

// Export function (placeholder)
function exportCombinedReport(chapterNumber, version) {
  showMessage(`Preparing Excel export for Chapter ${chapterNumber}...`, "info")

  // Get the modal data
  const modal = document.querySelector(".analysis-report-modal")
  if (!modal) {
    showMessage("Error: Modal data not found", "error")
    return
  }

  const aiData = JSON.parse(modal.dataset.aiData || "{}")
  const thesisData = JSON.parse(modal.dataset.thesisData || "{}")

  // Prepare export data
  const exportData = {
    chapter_number: chapterNumber,
    version: version,
    ai_data: aiData,
    thesis_data: thesisData,
    export_timestamp: new Date().toISOString(),
  }

  // Create form and submit
  const form = document.createElement("form")
  form.method = "POST"
  form.action = "export_analysis_report.php"
  form.target = "_blank"

  const input = document.createElement("input")
  input.type = "hidden"
  input.name = "export_data"
  input.value = JSON.stringify(exportData)

  form.appendChild(input)
  document.body.appendChild(form)
  form.submit()
  document.body.removeChild(form)

  showMessage("Excel export started. Check your downloads.", "success")
}

// UI Initialization
function initUI() {
  // User dropdown functionality
  const userAvatar = document.getElementById("userAvatar")
  const userDropdown = document.getElementById("userDropdown")

  if (userAvatar && userDropdown) {
    userAvatar.addEventListener("click", (e) => {
      e.stopPropagation()
      userDropdown.style.display = userDropdown.style.display === "block" ? "none" : "block"
    })

    document.addEventListener("click", (e) => {
      if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
        userDropdown.style.display = "none"
      }
    })
  }

  // Logout functionality
  const logoutBtn = document.getElementById("logoutBtn")
  const logoutLink = document.getElementById("logoutLink")
  const logoutModal = document.getElementById("logoutModal")

  if (logoutModal) {
    const showModal = (e) => {
      if (e) e.preventDefault()
      logoutModal.classList.add("show")
    }

    const hideModal = () => {
      logoutModal.classList.remove("show")
    }

    if (logoutBtn) logoutBtn.addEventListener("click", showModal)
    if (logoutLink) logoutLink.addEventListener("click", showModal)

    logoutModal.addEventListener("click", (e) => {
      if (e.target === logoutModal) {
        hideModal()
      }
    })

    window.closeLogoutModal = hideModal
    window.confirmLogout = () => {
      window.location.href = "../logout.php"
    }
  }

  // Add event listeners for search and filter
  const searchInput = document.getElementById("searchHistory")
  const chapterFilter = document.getElementById("chapterFilter")

  if (searchInput) {
    searchInput.addEventListener("input", filterUploadHistory)
  }

  if (chapterFilter) {
    chapterFilter.addEventListener("change", filterUploadHistory)
  }
}

function initAnimations() {
  const animatedElements = document.querySelectorAll("[data-animate]")

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const element = entry.target
          const animation = element.dataset.animate
          const delay = element.dataset.delay || 0

          setTimeout(() => {
            element.classList.add("animate-" + animation)
          }, delay)

          observer.unobserve(element)
        }
      })
    },
    { threshold: 0.1 },
  )

  animatedElements.forEach((el) => observer.observe(el))
}

function initUploadChart() {
  const canvas = document.getElementById("uploadChart")
  if (!canvas) return

  const ctx = canvas.getContext("2d")
  const uploadData = JSON.parse(canvas.getAttribute("data-uploads") || "{}")

  const chartData = processUploadDataForChart(uploadData, "week")
  drawChart(ctx, chartData)

  document.querySelectorAll(".chart-btn").forEach((btn) => {
    btn.addEventListener("click", function () {
      document.querySelectorAll(".chart-btn").forEach((b) => b.classList.remove("active"))
      this.classList.add("active")

      const period = this.dataset.period
      const newData = processUploadDataForChart(uploadData, period)
      drawChart(ctx, newData)
    })
  })
}

function processUploadDataForChart(uploadData, period) {
  const data = []
  const labels = []

  if (period === "week") {
    for (let i = 6; i >= 0; i--) {
      const date = new Date()
      date.setDate(date.getDate() - i)
      const dateStr = date.toISOString().split("T")[0]
      labels.push(date.toLocaleDateString("en-US", { weekday: "short" }))

      let count = 0
      Object.values(uploadData).forEach((chapters) => {
        chapters.forEach((upload) => {
          const uploadDate = new Date(upload.upload_date).toISOString().split("T")[0]
          if (uploadDate === dateStr) count++
        })
      })
      data.push(count)
    }
  } else {
    for (let i = 3; i >= 0; i--) {
      const date = new Date()
      date.setDate(date.getDate() - i * 7)
      labels.push(`Week ${4 - i}`)

      let count = 0
      Object.values(uploadData).forEach((chapters) => {
        chapters.forEach((upload) => {
          const uploadDate = new Date(upload.upload_date)
          const weekStart = new Date(date)
          weekStart.setDate(weekStart.getDate() - 7)
          if (uploadDate >= weekStart && uploadDate < date) count++
        })
      })
      data.push(count)
    }
  }

  return { labels, data }
}

function drawChart(ctx, chartData) {
  const canvas = ctx.canvas
  const width = canvas.width
  const height = canvas.height

  ctx.clearRect(0, 0, width, height)

  const padding = 60
  const chartWidth = width - padding * 2
  const chartHeight = height - padding * 2

  const maxValue = Math.max(...chartData.data, 1)
  const barWidth = chartWidth / chartData.labels.length

  chartData.data.forEach((value, index) => {
    const barHeight = (value / maxValue) * chartHeight
    const x = padding + index * barWidth + barWidth * 0.2
    const y = height - padding - barHeight
    const width = barWidth * 0.6

    const gradient = ctx.createLinearGradient(0, y, 0, y + barHeight)
    gradient.addColorStop(0, "#667eea")
    gradient.addColorStop(1, "#764ba2")

    ctx.fillStyle = gradient
    ctx.fillRect(x, y, width, barHeight)

    ctx.fillStyle = "#4a5568"
    ctx.font = "12px Inter"
    ctx.textAlign = "center"
    ctx.fillText(value, x + width / 2, y - 5)

    ctx.fillText(chartData.labels[index], x + width / 2, height - padding + 20)
  })

  ctx.strokeStyle = "#e2e8f0"
  ctx.lineWidth = 1
  ctx.beginPath()
  ctx.moveTo(padding, height - padding)
  ctx.lineTo(width - padding, height - padding)
  ctx.moveTo(padding, padding)
  ctx.lineTo(padding, height - padding)
  ctx.stroke()
}

function exportReport() {
  const data = []
  const rows = document.querySelectorAll("#uploadTableBody tr")

  data.push(["File Name", "Chapter", "Version", "Upload Date", "Upload Time"])

  rows.forEach((row) => {
    if (row.style.display !== "none") {
      const cells = row.querySelectorAll("td")
      const fileName = cells[0].querySelector(".file-name").textContent.trim()
      const chapter = cells[1].querySelector(".chapter-badge").textContent.trim()
      const version = cells[2].querySelector(".version-badge").textContent.trim()
      const dateText = cells[3].querySelector(".date").textContent.trim()
      const timeText = cells[3].querySelector(".time").textContent.trim()

      data.push([fileName, chapter, version, dateText, timeText])
    }
  })

  const csvContent = data.map((row) => row.map((cell) => `"${cell}"`).join(",")).join("\n")

  const blob = new Blob([csvContent], { type: "text/csv" })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement("a")
  a.href = url
  a.download = `upload-analytics-${new Date().toISOString().split("T")[0]}.csv`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  window.URL.revokeObjectURL(url)
}

function showMessage(text, type = "info") {
  document.querySelectorAll(".message").forEach((msg) => msg.remove())

  const message = document.createElement("div")
  message.className = `message message-${type}`

  const icon =
    type === "success"
      ? "check-circle"
      : type === "error"
        ? "exclamation-circle"
        : type === "warning"
          ? "exclamation-triangle"
          : "info-circle"

  message.innerHTML = `
        <div class="message-content">
            <i class="fas fa-${icon}"></i>
            <span>${text}</span>
        </div>
        <button class="message-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `

  const mainContent = document.querySelector(".main-content")
  if (mainContent) {
    mainContent.insertAdjacentElement("afterbegin", message)
  }

  setTimeout(() => {
    if (message.parentNode) {
      message.remove()
    }
  }, 5000)
}

// Delete functionality
let currentDeleteChapter = null
let currentDeleteVersion = null
let currentDeleteGroupId = null

function showDeleteConfirmation(chapterNumber, version, groupId, fileName) {
  currentDeleteChapter = chapterNumber
  currentDeleteVersion = version
  currentDeleteGroupId = groupId

  document.getElementById("deleteFileName").textContent = fileName
  document.getElementById("deleteChapterInfo").textContent = "Chapter " + chapterNumber
  document.getElementById("deleteVersionInfo").textContent = "v" + version

  document.getElementById("deleteConfirmationModal").classList.add("show")
}

function closeDeleteModal() {
  document.getElementById("deleteConfirmationModal").classList.remove("show")
  currentDeleteChapter = null
  currentDeleteVersion = null
  currentDeleteGroupId = null
}

function confirmDelete() {
  if (!currentDeleteChapter || !currentDeleteVersion || !currentDeleteGroupId) {
    showMessage("Error: Missing deletion parameters", "error")
    return
  }

  const deleteBtn = document.querySelector(".btn-confirm-delete")
  const originalText = deleteBtn.innerHTML
  deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...'
  deleteBtn.disabled = true

  fetch(window.location.href, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({
      action: "delete_upload",
      chapter_number: currentDeleteChapter,
      version: currentDeleteVersion,
      group_id: currentDeleteGroupId,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showMessage("File deleted successfully!", "success")
        setTimeout(() => {
          window.location.reload()
        }, 1500)
      } else {
        throw new Error(data.error || "Failed to delete file")
      }
    })
    .catch((error) => {
      console.error("Delete error:", error)
      showMessage("Error deleting file: " + error.message, "error")
    })
    .finally(() => {
      deleteBtn.innerHTML = originalText
      deleteBtn.disabled = false
      closeDeleteModal()
    })
}

function filterUploadHistory() {
  const chapterFilter = document.getElementById("chapterFilter")?.value || "all"
  const searchFilter = document.getElementById("searchHistory")?.value.toLowerCase().trim() || ""
  const tableRows = document.querySelectorAll(".upload-row")
  const noResultsMessage = document.getElementById("noResultsMessage")
  let visibleCount = 0

  tableRows.forEach((row) => {
    const chapterNum = row.getAttribute("data-chapter")
    const filename = row.getAttribute("data-filename")

    const chapterMatch = chapterFilter === "all" || chapterFilter === chapterNum
    const searchMatch = searchFilter === "" || filename.includes(searchFilter)

    if (chapterMatch && searchMatch) {
      row.style.display = ""
      visibleCount++
    } else {
      row.style.display = "none"
    }
  })

  if (visibleCount === 0 && tableRows.length > 0) {
    noResultsMessage.style.display = "block"
  } else {
    noResultsMessage.style.display = "none"
  }
}

function triggerFileUpload(chapterId) {
  const fileInput = document.getElementById(chapterId)
  if (fileInput) {
    fileInput.click()
  }
}

function closeLogoutModal() {
  document.getElementById("logoutModal").classList.remove("show")
}

function confirmLogout() {
  window.location.href = "../logout.php"
}

// Global functions
window.showDeleteConfirmation = showDeleteConfirmation
window.closeDeleteModal = closeDeleteModal
window.confirmDelete = confirmDelete
window.closeLogoutModal = closeLogoutModal
window.confirmLogout = confirmLogout
window.exportReport = exportReport
window.filterUploadHistory = filterUploadHistory
window.triggerFileUpload = triggerFileUpload
window.viewComprehensiveReport = viewComprehensiveReport
window.viewValidationReportBtn = viewValidationReportBtn
window.viewValidationReport = viewValidationReport
window.viewThesisReport = viewThesisReport
window.viewAIReport = viewAIReport
window.exportCombinedReport = exportCombinedReport
window.filterAnalysis = filterAnalysis

// Prevent default drag and drop
window.addEventListener("dragover", (e) => {
  e.preventDefault()
})

window.addEventListener("drop", (e) => {
  e.preventDefault()
})

// Modal closing functionality
document.addEventListener("click", (e) => {
  if (e.target.classList.contains("modal")) {
    e.target.classList.remove("show")
  }
  if (e.target.classList.contains("analysis-report-modal")) {
    e.target.remove()
  }
})

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    document.querySelectorAll(".modal.show").forEach((modal) => {
      modal.classList.remove("show")
    })
    document.querySelectorAll(".analysis-report-modal.show").forEach((modal) => {
      modal.remove()
    })
  }
})

// ===============  Start of version 11 update =============== 
// =============== Notification functionality ===============
document.addEventListener('DOMContentLoaded', function() {
    initializeNotificationSystem();
});

function initializeNotificationSystem() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationMenu = document.getElementById('notificationMenu');
    const markAllReadBtn = document.getElementById('markAllRead');
    const notificationList = document.getElementById('notificationList');

    // Toggle notification dropdown
    if (notificationBtn && notificationMenu) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationMenu.classList.toggle('show');
            
            // Mark as read when opening (optional)
            if (notificationMenu.classList.contains('show')) {
                // You can auto-mark as read when opening, or leave it manual
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

    // Mark individual notification as read when clicked
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
    fetch('student_feedback.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_as_read&notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            element.classList.remove('unread');
            updateNotificationBadge();
        }
    })
    .catch(error => console.error('Error marking notification as read:', error));
}

function markAllNotificationsAsRead() {
    console.log('Marking all notifications as read...');
    
    fetch('student_chap-upload.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_read'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        console.log('Mark all read response:', data);
        if (data.success) {
            // Remove unread class from all notifications
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            
            // Hide the mark all read button
            const markAllReadBtn = document.getElementById('markAllRead');
            if (markAllReadBtn) {
                markAllReadBtn.style.display = 'none';
            }
            
            // Remove notification badge completely
            const badge = document.getElementById('notificationBadge');
            if (badge) {
                badge.remove();
            }
            
            // Show success message
            showFlashMessage('All notifications marked as read', 'success');
        } else {
            throw new Error(data.message || 'Failed to mark all as read');
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
        showFlashMessage('Error marking notifications as read: ' + error.message, 'error');
    });
}

function refreshNotifications() {
    fetch('student_chap-upload.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_notifications'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNotificationDisplay(data.notifications, data.unread_count);
        }
    })
    .catch(error => console.error('Error refreshing notifications:', error));
}

function updateNotificationDisplay(notifications, unreadCount) {
    // Update notification badge
    updateNotificationBadgeCount(unreadCount);
    
    // You can implement full notification list update here if needed
    // For now, we'll just update the badge count
}

function updateNotificationBadgeCount(unreadCount) {
    const badge = document.getElementById('notificationBadge');
    const notificationBtn = document.getElementById('notificationBtn');
    
    if (unreadCount > 0) {
        if (!badge) {
            const newBadge = document.createElement('span');
            newBadge.className = 'notification-badge';
            newBadge.id = 'notificationBadge';
            newBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            notificationBtn.appendChild(newBadge);
        } else {
            badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
        }
        
        // Show mark all read button if there are unread notifications
        const markAllReadBtn = document.getElementById('markAllRead');
        if (markAllReadBtn) {
            markAllReadBtn.style.display = 'block';
        }
    } else {
        // Remove badge if no unread notifications
        if (badge) {
            badge.remove();
        }
        
        // Hide mark all read button
        const markAllReadBtn = document.getElementById('markAllRead');
        if (markAllReadBtn) {
            markAllReadBtn.style.display = 'none';
        }
    }
}

function updateNotificationBadge() {
    // Count remaining unread notifications in the DOM
    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
    updateNotificationBadgeCount(unreadCount);
}


       
// ===============  End of version 11 update =============== 

function downloadTemplateAsPDF(chapterNumber) {
    // Create a form to submit the request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../Coordinator/get_chapter_format.php'; // Updated path
    form.style.display = 'none';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'chapter';
    input.value = chapterNumber;
    
    form.appendChild(input);
    document.body.appendChild(form);
    
    // Get the format configuration
    fetch('../Coordinator/get_chapter_format.php', { // Updated path
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `chapter=${chapterNumber}`
    })
    .then(response => response.json())
    .then(config => {
        const params = new URLSearchParams({
            chapter: chapterNumber,
            download: 'true',
            fontFamily: config.fontFamily || 'Times New Roman, serif',
            fontSize: config.fontSize || 12,
            sectionFontSize: config.fontSize || 12,
            textAlign: config.alignment || 'justify',
            lineSpacing: config.lineSpacing || 1.6,
            marginTop: config.marginTop || 1.0,
            marginBottom: config.marginBottom || 1.0,
            marginLeft: config.marginLeft || 1.5,
            marginRight: config.marginRight || 1.0,
            indent: config.indentation || 0.5,
            borderEnabled: config.borderEnabled || false,
            logoPosition: config.logoPosition || 'none',
            enabledSections: JSON.stringify(config.enabledSections || [])
        });
        
        window.open(`../Coordinator/chapter-preview.php?${params.toString()}`, '_blank'); // Updated path
    })
    .catch(error => {
        console.error('Error loading format:', error);
        // Fallback to default format
        const params = new URLSearchParams({
            chapter: chapterNumber,
            download: 'true'
        });
        window.open(`../Coordinator/chapter-preview.php?${params.toString()}`, '_blank'); // Updated path
    })
    .finally(() => {
        document.body.removeChild(form);
    });
}

function previewChapterTemplate(chapterNumber) {
    // Get the current format configuration for this chapter
    fetch('../Coordinator/get_chapter_format.php', { // Updated path
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `chapter=${chapterNumber}`
    })
    .then(response => response.json())
    .then(config => {
        if (config.success) {
            const params = new URLSearchParams({
                chapter: chapterNumber,
                fontFamily: config.fontFamily,
                fontSize: config.fontSize,
                sectionFontSize: config.fontSize,
                textAlign: config.alignment,
                lineSpacing: config.lineSpacing,
                marginTop: config.marginTop,
                marginBottom: config.marginBottom,
                marginLeft: config.marginLeft,
                marginRight: config.marginRight,
                indent: config.indentation,
                borderEnabled: config.borderEnabled,
                logoPosition: config.logoPosition,
                enabledSections: JSON.stringify(config.enabledSections)
            });
            
            window.open(`../Coordinator/chapter-preview.php?${params.toString()}`, '_blank'); // Updated path
        } else {
            // Fallback to default preview
            window.open(`../Coordinator/chapter-preview.php?chapter=${chapterNumber}`, '_blank'); // Updated path
        }
    })
    .catch(error => {
        console.error('Error loading format:', error);
        // Fallback to default preview
        window.open(`../Coordinator/chapter-preview.php?chapter=${chapterNumber}`, '_blank'); // Updated path
    });
}


function generateFormattingAnalysisTab(formattingData) {
    console.log('📋 Raw formatting data from DB:', formattingData);
    
    // Check if we have valid formatting data - handle both direct and nested structures
    let analysisData = formattingData;
    
    // If data is nested under formatting_analysis, extract it
    if (formattingData && formattingData.formatting_analysis) {
        analysisData = formattingData.formatting_analysis;
    }
    
    if (!analysisData || !analysisData.formatting_compliance) {
        return `
            <div class="no-formatting-data">
                <div class="no-data-icon">
                    <i class="fas fa-ruler-combined"></i>
                </div>
                <h5>Formatting Analysis Not Available</h5>
                <p>Formatting analysis data is not available for this document.</p>
            </div>
        `;
    }

    console.log('📊 Processing formatting analysis data:', analysisData);
    
    const overallScore = analysisData.overall_score || 0;
    const recommendations = analysisData.recommendations || [];
    const compliance = analysisData.formatting_compliance || {};
    const documentType = analysisData.document_type || 'PDF';

    // Calculate stats from the actual data
    const margins = compliance.margins || [];
    const totalPages = margins.length;
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

    return `
        <div class="formatting-analysis-content">
            <!-- Header Section -->
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

            <!-- Quick Stats Overview -->
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

            <!-- Overall Score Visualization -->
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

            <!-- Detailed Analysis Sections -->
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

// Helper functions
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
// Updated helper function for compliance sections
function generateComplianceSections(compliance) {
    let html = '';
    
    // Margins - Handle as array
    if (compliance.margins && Array.isArray(compliance.margins)) {
        const compliantMargins = compliance.margins.filter(m => m.compliance === 'compliant').length;
        const totalMargins = compliance.margins.length;
        const marginStatus = compliantMargins === totalMargins ? 'compliant' : 'non-compliant';
        
        html += `
            <div class="formatting-section">
                <h5>
                    <i class="fas fa-arrows-alt"></i>
                    Margins Analysis
                </h5>
                <div class="compliance-status">
                    <span class="status-label">Status:</span>
                    <span class="status-value ${marginStatus}">
                        ${marginStatus === 'compliant' ? '✓ Compliant' : '⚠ Needs Attention'} (${compliantMargins}/${totalMargins} pages)
                    </span>
                </div>
                <div class="margins-summary">
                    <div class="margins-grid">
                        ${compliance.margins.slice(0, 3).map(margin => `
                            <div class="margin-item">
                                <div class="margin-header">
                                    <span class="page-label">Page ${margin.page}</span>
                                    <span class="margin-status ${margin.compliance}">
                                        ${margin.compliance === 'compliant' ? '✓' : '⚠'}
                                    </span>
                                </div>
                                ${margin.detected_margins ? `
                                <div class="margin-values">
                                    <div class="margin-detail">
                                        <span class="margin-label">Top:</span>
                                        <span class="margin-value">${margin.detected_margins.top}"</span>
                                    </div>
                                    <div class="margin-detail">
                                        <span class="margin-label">Left:</span>
                                        <span class="margin-value">${margin.detected_margins.left}"</span>
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        `).join('')}
                    </div>
                    ${compliance.margins.length > 3 ? `
                    <div class="more-pages">
                        + ${compliance.margins.length - 3} more pages with similar margins
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    }
    
    // Font Style - Handle the actual structure
    if (compliance.font_style) {
        const fontStyle = compliance.font_style;
        const overallStatus = fontStyle.overall_font_usage?.primary_font_compliance?.status || 'non_compliant';
        const compliantStatus = overallStatus === 'compliant' ? 'compliant' : 'non-compliant';
        
        html += `
            <div class="formatting-section">
                <h5>
                    <i class="fas fa-font"></i>
                    Font Style Analysis
                </h5>
                <div class="compliance-status">
                    <span class="status-label">Status:</span>
                    <span class="status-value ${compliantStatus}">
                        ${compliantStatus === 'compliant' ? '✓ Compliant' : '⚠ Needs Attention'}
                    </span>
                </div>
                
                ${fontStyle.overall_font_usage ? `
                <div class="font-overview">
                    <div class="font-summary">
                        <div class="summary-item">
                            <strong>Primary Font:</strong> 
                            <span class="font-value ${fontStyle.overall_font_usage.primary_font_compliance?.status === 'compliant' ? 'compliant' : 'non-compliant'}">
                                ${fontStyle.overall_font_usage.primary_font_compliance?.detected || fontStyle.overall_font_usage.primary_font_compliance?.primary_font || 'Not detected'}
                            </span>
                        </div>
                        <div class="summary-item">
                            <strong>Font Consistency:</strong> 
                            <span class="consistency-value ${fontStyle.overall_font_usage.font_consistency === 'good' ? 'good' : 'poor'}">
                                ${fontStyle.overall_font_usage.font_consistency ? formatFontConsistency(fontStyle.overall_font_usage.font_consistency) : 'Unknown'}
                            </span>
                        </div>
                    </div>
                    
                    ${fontStyle.overall_font_usage.fonts_detected && fontStyle.overall_font_usage.fonts_detected.length > 0 ? `
                    <div class="fonts-detected">
                        <strong>Fonts Detected:</strong>
                        <div class="fonts-list">
                            ${fontStyle.overall_font_usage.fonts_detected.map(font => `
                                <span class="font-tag ${font === 'Times New Roman' ? 'primary-font' : 'secondary-font'}">${font}</span>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>
                ` : ''}
                
                ${fontStyle.page_analysis && fontStyle.page_analysis.length > 0 ? `
                <div class="page-font-analysis">
                    <h6>Page Font Compliance</h6>
                    <div class="page-fonts-grid">
                        ${fontStyle.page_analysis.map(page => {
                            const pageStatus = page.primary_font_compliance?.status || 'non_compliant';
                            return `
                            <div class="page-font-card">
                                <div class="page-header">
                                    <span>Page ${page.page}</span>
                                    <span class="font-status ${pageStatus === 'compliant' ? 'compliant' : 'non-compliant'}">
                                        ${pageStatus === 'compliant' ? '✓ Compliant' : '⚠ Check'}
                                    </span>
                                </div>
                                ${page.fonts_detected && page.fonts_detected.length > 0 ? `
                                <div class="page-fonts">
                                    ${page.fonts_detected.slice(0, 2).map(font => `
                                        <span class="font-item ${font === 'Times New Roman' ? 'primary' : 'secondary'}">${font}</span>
                                    `).join('')}
                                    ${page.fonts_detected.length > 2 ? `<span class="more-fonts">+${page.fonts_detected.length - 2}</span>` : ''}
                                </div>
                                ` : ''}
                            </div>
                        `}).join('')}
                    </div>
                </div>
                ` : ''}
            </div>
        `;
    }
    
    // Font Size - Handle the actual structure
    if (compliance.font_size) {
        const fontSize = compliance.font_size;
        const compliantStatus = fontSize.compliance === 'compliant' ? 'compliant' : 'non-compliant';
        
        html += `
            <div class="formatting-section">
                <h5>
                    <i class="fas fa-text-height"></i>
                    Font Size Analysis
                </h5>
                <div class="compliance-status">
                    <span class="status-label">Status:</span>
                    <span class="status-value ${compliantStatus}">
                        ${compliantStatus === 'compliant' ? '✓ Compliant' : '⚠ Needs Attention'}
                    </span>
                </div>
                
                <div class="font-size-overview">
                    <div class="size-stats">
                        <div class="stat-item">
                            <strong>Primary Size:</strong> ${fontSize.primary_size || 'N/A'}pt
                        </div>
                        <div class="stat-item">
                            <strong>Compliance Rate:</strong> ${fontSize.compliance_rate || 0}%
                        </div>
                    </div>
                    
                    ${fontSize.detected_sizes && fontSize.detected_sizes.length > 0 ? `
                    <div class="size-distribution">
                        <strong>Font Sizes Detected:</strong>
                        <div class="sizes-list">
                            ${Array.from(new Set(fontSize.detected_sizes)).sort((a, b) => a - b).map(size => {
                                const count = fontSize.detected_sizes.filter(s => s === size).length;
                                return `
                                <span class="size-tag ${size === 12 ? 'standard-size' : 'non-standard-size'}">
                                    ${size}pt
                                    <span class="size-count">(${count})</span>
                                </span>
                            `}).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    }
    
    // Spacing - Handle as array
    if (compliance.spacing && Array.isArray(compliance.spacing)) {
        const estimatedPages = compliance.spacing.filter(s => s.compliance === 'estimated_check_required').length;
        const totalSpacing = compliance.spacing.length;
        
        html += `
            <div class="formatting-section">
                <h5>
                    <i class="fas fa-arrows-alt-v"></i>
                    Spacing Analysis
                </h5>
                <div class="compliance-status">
                    <span class="status-label">Status:</span>
                    <span class="status-value ${estimatedPages === 0 ? 'compliant' : 'non-compliant'}">
                        ${estimatedPages === 0 ? '✓ Compliant' : '⚠ Manual Check Required'} (${estimatedPages}/${totalSpacing} pages need verification)
                    </span>
                </div>
                
                <div class="spacing-overview">
                    <div class="spacing-stats">
                        ${compliance.spacing.slice(0, 3).map(spacing => `
                            <div class="spacing-item">
                                <span class="page-label">Page ${spacing.page}:</span>
                                <span class="spacing-value">${spacing.line_spacing_estimate || 'Unknown'} spacing</span>
                            </div>
                        `).join('')}
                    </div>
                    ${compliance.spacing.length > 3 ? `
                    <div class="more-pages">
                        + ${compliance.spacing.length - 3} more pages with similar spacing
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    }
    
    // Page Layout - Handle as array
    if (compliance.page_layout && Array.isArray(compliance.page_layout)) {
        const pagesWithHeaders = compliance.page_layout.filter(p => p.header_detected).length;
        const pagesWithNumbers = compliance.page_layout.filter(p => p.page_number_detected).length;
        const pagesWithTitles = compliance.page_layout.filter(p => p.chapter_title_detected).length;
        
        html += `
            <div class="formatting-section">
                <h5>
                    <i class="fas fa-layer-group"></i>
                    Page Layout Analysis
                </h5>
                <div class="layout-overview">
                    <div class="layout-stats">
                        <div class="layout-stat">
                            <span class="stat-value">${pagesWithHeaders}/${compliance.page_layout.length}</span>
                            <span class="stat-label">Pages with Headers</span>
                        </div>
                        <div class="layout-stat">
                            <span class="stat-value">${pagesWithNumbers}/${compliance.page_layout.length}</span>
                            <span class="stat-label">Pages with Numbers</span>
                        </div>
                        <div class="layout-stat">
                            <span class="stat-value">${pagesWithTitles}/${compliance.page_layout.length}</span>
                            <span class="stat-label">Pages with Titles</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Three Lines Requirement - Handle as array
    if (compliance.three_lines_right && Array.isArray(compliance.three_lines_right)) {
        const pagesWithThreeLines = compliance.three_lines_right.filter(t => t.three_lines_detected).length;
        const totalThreeLines = compliance.three_lines_right.length;
        
        html += `
            <div class="formatting-section">
                <h5>
                    <i class="fas fa-grip-lines"></i>
                    Three Lines Requirement
                </h5>
                <div class="compliance-status">
                    <span class="status-label">Status:</span>
                    <span class="status-value ${pagesWithThreeLines === totalThreeLines ? 'compliant' : 'non-compliant'}">
                        ${pagesWithThreeLines === totalThreeLines ? '✓ Compliant' : '⚠ Needs Attention'} (${pagesWithThreeLines}/${totalThreeLines} pages)
                    </span>
                </div>
            </div>
        `;
    }
    
    return html;
}

// Helper function to format font consistency
function formatFontConsistency(consistency) {
    const mappings = {
        'too_many_fonts': 'Too Many Fonts',
        'good': 'Good',
        'excellent': 'Excellent',
        'poor': 'Poor'
    };
    return mappings[consistency] || consistency;
}

// Keep the other helper functions the same (generatePageAnalysisSection, generateRecommendationsSection)
function generatePageAnalysisSection(pageAnalysis) {
    return `
        <div class="formatting-section">
            <h5>
                <i class="fas fa-file-alt"></i>
                Page-by-Page Analysis
            </h5>
            <div class="pages-grid">
                ${pageAnalysis.slice(0, 10).map(page => `
                    <div class="page-card">
                        <div class="page-header">
                            <span class="page-number">Page ${page.page || 'N/A'}</span>
                            <span class="page-score ${getFormattingScoreClass(page.overall_score || 0)}">
                                ${page.overall_score || 0}%
                            </span>
                        </div>
                        <div class="page-compliance ${page.compliance_status || 'unknown'}">
                            ${page.compliance_status === 'compliant' ? '✓ Compliant' : 
                              page.compliance_status === 'minor_issues' ? '⚠ Minor Issues' :
                              page.compliance_status === 'needs_attention' ? '🔧 Needs Attention' : '❌ Check Required'}
                        </div>
                        ${page.main_issues && page.main_issues.length > 0 ? `
                        <div class="page-issues">
                            <strong>Issues:</strong>
                            <ul class="issues-list">
                                ${page.main_issues.slice(0, 3).map(issue => `
                                    <li>${issue}</li>
                                `).join('')}
                                ${page.main_issues.length > 3 ? `<li>+${page.main_issues.length - 3} more issues</li>` : ''}
                            </ul>
                        </div>
                        ` : ''}
                    </div>
                `).join('')}
            </div>
            ${pageAnalysis.length > 10 ? `
            <div class="pages-footer">
                <p>+ ${pageAnalysis.length - 10} more pages analyzed</p>
            </div>
            ` : ''}
        </div>
    `;
}

function generateRecommendationsSection(recommendations) {
    return `
        <div class="formatting-recommendations">
            <h5>
                <i class="fas fa-lightbulb"></i>
                Formatting Recommendations
            </h5>
            <div class="recommendations-list">
                ${recommendations.map((rec, index) => `
                    <div class="recommendation-item">
                        <span class="rec-number">${index + 1}.</span>
                        <span class="rec-text">${rec}</span>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

// Helper function for formatting scores
function getFormattingScoreClass(score) {
    if (score >= 85) return 'excellent';
    if (score >= 70) return 'good';
    if (score >= 60) return 'fair';
    return 'poor';
}



function showPageDetails(pageNumber) {
    console.log('Showing details for page:', pageNumber);
    showMessage(`Detailed analysis for Page ${pageNumber} would be shown here. This feature can display specific formatting issues, margin measurements, and compliance status for this page.`, 'info');
}

function showAllPages() {
    const pages = window.currentFormattingData?.page_by_page_analysis || [];
    showMessage(`Viewing all ${pages.length} pages in console. Implement modal for full view.`, 'info');
    console.log('All pages analysis:', pages);
}

// Store formatting data globally for pagination
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

// Add this function to handle formatting analysis display
function updateFormattingAnalysis(chapterCard, data, chapterNumber) {
    let formattingSection = chapterCard.querySelector('.formatting-validation');
    
    if (!formattingSection) {
        formattingSection = document.createElement('div');
        formattingSection.className = 'formatting-validation';
        chapterCard.appendChild(formattingSection);
    }

    // Check if we have formatting data
    const hasFormattingData = data.formatting_score !== null && data.formatting_score !== undefined;
    const hasFormattingAnalysis = data.has_formatting_analysis;

    let formattingHTML = '';

    if (hasFormattingData && hasFormattingAnalysis) {
        const formattingScore = Number.parseFloat(data.formatting_score);
        const scoreClass = formattingScore >= 80 ? 'high' : formattingScore >= 60 ? 'medium' : 'low';
        const scoreText = formattingScore >= 80 ? 'Excellent Formatting' : 
                         formattingScore >= 60 ? 'Good Formatting' : 'Needs Improvement';

        formattingHTML = `
            <div class="validation-header">
                <i class="fas fa-ruler-combined"></i>
                <span>Formatting Analysis</span>
            </div>
            <div class="validation-score">
                <span class="score-label">Formatting Compliance:</span>
                <span class="score-badge score-${scoreClass}" title="${scoreText}">${formattingScore.toFixed(2)}%</span>
            </div>
            <div class="validation-issues">
                <p>${data.formatting_feedback || "Formatting analysis completed"}</p>
            </div>
        `;
    } else {
        formattingHTML = `
            <div class="validation-header">
                <i class="fas fa-ruler-combined"></i>
                <span>Formatting Analysis</span>
            </div>
            <div class="validation-issues">
                <p>${data.formatting_feedback || "Formatting analysis not available for this file type"}</p>
            </div>
        `;
    }

    formattingSection.innerHTML = formattingHTML;
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
