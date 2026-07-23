<?php
/**
 * update.php
 * Updates an existing customer. Accepts POST (with _method=PUT from
 * the admin dashboard, since plain HTML forms/fetch with files are
 * simplest as POST). Re-validates key fields server-side.
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    sendJson(['success' => false, 'message' => 'Invalid or expired session token.'], 403);
}

$customerId = sanitize($_POST['customer_id'] ?? '');
if ($customerId === '') {
    sendJson(['success' => false, 'message' => 'Customer ID is required.'], 422);
}

$pdo = getDbConnection();

$check = $pdo->prepare("SELECT id FROM customers WHERE customer_id = :id");
$check->execute([':id' => $customerId]);
if (!$check->fetch()) {
    sendJson(['success' => false, 'message' => 'Customer not found.'], 404);
}

$errors = [];

$email = trim($_POST['email'] ?? '');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
}

$mobile = trim($_POST['mobile_number'] ?? '');
if ($mobile !== '' && !preg_match('/^[6-9]\d{9}$/', $mobile)) {
    $errors['mobile_number'] = 'Enter a valid 10-digit mobile number.';
}

$pincode = trim($_POST['pincode'] ?? '');
if ($pincode !== '' && !preg_match('/^\d{6}$/', $pincode)) {
    $errors['pincode'] = 'Pincode must be exactly 6 digits.';
}

if (!empty($errors)) {
    sendJson(['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

try {
    $fields = [
        'full_name', 'father_name', 'mobile_number', 'whatsapp_number', 'email', 'gender',
        'dob', 'address', 'city', 'district', 'state', 'pincode', 'occupation', 'company_name',
        'annual_income', 'preferred_language', 'customer_category', 'reference_name',
        'reference_mobile', 'source', 'remarks',
    ];

    $setParts = [];
    $params = [':customer_id' => $customerId];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $setParts[] = "{$field} = :{$field}";
            $params[":{$field}"] = sanitize($_POST[$field]);
        }
    }

    // Recalculate age if dob changed
    if (!empty($_POST['dob'])) {
        $dobDate = new DateTime($_POST['dob']);
        $age = (new DateTime('today'))->diff($dobDate)->y;
        $setParts[] = "age = :age";
        $params[':age'] = $age;
    }

    // Optional file re-uploads
    $photoPath = handleFileUpload('photo', UPLOAD_DIR_PHOTO, $customerId);
    if ($photoPath) { $setParts[] = "photo_path = :photo_path"; $params[':photo_path'] = $photoPath; }

    $idProofPath = handleFileUpload('id_proof', UPLOAD_DIR_IDPROOF, $customerId);
    if ($idProofPath) { $setParts[] = "id_proof_path = :id_proof_path"; $params[':id_proof_path'] = $idProofPath; }

    $signaturePath = handleFileUpload('signature', UPLOAD_DIR_SIGNATURE, $customerId);
    if ($signaturePath) { $setParts[] = "signature_path = :signature_path"; $params[':signature_path'] = $signaturePath; }

    if (empty($setParts)) {
        sendJson(['success' => false, 'message' => 'No fields to update.'], 422);
    }

    $sql = "UPDATE customers SET " . implode(', ', $setParts) . " WHERE customer_id = :customer_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    sendJson(['success' => true, 'message' => 'Customer updated successfully.']);
} catch (RuntimeException $e) {
    sendJson(['success' => false, 'message' => $e->getMessage()], 422);
} catch (PDOException $e) {
    error_log('Update failed: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Could not update customer.'], 500);
}
