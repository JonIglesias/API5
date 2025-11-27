<?php
/**
 * Admin Logout
 * 
 * @version 4.0
 */

define('API_ACCESS', true);
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Auth.php';

Auth::logout();

header('Location: login.php');
exit;
