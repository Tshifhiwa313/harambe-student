<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once 'include/functions.php';
require_once 'include/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? page_title($page_title) : page_title(); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container-fluid">
        <!-- Flash Messages -->
        <?php
        $flash_message = get_flash_message();
        if ($flash_message): 
            $alert_class = 'alert-secondary';
            
            switch ($flash_message['type']) {
                case 'success':
                    $alert_class = 'alert-success';
                    $icon = 'fas fa-check-circle';
                    break;
                case 'error':
                    $alert_class = 'alert-danger';
                    $icon = 'fas fa-exclamation-circle';
                    break;
                case 'warning':
                    $alert_class = 'alert-warning';
                    $icon = 'fas fa-exclamation-triangle';
                    break;
                case 'info':
                    $alert_class = 'alert-info';
                    $icon = 'fas fa-info-circle';
                    break;
                default:
                    $icon = 'fas fa-bell';
            }
        ?>
        <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show mt-3" role="alert">
            <i class="<?php echo $icon; ?> me-2"></i> <?php echo $flash_message['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Main Content Container -->
        <div class="container py-4">
