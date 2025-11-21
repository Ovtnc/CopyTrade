
    <title>Account - CopyStar</title>

</head>
<body>
<?php
// Require authentication
if (!isset($currentUser) || !$currentUser) {
    // Clear any invalid cookies
    if (isset($_COOKIE['auth_key'])) {
        setcookie('auth_key', '', time() - 3600, '/');
    }
    header("Location: " . WEB_URL . "/login");
    exit;
}

// Handle name surname update
$nameUpdateSuccess = false;
$nameUpdateError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_name'])) {
    $nameSurname = isset($_POST['name_surname']) ? trim($_POST['name_surname']) : '';
    
    if (!empty($nameSurname)) {
        // Update name surname in database
        $stmt = $conn->prepare("UPDATE users SET name_surname = ? WHERE id = ?");
        $stmt->bind_param("si", $nameSurname, $currentUser['id']);
        
        if ($stmt->execute()) {
            $nameUpdateSuccess = true;
            // Refresh user data
            $currentUser = getCurrentUser();
        } else {
            $nameUpdateError = 'nameUpdateFailed';
        }
        $stmt->close();
    } else {
        $nameUpdateError = 'nameRequired';
    }
}

// Handle phone number update
$phoneUpdateSuccess = false;
$phoneUpdateError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_phone'])) {
    $phoneNumber = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $countryCode = isset($_POST['country_code']) ? trim($_POST['country_code']) : '';
    
    if (!empty($phoneNumber) && !empty($countryCode)) {
        // Combine country code and phone number
        $fullPhone = $countryCode . ' ' . $phoneNumber;
        
        // Update phone number in database
        $stmt = $conn->prepare("UPDATE users SET phone = ? WHERE id = ?");
        $stmt->bind_param("si", $fullPhone, $currentUser['id']);
        
        if ($stmt->execute()) {
            $phoneUpdateSuccess = true;
            // Refresh user data
            $currentUser = getCurrentUser();
        } else {
            $phoneUpdateError = 'phoneUpdateFailed';
        }
        $stmt->close();
    } else {
        $phoneUpdateError = 'phoneRequired';
    }
}

// Handle password change
$passwordUpdateSuccess = false;
$passwordUpdateError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = isset($_POST['currentPassword']) ? $_POST['currentPassword'] : '';
    $newPassword = isset($_POST['newPassword']) ? $_POST['newPassword'] : '';
    $confirmPassword = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';
    
    // Validation
    if (empty($currentPassword)) {
        $passwordUpdateError = 'currentPasswordRequired';
    } elseif (empty($newPassword)) {
        $passwordUpdateError = 'newPasswordRequired';
    } elseif (strlen($newPassword) < 8) {
        $passwordUpdateError = 'passwordMinLength';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordUpdateError = 'passwordsNotMatch';
    } else {
        // Verify current password
        $decodedPassword = base64_decode($currentUser['password']);
        if ($currentPassword !== $decodedPassword) {
            $passwordUpdateError = 'currentPasswordIncorrect';
        } else {
            // Update password
            $encodedNewPassword = base64_encode($newPassword);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $encodedNewPassword, $currentUser['id']);
            
            if ($stmt->execute()) {
                $passwordUpdateSuccess = true;
                // Refresh user data
                $currentUser = getCurrentUser();
            } else {
                $passwordUpdateError = 'passwordUpdateFailed';
            }
            $stmt->close();
        }
    }
}

// Parse phone number if exists
$phoneCountryCode = '+90';
$phoneNumber = '';
if (!empty($currentUser['phone'])) {
    $phoneParts = explode(' ', $currentUser['phone'], 2);
    if (count($phoneParts) == 2) {
        $phoneCountryCode = $phoneParts[0];
        $phoneNumber = $phoneParts[1];
    } else {
        $phoneNumber = $currentUser['phone'];
    }
}

// Get account level text
$accountLevelText = 'Seviye ' . $currentUser['account_level'];
$accountLevelBadge = $currentUser['account_level'] == 1 ? 'bg-warning' : ($currentUser['account_level'] == 2 ? 'bg-success' : 'bg-info');

