<?php
// Email Helper Functions using SendGrid

// Load SendGrid if available
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

/**
 * Send email using SendGrid
 */
if (!function_exists('sendEmail')) {
function sendEmail($to, $subject, $htmlContent, $textContent = null) {
    // global $conn; // Not needed for sending email
    
    // Get SendGrid API key from environment or config
    $sendgridApiKey = getenv('SENDGRID_API_KEY');
    if (empty($sendgridApiKey)) {
        // Try to get from config file or database
        $sendgridApiKey = defined('SENDGRID_API_KEY') ? SENDGRID_API_KEY : '';
    }
    
    if (empty($sendgridApiKey)) {
        error_log("SendGrid API key not configured - Check .env file or SENDGRID_API_KEY constant");
        error_log("Environment check: getenv('SENDGRID_API_KEY') = " . (getenv('SENDGRID_API_KEY') ? 'exists' : 'empty'));
        error_log("Constant check: SENDGRID_API_KEY = " . (defined('SENDGRID_API_KEY') ? SENDGRID_API_KEY : 'not defined'));
        return false;
    }
    
    // Get sender email from config
    $fromEmail = getenv('SENDGRID_FROM_EMAIL') ?: (defined('SENDGRID_FROM_EMAIL') ? SENDGRID_FROM_EMAIL : 'noreply@copystar.net');
    $fromName = getenv('SENDGRID_FROM_NAME') ?: (defined('SENDGRID_FROM_NAME') ? SENDGRID_FROM_NAME : 'CopyStar');
    
    // Check if SendGrid is available
    if (!class_exists('\SendGrid\Mail\Mail')) {
        error_log("SendGrid library not installed. Run: composer install");
        error_log("Vendor path: " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'exists' : 'not found'));
        return false;
    }
    
    try {
        $email = new \SendGrid\Mail\Mail();
        $email->setFrom($fromEmail, $fromName);
        $email->setSubject($subject);
        $email->addTo($to);
        
        if ($textContent) {
            $email->addContent("text/plain", $textContent);
        }
        $email->addContent("text/html", $htmlContent);
        
        $sendgrid = new \SendGrid($sendgridApiKey);
        $response = $sendgrid->send($email);
        
        $statusCode = $response->statusCode();
        $responseBody = $response->body();
        
        if ($statusCode >= 200 && $statusCode < 300) {
            error_log("SendGrid success: Email sent to " . $to . " (Status: " . $statusCode . ")");
            return true;
        } else {
            error_log("SendGrid error: Status " . $statusCode . " - " . $responseBody);
            error_log("SendGrid error details: To=" . $to . ", From=" . $fromEmail);
            return false;
        }
    } catch (Exception $e) {
        error_log("SendGrid exception: " . $e->getMessage());
        error_log("SendGrid exception trace: " . $e->getTraceAsString());
        return false;
    }
}
}

/**
 * Send email verification email
 */
if (!function_exists('sendVerificationEmail')) {
function sendVerificationEmail($userId, $email, $verificationToken) {
    global $conn;
    
    $verificationUrl = WEB_URL . "/verify-email?token=" . urlencode($verificationToken) . "&email=" . urlencode($email);
    
    $subject = "Email Doğrulama - CopyStar";
    
    $htmlContent = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Doğrulama</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
            <h1 style="color: white; margin: 0;">CopyStar</h1>
        </div>
        <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e0e0e0;">
            <h2 style="color: #333; margin-top: 0;">Email Adresinizi Doğrulayın</h2>
            <p>Merhaba,</p>
            <p>CopyStar hesabınızı oluşturduğunuz için teşekkür ederiz. Email adresinizi doğrulamak için aşağıdaki butona tıklayın:</p>
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . htmlspecialchars($verificationUrl) . '" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">Email Adresimi Doğrula</a>
            </div>
            <p>Veya aşağıdaki linki tarayıcınıza kopyalayıp yapıştırabilirsiniz:</p>
            <p style="word-break: break-all; color: #667eea;">' . htmlspecialchars($verificationUrl) . '</p>
            <p style="color: #666; font-size: 14px; margin-top: 30px;">Bu link 24 saat geçerlidir.</p>
            <p style="color: #666; font-size: 14px;">Eğer bu hesabı siz oluşturmadıysanız, bu emaili görmezden gelebilirsiniz.</p>
        </div>
        <div style="text-align: center; margin-top: 20px; color: #999; font-size: 12px;">
            <p>&copy; ' . date('Y') . ' CopyStar. Tüm hakları saklıdır.</p>
        </div>
    </body>
    </html>';
    
    $textContent = "Email Adresinizi Doğrulayın\n\n";
    $textContent .= "CopyStar hesabınızı oluşturduğunuz için teşekkür ederiz.\n\n";
    $textContent .= "Email adresinizi doğrulamak için aşağıdaki linke tıklayın:\n";
    $textContent .= $verificationUrl . "\n\n";
    $textContent .= "Bu link 24 saat geçerlidir.\n\n";
    $textContent .= "Eğer bu hesabı siz oluşturmadıysanız, bu emaili görmezden gelebilirsiniz.\n";
    
    return sendEmail($email, $subject, $htmlContent, $textContent);
}
}

