<?php
require_once __DIR__ . '/../includes/auth.php';

logout_user();

// Need to start a fresh session to set the flash message after destroying
session_start();
set_flash('info', 'You have been signed out.');

header('Location: ' . BASE_PATH . '/index.php');
exit;