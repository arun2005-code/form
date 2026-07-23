<?php
/**
 * api/csrf.php
 * Returns a fresh CSRF token the frontend embeds in the registration form.
 */
require_once __DIR__ . '/../config.php';

sendJson(['success' => true, 'csrf_token' => csrfToken()]);
