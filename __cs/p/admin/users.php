<?php
requireAdmin();

$pageTitle = "Kullanıcı Yönetimi";
$breadcrumbs = [
    ['name' => 'Admin', 'url' => WEB_URL . '/admin'],
    ['name' => 'Kullanıcılar', 'url' => WEB_URL . '/admin/users']
];

$success = '';
$error = '';

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "Bakiye başarıyla güncellendi";
}
if (isset($_GET['status_success']) && $_GET['status_success'] == '1') {
    $success = "Kullanıcı durumu başarıyla güncellendi";
}

// Handle balance update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_balance'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    
    if ($user_id <= 0) {
        $error = "Geçersiz kullanıcı ID";
    } elseif (empty($action)) {
        $error = "İşlem tipi seçilmedi";
    } elseif ($amount <= 0) {
        $error = "Tutar 0'dan büyük olmalıdır";
    } else {
        $stmt = $conn->prepare("SELECT balance, withdrawable_balance FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $currentBalance = floatval($user['balance']);
            $currentWithdrawableBalance = floatval($user['withdrawable_balance'] ?? 0);
            
            $updateError = false;
            if ($action === 'add') {
                $newBalance = $currentBalance + $amount;
                $newWithdrawableBalance = $currentWithdrawableBalance + $amount; // Para yatırma işleminde her ikisi de artar
            } elseif ($action === 'subtract') {
                $newBalance = max(0, $currentBalance - $amount);
                $newWithdrawableBalance = max(0, $currentWithdrawableBalance - $amount);
            } elseif ($action === 'set') {
                $newBalance = $amount;
                $newWithdrawableBalance = $amount; // Set işleminde her ikisi de aynı değere set edilir
            } else {
                $error = "Geçersiz işlem tipi";
                $updateError = true;
            }
            
            if (!$updateError) {
                $updateStmt = $conn->prepare("UPDATE users SET balance = ?, withdrawable_balance = ? WHERE id = ?");
                $updateStmt->bind_param("ddi", $newBalance, $newWithdrawableBalance, $user_id);
                
                if ($updateStmt->execute()) {
                    $success = "Bakiye başarıyla güncellendi. Yeni bakiye: $" . number_format($newBalance, 2);
                    // Redirect to prevent form resubmission
                    header("Location: " . WEB_URL . "/admin/users?success=1");
                    exit;
                } else {
                    $error = "Bakiye güncellenirken hata oluştu: " . $conn->error;
                }
                $updateStmt->close();
            }
        } else {
            $error = "Kullanıcı bulunamadı";
        }
        $stmt->close();
    }
}

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    
    if ($user_id > 0) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $user_id);
        
        if ($stmt->execute()) {
            $success = "Kullanıcı durumu başarıyla güncellendi";
            // Redirect to prevent form resubmission
            header("Location: " . WEB_URL . "/admin/users?status_success=1");
            exit;
        } else {
            $error = "Durum güncellenirken hata oluştu: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$whereClause = "1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $whereClause .= " AND (email LIKE ? OR name_surname LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

$query = "SELECT id, email, name_surname, balance, withdrawable_balance, status, account_level, created_at FROM users WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM users WHERE $whereClause";
$countStmt = $conn->prepare($countQuery);
if (!empty($search)) {
    $countStmt->bind_param("ss", $searchParam, $searchParam);
}
$countStmt->execute();
$totalUsers = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$totalPages = ceil($totalUsers / $perPage);

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

            <!-- Search and Filters -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card glass-card p-4">
                        <form method="GET" action="<?= WEB_URL; ?>/admin/users" class="row g-3">
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Email veya İsim ile ara...">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Ara
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="row">
                <div class="col-12">
                    <div class="dashboard-card glass-card p-4">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Email</th>
                                        <th>İsim</th>
                                        <th>Bakiye</th>
                                        <th>Çekilebilir</th>
                                        <th>Durum</th>
                                        <th>Level</th>
                                        <th>Kayıt Tarihi</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                Kullanıcı bulunamadı
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?= $user['id'] ?></td>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                                <td><?= htmlspecialchars($user['name_surname'] ?? '-') ?></td>
                                                <td><strong>$<?= number_format(floatval($user['balance']), 2) ?></strong></td>
                                                <td>$<?= number_format(floatval($user['withdrawable_balance']), 2) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $user['status'] == 'active' ? 'success' : ($user['status'] == 'banned' ? 'danger' : 'warning') ?>">
                                                        <?= ucfirst($user['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $user['account_level'] ?></td>
                                                <td><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="<?= WEB_URL; ?>/admin/users/view?id=<?= $user['id'] ?>" 
                                                           class="btn btn-info" 
                                                           aria-label="Detay">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-primary" 
                                                                onclick="openBalanceModal(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['email'])) ?>', <?= floatval($user['balance']) ?>)"
                                                                aria-label="Bakiye Yönetimi">
                                                            <i class="fas fa-wallet"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-warning" 
                                                                onclick="openStatusModal(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['email'])) ?>', '<?= $user['status'] ?>')"
                                                                aria-label="Durum Güncelle">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Önceki</a>
                                    </li>
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Sonraki</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

<!-- Balance Update Modal -->
<div class="modal fade" id="balanceModal" tabindex="-1" aria-labelledby="balanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="balanceModalLabel">Bakiye Yönetimi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= WEB_URL; ?>/admin/users" id="balanceForm">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="balanceUserId" value="">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="text" class="form-control" id="balanceUserEmail" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mevcut Bakiye</label>
                        <input type="text" class="form-control" id="currentBalance" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">İşlem Tipi</label>
                        <select class="form-select" name="action" id="balanceAction" required>
                            <option value="add">Bakiye Ekle</option>
                            <option value="subtract">Bakiye Çıkar</option>
                            <option value="set">Bakiye Belirle</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tutar</label>
                        <input type="number" step="0.01" class="form-control" 
                               name="amount" id="balanceAmount" required min="0" placeholder="0.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="update_balance" class="btn btn-primary">Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusModalLabel">Durum Güncelle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= WEB_URL; ?>/admin/users" id="statusForm">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="statusUserId" value="">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="text" class="form-control" id="statusUserEmail" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Durum</label>
                        <select class="form-select" name="status" id="userStatus" required>
                            <option value="active">Active</option>
                            <option value="banned">Banned</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="update_status" class="btn btn-warning">Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openBalanceModal(userId, userEmail, currentBalance) {
    document.getElementById('balanceUserId').value = userId;
    document.getElementById('balanceUserEmail').value = userEmail;
    document.getElementById('balanceModalLabel').textContent = 'Bakiye Yönetimi - ' + userEmail;
    document.getElementById('currentBalance').value = '$' + parseFloat(currentBalance).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    document.getElementById('balanceAction').value = 'add';
    document.getElementById('balanceAmount').value = '';
    
    const modalElement = document.getElementById('balanceModal');
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
}

function openStatusModal(userId, userEmail, currentStatus) {
    document.getElementById('statusUserId').value = userId;
    document.getElementById('statusUserEmail').value = userEmail;
    document.getElementById('statusModalLabel').textContent = 'Durum Güncelle - ' + userEmail;
    document.getElementById('userStatus').value = currentStatus;
    
    const modalElement = document.getElementById('statusModal');
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
}

// Ensure modals are fully functional
document.addEventListener('DOMContentLoaded', function() {
    // Fix any z-index issues when modal opens
    const balanceModal = document.getElementById('balanceModal');
    const statusModal = document.getElementById('statusModal');
    
    [balanceModal, statusModal].forEach(function(modal) {
        if (modal) {
            modal.addEventListener('shown.bs.modal', function() {
                // Ensure modal is on top
                this.style.zIndex = '1060';
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.style.zIndex = '1055';
                }
            });
        }
    });
});
</script>

<?php include(V_PATH."p/admin/footer.php"); ?>
