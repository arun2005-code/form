<?php
/**
 * ============================================================
 * Smart Customer Registration System - Configuration
 * ============================================================
 * Central place for DB connection, session, CORS and helpers.
 * Every other backend script includes this file first.
 */

// ---- Error display: OFF in production, logged instead ----
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ---- Session (needed for CSRF token + admin login) ----
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Database credentials (edit these for your server) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_customer_registration');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Upload settings ----
define('UPLOAD_DIR_PHOTO', __DIR__ . '/uploads/photos/');
define('UPLOAD_DIR_IDPROOF', __DIR__ . '/uploads/idproof/');
define('UPLOAD_DIR_SIGNATURE', __DIR__ . '/uploads/signature/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);

// ---- CORS (adjust origin for your deployment) ----
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Get a PDO connection using prepared-statement-friendly defaults.
 */
function getDbConnection(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
            ]);
        } catch (PDOException $e) {
            error_log('DB Connection failed: ' . $e->getMessage());
            sendJson(['success' => false, 'message' => 'Database connection failed.'], 500);
        }
    }
    return $pdo;
}

/**
 * Send a JSON response and stop execution.
 */
function sendJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sanitize a plain text input (trim + strip tags + convert special chars).
 * Prepared statements already stop SQL injection; this stops stored XSS.
 */
function sanitize(?string $value): string
{
    if ($value === null) return '';
    $value = trim($value);
    $value = strip_tags($value);
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate/validate a CSRF token stored in the session.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate the next sequential customer id, e.g. CUS0001, CUS0002...
 * Uses a small helper table + LAST_INSERT_ID trick so concurrent
 * requests never collide.
 */
function generateCustomerId(PDO $pdo): string
{
    $pdo->exec("INSERT INTO customer_id_sequence (tag) VALUES ('x')");
    $nextId = (int) $pdo->lastInsertId();
    return 'CUS' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
}

/**
 * Handle a single file upload (photo / id proof / signature).
 * Returns the relative path stored in DB, or null if no file sent.
 * Throws RuntimeException with a user-facing message on failure.
 */
function handleFileUpload(string $fieldName, string $targetDir, string $customerId): ?string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Upload error for {$fieldName}.");
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException(ucfirst($fieldName) . ' must be under 2MB.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        throw new RuntimeException(ucfirst($fieldName) . ' must be JPG, JPEG or PNG.');
    }

    $ext = $mime === 'image/png' ? 'png' : 'jpg';
    $fileName = $customerId . '_' . $fieldName . '_' . time() . '.' . $ext;
    $destination = $targetDir . $fileName;

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException("Could not save {$fieldName}.");
    }

    return $fileName;
}
