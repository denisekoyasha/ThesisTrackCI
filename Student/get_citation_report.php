<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../auth.php';
requireRole(['student']);
require_once __DIR__ . '/../db/db.php';

// Handle citation report requests
if (isset($_GET['get_citation_report']) && $_GET['get_citation_report'] === 'true') {
    header('Content-Type: application/json');
    
    $chapter_number = $_GET['chapter'] ?? null;
    $version = $_GET['version'] ?? null;
    $group_id = $_GET['group'] ?? null;

    if (!$chapter_number || !$version || !$group_id) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        exit();
    }

    try {
        // Get the chapter record with citation analysis data
        $stmt = $pdo->prepare("
            SELECT citation_report, citation_score, citation_feedback, chapter_number, version
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
        
        // Process citation report
        if (!empty($chapter['citation_report'])) {
            $citationReport = json_decode($chapter['citation_report'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response = array_merge($response, $citationReport);
            } else {
                error_log("Citation report JSON decode error: " . json_last_error_msg());
            }
        }
        
        // Add citation scores if available
        if (!empty($chapter['citation_score'])) {
            $response['citation_score'] = floatval($chapter['citation_score']);
        }
        if (!empty($chapter['citation_feedback'])) {
            $response['citation_feedback'] = $chapter['citation_feedback'];
        }
        
        // Ensure we have the required structure
        if (!isset($response['total_citations'])) {
            $response['total_citations'] = 0;
        }
        if (!isset($response['correct_citations'])) {
            $response['correct_citations'] = 0;
        }
        if (!isset($response['corrected_citations'])) {
            $response['corrected_citations'] = [];
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        error_log("Citation report error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
} else {
    // If not a citation report request, return error
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}
?>
