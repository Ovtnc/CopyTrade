<?php
requireAdmin();

$trader_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($trader_id <= 0) {
    header("Location: " . WEB_URL . "/admin/traders");
    exit;
}

$pageTitle = "Trader Detayı";
$breadcrumbs = [
    ['name' => 'Admin', 'url' => WEB_URL . '/admin'],
    ['name' => 'Traders', 'url' => WEB_URL . '/admin/traders'],
    ['name' => 'Detay', 'url' => WEB_URL . '/admin/traders/view?id=' . $trader_id]
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
                $filename = 'trader_' . $trader_id . '_' . time() . '_' . uniqid() . '.' . $extension;
                $filePath = $avatarsBaseDir . '/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    $avatarUrl = '__cs/avatars/' . $filename;
                    
                    // Update trader's avatar_url
                    $updateStmt = $conn->prepare("UPDATE traders SET avatar_url = ? WHERE id = ?");
                    $updateStmt->bind_param("si", $avatarUrl, $trader_id);
                    
                    if ($updateStmt->execute()) {
                        $success = "Trader resmi başarıyla yüklendi";
                        header("Location: " . WEB_URL . "/admin/traders/view?id=" . $trader_id . "&success=1");
                        exit;
                    } else {
                        $error = "Trader resmi güncellenirken hata oluştu: " . $conn->error;
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

// Handle trader update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_trader'])) {
    $user_id = !empty($_POST['user_id']) ? intval($_POST['user_id']) : null;
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $avatar_url = trim($_POST['avatar_url'] ?? '');
    $roi_30d = floatval($_POST['roi_30d'] ?? 0);
    $followers = intval($_POST['followers'] ?? 0);
    $aum = floatval($_POST['aum'] ?? 0);
    $mdd_30d = floatval($_POST['mdd_30d'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($username)) {
        $error = 'Username gerekli';
    } elseif (empty($name)) {
        $error = 'İsim gerekli';
    } else {
        // Check if username exists for another trader
        $checkStmt = $conn->prepare("SELECT id FROM traders WHERE username = ? AND id != ?");
        $checkStmt->bind_param("si", $username, $trader_id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $error = 'Bu username zaten kullanılıyor';
        } else {
            // Update trader
            if ($user_id) {
                $updateStmt = $conn->prepare("UPDATE traders SET user_id = ?, username = ?, name = ?, description = ?, avatar_url = ?, roi_30d = ?, followers = ?, aum = ?, mdd_30d = ?, status = ? WHERE id = ?");
                $updateStmt->bind_param("issssddidsi", $user_id, $username, $name, $description, $avatar_url, $roi_30d, $followers, $aum, $mdd_30d, $status, $trader_id);
            } else {
                $updateStmt = $conn->prepare("UPDATE traders SET user_id = NULL, username = ?, name = ?, description = ?, avatar_url = ?, roi_30d = ?, followers = ?, aum = ?, mdd_30d = ?, status = ? WHERE id = ?");
                $updateStmt->bind_param("sssddidsi", $username, $name, $description, $avatar_url, $roi_30d, $followers, $aum, $mdd_30d, $status, $trader_id);
            }
            
            if ($updateStmt->execute()) {
                $success = "Trader bilgileri başarıyla güncellendi";
                // Refresh trader data
                header("Location: " . WEB_URL . "/admin/traders/view?id=" . $trader_id . "&success=1");
                exit;
            } else {
                $error = "Trader güncellenirken hata oluştu: " . $conn->error;
            }
            $updateStmt->close();
        }
        $checkStmt->close();
    }
}

// Check for success message
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "İşlem başarıyla tamamlandı";
}

// Get all users for dropdown
$usersStmt = $conn->prepare("SELECT id, email, name_surname FROM users ORDER BY email ASC");
$usersStmt->execute();
$allUsers = $usersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$usersStmt->close();

// Get trader details
$trader = null;
$stmt = $conn->prepare("
    SELECT 
        t.id, t.user_id, t.username, t.name, t.description, 
        t.avatar_url, t.roi_30d, t.followers, t.aum, t.mdd_30d, 
        t.status, t.created_at, t.updated_at,
        u.email as user_email, u.name_surname as user_name
    FROM traders t 
    LEFT JOIN users u ON t.user_id = u.id 
    WHERE t.id = ?
");
$stmt->bind_param("i", $trader_id);
$stmt->execute();
$result = $stmt->get_result();
$trader = $result->fetch_assoc();
$stmt->close();

if (!$trader) {
    header("Location: " . WEB_URL . "/admin/traders");
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
                <!-- Trader Avatar Card -->
                <div class="col-md-4">
                    <div class="dashboard-card glass-card p-4">
                        <div class="text-center mb-4">
                            <?php if (!empty($trader['avatar_url'])): ?>
                                <img src="<?= WEB_URL; ?>/<?= htmlspecialchars($trader['avatar_url']) ?>" 
                                     alt="Trader Avatar" 
                                     class="img-fluid rounded-circle mb-3" 
                                     style="width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                     style="width: 150px; height: 150px;">
                                    <i class="fas fa-user-tie fa-4x text-white"></i>
                                </div>
                            <?php endif; ?>
                            
                            <h4 class="mb-1"><?= htmlspecialchars($trader['name']) ?></h4>
                            <p class="text-muted mb-0">@<?= htmlspecialchars($trader['username']) ?></p>
                            
                            <span class="badge bg-<?= $trader['status'] == 'active' ? 'success' : 'secondary' ?> mt-2">
                                <?= ucfirst($trader['status']) ?>
                            </span>
                        </div>
                        
                        <!-- Avatar Upload Form -->
                        <form method="POST" action="<?= WEB_URL; ?>/admin/traders/view?id=<?= $trader_id ?>" 
                              enctype="multipart/form-data" class="mb-3">
                            <div class="mb-3">
                                <label class="form-label">Trader Resmi Yükle</label>
                                <input type="file" class="form-control" name="avatar" accept="image/jpeg,image/jpg,image/png,image/webp,image/gif" required>
                                <small class="text-muted">Maksimum dosya boyutu: 5MB</small>
                            </div>
                            <button type="submit" name="upload_avatar" class="btn btn-primary w-100">
                                <i class="fas fa-upload me-2"></i>Resim Yükle
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Trader Details Form -->
                <div class="col-md-8">
                    <div class="dashboard-card glass-card p-4">
                        <h3 class="h5 fw-bold mb-4">Trader Bilgileri</h3>
                        
                        <form method="POST" action="<?= WEB_URL; ?>/admin/traders/view?id=<?= $trader_id ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">ID</label>
                                    <div class="form-control-plaintext"><?= $trader['id'] ?></div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="user_id" class="form-label">Kullanıcı (User ID)</label>
                                    <select class="form-select" id="user_id" name="user_id">
                                        <option value="">-- Kullanıcı Seçin veya Boş Bırakın --</option>
                                        <?php foreach ($allUsers as $user): ?>
                                            <option value="<?= $user['id'] ?>" 
                                                    <?= ($trader['user_id'] == $user['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($user['email']) ?> 
                                                <?= !empty($user['name_surname']) ? '(' . htmlspecialchars($user['name_surname']) . ')' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Trader'ı bir kullanıcıya bağlamak için seçin</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?= htmlspecialchars($trader['username']) ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="name" class="form-label">İsim <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?= htmlspecialchars($trader['name']) ?>" required>
                                </div>
                                
                                <div class="col-12">
                                    <label for="description" class="form-label">Açıklama</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($trader['description'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <label for="avatar_url" class="form-label">Avatar URL</label>
                                    <input type="url" class="form-control" id="avatar_url" name="avatar_url" 
                                           value="<?= htmlspecialchars($trader['avatar_url'] ?? '') ?>" 
                                           placeholder="https://example.com/avatar.jpg">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="roi_30d" class="form-label">ROI 30 Gün (%)</label>
                                    <input type="number" step="0.01" class="form-control" id="roi_30d" name="roi_30d" 
                                           value="<?= htmlspecialchars($trader['roi_30d']) ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="mdd_30d" class="form-label">MDD 30 Gün (%)</label>
                                    <input type="number" step="0.01" class="form-control" id="mdd_30d" name="mdd_30d" 
                                           value="<?= htmlspecialchars($trader['mdd_30d']) ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="followers" class="form-label">Takipçi Sayısı</label>
                                    <input type="number" step="1" class="form-control" id="followers" name="followers" 
                                           value="<?= htmlspecialchars($trader['followers']) ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="aum" class="form-label">AUM (Assets Under Management)</label>
                                    <input type="number" step="0.01" class="form-control" id="aum" name="aum" 
                                           value="<?= htmlspecialchars($trader['aum']) ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Durum</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" <?= $trader['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $trader['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Oluşturulma Tarihi</label>
                                    <div class="form-control-plaintext">
                                        <?= date('d.m.Y H:i', strtotime($trader['created_at'])) ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Son Güncelleme</label>
                                    <div class="form-control-plaintext">
                                        <?= date('d.m.Y H:i', strtotime($trader['updated_at'])) ?>
                                    </div>
                                </div>
                                
                                <?php if ($trader['user_id'] && !empty($trader['user_email'])): ?>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Bağlı Kullanıcı</label>
                                    <div class="form-control-plaintext">
                                        <span class="badge bg-info">
                                            <i class="fas fa-user me-1"></i>
                                            <?= htmlspecialchars($trader['user_email']) ?>
                                            <?php if (!empty($trader['user_name'])): ?>
                                                <br><small><?= htmlspecialchars($trader['user_name']) ?></small>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" name="update_trader" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Güncelle
                                </button>
                                <a href="<?= WEB_URL; ?>/admin/traders" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Geri Dön
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

<?php include(V_PATH."p/admin/footer.php"); ?>

