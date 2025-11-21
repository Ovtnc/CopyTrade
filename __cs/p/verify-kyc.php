    <title>KYC Verification - CopyStar</title>
</head>
<body>
<?php
// Require authentication
if (!isset($currentUser) || !$currentUser) {
    header("Location: " . WEB_URL . "/login");
    exit;
}

// Check if user already has approved KYC
if ($currentUser['kyc_verified'] == 1) {
    header("Location: " . WEB_URL . "/account");
    exit;
}

// Check if user has pending KYC
$hasPendingKYC = false;
$kycStmt = $conn->prepare("SELECT id, status FROM kyc_verifications WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
$kycStmt->bind_param("i", $currentUser['id']);
$kycStmt->execute();
$kycResult = $kycStmt->get_result();
if ($kycResult->num_rows > 0) {
    $hasPendingKYC = true;
}
$kycStmt->close();

// Handle form submission
$kycErrors = [];
$kycSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_kyc'])) {
    // Get form data
    $country = isset($_POST['country']) ? trim($_POST['country']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    
    // Validation
    if (empty($country)) {
        $kycErrors['country'] = 'countryRequired';
    }
    if (empty($phone)) {
        $kycErrors['phone'] = 'phoneRequired';
    }
    if (empty($address)) {
        $kycErrors['address'] = 'addressRequired';
    }
    
    // Check file uploads
    $requiredFiles = ['id_front', 'id_back', 'selfie', 'id_selfie'];
    $uploadedFiles = [];
    $uploadErrors = [];
    
    // Create user's KYC directory
    $kycBaseDir = realpath('./__cs/kyc/');
    if (!$kycBaseDir) {
        mkdir('./__cs/kyc/', 0755, true);
        $kycBaseDir = realpath('./__cs/kyc/');
    }
    $kycDir = $kycBaseDir . '/' . $currentUser['id'] . '/';
    if (!file_exists($kycDir)) {
        mkdir($kycDir, 0755, true);
    }
    
    foreach ($requiredFiles as $fileKey) {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors[$fileKey] = 'fileRequired';
        } else {
            $file = $_FILES[$fileKey];
            
            // Validate file type (images only)
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                $uploadErrors[$fileKey] = 'invalidFileType';
            } else {
                // Validate file size (max 5MB)
                if ($file['size'] > 5 * 1024 * 1024) {
                    $uploadErrors[$fileKey] = 'fileTooLarge';
                } else {
                    // Generate unique filename
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = $fileKey . '_' . time() . '_' . uniqid() . '.' . $extension;
                    $filePath = $kycDir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filePath)) {
                        $uploadedFiles[$fileKey] = '__cs/kyc/' . $currentUser['id'] . '/' . $filename;
                    } else {
                        $uploadErrors[$fileKey] = 'uploadFailed';
                    }
                }
            }
        }
    }
    
    // If no errors, save to database
    if (empty($kycErrors) && empty($uploadErrors)) {
        $stmt = $conn->prepare("INSERT INTO kyc_verifications (user_id, country, phone, address, id_front_path, id_back_path, selfie_path, id_selfie_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("isssssss", 
            $currentUser['id'],
            $country,
            $phone,
            $address,
            $uploadedFiles['id_front'],
            $uploadedFiles['id_back'],
            $uploadedFiles['selfie'],
            $uploadedFiles['id_selfie']
        );
        
        if ($stmt->execute()) {
            $kycSuccess = true;
        } else {
            $kycErrors['general'] = 'kycSubmissionFailed';
        }
        $stmt->close();
    } else {
        // If there were upload errors, merge them
        $kycErrors = array_merge($kycErrors, $uploadErrors);
    }
}
?>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Navigation -->
    <?php include(V_PATH."topnav.php"); ?>

    <!-- KYC Verification Section -->
    <section class="dashboard-section" style="padding-top: 20px;">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <a href="<?= WEB_URL; ?>/account" class="btn btn-outline-modern mb-3">
                        <i class="fas fa-arrow-left me-2"></i><span data-key="back">Geri</span>
                    </a>
                    <h1 class="h2 fw-bold mb-0" data-key="kycVerification">KYC Doğrulama</h1>
                    <p class="text-muted mt-2" data-key="kycDescription">Kimlik doğrulama işlemini tamamlayarak hesap güvenliğinizi artırın.</p>
                </div>
            </div>

            <?php if ($hasPendingKYC): ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong data-key="kycPending">KYC Doğrulama Bekliyor</strong>
                        <p class="mb-0 mt-2" data-key="kycPendingMessage">KYC doğrulama talebiniz onay bekliyor. Onaylandıktan sonra hesabınız güncellenecektir.</p>
                    </div>
                </div>
            </div>
            <?php elseif ($kycSuccess): ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong data-key="kycSubmitted">KYC Doğrulama Talebi Gönderildi</strong>
                        <p class="mb-0 mt-2" data-key="kycSubmittedMessage">KYC doğrulama talebiniz başarıyla gönderildi. Onay süreci tamamlandığında bilgilendirileceksiniz.</p>
                        <a href="<?= WEB_URL; ?>/account" class="btn btn-modern mt-3" data-key="backToAccount">Hesap Sayfasına Dön</a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <form id="kycForm" method="POST" enctype="multipart/form-data" action="">
                <div class="row g-4">
                    <!-- Personal Information -->
                    <div class="col-12">
                        <div class="glass-card">
                            <h3 class="h5 fw-bold mb-4" data-key="personalInformation">Kişisel Bilgiler</h3>
                            
                            <?php if (!empty($kycErrors['general'])): ?>
                                <div class="alert alert-danger mb-3">
                                    <span data-key="<?php echo htmlspecialchars($kycErrors['general']); ?>">Hata oluştu!</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label" data-key="country">Ülke</label>
                                    <select class="form-select form-select-modern <?= isset($kycErrors['country']) ? 'is-invalid' : '' ?>" name="country" required>
                                        <option value="">Seçiniz</option>
                                        <option value="TR" <?= isset($_POST['country']) && $_POST['country'] == 'TR' ? 'selected' : '' ?>>🇹🇷 Türkiye</option>
                                        <option value="US" <?= isset($_POST['country']) && $_POST['country'] == 'US' ? 'selected' : '' ?>>🇺🇸 United States</option>
                                        <option value="GB" <?= isset($_POST['country']) && $_POST['country'] == 'GB' ? 'selected' : '' ?>>🇬🇧 United Kingdom</option>
                                        <option value="DE" <?= isset($_POST['country']) && $_POST['country'] == 'DE' ? 'selected' : '' ?>>🇩🇪 Germany</option>
                                        <option value="FR" <?= isset($_POST['country']) && $_POST['country'] == 'FR' ? 'selected' : '' ?>>🇫🇷 France</option>
                                        <option value="IT" <?= isset($_POST['country']) && $_POST['country'] == 'IT' ? 'selected' : '' ?>>🇮🇹 Italy</option>
                                        <option value="ES" <?= isset($_POST['country']) && $_POST['country'] == 'ES' ? 'selected' : '' ?>>🇪🇸 Spain</option>
                                        <option value="RU" <?= isset($_POST['country']) && $_POST['country'] == 'RU' ? 'selected' : '' ?>>🇷🇺 Russia</option>
                                        <option value="CN" <?= isset($_POST['country']) && $_POST['country'] == 'CN' ? 'selected' : '' ?>>🇨🇳 China</option>
                                        <option value="JP" <?= isset($_POST['country']) && $_POST['country'] == 'JP' ? 'selected' : '' ?>>🇯🇵 Japan</option>
                                        <option value="KR" <?= isset($_POST['country']) && $_POST['country'] == 'KR' ? 'selected' : '' ?>>🇰🇷 South Korea</option>
                                        <option value="IN" <?= isset($_POST['country']) && $_POST['country'] == 'IN' ? 'selected' : '' ?>>🇮🇳 India</option>
                                        <option value="AE" <?= isset($_POST['country']) && $_POST['country'] == 'AE' ? 'selected' : '' ?>>🇦🇪 UAE</option>
                                        <option value="SA" <?= isset($_POST['country']) && $_POST['country'] == 'SA' ? 'selected' : '' ?>>🇸🇦 Saudi Arabia</option>
                                        <option value="EG" <?= isset($_POST['country']) && $_POST['country'] == 'EG' ? 'selected' : '' ?>>🇪🇬 Egypt</option>
                                        <option value="ZA" <?= isset($_POST['country']) && $_POST['country'] == 'ZA' ? 'selected' : '' ?>>🇿🇦 South Africa</option>
                                        <option value="BR" <?= isset($_POST['country']) && $_POST['country'] == 'BR' ? 'selected' : '' ?>>🇧🇷 Brazil</option>
                                        <option value="MX" <?= isset($_POST['country']) && $_POST['country'] == 'MX' ? 'selected' : '' ?>>🇲🇽 Mexico</option>
                                        <option value="AU" <?= isset($_POST['country']) && $_POST['country'] == 'AU' ? 'selected' : '' ?>>🇦🇺 Australia</option>
                                        <option value="NZ" <?= isset($_POST['country']) && $_POST['country'] == 'NZ' ? 'selected' : '' ?>>🇳🇿 New Zealand</option>
                                    </select>
                                    <?php if (isset($kycErrors['country'])): ?>
                                        <div class="invalid-feedback d-block"><span data-key="<?= htmlspecialchars($kycErrors['country']) ?>"></span></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <label class="form-label" data-key="phoneNumber">Telefon Numarası</label>
                                    <input type="tel" class="form-control form-control-modern <?= isset($kycErrors['phone']) ? 'is-invalid' : '' ?>" name="phone" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" placeholder="+90 555 123 45 67" required>
                                    <?php if (isset($kycErrors['phone'])): ?>
                                        <div class="invalid-feedback d-block"><span data-key="<?= htmlspecialchars($kycErrors['phone']) ?>"></span></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label" data-key="address">Ev Adresi</label>
                                    <textarea class="form-control form-control-modern <?= isset($kycErrors['address']) ? 'is-invalid' : '' ?>" name="address" rows="3" required><?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?></textarea>
                                    <?php if (isset($kycErrors['address'])): ?>
                                        <div class="invalid-feedback d-block"><span data-key="<?= htmlspecialchars($kycErrors['address']) ?>"></span></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Document Uploads -->
                    <div class="col-12">
                        <div class="glass-card">
                            <h3 class="h5 fw-bold mb-4" data-key="documentUploads">Belge Yüklemeleri</h3>
                            <p class="text-muted small mb-4" data-key="documentUploadsInfo">Lütfen aşağıdaki belgeleri yükleyin. Tüm belgeler net ve okunabilir olmalıdır.</p>
                            
                            <div class="row g-4">
                                <div class="col-12 col-md-6">
                                    <label class="form-label" data-key="idFront">Kimlik Ön Yüz</label>
                                    <input type="file" class="form-control form-control-modern <?= isset($kycErrors['id_front']) ? 'is-invalid' : '' ?>" name="id_front" accept="image/jpeg,image/jpg,image/png,image/webp" required>
                                    <?php if (isset($kycErrors['id_front'])): ?>
                                        <div class="invalid-feedback d-block"><span data-key="<?= htmlspecialchars($kycErrors['id_front']) ?>"></span></div>
                                    <?php endif; ?>
                                    <small class="text-muted d-block mt-1" data-key="maxFileSize">Maksimum dosya boyutu: 5MB</small>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <label class="form-label" data-key="idBack">Kimlik Arka Yüz</label>
                                    <input type="file" class="form-control form-control-modern <?= isset($kycErrors['id_back']) ? 'is-invalid' : '' ?>" name="id_back" accept="image/jpeg,image/jpg,image/png,image/webp" required>
                                    <?php if (isset($kycErrors['id_back'])): ?>
                                        <div class="invalid-feedback d-block"><span data-key="<?= htmlspecialchars($kycErrors['id_back']) ?>"></span></div>
                                    <?php endif; ?>
                                    <small class="text-muted d-block mt-1" data-key="maxFileSize">Maksimum dosya boyutu: 5MB</small>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <label class="form-label" data-key="selfie">Selfie</label>
                                    <input type="file" class="form-control form-control-modern <?= isset($kycErrors['selfie']) ? 'is-invalid' : '' ?>" name="selfie" accept="image/jpeg,image/jpg,image/png,image/webp" required>
                                    <?php if (isset($kycErrors['selfie'])): ?>
                                        <div class="invalid-feedback d-block"><span data-key="<?= htmlspecialchars($kycErrors['selfie']) ?>"></span></div>
                                    <?php endif; ?>
                                    <small class="text-muted d-block mt-1" data-key="maxFileSize">Maksimum dosya boyutu: 5MB</small>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <label class="form-label" data-key="idSelfie">Kimlik Ön Yüz + Selfie</label>
                                    <input type="file" class="form-control form-control-modern <?= isset($kycErrors['id_selfie']) ? 'is-invalid' : '' ?>" name="id_selfie" accept="image/jpeg,image/jpg,image/png,image/webp" required>
                                    <?php if (isset($kycErrors['id_selfie'])): ?>
                                        <div class="invalid-feedback d-block"><span data-key="<?= htmlspecialchars($kycErrors['id_selfie']) ?>"></span></div>
                                    <?php endif; ?>
                                    <small class="text-muted d-block mt-1" data-key="maxFileSize">Maksimum dosya boyutu: 5MB</small>
                                    <small class="text-muted d-block mt-1" data-key="idSelfieInfo">Kimliğinizin ön yüzü ile birlikte çekilmiş selfie fotoğrafı</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="col-12">
                        <div class="d-flex gap-3">
                            <button type="submit" name="submit_kyc" class="btn btn-modern btn-lg" data-key="submitKYC">
                                <i class="fas fa-paper-plane me-2"></i><span data-key="submitKYC">KYC Doğrulama Talebini Gönder</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </section>

