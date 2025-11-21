    <title>Email Doğrulama - CopyStar</title>
</head>
<body>
<?php
// Email verification page
$verificationSuccess = false;
$verificationError = '';
$showResendForm = false;

// Get token and email from URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$resend = isset($_GET['resend']) ? intval($_GET['resend']) : 0;

// If resend is requested, show resend form
if ($resend == 1) {
    $showResendForm = true;
    if (isset($currentUser) && $currentUser) {
        $email = $currentUser['email'];
    }
}

if ($showResendForm) {
    // Show resend form, don't verify
} elseif (empty($token) || empty($email)) {
    $verificationError = 'Geçersiz doğrulama linki';
    $showResendForm = true;
} else {
    require_once(V_PATH . 'email.php');
    $result = verifyEmailToken($token, $email);
    
    if ($result['success']) {
        $verificationSuccess = true;
        
        // If user is logged in, refresh their session
        if (isset($currentUser) && $currentUser && $currentUser['id'] == $result['user_id']) {
            $currentUser = getCurrentUser();
        }
    } else {
        $verificationError = $result['message'];
    }
}

// Resend verification email handler
$resendSuccess = false;
$resendError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_verification'])) {
    $resendEmail = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($resendEmail)) {
        $resendError = 'Email adresi gerekli';
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, email_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $resendEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $resendError = 'Bu email adresi ile kayıtlı kullanıcı bulunamadı';
        } else {
            $user = $result->fetch_assoc();
            
            if ($user['email_verified'] == 1) {
                $resendError = 'Bu email adresi zaten doğrulanmış';
            } else {
                require_once(V_PATH . 'email.php');
                $verificationToken = generateVerificationToken($user['id'], $resendEmail);
                $emailSent = sendVerificationEmail($user['id'], $resendEmail, $verificationToken);
                
                if ($emailSent) {
                    $resendSuccess = true;
                } else {
                    $resendError = 'Email gönderilemedi. Lütfen daha sonra tekrar deneyin.';
                }
            }
        }
        $stmt->close();
    }
}
?>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Navigation -->
    <?php include(V_PATH."topnav.php"); ?>

    <!-- Email Verification Section -->
    <section class="login-section" style="padding-top: 120px;">
        <div class="container">
            <div class="row justify-content-center align-items-center">
                <div class="col-lg-5 col-md-7">
                    <div class="login-card glass-card">
                        <div class="text-center mb-4">
                            <img src="<?= WEB_URL; ?>/vendor/logo.png" alt="CopyStar Logo" class="login-logo mb-3">
                            <h1 class="h3 fw-bold mb-2">Email Doğrulama</h1>
                        </div>

                        <?php if ($verificationSuccess): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Email adresiniz başarıyla doğrulandı!</strong>
                                <p class="mb-0 mt-2">Artık tüm özellikleri kullanabilirsiniz.</p>
                            </div>
                            <div class="text-center mt-4">
                                <a href="<?= WEB_URL; ?>/dashboard" class="btn btn-primary btn-modern">
                                    <i class="fas fa-arrow-right me-2"></i>Dashboard'a Git
                                </a>
                            </div>
                        <?php elseif ($showResendForm && empty($verificationError)): ?>
                            <!-- Resend Verification Form -->
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <p class="mb-0">Doğrulama email'i tekrar göndermek için email adresinizi girin.</p>
                            </div>
                            
                            <!-- Resend Verification Form -->
                        <?php elseif (!empty($verificationError)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Doğrulama Başarısız</strong>
                                <p class="mb-0 mt-2"><?= htmlspecialchars($verificationError) ?></p>
                            </div>
                            
                            <!-- Resend Verification Form -->
                        <?php endif; ?>
                        
                        <?php if ($showResendForm || !empty($verificationError)): ?>
                            <div class="mt-4">
                                <h5 class="mb-3">Doğrulama Email'i Tekrar Gönder</h5>
                                
                                <?php if ($resendSuccess): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle me-2"></i>
                                        Doğrulama email'i başarıyla gönderildi. Lütfen email kutunuzu kontrol edin.
                                    </div>
                                <?php elseif (!empty($resendError)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <?= htmlspecialchars($resendError) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="resend_email" class="form-label">Email Adresi</label>
                                        <input type="email" class="form-control" id="resend_email" name="email" 
                                               value="<?= htmlspecialchars($email) ?>" required>
                                    </div>
                                    <button type="submit" name="resend_verification" class="btn btn-primary w-100">
                                        <i class="fas fa-paper-plane me-2"></i>Doğrulama Email'i Gönder
                                    </button>
                                </form>
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="<?= WEB_URL; ?>/dashboard" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Dashboard'a Dön
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <p class="mb-0">Email doğrulama linki bekleniyor...</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

