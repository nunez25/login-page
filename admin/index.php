<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

$currentUser = requireRole('Admin');
$pageTitle = 'Admin dashboard';
$dashboardTitle = 'Admin dashboard';
$dashboardDescription = 'Manage regular User accounts. Admin permissions do not extend to other administrators.';
$dashboardActions = [
    ['href' => '/admin/manage_users.php', 'label' => 'Manage users'],
    ['href' => '/admin/edit_user.php?id=' . rawurlencode((string) $currentUser['id']), 'label' => 'My account'],
];
require __DIR__ . '/../includes/role_dashboard.php';
