<?php
// Authentication Helper Functions

// Generate 32-character random auth key
function generateAuthKey() {
    return bin2hex(random_bytes(16)); // 32 karakter hex string
}

// Check if user is logged in via session or cookie
function checkAuth() {
    global $conn;
    
    // Check session first
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
        // Verify session is still valid
        $stmt = $conn->prepare("SELECT id, email, status FROM users WHERE id = ? AND email = ? AND status = 'active'");
        $stmt->bind_param("is", $_SESSION['user_id'], $_SESSION['user_email']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $stmt->close();
            return $user;
        }
        $stmt->close();
    }
    
    // Check cookie if session not found
    if (isset($_COOKIE['auth_key'])) {
        $authKey = $_COOKIE['auth_key'];
        
        $stmt = $conn->prepare("SELECT id, email, status FROM users WHERE auth_key = ? AND status = 'active'");
        $stmt->bind_param("s", $authKey);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Start session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            
            $stmt->close();
            return $user;
        }
        $stmt->close();
        
        // Invalid cookie, delete auth_key cookie
        setcookie('auth_key', '', time() - 3600, '/');
    }
    
    return false;
}

// Login user and set session/cookie
function loginUser($userId, $email, $rememberMe = false) {
    global $conn;
    
    // Generate new auth key
    $authKey = generateAuthKey();
    
    // Update auth_key in database
    $stmt = $conn->prepare("UPDATE users SET auth_key = ? WHERE id = ?");
    $stmt->bind_param("si", $authKey, $userId);
    $stmt->execute();
    $stmt->close();
    
    // Set session
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    
    // Set cookie if "Remember Me" is checked
    if ($rememberMe) {
        // Cookie expires in 30 days
        setcookie('auth_key', $authKey, time() + (30 * 24 * 60 * 60), '/', '', false, true); // httpOnly = true
    }
    
    return true;
}

// Logout user
function logoutUser() {
    global $conn;
    
    if (isset($_SESSION['user_id'])) {
        // Clear auth_key from database
        $stmt = $conn->prepare("UPDATE users SET auth_key = NULL WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    // Unset session variables
    $_SESSION = array();
    
    // Destroy session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy session
    session_destroy();
    
    // Delete auth cookie
    setcookie('auth_key', '', time() - 3600, '/');
}

// Get current user
function getCurrentUser() {
    global $conn;
    
    $user = checkAuth();
    if ($user) {
        // Get full user data
        $stmt = $conn->prepare("SELECT id, email, email_verified, name_surname, phone, account_level, kyc_verified, referral_code, eth_wallet_address, tron_wallet_address, balance, withdrawable_balance, status FROM users WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $fullUser = $result->fetch_assoc();
        $stmt->close();
        return $fullUser;
    }
    
    return false;
}

// Check if user is admin (account_level >= 10)
function isAdmin($userId = null) {
    global $conn;
    
    if ($userId === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $userId = $_SESSION['user_id'];
    }
    
    $stmt = $conn->prepare("SELECT account_level FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stmt->close();
        return isset($user['account_level']) && intval($user['account_level']) >= 10;
    }
    
    $stmt->close();
    return false;
}

// Require admin access - redirect if not admin
function requireAdmin() {
    if (!isset($GLOBALS['currentUser']) || !$GLOBALS['currentUser']) {
        header("Location: " . WEB_URL . "/login");
        exit;
    }
    
    if (!isAdmin($GLOBALS['currentUser']['id'])) {
        header("Location: " . WEB_URL . "/dashboard");
        exit;
    }
}
?>

