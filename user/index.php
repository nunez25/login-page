<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

$currentUser = requireRole('User');
$pageTitle = 'User dashboard';
$dashboardTitle = 'User dashboard';
$dashboardDescription = 'You are signed in with a standard User account. Account administration is not available in this portal.';
$dashboardActions = [];
require __DIR__ . '/../includes/role_dashboard.php';
