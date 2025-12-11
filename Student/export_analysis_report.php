<?php
/**
 * Export Analysis Report to Excel
 * Version 13 - Created for exporting Full Analysis Report modal data to Excel
 * Updated to use native PHP (no external libraries required)
 */

/* Removed PhpSpreadsheet dependency - using native PHP with Excel XML format */

/* Prevent direct access */
if (!isset($_POST['export_data'])) {
    die('Invalid request');
}

/* Parse export data */
$exportData = json_decode($_POST['export_data'], true);
if (!$exportData) {
    die('Invalid export data');
}

$chapterNumber = $exportData['chapter_number'] ?? 'Unknown';
$version = $exportData['version'] ?? '1';
$aiData = $exportData['ai_data'] ?? [];
$thesisData = $exportData['thesis_data'] ?? [];

/* Calculate scores */
$aiScore = $aiData['overall_ai_percentage'] ?? 0;
$completenessScore = $thesisData['chapter_scores']['chapter_completeness_score'] ?? 0;
$relevanceScore = $thesisData['chapter_scores']['chapter_relevance_score'] ?? 0;
$totalAnalyzed = $aiData['total_sentences_analyzed'] ?? 0;
$totalFlagged = $aiData['sentences_flagged_as_ai'] ?? 0;
$presentSections = $thesisData['chapter_scores']['present_sections'] ?? 0;
$totalSections = $thesisData['chapter_scores']['total_sections'] ?? 0;

/* Generate filename */
$filename = "analysis_report_chapter_{$chapterNumber}.xls";

/* Set headers for Excel download */
header('Content-Type: application/vnd.ms-excel');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Cache-Control: max-age=0');

/* Start Excel XML */
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
echo ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
echo ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

/* Define Styles */
echo '<Styles>' . "\n";

/* Header Style */
echo '<Style ss:ID="Header">' . "\n";
echo '  <Font ss:Bold="1" ss:Size="16"/>' . "\n";
echo '  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . "\n";
echo '</Style>' . "\n";

/* Subheader Style */
echo '<Style ss:ID="Subheader">' . "\n";
echo '  <Font ss:Bold="1" ss:Size="14"/>' . "\n";
echo '  <Interior ss:Color="#E2E8F0" ss:Pattern="Solid"/>' . "\n";
echo '</Style>' . "\n";

/* Column Header Style */
echo '<Style ss:ID="ColumnHeader">' . "\n";
echo '  <Font ss:Bold="1"/>' . "\n";
echo '  <Interior ss:Color="#CBD5E1" ss:Pattern="Solid"/>' . "\n";
echo '  <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . "\n";
echo '</Style>' . "\n";

/* Bold Style */
echo '<Style ss:ID="Bold">' . "\n";
echo '  <Font ss:Bold="1"/>' . "\n";
echo '</Style>' . "\n";

/* AI Content Style (Red) */
echo '<Style ss:ID="AIContent">' . "\n";
echo '  <Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/>' . "\n";
echo '</Style>' . "\n";

/* Human Content Style (Green) */
echo '<Style ss:ID="HumanContent">' . "\n";
echo '  <Interior ss:Color="#D1FAE5" ss:Pattern="Solid"/>' . "\n";
echo '</Style>' . "\n";

/* Present Style (Green) */
echo '<Style ss:ID="Present">' . "\n";
echo '  <Interior ss:Color="#D1FAE5" ss:Pattern="Solid"/>' . "\n";
echo '</Style>' . "\n";

/* Missing Style (Red) */
echo '<Style ss:ID="Missing">' . "\n";
echo '  <Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/>' . "\n";
echo '</Style>' . "\n";

echo '</Styles>' . "\n";

/* ========== OVERVIEW SHEET ========== */
echo '<Worksheet ss:Name="Overview">' . "\n";
echo '<Table>' . "\n";

/* Set column widths */
echo '<Column ss:Width="200"/>' . "\n";
echo '<Column ss:Width="120"/>' . "\n";
echo '<Column ss:Width="150"/>' . "\n";
echo '<Column ss:Width="400"/>' . "\n";

/* Header Row */
echo '<Row>' . "\n";
echo '  <Cell ss:MergeAcross="3" ss:StyleID="Header"><Data ss:Type="String">Comprehensive Analysis Report</Data></Cell>' . "\n";
echo '</Row>' . "\n";
echo '<Row/>' . "\n"; // Empty row

