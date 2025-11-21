<?php 
// Logout user
logoutUser();

// Also clear referral code cookie if exists
if (isset($_COOKIE['referral_code'])) {
    setcookie('referral_code', '', time() - 3600, '/');
}

// Redirect to home page
header("Location: " . WEB_URL . "/");
exit;
?>