<?php
/**
 * fetch.php
 * GET endpoint used by the admin dashboard to list, search, filter,
 * sort and paginate customers, plus return summary stats.
 *
 * Query params:
 *   action=stats                -> dashboard summary cards
 *   action=list (default)       -> paginated customer list
 *     search, district, city, gender, occupation
 *     sort_by (created_at|full_name|customer_id), sort_dir (asc|desc)
 *     page, per_page
 *   action=single&id=CUS0001    -> one customer's full record
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJson(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$pdo = getDbConnection();
$action = $_GET['action'] ?? 'list';

if ($action === 'stats') {
    $total = (int) $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();

    $today = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE DATE(created_at) = CURDATE()");
    $today->execute();
    $todayCount = (int) $today->fetchColumn();

    $genderStmt = $pdo->query("SELECT gender, COUNT(*) as cnt FROM customers GROUP BY gender");
    $genderCounts = ['Male' => 0, 'Female' => 0, 'Other' => 0];
    foreach ($genderStmt as $row) {
        $genderCounts[$row['gender']] = (int) $row['cnt'];
    }

    sendJson([
        'success' => true,
        'total_customers' => $total,
        'today_registrations' => $todayCount,
        'male' => $genderCounts['Male'],
        'female' => $genderCounts['Female'],
        'others' => $genderCounts['Other'],
    ]);
}

if ($action === 'single') {
    $id = sanitize($_GET['id'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        sendJson(['success' => false, 'message' => 'Customer not found.'], 404);
    }
    sendJson(['success' => true, 'customer' => $customer]);
}

// ---- action=list (default) ----
$search = sanitize($_GET['search'] ?? '');
$district = sanitize($_GET['district'] ?? '');
$city = sanitize($_GET['city'] ?? '');
$gender = sanitize($_GET['gender'] ?? '');
$occupation = sanitize($_GET['occupation'] ?? '');

$allowedSort = ['created_at', 'full_name', 'customer_id'];
$sortBy = in_array($_GET['sort_by'] ?? '', $allowedSort, true) ? $_GET['sort_by'] : 'created_at';
$sortDir = strtolower($_GET['sort_dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(100, max(5, (int) ($_GET['per_page'] ?? 10)));
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(full_name LIKE :search OR customer_id LIKE :search OR mobile_number LIKE :search OR email LIKE :search)";
    $params[':search'] = "%{$search}%";
}
if ($district !== '') { $where[] = "district = :district"; $params[':district'] = $district; }
if ($city !== '') { $where[] = "city = :city"; $params[':city'] = $city; }
if ($gender !== '') { $where[] = "gender = :gender"; $params[':gender'] = $gender; }
if ($occupation !== '') { $where[] = "occupation = :occupation"; $params[':occupation'] = $occupation; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// total count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers {$whereSql}");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$sql = "SELECT customer_id, full_name, mobile_number, email, gender, city, district,
               occupation, photo_path, created_at
        FROM customers
        {$whereSql}
        ORDER BY {$sortBy} {$sortDir}
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll();

sendJson([
    'success' => true,
    'data' => $customers,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total_rows' => $totalRows,
        'total_pages' => (int) ceil($totalRows / $perPage),
    ],
]);
