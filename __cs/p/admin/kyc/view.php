<?php
requireAdmin();

$kyc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($kyc_id <= 0) {
    header("Location: " . WEB_URL . "/admin/kyc");
    exit;
}

$pageTitle = "KYC Doğrulama Detayı";
$breadcrumbs = [
    ['name' => 'Admin', 'url' => WEB_URL . '/admin'],
    ['name' => 'KYC Doğrulamaları', 'url' => WEB_URL . '/admin/kyc'],
    ['name' => 'Detay', 'url' => WEB_URL . '/admin/kyc/view?id=' . $kyc_id]
];

$success = '';
$error = '';

// Handle KYC status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_kyc_status'])) {
    $status = $_POST['status'] ?? '';
    $admin_note = trim($_POST['admin_note'] ?? '');
    
    if (!in_array($status, ['approved', 'rejected'])) {
        $error = "Geçersiz durum";
    } else {
        $conn->begin_transaction();
        
        try {
            // Get KYC data
            $getKycStmt = $conn->prepare("SELECT user_id FROM kyc_verifications WHERE id = ?");
            $getKycStmt->bind_param("i", $kyc_id);
            $getKycStmt->execute();
            $kycResult = $getKycStmt->get_result();
            $kycData = $kycResult->fetch_assoc();
            $getKycStmt->close();
            
            if (!$kycData) {
                throw new Exception("KYC kaydı bulunamadı");
            }
            
            $user_id = intval($kycData['user_id']);
            
            // Update KYC status
            $updateKycStmt = $conn->prepare("UPDATE kyc_verifications SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?");
            $updateKycStmt->bind_param("ssi", $status, $admin_note, $kyc_id);
            
            if (!$updateKycStmt->execute()) {
                throw new Exception("KYC durumu güncellenemedi: " . $conn->error);
            }
            $updateKycStmt->close();
            
            // Update user's kyc_verified status
            if ($status === 'approved') {
                $updateUserStmt = $conn->prepare("UPDATE users SET kyc_verified = 1 WHERE id = ?");
                $updateUserStmt->bind_param("i", $user_id);
                
                if (!$updateUserStmt->execute()) {
                    throw new Exception("Kullanıcı durumu güncellenemedi: " . $conn->error);
                }
                $updateUserStmt->close();
            } elseif ($status === 'rejected') {
                $updateUserStmt = $conn->prepare("UPDATE users SET kyc_verified = 0 WHERE id = ?");
                $updateUserStmt->bind_param("i", $user_id);
                
                if (!$updateUserStmt->execute()) {
                    throw new Exception("Kullanıcı durumu güncellenemedi: " . $conn->error);
                }
                $updateUserStmt->close();
            }
            
            $conn->commit();
            $success = "KYC durumu başarıyla güncellendi";
            header("Location: " . WEB_URL . "/admin/kyc/view?id=" . $kyc_id . "&success=1");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Hata: " . $e->getMessage();
            error_log("KYC update error: " . $e->getMessage());
        }
    }
}

// Check for success message
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "KYC durumu başarıyla güncellendi";
}

