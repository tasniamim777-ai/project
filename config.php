<?php
// ============================================
// কৃষি মিত্র - Configuration
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'krishi_mitra');
define('SITE_NAME', 'কৃষি মিত্র');
define('BASE_URL', 'http://localhost/krishi-mitra/');
define('UPLOAD_PATH', __DIR__ . '/uploads/');

// Database Connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die(json_encode(['success' => false, 'message' => 'ডেটাবেস সংযোগ ব্যর্থ হয়েছে']));
        }
    }
    return $pdo;
}

session_start();

function isLoggedIn($type = 'buyer') {
    return isset($_SESSION[$type . '_id']);
}

function getCurrentUser($type = 'buyer') {
    if (!isLoggedIn($type)) return null;
    $db = getDB();
    $table = $type === 'admin' ? 'admins' : ($type === 'farmer' ? 'farmers' : 'buyers');
    $stmt = $db->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$_SESSION[$type . '_id']]);
    return $stmt->fetch();
}

function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function generateOrderNumber() {
    return 'KM' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function uploadImage($file, $folder = 'products') {
    $uploadDir = UPLOAD_PATH . $folder . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed)) return null;
    
    $filename = uniqid() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return 'uploads/' . $folder . '/' . $filename;
    }
    return null;
}
?>
