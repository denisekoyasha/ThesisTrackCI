<?php
function callSpellingGrammarAPI($filePath, $chapter_number) {
    $api_url = "http://localhost:8003/check-file/";
    
    error_log("🔤 Starting Spelling & Grammar Analysis for Chapter $chapter_number");
    error_log("📁 File: " . basename($filePath));
    
    if (!file_exists($filePath)) {
        error_log("❌ File not found: $filePath");
        return ['error' => 'File not found'];
    }
    
    // Prepare the file for upload
    $file = new CURLFile($filePath, mime_content_type($filePath), basename($filePath));
    
    $postData = ['file' => $file];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: multipart/form-data'],
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'ThesisTrack/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log response details
    error_log("📊 Spelling & Grammar API Response - HTTP: $httpCode, Error: " . ($error ?: 'None'));
    
    if ($httpCode !== 200 || $error) {
        $error_msg = "Spelling & Grammar API failed - HTTP: $httpCode" . ($error ? ", Error: $error" : "");
        error_log("❌ Spelling & Grammar Analysis failed: $error_msg");
        return ['error' => $error_msg];
    }
    
    if (!$response) {
        error_log("❌ Spelling & Grammar API returned empty response");
        return ['error' => 'Empty response from Spelling & Grammar API'];
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $json_error = json_last_error_msg();
        error_log("❌ Spelling & Grammar API JSON decode error: $json_error");
        error_log("   Raw response: " . substr($response, 0, 500));
        return ['error' => "Invalid JSON from Spelling & Grammar API: $json_error"];
    }
    
    error_log("✅ Spelling & Grammar Analysis successful");
    return $data;
}

function processSpellingGrammarResult($apiResult) {
    if (isset($apiResult['error'])) {
        return [
            'success' => false,
            'error' => $apiResult['error']
        ];
    }
    
    if (!isset($apiResult['result'])) {
        return [
            'success' => false,
            'error' => 'Invalid API response structure'
        ];
    }
    
    $result = $apiResult['result'];
    
    // Calculate overall scores
    $totalIssues = count($result['issues'] ?? []);
    $spellingIssues = array_filter($result['issues'] ?? [], function($issue) {
        return $issue['type'] === 'spelling';
    });
    $grammarIssues = array_filter($result['issues'] ?? [], function($issue) {
        return $issue['type'] === 'grammar';
    });
    
    $spellingCount = count($spellingIssues);
    $grammarCount = count($grammarIssues);
    
    // Calculate scores (lower is better - fewer issues)
    $wordCount = $result['statistics']['word_count'] ?? 1;
    $spellingScore = $wordCount > 0 ? max(0, 100 - ($spellingCount / $wordCount * 100)) : 100;
    $grammarScore = $wordCount > 0 ? max(0, 100 - ($grammarCount / $wordCount * 100)) : 100;
    
    return [
        'success' => true,
        'spelling_score' => round($spellingScore),
        'grammar_score' => round($grammarScore),
        'spelling_feedback' => generateSpellingFeedback($spellingCount, $wordCount),
        'grammar_feedback' => generateGrammarFeedback($grammarCount, $wordCount),
        'spelling_report' => json_encode([
            'total_spelling_issues' => $spellingCount,
            'spelling_issues' => array_values($spellingIssues),
            'analysis_details' => $result
        ], JSON_PRETTY_PRINT),
        'grammar_report' => json_encode([
            'total_grammar_issues' => $grammarCount,
            'grammar_issues' => array_values($grammarIssues),
            'analysis_details' => $result
        ], JSON_PRETTY_PRINT),
        'total_issues' => $totalIssues,
        'word_count' => $wordCount,
        'analysis_details' => $result
    ];
}

function generateSpellingFeedback($spellingCount, $wordCount) {
    if ($spellingCount === 0) {
        return "Excellent spelling - no spelling errors detected";
    } elseif ($spellingCount <= 3) {
        return "Good spelling - only $spellingCount minor spelling issue(s) found";
    } elseif ($spellingCount <= 10) {
        return "Moderate spelling - $spellingCount spelling issues need attention";
    } else {
        $density = round(($spellingCount / max(1, $wordCount)) * 100, 1);
        return "Needs improvement - $spellingCount spelling issues found ($density% of words)";
    }
}

function generateGrammarFeedback($grammarCount, $wordCount) {
    if ($grammarCount === 0) {
        return "Excellent grammar - no grammatical errors detected";
    } elseif ($grammarCount <= 2) {
        return "Good grammar - only $grammarCount minor grammatical issue(s) found";
    } elseif ($grammarCount <= 5) {
        return "Moderate grammar - $grammarCount grammatical issues need attention";
    } else {
        $density = round(($grammarCount / max(1, $wordCount)) * 100, 1);
        return "Needs improvement - $grammarCount grammatical issues found ($density% of words)";
    }
}
?>
