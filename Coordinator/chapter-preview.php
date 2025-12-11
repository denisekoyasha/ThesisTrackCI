<?php
// Include database connection and authentication
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db/db.php';

// Get chapter parameter FIRST
$chapter = isset($_GET['chapter']) ? intval($_GET['chapter']) : 1;

// Load format configuration from database
function loadFormatConfig($chapter, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT font_family, font_size, alignment, line_spacing, 
                   margin_top, margin_bottom, margin_left, margin_right,
                   indentation, border_style, logo_position, active_sections
            FROM thesis_format_config 
            WHERE chapter = ?
        ");
        $stmt->execute(["Chapter $chapter"]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            return [
                'fontFamily' => $config['font_family'] ?? 'Times New Roman, serif',
                'fontSize' => intval($config['font_size'] ?? 12),
                'textAlign' => $config['alignment'] ?? 'justify',
                'lineSpacing' => floatval($config['line_spacing'] ?? 1.6),
                'marginTop' => floatval($config['margin_top'] ?? 1.0),
                'marginBottom' => floatval($config['margin_bottom'] ?? 1.0),
                'marginLeft' => floatval($config['margin_left'] ?? 1.5),
                'marginRight' => floatval($config['margin_right'] ?? 1.0),
                'indent' => floatval($config['indentation'] ?? 0.5),
                'borderEnabled' => ($config['border_style'] ?? 'none') !== 'none',
                'logoPosition' => $config['logo_position'] ?? 'none',
                'enabledSections' => json_decode($config['active_sections'] ?? '[]', true)
            ];
        }
    } catch (Exception $e) {
        error_log("Error loading format config for chapter $chapter: " . $e->getMessage());
    }
    
    return null;
}

// Load database configuration if available
$dbConfig = loadFormatConfig($chapter, $pdo);

// Use database configuration or fall back to URL parameters
$fontFamily = $dbConfig['fontFamily'] ?? (isset($_GET['fontFamily']) ? $_GET['fontFamily'] : 'Times New Roman, serif');
$fontSize = $dbConfig['fontSize'] ?? (isset($_GET['fontSize']) ? intval($_GET['fontSize']) : 12);
$sectionFontSize = $dbConfig['fontSize'] ?? (isset($_GET['sectionFontSize']) ? intval($_GET['sectionFontSize']) : $fontSize);
$textAlign = $dbConfig['textAlign'] ?? (isset($_GET['textAlign']) ? $_GET['textAlign'] : 'justify');
$lineSpacing = $dbConfig['lineSpacing'] ?? (isset($_GET['lineSpacing']) ? floatval($_GET['lineSpacing']) : 1.6);
$marginTop = 0.2; // Small top margin for all pages
$marginBottom = 0.2; // Small bottom margin for all pages
$marginLeft = 0.05; // 0.2 inch left margin
$marginRight = 0.1; // 0.2 inch right margin
$indent = $dbConfig['indent'] ?? (isset($_GET['indent']) ? floatval($_GET['indent']) : 0.5);
$borderEnabled = $dbConfig['borderEnabled'] ?? (isset($_GET['borderEnabled']) ? filter_var($_GET['borderEnabled'], FILTER_VALIDATE_BOOLEAN) : false);
$logoPosition = $dbConfig['logoPosition'] ?? (isset($_GET['logoPosition']) ? $_GET['logoPosition'] : 'none');

// Use enabled sections from database or URL
if (!empty($dbConfig['enabledSections'])) {
    $enabledSections = $dbConfig['enabledSections'];
} else {
    $enabledSections = isset($_GET['enabledSections']) ? json_decode($_GET['enabledSections'], true) : [];
}

// Debug logging
error_log("Chapter $chapter format settings loaded:");
error_log("Font: $fontFamily, Size: $fontSize, Align: $textAlign");
error_log("Enabled sections: " . implode(', ', $enabledSections));

