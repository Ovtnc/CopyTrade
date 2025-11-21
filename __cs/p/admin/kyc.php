<?php
requireAdmin();

$pageTitle = "KYC Doğrulamaları";
$breadcrumbs = [
    ['name' => 'Admin', 'url' => WEB_URL . '/admin'],
    ['name' => 'KYC Doğrulamaları', 'url' => WEB_URL . '/admin/kyc']
];

$success = '';
$error = '';

// Handle KYC status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_kyc_status'])) {
    $kyc_id = intval($_POST['kyc_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $admin_note = trim($_POST['admin_note'] ?? '');
    
    if ($kyc_id <= 0) {
        $error = "Geçersiz KYC ID";
    } elseif (!in_array($status, ['approved', 'rejected'])) {
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
            header("Location: " . WEB_URL . "/admin/kyc?success=1");
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

// Get filter
$filter = $_GET['filter'] ?? 'all'; // all, pending, approved, rejected

// Get KYC verifications with user info
$kycVerifications = [];
$totalKYC = 0;

try {
    // Count total
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM kyc_verifications");
    if ($countStmt) {
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $countData = $countResult->fetch_assoc();
        $totalKYC = intval($countData['total'] ?? 0);
        $countStmt->close();
    }
    
    // Build query based on filter
    $whereClause = "";
    if ($filter === 'pending') {
        $whereClause = "WHERE kv.status = 'pending'";
    } elseif ($filter === 'approved') {
        $whereClause = "WHERE kv.status = 'approved'";
    } elseif ($filter === 'rejected') {
        $whereClause = "WHERE kv.status = 'rejected'";
    }
    
    $stmt = $conn->prepare("
        SELECT 
            kv.*,
            u.email,
            u.name_surname,
            u.kyc_verified as user_kyc_verified
        FROM kyc_verifications kv
        INNER JOIN users u ON kv.user_id = u.id
        $whereClause
        ORDER BY kv.created_at DESC
        LIMIT 100
    ");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $kycVerifications = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error = "Sorgu hazırlanırken hata oluştu: " . $conn->error;
    }
} catch (Exception $e) {
    $error = "Hata: " . $e->getMessage();
    error_log("Admin KYC query error: " . $e->getMessage());
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
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <div>
                        <?php if ($totalKYC > 0): ?>
                            <span class="text-muted">Toplam <?= $totalKYC ?> KYC doğrulaması bulundu</span>
                        <?php endif; ?>
                    </div>
                    <div class="btn-group" role="group">
                        <a href="<?= WEB_URL; ?>/admin/kyc?filter=all" class="btn btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">
                            Tümü
                        </a>
                        <a href="<?= WEB_URL; ?>/admin/kyc?filter=pending" class="btn btn-outline-warning <?= $filter === 'pending' ? 'active' : '' ?>">
                            Bekleyen
                        </a>
                        <a href="<?= WEB_URL; ?>/admin/kyc?filter=approved" class="btn btn-outline-success <?= $filter === 'approved' ? 'active' : '' ?>">
                            Onaylanan
                        </a>
                        <a href="<?= WEB_URL; ?>/admin/kyc?filter=rejected" class="btn btn-outline-danger <?= $filter === 'rejected' ? 'active' : '' ?>">
                            Reddedilen
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="dashboard-card glass-card p-4">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Kullanıcı</th>
                                        <th>Ülke</th>
                                        <th>Telefon</th>
                                        <th>Durum</th>
                                        <th>Oluşturulma</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($kycVerifications)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                KYC doğrulaması bulunamadı
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($kycVerifications as $kyc): ?>
                                            <tr>
                                                <td><?= $kyc['id'] ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($kyc['name_surname'] ?? $kyc['email']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($kyc['email']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($kyc['country']) ?></td>
                                                <td><?= htmlspecialchars($kyc['phone']) ?></td>
                                                <td>
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
                                                </td>
                                                <td><?= date('d.m.Y H:i', strtotime($kyc['created_at'])) ?></td>
                                                <td>
                                                    <a href="<?= WEB_URL; ?>/admin/kyc/view?id=<?= $kyc['id'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i> Görüntüle
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

<?php include(V_PATH."p/admin/footer.php"); ?>