/* Chapter Info */
echo '<Row>' . "\n";
echo '  <Cell ss:StyleID="Bold"><Data ss:Type="String">Chapter:</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . htmlspecialchars($chapterNumber) . '</Data></Cell>' . "\n";
echo '  <Cell ss:StyleID="Bold"><Data ss:Type="String">Version:</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . htmlspecialchars($version) . '</Data></Cell>' . "\n";
echo '</Row>' . "\n";
echo '<Row/>' . "\n"; // Empty row

/* Overview Scores Section */
echo '<Row>' . "\n";
echo '  <Cell ss:MergeAcross="3" ss:StyleID="Subheader"><Data ss:Type="String">Overview Scores</Data></Cell>' . "\n";
echo '</Row>' . "\n";

/* Column Headers */
echo '<Row>' . "\n";
echo '  <Cell ss:StyleID="ColumnHeader"><Data ss:Type="String">Metric</Data></Cell>' . "\n";
echo '  <Cell ss:StyleID="ColumnHeader"><Data ss:Type="String">Score</Data></Cell>' . "\n";
echo '  <Cell ss:StyleID="ColumnHeader"><Data ss:Type="String">Status</Data></Cell>' . "\n";
echo '  <Cell ss:StyleID="ColumnHeader"><Data ss:Type="String">Description</Data></Cell>' . "\n";
echo '</Row>' . "\n";

/* AI Score Row */
$aiStatus = $aiScore >= 75 ? 'High Risk' : ($aiScore >= 50 ? 'Medium Risk' : 'Low Risk');
$aiDescription = $aiScore >= 75 ? 'High probability of AI-generated content' : ($aiScore >= 50 ? 'Moderate AI content detected' : 'Low AI content probability');
echo '<Row>' . "\n";
echo '  <Cell><Data ss:Type="String">AI Content Probability</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . round($aiScore) . '%</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . htmlspecialchars($aiStatus) . '</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . htmlspecialchars($aiDescription) . '</Data></Cell>' . "\n";
echo '</Row>' . "\n";

/* Completeness Score Row */
$completenessStatus = $completenessScore >= 80 ? 'Excellent' : ($completenessScore >= 60 ? 'Good' : ($completenessScore >= 40 ? 'Fair' : 'Poor'));
$completenessDescription = $completenessScore >= 80 ? 'Well-structured chapter' : ($completenessScore >= 60 ? 'Adequate structure' : 'Needs structural improvement');
echo '<Row>' . "\n";
echo '  <Cell><Data ss:Type="String">Structure Completeness</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . round($completenessScore) . '%</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . htmlspecialchars($completenessStatus) . '</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . htmlspecialchars($completenessDescription) . '</Data></Cell>' . "\n";
echo '</Row>' . "\n";

/* Relevance Score Row */
$relevanceStatus = $relevanceScore >= 80 ? 'Excellent' : ($relevanceScore >= 60 ? 'Good' : ($relevanceScore >= 40 ? 'Fair' : 'Poor'));
$relevanceDescription = $relevanceScore >= 80 ? 'Highly relevant content' : ($relevanceScore >= 60 ? 'Mostly relevant content' : 'Some relevance issues');
echo '<Row>' . "\n";
echo '  <Cell><Data ss:Type="String">Content Relevance</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . round($relevanceScore) . '%</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . htmlspecialchars($relevanceStatus) . '</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . htmlspecialchars($relevanceDescription) . '</Data></Cell>' . "\n";
echo '</Row>' . "\n";

echo '</Table>' . "\n";
echo '</Worksheet>' . "\n";

/* ========== AI ANALYSIS SHEET ========== */
echo '<Worksheet ss:Name="AI Analysis">' . "\n";
echo '<Table>' . "\n";

/* Set column widths */
echo '<Column ss:Width="80"/>' . "\n";
echo '<Column ss:Width="100"/>' . "\n";
echo '<Column ss:Width="120"/>' . "\n";
echo '<Column ss:Width="120"/>' . "\n";
echo '<Column ss:Width="600"/>' . "\n";

