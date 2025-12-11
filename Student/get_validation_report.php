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
    // Get the chapter record with AI analysis data
    $stmt = $pdo->prepare("
        SELECT ai_report, ai_score, ai_feedback, chapter_number, version,
               completeness_score, completeness_feedback, completeness_report
        FROM chapters 
        WHERE group_id = ? AND chapter_number = ? AND version = ?
    ");
    $stmt->execute([$group_id, $chapter_number, $version]);
    $chapter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chapter) {
        echo json_encode(['success' => false, 'error' => 'Chapter not found']);
        exit();
    }
    
    // Enhanced debug logging
    error_log("=== VALIDATION REPORT DEBUG ===");
    error_log("Chapter: $chapter_number, Version: $version, Group: $group_id");
    error_log("AI Report exists: " . (!empty($chapter['ai_report']) ? 'YES' : 'NO'));
    error_log("AI Score: " . ($chapter['ai_score'] ?? 'NULL'));
    
    $response = [
        'success' => true,
        'chapter_number' => intval($chapter_number),
        'version' => intval($version),
        'overall_ai_percentage' => $chapter['ai_score'] ?? 0,
        'ai_feedback' => $chapter['ai_feedback'] ?? '',
        'total_sentences_analyzed' => 0,
        'sentences_flagged_as_ai' => 0,
        'analysis' => [],
        'generated_on' => date('Y-m-d H:i:s'),
        'completeness_score' => $chapter['completeness_score'] ?? null,
        'completeness_feedback' => $chapter['completeness_feedback'] ?? '',
        'data_status' => 'complete'
    ];
    
    // Process AI Report data
    if (!empty($chapter['ai_report'])) {
        $rawReport = $chapter['ai_report'];
        $reportLength = strlen($rawReport);
        
        error_log("AI Report length: $reportLength");
        
        // Check for truncation (TEXT column max is 65535)
        if ($reportLength >= 65535) {
            error_log("WARNING: AI report likely TRUNCATED at 65535 characters");
            $response['data_status'] = 'truncated';
            $response['warning'] = 'Detailed analysis was truncated due to database storage limits. Some data may be missing.';
            
            // Try to extract what we can from the truncated data
            processTruncatedReport($rawReport, $response, $chapter);
        } else {
            // Normal processing for non-truncated reports
            processNormalReport($rawReport, $response, $chapter);
        }
    } else {
        error_log("No AI report data in database");
        $response['data_status'] = 'missing';
        $response['warning'] = 'No detailed analysis data available';
    }
    
    // Final summary
    error_log("=== FINAL RESPONSE ===");
    error_log("AI Percentage: " . $response['overall_ai_percentage']);
    error_log("Analysis Items: " . count($response['analysis']));
    error_log("Data Status: " . $response['data_status']);
    error_log("======================");
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Validation report error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function processNormalReport($rawReport, &$response, $chapter) {
    $aiReport = json_decode($rawReport, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        error_log("JSON Decode: SUCCESS");
        
        if (isset($aiReport['ai_analysis'])) {
            $aiAnalysis = $aiReport['ai_analysis'];
            
            $response['overall_ai_percentage'] = $aiAnalysis['overall_ai_percentage'] ?? $chapter['ai_score'];
            $response['total_sentences_analyzed'] = $aiAnalysis['total_sentences_analyzed'] ?? 0;
            $response['sentences_flagged_as_ai'] = $aiAnalysis['sentences_flagged_as_ai'] ?? 0;
            $response['analysis'] = $aiAnalysis['analysis'] ?? [];
            $response['generated_on'] = $aiAnalysis['generated_on'] ?? date('Y-m-d H:i:s');
            
            error_log("Extracted from ai_analysis: " . count($response['analysis']) . " analysis items");
        }
    } else {
        error_log("JSON Decode FAILED: " . json_last_error_msg());
        $response['data_status'] = 'corrupted';
    }
}