/**
 * Generate email verification token
 */
if (!function_exists('generateVerificationToken')) {
function generateVerificationToken($userId, $email) {
    global $conn;
    
    // Generate unique token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Check if email_verification_tokens table exists, if not create it
    $checkTable = $conn->query("SHOW TABLES LIKE 'email_verification_tokens'");
    if ($checkTable->num_rows == 0) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `email` varchar(255) NOT NULL,
                `token` varchar(64) NOT NULL,
                `expires_at` datetime NOT NULL,
                `used` tinyint(1) DEFAULT 0,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `token` (`token`),
                KEY `user_id` (`user_id`),
                KEY `email` (`email`),
                KEY `expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    // Delete old tokens for this user
    $deleteStmt = $conn->prepare("DELETE FROM email_verification_tokens WHERE user_id = ? AND used = 0");
    $deleteStmt->bind_param("i", $userId);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Insert new token
    $stmt = $conn->prepare("INSERT INTO email_verification_tokens (user_id, email, token, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $email, $token, $expiresAt);
    $stmt->execute();
    $stmt->close();
    
    return $token;
}
}

/**
 * Verify email token
 */
if (!function_exists('verifyEmailToken')) {
function verifyEmailToken($token, $email) {
    global $conn;
    
    // Check if email_verification_tokens table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'email_verification_tokens'");
    if ($checkTable->num_rows == 0) {
        return ['success' => false, 'message' => 'Verification system not initialized'];
    }
    
    // Get token
    $stmt = $conn->prepare("SELECT id, user_id, email, expires_at, used FROM email_verification_tokens WHERE token = ? AND email = ?");
    $stmt->bind_param("ss", $token, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'Invalid verification token'];
    }
    
    $tokenData = $result->fetch_assoc();
    $stmt->close();
    
    // Check if already used
    if ($tokenData['used'] == 1) {
        return ['success' => false, 'message' => 'This verification link has already been used'];
    }
    
    // Check if expired
    if (strtotime($tokenData['expires_at']) < time()) {
        return ['success' => false, 'message' => 'Verification link has expired'];
    }
    
    // Mark token as used
    $updateStmt = $conn->prepare("UPDATE email_verification_tokens SET used = 1 WHERE id = ?");
    $updateStmt->bind_param("i", $tokenData['id']);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Update user email_verified status
    $userStmt = $conn->prepare("UPDATE users SET email_verified = 1 WHERE id = ? AND email = ?");
    $userStmt->bind_param("is", $tokenData['user_id'], $email);
    $userStmt->execute();
    $userStmt->close();
    
    return ['success' => true, 'message' => 'Email verified successfully', 'user_id' => $tokenData['user_id']];
}
}

/**
 * Generate 6-digit verification code
 */
if (!function_exists('generateVerificationCode')) {
function generateVerificationCode($email) {
    global $conn;
    
    // Generate 6-digit code
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes')); // 10 dakika geçerli
    
    // Check if email_verification_tokens table exists, if not create it
    $checkTable = $conn->query("SHOW TABLES LIKE 'email_verification_tokens'");
    if ($checkTable->num_rows == 0) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) DEFAULT NULL,
                `email` varchar(255) NOT NULL,
                `token` varchar(64) NOT NULL,
                `expires_at` datetime NOT NULL,
                `used` tinyint(1) DEFAULT 0,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `token` (`token`),
                KEY `user_id` (`user_id`),
                KEY `email` (`email`),
                KEY `expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } else {
        // Check if user_id column allows NULL, if not alter it
        $columnCheck = $conn->query("SHOW COLUMNS FROM email_verification_tokens WHERE Field = 'user_id'");
        if ($columnCheck->num_rows > 0) {
            $column = $columnCheck->fetch_assoc();
            if ($column['Null'] === 'NO') {
                // Alter column to allow NULL
                $conn->query("ALTER TABLE email_verification_tokens MODIFY COLUMN user_id int(11) DEFAULT NULL");
            }
        }
    }
    
    // Delete old unused codes for this email
    $deleteStmt = $conn->prepare("DELETE FROM email_verification_tokens WHERE email = ? AND used = 0 AND user_id IS NULL");
    $deleteStmt->bind_param("s", $email);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Insert new code (token field stores the code)
    $stmt = $conn->prepare("INSERT INTO email_verification_tokens (email, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $code, $expiresAt);
    $stmt->execute();
    $stmt->close();
    
    return $code;
}
}

