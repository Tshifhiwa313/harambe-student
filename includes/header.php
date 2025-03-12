<?php
// Include required files
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for session timeout if user is logged in
if (isLoggedIn() && isSessionExpired()) {
    logoutUser();
    setFlashMessage('Your session has expired. Please log in again.', 'warning');
    redirect('/auth/login.php');
}

// Update last activity time if user is logged in
if (isLoggedIn()) {
    updateLastActivity();
}

// Get the current page name
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/index.php">
                <i class="fas fa-building me-2"></i>
                <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage == 'index.php' ? 'active' : ''); ?>" href="/index.php">
                            <i class="fas fa-home me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage == 'accommodations.php' ? 'active' : ''); ?>" href="/accommodations.php">
                            <i class="fas fa-building me-1"></i> Accommodations
                        </a>
                    </li>
                    
                    <?php if (isLoggedIn() && hasRole(ROLE_STUDENT)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'applications.php' ? 'active' : ''); ?>" href="/student/applications.php">
                                <i class="fas fa-file-alt me-1"></i> My Applications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'leases.php' ? 'active' : ''); ?>" href="/student/leases.php">
                                <i class="fas fa-file-contract me-1"></i> My Leases
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'invoices.php' ? 'active' : ''); ?>" href="/student/invoices.php">
                                <i class="fas fa-file-invoice-dollar me-1"></i> My Invoices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'maintenance.php' ? 'active' : ''); ?>" href="/student/maintenance.php">
                                <i class="fas fa-tools me-1"></i> Maintenance
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (isLoggedIn() && hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-shield me-1"></i> Admin
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                <li>
                                    <a class="dropdown-item" href="/admin/dashboard.php">
                                        <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/admin/accommodations.php">
                                        <i class="fas fa-building me-1"></i> Accommodations
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/admin/applications.php">
                                        <i class="fas fa-file-alt me-1"></i> Applications
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/admin/leases.php">
                                        <i class="fas fa-file-contract me-1"></i> Leases
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/admin/invoices.php">
                                        <i class="fas fa-file-invoice-dollar me-1"></i> Invoices
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/admin/maintenance.php">
                                        <i class="fas fa-tools me-1"></i> Maintenance
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/admin/notifications.php">
                                        <i class="fas fa-bell me-1"></i> Notifications
                                    </a>
                                </li>
                                <?php if (hasRole(ROLE_MASTER_ADMIN)): ?>
                                    <li>
                                        <a class="dropdown-item" href="/admin/users.php">
                                            <i class="fas fa-users me-1"></i> Users
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <?php
                        // Get the count of unread notifications for the user
                        $unreadCount = countUnreadNotifications($conn, getCurrentUserId());
                        ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell me-1"></i>
                                <?php if ($unreadCount > 0): ?>
                                    <span class="badge bg-danger"><?php echo $unreadCount; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" style="width: 300px;">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <?php
                                // Get the latest notifications for the user
                                $notifications = getUserNotifications($conn, getCurrentUserId(), false, 5);
                                
                                if (count($notifications) > 0):
                                    foreach ($notifications as $notification):
                                ?>
                                    <li>
                                        <a class="dropdown-item <?php echo ($notification['is_read'] ? '' : 'fw-bold'); ?>" href="#">
                                            <small class="text-muted"><?php echo formatDate($notification['created_at'], 'd M Y H:i'); ?></small><br>
                                            <?php echo $notification['message']; ?>
                                        </a>
                                    </li>
                                <?php
                                    endforeach;
                                else:
                                ?>
                                    <li><span class="dropdown-item">No notifications</span></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-center" href="#">
                                        <small>Mark all as read</small>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i> <?php echo getCurrentUserName(); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li>
                                    <a class="dropdown-item" href="/auth/profile.php">
                                        <i class="fas fa-id-card me-1"></i> Profile
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="/auth/logout.php">
                                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'login.php' ? 'active' : ''); ?>" href="/auth/login.php">
                                <i class="fas fa-sign-in-alt me-1"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'register.php' ? 'active' : ''); ?>" href="/auth/register.php">
                                <i class="fas fa-user-plus me-1"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container my-4">
        <!-- Flash Messages -->
        <?php displayFlashMessage(); ?>
