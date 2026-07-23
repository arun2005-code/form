<?php
/**
 * api/auth_guard.php
 * Include this at the top of any admin-only endpoint to require login.
 */
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['admin_id'])) {
    sendJson(['success' => false, 'message' => 'Unauthorized. Please log in.'], 401);
}
