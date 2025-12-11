<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db/db.php';

// Verify coordinator access
requireRole(['coordinator']);

try {
    $stmt = $pdo->query("
        SELECT chapter, active_sections, font_family, font_size, alignment, 
               line_spacing, indentation, border_style, logo_position,
               margin_top, margin_bottom, margin_left, margin_right
        FROM thesis_format_config 
        ORDER BY chapter
    ");
    
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $formatted = [];

    foreach ($configs as $config) {
        // Handle JSON decoding safely
        $activeSections = json_decode($config['active_sections'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($activeSections)) {
            $activeSections = [];
        }

        $formatted[$config['chapter']] = [
            'active_sections' => $activeSections,
            'font_family'     => $config['font_family'] ?? 'Times New Roman',
            'font_size'       => intval($config['font_size'] ?? 12),
            'alignment'       => $config['alignment'] ?? 'justify',
            'line_spacing'    => floatval($config['line_spacing'] ?? 1.5),
            'indentation'     => floatval($config['indentation'] ?? 0.5),
            'border_style'    => $config['border_style'] ?? 'none',
            'logo_position'   => $config['logo_position'] ?? 'none',
            'margins'         => [
                'top'    => floatval($config['margin_top'] ?? 1.0),
                'bottom' => floatval($config['margin_bottom'] ?? 1.0),
                'left'   => floatval($config['margin_left'] ?? 1.0),
                'right'  => floatval($config['margin_right'] ?? 1.0)
            ]
        ];
    }

    echo json_encode([
        'success' => true, 
        'data' => $formatted,
        'count' => count($formatted)
    ]);

} catch (Exception $e) {
    error_log("Fetch format config error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error loading configuration: ' . $e->getMessage(),
        'data' => []
    ]);
}
?>
