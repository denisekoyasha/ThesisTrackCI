<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db/db.php';

// Verify student access
requireRole(['student']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chapter = $_POST['chapter'] ?? null;
    
    if (!$chapter) {
        echo json_encode(['success' => false, 'message' => 'Chapter number required']);
        exit();
    }

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
            $response = [
                'success' => true,
                'fontFamily' => $config['font_family'] ?? 'Times New Roman, serif',
                'fontSize' => intval($config['font_size'] ?? 12),
                'alignment' => $config['alignment'] ?? 'justify',
                'lineSpacing' => floatval($config['line_spacing'] ?? 1.6),
                'marginTop' => floatval($config['margin_top'] ?? 1.0),
                'marginBottom' => floatval($config['margin_bottom'] ?? 1.0),
                'marginLeft' => floatval($config['margin_left'] ?? 1.5),
                'marginRight' => floatval($config['margin_right'] ?? 1.0),
                'indentation' => floatval($config['indentation'] ?? 0.5),
                'borderEnabled' => ($config['border_style'] ?? 'none') !== 'none',
                'logoPosition' => $config['logo_position'] ?? 'none',
                'enabledSections' => json_decode($config['active_sections'] ?? '[]', true)
            ];
            echo json_encode($response);
        } else {
            echo json_encode([
                'success' => true,
                'fontFamily' => 'Times New Roman, serif',
                'fontSize' => 12,
                'alignment' => 'justify',
                'lineSpacing' => 1.6,
                'marginTop' => 1.0,
                'marginBottom' => 1.0,
                'marginLeft' => 1.5,
                'marginRight' => 1.0,
                'indentation' => 0.5,
                'borderEnabled' => false,
                'logoPosition' => 'none',
                'enabledSections' => []
            ]);
        }
    } catch (Exception $e) {
        error_log("Error loading chapter format: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error loading format configuration']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
