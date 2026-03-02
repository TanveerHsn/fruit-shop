<?php
/**
 * Setup Script – Run once to initialise the database.
 * Access: http://localhost/fruit-shop/setup.php
 * DELETE this file after running it.
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'fruit_shop');

$errors   = [];
$messages = [];

try {
    // Connect without selecting a DB so we can create it
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    $messages[] = "Database `" . DB_NAME . "` ready.";

    // Admins
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
    $messages[] = "Table `admins` ready.";

    // Fruits
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fruits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            actual_price DECIMAL(10,2) NOT NULL,
            discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            category VARCHAR(50) DEFAULT 'General',
            in_stock TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
    $messages[] = "Table `fruits` ready.";

    // Fruit images
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fruit_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fruit_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (fruit_id) REFERENCES fruits(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");
    $messages[] = "Table `fruit_images` ready.";

    // Default admin – password: admin123
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO admins (username, email, password) VALUES (?, ?, ?)");
    $stmt->execute(['admin', 'admin@fruitshop.com', $hash]);
    $messages[] = "Default admin created (username: <strong>admin</strong> / password: <strong>admin123</strong>).";

    // Create upload directory
    $uploadDir = __DIR__ . '/uploads/fruits/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        $messages[] = "Upload directory created.";
    } else {
        $messages[] = "Upload directory already exists.";
    }

} catch (PDOException $e) {
    $errors[] = "Database error: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fruit Shop Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="card shadow" style="max-width:540px;width:100%">
    <div class="card-body p-4">
        <h3 class="mb-4 text-success"><i class="bi bi-tools"></i> Fruit Shop Setup</h3>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= $err ?></div>
        <?php endforeach; ?>

        <?php foreach ($messages as $msg): ?>
            <div class="alert alert-success py-2"><?= $msg ?></div>
        <?php endforeach; ?>

        <?php if (empty($errors)): ?>
            <div class="alert alert-warning mt-3">
                <strong>Important:</strong> Delete <code>setup.php</code> after setup is complete!
            </div>
            <a href="index.php" class="btn btn-success me-2">Go to Shop</a>
            <a href="admin/login.html" class="btn btn-outline-secondary">Admin Login</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
