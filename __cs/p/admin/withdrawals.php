<?php
requireAdmin();

$pageTitle = "Para Çekme Talepleri";
$breadcrumbs = [
    ['name' => 'Admin', 'url' => WEB_URL . '/admin'],
    ['name' => 'Para Çekme Talepleri', 'url' => WEB_URL . '/admin/withdrawals']
];

$success = '';
$error = '';

// Handle withdrawal approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_withdrawal'])) {
        $transaction_id = intval($_POST['transaction_id'] ?? 0);
        $tx_hash = trim($_POST['tx_hash'] ?? '');
        $admin_note = trim($_POST['admin_note'] ?? '');
        
        if ($transaction_id <= 0) {
            $error = "Geçersiz transaction ID";
        } else {
            // Get transaction details
            $stmt = $conn->prepare("SELECT id, user_id, amount, status FROM transactions WHERE id = ? AND type = 'withdraw'");
            $stmt->bind_param("i", $transaction_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = "Transaction bulunamadı";
            } else {
                $transaction = $result->fetch_assoc();
                
                if ($transaction['status'] !== 'pending') {
                    $error = "Bu transaction zaten işlenmiş";
                } else {
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Update transaction status (balance already deducted when request was created)
                        $updateStmt = $conn->prepare("UPDATE transactions SET status = 'completed', tx_hash = ?, admin_note = ?, updated_at = NOW() WHERE id = ?");
                        $updateStmt->bind_param("ssi", $tx_hash, $admin_note, $transaction_id);
                        $updateStmt->execute();
                        $updateStmt->close();
                        
                        // Balance was already deducted when withdrawal request was created
                        // No need to deduct again, just update the status
                        $conn->commit();
                        $success = "Para çekme talebi onaylandı";
                        header("Location: " . WEB_URL . "/admin/withdrawals?success=1");
                        exit;
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Hata: " . $e->getMessage();
                    }
                }
            }
            $stmt->close();
        }
    } elseif (isset($_POST['reject_withdrawal'])) {
        $transaction_id = intval($_POST['transaction_id'] ?? 0);
        $admin_note = trim($_POST['admin_note'] ?? '');
        
        if ($transaction_id <= 0) {
            $error = "Geçersiz transaction ID";
        } else {
            // Get transaction details
            $stmt = $conn->prepare("SELECT id, user_id, amount, status FROM transactions WHERE id = ? AND type = 'withdraw'");
            $stmt->bind_param("i", $transaction_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $error = "Transaction bulunamadı";
            } else {
                $transaction = $result->fetch_assoc();
                
                if ($transaction['status'] !== 'pending') {
                    $error = "Bu transaction zaten işlenmiş";
                } else {
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Update transaction status to rejected
                        $updateStmt = $conn->prepare("UPDATE transactions SET status = 'rejected', admin_note = ?, updated_at = NOW() WHERE id = ?");
                        $updateStmt->bind_param("si", $admin_note, $transaction_id);
                        $updateStmt->execute();
                        $updateStmt->close();
                        
                        // Return the amount to user's balance (it was deducted when request was created)
                        $amount = floatval($transaction['amount']);
                        $returnStmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $returnStmt->bind_param("di", $amount, $transaction['user_id']);
                        $returnStmt->execute();
                        
                        if ($returnStmt->affected_rows > 0) {
                            $conn->commit();
                            $success = "Para çekme talebi reddedildi ve bakiye geri eklendi";
                            header("Location: " . WEB_URL . "/admin/withdrawals?success=1");
                            exit;
                        } else {
                            $conn->rollback();
                            $error = "Bakiye güncellenemedi";
                        }
                        $returnStmt->close();
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Hata: " . $e->getMessage();
                    }
                }
            }
            $stmt->close();
        }
    }
}

// Check for success message
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "İşlem başarıyla tamamlandı";
}

// Get filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$validStatuses = ['pending', 'completed', 'rejected', 'all'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = 'pending';
}

// Get withdrawals
$whereClause = "t.type = 'withdraw'";
if ($statusFilter !== 'all') {
    $whereClause .= " AND t.status = '" . $conn->real_escape_string($statusFilter) . "'";
}

