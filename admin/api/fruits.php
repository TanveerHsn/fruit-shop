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

    // ── List all fruits ──────────────────────────────────────────
    case 'list':
        $stmt = $pdo->query("
            SELECT f.*,
                   fi.image_path AS primary_image,
                   (SELECT COUNT(*) FROM fruit_images WHERE fruit_id = f.id) AS image_count
            FROM   fruits f
            LEFT JOIN fruit_images fi ON fi.fruit_id = f.id AND fi.is_primary = 1
            ORDER  BY f.created_at DESC
        ");
        $fruits = $stmt->fetchAll();

        $total      = count($fruits);
        $inStock    = count(array_filter($fruits, fn($f) => $f['in_stock']));
        $discounted = count(array_filter($fruits, fn($f) => $f['discount_percentage'] > 0));

        echo json_encode([
            'success' => true,
            'fruits'  => $fruits,
            'stats'   => [
                'total'      => $total,
                'in_stock'   => $inStock,
                'out_stock'  => $total - $inStock,
                'discounted' => $discounted,
            ],
        ]);
        break;

    // ── Get single fruit with images ─────────────────────────────
    case 'get':
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Invalid ID']); break; }

        $fruit = getFruitById($pdo, $id);
        if (!$fruit) { http_response_code(404); echo json_encode(['error' => 'Fruit not found']); break; }

        echo json_encode([
            'success' => true,
            'fruit'   => $fruit,
            'images'  => getFruitImages($pdo, $id),
        ]);
        break;

    // ── Create fruit ─────────────────────────────────────────────
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo json_encode(['error' => 'Method not allowed']); break;
        }

        $name        = trim($_POST['name']                ?? '');
        $description = trim($_POST['description']         ?? '');
        $price       = (float)($_POST['actual_price']     ?? 0);
        $discount    = (float)($_POST['discount_percentage'] ?? 0);
        $category    = trim($_POST['category']            ?? 'General') ?: 'General';
        $in_stock    = (($_POST['in_stock'] ?? '0') === '1') ? 1 : 0;

        $errors = [];
        if ($name === '')                        $errors[] = 'Fruit name is required.';
        if ($price <= 0)                         $errors[] = 'Price must be greater than 0.';
        if ($discount < 0 || $discount > 100)   $errors[] = 'Discount must be between 0 and 100.';

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'errors' => $errors]);
            break;
        }

        $pdo->prepare("
            INSERT INTO fruits (name, description, actual_price, discount_percentage, category, in_stock)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$name, $description, $price, $discount, $category, $in_stock]);

        $fruitId  = (int)$pdo->lastInsertId();
        $uploaded = 0;

        if (!empty($_FILES['images']['name'][0])) {
            $files = $_FILES['images'];
            for ($i = 0; $i < count($files['name']) && $uploaded < MAX_IMAGES; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $path = uploadImage([
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ]);
                if ($path) {
                    $pdo->prepare("
                        INSERT INTO fruit_images (fruit_id, image_path, is_primary, sort_order)
                        VALUES (?, ?, ?, ?)
                    ")->execute([$fruitId, $path, $uploaded === 0 ? 1 : 0, $uploaded]);
                    $uploaded++;
                }
            }
        }

        echo json_encode(['success' => true, 'id' => $fruitId]);
        break;

    // ── Update fruit ─────────────────────────────────────────────
    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo json_encode(['error' => 'Method not allowed']); break;
        }

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT)
           ?: filter_input(INPUT_GET,  'id', FILTER_VALIDATE_INT);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Invalid ID']); break; }

        if (!getFruitById($pdo, $id)) {
            http_response_code(404); echo json_encode(['error' => 'Fruit not found']); break;
        }

        $name        = trim($_POST['name']                ?? '');
        $description = trim($_POST['description']         ?? '');
        $price       = (float)($_POST['actual_price']     ?? 0);
        $discount    = (float)($_POST['discount_percentage'] ?? 0);
        $category    = trim($_POST['category']            ?? 'General') ?: 'General';
        $in_stock    = (($_POST['in_stock'] ?? '0') === '1') ? 1 : 0;

        $errors = [];
        if ($name === '')                        $errors[] = 'Fruit name is required.';
        if ($price <= 0)                         $errors[] = 'Price must be greater than 0.';
        if ($discount < 0 || $discount > 100)   $errors[] = 'Discount must be between 0 and 100.';

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'errors' => $errors]);
            break;
        }

        $pdo->prepare("
            UPDATE fruits
            SET name=?, description=?, actual_price=?, discount_percentage=?, category=?, in_stock=?
            WHERE id=?
        ")->execute([$name, $description, $price, $discount, $category, $in_stock, $id]);

        // Upload new images into remaining slots
        if (!empty($_FILES['images']['name'][0])) {
            $existing = countFruitImages($pdo, $id);
            $slots    = MAX_IMAGES - $existing;
            if ($slots > 0) {
                $files    = $_FILES['images'];
                $uploaded = 0;
                for ($i = 0; $i < count($files['name']) && $uploaded < $slots; $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $path = uploadImage([
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ]);
                    if ($path) {
                        $isPrimary = ($existing + $uploaded === 0) ? 1 : 0;
                        $pdo->prepare("
                            INSERT INTO fruit_images (fruit_id, image_path, is_primary, sort_order)
                            VALUES (?, ?, ?, ?)
                        ")->execute([$id, $path, $isPrimary, $existing + $uploaded]);
                        $uploaded++;
                    }
                }
            }
        }

        // Optionally change primary image
        if (!empty($_POST['set_primary'])) {
            $pid = filter_var($_POST['set_primary'], FILTER_VALIDATE_INT);
            if ($pid) setPrimaryImage($pdo, $id, $pid);
        }

        echo json_encode(['success' => true]);
        break;

    // ── Delete fruit ─────────────────────────────────────────────
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo json_encode(['error' => 'Method not allowed']); break;
        }

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT)
           ?: filter_input(INPUT_GET,  'id', FILTER_VALIDATE_INT);
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Invalid ID']); break; }

        $fruit = getFruitById($pdo, $id);
        if (!$fruit) { http_response_code(404); echo json_encode(['error' => 'Fruit not found']); break; }

        foreach (getFruitImages($pdo, $id) as $img) {
            deleteImageFile($img['image_path']);
        }

        $pdo->prepare("DELETE FROM fruits WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'name' => $fruit['name']]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action.']);
}
