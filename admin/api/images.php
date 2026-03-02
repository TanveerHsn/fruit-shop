<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'delete':
        $imageId = filter_input(INPUT_POST, 'id',       FILTER_VALIDATE_INT)
                ?: filter_input(INPUT_GET,  'id',       FILTER_VALIDATE_INT);
        $fruitId = filter_input(INPUT_POST, 'fruit_id', FILTER_VALIDATE_INT)
                ?: filter_input(INPUT_GET,  'fruit_id', FILTER_VALIDATE_INT);

        if (!$imageId || !$fruitId) {
            http_response_code(400); echo json_encode(['error' => 'Invalid parameters']); break;
        }

        $stmt = $pdo->prepare("SELECT * FROM fruit_images WHERE id = ? AND fruit_id = ?");
        $stmt->execute([$imageId, $fruitId]);
        $image = $stmt->fetch();

        if (!$image) { http_response_code(404); echo json_encode(['error' => 'Image not found']); break; }

        deleteImageFile($image['image_path']);
        $pdo->prepare("DELETE FROM fruit_images WHERE id = ?")->execute([$imageId]);

        // Promote next image to primary if needed
        if ($image['is_primary']) {
            $next = $pdo->prepare("SELECT id FROM fruit_images WHERE fruit_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
            $next->execute([$fruitId]);
            $nextImg = $next->fetch();
            if ($nextImg) setPrimaryImage($pdo, $fruitId, (int)$nextImg['id']);
        }

        echo json_encode(['success' => true]);
        break;

    case 'set_primary':
        $imageId = filter_input(INPUT_POST, 'id',       FILTER_VALIDATE_INT)
                ?: filter_input(INPUT_GET,  'id',       FILTER_VALIDATE_INT);
        $fruitId = filter_input(INPUT_POST, 'fruit_id', FILTER_VALIDATE_INT)
                ?: filter_input(INPUT_GET,  'fruit_id', FILTER_VALIDATE_INT);

        if (!$imageId || !$fruitId) {
            http_response_code(400); echo json_encode(['error' => 'Invalid parameters']); break;
        }

        setPrimaryImage($pdo, $fruitId, $imageId);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action.']);
}
