<?php
/**
 * Logout page
 * 
 * This page handles user logout.
 */

require_once '../includes/header.php';

// Log the user out
logoutUser();

// Redirect to login page with message
setFlashMessage('You have been successfully logged out.', 'success');
redirect('/auth/login.php');
?>
