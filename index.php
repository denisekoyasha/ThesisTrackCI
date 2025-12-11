<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ThesisTrack</title>
  <link rel="icon" type="image/x-icon" href="images/book-icon.ico">
  <link rel="stylesheet" href="CSS/index.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body>
  <header class="navbar">
    <div class="logo">
      <img src="images/book-icon.png" alt="Logo" />
      <div class="logo-text">
        <strong>ThesisTrack</strong><br>
        <span>CICT Research Platform</span>
      </div>
    </div>
    <div class="nav-buttons">
      <a href="portal.php">
        <button class="btn-outline">Sign In</button>
      </a>
    </div>
  </header>

  <main class="hero">
    <div class="hero-content">
      <div class="tagline">Algorithm-Driven Research Platform</div>
      <h1>Efficient Monitoring of <br><span class="highlight">Student Research Progress</span></h1>
      <p class="description">
        ThesisTrack revolutionizes academic research management with AI-powered analysis, real-time collaboration, and comprehensive progress tracking for the College of Information and Communications Technology.
      </p>
      <div class="actions">
        <a href="portal.php">
          <button class="btn-solid">Start Your Research Journey</button>
        </a>
      </div>
    </div>
  </main>

  <!-- Feature Section -->
  <section class="features">
    <h2>Powerful Features for Academic Excellence</h2>
    <p class="subtext">
      Our comprehensive platform combines cutting-edge AI technology with intuitive design to streamline your research workflow.
    </p>

    <div class="feature-grid">
      <div class="feature-card">
        <span class="material-icons blue">menu_book</span>
        <h3>Document Management</h3>
        <p>Upload and manage thesis chapters with secure PDF storage and version control.</p>
      </div>
      <div class="feature-card">
        <span class="material-icons purple">psychology</span>
        <h3>AI-Powered Analysis</h3>
        <p>AI-powered grammar, structure, and section validation using a fine-tuned variant of Google's FLAN-T5-Base.</p>
      </div>
      <div class="feature-card">
        <span class="material-icons green">groups</span>
        <h3>Collaborative Platform</h3>
        <p>Seamless communication between students, professors, and research coordinators.</p>
      </div>
      <div class="feature-card">
        <span class="material-icons red">shield</span>
        <h3>Secure & Compliant</h3>
        <p>Role-based access control and data protection.</p>
      </div>
    </div>
  </section>

  <!-- Role-Based Features Section -->
  <section class="roles">
    <h2>Tailored for Every Role</h2>
    <p class="subtext">
      Specialized dashboards and features designed for students, professors, and research coordinators.
    </p>

    <div class="role-grid">
      <div class="role-card">
        <span class="material-icons blue">school</span>
        <h3>Student</h3>
        <div class="feature-item">
          <p><span class="material-icons check">check_circle</span> Upload thesis chapters (PDF)</p>
          <p><span class="material-icons check">check_circle</span> Kanban-style progress tracking</p>
          <p><span class="material-icons check">check_circle</span> AI-generated feedback and alerts</p>
          <p><span class="material-icons check">check_circle</span> Grammar and structure validation</p>
        </div>
      </div>

      <div class="role-card">
        <span class="material-icons green">person</span>
        <h3>Professor</h3>
        <div class="feature-item">
          <p><span class="material-icons check">check_circle</span> Review and validate submissions</p>
          <p><span class="material-icons check">check_circle</span> Provide detailed feedback</p>
          <p><span class="material-icons check">check_circle</span> Approve chapters and milestones</p>
          <p><span class="material-icons check">check_circle</span> Generate comprehensive reports</p>
          <p><span class="material-icons check">check_circle</span> Monitor student progress</p>
        </div>
      </div>

      <div class="role-card">
        <span class="material-icons purple">settings</span>
        <h3>Research Coordinator</h3>
        <div class="feature-item">
          <p><span class="material-icons check">check_circle</span> Manage system administration</p>
          <p><span class="material-icons check">check_circle</span> Create advisor accounts</p>
          <p><span class="material-icons check">check_circle</span> Assign advisors to sections</p>
          <p><span class="material-icons check">check_circle</span> Oversee research progress</p>
          <p><span class="material-icons check">check_circle</span> Generate system-wide reports</p>
          
        </div>
      </div>
    </div>
  </section>

  <!-- AI Features Section -->
  <section class="ai-section">
    <h2 class="ai-title">Advanced AI-Powered Analysis</h2>
    <p class="ai-subtitle">
      Leverage cutting-edge artificial intelligence to enhance research quality and efficiency.
    </p>

    <div class="ai-container">
      <!-- Features List -->
      <div class="ai-features">
        <div class="feature-card ai-feature-item">
          <div class="icon-container">
            <span class="material-symbols-outlined ai-icon">task_alt</span>
          </div>
          <h4>Fine-tuned FLAN-T5-Base model Validation</h4>
          <p>AI-powered grammar, structure, and section validation using state-of-the-art language models.</p>
       
        </div>

        

        <div class="feature-card ai-feature-item">
          <div class="icon-container">
            <span class="material-symbols-outlined ai-icon">verified_user</span>
          </div>
          <h4>AI Content Detection</h4>
          <p>Sophisticated algorithms detect potentially AI-generated content to ensure academic integrity.</p>
        </div>

        <div class="feature-card ai-feature-item">
          <div class="icon-container">
            <span class="material-symbols-outlined ai-icon">spellcheck</span>
          </div>
          <h4>Advanced Grammar & Spelling Analysis</h4>
          <p>Real-time grammar and spelling contextual suggestions and academic writing improvements.</p>
        </div>

        <div class="feature-card ai-feature-item">
          <div class="icon-container">
            <span class="material-symbols-outlined ai-icon">format_align_left</span>
          </div>
          <h4>Intelligent Formatting Assistant</h4>
          <p>Automated formatting checks and suggestions for proper academic document structure and layout compliance.</p>
        </div>

        <div class="feature-card ai-feature-item">
          <div class="icon-container">
            <span class="material-symbols-outlined ai-icon">schema</span>
          </div>
          <h4>Structure Analysis</h4>
          <p>Deep analysis of document structure, ensuring proper organization of sections, chapters, and research components.</p>
        </div>

        <div class="feature-card ai-feature-item">
          <div class="icon-container">
            <span class="material-symbols-outlined ai-icon">library_books</span>
          </div>
          <h4>Citation Analysis & Validation</h4>
          <p>Comprehensive citation checking, format validation.</p>
        </div>
      </div>

      <!-- Real-time Analysis Box -->
      <!-- <div class="ai-analysis-box">
        <h3>Real-time Analysis</h3>
        <p>Get instant feedback on your research with our advanced AI systems that analyze grammar, structure, and compliance in real-time.</p>
      </div> -->
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-logo">
      <span class="material-symbols-outlined">menu_book</span>
      <div><strong>ThesisTrack</strong><br />CICT Research Platform</div>
    </div>
    <div class="footer-text">
      © 2025 ThesisTrack. College of Information and Communications Technology.<br />
      <span>Algorithm-Driven Platform for Efficient Research Monitoring</span>
    </div>
  </footer>

  <script src="JS/index.js"></script>
</body>
</html>
