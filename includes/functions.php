<?php

const MAX_IMAGES       = 5;
const UPLOAD_DIR       = __DIR__ . '/../uploads/fruits/';
const ALLOWED_TYPES    = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const ALLOWED_EXT      = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const MAX_FILE_SIZE    = 5 * 1024 * 1024; // 5 MB

/**
 * Calculate price after applying discount.
 */
function discountedPrice(float $price, float $discount): float
{
    return round($price - ($price * $discount / 100), 2);
}

/**
 * Format a number as a currency string.
 */
function formatPrice(float $amount): string
{
    return '$' . number_format($amount, 2);
}

/**
 * Safely escape output.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Upload a single image file, returning the relative path or false on failure.
 */
function uploadImage(array $file): string|false
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_TYPES, true)) {
        return false;
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        return false;
    }

    $filename = uniqid('fruit_', true) . '.' . $ext;
    $destPath = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return false;
    }

    return 'uploads/fruits/' . $filename;
}

/**
 * Delete an image file from disk.
 */
function deleteImageFile(string $relativePath): void
{
    $fullPath = __DIR__ . '/../' . $relativePath;
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
}

/**
 * Get all fruits with their primary image.
 */
function getAllFruits(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT f.*,
               fi.image_path AS primary_image
        FROM   fruits f
        LEFT JOIN fruit_images fi
               ON fi.fruit_id = f.id AND fi.is_primary = 1
        ORDER  BY f.created_at DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Get a single fruit by ID.
 */
function getFruitById(PDO $pdo, int $id): array|false
{
    $stmt = $pdo->prepare("SELECT * FROM fruits WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Get all images for a fruit, primary first.
 */
function getFruitImages(PDO $pdo, int $fruitId): array
{
    $stmt = $pdo->prepare("
        SELECT * FROM fruit_images
        WHERE  fruit_id = ?
        ORDER  BY is_primary DESC, sort_order ASC, id ASC
    ");
    $stmt->execute([$fruitId]);
    return $stmt->fetchAll();
}

/**
 * Count how many images a fruit already has.
 */
function countFruitImages(PDO $pdo, int $fruitId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fruit_images WHERE fruit_id = ?");
    $stmt->execute([$fruitId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Set one image as primary (unsets others first).
 */
function setPrimaryImage(PDO $pdo, int $fruitId, int $imageId): void
{
    $pdo->prepare("UPDATE fruit_images SET is_primary = 0 WHERE fruit_id = ?")->execute([$fruitId]);
    $pdo->prepare("UPDATE fruit_images SET is_primary = 1 WHERE id = ? AND fruit_id = ?")->execute([$imageId, $fruitId]);
}