/* Header Row */
echo '<Row>' . "\n";
echo '  <Cell ss:MergeAcross="4" ss:StyleID="Header"><Data ss:Type="String">AI Content Analysis</Data></Cell>' . "\n";
echo '</Row>' . "\n";
echo '<Row/>' . "\n"; // Empty row

/* Summary Stats */
echo '<Row>' . "\n";
echo '  <Cell ss:StyleID="Bold"><Data ss:Type="String">Overall AI Probability:</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . round($aiScore) . '%</Data></Cell>' . "\n";
echo '</Row>' . "\n";

echo '<Row>' . "\n";
echo '  <Cell ss:StyleID="Bold"><Data ss:Type="String">Text Chunks Analyzed:</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="Number">' . $totalAnalyzed . '</Data></Cell>' . "\n";
echo '</Row>' . "\n";

echo '<Row>' . "\n";
echo '  <Cell ss:StyleID="Bold"><Data ss:Type="String">AI Chunks Flagged:</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="Number">' . $totalFlagged . '</Data></Cell>' . "\n";
echo '</Row>' . "\n";
echo '<Row/>' . "\n"; // Empty row

/* Detailed Analysis */
if (!empty($aiData['analysis'])) {
    echo '<Row>' . "\n";
    echo '  <Cell ss:MergeAcross="4" ss:StyleID="Subheader"><Data ss:Type="String">Detailed Text Analysis</Data></Cell>' . "\n";
    echo '</Row>' . "\n";

    /* Column Headers */
    echo '<Row>' . "\n";
    echo '  <Cell ss:StyleID="ColumnHeader"><Data ss:Type="String">Section #</Data></Cell>' . "\n";
    echo '  <Cell ss:StyleID="ColumnHeader"><Data ss:Type="String">Type</Data></Cell>' . "\n";
    echo '  <Cell ss:StyleID="ColumnHeader"><Data ss:Type="String">Status</Data></Cell>' . "\n";
    echo '  <Cell ss:StyleID="ColumnHeader"><Data ss:Type="String">AI Probability</Data></Cell>' . "\n";
    echo '  <Cell ss:StyleID="ColumnHeader"><Data ss:Type="String">Content</Data></Cell>' . "\n";
    echo '</Row>' . "\n";

    /* Data Rows */
    foreach ($aiData['analysis'] as $index => $section) {
        $sectionText = $section['text'] ?? $section['content'] ?? $section['sentence'] ?? 'No content';
        $isAI = $section['is_ai'] ?? (($section['ai_probability'] ?? 0) >= 50);
        $aiProbability = $section['ai_probability'] ?? ($isAI ? 100 : 0);
        $sectionType = $section['type'] ?? 'paragraph';
        
        $statusStyle = $isAI ? 'AIContent' : 'HumanContent';
        $statusText = $isAI ? 'AI Content' : 'Human Content';
        
        echo '<Row>' . "\n";
        echo '  <Cell><Data ss:Type="Number">' . ($index + 1) . '</Data></Cell>' . "\n";
        echo '  <Cell><Data ss:Type="String">' . htmlspecialchars($sectionType) . '</Data></Cell>' . "\n";
        echo '  <Cell ss:StyleID="' . $statusStyle . '"><Data ss:Type="String">' . $statusText . '</Data></Cell>' . "\n";
        echo '  <Cell><Data ss:Type="String">' . round($aiProbability) . '%</Data></Cell>' . "\n";
        echo '  <Cell><Data ss:Type="String">' . htmlspecialchars(substr($sectionText, 0, 500)) . '</Data></Cell>' . "\n";
        echo '</Row>' . "\n";
    }
}

echo '</Table>' . "\n";
echo '</Worksheet>' . "\n";

/* ========== STRUCTURE ANALYSIS SHEET ========== */
echo '<Worksheet ss:Name="Structure Analysis">' . "\n";
echo '<Table>' . "\n";

/* Set column widths */
echo '<Column ss:Width="250"/>' . "\n";
echo '<Column ss:Width="120"/>' . "\n";
echo '<Column ss:Width="120"/>' . "\n";
echo '<Column ss:Width="200"/>' . "\n";