// Get KYC verification details
$kyc = null;
$stmt = $conn->prepare("
    SELECT 
        kv.*,
        u.email,
        u.name_surname,
        u.kyc_verified as user_kyc_verified
    FROM kyc_verifications kv
    INNER JOIN users u ON kv.user_id = u.id
    WHERE kv.id = ?
");
$stmt->bind_param("i", $kyc_id);
$stmt->execute();
$result = $stmt->get_result();
$kyc = $result->fetch_assoc();
$stmt->close();

if (!$kyc) {
    header("Location: " . WEB_URL . "/admin/kyc");
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

            <div class="row mb-4">
                <div class="col-12">
                    <a href="<?= WEB_URL; ?>/admin/kyc" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Geri Dön
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="dashboard-card glass-card p-4">
                        <h3 class="h4 fw-bold mb-4">KYC Doğrulama Detayları</h3>
                        
                        <!-- User Info -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted small">Kullanıcı</label>
                                    <div class="fw-bold"><?= htmlspecialchars($kyc['name_surname'] ?? $kyc['email']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($kyc['email']) ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted small">Durum</label>
                                    <div>
                                        <?php
                                        $statusClass = 'bg-warning';
                                        $statusText = 'Beklemede';
                                        if ($kyc['status'] === 'approved') {
                                            $statusClass = 'bg-success';
                                            $statusText = 'Onaylandı';
                                        } elseif ($kyc['status'] === 'rejected') {
                                            $statusClass = 'bg-danger';
                                            $statusText = 'Reddedildi';
                                        }
                                        ?>
                                        <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Personal Info -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label text-muted small">Ülke</label>
                                    <div class="fw-bold"><?= htmlspecialchars($kyc['country']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label text-muted small">Telefon</label>
                                    <div class="fw-bold"><?= htmlspecialchars($kyc['phone']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label text-muted small">Oluşturulma</label>
                                    <div class="fw-bold"><?= date('d.m.Y H:i', strtotime($kyc['created_at'])) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label text-muted small">Adres</label>
                                    <div class="fw-bold"><?= nl2br(htmlspecialchars($kyc['address'])) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="fw-bold mb-3">Belgeler</h5>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small">Kimlik Ön Yüz</label>
                                <?php if (!empty($kyc['id_front_path'])): ?>
                                    <div class="document-preview">
                                        <img src="<?= WEB_URL; ?>/<?= htmlspecialchars($kyc['id_front_path']) ?>" 
                                             alt="ID Front" 
                                             class="img-fluid rounded border" 
                                             style="max-height: 300px; cursor: pointer;"
                                             onclick="openImageModal('<?= WEB_URL; ?>/<?= htmlspecialchars($kyc['id_front_path']) ?>', 'Kimlik Ön Yüz')">
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted">Belge yüklenmemiş</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small">Kimlik Arka Yüz</label>
                                <?php if (!empty($kyc['id_back_path'])): ?>
                                    <div class="document-preview">
                                        <img src="<?= WEB_URL; ?>/<?= htmlspecialchars($kyc['id_back_path']) ?>" 
                                             alt="ID Back" 
                                             class="img-fluid rounded border" 
                                             style="max-height: 300px; cursor: pointer;"
                                             onclick="openImageModal('<?= WEB_URL; ?>/<?= htmlspecialchars($kyc['id_back_path']) ?>', 'Kimlik Arka Yüz')">
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted">Belge yüklenmemiş</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small">Selfie</label>
                                <?php if (!empty($kyc['selfie_path'])): ?>
                                    <div class="document-preview">
                                        <img src="<?= WEB_URL; ?>/<?= htmlspecialchars($kyc['selfie_path']) ?>" 
                                             alt="Selfie" 
                                             class="img-fluid rounded border" 
                                             style="max-height: 300px; cursor: pointer;"
                                             onclick="openImageModal('<?= WEB_URL; ?>/<?= htmlspecialchars($kyc['selfie_path']) ?>', 'Selfie')">
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted">Belge yüklenmemiş</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small">Kimlik + Selfie</label>
                                <?php if (!empty($kyc['id_selfie_path'])): ?>
                                    <div class="document-preview">
                                        <img src="<?= WEB_URL; ?>/<?= htmlspecialchars($kyc['id_selfie_path']) ?>" 
                                             alt="ID Selfie" 
                                             class="img-fluid rounded border" 
                                             style="max-height: 300px; cursor: pointer;"
                                             onclick="openImageModal('<?= WEB_URL; ?>/<?= htmlspecialchars($kyc['id_selfie_path']) ?>', 'Kimlik + Selfie')">
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted">Belge yüklenmemiş</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Admin Note -->
                        <?php if (!empty($kyc['admin_note'])): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <strong>Admin Notu:</strong>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($kyc['admin_note'])) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Action Form -->
                        <?php if ($kyc['status'] === 'pending'): ?>
                        <div class="row">
                            <div class="col-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title fw-bold mb-3">KYC Durumunu Güncelle</h5>
                                        <form method="POST">
                                            <input type="hidden" name="update_kyc_status" value="1">
                                            <input type="hidden" name="kyc_id" value="<?= $kyc_id ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Durum</label>
                                                <select class="form-select" name="status" required>
                                                    <option value="">Durum Seçiniz</option>
                                                    <option value="approved">Onayla</option>
                                                    <option value="rejected">Reddet</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Admin Notu (Opsiyonel)</label>
                                                <textarea class="form-control" name="admin_note" rows="3" placeholder="KYC doğrulama hakkında notlar..."><?= htmlspecialchars($kyc['admin_note'] ?? '') ?></textarea>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-check me-2"></i>Güncelle
                                                </button>
                                                <a href="<?= WEB_URL; ?>/admin/kyc" class="btn btn-secondary">
                                                    <i class="fas fa-times me-2"></i>İptal
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Belge Görüntüle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="Document" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<script>
function openImageModal(imageSrc, title) {
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    document.getElementById('imageModalLabel').textContent = title;
    document.getElementById('modalImage').src = imageSrc;
    modal.show();
}
</script>

<?php include(V_PATH."p/admin/footer.php"); ?>

