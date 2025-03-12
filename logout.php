<?php
require_once 'include/config.php';
require_once 'include/auth.php';

// Log out the user
logout_user();

// Redirect to the login page with a message
set_flash_message('success', 'You have been successfully logged out.');
redirect('login.php');
