<?php
require_once 'includes/config.php';
require_once 'includes/authentication.php';

// Log out the user
logoutUser();

// Redirect to the home page
header('Location: index.php');
exit;
?>
