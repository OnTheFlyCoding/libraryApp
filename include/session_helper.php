<?php
/**
 * Session Helper Functions
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */

// NOT In USE but might be helpful later

// Only include session if not already started
if (session_status() == PHP_SESSION_NONE) {
    include_once (realpath(dirname(__FILE__).'/path.php'));
    include_once (ROOT_PATH . '/php/session.php');
}

// Create helper variables for easy access in templates
$is_logged_in = isset($_SESSION['user_name']) && !empty($_SESSION['user_name']);
$user_first_name = isset($_SESSION['user_first']) ? $_SESSION['user_first'] : '';
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';

?>