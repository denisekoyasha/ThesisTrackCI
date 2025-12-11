<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="images/book-icon.ico">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="CSS/portal.css">
    <title>ThesisTrack - CICT Portal</title>
</head>
<body>
  
    <div class="login-container">
        <div class="login-left">
            <div class="university-info">
                <!-- Container for two logos side by side -->
                <div class="university-logo-container">
                    <div class="university-logo">
                        <a href="index.php"><img src="images/CICTlogo.png" alt="ThesisTrack Logo" />
                    </a>
                    </div>
                    <div class="university-logo">
                        <a href="index.php"><img src="images/TCUlogo.png" alt="TCU Logo" /></a>
                    </div>
                </div>
                
                <div class="university-name">TAGUIG CITY UNIVERSITY</div>
                <div class="college-name">College of Information and Communication Technology</div>
                <div class="system-title">ThesisTrack</div>
                <div class="system-subtitle">Comprehensive Thesis Management System for BSCS & BSIS Students</div>
            </div>
        </div>
        
        <div class="login-right">
            <div class="login-header">
                <h2>Welcome to CICT Portal</h2>
                <p>Select your role to access the thesis management system</p>
            </div>
            
            <div class="role-selection">
                <div class="role-card" onclick="selectRole('student')">
                    <div class="role-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="role-content">
                        <div class="role-title">Student</div>
                        <div class="role-description">Upload chapters, track progress, and receive feedback</div>
                    </div>
                    <div class="role-arrow">→</div>
                </div>
                
                <div class="role-card" onclick="selectRole('advisor')">
                    <div class="role-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="role-content">
                        <div class="role-title">Subject Advisor</div>
                        <div class="role-description">Monitor groups, review chapters, and provide feedback</div>
                    </div>
                    <div class="role-arrow">→</div>
                </div>
                
                <div class="role-card" onclick="selectRole('coordinator')">
                    <div class="role-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="role-content">
                        <div class="role-title">Research Coordinator</div>
                        <div class="role-description">Oversee all sections and monitor system-wide progress</div>
                    </div>
                    <div class="role-arrow">→</div>
                </div>
            </div>
        </div>
    </div>

    <script src="JS/portal.js"></script>
</body>
</html>