// Convert chapter number to Roman numeral
function toRoman($number) {
    $map = [
        'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
        'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
        'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
    ];
    $returnValue = '';
    while ($number > 0) {
        foreach ($map as $roman => $int) {
            if($number >= $int) {
                $number -= $int;
                $returnValue .= $roman;
                break;
            }
        }
    }
    return $returnValue;
}

$chapterRoman = toRoman($chapter);

// Define chapter content templates with placeholder descriptions
$chapterTemplates = [
    1 => [
        'title' => 'INTRODUCTION',
        'sections' => [
            'Introduction' => [
                'content' => "[This section should provide an overview of the entire chapter, introducing the research topic, background, and context of the study.]",
                'level' => 'section'
            ],
            'Project Context' => [
                'content' => "[Describe the current situation, environment, or system where the problem exists. Explain the background and circumstances that led to the need for this study.]",
                'level' => 'section'
            ],
            'Purpose and Description' => [
                'content' => "[Clearly state the purpose of the study and provide a detailed description of what the research aims to accomplish. Explain the main goals and intentions.]",
                'level' => 'section'
            ],
            'Objectives of the Study' => [
                'content' => "[Present the main objectives that the research intends to achieve. This section outlines what the study hopes to accomplish.]",
                'level' => 'section'
            ],
            'General Objectives' => [
                'content' => "[State the broad, overall goals of the research. These should be comprehensive and cover the main purpose of the study.]",
                'level' => 'subsection'
            ],
            'Specific Objectives' => [
                'content' => "[List the detailed, measurable objectives that support the general objectives. These should be specific, achievable, and clearly defined.]",
                'level' => 'subsection'
            ],
            'Conceptual Paradigm' => [
                'content' => "[Present the conceptual framework or theoretical model that guides the research. Include diagrams or models if necessary to illustrate the research framework.]",
                'level' => 'section'
            ],
            'Scope and Limitation of the Study' => [
                'content' => "[Define the boundaries and coverage of the research. Specify what is included and excluded, and acknowledge any limitations or constraints.]",
                'level' => 'section'
            ],
            'Significance of the Study' => [
                'content' => "[Explain the importance and potential contributions of the research. Identify who will benefit from the study and how it will impact the field.]",
                'level' => 'section'
            ],
            'Definition of Terms' => [
                'content' => "[Provide clear, operational definitions of key terms and concepts used in the study. Ensure technical terms are explained for better understanding.]",
                'level' => 'section'
            ]
        ]
    ],
    2 => [
        'title' => 'REVIEW OF RELATED LITERATURE AND STUDIES',
        'sections' => [
            'Review of Related Literature and Studies' => [
                'content' => "[This section should present a comprehensive review of existing literature, research studies, and scholarly articles relevant to your topic. Organize the review thematically or chronologically.]",
                'level' => 'section'
            ],
            'Synthesis of the Study' => [
                'content' => "[Summarize and integrate the key findings from the literature review. Identify gaps, contradictions, or areas where further research is needed based on the reviewed materials.]",
                'level' => 'section'
            ]
        ]
    ],
    3 => [
        'title' => 'RESEARCH METHODOLOGY',
        'sections' => [
            'Research Methodology' => [
                'content' => "[Describe the overall research approach and methodology used in the study. Explain the research design and philosophical underpinnings of your methodological choices.]",
                'level' => 'chapter'
            ],
            'Research Method Used' => [
                'content' => "[Specify the research method(s) employed (e.g., quantitative, qualitative, mixed-methods). Justify why this method was chosen and how it aligns with your research objectives.]",
                'level' => 'section'
            ],
            'Population, Sample Size, and Sampling Technique' => [
                'content' => "[Define the target population, explain how the sample size was determined, and describe the sampling technique used. Include inclusion/exclusion criteria if applicable.]",
                'level' => 'section'
            ],
            'Description of Respondents' => [
                'content' => "[Provide detailed information about the research participants or respondents. Include demographic characteristics, selection criteria, and relevant background information.]",
                'level' => 'section'
            ],
            'Research Instrument' => [
                'content' => "[Describe the tools, instruments, or equipment used for data collection. Explain the development, validation, and reliability testing of your research instruments.]",
                'level' => 'section'
            ],
            'Data Gathering Procedure' => [
                'content' => "[Outline the step-by-step process of data collection. Include details about timing, location, ethical considerations, and procedures followed during data gathering.]",
                'level' => 'section'
            ],
            'Data Analysis and Procedure' => [
                'content' => "[Explain the methods and techniques used for data analysis. Describe statistical tests, qualitative analysis methods, or other analytical approaches employed.]",
                'level' => 'section'
            ],
            'Survey Questionnaire' => [
                'content' => "[Include or describe the survey questionnaire used in the study. Explain the structure, question types, scaling methods, and how it was administered.]",
                'level' => 'section'
            ],
            'Software Evaluation Instrument of ISO 25010' => [
                'content' => "[Describe the software evaluation criteria based on ISO 25010 standards. Explain how these standards were applied to evaluate the system's quality characteristics.]",
                'level' => 'section'
            ],
            'Interview and Observation' => [
                'content' => "[Detail the interview protocols and observation methods used. Include information about interview questions, observation checklists, and data recording procedures.]",
                'level' => 'section'
            ],
            'Validation and Distribution of the Instrument' => [
                'content' => "[Explain how the research instruments were validated and tested. Describe the pilot testing process and any modifications made based on validation results.]",
                'level' => 'section'
            ],
            'Data Encoding and Formulation of the Solution' => [
                'content' => "[Describe the process of data encoding, entry, and preparation. Explain how the data was organized and prepared for analysis.]",
                'level' => 'section'
            ],
            'Evaluation of Data and Result' => [
                'content' => "[Outline the methods used to evaluate and interpret the collected data. Describe how results were assessed and what criteria were used for evaluation.]",
                'level' => 'section'
            ],
            'Statistical Treatment of Data' => [
                'content' => "[Specify the statistical methods and treatments applied to the data. Include information about descriptive and inferential statistics used.]",
                'level' => 'section'
            ],
            'Statistical Tools' => [
                'content' => "[List and describe the statistical software or tools used for data analysis. Explain why these tools were selected and how they were utilized.]",
                'level' => 'section'
            ],
            'Frequency' => [
                'content' => "[Explain how frequency analysis was used in the study. Describe what frequency distributions were calculated and how they were interpreted.]",
                'level' => 'section'
            ],
            'Percentage' => [
                'content' => "[Describe how percentage calculations were applied in data analysis. Explain what percentages represent and how they contribute to understanding the results.]",
                'level' => 'section'
            ],
            'Weighted Mean' => [
                'content' => "[Explain the use of weighted mean in data analysis. Describe how weights were assigned and what the weighted mean represents in your study.]",
                'level' => 'section'
            ],
            'Technical Requirements' => [
                'content' => "[Specify the technical requirements needed for the system or study. Include hardware, software, and other technical specifications.]",
                'level' => 'section'
            ],
            'Hardware Requirements' => [
                'content' => "[Detail the hardware specifications and requirements. Include server specifications, client requirements, and any specialized hardware needed.]",
                'level' => 'section'
            ],
            'Software Requirements' => [
                'content' => "[List the software requirements and dependencies. Include operating systems, programming languages, frameworks, and other software components.]",
                'level' => 'section'
            ],
            'Network Requirements' => [
                'content' => "[Describe the network infrastructure and requirements. Include bandwidth, protocols, security measures, and network architecture specifications.]",
                'level' => 'section'
            ],
            'API Specifications' => [
                'content' => "[Detail the API requirements and specifications. Include endpoints, data formats, authentication methods, and integration requirements.]",
                'level' => 'section'
            ],
            'Project Design' => [
                'content' => "[Present the overall design of the project or system. Explain the architectural decisions, design patterns, and overall project structure.]",
                'level' => 'section'
            ],
            'Diagrams' => [
                'content' => "[Include and explain relevant diagrams that illustrate the system design, processes, or architecture. Reference figures and provide clear explanations.]",
                'level' => 'section'
            ],
            'System Architecture' => [
                'content' => "[Describe the system architecture in detail. Explain the components, layers, and how they interact with each other.]",
                'level' => 'section'
            ],
            'Data Flow Diagram' => [
                'content' => "[Present and explain the data flow diagrams. Show how data moves through the system and between different components.]",
                'level' => 'section'
            ],
            'Proposed Flowchart' => [
                'content' => "[Include flowcharts that illustrate processes, algorithms, or workflows. Provide clear explanations of each step in the flowchart.]",
                'level' => 'section'
            ],
            'Unified Modeling Language' => [
                'content' => "[Present UML diagrams that model the system. Include use case diagrams, class diagrams, sequence diagrams, or other relevant UML models.]",
                'level' => 'section'
            ],
            'System Development' => [
                'content' => "[Describe the system development process and methodology. Explain the development lifecycle, iterations, and implementation approach.]",
                'level' => 'section'
            ],
            'Algorithm Discussion' => [
                'content' => "[Explain the algorithms used in the system. Provide pseudocode, flowcharts, or detailed descriptions of key algorithms.]",
                'level' => 'section'
            ],
            'Features' => [
                'content' => "[List and describe the main features of the system. Explain what each feature does and how it contributes to the overall functionality.]",
                'level' => 'section'
            ],
            'Function' => [
                'content' => "[Detail the functions and operations of the system. Explain how different components work together to achieve the system's objectives.]",
                'level' => 'section'
            ],
            'Uses' => [
                'content' => "[Describe the practical applications and uses of the system. Explain how different users will interact with and benefit from the system.]",
                'level' => 'section'
            ]
        ]
    ],
    4 => [
        'title' => 'RESULTS AND DISCUSSION',
        'sections' => [
            'Results and Discussion' => [
                'content' => "[This section should present the findings of your research in a clear and organized manner. Present data, analysis results, and discuss their implications.]",
                'level' => 'section'
            ],
            'Evaluation and Scoring' => [
                'content' => "[Present the evaluation results and scoring of your system or research outcomes. Explain the evaluation criteria and how scores were determined.]",
                'level' => 'section'
            ]
        ]
    ],
    5 => [
        'title' => 'SUMMARY, CONCLUSIONS, AND RECOMMENDATIONS',
        'sections' => [
            'Summary, Conclusions, and Recommendations' => [
                'content' => "[This section should provide a comprehensive summary of the entire study, draw conclusions based on findings, and offer recommendations for future work.]",
                'level' => 'section'
            ],
            'Summary of Findings' => [
                'content' => "[Summarize the key findings from your research. Present the main results in a concise manner, highlighting the most important discoveries.]",
                'level' => 'section'
            ],
            'Conclusions' => [
                'content' => "[Draw conclusions based on your research findings. Explain what can be concluded from the study and how these conclusions address your research objectives.]",
                'level' => 'section'
            ],
            'Recommendations' => [
                'content' => "[Provide recommendations based on your research outcomes. Suggest practical applications, future research directions, or improvements that could be made.]",
                'level' => 'section'
            ],
            'Bibliography' => [
                'content' => "[List all references cited in your thesis using the appropriate citation style. Ensure all sources are properly formatted and alphabetized.]",
                'level' => 'section'
            ]
        ]
    ]
];

