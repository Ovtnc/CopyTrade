<?php
// Handle AJAX requests FIRST - before any HTML output
// Check if it's an AJAX request (either by action parameter or X-Requested-With header)
$isAjaxRequest = isset($_POST['action']) || 
                 (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// Also check for AJAX registration form (has email, password, verificationCode but no action)
$hasEmail = isset($_POST['email']) && !empty($_POST['email']);
$hasPassword = isset($_POST['password']) && !empty($_POST['password']);
$hasVerificationCode = isset($_POST['verificationCode']) && !empty($_POST['verificationCode']);
$isRegistrationAjax = $hasEmail && $hasPassword && $hasVerificationCode && !isset($_POST['action']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($isAjaxRequest || $isRegistrationAjax)) {
    // Clear any previous output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set JSON header
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Check if $conn is available
    if (!isset($conn)) {
        echo json_encode(['success' => false, 'message' => 'Database connection not available']);
        exit;
    }
    
    if ($_POST['action'] === 'send_code') {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Email adresi gerekli']);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Geçersiz email formatı']);
            exit;
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Bu email adresi zaten kayıtlı']);
            exit;
        }
        $stmt->close();
        
        // Send verification code
        require_once(V_PATH . 'email.php');
        $emailSent = sendVerificationCode($email);
        
        if ($emailSent) {
            $message = 'Doğrulama kodu email adresinize gönderildi. Lütfen spam klasörünü de kontrol edin.';
            
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            // Get last error from error log or SendGrid
            $errorMsg = 'Email gönderilemedi. Lütfen tekrar deneyin.';
            
            // Check SendGrid configuration
            $sendgridApiKey = getenv('SENDGRID_API_KEY');
            if (empty($sendgridApiKey)) {
                $errorMsg = 'SendGrid yapılandırması eksik. Lütfen yöneticiye başvurun.';
            }
            
            echo json_encode(['success' => false, 'message' => $errorMsg]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'verify_code') {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $code = isset($_POST['code']) ? trim($_POST['code']) : '';
        
        if (empty($email) || empty($code)) {
            echo json_encode(['success' => false, 'message' => 'Email ve kod gerekli']);
            exit;
        }
        
        require_once(V_PATH . 'email.php');
        $result = verifyRegistrationCode($email, $code);
        
        echo json_encode($result);
        exit;
    }
    
    // Handle AJAX form submission (register form via fetch)
    // Check if it's a registration form submission (has email, password, and verificationCode)
    $hasEmail = isset($_POST['email']) && !empty($_POST['email']);
    $hasPassword = isset($_POST['password']) && !empty($_POST['password']);
    $hasVerificationCode = isset($_POST['verificationCode']) && !empty($_POST['verificationCode']);
    
    if ($hasEmail && $hasPassword && $hasVerificationCode && !isset($_POST['action'])) {
        // This is an AJAX registration request (form submission without action parameter)
        // Process registration and return JSON
        require_once(V_PATH . 'email.php');
        
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $passwordConfirm = isset($_POST['passwordConfirm']) ? $_POST['passwordConfirm'] : '';
        $verificationCode = isset($_POST['verificationCode']) ? trim($_POST['verificationCode']) : '';
        $referralCode = isset($_POST['referralCode']) ? trim($_POST['referralCode']) : '';
        
        $errors = [];
        
        // Validation
        if (empty($email)) {
            $errors['email'] = 'Email adresi gerekli';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Geçersiz email formatı';
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors['email'] = 'Bu email adresi zaten kayıtlı';
            }
            $stmt->close();
        }
        
        if (empty($password)) {
            $errors['password'] = 'Şifre gerekli';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Şifre en az 8 karakter olmalı';
        }
        
        if ($password !== $passwordConfirm) {
            $errors['passwordConfirm'] = 'Şifreler eşleşmiyor';
        }
        
        if (empty($verificationCode)) {
            $errors['verificationCode'] = 'Doğrulama kodu gerekli';
        } else {
            $codeVerification = verifyRegistrationCode($email, $verificationCode);
            if (!$codeVerification['success']) {
                $errors['verificationCode'] = $codeVerification['message'];
            }
        }
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors, 'message' => 'Lütfen formu kontrol edin']);
            exit;
        }
        
        // All validations passed, proceed with registration
        $conn->begin_transaction();
        
        try {
            $encodedPassword = base64_encode($password);
            $authKey = bin2hex(random_bytes(16));
            
            // Generate referral code
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            do {
                $newReferralCode = '';
                for ($i = 0; $i < 10; $i++) {
                    $newReferralCode .= $characters[rand(0, strlen($characters) - 1)];
                }
                $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
                $stmt->bind_param("s", $newReferralCode);
                $stmt->execute();
                $result = $stmt->get_result();
                $exists = $result->num_rows > 0;
                $stmt->close();
            } while ($exists);
            
            // Get referral user if code provided
            $referredBy = null;
            if (!empty($referralCode)) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
                $stmt->bind_param("s", $referralCode);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $referredBy = $result->fetch_assoc()['id'];
                }
                $stmt->close();
            }
            
            // Get available wallets
            $ethWallet = null;
            $tronWallet = null;
            
            $network = 'eth';
            $stmt = $conn->prepare("SELECT id, address FROM wallets WHERE network = ? AND used = 0 LIMIT 1");
            $stmt->bind_param("s", $network);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $ethWallet = $result->fetch_assoc();
            }
            $stmt->close();
            
            $network = 'tron';
            $stmt = $conn->prepare("SELECT id, address FROM wallets WHERE network = ? AND used = 0 LIMIT 1");
            $stmt->bind_param("s", $network);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $tronWallet = $result->fetch_assoc();
            }
            $stmt->close();
            
            if (!$ethWallet || !$tronWallet) {
                throw new Exception('Cüzdan bulunamadı. Lütfen daha sonra tekrar deneyin.');
            }
            
            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (email, email_verified, name_surname, password, account_level, kyc_verified, referral_code, referred_by, auth_key, eth_wallet_address, tron_wallet_address, balance, withdrawable_balance, status) VALUES (?, 1, NULL, ?, 1, 0, ?, ?, ?, ?, ?, 0, 0, 'active')");
            $stmt->bind_param("sssisss", $email, $encodedPassword, $newReferralCode, $referredBy, $authKey, $ethWallet['address'], $tronWallet['address']);
            $stmt->execute();
            $userId = $conn->insert_id;
            $stmt->close();
            
            // Mark wallets as used
            $stmt = $conn->prepare("UPDATE wallets SET used = 1, user_id = ?, assigned_at = NOW() WHERE id IN (?, ?)");
            $stmt->bind_param("iii", $userId, $ethWallet['id'], $tronWallet['id']);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            // Auto-login
            require_once(V_PATH . 'auth.php');
            loginUser($userId, $email, true);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Kayıt başarılı! Yönlendiriliyorsunuz...',
                'redirect' => WEB_URL . '/dashboard'
            ]);
            exit;
            
        } catch (Exception $e) {
            if (isset($conn) && $conn->in_transaction) {
                $conn->rollback();
            }
            error_log("Registration error: " . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Kayıt sırasında bir hata oluştu: ' . $e->getMessage()
            ]);
            exit;
        } catch (Error $e) {
            if (isset($conn) && $conn->in_transaction) {
                $conn->rollback();
            }
            error_log("Registration fatal error: " . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Sunucu hatası oluştu. Lütfen tekrar deneyin.'
            ]);
            exit;
        }
    }
}