$stmt = $conn->prepare("
    SELECT 
        t.id, t.user_id, t.network, t.token, t.amount, t.address, 
        t.network_fee, t.receive_amount, t.status, t.admin_note, 
        t.tx_hash, t.created_at, t.updated_at,
        u.email, u.name_surname
    FROM transactions t
    INNER JOIN users u ON t.user_id = u.id
    WHERE $whereClause
    ORDER BY t.created_at DESC
");
$stmt->execute();
$withdrawals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$statsStmt = $conn->prepare("
    SELECT 
        status,
        COUNT(*) as count,
        SUM(amount) as total_amount
    FROM transactions
    WHERE type = 'withdraw'
    GROUP BY status
");
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$stats = [];
while ($row = $statsResult->fetch_assoc()) {
    $stats[$row['status']] = [
        'count' => intval($row['count']),
        'total' => floatval($row['total_amount'])
    ];
}
$statsStmt->close();

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

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="dashboard-card glass-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Bekleyen</h6>
                                <h4 class="mb-0 text-warning"><?= $stats['pending']['count'] ?? 0 ?></h4>
                                <small class="text-muted">$<?= number_format($stats['pending']['total'] ?? 0, 2) ?></small>
                            </div>
                            <i class="fas fa-clock fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card glass-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Tamamlanan</h6>
                                <h4 class="mb-0 text-success"><?= $stats['completed']['count'] ?? 0 ?></h4>
                                <small class="text-muted">$<?= number_format($stats['completed']['total'] ?? 0, 2) ?></small>
                            </div>
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card glass-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Reddedilen</h6>
                                <h4 class="mb-0 text-danger"><?= $stats['rejected']['count'] ?? 0 ?></h4>
                                <small class="text-muted">$<?= number_format($stats['rejected']['total'] ?? 0, 2) ?></small>
                            </div>
                            <i class="fas fa-times-circle fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card glass-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Toplam</h6>
                                <h4 class="mb-0"><?= count($withdrawals) ?></h4>
                                <small class="text-muted">Tüm Talepler</small>
                            </div>
                            <i class="fas fa-list fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="row mb-3">
                <div class="col-12">
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="?status=pending">
                                Bekleyen (<?= $stats['pending']['count'] ?? 0 ?>)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $statusFilter === 'completed' ? 'active' : '' ?>" href="?status=completed">
                                Tamamlanan (<?= $stats['completed']['count'] ?? 0 ?>)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $statusFilter === 'rejected' ? 'active' : '' ?>" href="?status=rejected">
                                Reddedilen (<?= $stats['rejected']['count'] ?? 0 ?>)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $statusFilter === 'all' ? 'active' : '' ?>" href="?status=all">
                                Tümü
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Withdrawals Table -->
            <div class="row">
                <div class="col-12">
                    <div class="dashboard-card glass-card p-4">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Kullanıcı</th>
                                        <th>Ağ/Token</th>
                                        <th>Miktar</th>
                                        <th>Adres</th>
                                        <th>Durum</th>
                                        <th>TX Hash</th>
                                        <th>Tarih</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($withdrawals)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                Henüz para çekme talebi yok
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($withdrawals as $withdrawal): 
                                            $statusClass = [
                                                'pending' => 'warning',
                                                'completed' => 'success',
                                                'rejected' => 'danger',
                                                'processing' => 'info',
                                                'cancelled' => 'secondary'
                                            ][$withdrawal['status']] ?? 'secondary';
                                            
                                            $statusText = [
                                                'pending' => 'Beklemede',
                                                'completed' => 'Tamamlandı',
                                                'rejected' => 'Reddedildi',
                                                'processing' => 'İşlemde',
                                                'cancelled' => 'İptal Edildi'
                                            ][$withdrawal['status']] ?? $withdrawal['status'];
                                            
                                            $networkNames = [
                                                'eth' => 'Ethereum',
                                                'bnb' => 'BSC',
                                                'trx' => 'Tron'
                                            ];
                                            
                                            $tokenNames = [
                                                'usdt' => 'USDT',
                                                'usdc' => 'USDC',
                                                'eth' => 'ETH',
                                                'bnb' => 'BNB',
                                                'trx' => 'TRX'
                                            ];
                                        ?>
                                        <tr>
                                            <td><?= $withdrawal['id'] ?></td>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($withdrawal['email']) ?></strong>
                                                    <?php if (!empty($withdrawal['name_surname'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($withdrawal['name_surname']) ?></small>
                                                    <?php endif; ?>
                                                    <br><small class="text-muted">ID: <?= $withdrawal['user_id'] ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= $networkNames[$withdrawal['network']] ?? $withdrawal['network'] ?></span>
                                                <span class="badge bg-secondary"><?= $tokenNames[$withdrawal['token']] ?? $withdrawal['token'] ?></span>
                                            </td>
                                            <td>
                                                <strong>$<?= number_format(floatval($withdrawal['amount']), 2) ?></strong>
                                                <?php if (floatval($withdrawal['network_fee']) > 0): ?>
                                                    <br><small class="text-muted">Fee: $<?= number_format(floatval($withdrawal['network_fee']), 2) ?></small>
                                                    <br><small class="text-muted">Alınacak: $<?= number_format(floatval($withdrawal['receive_amount']), 2) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code class="small"><?= htmlspecialchars(substr($withdrawal['address'], 0, 20)) ?>...</code>
                                                <button class="btn btn-sm btn-link p-0 ms-1" onclick="copyToClipboard('<?= htmlspecialchars($withdrawal['address']) ?>')" title="Kopyala">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($withdrawal['tx_hash'])): ?>
                                                    <code class="small"><?= htmlspecialchars(substr($withdrawal['tx_hash'], 0, 15)) ?>...</code>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?= date('d.m.Y H:i', strtotime($withdrawal['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($withdrawal['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#approveModal<?= $withdrawal['id'] ?>" title="Onayla">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $withdrawal['id'] ?>" title="Reddet">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <?php if (!empty($withdrawal['admin_note'])): ?>
                                                        <button class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="<?= htmlspecialchars($withdrawal['admin_note']) ?>">
                                                            <i class="fas fa-info-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
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

            <!-- Modals -->
            <?php if (!empty($withdrawals)): ?>
                <?php foreach ($withdrawals as $withdrawal): ?>
                    <?php if ($withdrawal['status'] === 'pending'): ?>
                        <!-- Approve Modal -->
                        <div class="modal fade" id="approveModal<?= $withdrawal['id'] ?>" tabindex="-1" aria-labelledby="approveModalLabel<?= $withdrawal['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="approveModalLabel<?= $withdrawal['id'] ?>">Para Çekme Talebini Onayla</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="transaction_id" value="<?= $withdrawal['id'] ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Kullanıcı</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($withdrawal['email']) ?>" readonly>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Miktar</label>
                                                <input type="text" class="form-control" value="$<?= number_format(floatval($withdrawal['amount']), 2) ?>" readonly>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Çekilecek Adres</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control font-monospace" value="<?= htmlspecialchars($withdrawal['address']) ?>" readonly>
                                                    <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('<?= htmlspecialchars($withdrawal['address']) ?>')">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="tx_hash<?= $withdrawal['id'] ?>" class="form-label">Transaction Hash <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control font-monospace" id="tx_hash<?= $withdrawal['id'] ?>" name="tx_hash" placeholder="0x..." required>
                                                <small class="text-muted">Para gönderim işleminin transaction hash'i</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="admin_note_approve<?= $withdrawal['id'] ?>" class="form-label">Admin Notu (Opsiyonel)</label>
                                                <textarea class="form-control" id="admin_note_approve<?= $withdrawal['id'] ?>" name="admin_note" rows="3" placeholder="Not ekleyin..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                            <button type="submit" name="approve_withdrawal" class="btn btn-success">
                                                <i class="fas fa-check me-2"></i>Onayla ve Para Gönder
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal<?= $withdrawal['id'] ?>" tabindex="-1" aria-labelledby="rejectModalLabel<?= $withdrawal['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="rejectModalLabel<?= $withdrawal['id'] ?>">Para Çekme Talebini Reddet</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="transaction_id" value="<?= $withdrawal['id'] ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Kullanıcı</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($withdrawal['email']) ?>" readonly>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Miktar</label>
                                                <input type="text" class="form-control" value="$<?= number_format(floatval($withdrawal['amount']), 2) ?>" readonly>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="admin_note_reject<?= $withdrawal['id'] ?>" class="form-label">Red Sebebi <span class="text-danger">*</span></label>
                                                <textarea class="form-control" id="admin_note_reject<?= $withdrawal['id'] ?>" name="admin_note" rows="3" placeholder="Red sebebini yazın..." required></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                            <button type="submit" name="reject_withdrawal" class="btn btn-danger">
                                                <i class="fas fa-times me-2"></i>Reddet
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('Adres kopyalandı!');
    }, function(err) {
        console.error('Kopyalama hatası:', err);
    });
}

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

<?php include(V_PATH."p/admin/footer.php"); ?>

