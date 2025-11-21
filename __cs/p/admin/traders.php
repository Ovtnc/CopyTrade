<?php
requireAdmin();

$pageTitle = "Trader Yönetimi";
$breadcrumbs = [
    ['name' => 'Admin', 'url' => WEB_URL . '/admin'],
    ['name' => 'Traders', 'url' => WEB_URL . '/admin/traders']
];

// Get traders with user info
$stmt = $conn->prepare("SELECT t.id, t.user_id, t.username, t.name, t.description, t.roi_30d, t.followers, t.aum, t.mdd_30d, t.status, t.created_at, u.email as user_email, u.name_surname as user_name FROM traders t LEFT JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC");
$stmt->execute();
$traders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include(V_PATH."p/admin/layout.php");
?>

            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <div></div>
                    <a href="<?= WEB_URL; ?>/admin/traders/add" class="btn btn-primary btn-modern">
                        <i class="fas fa-plus me-2"></i>Yeni Trader Ekle
                    </a>
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
                                        <th>Username</th>
                                        <th>İsim</th>
                                        <th>Kullanıcı</th>
                                        <th>ROI 30d</th>
                                        <th>MDD 30d</th>
                                        <th>Takipçi</th>
                                        <th>AUM</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($traders)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                Henüz trader eklenmemiş
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($traders as $trader): ?>
                                            <tr>
                                                <td><?= $trader['id'] ?></td>
                                                <td><strong><?= htmlspecialchars($trader['username']) ?></strong></td>
                                                <td><?= htmlspecialchars($trader['name']) ?></td>
                                                <td>
                                                    <?php if ($trader['user_id']): ?>
                                                        <span class="badge bg-info" title="Kullanıcı ID: <?= $trader['user_id'] ?>">
                                                            <i class="fas fa-user me-1"></i>
                                                            <?= htmlspecialchars($trader['user_email']) ?>
                                                            <?php if (!empty($trader['user_name'])): ?>
                                                                <br><small><?= htmlspecialchars($trader['user_name']) ?></small>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="text-<?= floatval($trader['roi_30d']) >= 0 ? 'success' : 'danger' ?>">
                                                        <?= number_format(floatval($trader['roi_30d']), 2) ?>%
                                                    </span>
                                                </td>
                                                <td><?= number_format(floatval($trader['mdd_30d']), 2) ?>%</td>
                                                <td><?= number_format(intval($trader['followers'])) ?></td>
                                                <td>$<?= number_format(floatval($trader['aum']), 2) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $trader['status'] == 'active' ? 'success' : 'secondary' ?>">
                                                        <?= ucfirst($trader['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="<?= WEB_URL; ?>/admin/traders/view?id=<?= $trader['id'] ?>" 
                                                       class="btn btn-sm btn-primary me-1" title="Görüntüle/Düzenle">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="<?= WEB_URL; ?>/admin/trades/add?trader_id=<?= $trader['id'] ?>" 
                                                       class="btn btn-sm btn-success" title="Trade Ekle">
                                                        <i class="fas fa-plus"></i>
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
