<?php
requireAdmin();

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id <= 0) {
    header("Location: " . WEB_URL . "/admin/users");
    exit;
}

$pageTitle = "Kullanıcı Detayı";
$breadcrumbs = [
    ['name' => 'Admin', 'url' => WEB_URL . '/admin'],
    ['name' => 'Kullanıcılar', 'url' => WEB_URL . '/admin/users'],
    ['name' => 'Detay', 'url' => WEB_URL . '/admin/users/view?id=' . $user_id]
];

$success = '';
$error = '';

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        
        // Validate file type (images only)
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
        $fileType = mime_content_type($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            $error = "Geçersiz dosya tipi. Sadece resim dosyaları kabul edilir.";
        } else {
            // Validate file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                $error = "Dosya boyutu çok büyük. Maksimum 5MB olmalıdır.";
            } else {
                // Create avatars directory if it doesn't exist
                $avatarsBaseDir = realpath('./__cs/avatars/');
                if (!$avatarsBaseDir) {
                    mkdir('./__cs/avatars/', 0755, true);
                    $avatarsBaseDir = realpath('./__cs/avatars/');
                }
                
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'user_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $extension;
                $filePath = $avatarsBaseDir . '/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $avatarUrl = '__cs/avatars/' . $filename;
                    
                    // Check if avatar_url column exists, if not add it
                    $checkColumnStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_url'");
                    if ($checkColumnStmt->num_rows == 0) {
                        $conn->query("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(500) DEFAULT NULL AFTER name_surname");
                    }
                    $checkColumnStmt->close();
                    
                    // Update user's avatar_url
                    $updateStmt = $conn->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
                    $updateStmt->bind_param("si", $avatarUrl, $user_id);
                    
                    if ($updateStmt->execute()) {
                        $success = "Profil resmi başarıyla yüklendi";
                        header("Location: " . WEB_URL . "/admin/users/view?id=" . $user_id . "&success=1");
                        exit;
                    } else {
                        $error = "Profil resmi güncellenirken hata oluştu: " . $conn->error;
                    }
                    $updateStmt->close();
                } else {
                    $error = "Dosya yüklenirken hata oluştu";
                }
            }
        }
    } else {
        $error = "Lütfen bir dosya seçin";
    }
}

// Check for success message
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "İşlem başarıyla tamamlandı";
}

// Check if avatar_url column exists, if not add it
$checkColumnStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_url'");
if ($checkColumnStmt->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(500) DEFAULT NULL AFTER name_surname");
}
$checkColumnStmt->close();

// Get user details
$user = null;
$stmt = $conn->prepare("
    SELECT 
        id, email, name_surname, phone, account_level, 
        kyc_verified, referral_code, referred_by, 
        balance, withdrawable_balance, status, 
        avatar_url, password, created_at, updated_at
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Decode password for admin view
if ($user && isset($user['password'])) {
    $user['decoded_password'] = base64_decode($user['password']);
}

if (!$user) {
    header("Location: " . WEB_URL . "/admin/users");
    exit;
}

include(V_PATH."p/admin/layout.php");
?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- User Info Card -->
                <div class="col-md-4">
                    <div class="dashboard-card glass-card p-4">
                        <div class="text-center mb-4">
                            <?php if (!empty($user['avatar_url'])): ?>
                                <img src="<?= WEB_URL; ?>/<?= htmlspecialchars($user['avatar_url']) ?>" 
                                     alt="Profil Resmi" 
                                     class="img-fluid rounded-circle mb-3" 
                                     style="width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                     style="width: 150px; height: 150px;">
                                    <i class="fas fa-user fa-4x text-white"></i>
                                </div>
                            <?php endif; ?>
                            
                            <h4 class="mb-1"><?= htmlspecialchars($user['name_surname'] ?? $user['email']) ?></h4>
                            <p class="text-muted mb-0"><?= htmlspecialchars($user['email']) ?></p>
                            
                            <span class="badge bg-<?= $user['status'] == 'active' ? 'success' : ($user['status'] == 'banned' ? 'danger' : 'warning') ?> mt-2">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </div>
                        
                        <!-- Avatar Upload Form -->
                        <form method="POST" action="<?= WEB_URL; ?>/admin/users/view?id=<?= $user_id ?>" 
                              enctype="multipart/form-data" class="mb-3">
                            <div class="mb-3">
                                <label class="form-label">Profil Resmi Yükle</label>
                                <input type="file" class="form-control" name="avatar" accept="image/jpeg,image/jpg,image/png,image/webp,image/gif" required>
                                <small class="text-muted">Maksimum dosya boyutu: 5MB</small>
                            </div>
                            <button type="submit" name="upload_avatar" class="btn btn-primary w-100">
                                <i class="fas fa-upload me-2"></i>Yükle
                            </button>
                        </form>
                    </div>
                </div>

                <!-- User Details Card -->
                <div class="col-md-8">
                    <div class="dashboard-card glass-card p-4">
                        <h3 class="h5 fw-bold mb-4">Kullanıcı Bilgileri</h3>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Kullanıcı ID</label>
                                <div class="form-control-plaintext"><?= $user['id'] ?></div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Email</label>
                                <div class="form-control-plaintext"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small">İsim Soyisim</label>
                                <div class="form-control-plaintext"><?= htmlspecialchars($user['name_surname'] ?? '-') ?></div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Telefon</label>
                                <div class="form-control-plaintext"><?= htmlspecialchars($user['phone'] ?? '-') ?></div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Şifre</label>
                                <div class="form-control-plaintext">
                                    <code class="text-primary"><?= htmlspecialchars($user['decoded_password'] ?? '') ?></code>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Hesap Seviyesi</label>
                                <div class="form-control-plaintext"><?= $user['account_level'] ?></div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small">KYC Doğrulaması</label>
                                <div class="form-control-plaintext">
                                    <span class="badge bg-<?= $user['kyc_verified'] ? 'success' : 'warning' ?>">
                                        <?= $user['kyc_verified'] ? 'Doğrulanmış' : 'Doğrulanmamış' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Referral Kodu</label>
                                <div class="form-control-plaintext"><?= htmlspecialchars($user['referral_code']) ?></div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Bakiye</label>
                                <div class="form-control-plaintext">
                                    <strong>$<?= number_format(floatval($user['balance']), 2) ?></strong>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Çekilebilir Bakiye</label>
                                <div class="form-control-plaintext">
                                    <strong>$<?= number_format(floatval($user['withdrawable_balance']), 2) ?></strong>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Kayıt Tarihi</label>
                                <div class="form-control-plaintext">
                                    <?= date('d.m.Y H:i', strtotime($user['created_at'])) ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small">Son Güncelleme</label>
                                <div class="form-control-plaintext">
                                    <?= date('d.m.Y H:i', strtotime($user['updated_at'])) ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a href="<?= WEB_URL; ?>/admin/users" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Geri Dön
                            </a>
                        </div>
                    </div>
                </div>
            </div>

<?php include(V_PATH."p/admin/footer.php"); ?>

