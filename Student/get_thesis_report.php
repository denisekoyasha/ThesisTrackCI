<?php
require_once __DIR__ . '/../auth.php';
requireRole(['student']);
require_once __DIR__ . '/../db/db.php';

header('Content-Type: application/json');

$chapter_number = $_GET['chapter'] ?? null;
$version = $_GET['version'] ?? null;
$group_id = $_GET['group'] ?? null;

if (!$chapter_number || !$version || !$group_id) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

try {
    // Get the chapter record with thesis analysis data using NEW column names
    $stmt = $pdo->prepare("
        SELECT completeness_report, ai_report, chapter_number, version,
               completeness_score, completeness_feedback, ai_score, ai_feedback
        FROM chapters 
        WHERE group_id = ? AND chapter_number = ? AND version = ?
    ");
    $stmt->execute([$group_id, $chapter_number, $version]);
    $chapter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chapter) {
        echo json_encode(['success' => false, 'error' => 'Chapter not found']);
        exit();
    }
    
    $response = [
        'success' => true,
        'chapter_number' => intval($chapter_number),
        'version' => intval($version)
    ];
    
    // Process completeness report (contains sections and chapter scores)
    if (!empty($chapter['completeness_report'])) {
        $completenessReport = json_decode($chapter['completeness_report'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $response = array_merge($response, $completenessReport);
        }
    }
    
    // Process AI report for additional data if needed
    if (!empty($chapter['ai_report'])) {
        $aiReport = json_decode($chapter['ai_report'], true);
        if (json_last_error() === JSON_ERROR_NONE && isset($aiReport['thesis_analysis'])) {
            // Merge thesis analysis data from AI report if available
            if (empty($response['sections']) && !empty($aiReport['thesis_analysis']['sections'])) {
                $response['sections'] = $aiReport['thesis_analysis']['sections'];
            }
            if (empty($response['chapter_scores']) && !empty($aiReport['thesis_analysis']['chapter_scores'])) {
                $response['chapter_scores'] = $aiReport['thesis_analysis']['chapter_scores'];
            }
        }
    }
    
    // Extract section breakdown from completeness data
    if (!empty($response['sections_analysis'])) {
        // Convert sections_analysis to the expected sections format
        $response['sections'] = [];
        foreach ($response['sections_analysis'] as $sectionName => $sectionData) {
            $response['sections'][$sectionName] = [
                'present' => $sectionData['present'] ?? false,
                'relevance_percent' => $sectionData['relevance_percent'] ?? 0,
                'extracted_text' => '',
                'detection_method' => $sectionData['detection_method'] ?? 'unknown'
            ];
        }
        // Remove the temporary key
        unset($response['sections_analysis']);
    }
    
    // Ensure we have chapter scores with proper structure
    if (empty($response['chapter_scores'])) {
        $response['chapter_scores'] = [
            'total_sections' => 0,
            'present_sections' => 0,
            'missing_sections_count' => 0,
            'missing_sections' => [],
            'chapter_completeness_score' => $chapter['completeness_score'] ?? 0,
            'chapter_relevance_score' => $chapter['completeness_score'] ?? 0 // Using completeness_score for relevance
        ];
    } else {
        // Ensure the chapter_scores has all required fields
        $response['chapter_scores'] = array_merge([
            'total_sections' => 0,
            'present_sections' => 0,
            'missing_sections_count' => 0,
            'missing_sections' => [],
            'chapter_completeness_score' => $chapter['completeness_score'] ?? 0,
            'chapter_relevance_score' => $chapter['completeness_score'] ?? 0
        ], $response['chapter_scores']);
    }
    
    // Ensure we have sections data
    if (empty($response['sections'])) {
        $response['sections'] = [];
    }
    
    // Add completeness feedback if available
    if (!empty($chapter['completeness_feedback'])) {
        $response['completeness_feedback'] = $chapter['completeness_feedback'];
    }
    
    // Add AI data for comprehensive report
    if (!empty($chapter['ai_score'])) {
        $response['ai_score'] = $chapter['ai_score'];
    }
    if (!empty($chapter['ai_feedback'])) {
        $response['ai_feedback'] = $chapter['ai_feedback'];
    }
    
    // Add analyzed chapter name if available
    if (!empty($response['analyzed_chapter'])) {
        $response['analyzed_chapter_name'] = $response['analyzed_chapter'];
    } else {
        $response['analyzed_chapter_name'] = 'Chapter ' . $chapter_number;
    }
    
    // Add analysis date
    if (!empty($response['analysis_date'])) {
        $response['generated_on'] = $response['analysis_date'];
    } else {
        $response['generated_on'] = date('Y-m-d H:i:s');
    }
    
    // Debug logging
    error_log("Thesis Report Response for Chapter $chapter_number: " . json_encode([
        'sections_count' => count($response['sections']),
        'chapter_scores' => $response['chapter_scores'],
        'completeness_score' => $chapter['completeness_score'] ?? 'N/A'
    ]));
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Thesis report error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