$currentChapter = $chapterTemplates[$chapter] ?? $chapterTemplates[1];

// Function to split content into pages with proper section filtering
function splitContentIntoPages($sections, $enabledSections) {
    $pages = [];
    $currentPage = [];
    $currentPageHeight = 0;
    $maxPageHeight = 800;
    
    // Filter sections first
    $filteredSections = [];
    foreach ($sections as $sectionName => $sectionData) {
        if (in_array($sectionName, $enabledSections)) {
            $filteredSections[$sectionName] = $sectionData;
        }
    }
    
    error_log("Filtered sections count: " . count($filteredSections));
    
    foreach ($filteredSections as $sectionName => $sectionData) {
        $sectionHeight = estimateContentHeight($sectionData['content'], $sectionData['level']);
        
        if ($currentPageHeight + $sectionHeight > $maxPageHeight && !empty($currentPage)) {
            $pages[] = $currentPage;
            $currentPage = [];
            $currentPageHeight = 0;
        }
        
        $currentPage[] = [
            'name' => $sectionName,
            'data' => $sectionData
        ];
        $currentPageHeight += $sectionHeight;
    }
    
    if (!empty($currentPage)) {
        $pages[] = $currentPage;
    }
    
    error_log("Generated pages: " . count($pages));
    return $pages;
}

