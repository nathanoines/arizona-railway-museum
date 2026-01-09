<?php
/**
 * Fallback handler for /admin/membership/edit/ without ID
 * Redirects to members list with an error message
 */

declare(strict_types=1); 

session_start();

$_SESSION['error_message'] = 'Please select a member to edit.';
header('Location: /admin/members/');
exit;