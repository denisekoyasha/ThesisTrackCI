<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db/db.php';

// Verify coordinator access
requireRole(['coordinator']);

try {
    // Get raw POST data
    $rawInput = file_get_contents("php://input");
    
    if (empty($rawInput)) {
        throw new Exception('No data received. Raw input is empty.');
    }

    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    // Validate required fields
    if (!isset($data['chapter'])) {
        throw new Exception('Missing required field: chapter');
    }

    if (!isset($data['active_sections'])) {
        throw new Exception('Missing required field: active_sections');
    }

    $chapterName = "Chapter " . intval(str_replace('Chapter ', '', $data['chapter']));

    // First, check if record exists
    $checkStmt = $pdo->prepare("SELECT id FROM thesis_format_config WHERE chapter = ?");
    $checkStmt->execute([$chapterName]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        // UPDATE existing record
        $sql = "
            UPDATE thesis_format_config 
            SET active_sections = :active_sections,
                font_family = :font_family,
                font_size = :font_size,
                alignment = :alignment,
                line_spacing = :line_spacing,
                indentation = :indentation,
                border_style = :border_style,
                margin_top = :margin_top,
                margin_bottom = :margin_bottom,
                margin_left = :margin_left,
                margin_right = :margin_right,
                logo_position = :logo_position,
                updated_at = CURRENT_TIMESTAMP
            WHERE chapter = :chapter
        ";
        $action = 'updated';
    } else {
        // INSERT new record
        $sql = "
            INSERT INTO thesis_format_config 
            (chapter, active_sections, font_family, font_size, alignment, line_spacing, 
             indentation, border_style, margin_top, margin_bottom, margin_left, margin_right, logo_position)
            VALUES (:chapter, :active_sections, :font_family, :font_size, :alignment, :line_spacing,
                    :indentation, :border_style, :margin_top, :margin_bottom, :margin_left, :margin_right, :logo_position)
        ";
        $action = 'inserted';
    }

    $stmt = $pdo->prepare($sql);

    $params = [
        ':chapter' => $chapterName,
        ':active_sections' => json_encode($data['active_sections']),
        ':font_family' => $data['font_family'] ?? 'Times New Roman',
        ':font_size' => isset($data['font_size']) ? intval($data['font_size']) : 12,
        ':alignment' => $data['alignment'] ?? 'justify',
        ':line_spacing' => isset($data['line_spacing']) ? floatval($data['line_spacing']) : 1.5,
        ':indentation' => isset($data['indentation']) ? floatval($data['indentation']) : 0.5,
        ':border_style' => (isset($data['border_style']) && $data['border_style'] === 'solid') ? 'solid' : 'none',
        ':margin_top' => isset($data['margin_top']) ? floatval($data['margin_top']) : 1.0,
        ':margin_bottom' => isset($data['margin_bottom']) ? floatval($data['margin_bottom']) : 1.0,
        ':margin_left' => isset($data['margin_left']) ? floatval($data['margin_left']) : 1.5,
        ':margin_right' => isset($data['margin_right']) ? floatval($data['margin_right']) : 1.0,
        ':logo_position' => $data['logo_position'] ?? 'none'
    ];

    $success = $stmt->execute($params);

    if ($success) {
        echo json_encode([
            'success' => true, 
            'message' => "Configuration {$action} successfully",
            'chapter' => $chapterName,
            'sections_count' => count($data['active_sections']),
            'action' => $action
        ]);
    } else {
        $errorInfo = $stmt->errorInfo();
        throw new Exception('Database error: ' . ($errorInfo[2] ?? 'Unknown error'));
    }

} catch (Exception $e) {
    error_log("Save format config error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error saving configuration: ' . $e->getMessage()
    ]);
}
?>