function processTruncatedReport($rawReport, &$response, $chapter) {
    error_log("Attempting to process truncated report...");
    
    // Try multiple approaches to extract data from truncated JSON
    
    // Approach 1: Try to parse as-is first
    $aiReport = json_decode($rawReport, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        error_log("Truncated JSON parsed successfully");
        if (isset($aiReport['ai_analysis'])) {
            extractDataFromAnalysis($aiReport['ai_analysis'], $response);
        }
        return;
    }
    
    // Approach 2: Look for the analysis array pattern in the raw text
    $analysisData = extractAnalysisFromRawText($rawReport);
    if (!empty($analysisData)) {
        error_log("Extracted " . count($analysisData) . " analysis items from raw text");
        $response['analysis'] = $analysisData;
        $response['total_sentences_analyzed'] = count($analysisData);
        
        // Count AI flagged sentences
        $aiCount = 0;
        foreach ($analysisData as $item) {
            if (isset($item['is_ai']) && $item['is_ai']) {
                $aiCount++;
            }
        }
        $response['sentences_flagged_as_ai'] = $aiCount;
        return;
    }
    
    // Approach 3: Try to fix the JSON structure
    $fixedJson = fixTruncatedJson($rawReport);
    $aiReport = json_decode($fixedJson, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        error_log("Fixed JSON parsed successfully");
        if (isset($aiReport['ai_analysis'])) {
            extractDataFromAnalysis($aiReport['ai_analysis'], $response);
        }
    } else {
        error_log("All extraction methods failed");
        // At least provide the basic scores
        $response['analysis'] = [];
        $response['total_sentences_analyzed'] = 0;
        $response['sentences_flagged_as_ai'] = 0;
    }
}

function extractDataFromAnalysis($aiAnalysis, &$response) {
    $response['overall_ai_percentage'] = $aiAnalysis['overall_ai_percentage'] ?? $response['overall_ai_percentage'];
    $response['total_sentences_analyzed'] = $aiAnalysis['total_sentences_analyzed'] ?? 0;
    $response['sentences_flagged_as_ai'] = $aiAnalysis['sentences_flagged_as_ai'] ?? 0;
    $response['analysis'] = $aiAnalysis['analysis'] ?? [];
    $response['generated_on'] = $aiAnalysis['generated_on'] ?? date('Y-m-d H:i:s');
}

function extractAnalysisFromRawText($rawText) {
    $analysis = [];
    
    // Look for the "analysis" array in the raw text
    $analysisPattern = '/"analysis"\s*:\s*\[(.*?)\]\s*,?\s*"generated_on"/s';
    if (preg_match($analysisPattern, $rawText, $matches)) {
        $analysisContent = $matches[1];
        
        // Extract individual analysis items
        $itemPattern = '/\{([^}]*)\}/';
        preg_match_all($itemPattern, $analysisContent, $itemMatches);
        
        foreach ($itemMatches[1] as $itemContent) {
            $item = [];
            
            // Extract basic fields
            if (preg_match('/"text"\s*:\s*"([^"]*)"/', $itemContent, $textMatch)) {
                $item['text'] = $textMatch[1];
            }
            if (preg_match('/"is_ai"\s*:\s*(true|false)/', $itemContent, $aiMatch)) {
                $item['is_ai'] = $aiMatch[1] === 'true';
            }
            if (preg_match('/"ai_probability"\s*:\s*([0-9.]+)/', $itemContent, $probMatch)) {
                $item['ai_probability'] = floatval($probMatch[1]);
            }
            
            if (!empty($item)) {
                $analysis[] = $item;
            }
        }
    }
    
    return $analysis;
}

function fixTruncatedJson($json) {
    $depth = 0;
    $inString = false;
    $escaped = false;
    $lastValidPos = 0;
    
    for ($i = 0; $i < strlen($json); $i++) {
        $char = $json[$i];
        
        if (!$inString) {
            if ($char === '{' || $char === '[') {
                $depth++;
                $lastValidPos = $i;
            } elseif ($char === '}' || $char === ']') {
                $depth--;
                $lastValidPos = $i;
            } elseif ($char === '"') {
                $inString = true;
            }
        } else {
            if ($char === '\\') {
                $escaped = !$escaped;
            } elseif ($char === '"' && !$escaped) {
                $inString = false;
                $lastValidPos = $i;
            } else {
                $escaped = false;
            }
        }
    }
    
    // Take the last valid position and close everything
    $fixed = substr($json, 0, $lastValidPos + 1);
    
    // Close all open structures
    for ($i = 0; $i < $depth; $i++) {
        $fixed .= '}';
    }
    
    return $fixed;
}
?>
