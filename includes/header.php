<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Corrected file paths
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= defined('SITE_NAME') ? SITE_NAME : 'Harambee Student Living' ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/index.php"><?= SITE_NAME ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/index.php"><i class="fas fa-home"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/accommodations.php"><i class="fas fa-building"></i> Accommodations</a>
                    </li>
                    
                    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                        <?php if (function_exists('hasRole') && hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/applications.php"><i class="fas fa-file-alt"></i> Applications</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/leases.php"><i class="fas fa-file-contract"></i> Leases</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/invoices.php"><i class="fas fa-file-invoice-dollar"></i> Invoices</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/maintenance.php"><i class="fas fa-tools"></i> Maintenance</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/notifications.php"><i class="fas fa-bell"></i> Notifications</a>
                            </li>
                        <?php elseif (hasRole(ROLE_STUDENT)): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/applications.php"><i class="fas fa-file-alt"></i> My Applications</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/leases.php"><i class="fas fa-file-contract"></i> My Leases</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/invoices.php"><i class="fas fa-file-invoice-dollar"></i> My Invoices</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/maintenance.php"><i class="fas fa-tools"></i> Maintenance Requests</a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav ms-auto">
                    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user"></i> <?= $_SESSION['username'] ?? 'User' ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/register.php"><i class="fas fa-user-plus"></i> Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <!-- Main content will come here -->
