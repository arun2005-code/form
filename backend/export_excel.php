<?php
/**
 * api/export_excel.php
 * Streams all customers as a CSV file that opens directly in Excel.
 * (No external library needed — CSV is the most portable "Excel export".)
 */
require_once __DIR__ . '/auth_guard.php';

$pdo = getDbConnection();
$rows = $pdo->query("SELECT customer_id, full_name, father_name, mobile_number, whatsapp_number,
                             email, gender, dob, age, address, city, district, state, pincode,
                             occupation, company_name, annual_income, aadhar_number, pan_number,
                             gst_number, source, created_at
                      FROM customers ORDER BY created_at DESC")->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="customers_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

if (!empty($rows)) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
} else {
    fputcsv($out, ['No customers found']);
}

fclose($out);
exit;
