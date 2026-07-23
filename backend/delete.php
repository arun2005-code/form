<?php
/**
 * delete.php
 * Deletes a customer record (and their uploaded files) by customer_id.
 * Expects POST { customer_id, csrf_token } — POST is used instead of
 * DELETE for maximum compatibility with shared hosting.
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$csrf = $input['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    sendJson(['success' => false, 'message' => 'Invalid or expired session token.'], 403);
}

$customerId = sanitize($input['customer_id'] ?? '');
if ($customerId === '') {
    sendJson(['success' => false, 'message' => 'Customer ID is required.'], 422);
}

$pdo = getDbConnection();

$stmt = $pdo->prepare("SELECT photo_path, id_proof_path, signature_path FROM customers WHERE customer_id = :id");
$stmt->execute([':id' => $customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    sendJson(['success' => false, 'message' => 'Customer not found.'], 404);
}

try {
    $del = $pdo->prepare("DELETE FROM customers WHERE customer_id = :id");
    $del->execute([':id' => $customerId]);

    // Best-effort cleanup of uploaded files
    foreach ([
        UPLOAD_DIR_PHOTO . $customer['photo_path'],
        UPLOAD_DIR_IDPROOF . $customer['id_proof_path'],
        UPLOAD_DIR_SIGNATURE . $customer['signature_path'],
    ] as $path) {
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    sendJson(['success' => true, 'message' => 'Customer deleted successfully.']);
} catch (PDOException $e) {
    error_log('Delete failed: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Could not delete customer.'], 500);
}
