<?php
// Include database connection and authentication
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Get chapter parameter
$chapter = isset($_GET['chapter']) ? intval($_GET['chapter']) : 1;

// Validate chapter number
if ($chapter < 1 || $chapter > 5) {
    $chapter = 1;
}

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

// Use database configuration or fall back to defaults
$fontFamily = $dbConfig['fontFamily'] ?? 'Times New Roman, serif';
$fontSize = $dbConfig['fontSize'] ?? 12;
$textAlign = $dbConfig['textAlign'] ?? 'justify';
$lineSpacing = $dbConfig['lineSpacing'] ?? 1.6;
$marginTop = $dbConfig['marginTop'] ?? 1.0;
$marginBottom = $dbConfig['marginBottom'] ?? 1.0;
$marginLeft = $dbConfig['marginLeft'] ?? 1.5;
$marginRight = $dbConfig['marginRight'] ?? 1.0;
$indent = $dbConfig['indent'] ?? 0.5;
$borderEnabled = $dbConfig['borderEnabled'] ?? false;
$logoPosition = $dbConfig['logoPosition'] ?? 'none';
$enabledSections = $dbConfig['enabledSections'] ?? [];

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

// Define chapter content templates
$chapterTemplates = [
    1 => [
        'title' => 'INTRODUCTION',
        'sections' => [
            'Background of the Study',
            'Significance of the Study',
            'Problem Statement',
            'Research Objectives',
            'Scope and Limitations'
        ]
    ],
    2 => [
        'title' => 'LITERATURE REVIEW',
        'sections' => [
            'Theoretical Framework',
            'Related Works',
            'Comparative Analysis',
            'Research Gap',
            'Conclusion'
        ]
    ],
    3 => [
        'title' => 'METHODOLOGY',
        'sections' => [
            'Research Design',
            'Participants/Subjects',
            'Data Collection Methods',
            'Data Analysis Procedures',
            'Ethical Considerations'
        ]
    ],
    4 => [
        'title' => 'RESULTS AND DISCUSSION',
        'sections' => [
            'Presentation of Results',
            'Statistical Analysis',
            'Key Findings',
            'Discussion of Findings',
            'Implications'
        ]
    ],
    5 => [
        'title' => 'CONCLUSION AND RECOMMENDATIONS',
        'sections' => [
            'Summary of Findings',
            'Conclusions',
            'Limitations',
            'Recommendations for Future Research',
            'Final Remarks'
        ]
    ]
];

$template = $chapterTemplates[$chapter] ?? $chapterTemplates[1];

// Generate HTML content for PDF
$html = <<<EOD
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: '$fontFamily';
            font-size: {$fontSize}pt;
            line-height: $lineSpacing;
            margin-top: {$marginTop}in;
            margin-bottom: {$marginBottom}in;
            margin-left: {$marginLeft}in;
            margin-right: {$marginRight}in;
            text-align: $textAlign;
            color: #333;
        }
        
        .page {
            page-break-after: always;
            min-height: 8.5in;
        }
        
        .page:last-child {
            page-break-after: avoid;
        }
        
        .header {
            text-align: center;
            margin-bottom: 1in;
            border-bottom: 1px solid #000;
            padding-bottom: 0.5in;
        }
        
        .university-name {
            font-weight: bold;
            font-size: 14pt;
            margin-bottom: 0.2in;
        }
        
        .chapter-title {
            text-transform: uppercase;
            font-weight: bold;
            font-size: {$fontSize}pt;
            text-align: center;
            margin-top: 1.5in;
            margin-bottom: 1.5in;
        }
        
        .section-title {
            font-weight: bold;
            font-size: {$fontSize}pt;
            margin-top: 1in;
            margin-bottom: 0.5in;
            text-transform: uppercase;
        }
        
        .section-content {
            text-indent: {$indent}in;
            text-align: $textAlign;
            margin-bottom: 0.5in;
        }
        
        p {
            margin: 0;
            margin-bottom: 0.2in;
        }
        
        @media print {
            .page {
                page-break-after: always;
            }
            .page:last-child {
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="university-name">TAGUIG CITY UNIVERSITY</div>
            <div style="margin-top: 0.3in;">Graduate Studies</div>
        </div>
        
        <div class="chapter-title">
            Chapter $chapter: {$template['title']}
        </div>
        
EOD;

// Add sections
foreach ($template['sections'] as $index => $section) {
    $html .= <<<EOD
        <div class="section-title">$section</div>
        <div class="section-content">
            <p>This is a template for the "$section" section. Replace this placeholder text with your actual content.</p>
            <p>Ensure that your content follows the university's thesis formatting guidelines, including proper spacing, indentation, and font specifications.</p>
        </div>
EOD;
}

$html .= <<<EOD
        <div style="margin-top: 2in; text-align: center; font-size: 10pt; color: #999;">
            <p>This is a template document. Do not submit as final thesis.</p>
        </div>
    </div>
</body>
</html>
EOD;

try {
    // Create PDF
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isPhpEnabled', false);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Generate filename
    $chapterNames = ['Introduction', 'Literature_Review', 'Methodology', 'Results_Discussion', 'Conclusion'];
    $filename = "Chapter_" . $chapter . "_" . $chapterNames[$chapter - 1] . "_Template.pdf";
    
    // Output PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: public, must-revalidate, max-age=0');
    header('Pragma: public');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    echo $dompdf->output();
    exit;
    
} catch (Exception $e) {
    error_log("PDF Generation Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Failed to generate PDF',
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