function estimateContentHeight($content, $level) {
    $baseHeight = ($level === 'chapter') ? 80 : 60;
    $contentHeight = ceil(strlen($content) / 60) * 18;
    return $baseHeight + $contentHeight;
}

$pages = splitContentIntoPages($currentChapter['sections'], $enabledSections);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter <?php echo $chapterRoman; ?> Preview - ThesisTrack</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --light-color: #ecf0f1;
            --dark-color: #34495e;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f5f5;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            font-family: <?php echo $fontFamily; ?>;
        }

        .preview-header {
            width: 100%;
            max-width: 1200px;
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .preview-container {
            width: 100%;
            max-width: 1200px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .preview-toolbar {
            padding: 15px;
            background: var(--light-color);
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .preview-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #e0e0e0;
            gap: 20px;
        }

        .document-page {
            background: white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            width: 8.5in;
            height: 11in;
            position: relative;
            margin-bottom: 20px;
            page-break-after: always;
            overflow: hidden;
        }

        /* Border Lines - ADJUSTED TOP BORDER */
        .border-line {
            position: absolute;
            pointer-events: none;
            z-index: 10;
            <?php echo $borderEnabled ? 'display: block;' : 'display: none;'; ?>
        }

        .border-line.top {
            left: -0.01in; /* Moved slightly to the left */
            top: -0.02in; /* Moved slightly higher */
            width: 8.52in; /* Extended width */
            height: 0.88in;
            border: 1px solid #000000;
        }

        .border-line.top-2 {
            left: 0.01in;
            top: 1.27in;
            width: 8.49in;
            height: 0in;
            border-top: 1px solid #000000;
        }

        .border-line.bottom {
            left: 0in;
            top: 10.29in;
            width: 8.49in;
            height: 0in;
            border-top: 1px solid #000000;
        }

        .border-line.left {
            left: 0.91in;
            top: 0in;
            width: 0in;
            height: 11in;
            border-left: 1px solid #000000;
        }

        .border-line.right {
            left: 7.64in;
            top: 0in;
            width: 0in;
            height: 11in;
            border-left: 1px solid #000000;
        }

        .border-line.right-inside-1 {
            left: 7.64in;
            top: 7.14in;
            width: 0.84in;
            height: 0in;
            border-top: 1px solid #000000;
        }

        .border-line.right-inside-2 {
            left: 7.64in;
            top: 8.8in;
            width: 0.87in;
            height: 0in;
            border-top: 1px solid #000000;
        }

        .content-safe-zone {
            position: absolute;
            top: 0.86in;
            left: 0.91in;
            right: 0.86in;
            bottom: 0.71in;
            pointer-events: none;
            z-index: 5;
        }

        /* FIXED: Adjusted positioning to create proper 0.2 inch margins */
        .document-content {
            position: absolute;
            top: 1.3in; /* 0.86in + 0.2in margin */
            left: 1.02in; /* 0.91in + 0.2in margin */
            right: 1.02in; /* 0.86in + 0.2in margin */
            bottom: 0.91in; /* 0.71in + 0.2in margin */
            display: flex;
            flex-direction: column;
            z-index: 1;
            overflow: hidden;
            font-family: <?php echo $fontFamily; ?>;
            font-size: <?php echo $fontSize; ?>pt;
            text-align: <?php echo $textAlign; ?>;
            line-height: <?php echo $lineSpacing; ?>;
        }

        .header-section {
            position: absolute;
            top: 0.86in;
            left: 0.98in;
            right: 0.92in;
            height: 0.41in;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            <?php echo $logoPosition === 'none' ? 'display: none;' : ''; ?>
        }

        .university-header {
            font-family: "Arial MT", Arial, sans-serif;
            font-size: 20px;
            font-weight: normal;
            text-align: center;
            color: #000;
            letter-spacing: 3.1pt;
        }

        /* Chapter Content Styles */
        .chapter-title {
            text-align: center;
            margin-bottom: 30px;
            margin-top: 0.1in;
        }

        .chapter-title h1 {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .chapter-title h2 {
            font-size: 14pt;
            font-weight: bold;
        }

        .chapter-content {
            line-height: <?php echo $lineSpacing; ?>;
            flex: 1;
            overflow: hidden;
            width: 100%;
        }

        .section-title {
            font-weight: bold;
            margin: 20px 0 12px 0;
            text-align: left;
            text-indent: 0;
            page-break-after: avoid;
            font-size: <?php echo $sectionFontSize; ?>pt;
        }

        .chapter-level {
            font-size: 14pt;
            text-align: center;
            margin-top: 25px;
            margin-bottom: 15px;
        }

        .section-level {
            font-size: 12pt;
            margin-left: 0;
        }

        .subsection-level {
            font-size: 12pt;
            margin-left: 0;
            font-weight: normal;
        }

        .section-content {
            margin-bottom: 15px;
            text-align: <?php echo $textAlign; ?>;
            text-indent: <?php echo $indent; ?>in;
            line-height: <?php echo $lineSpacing; ?>;
            page-break-inside: avoid;
        }

        .section-content p {
            margin-bottom: 8px;
        }

        .logo-left, .logo-right {
            width: 0.3in;
            height: 0.3in;
            object-fit: contain;
        }

        .logo-left {
            margin-right: 10px;
        }

        .logo-right {
            margin-left: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background: #1a252f;
        }

        .page-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .page-info {
            font-weight: 600;
            color: var(--dark-color);
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .preview-header, .preview-toolbar {
                display: none;
            }
            
            .preview-container {
                box-shadow: none;
                border-radius: 0;
            }
            
            .document-page {
                box-shadow: none;
                margin: 0;
                page-break-after: always;
            }
            
            .border-line, .content-safe-zone {
                display: none !important;
            }
            
            .preview-content {
                padding: 0;
                gap: 0;
            }
            
            .section-content {
                page-break-inside: auto;
            }
            
            .section-title {
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Preview Header -->
    <div class="preview-header">
        <h1><i class="fas fa-file-pdf"></i> Chapter <?php echo $chapterRoman; ?> Preview - <?php echo $currentChapter['title']; ?></h1>
        <div class="preview-actions">
            <button class="btn btn-secondary" onclick="togglePositionGuides()">
                <i class="fas fa-ruler"></i> Guides
            </button>
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
            <button class="btn btn-primary" onclick="window.close()">
                <i class="fas fa-times"></i> Close Preview
            </button>
        </div>
    </div>

    <!-- Preview Container -->
    <div class="preview-container">
        <div class="preview-toolbar">
            <div class="page-controls">
                <button class="btn btn-secondary" onclick="zoomOut()">
                    <i class="fas fa-search-minus"></i>
                </button>
                <span class="page-info" id="zoomLevel">100%</span>
                <button class="btn btn-secondary" onclick="zoomIn()">
                    <i class="fas fa-search-plus"></i>
                </button>
                <button class="btn btn-secondary" onclick="toggleBorders()">
                    <i class="fas fa-border-style"></i> Toggle Borders
                </button>
            </div>
            <div class="page-info">
                Pages: <?php echo count($pages); ?>
            </div>
        </div>

        <div class="preview-content" id="previewContent">
            <?php if (empty($enabledSections)): ?>
                <!-- Show message if no sections enabled -->
                <div class="document-page">
                    <div class="border-line top"></div>
                    <div class="border-line top-2"></div>
                    <div class="border-line bottom"></div>
                    <div class="border-line left"></div>
                    <div class="border-line right"></div>
                    <div class="border-line right-inside-1"></div>
                    <div class="border-line right-inside-2"></div>
                    
                    <div class="content-safe-zone" id="safeZone"></div>

                    <div class="header-section">
                        <img src="../images/tcu.png" class="logo-left" alt="TCU Logo" onerror="this.style.display='none'">
                        <div class="university-header">TAGUIG CITY UNIVERSITY</div>
                        <img src="../images/cict.png" class="logo-right" alt="CICT Logo" onerror="this.style.display='none'">
                    </div>

                    <div class="document-content">
                        <div class="chapter-title">
                            <h1>CHAPTER <?php echo $chapterRoman; ?></h1>
                            <h2><?php echo $currentChapter['title']; ?></h2>
                        </div>
                        <div class="chapter-content">
                            <div class="section-content">No sections enabled for this chapter. Please enable sections in the control panel.</div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Generate pages with content -->
                <?php foreach ($pages as $pageIndex => $pageSections): ?>
                    <div class="document-page" id="page-<?php echo $pageIndex + 1; ?>">
                        <div class="border-line top"></div>
                        <div class="border-line top-2"></div>
                        <div class="border-line bottom"></div>
                        <div class="border-line left"></div>
                        <div class="border-line right"></div>
                        <div class="border-line right-inside-1"></div>
                        <div class="border-line right-inside-2"></div>
                        
                        <div class="content-safe-zone" id="safeZone"></div>

                        <div class="header-section">
                            <img src="../images/tcu.png" class="logo-left" alt="TCU Logo" onerror="this.style.display='none'">
                            <div class="university-header">TAGUIG CITY UNIVERSITY</div>
                            <img src="../images/cict.png" class="logo-right" alt="CICT Logo" onerror="this.style.display='none'">
                        </div>

                        <div class="document-content">
                            <?php if ($pageIndex === 0): ?>
                                <!-- Show chapter title only on first page -->
                                <div class="chapter-title">
                                    <h1>CHAPTER <?php echo $chapterRoman; ?></h1>
                                    <h2><?php echo $currentChapter['title']; ?></h2>
                                </div>
                            <?php endif; ?>

                            <div class="chapter-content">
                                <?php foreach ($pageSections as $section): 
                                    $sectionName = $section['name'];
                                    $sectionData = $section['data'];
                                    $levelClass = $sectionData['level'] . '-level';
                                ?>
                                    <div class="section-title <?php echo $levelClass; ?>">
                                        <?php echo $sectionName; ?>
                                    </div>
                                    <div class="section-content">
                                        <?php echo nl2br(htmlspecialchars($sectionData['content'])); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Apply formatting from URL parameters (for dynamic updates)
        function applyFormatting() {
            const urlParams = new URLSearchParams(window.location.search);
            const documentContents = document.querySelectorAll('.document-content');
            const sectionTitles = document.querySelectorAll('.section-title');
            const sectionContents = document.querySelectorAll('.section-content');
            const borderLines = document.querySelectorAll('.border-line');
            const headerSection = document.querySelector('.header-section');
            
            // Font Family
            const fontFamily = urlParams.get('fontFamily') || '<?php echo $fontFamily; ?>';
            document.body.style.fontFamily = fontFamily;
            documentContents.forEach(content => {
                content.style.fontFamily = fontFamily;
            });
            
            // Font Size for content
            const fontSize = urlParams.get('fontSize') || '<?php echo $fontSize; ?>';
            documentContents.forEach(content => {
                content.style.fontSize = fontSize + 'pt';
            });
            
            // Font Size for section titles
            const sectionFontSize = urlParams.get('sectionFontSize') || '<?php echo $sectionFontSize; ?>';
            sectionTitles.forEach(title => {
                title.style.fontSize = sectionFontSize + 'pt';
            });
            
            // Text Alignment
            const textAlign = urlParams.get('textAlign') || '<?php echo $textAlign; ?>';
            documentContents.forEach(content => {
                content.style.textAlign = textAlign;
            });
            sectionContents.forEach(content => {
                content.style.textAlign = textAlign;
            });
            
            // Line Spacing
            const lineSpacing = urlParams.get('lineSpacing') || '<?php echo $lineSpacing; ?>';
            documentContents.forEach(content => {
                content.style.lineHeight = lineSpacing;
            });
            sectionContents.forEach(content => {
                content.style.lineHeight = lineSpacing;
            });
            
            // Indentation
            const indent = urlParams.get('indent') || '<?php echo $indent; ?>';
            sectionContents.forEach(content => {
                content.style.textIndent = indent + 'in';
            });
            
            // Borders
            const borderEnabled = urlParams.get('borderEnabled') || '<?php echo $borderEnabled ? 'true' : 'false'; ?>';
            borderLines.forEach(line => {
                line.style.display = borderEnabled === 'true' ? 'block' : 'none';
            });
            
            // Logo Position
            const logoPosition = urlParams.get('logoPosition') || '<?php echo $logoPosition; ?>';
            if (headerSection) {
                headerSection.style.display = logoPosition === 'none' ? 'none' : 'flex';
            }
            
            console.log('Formatting applied from URL parameters');
        }

        // Zoom functionality
        let zoomLevel = 1;
        
        function zoomIn() {
            if (zoomLevel < 2) {
                zoomLevel += 0.1;
                updateZoom();
            }
        }
        
        function zoomOut() {
            if (zoomLevel > 0.5) {
                zoomLevel -= 0.1;
                updateZoom();
            }
        }
        
        function updateZoom() {
            const previewContent = document.getElementById('previewContent');
            previewContent.style.transform = `scale(${zoomLevel})`;
            previewContent.style.transformOrigin = 'top center';
            document.getElementById('zoomLevel').textContent = Math.round(zoomLevel * 100) + '%';
        }

        // Toggle borders
        function toggleBorders() {
            const borderLines = document.querySelectorAll('.border-line');
            const currentDisplay = borderLines[0].style.display;
            borderLines.forEach(line => {
                line.style.display = currentDisplay === 'none' ? 'block' : 'none';
            });
        }

        // Toggle position guides
        function togglePositionGuides() {
            const safeZones = document.querySelectorAll('.content-safe-zone');
            safeZones.forEach(zone => {
                if (zone.style.backgroundColor === 'rgba(0, 255, 0, 0.05)') {
                    zone.style.backgroundColor = 'transparent';
                    zone.style.border = 'none';
                } else {
                    zone.style.backgroundColor = 'rgba(0, 255, 0, 0.05)';
                    zone.style.border = '1px solid rgba(0, 255, 0, 0.3)';
                }
            });
        }

        // Initialize when page loads
        window.onload = function() {
            applyFormatting();
        };

        // Handle print event
        window.addEventListener('beforeprint', function() {
            const borderElements = document.querySelectorAll('.border-line, .content-safe-zone');
            borderElements.forEach(element => {
                element.style.display = 'none';
            });
        });
    </script>
</body>
</html>
