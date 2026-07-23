<?php
/**
 * api/export_pdf.php
 * Renders a print-ready HTML report of all customers. The admin
 * dashboard opens this in a new tab and calls window.print(), which
 * lets the browser "Save as PDF" — no extra PHP PDF library required.
 */
require_once __DIR__ . '/auth_guard.php';

$pdo = getDbConnection();
$rows = $pdo->query("SELECT customer_id, full_name, mobile_number, email, gender, city, district,
                             occupation, created_at
                      FROM customers ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customer Report - Smart Customer Registration System</title>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; padding: 24px; color: #111827; }
  h1 { color: #0F172A; margin-bottom: 4px; }
  p.meta { color: #6b7280; margin-top: 0; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; }
  th, td { border: 1px solid #e5e7eb; padding: 8px 10px; font-size: 13px; text-align: left; }
  th { background: #0F172A; color: #fff; }
  tr:nth-child(even) { background: #f8fafc; }
  @media print { .no-print { display: none; } }
</style>
</head>
<body>
  <button class="no-print" onclick="window.print()">Print / Save as PDF</button>
  <h1>Customer Report</h1>
  <p class="meta">Generated on <?= date('d-M-Y H:i') ?> &middot; Total records: <?= count($rows) ?></p>
  <table>
    <thead>
      <tr>
        <th>Customer ID</th><th>Name</th><th>Mobile</th><th>Email</th>
        <th>Gender</th><th>City</th><th>District</th><th>Occupation</th><th>Registered</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['customer_id']) ?></td>
        <td><?= htmlspecialchars($r['full_name']) ?></td>
        <td><?= htmlspecialchars($r['mobile_number']) ?></td>
        <td><?= htmlspecialchars($r['email']) ?></td>
        <td><?= htmlspecialchars($r['gender']) ?></td>
        <td><?= htmlspecialchars($r['city']) ?></td>
        <td><?= htmlspecialchars($r['district']) ?></td>
        <td><?= htmlspecialchars($r['occupation']) ?></td>
        <td><?= htmlspecialchars($r['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <script>window.onload = () => setTimeout(() => window.print(), 400);</script>
</body>
</html>