// Check KYC status
$kycStmt = $conn->prepare("SELECT status FROM kyc_verifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$kycStmt->bind_param("i", $currentUser['id']);
$kycStmt->execute();
$kycResult = $kycStmt->get_result();
$kycData = $kycResult->fetch_assoc();
$kycStmt->close();

$kycStatus = 'Doğrulanmadı';
$kycBadge = 'bg-warning';
$showKYCButton = true;

if ($currentUser['kyc_verified'] == 1) {
    $kycStatus = 'Doğrulandı';
    $kycBadge = 'bg-success';
    $showKYCButton = false;
} elseif ($kycData && $kycData['status'] == 'pending') {
    $kycStatus = 'Onay Bekliyor';
    $kycBadge = 'bg-info';
    $showKYCButton = false;
} elseif ($kycData && $kycData['status'] == 'rejected') {
    $kycStatus = 'Reddedildi';
    $kycBadge = 'bg-danger';
    $showKYCButton = true;
}
?>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Navigation -->
    <?php include(V_PATH."topnav.php"); ?>

    <!-- Account Section -->
    <section class="dashboard-section" style="padding-top: 20px;">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h2 fw-bold mb-0" data-key="accountSettings">Hesap Ayarları</h1>
                </div>
            </div>

            <div class="row g-4">
                <!-- Email Address Card -->
                <div class="col-12 col-md-6">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h5 fw-bold mb-0" data-key="emailAddress">E-posta Adresi</h3>
                            <i class="fas fa-envelope text-primary"></i>
                        </div>
                        <div class="mb-3">
                            <p class="text-muted small mb-2" data-key="emailAddress">E-posta Adresi</p>
                            <input type="email" class="form-control form-control-modern" id="emailInput" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" readonly>
                        </div>
                    </div>
                </div>

                <!-- Name Surname Card -->
                <div class="col-12 col-md-6">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h5 fw-bold mb-0" data-key="nameSurname">İsim Soyisim</h3>
                            <i class="fas fa-user text-info"></i>
                        </div>
                        <div class="mb-3">
                            <?php if ($nameUpdateSuccess): ?>
                                <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                                    <span data-key="nameUpdateSuccess">İsim soyisim başarıyla güncellendi!</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <?php if ($nameUpdateError): ?>
                                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                                    <span data-key="<?php echo htmlspecialchars($nameUpdateError); ?>">Hata oluştu!</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <form id="nameForm" method="POST" action="">
                                <input type="hidden" name="update_name" value="1">
                                <div class="mb-2">
                                    <p class="text-muted small mb-2" data-key="nameSurname">İsim Soyisim</p>
                                    <input type="text" class="form-control form-control-modern" id="nameInput" name="name_surname" 
                                           value="<?php echo htmlspecialchars($currentUser['name_surname'] ?? ''); ?>" 
                                           placeholder="Adınız Soyadınız" required>
                                </div>
                                <button type="submit" class="btn btn-sm btn-modern w-100" data-key="save">
                                    <i class="fas fa-save me-1"></i><span data-key="save">Kaydet</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Phone Number Card -->
                <div class="col-12 col-md-6">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h5 fw-bold mb-0" data-key="phoneNumber">Telefon Numarası</h3>
                            <i class="fas fa-phone text-success"></i>
                        </div>
                        <div class="mb-3">
                            <p class="text-muted small mb-2" data-key="phoneNumber">Telefon Numarası</p>
                            <?php if ($phoneUpdateSuccess): ?>
                                <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                                    <span data-key="phoneUpdateSuccess">Telefon numarası başarıyla güncellendi!</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <?php if ($phoneUpdateError): ?>
                                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                                    <span data-key="<?php echo htmlspecialchars($phoneUpdateError); ?>">Hata oluştu!</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <form id="phoneForm" method="POST" action="">
                                <input type="hidden" name="update_phone" value="1">
                                <div class="d-flex gap-2 mb-2">
                                    <select class="form-select form-select-modern" id="countryCode" name="country_code" style="max-width: 120px;" required>
                                        <option value="+90" <?php echo $phoneCountryCode == '+90' ? 'selected' : ''; ?>>🇹🇷 +90</option>
                                        <option value="+1" <?php echo $phoneCountryCode == '+1' ? 'selected' : ''; ?>>🇺🇸 +1</option>
                                        <option value="+44" <?php echo $phoneCountryCode == '+44' ? 'selected' : ''; ?>>🇬🇧 +44</option>
                                        <option value="+49" <?php echo $phoneCountryCode == '+49' ? 'selected' : ''; ?>>🇩🇪 +49</option>
                                        <option value="+33" <?php echo $phoneCountryCode == '+33' ? 'selected' : ''; ?>>🇫🇷 +33</option>
                                        <option value="+39" <?php echo $phoneCountryCode == '+39' ? 'selected' : ''; ?>>🇮🇹 +39</option>
                                        <option value="+34" <?php echo $phoneCountryCode == '+34' ? 'selected' : ''; ?>>🇪🇸 +34</option>
                                        <option value="+7" <?php echo $phoneCountryCode == '+7' ? 'selected' : ''; ?>>🇷🇺 +7</option>
                                        <option value="+86" <?php echo $phoneCountryCode == '+86' ? 'selected' : ''; ?>>🇨🇳 +86</option>
                                        <option value="+81" <?php echo $phoneCountryCode == '+81' ? 'selected' : ''; ?>>🇯🇵 +81</option>
                                        <option value="+82" <?php echo $phoneCountryCode == '+82' ? 'selected' : ''; ?>>🇰🇷 +82</option>
                                        <option value="+91" <?php echo $phoneCountryCode == '+91' ? 'selected' : ''; ?>>🇮🇳 +91</option>
                                        <option value="+971" <?php echo $phoneCountryCode == '+971' ? 'selected' : ''; ?>>🇦🇪 +971</option>
                                        <option value="+966" <?php echo $phoneCountryCode == '+966' ? 'selected' : ''; ?>>🇸🇦 +966</option>
                                        <option value="+20" <?php echo $phoneCountryCode == '+20' ? 'selected' : ''; ?>>🇪🇬 +20</option>
                                        <option value="+27" <?php echo $phoneCountryCode == '+27' ? 'selected' : ''; ?>>🇿🇦 +27</option>
                                        <option value="+55" <?php echo $phoneCountryCode == '+55' ? 'selected' : ''; ?>>🇧🇷 +55</option>
                                        <option value="+52" <?php echo $phoneCountryCode == '+52' ? 'selected' : ''; ?>>🇲🇽 +52</option>
                                        <option value="+61" <?php echo $phoneCountryCode == '+61' ? 'selected' : ''; ?>>🇦🇺 +61</option>
                                        <option value="+64" <?php echo $phoneCountryCode == '+64' ? 'selected' : ''; ?>>🇳🇿 +64</option>
                                    </select>
                                    <input type="tel" class="form-control form-control-modern" id="phoneInput" name="phone" value="<?php echo htmlspecialchars($phoneNumber); ?>" placeholder="555 123 45 67" required>
                                </div>
                                <button type="submit" class="btn btn-sm btn-modern w-100" data-key="save">
                                    <i class="fas fa-save me-1"></i><span data-key="save">Kaydet</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Account Level Card -->
                <div class="col-12 col-md-6">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h5 fw-bold mb-0" data-key="accountLevel">Hesap Seviyesi</h3>
                            <i class="fas fa-shield-alt text-warning"></i>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge <?php echo $accountLevelBadge; ?> text-dark"><?php echo htmlspecialchars($accountLevelText); ?></span>
                                <span class="text-muted small" data-key="<?php echo $currentUser['kyc_verified'] == 1 ? 'verified' : 'notVerified'; ?>"><?php echo htmlspecialchars($kycStatus); ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar <?php echo $accountLevelBadge; ?>" role="progressbar" style="width: <?php echo ($currentUser['account_level'] / 2) * 100; ?>%;" aria-valuenow="<?php echo $currentUser['account_level']; ?>" aria-valuemin="0" aria-valuemax="2"></div>
                            </div>
                            <p class="text-muted small mt-2 mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                <span data-key="kycDescription">KYC doğrulamasını tamamlayarak Seviye 2'ye yükseltin.</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- KYC Verification Card -->
                <div class="col-12 col-md-6">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h5 fw-bold mb-0" data-key="kycVerification">KYC Doğrulama</h3>
                            <i class="fas fa-id-card text-info"></i>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="badge <?php echo $kycBadge; ?> text-dark" data-key="<?php echo $currentUser['kyc_verified'] == 1 ? 'verifiedStatus' : 'notVerifiedStatus'; ?>"><?php echo htmlspecialchars($kycStatus); ?></span>
                            </div>
                            <p class="text-muted small mb-3" data-key="kycDescription">
                                Kimlik doğrulama işlemini tamamlayarak hesap güvenliğinizi artırın ve daha yüksek limitlere erişin.
                            </p>
                            <?php if ($showKYCButton): ?>
                            <a href="<?= WEB_URL; ?>/verify-kyc" class="btn btn-modern w-100" data-key="verifyKYC">
                                <i class="fas fa-check-circle me-2"></i><span data-key="verifyKYC">KYC Doğrula</span>
                            </a>
                            <?php elseif ($currentUser['kyc_verified'] == 1): ?>
                            <button class="btn btn-success w-100" disabled>
                                <i class="fas fa-check-circle me-2"></i><span data-key="kycVerified">KYC Doğrulandı</span>
                            </button>
                            <?php else: ?>
                            <button class="btn btn-info w-100" disabled>
                                <i class="fas fa-clock me-2"></i><span data-key="kycPending">Onay Bekliyor</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Wallet Actions Card -->
                <div class="col-12 col-md-6">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h5 fw-bold mb-0" data-key="wallet">Cüzdan</h3>
                            <i class="fas fa-wallet text-primary"></i>
                        </div>
                        <div class="mb-3">
                            <p class="text-muted small mb-3" data-key="walletDescription">
                                Para yatırma ve çekme işlemlerinizi yönetin.
                            </p>
                            <div class="d-flex flex-column gap-2">
                                <a href="<?= WEB_URL; ?>/wallet" class="btn btn-modern w-100">
                                    <i class="fas fa-arrow-down me-2"></i><span data-key="deposit">Para Yatır</span>
                                </a>
                                <a href="<?= WEB_URL; ?>/wallet" class="btn btn-outline-modern w-100">
                                    <i class="fas fa-arrow-up me-2"></i><span data-key="withdraw">Para Çek</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Referral Code Card -->
                <div class="col-12 col-md-6">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h5 fw-bold mb-0" data-key="referralCode">Referans Kodu</h3>
                            <i class="fas fa-user-plus text-success"></i>
                        </div>
                        <div class="mb-3">
                            <p class="text-muted small mb-2" data-key="referralCode">Referans Kodu</p>
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" class="form-control form-control-modern" id="referralCodeInput" value="<?php echo htmlspecialchars($currentUser['referral_code'] ?? ''); ?>" readonly>
                                <button class="btn btn-sm btn-modern" onclick="copyToClipboard('referralCodeInput')" data-key="copyReferralCode">
                                    <i class="fas fa-copy me-1"></i><span data-key="copyReferralCode">Kopyala</span>
                                </button>
                            </div>
                            <p class="text-muted small mt-2 mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                Bu kodu arkadaşlarınızla paylaşın ve referans bonusları kazanın.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Change Password Card -->
                <div class="col-12">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h3 class="h5 fw-bold mb-0" data-key="changePassword">Şifre Değiştir</h3>
                            <i class="fas fa-key text-primary"></i>
                        </div>
                        <?php if ($passwordUpdateSuccess): ?>
                            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                                <span data-key="passwordUpdateSuccess">Şifre başarıyla güncellendi!</span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($passwordUpdateError): ?>
                            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                                <span data-key="<?php echo htmlspecialchars($passwordUpdateError); ?>">Hata oluştu!</span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <form id="changePasswordForm" method="POST" action="">
                            <input type="hidden" name="change_password" value="1">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label small text-muted" data-key="currentPassword">Mevcut Şifre</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-modern" id="currentPassword" name="currentPassword" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('currentPassword')">
                                            <i class="fas fa-eye" id="currentPasswordIcon"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label small text-muted" data-key="newPassword">Yeni Şifre</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-modern" id="newPassword" name="newPassword" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('newPassword')">
                                            <i class="fas fa-eye" id="newPasswordIcon"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label small text-muted" data-key="confirmPassword">Şifreyi Onayla</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-modern" id="confirmPassword" name="confirmPassword" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('confirmPassword')">
                                            <i class="fas fa-eye" id="confirmPasswordIcon"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-modern" data-key="updatePassword">
                                    <i class="fas fa-save me-2"></i><span data-key="updatePassword">Şifreyi Güncelle</span>
                                </button>
                                <button type="reset" class="btn btn-outline-modern" data-key="cancel">
                                    <i class="fas fa-times me-2"></i><span data-key="cancel">İptal</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Copy to clipboard function (only for referral code)
        function copyToClipboard(inputId) {
            const input = document.getElementById(inputId);
            input.select();
            input.setSelectionRange(0, 99999); // For mobile devices
            
            try {
                document.execCommand('copy');
                
                // Show feedback
                const btn = event.target.closest('button');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i>';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-modern');
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-modern');
                }, 2000);
            } catch (err) {
                console.error('Copy failed:', err);
            }
        }

        // Toggle password visibility
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + 'Icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Format phone number automatically
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('phoneInput');
            const countryCode = document.getElementById('countryCode');
            
            if (phoneInput) {
                // Format on input
                phoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
                    
                    // Format based on country code
                    const code = countryCode.value;
                    let formatted = '';
                    
                    if (code === '+90') {
                        // Turkish format: 555 123 45 67
                        if (value.length > 0) {
                            formatted = value.substring(0, 3);
                            if (value.length > 3) {
                                formatted += ' ' + value.substring(3, 6);
                            }
                            if (value.length > 6) {
                                formatted += ' ' + value.substring(6, 8);
                            }
                            if (value.length > 8) {
                                formatted += ' ' + value.substring(8, 10);
                            }
                        }
                    } else if (code === '+1') {
                        // US format: (555) 123-4567
                        if (value.length > 0) {
                            formatted = '(' + value.substring(0, 3);
                            if (value.length > 3) {
                                formatted += ') ' + value.substring(3, 6);
                            }
                            if (value.length > 6) {
                                formatted += '-' + value.substring(6, 10);
                            }
                        }
                    } else {
                        // Default format: spaces every 3 digits
                        formatted = value.match(/.{1,3}/g)?.join(' ') || value;
                    }
                    
                    e.target.value = formatted;
                });
                
                // Limit length based on country
                phoneInput.addEventListener('keypress', function(e) {
                    const code = countryCode.value;
                    const currentValue = e.target.value.replace(/\D/g, '');
                    let maxLength = 10;
                    
                    if (code === '+90') maxLength = 10; // Turkish
                    else if (code === '+1') maxLength = 10; // US
                    else if (code === '+44') maxLength = 10; // UK
                    else if (code === '+49') maxLength = 11; // Germany
                    else if (code === '+33') maxLength = 9; // France
                    else maxLength = 15; // International
                    
                    if (currentValue.length >= maxLength && e.key !== 'Backspace' && e.key !== 'Delete') {
                        e.preventDefault();
                    }
                });
            }
        });


        // Change Password Form Handler - Client-side validation only
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            // Client-side validation
            if (!currentPassword) {
                e.preventDefault();
                alert('Lütfen mevcut şifrenizi giriniz!');
                return false;
            }
            
            if (!newPassword) {
                e.preventDefault();
                alert('Lütfen yeni şifrenizi giriniz!');
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Yeni şifre en az 8 karakter olmalıdır!');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Yeni şifre ve şifre onayı eşleşmiyor!');
                return false;
            }
            
            // If validation passes, form will submit normally to PHP
        });
        
        // Clear form on successful password update
        <?php if ($passwordUpdateSuccess): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('changePasswordForm').reset();
        });
        <?php endif; ?>

        // Set user email (this would come from the server in a real application)
        document.addEventListener('DOMContentLoaded', function() {
            // Example: Set user email
            // document.getElementById('emailInput').value = userEmail;
        });
    </script>

