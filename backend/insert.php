<?php
/**
 * insert.php
 * Receives the registration form (multipart/form-data), validates,
 * stores uploaded files, and inserts a new customer row.
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// ---- CSRF check ----
$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    sendJson(['success' => false, 'message' => 'Invalid or expired session token. Please reload the page.'], 403);
}

$errors = [];

// ---- Required text fields ----
$required = [
    'full_name' => 'Full Name', 'father_name' => "Father's Name", 'mobile_number' => 'Mobile Number',
    'whatsapp_number' => 'WhatsApp Number', 'email' => 'Email Address', 'gender' => 'Gender',
    'dob' => 'Date of Birth', 'address' => 'Address', 'city' => 'City', 'district' => 'District',
    'state' => 'State', 'pincode' => 'Pincode', 'occupation' => 'Occupation',
    'aadhar_number' => 'Aadhar Number', 'pan_number' => 'PAN Number',
];

foreach ($required as $field => $label) {
    if (empty(trim($_POST[$field] ?? ''))) {
        $errors[$field] = "{$label} is required.";
    }
}

if (empty($_POST['terms']) || $_POST['terms'] !== 'on' && $_POST['terms'] !== '1' && $_POST['terms'] !== 'true') {
    $errors['terms'] = 'You must accept the Terms & Conditions.';
}

// ---- Format validations ----
$email = trim($_POST['email'] ?? '');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
}

$mobile = trim($_POST['mobile_number'] ?? '');
if ($mobile !== '' && !preg_match('/^[6-9]\d{9}$/', $mobile)) {
    $errors['mobile_number'] = 'Enter a valid 10-digit mobile number.';
}

$whatsapp = trim($_POST['whatsapp_number'] ?? '');
if ($whatsapp !== '' && !preg_match('/^[6-9]\d{9}$/', $whatsapp)) {
    $errors['whatsapp_number'] = 'Enter a valid 10-digit WhatsApp number.';
}

$aadhar = trim($_POST['aadhar_number'] ?? '');
if ($aadhar !== '' && !preg_match('/^\d{12}$/', $aadhar)) {
    $errors['aadhar_number'] = 'Aadhar number must be exactly 12 digits.';
}

$pan = strtoupper(trim($_POST['pan_number'] ?? ''));
if ($pan !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan)) {
    $errors['pan_number'] = 'Enter a valid PAN (e.g. ABCDE1234F).';
}

$gst = strtoupper(trim($_POST['gst_number'] ?? ''));
if ($gst !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gst)) {
    $errors['gst_number'] = 'Enter a valid GST number.';
}

$pincode = trim($_POST['pincode'] ?? '');
if ($pincode !== '' && !preg_match('/^\d{6}$/', $pincode)) {
    $errors['pincode'] = 'Pincode must be exactly 6 digits.';
}

// ---- Age validation (must be above 18) ----
$dob = trim($_POST['dob'] ?? '');
$age = null;
if ($dob !== '') {
    try {
        $dobDate = new DateTime($dob);
        $today = new DateTime('today');
        if ($dobDate > $today) {
            $errors['dob'] = 'Date of birth cannot be in the future.';
        } else {
            $age = $today->diff($dobDate)->y;
            if ($age < 18) {
                $errors['dob'] = 'Customer must be above 18 years old.';
            }
        }
    } catch (Exception $e) {
        $errors['dob'] = 'Enter a valid date of birth.';
    }
}

if (!empty($errors)) {
    sendJson(['success' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

$pdo = getDbConnection();

try {
    $pdo->beginTransaction();

    $customerId = generateCustomerId($pdo);

    // ---- File uploads ----
    $photoPath = handleFileUpload('photo', UPLOAD_DIR_PHOTO, $customerId);
    $idProofPath = handleFileUpload('id_proof', UPLOAD_DIR_IDPROOF, $customerId);
    $signaturePath = handleFileUpload('signature', UPLOAD_DIR_SIGNATURE, $customerId);

    $registrationNo = 'REG-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));

    $sql = "INSERT INTO customers (
                customer_id, full_name, father_name, mobile_number, whatsapp_number, email,
                gender, dob, age, address, city, district, state, pincode, occupation,
                company_name, annual_income, preferred_language, customer_category,
                aadhar_number, pan_number, gst_number, photo_path, id_proof_path, signature_path,
                reference_name, reference_mobile, source, remarks, terms_accepted, registration_no
            ) VALUES (
                :customer_id, :full_name, :father_name, :mobile_number, :whatsapp_number, :email,
                :gender, :dob, :age, :address, :city, :district, :state, :pincode, :occupation,
                :company_name, :annual_income, :preferred_language, :customer_category,
                :aadhar_number, :pan_number, :gst_number, :photo_path, :id_proof_path, :signature_path,
                :reference_name, :reference_mobile, :source, :remarks, :terms_accepted, :registration_no
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':customer_id' => $customerId,
        ':full_name' => sanitize($_POST['full_name']),
        ':father_name' => sanitize($_POST['father_name']),
        ':mobile_number' => $mobile,
        ':whatsapp_number' => $whatsapp,
        ':email' => $email,
        ':gender' => sanitize($_POST['gender']),
        ':dob' => $dob,
        ':age' => $age,
        ':address' => sanitize($_POST['address']),
        ':city' => sanitize($_POST['city']),
        ':district' => sanitize($_POST['district']),
        ':state' => sanitize($_POST['state']),
        ':pincode' => $pincode,
        ':occupation' => sanitize($_POST['occupation']),
        ':company_name' => sanitize($_POST['company_name'] ?? ''),
        ':annual_income' => sanitize($_POST['annual_income'] ?? ''),
        ':preferred_language' => sanitize($_POST['preferred_language'] ?? ''),
        ':customer_category' => sanitize($_POST['customer_category'] ?? ''),
        ':aadhar_number' => $aadhar,
        ':pan_number' => $pan,
        ':gst_number' => $gst,
        ':photo_path' => $photoPath,
        ':id_proof_path' => $idProofPath,
        ':signature_path' => $signaturePath,
        ':reference_name' => sanitize($_POST['reference_name'] ?? ''),
        ':reference_mobile' => sanitize($_POST['reference_mobile'] ?? ''),
        ':source' => sanitize($_POST['source'] ?? 'Other'),
        ':remarks' => sanitize($_POST['remarks'] ?? ''),
        ':terms_accepted' => 1,
        ':registration_no' => $registrationNo,
    ]);

    $pdo->commit();

    sendJson([
        'success' => true,
        'message' => 'Customer registered successfully!',
        'customer_id' => $customerId,
        'registration_no' => $registrationNo,
    ]);
} catch (RuntimeException $e) {
    $pdo->rollBack();
    sendJson(['success' => false, 'message' => $e->getMessage()], 422);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Insert failed: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Could not save customer. Please try again.'], 500);
}
