<?php
/**
 * api/login.php
 * Simple session-based admin login for the dashboard.
 */
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$username = sanitize($input['username'] ?? '');
$password = $input['password'] ?? '';

if ($username === '' || $password === '') {
    sendJson(['success' => false, 'message' => 'Username and password are required.'], 422);
}

$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT id, username, password_hash FROM admins WHERE username = :u LIMIT 1");
$stmt->execute([':u' => $username]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    sendJson(['success' => false, 'message' => 'Invalid username or password.'], 401);
}

$_SESSION['admin_id'] = $admin['id'];
$_SESSION['admin_username'] = $admin['username'];

sendJson(['success' => true, 'message' => 'Login successful.', 'username' => $admin['username']]);
