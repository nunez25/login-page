<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

$currentUser = requireRole('Super Admin');
$pageTitle = 'Super Admin dashboard';
$dashboardTitle = 'Super Admin dashboard';
$dashboardDescription = 'Manage Admin and User accounts. Super Admin accounts are protected from account-management changes.';
$dashboardActions = [
    ['href' => '/admin/manage_users.php', 'label' => 'Manage Admins and Users'],
    ['href' => '/admin/edit_user.php?id=' . rawurlencode((string) $currentUser['id']), 'label' => 'My account'],
];
require __DIR__ . '/../includes/role_dashboard.php';
