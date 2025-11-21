<?php
// Require admin access
requireAdmin();

$pageTitle = "Dashboard";
$breadcrumbs = [
    ['name' => 'Admin', 'url' => WEB_URL . '/admin'],
    ['name' => 'Dashboard', 'url' => WEB_URL . '/admin']
];

// Get statistics
$stats = [];

// Total users
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
$stmt->execute();
$result = $stmt->get_result();
$stats['total_users'] = $result->fetch_assoc()['total'];
$stmt->close();

// Active users
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
$stmt->execute();
$result = $stmt->get_result();
$stats['active_users'] = $result->fetch_assoc()['total'];
$stmt->close();

// Total traders
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM traders");
$stmt->execute();
$result = $stmt->get_result();
$stats['total_traders'] = $result->fetch_assoc()['total'];
$stmt->close();

// Active trades
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM trades WHERE status = 'open'");
$stmt->execute();
$result = $stmt->get_result();
$stats['active_trades'] = $result->fetch_assoc()['total'];
$stmt->close();

// Total balance (sum of all user balances)
$stmt = $conn->prepare("SELECT SUM(balance) as total FROM users");
$stmt->execute();
$result = $stmt->get_result();
$stats['total_balance'] = $result->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Recent users (last 5)
$stmt = $conn->prepare("SELECT id, email, name_surname, balance, status, created_at FROM users ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recentUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Recent traders (last 5)
$stmt = $conn->prepare("SELECT id, username, name, followers, roi_30d, status FROM traders ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recentTraders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include(V_PATH."p/admin/layout.php");
?>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card glass-card text-center p-4">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <div class="stat-icon bg-primary text-white rounded-circle p-3">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                        <h3 class="h4 fw-bold mb-1"><?= number_format($stats['total_users']) ?></h3>
                        <p class="text-muted mb-0">Toplam Kullanıcı</p>
                        <small class="text-success">
                            <i class="fas fa-check-circle me-1"></i><?= number_format($stats['active_users']) ?> Aktif
                        </small>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card glass-card text-center p-4">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <div class="stat-icon bg-success text-white rounded-circle p-3">
                                <i class="fas fa-chart-line fa-2x"></i>
                            </div>
                        </div>
                        <h3 class="h4 fw-bold mb-1"><?= number_format($stats['total_traders']) ?></h3>
                        <p class="text-muted mb-0">Toplam Trader</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card glass-card text-center p-4">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <div class="stat-icon bg-warning text-white rounded-circle p-3">
                                <i class="fas fa-exchange-alt fa-2x"></i>
                            </div>
                        </div>
                        <h3 class="h4 fw-bold mb-1"><?= number_format($stats['active_trades']) ?></h3>
                        <p class="text-muted mb-0">Aktif Trade</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="dashboard-card glass-card text-center p-4">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <div class="stat-icon bg-info text-white rounded-circle p-3">
                                <i class="fas fa-wallet fa-2x"></i>
                            </div>
                        </div>
                        <h3 class="h4 fw-bold mb-1">$<?= number_format($stats['total_balance'], 2) ?></h3>
                        <p class="text-muted mb-0">Toplam Bakiye</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="dashboard-card glass-card p-4">
                        <h3 class="h5 fw-bold mb-4">
                            <i class="fas fa-bolt me-2"></i>Hızlı İşlemler
                        </h3>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <a href="<?= WEB_URL; ?>/admin/traders/add" class="btn btn-primary w-100 btn-modern">
                                    <i class="fas fa-user-plus me-2"></i>Trader Ekle
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="<?= WEB_URL; ?>/admin/trades/add" class="btn btn-success w-100 btn-modern">
                                    <i class="fas fa-plus-circle me-2"></i>Trade Ekle
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="<?= WEB_URL; ?>/admin/users" class="btn btn-info w-100 btn-modern">
                                    <i class="fas fa-users-cog me-2"></i>Kullanıcı Yönetimi
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="<?= WEB_URL; ?>/admin/traders" class="btn btn-warning w-100 btn-modern">
                                    <i class="fas fa-list me-2"></i>Trader Listesi
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Users and Traders -->
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="dashboard-card glass-card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="h5 fw-bold mb-0">
                                <i class="fas fa-user-clock me-2"></i>Son Kullanıcılar
                            </h3>
                            <a href="<?= WEB_URL; ?>/admin/users" class="btn btn-sm btn-outline-primary">Tümünü Gör</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>İsim</th>
                                        <th>Bakiye</th>
                                        <th>Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsers as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><?= htmlspecialchars($user['name_surname'] ?? '-') ?></td>
                                        <td>$<?= number_format(floatval($user['balance']), 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $user['status'] == 'active' ? 'success' : 'danger' ?>">
                                                <?= ucfirst($user['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="dashboard-card glass-card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="h5 fw-bold mb-0">
                                <i class="fas fa-chart-line me-2"></i>Son Traders
                            </h3>
                            <a href="<?= WEB_URL; ?>/admin/traders" class="btn btn-sm btn-outline-primary">Tümünü Gör</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>İsim</th>
                                        <th>ROI 30d</th>
                                        <th>Takipçi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTraders as $trader): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($trader['username']) ?></td>
                                        <td><?= htmlspecialchars($trader['name']) ?></td>
                                        <td>
                                            <span class="text-<?= floatval($trader['roi_30d']) >= 0 ? 'success' : 'danger' ?>">
                                                <?= number_format(floatval($trader['roi_30d']), 2) ?>%
                                            </span>
                                        </td>
                                        <td><?= number_format(intval($trader['followers'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

<?php include(V_PATH."p/admin/footer.php"); ?>