// If we reach here and it's a POST request but not handled above, it might be a normal form submission
// But we should still check if it's AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // AJAX request but not handled above - return error
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false, 
        'message' => 'Geçersiz istek'
    ]);
    exit;
}

// Now continue with normal page rendering
?>
    <title>Register - CopyStar</title>
</head>
<body>
<?php
// Redirect to dashboard if user is already logged in
if (isset($currentUser) && $currentUser) {
    header("Location: " . WEB_URL . "/dashboard");
    exit;
}

// Registration processing
$registrationErrors = [];
$registrationSuccess = false;

// Check if $conn is available (should be from index.php)
if (!isset($conn)) {
    die("Database connection not available. Please check your configuration.");
}

// Get referral code from session or cookie (set from ?ref= parameter)
$storedReferralCode = '';
if (isset($_SESSION['referral_code']) && !empty($_SESSION['referral_code'])) {
    $storedReferralCode = trim($_SESSION['referral_code']);
} elseif (isset($_COOKIE['referral_code']) && !empty($_COOKIE['referral_code'])) {
    $storedReferralCode = trim($_COOKIE['referral_code']);
}

// Check if this is a normal form submission (not AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register']) && !$isAjaxRequest) {
    // Get form data
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $passwordConfirm = isset($_POST['passwordConfirm']) ? $_POST['passwordConfirm'] : '';
    $verificationCode = isset($_POST['verificationCode']) ? trim($_POST['verificationCode']) : '';
    // Use stored referral code if available, otherwise use form input
    $referralCode = !empty($storedReferralCode) ? $storedReferralCode : (isset($_POST['referralCode']) ? trim($_POST['referralCode']) : '');
    
    // Validation
    if (empty($email)) {
        $registrationErrors['email'] = 'emailRequired';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registrationErrors['email'] = 'emailInvalidFormat';
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $registrationErrors['email'] = 'emailAlreadyRegistered';
        }
        $stmt->close();
    }
    
    if (empty($password)) {
        $registrationErrors['password'] = 'passwordRequired';
    } elseif (strlen($password) < 8) {
        $registrationErrors['password'] = 'passwordMinLength';
    }
    
    if (empty($passwordConfirm)) {
        $registrationErrors['passwordConfirm'] = 'passwordConfirmRequired';
    } elseif ($password !== $passwordConfirm) {
        $registrationErrors['passwordConfirm'] = 'passwordsNotMatch';
    }
    
    // Verify code
    if (empty($verificationCode)) {
        $registrationErrors['verificationCode'] = 'verificationCodeRequired';
    } else {
        require_once(V_PATH . 'email.php');
        $codeVerification = verifyRegistrationCode($email, $verificationCode);
        if (!$codeVerification['success']) {
            $registrationErrors['verificationCode'] = $codeVerification['message'];
        }
    }
    
    // Check referral code if provided
    $referredBy = null;
    if (!empty($referralCode)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->bind_param("s", $referralCode);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $registrationErrors['referralCode'] = 'referralCodeInvalid';
        } else {
            $referredBy = $result->fetch_assoc()['id'];
        }
        $stmt->close();
    }
    
    // If no errors, proceed with registration
    if (empty($registrationErrors)) {
        // Generate unique referral code
        function generateReferralCode($conn) {
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            do {
                $code = '';
                for ($i = 0; $i < 10; $i++) {
                    $code .= $characters[rand(0, strlen($characters) - 1)];
                }
                $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
                $stmt->bind_param("s", $code);
                $stmt->execute();
                $result = $stmt->get_result();
                $exists = $result->num_rows > 0;
                $stmt->close();
            } while ($exists);
            return $code;
        }
        
        // Get available wallets
        function getAvailableWallet($conn, $network) {
            $stmt = $conn->prepare("SELECT id, address FROM wallets WHERE network = ? AND used = 0 LIMIT 1");
            $stmt->bind_param("s", $network);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $wallet = $result->fetch_assoc();
                $stmt->close();
                return $wallet;
            }
            $stmt->close();
            return null;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Encode password with base64
            $encodedPassword = base64_encode($password);
            
            // Generate referral code
            $newReferralCode = generateReferralCode($conn);
            
            // Get available wallets
            $ethWallet = getAvailableWallet($conn, 'eth');
            $tronWallet = getAvailableWallet($conn, 'tron');
            
            if (!$ethWallet || !$tronWallet) {
                throw new Exception('noAvailableWallets');
            }
            
            // Generate auth key
            $authKey = bin2hex(random_bytes(16)); // 32 karakter
            
            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (email, email_verified, name_surname, password, account_level, kyc_verified, referral_code, referred_by, auth_key, eth_wallet_address, tron_wallet_address, balance, withdrawable_balance, status) VALUES (?, 0, NULL, ?, 1, 0, ?, ?, ?, ?, ?, 0, 0, 'active')");
            $stmt->bind_param("sssisss", $email, $encodedPassword, $newReferralCode, $referredBy, $authKey, $ethWallet['address'], $tronWallet['address']);
            $stmt->execute();
            $userId = $conn->insert_id;
            
            // Clear referral code from session and cookie after successful registration
            if (isset($_SESSION['referral_code'])) {
                unset($_SESSION['referral_code']);
            }
            if (isset($_COOKIE['referral_code'])) {
                setcookie('referral_code', '', time() - 3600, '/');
            }
            $stmt->close();
            
            // Mark wallets as used
            $stmt = $conn->prepare("UPDATE wallets SET used = 1, user_id = ?, assigned_at = NOW() WHERE id IN (?, ?)");
            $stmt->bind_param("iii", $userId, $ethWallet['id'], $tronWallet['id']);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            // Mark email as verified (code was already verified)
            $updateStmt = $conn->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
            $updateStmt->bind_param("i", $userId);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Auto-login user after registration
            require_once(V_PATH . 'auth.php');
            loginUser($userId, $email, true); // Remember me = true
            
            // Success - redirect to dashboard
            header("Location: " . WEB_URL . "/dashboard");
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            // Check if error message is a translation key
            $errorMsg = $e->getMessage();
            if (in_array($errorMsg, ['noAvailableWallets'])) {
                $registrationErrors['general'] = $errorMsg;
            } else {
                $registrationErrors['general'] = 'registrationFailed';
            }
        }
    }
}
?>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= WEB_URL; ?>">
                <img src="vendor/logo.png" alt="CopyStar Logo" class="navbar-logo me-2">
                CopyStar
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item nav-item-mobile me-3">
                        <button class="btn btn-theme-toggle nav-link-mobile" id="themeToggle" title="Toggle Theme">
                            <i class="fas fa-moon me-2" id="themeIcon"></i><span class="nav-text-mobile" data-key="toggleTheme">Toggle Theme</span>
                        </button>
                    </li>
                    <li class="nav-item dropdown nav-item-mobile me-3">
                        <button class="btn btn-lang-dropdown nav-link-mobile dropdown-toggle" type="button" id="langDropdown" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                            <i class="fas fa-language me-2"></i><span class="nav-text-mobile" id="currentLangText">TR</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="langDropdown">
                            <li><a class="dropdown-item lang-option" href="#" data-lang="tr"><span class="flag-emoji me-2">🇹🇷</span>Türkçe</a></li>
                            <li><a class="dropdown-item lang-option" href="#" data-lang="en"><span class="flag-emoji me-2">🇬🇧</span>English</a></li>
                            <li><a class="dropdown-item lang-option" href="#" data-lang="es"><span class="flag-emoji me-2">🇪🇸</span>Español</a></li>
                            <li><a class="dropdown-item lang-option" href="#" data-lang="it"><span class="flag-emoji me-2">🇮🇹</span>Italiano</a></li>
                        </ul>
                    </li>
                    <li class="nav-item nav-item-mobile">
                        <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>" data-key="backToHome">
                            <i class="fas fa-home me-2"></i><span class="nav-text-mobile" data-key="backToHome">Back to Home</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Register Section -->
    <section class="login-section">
        <div class="container">
            <div class="row justify-content-center align-items-center">
                <div class="col-lg-5 col-md-7">
                    <div class="login-card glass-card position-relative" style="overflow: hidden;">
                        <!-- Progress Indicator -->
                        <div class="register-progress mb-4">
                            <div class="progress-steps d-flex justify-content-between align-items-center">
                                <div class="step active" data-step="1">
                                    <div class="step-circle">1</div>
                                    <div class="step-label">Bilgiler</div>
                                </div>
                                <div class="step-line"></div>
                                <div class="step" data-step="2">
                                    <div class="step-circle">2</div>
                                    <div class="step-label">Doğrulama</div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mb-4">
                            <img src="<?= WEB_URL; ?>/vendor/logo.png" alt="CopyStar Logo" class="login-logo mb-3">
                            <h1 class="h3 fw-bold mb-2" id="registerTitle" data-key="registerTitle">Hesap Oluştur</h1>
                            <p class="text-muted" id="registerSubtitle" data-key="registerSubtitle">CopyStar'a katıl ve kopya ticaret yolculuğuna başla.</p>
                        </div>

                        <?php if (!empty($registrationErrors['general'])): ?>
                            <div class="alert alert-danger mb-4">
                                <span data-key="<?= htmlspecialchars($registrationErrors['general']) ?>"><?= htmlspecialchars($registrationErrors['general']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div id="codeMessage" class="alert mb-4" style="display: none;"></div>
                        
                        <form id="registerForm" method="POST" action="<?= WEB_URL; ?>/register">
                            <!-- Step 1: Registration Form -->
                            <div id="step1" class="register-step">
                                <div class="mb-4">
                                    <label for="email" class="form-label" data-key="emailLabel">
                                        <i class="fas fa-envelope me-2"></i>Email Adresi
                                    </label>
                                    <input type="email" class="form-control form-control-lg <?= isset($registrationErrors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" placeholder="ornek@email.com" data-key="emailPlaceholder" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                                    <?php if (isset($registrationErrors['email'])): ?>
                                        <div class="invalid-feedback d-block"><span data-key="<?= htmlspecialchars($registrationErrors['email']) ?>"><?= htmlspecialchars($registrationErrors['email']) ?></span></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-4">
                                    <label for="password" class="form-label" data-key="passwordLabel">
                                        <i class="fas fa-lock me-2"></i>Şifre
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg <?= isset($registrationErrors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" placeholder="Şifrenizi girin" data-key="passwordPlaceholder" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye" id="passwordIcon"></i>
                                        </button>
                                    </div>
                                    <?php if (isset($registrationErrors['password'])): ?>
                                        <div class="invalid-feedback d-block"><span data-key="<?= htmlspecialchars($registrationErrors['password']) ?>"><?= htmlspecialchars($registrationErrors['password']) ?></span></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-4">
                                    <label for="passwordConfirm" class="form-label" data-key="passwordConfirmLabel">
                                        <i class="fas fa-lock me-2"></i>Şifre Tekrar
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg <?= isset($registrationErrors['passwordConfirm']) ? 'is-invalid' : '' ?>" id="passwordConfirm" name="passwordConfirm" placeholder="Şifrenizi tekrar girin" data-key="passwordConfirmPlaceholder" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirm">
                                            <i class="fas fa-eye" id="passwordConfirmIcon"></i>
                                        </button>
                                    </div>
                                    <?php if (isset($registrationErrors['passwordConfirm'])): ?>
                                        <div class="invalid-feedback d-block"><span data-key="<?= htmlspecialchars($registrationErrors['passwordConfirm']) ?>"><?= htmlspecialchars($registrationErrors['passwordConfirm']) ?></span></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-4">
                                    <label for="referralCode" class="form-label" data-key="referralCodeLabel">
                                        <i class="fas fa-gift me-2"></i>Referans Kodu <small class="text-muted">(Opsiyonel)</small>
                                    </label>
                                    <input type="text" class="form-control form-control-lg <?= isset($registrationErrors['referralCode']) ? 'is-invalid' : '' ?>" id="referralCode" name="referralCode" placeholder="Referans kodunuz" data-key="referralCodePlaceholder" value="<?= !empty($storedReferralCode) ? htmlspecialchars($storedReferralCode) : (isset($_POST['referralCode']) ? htmlspecialchars($_POST['referralCode']) : '') ?>" <?= !empty($storedReferralCode) ? 'readonly' : '' ?>>
                                    <?php if (isset($registrationErrors['referralCode'])): ?>
                                        <div class="invalid-feedback d-block"><span data-key="<?= htmlspecialchars($registrationErrors['referralCode']) ?>"><?= htmlspecialchars($registrationErrors['referralCode']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($storedReferralCode)): ?>
                                        <small class="text-success d-block mt-1">
                                            <i class="fas fa-check-circle me-1"></i>
                                            <span data-key="referralCodeFromLink">Davet linkinden gelen referans kodu</span>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-4">
                                    <p class="small text-muted mb-0">
                                        <span data-key="termsAcceptText">Kayıt olarak,</span> 
                                        <a href="<?= WEB_URL; ?>/user-agreement" class="text-decoration-none fw-bold" data-key="termsLink">Kullanım Şartları</a>
                                        <span>nı kabul etmiş olursunuz.</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Step 2: Verification Code -->
                            <div id="step2" class="register-step" style="display: none;">
                                <div class="text-center mb-4">
                                    <div class="verification-icon mb-3">
                                        <i class="fas fa-envelope-circle-check fa-4x text-primary"></i>
                                    </div>
                                    <h4 class="fw-bold">Doğrulama Kodu</h4>
                                    <p class="text-muted">Email adresinize gönderilen 6 haneli doğrulama kodunu girin.</p>
                                </div>

                                <div class="mb-4">
                                    <label for="verificationCode" class="form-label text-center d-block">
                                        <i class="fas fa-key me-2"></i>Doğrulama Kodu
                                    </label>
                                    <div class="verification-code-inputs d-flex justify-content-center gap-2 mb-3">
                                        <input type="text" class="form-control form-control-lg text-center verification-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                                        <input type="text" class="form-control form-control-lg text-center verification-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                                        <input type="text" class="form-control form-control-lg text-center verification-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                                        <input type="text" class="form-control form-control-lg text-center verification-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                                        <input type="text" class="form-control form-control-lg text-center verification-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                                        <input type="text" class="form-control form-control-lg text-center verification-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                                    </div>
                                    <input type="hidden" id="verificationCode" name="verificationCode">
                                    <?php if (isset($registrationErrors['verificationCode'])): ?>
                                        <div class="invalid-feedback d-block text-center"><?= htmlspecialchars($registrationErrors['verificationCode']) ?></div>
                                    <?php endif; ?>
                                    <div class="text-center">
                                        <button type="button" class="btn btn-link text-decoration-none" id="resendCodeBtn" style="display: none;">
                                            <i class="fas fa-redo me-1"></i>Kodu Tekrar Gönder
                                        </button>
                                        <div id="countdownText" class="text-muted small"></div>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="register" value="1">
                            
                            <!-- Step 1 Button -->
                            <button type="submit" id="step1Btn" class="btn btn-primary btn-lg w-100 btn-modern mb-3">
                                <i class="fas fa-arrow-right me-2"></i>Devam Et
                            </button>
                            
                            <!-- Step 2 Button -->
                            <button type="submit" id="step2Btn" class="btn btn-primary btn-lg w-100 btn-modern mb-3" style="display: none;">
                                <i class="fas fa-check me-2"></i>Hesap Oluştur
                            </button>

                            <div class="text-center mt-4">
                                <p class="mb-0">
                                    <span data-key="alreadyHaveAccount">Zaten hesabınız var mı?</span> 
                                    <a href="<?= WEB_URL; ?>/login" class="text-decoration-none fw-bold" data-key="signInLink">Giriş Yap</a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <style>
        .register-progress {
            margin-bottom: 2rem;
        }
        .progress-steps {
            position: relative;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }
        .step-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            z-index: 2;
        }
        .step.active .step-circle {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: white;
            transform: scale(1.1);
        }
        .step-label {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        .step.active .step-label {
            color: var(--primary-color);
            font-weight: 600;
        }
        .step-line {
            flex: 1;
            height: 2px;
            background: var(--border-color);
            margin: 0 1rem;
            margin-top: -25px;
            z-index: 1;
            transition: all 0.3s ease;
        }
        .step.active ~ .step-line,
        .step.active + .step-line {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .register-step {
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .verification-code-inputs .verification-digit {
            width: 55px;
            height: 60px;
            font-size: 1.5rem;
            font-weight: bold;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .verification-code-inputs .verification-digit:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            transform: scale(1.05);
        }
        .verification-icon {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
    </style>

    <script>
        // Modern Two-Step Registration JavaScript
        (function() {
            'use strict';
            
            // Elements
            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            const step1Btn = document.getElementById('step1Btn');
            const step2Btn = document.getElementById('step2Btn');
            const registerForm = document.getElementById('registerForm');
            const codeMessage = document.getElementById('codeMessage');
            const resendCodeBtn = document.getElementById('resendCodeBtn');
            const countdownText = document.getElementById('countdownText');
            const verificationCodeInput = document.getElementById('verificationCode');
            const verificationDigits = document.querySelectorAll('.verification-digit');
            const progressSteps = document.querySelectorAll('.step');
            
            let codeSent = false;
            let countdownInterval = null;
            let currentStep = 1;
            
            // Password visibility toggles
            function initPasswordToggles() {
                const togglePassword = document.getElementById('togglePassword');
                const passwordInput = document.getElementById('password');
                const passwordIcon = document.getElementById('passwordIcon');
                
                if (togglePassword && passwordInput && passwordIcon) {
                    togglePassword.addEventListener('click', function() {
                        if (passwordInput.type === 'password') {
                            passwordInput.type = 'text';
                            passwordIcon.classList.remove('fa-eye');
                            passwordIcon.classList.add('fa-eye-slash');
                        } else {
                            passwordInput.type = 'password';
                            passwordIcon.classList.remove('fa-eye-slash');
                            passwordIcon.classList.add('fa-eye');
                        }
                    });
                }
                
                const togglePasswordConfirm = document.getElementById('togglePasswordConfirm');
                const passwordConfirmInput = document.getElementById('passwordConfirm');
                const passwordConfirmIcon = document.getElementById('passwordConfirmIcon');
                
                if (togglePasswordConfirm && passwordConfirmInput && passwordConfirmIcon) {
                    togglePasswordConfirm.addEventListener('click', function() {
                        if (passwordConfirmInput.type === 'password') {
                            passwordConfirmInput.type = 'text';
                            passwordConfirmIcon.classList.remove('fa-eye');
                            passwordConfirmIcon.classList.add('fa-eye-slash');
                        } else {
                            passwordConfirmInput.type = 'password';
                            passwordConfirmIcon.classList.remove('fa-eye-slash');
                            passwordConfirmIcon.classList.add('fa-eye');
                        }
                    });
                }
            }
            
            // Verification code input handling
            function initVerificationInputs() {
                verificationDigits.forEach((digit, index) => {
                    digit.addEventListener('input', function(e) {
                        const value = e.target.value.replace(/[^0-9]/g, '');
                        e.target.value = value;
                        
                        if (value && index < verificationDigits.length - 1) {
                            verificationDigits[index + 1].focus();
                        }
                        
                        updateVerificationCode();
                    });
                    
                    digit.addEventListener('keydown', function(e) {
                        if (e.key === 'Backspace' && !e.target.value && index > 0) {
                            verificationDigits[index - 1].focus();
                        }
                    });
                    
                    digit.addEventListener('paste', function(e) {
                        e.preventDefault();
                        const paste = (e.clipboardData || window.clipboardData).getData('text');
                        const digits = paste.replace(/[^0-9]/g, '').slice(0, 6);
                        
                        digits.split('').forEach((char, i) => {
                            if (verificationDigits[i]) {
                                verificationDigits[i].value = char;
                            }
                        });
                        
                        updateVerificationCode();
                        if (digits.length === 6) {
                            verificationDigits[5].focus();
                        } else if (digits.length > 0) {
                            verificationDigits[digits.length].focus();
                        }
                    });
                });
            }
            
            function updateVerificationCode() {
                const code = Array.from(verificationDigits).map(d => d.value).join('');
                if (verificationCodeInput) {
                    verificationCodeInput.value = code;
                }
            }
            
            // Show message
            function showMessage(message, type) {
                if (codeMessage) {
                    codeMessage.className = `alert alert-${type}`;
                    codeMessage.textContent = message;
                    codeMessage.style.display = 'block';
                    
                    if (type === 'success') {
                        setTimeout(() => {
                            codeMessage.style.display = 'none';
                        }, 5000);
                    }
                }
            }
            
            // Update progress indicator
            function updateProgress(step) {
                progressSteps.forEach((s, index) => {
                    if (index + 1 <= step) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            }
            
            // Go to step
            function goToStep(step) {
                if (step === 1) {
                    step1.style.display = 'block';
                    step2.style.display = 'none';
                    step1Btn.style.display = 'block';
                    step2Btn.style.display = 'none';
                    document.getElementById('registerTitle').textContent = 'Hesap Oluştur';
                    document.getElementById('registerSubtitle').textContent = 'CopyStar\'a katıl ve kopya ticaret yolculuğuna başla.';
                } else if (step === 2) {
                    step1.style.display = 'none';
                    step2.style.display = 'block';
                    step1Btn.style.display = 'none';
                    step2Btn.style.display = 'block';
                    document.getElementById('registerTitle').textContent = 'Doğrulama Kodu';
                    document.getElementById('registerSubtitle').textContent = 'Email adresinize gönderilen kodu girin.';
                    if (verificationDigits[0]) verificationDigits[0].focus();
                }
                currentStep = step;
                updateProgress(step);
            }
            
            // Send verification code
            function sendVerificationCode() {
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value.trim();
                const passwordConfirm = document.getElementById('passwordConfirm').value.trim();
                
                if (!email) {
                    showMessage('Lütfen email adresinizi girin', 'danger');
                    document.getElementById('email').focus();
                    return false;
                }
                
                if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    showMessage('Geçersiz email formatı', 'danger');
                    document.getElementById('email').focus();
                    return false;
                }
                
                if (!password) {
                    showMessage('Lütfen şifrenizi girin', 'danger');
                    document.getElementById('password').focus();
                    return false;
                }
                
                if (password.length < 8) {
                    showMessage('Şifre en az 8 karakter olmalıdır', 'danger');
                    document.getElementById('password').focus();
                    return false;
                }
                
                if (password !== passwordConfirm) {
                    showMessage('Şifreler eşleşmiyor', 'danger');
                    document.getElementById('passwordConfirm').focus();
                    return false;
                }
                
                step1Btn.disabled = true;
                step1Btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Kod Gönderiliyor...';
                
                fetch('<?= WEB_URL; ?>/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=send_code&email=' + encodeURIComponent(email)
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.text().then(text => {
                        console.log('Response text:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON parse error:', e, 'Text:', text);
                            throw new Error('Geçersiz yanıt: ' + text.substring(0, 100));
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed data:', data);
                    if (data.success) {
                        showMessage(data.message, 'success');
                        codeSent = true;
                        
                        // If we're already on step 2, don't change step
                        if (currentStep === 1) {
                            goToStep(2);
                        }
                        
                        // Start countdown
                        startCountdown(60);
                        
                        // Re-enable resend button if it was clicked
                        if (resendCodeBtn) {
                            resendCodeBtn.disabled = false;
                            resendCodeBtn.innerHTML = '<i class="fas fa-redo me-1"></i>Kodu Tekrar Gönder';
                        }
                        
                        // Re-enable step1 button if it was used
                        if (step1Btn) {
                            step1Btn.disabled = false;
                            step1Btn.innerHTML = '<i class="fas fa-arrow-right me-2"></i>Devam Et';
                        }
                    } else {
                        showMessage(data.message || 'Kod gönderilemedi', 'danger');
                        step1Btn.disabled = false;
                        step1Btn.innerHTML = '<i class="fas fa-arrow-right me-2"></i>Devam Et';
                        
                        // Re-enable resend button on error
                        if (resendCodeBtn) {
                            resendCodeBtn.disabled = false;
                            resendCodeBtn.innerHTML = '<i class="fas fa-redo me-1"></i>Kodu Tekrar Gönder';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error details:', error);
                    showMessage('Bir hata oluştu: ' + (error.message || 'Bilinmeyen hata'), 'danger');
                    step1Btn.disabled = false;
                    step1Btn.innerHTML = '<i class="fas fa-arrow-right me-2"></i>Devam Et';
                    
                    // Re-enable resend button on error
                    if (resendCodeBtn) {
                        resendCodeBtn.disabled = false;
                        resendCodeBtn.innerHTML = '<i class="fas fa-redo me-1"></i>Kodu Tekrar Gönder';
                    }
                });
                
                return true;
            }
            
            // Countdown
            function startCountdown(seconds) {
                if (countdownInterval) {
                    clearInterval(countdownInterval);
                }
                
                let remaining = seconds;
                if (resendCodeBtn) resendCodeBtn.style.display = 'none';
                
                countdownInterval = setInterval(() => {
                    remaining--;
                    if (remaining > 0) {
                        if (countdownText) countdownText.textContent = `Kodu tekrar göndermek için ${remaining} saniye bekleyin`;
                    } else {
                        clearInterval(countdownInterval);
                        if (resendCodeBtn) resendCodeBtn.style.display = 'block';
                        if (countdownText) countdownText.textContent = '';
                    }
                }, 1000);
            }
            
            // Resend code
            if (resendCodeBtn) {
                resendCodeBtn.addEventListener('click', function() {
                    // Disable button and show loading
                    resendCodeBtn.disabled = true;
                    resendCodeBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Gönderiliyor...';
                    
                    // Clear countdown
                    if (countdownInterval) {
                        clearInterval(countdownInterval);
                        countdownInterval = null;
                    }
                    if (countdownText) countdownText.textContent = '';
                    
                    // Send code
                    sendVerificationCode();
                });
            }
            
            // Form submission
            if (registerForm) {
                registerForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    if (currentStep === 1) {
                        sendVerificationCode();
                    } else if (currentStep === 2) {
                        // Get code from verification digits
                        const codeFromDigits = Array.from(verificationDigits).map(d => d.value).join('');
                        const codeFromInput = verificationCodeInput ? verificationCodeInput.value.trim() : '';
                        const code = codeFromDigits || codeFromInput;
                        
                        console.log('Code from digits:', codeFromDigits);
                        console.log('Code from input:', codeFromInput);
                        console.log('Final code:', code);
                        
                        if (!code || code.length !== 6) {
                            showMessage('Lütfen 6 haneli doğrulama kodunu girin', 'danger');
                            if (verificationDigits[0]) verificationDigits[0].focus();
                            return false;
                        }
                        
                        // Update hidden input with the code
                        if (verificationCodeInput) {
                            verificationCodeInput.value = code;
                        }
                        
                        step2Btn.disabled = true;
                        step2Btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Hesap Oluşturuluyor...';
                        
                        const formData = new FormData(registerForm);
                        console.log('Submitting form with data:', Object.fromEntries(formData));
                        console.log('Verification code in form:', formData.get('verificationCode'));
                        
                        fetch('<?= WEB_URL; ?>/register', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        })
                        .then(response => {
                            console.log('Registration response status:', response.status);
                            console.log('Response redirected:', response.redirected);
                            console.log('Response URL:', response.url);
                            
                            // Check if response is JSON
                            const contentType = response.headers.get('content-type');
                            if (contentType && contentType.includes('application/json')) {
                                return response.json();
                            } else {
                                return response.text();
                            }
                        })
                        .then(data => {
                            if (typeof data === 'object' && data !== null) {
                                // JSON response
                                console.log('JSON response received:', data);
                                if (data.success) {
                                    showMessage(data.message || 'Kayıt başarılı!', 'success');
                                    if (data.redirect) {
                                        setTimeout(() => {
                                            window.location.href = data.redirect;
                                        }, 1000);
                                    } else {
                                        window.location.href = '<?= WEB_URL; ?>/dashboard';
                                    }
                                } else {
                                    // Show errors
                                    if (data.errors) {
                                        let errorMsg = data.message || 'Lütfen formu kontrol edin';
                                        if (data.errors.verificationCode) {
                                            errorMsg = data.errors.verificationCode;
                                        } else if (data.errors.email) {
                                            errorMsg = data.errors.email;
                                        } else if (data.errors.password) {
                                            errorMsg = data.errors.password;
                                        }
                                        showMessage(errorMsg, 'danger');
                                    } else {
                                        showMessage(data.message || 'Kayıt sırasında bir hata oluştu', 'danger');
                                    }
                                    step2Btn.disabled = false;
                                    step2Btn.innerHTML = '<i class="fas fa-check me-2"></i>Hesap Oluştur';
                                }
                            } else {
                                // HTML response (fallback)
                                const html = data;
                                console.log('HTML response received, length:', html ? html.length : 0);
                                
                                if (html) {
                                    // Check for PHP errors first
                                    if (html.includes('Fatal error') || html.includes('Parse error') || html.includes('Warning') || html.includes('mysqli_sql_exception')) {
                                        console.error('PHP Error in response:', html.substring(0, 1000));
                                        showMessage('Sunucu hatası oluştu. Lütfen tekrar deneyin.', 'danger');
                                        step2Btn.disabled = false;
                                        step2Btn.innerHTML = '<i class="fas fa-check me-2"></i>Hesap Oluştur';
                                        return;
                                    }
                                    
                                    // Check for redirect
                                    if (html.includes('Location:') || html.includes('dashboard')) {
                                        console.log('Redirect detected, going to dashboard');
                                        window.location.href = '<?= WEB_URL; ?>/dashboard';
                                        return;
                                    }
                                    
                                    // Parse HTML for errors
                                    const parser = new DOMParser();
                                    const doc = parser.parseFromString(html, 'text/html');
                                    const errorDiv = doc.querySelector('.alert-danger');
                                    
                                    if (errorDiv) {
                                        const errorText = errorDiv.textContent.trim();
                                        console.error('Registration error:', errorText);
                                        showMessage(errorText || 'Kayıt sırasında bir hata oluştu', 'danger');
                                        step2Btn.disabled = false;
                                        step2Btn.innerHTML = '<i class="fas fa-check me-2"></i>Hesap Oluştur';
                                    } else {
                                        // No errors found, assume success
                                        console.log('No errors found, redirecting to dashboard');
                                        window.location.href = '<?= WEB_URL; ?>/dashboard';
                                    }
                                } else {
                                    // No response, assume success
                                    console.log('No response, redirecting to dashboard');
                                    window.location.href = '<?= WEB_URL; ?>/dashboard';
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Registration fetch error:', error);
                            showMessage('Bir hata oluştu: ' + (error.message || 'Bilinmeyen hata'), 'danger');
                            step2Btn.disabled = false;
                            step2Btn.innerHTML = '<i class="fas fa-check me-2"></i>Hesap Oluştur';
                        });
                    }
                });
            }
            
            // Initialize
            initPasswordToggles();
            initVerificationInputs();
            updateProgress(1);
        })();
        
        // Translate error messages after page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (typeof updateLanguage === 'function' && typeof currentLang !== 'undefined') {
                    updateLanguage(currentLang);
                } else if (typeof updateLanguage === 'function') {
                    const lang = localStorage.getItem('language') || 'tr';
                    updateLanguage(lang);
                }
            }, 200);
        });
    </script>

