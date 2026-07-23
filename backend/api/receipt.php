<?php
/**
 * api/receipt.php?id=CUS0001
 * Printable registration receipt. QR code is generated via a public
 * QR image API (encodes the Customer ID) — swap for a local QR
 * library if the server has no internet access.
 */
require_once __DIR__ . '/../config.php';

$customerId = sanitize($_GET['id'] ?? '');
if ($customerId === '') {
    http_response_code(400);
    echo 'Missing customer id.';
    exit;
}

$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = :id LIMIT 1");
$stmt->execute([':id' => $customerId]);
$c = $stmt->fetch();

if (!$c) {
    http_response_code(404);
    echo 'Customer not found.';
    exit;
}

$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=' . urlencode($c['customer_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Registration Receipt - <?= htmlspecialchars($c['customer_id']) ?></title>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; padding: 32px; color: #111827; }
  .receipt { max-width: 560px; margin: auto; border: 2px solid #0F172A; border-radius: 12px; padding: 24px; }
  .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px dashed #cbd5e1; padding-bottom: 16px; }
  h1 { font-size: 20px; color: #0F172A; margin: 0; }
  .row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
  .label { color: #6b7280; }
  .value { font-weight: 600; }
  .badge { background: #22C55E; color: #fff; padding: 4px 10px; border-radius: 999px; font-size: 12px; }
  @media print { .no-print { display: none; } }
</style>
</head>
<body>
  <button class="no-print" onclick="window.print()">Print / Download PDF</button>
  <div class="receipt">
    <div class="header">
      <div>
        <h1>Smart Customer Registration</h1>
        <small>Registration Receipt</small>
      </div>
      <img src="<?= $qrUrl ?>" alt="QR code for <?= htmlspecialchars($c['customer_id']) ?>">
    </div>
    <div class="row"><span class="label">Customer ID</span><span class="value"><?= htmlspecialchars($c['customer_id']) ?></span></div>
    <div class="row"><span class="label">Registration No.</span><span class="value"><?= htmlspecialchars($c['registration_no']) ?></span></div>
    <div class="row"><span class="label">Full Name</span><span class="value"><?= htmlspecialchars($c['full_name']) ?></span></div>
    <div class="row"><span class="label">Mobile</span><span class="value"><?= htmlspecialchars($c['mobile_number']) ?></span></div>
    <div class="row"><span class="label">Email</span><span class="value"><?= htmlspecialchars($c['email']) ?></span></div>
    <div class="row"><span class="label">City / District</span><span class="value"><?= htmlspecialchars($c['city']) ?>, <?= htmlspecialchars($c['district']) ?></span></div>
    <div class="row"><span class="label">Registered On</span><span class="value"><?= htmlspecialchars($c['created_at']) ?></span></div>
    <div class="row"><span class="label">Status</span><span class="badge">Confirmed</span></div>
  </div>
</body>
</html>