/* Header Row */
echo '<Row>' . "\n";
echo '  <Cell ss:MergeAcross="3" ss:StyleID="Header"><Data ss:Type="String">Chapter Structure Analysis</Data></Cell>' . "\n";
echo '</Row>' . "\n";
echo '<Row/>' . "\n"; // Empty row

/* Summary Stats */
echo '<Row>' . "\n";
echo '  <Cell ss:StyleID="Bold"><Data ss:Type="String">Structure Completeness:</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . round($completenessScore) . '%</Data></Cell>' . "\n";
echo '</Row>' . "\n";

echo '<Row>' . "\n";
echo '  <Cell ss:StyleID="Bold"><Data ss:Type="String">Content Relevance:</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . round($relevanceScore) . '%</Data></Cell>' . "\n";
echo '</Row>' . "\n";

echo '<Row>' . "\n";
echo '  <Cell ss:StyleID="Bold"><Data ss:Type="String">Sections Found:</Data></Cell>' . "\n";
echo '  <Cell><Data ss:Type="String">' . $presentSections . '/' . $totalSections . '</Data></Cell>' . "\n";
echo '</Row>' . "\n";
echo '<Row/>' . "\n"; // Empty row

/* Section Breakdown */
if (!empty($thesisData['sections'])) {
    echo '<Row>' . "\n";
    echo '  <Cell ss:MergeAcross="3" ss:StyleID="Subheader"><Data ss:Type="String">Section Breakdown</Data></Cell>' . "\n";
    echo '</Row>' . "\n";

    /* Column Headers */
    echo '<Row>' . "\n";
    echo '  <Cell ss:StyleID="ColumnHeader"><Data ss:Type="String">Section Name</Data></Cell>' . "\n";
    echo '  <Cell ss:StyleID="ColumnHeader"><Data ss:Type="String">Status</Data></Cell>' . "\n";
    echo '  <Cell ss:StyleID="ColumnHeader"><Data ss:Type="String">Relevance</Data></Cell>' . "\n";
    echo '  <Cell ss:StyleID="ColumnHeader"><Data ss:Type="String">Detection Method</Data></Cell>' . "\n";
    echo '</Row>' . "\n";

    /* Data Rows */
    foreach ($thesisData['sections'] as $sectionName => $sectionData) {
        $isPresent = $sectionData['present'] ?? false;
        $relevance = $sectionData['relevance_percent'] ?? 0;
        $detectionMethod = $sectionData['detection_method'] ?? 'unknown';
        
        $formattedName = ucwords(str_replace('_', ' ', $sectionName));
        $statusStyle = $isPresent ? 'Present' : 'Missing';
        $statusText = $isPresent ? 'Present' : 'Missing';
        $relevanceText = $isPresent ? round($relevance) . '%' : 'N/A';
        
        echo '<Row>' . "\n";
        echo '  <Cell><Data ss:Type="String">' . htmlspecialchars($formattedName) . '</Data></Cell>' . "\n";
        echo '  <Cell ss:StyleID="' . $statusStyle . '"><Data ss:Type="String">' . $statusText . '</Data></Cell>' . "\n";
        echo '  <Cell><Data ss:Type="String">' . $relevanceText . '</Data></Cell>' . "\n";
        echo '  <Cell><Data ss:Type="String">' . htmlspecialchars(ucwords(str_replace('_', ' ', $detectionMethod))) . '</Data></Cell>' . "\n";
        echo '</Row>' . "\n";
    }
}

/* Missing Sections */
$missingSections = $thesisData['chapter_scores']['missing_sections'] ?? [];
if (!empty($missingSections)) {
    echo '<Row/>' . "\n"; // Empty row
    echo '<Row>' . "\n";
    echo '  <Cell ss:MergeAcross="3" ss:StyleID="Subheader"><Data ss:Type="String">Missing Sections</Data></Cell>' . "\n";
    echo '</Row>' . "\n";

    foreach ($missingSections as $missingSection) {
        echo '<Row>' . "\n";
        echo '  <Cell ss:MergeAcross="3"><Data ss:Type="String">• ' . htmlspecialchars($missingSection) . '</Data></Cell>' . "\n";
        echo '</Row>' . "\n";
    }
}

echo '</Table>' . "\n";
echo '</Worksheet>' . "\n";

/* Close Workbook */
echo '</Workbook>' . "\n";

exit;
?>
