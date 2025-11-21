<?php
requireAdmin();

$pageTitle = "Trader Ekle";
$breadcrumbs = [
    ['name' => 'Admin', 'url' => WEB_URL . '/admin'],
    ['name' => 'Traders', 'url' => WEB_URL . '/admin/traders'],
    ['name' => 'Trader Ekle', 'url' => WEB_URL . '/admin/traders/add']
];

$errors = [];
$success = false;

// Get all users for dropdown
$usersStmt = $conn->prepare("SELECT id, email, name_surname FROM users ORDER BY email ASC");
$usersStmt->execute();
$allUsers = $usersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$usersStmt->close();

// Check if traders table has user_id column, if not add it
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM traders LIKE 'user_id'");
    if ($checkColumn && $checkColumn->num_rows == 0) {
        // Add user_id column
        $conn->query("ALTER TABLE traders ADD COLUMN user_id INT(11) NULL DEFAULT NULL AFTER id");
        // Add index
        $conn->query("ALTER TABLE traders ADD KEY user_id (user_id)");
        // Add foreign key (may fail if constraint already exists)
        try {
            $conn->query("ALTER TABLE traders ADD CONSTRAINT traders_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
        } catch (Exception $e) {
            // Foreign key might already exist, ignore
        }
    }
} catch (Exception $e) {
    // Column might already exist, ignore
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_trader'])) {
    $user_id = !empty($_POST['user_id']) ? intval($_POST['user_id']) : null;
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $avatar_url = trim($_POST['avatar_url'] ?? '');
    $roi_30d = floatval($_POST['roi_30d'] ?? 0);
    $mdd_30d = floatval($_POST['mdd_30d'] ?? 0);
    
    // If user is selected, get user info
    if ($user_id) {
        $userStmt = $conn->prepare("SELECT email, name_surname FROM users WHERE id = ?");
        $userStmt->bind_param("i", $user_id);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        if ($userResult->num_rows > 0) {
            $selectedUser = $userResult->fetch_assoc();
            // Auto-fill if empty
            if (empty($username)) {
                $username = $selectedUser['email'];
            }
            if (empty($name) && !empty($selectedUser['name_surname'])) {
                $name = $selectedUser['name_surname'];
            } elseif (empty($name)) {
                $name = $selectedUser['email'];
            }
        }
        $userStmt->close();
        
        // Check if user is already a trader
        $checkStmt = $conn->prepare("SELECT id FROM traders WHERE user_id = ?");
        $checkStmt->bind_param("i", $user_id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $errors['user_id'] = 'Bu kullanıcı zaten bir trader';
        }
        $checkStmt->close();
    }
    
    // Validation
    if (empty($username)) {
        $errors['username'] = 'Username gerekli';
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM traders WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors['username'] = 'Bu username zaten kullanılıyor';
        }
        $stmt->close();
    }
    
    if (empty($name)) {
        $errors['name'] = 'İsim gerekli';
    }
    
    if (empty($errors)) {
        if ($user_id) {
            $stmt = $conn->prepare("INSERT INTO traders (user_id, username, name, description, avatar_url, roi_30d, mdd_30d, followers, aum, status) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 'active')");
            $stmt->bind_param("issssdd", $user_id, $username, $name, $description, $avatar_url, $roi_30d, $mdd_30d);
        } else {
            $stmt = $conn->prepare("INSERT INTO traders (username, name, description, avatar_url, roi_30d, mdd_30d, followers, aum, status) VALUES (?, ?, ?, ?, ?, ?, 0, 0, 'active')");
            $stmt->bind_param("ssssdd", $username, $name, $description, $avatar_url, $roi_30d, $mdd_30d);
        }
        
        if ($stmt->execute()) {
            $success = true;
            $_POST = []; // Clear form
        } else {
            $errors['general'] = 'Trader eklenirken hata oluştu: ' . $conn->error;
        }
        $stmt->close();
    }
}

include(V_PATH."p/admin/layout.php");
?>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="dashboard-card glass-card p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i>Trader başarıyla eklendi!
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors['general'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($errors['general']) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= WEB_URL; ?>/admin/traders/add">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Kullanıcıyı Trader Yap:</strong> Aşağıdaki listeden bir kullanıcı seçerek onu trader yapabilirsiniz. Kullanıcı seçildiğinde bilgiler otomatik doldurulacaktır.
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label for="user_id" class="form-label">Kullanıcı Seç (Opsiyonel)</label>
                                    <select class="form-select <?= isset($errors['user_id']) ? 'is-invalid' : '' ?>" 
                                            id="user_id" name="user_id">
                                        <option value="">-- Kullanıcı Seçin veya Manuel Girin --</option>
                                        <?php foreach ($allUsers as $user): ?>
                                            <option value="<?= $user['id'] ?>" 
                                                    data-email="<?= htmlspecialchars($user['email']) ?>"
                                                    data-name="<?= htmlspecialchars($user['name_surname'] ?? '') ?>"
                                                    <?= (isset($_POST['user_id']) && $_POST['user_id'] == $user['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($user['email']) ?> 
                                                <?= !empty($user['name_surname']) ? '(' . htmlspecialchars($user['name_surname']) . ')' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['user_id'])): ?>
                                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['user_id']) ?></div>
                                    <?php endif; ?>
                                    <small class="form-text text-muted">Kullanıcı seçildiğinde username ve isim otomatik doldurulur</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" 
                                           id="username" name="username" 
                                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                                    <?php if (isset($errors['username'])): ?>
                                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['username']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="name" class="form-label">İsim <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                                           id="name" name="name" 
                                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                                    <?php if (isset($errors['name'])): ?>
                                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['name']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label for="description" class="form-label">Açıklama</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label for="avatar_url" class="form-label">Avatar URL</label>
                                    <input type="url" class="form-control" id="avatar_url" name="avatar_url" 
                                           value="<?= htmlspecialchars($_POST['avatar_url'] ?? '') ?>" 
                                           placeholder="https://example.com/avatar.jpg">
                                </div>
                                <div class="col-md-6">
                                    <label for="roi_30d" class="form-label">ROI 30 Gün (%)</label>
                                    <input type="number" step="0.01" class="form-control" id="roi_30d" name="roi_30d" 
                                           value="<?= htmlspecialchars($_POST['roi_30d'] ?? '0') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="mdd_30d" class="form-label">MDD 30 Gün (%)</label>
                                    <input type="number" step="0.01" class="form-control" id="mdd_30d" name="mdd_30d" 
                                           value="<?= htmlspecialchars($_POST['mdd_30d'] ?? '0') ?>">
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="add_trader" class="btn btn-primary btn-lg btn-modern">
                                        <i class="fas fa-save me-2"></i>Trader Ekle
                                    </button>
                                    <a href="<?= WEB_URL; ?>/admin/traders" class="btn btn-secondary btn-lg ms-2">
                                        <i class="fas fa-times me-2"></i>İptal
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userSelect = document.getElementById('user_id');
    const usernameInput = document.getElementById('username');
    const nameInput = document.getElementById('name');
    
    userSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value) {
            const email = selectedOption.getAttribute('data-email');
            const name = selectedOption.getAttribute('data-name');
            
            // Auto-fill username if empty
            if (!usernameInput.value || usernameInput.value.trim() === '') {
                usernameInput.value = email;
            }
            
            // Auto-fill name if empty
            if (!nameInput.value || nameInput.value.trim() === '') {
                nameInput.value = name || email;
            }
        }
    });
});
</script>

<?php include(V_PATH."p/admin/footer.php"); ?>
