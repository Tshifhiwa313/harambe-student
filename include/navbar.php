<?php
// Get current user data
$current_user = get_current_user();
$unread_notifications = 0;

// Get unread notifications count if user is logged in
if (is_logged_in()) {
    $notifications = get_user_notifications($current_user['id'], true);
    $unread_notifications = count($notifications);
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-building me-2"></i>
            <?php echo SITE_NAME; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo is_page_active('index.php') ? 'active' : ''; ?>" href="index.php">
                        <i class="fas fa-home me-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo is_page_active('accommodations.php') ? 'active' : ''; ?>" href="accommodations.php">
                        <i class="fas fa-building me-1"></i> Accommodations
                    </a>
                </li>
                
                <?php if (is_logged_in()): ?>
                    <?php if (is_student()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_page_active('applications.php') ? 'active' : ''; ?>" href="applications.php">
                                <i class="fas fa-file-alt me-1"></i> My Applications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_page_active('leases.php') ? 'active' : ''; ?>" href="leases.php">
                                <i class="fas fa-file-contract me-1"></i> My Leases
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_page_active('invoices.php') ? 'active' : ''; ?>" href="invoices.php">
                                <i class="fas fa-file-invoice-dollar me-1"></i> My Invoices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_page_active('maintenance.php') ? 'active' : ''; ?>" href="maintenance.php">
                                <i class="fas fa-tools me-1"></i> Maintenance
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (is_admin()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarAdminDropdown" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-cogs me-1"></i> Management
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarAdminDropdown">
                                <?php if (is_master_admin()): ?>
                                    <li>
                                        <a class="dropdown-item" href="master_admin.php">
                                            <i class="fas fa-user-shield me-1"></i> Master Admin Panel
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li>
                                    <a class="dropdown-item" href="admin.php">
                                        <i class="fas fa-tachometer-alt me-1"></i> Admin Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="applications.php">
                                        <i class="fas fa-file-alt me-1"></i> Applications
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="leases.php">
                                        <i class="fas fa-file-contract me-1"></i> Leases
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="invoices.php">
                                        <i class="fas fa-file-invoice-dollar me-1"></i> Invoices
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="maintenance.php">
                                        <i class="fas fa-tools me-1"></i> Maintenance
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="notifications.php">
                                        <i class="fas fa-bell me-1"></i> Notifications
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a class="nav-link" href="about.php">
                        <i class="fas fa-info-circle me-1"></i> About
                    </a>
                </li>
            </ul>
            
            <ul class="navbar-nav ms-auto">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="notifications.php">
                            <i class="fas fa-bell me-1"></i> Notifications
                            <?php if ($unread_notifications > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $unread_notifications; ?>
                                    <span class="visually-hidden">unread notifications</span>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarUserDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> 
                            <?php echo $current_user['full_name']; ?>
                            <span class="badge bg-secondary ms-1"><?php echo format_role($current_user['role']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                            <li>
                                <a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-id-card me-1"></i> My Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo is_page_active('login.php') ? 'active' : ''; ?>" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo is_page_active('register.php') ? 'active' : ''; ?>" href="register.php">
                            <i class="fas fa-user-plus me-1"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