/**
 * Send verification code email
 */
if (!function_exists('sendVerificationCode')) {
function sendVerificationCode($email) {
    try {
        $code = generateVerificationCode($email);
        
        if (!$code) {
            error_log("Failed to generate verification code for: " . $email);
            return false;
        }
        
        $subject = "Kayıt Doğrulama Kodu - CopyStar";
        
        $htmlContent = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Doğrulama Kodu</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: white; margin: 0;">CopyStar</h1>
            </div>
            <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e0e0e0;">
                <h2 style="color: #333; margin-top: 0;">Kayıt Doğrulama Kodu</h2>
                <p>Merhaba,</p>
                <p>CopyStar hesabınızı oluşturmak için aşağıdaki doğrulama kodunu kullanın:</p>
                <div style="text-align: center; margin: 30px 0;">
                    <div style="background: #fff; border: 2px solid #667eea; border-radius: 10px; padding: 20px; display: inline-block;">
                        <h1 style="color: #667eea; font-size: 48px; letter-spacing: 10px; margin: 0; font-family: monospace;">' . htmlspecialchars($code) . '</h1>
                    </div>
                </div>
                <p style="color: #666; font-size: 14px; margin-top: 30px;">Bu kod 10 dakika geçerlidir.</p>
                <p style="color: #666; font-size: 14px;">Eğer bu kodu siz talep etmediyseniz, bu emaili görmezden gelebilirsiniz.</p>
            </div>
            <div style="text-align: center; margin-top: 20px; color: #999; font-size: 12px;">
                <p>&copy; ' . date('Y') . ' CopyStar. Tüm hakları saklıdır.</p>
            </div>
        </body>
        </html>';
        
        $textContent = "Kayıt Doğrulama Kodu\n\n";
        $textContent .= "CopyStar hesabınızı oluşturmak için aşağıdaki doğrulama kodunu kullanın:\n\n";
        $textContent .= "Kod: " . $code . "\n\n";
        $textContent .= "Bu kod 10 dakika geçerlidir.\n\n";
        $textContent .= "Eğer bu kodu siz talep etmediyseniz, bu emaili görmezden gelebilirsiniz.\n";
        
        $result = sendEmail($email, $subject, $htmlContent, $textContent);
        
        if (!$result) {
            error_log("Failed to send verification code email to: " . $email);
        } else {
            error_log("Verification code sent successfully to: " . $email . " (Code: " . $code . ")");
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Exception in sendVerificationCode: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Verify registration code
 */
if (!function_exists('verifyRegistrationCode')) {
function verifyRegistrationCode($email, $code) {
    global $conn;
    
    // Check if email_verification_tokens table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'email_verification_tokens'");
    if ($checkTable->num_rows == 0) {
        return ['success' => false, 'message' => 'Verification system not initialized'];
    }
    
    // Get code
    $stmt = $conn->prepare("SELECT id, email, expires_at, used FROM email_verification_tokens WHERE email = ? AND token = ? AND user_id IS NULL");
    $stmt->bind_param("ss", $email, $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'Geçersiz doğrulama kodu'];
    }
    
    $codeData = $result->fetch_assoc();
    $stmt->close();
    
    // Check if already used
    if ($codeData['used'] == 1) {
        return ['success' => false, 'message' => 'Bu kod daha önce kullanılmış'];
    }
    
    // Check if expired
    if (strtotime($codeData['expires_at']) < time()) {
        return ['success' => false, 'message' => 'Doğrulama kodu süresi dolmuş'];
    }
    
    // Mark code as used
    $updateStmt = $conn->prepare("UPDATE email_verification_tokens SET used = 1 WHERE id = ?");
    $updateStmt->bind_param("i", $codeData['id']);
    $updateStmt->execute();
    $updateStmt->close();
    
    return ['success' => true, 'message' => 'Kod doğrulandı'];
}
}
