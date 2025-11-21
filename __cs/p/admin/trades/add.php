<?php
requireAdmin();

$pageTitle = "Trade Ekle";
$breadcrumbs = [
    ['name' => 'Admin', 'url' => WEB_URL . '/admin'],
    ['name' => 'Trades', 'url' => WEB_URL . '/admin/trades'],
    ['name' => 'Trade Ekle', 'url' => WEB_URL . '/admin/trades/add']
];

$errors = [];
$success = false;
$successMessage = '';

// Check for success/warning/error messages from session
if (isset($_GET['success']) && $_GET['success'] == '1') {
    if (isset($_SESSION['trade_add_success'])) {
        $success = true;
        $successMessage = $_SESSION['trade_add_success'];
        unset($_SESSION['trade_add_success']);
    }
    if (isset($_SESSION['trade_add_warning'])) {
        $success = true;
        $successMessage = $_SESSION['trade_add_warning'];
        if (!isset($errors['user_trades'])) {
            $errors['user_trades'] = [];
        }
        $errors['user_trades'][] = $_SESSION['trade_add_warning'];
        unset($_SESSION['trade_add_warning']);
    }
}

if (isset($_GET['error']) && $_GET['error'] == '1') {
    if (isset($_SESSION['trade_add_error'])) {
        $errors['general'] = $_SESSION['trade_add_error'];
        unset($_SESSION['trade_add_error']);
    }
}

if (isset($_GET['validation_error']) && $_GET['validation_error'] == '1') {
    if (isset($_SESSION['trade_add_validation_errors'])) {
        $errors = array_merge($errors, $_SESSION['trade_add_validation_errors']);
        unset($_SESSION['trade_add_validation_errors']);
    }
}

// Get traders list
$stmt = $conn->prepare("SELECT id, username, name FROM traders WHERE status = 'active' ORDER BY name");
$stmt->execute();
$traders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get trader_id from URL if provided
$selectedTraderId = isset($_GET['trader_id']) ? intval($_GET['trader_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_trade'])) {
    // Debug: Log POST data
    error_log("Trade Add POST: " . print_r($_POST, true));
    
    $trader_id = intval($_POST['trader_id'] ?? 0);
    $pair = trim($_POST['pair'] ?? '');
    $trade_mode = $_POST['trade_mode'] ?? 'spot';
    $type = $_POST['type'] ?? 'SPOT';
    $leverage = trim($_POST['leverage'] ?? '');
    $entry_price = floatval($_POST['entry_price'] ?? 0);
    $entry_amount = floatval($_POST['entry_amount'] ?? 0);
    $exit_price = floatval($_POST['exit_price'] ?? 0);
    $exit_amount = floatval($_POST['exit_amount'] ?? 0);
    $status = $_POST['status'] ?? 'open';
    
    // Validation
    if ($trader_id <= 0) {
        $errors['trader_id'] = 'Trader seçiniz';
    }
    
    if (empty($pair)) {
        $errors['pair'] = 'Pair gerekli (örn: BTC/USDT)';
    }
    
    if ($trade_mode === 'margin') {
        if (empty($type) || ($type !== 'LONG' && $type !== 'SHORT')) {
            $errors['type'] = 'Margin işlemler için pozisyon türü (LONG/SHORT) gerekli';
        }
        if (empty($leverage)) {
            $errors['leverage'] = 'Margin işlemler için leverage gerekli';
        }
    } else {
        $type = 'SPOT';
        $leverage = null;
    }
    
    if ($entry_price <= 0) {
        $errors['entry_price'] = 'Entry price gerekli';
    }
    
    if ($entry_amount <= 0) {
        $errors['entry_amount'] = 'Entry amount gerekli';
    }
    
    if ($status === 'closed' && $exit_price <= 0) {
        $errors['exit_price'] = 'Closed trade için exit price gerekli';
    }
    
    // Debug: Log validation result
    error_log("Trade Add Validation - Errors: " . count($errors) . ", Trader ID: $trader_id, Pair: $pair, Status: $status");
    
    // If there are validation errors, store them in session and redirect
    if (!empty($errors)) {
        $_SESSION['trade_add_validation_errors'] = $errors;
        header("Location: " . WEB_URL . "/admin/trades/add?validation_error=1");
        exit;
    }
    
    if (empty($errors)) {
        error_log("Trade Add: Validation passed, starting transaction");
        
        $profit = 0;
        $profit_percent = 0;
        $exit_time = null;
        
        if ($status === 'closed' && $exit_price > 0) {
            if ($type === 'LONG') {
                $profit = ($exit_price - $entry_price) * $entry_amount;
            } else {
                $profit = ($entry_price - $exit_price) * $entry_amount;
            }
            $profit_percent = (($exit_price - $entry_price) / $entry_price) * 100;
            if ($type === 'SHORT') {
                $profit_percent = -$profit_percent;
            }
            $exit_time = date('Y-m-d H:i:s');
        }
        
        // Start transaction
        $conn->begin_transaction();
        error_log("Trade Add: Transaction started");
        
        try {
            $stmt = $conn->prepare("INSERT INTO trades (trader_id, pair, type, leverage, entry_price, exit_price, entry_amount, exit_amount, profit, profit_percent, status, exit_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssddddddss", $trader_id, $pair, $type, $leverage, $entry_price, $exit_price, $entry_amount, $exit_amount, $profit, $profit_percent, $status, $exit_time);
            
            if (!$stmt->execute()) {
                throw new Exception("Trade eklenirken hata oluştu: " . $conn->error);
            }
            
            $new_trade_id = $conn->insert_id;
            $stmt->close();
            
            // If trade is open, create user_trades for all users following this trader
            if ($status === 'open') {
                // Get all users following this trader
                $getFollowersStmt = $conn->prepare("
                    SELECT 
                        ft.id as followed_trader_id,
                        ft.user_id,
                        ft.current_balance
                    FROM followed_traders ft
                    WHERE ft.trader_id = ? AND ft.status = 'active'
                ");
                $getFollowersStmt->bind_param("i", $trader_id);
                $getFollowersStmt->execute();
                $followersResult = $getFollowersStmt->get_result();
                
                $usersCreated = 0;
                $totalFollowers = $followersResult->num_rows;
                
                while ($follower = $followersResult->fetch_assoc()) {
                    $user_id = intval($follower['user_id']);
                    $followed_trader_id = intval($follower['followed_trader_id']);
                    $userBalance = floatval($follower['current_balance']);
                    
                    // Calculate user's entry amount based on their balance
                    // User invests their entire current_balance in this trade
                    $userEntryAmount = $userBalance;
                    
                    // Only create user_trade if user has balance > 0
                    if ($userEntryAmount > 0) {
                        // Handle NULL leverage
                        $leverageValue = (!empty($leverage) && $leverage !== 'NULL') ? $leverage : null;
                        
                        $insertUserTradeStmt = $conn->prepare("
                            INSERT INTO user_trades 
                            (user_id, trader_id, trade_id, followed_trader_id, pair, type, leverage, entry_price, entry_amount, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')
                        ");
                        $insertUserTradeStmt->bind_param("iiiisssdd", 
                            $user_id, 
                            $trader_id, 
                            $new_trade_id, 
                            $followed_trader_id, 
                            $pair, 
                            $type, 
                            $leverageValue, 
                            $entry_price, 
                            $userEntryAmount
                        );
                        
                        if ($insertUserTradeStmt->execute()) {
                            $usersCreated++;
                        } else {
                            $errorMsg = "User trade oluşturulamadı (user_id: $user_id, trade_id: $new_trade_id): " . $conn->error;
                            error_log($errorMsg);
                            // Also add to errors array for display
                            if (!isset($errors['user_trades'])) {
                                $errors['user_trades'] = [];
                            }
                            $errors['user_trades'][] = $errorMsg;
                        }
                        $insertUserTradeStmt->close();
                    }
                }
                $getFollowersStmt->close();
                
                // Debug info
                error_log("Trade ID: $new_trade_id, Trader ID: $trader_id, Total Followers: $totalFollowers, Users Created: $usersCreated");
            }
            
            // Commit transaction
            $conn->commit();
            error_log("Trade Add: Transaction committed successfully");
            
            // Store success message in session
            if ($status === 'open') {
                if ($usersCreated > 0) {
                    $_SESSION['trade_add_success'] = "Trade başarıyla eklendi. " . $usersCreated . " kullanıcı için işlem oluşturuldu.";
                } else {
                    if ($totalFollowers > 0) {
                        $_SESSION['trade_add_warning'] = "Trade başarıyla eklendi. Ancak takip eden " . $totalFollowers . " kullanıcının bakiyeleri 0 olduğu için işlem oluşturulamadı.";
                    } else {
                        $_SESSION['trade_add_warning'] = "Trade başarıyla eklendi. Ancak bu trader'ı takip eden aktif kullanıcı bulunamadı.";
                    }
                }
            } else {
                $_SESSION['trade_add_success'] = "Trade başarıyla eklendi (kapalı işlem - kullanıcılara kopyalanmadı).";
            }
            
            // Redirect to prevent form resubmission
            header("Location: " . WEB_URL . "/admin/trades/add?success=1");
            exit;
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $errorMsg = 'Trade eklenirken hata oluştu: ' . $e->getMessage();
            $errors['general'] = $errorMsg;
            $_SESSION['trade_add_error'] = $errorMsg;
            error_log("Trade add error: " . $e->getMessage());
            
            // Redirect to show error
            header("Location: " . WEB_URL . "/admin/trades/add?error=1");
            exit;
        }
    }
}

include(V_PATH."p/admin/layout.php");
?>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="dashboard-card glass-card p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php 
                                if (isset($successMessage)) {
                                    echo htmlspecialchars($successMessage);
                                } else {
                                    echo "Trade başarıyla eklendi!";
                                }
                                ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors['general'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($errors['general']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors['user_trades'])): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Kullanıcı İşlemleri:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors['user_trades'] as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors) && empty($errors['general']) && empty($errors['user_trades'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Lütfen aşağıdaki hataları düzeltin:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors as $field => $error): ?>
                                        <?php if ($field !== 'general' && $field !== 'user_trades'): ?>
                                            <li><strong><?= htmlspecialchars($field) ?>:</strong> <?= htmlspecialchars($error) ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_trade']) && empty($errors) && !$success): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Form gönderildi ancak işlem tamamlanamadı. Lütfen tekrar deneyin.
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= WEB_URL; ?>/admin/trades/add">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="trader_id" class="form-label">Trader <span class="text-danger">*</span></label>
                                    <select class="form-select <?= isset($errors['trader_id']) ? 'is-invalid' : '' ?>" 
                                            id="trader_id" name="trader_id" required>
                                        <option value="">Trader Seçiniz</option>
                                        <?php foreach ($traders as $trader): ?>
                                            <option value="<?= $trader['id'] ?>" 
                                                    <?= ($selectedTraderId == $trader['id'] || (isset($_POST['trader_id']) && $_POST['trader_id'] == $trader['id'])) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($trader['name'] . ' (' . $trader['username'] . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['trader_id'])): ?>
                                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['trader_id']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="pair" class="form-label">Pair <span class="text-danger">*</span></label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control <?= isset($errors['pair']) ? 'is-invalid' : '' ?>" 
                                               id="pair" name="pair" 
                                               value="<?= htmlspecialchars($_POST['pair'] ?? '') ?>" 
                                               placeholder="BTC/USDT" 
                                               autocomplete="off"
                                               required>
                                        <div id="pairSuggestions" class="list-group position-absolute w-100" style="z-index: 1000; max-height: 200px; overflow-y: auto; display: none; top: 100%; margin-top: 2px;"></div>
                                    </div>
                                    <?php if (isset($errors['pair'])): ?>
                                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['pair']) ?></div>
                                    <?php endif; ?>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Pair yazıldıkça Binance'den fiyat otomatik çekilir. Örnek: BTC/USDT, ETH/USDT
                                        <span id="priceStatus" class="ms-2"></span>
                                    </small>
                                </div>
                                <div class="col-md-4">
                                    <label for="trade_mode" class="form-label">İşlem Türü <span class="text-danger">*</span></label>
                                    <select class="form-select" id="trade_mode" name="trade_mode" required>
                                        <option value="spot" <?= (isset($_POST['trade_mode']) && $_POST['trade_mode'] == 'spot') ? 'selected' : '' ?>>Spot</option>
                                        <option value="margin" <?= (isset($_POST['trade_mode']) && $_POST['trade_mode'] == 'margin') ? 'selected' : '' ?>>Margin</option>
                                    </select>
                                </div>
                                <div class="col-md-4" id="position_type_group" style="display: none;">
                                    <label for="type" class="form-label">Pozisyon Türü <span class="text-danger">*</span></label>
                                    <select class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>" id="type" name="type" required>
                                        <option value="">Pozisyon Seçiniz</option>
                                        <option value="LONG" <?= (isset($_POST['type']) && $_POST['type'] == 'LONG') ? 'selected' : '' ?>>LONG</option>
                                        <option value="SHORT" <?= (isset($_POST['type']) && $_POST['type'] == 'SHORT') ? 'selected' : '' ?>>SHORT</option>
                                    </select>
                                    <?php if (isset($errors['type'])): ?>
                                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['type']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4" id="leverage_group" style="display: none;">
                                    <label for="leverage" class="form-label">Leverage (X) <span class="text-danger">*</span></label>
                                    <select class="form-select <?= isset($errors['leverage']) ? 'is-invalid' : '' ?>" id="leverage" name="leverage" required>
                                        <option value="">Leverage Seçiniz</option>
                                        <option value="1x" <?= (isset($_POST['leverage']) && $_POST['leverage'] == '1x') ? 'selected' : '' ?>>1x</option>
                                        <option value="2x" <?= (isset($_POST['leverage']) && $_POST['leverage'] == '2x') ? 'selected' : '' ?>>2x</option>
                                        <option value="3x" <?= (isset($_POST['leverage']) && $_POST['leverage'] == '3x') ? 'selected' : '' ?>>3x</option>
                                        <option value="5x" <?= (isset($_POST['leverage']) && $_POST['leverage'] == '5x') ? 'selected' : '' ?>>5x</option>
                                        <option value="10x" <?= (isset($_POST['leverage']) && $_POST['leverage'] == '10x') ? 'selected' : '' ?>>10x</option>
                                        <option value="20x" <?= (isset($_POST['leverage']) && $_POST['leverage'] == '20x') ? 'selected' : '' ?>>20x</option>
                                        <option value="25x" <?= (isset($_POST['leverage']) && $_POST['leverage'] == '25x') ? 'selected' : '' ?>>25x</option>
                                        <option value="50x" <?= (isset($_POST['leverage']) && $_POST['leverage'] == '50x') ? 'selected' : '' ?>>50x</option>
                                        <option value="75x" <?= (isset($_POST['leverage']) && $_POST['leverage'] == '75x') ? 'selected' : '' ?>>75x</option>
                                        <option value="100x" <?= (isset($_POST['leverage']) && $_POST['leverage'] == '100x') ? 'selected' : '' ?>>100x</option>
                                        <option value="125x" <?= (isset($_POST['leverage']) && $_POST['leverage'] == '125x') ? 'selected' : '' ?>>125x</option>
                                    </select>
                                    <?php if (isset($errors['leverage'])): ?>
                                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['leverage']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4" id="spot_type_group" style="display: none;">
                                    <label for="spot_type" class="form-label">Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="spot_type" name="type">
                                        <option value="SPOT" selected>SPOT</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="open" <?= (isset($_POST['status']) && $_POST['status'] == 'open') ? 'selected' : 'selected' ?>>Open</option>
                                        <option value="closed" <?= (isset($_POST['status']) && $_POST['status'] == 'closed') ? 'selected' : '' ?>>Closed</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="entry_price" class="form-label">Entry Price <span class="text-danger">*</span></label>
                                    <input type="number" step="0.00000001" class="form-control <?= isset($errors['entry_price']) ? 'is-invalid' : '' ?>" 
                                           id="entry_price" name="entry_price" 
                                           value="<?= htmlspecialchars($_POST['entry_price'] ?? '') ?>" required>
                                    <?php if (isset($errors['entry_price'])): ?>
                                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['entry_price']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="entry_amount" class="form-label">Entry Amount <span class="text-danger">*</span></label>
                                    <input type="number" step="0.00000001" class="form-control <?= isset($errors['entry_amount']) ? 'is-invalid' : '' ?>" 
                                           id="entry_amount" name="entry_amount" 
                                           value="<?= htmlspecialchars($_POST['entry_amount'] ?? '') ?>" required>
                                    <?php if (isset($errors['entry_amount'])): ?>
                                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['entry_amount']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6" id="exit_price_group" style="display: none;">
                                    <label for="exit_price" class="form-label">Exit Price</label>
                                    <input type="number" step="0.00000001" class="form-control <?= isset($errors['exit_price']) ? 'is-invalid' : '' ?>" 
                                           id="exit_price" name="exit_price" 
                                           value="<?= htmlspecialchars($_POST['exit_price'] ?? '') ?>">
                                    <?php if (isset($errors['exit_price'])): ?>
                                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['exit_price']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6" id="exit_amount_group" style="display: none;">
                                    <label for="exit_amount" class="form-label">Exit Amount</label>
                                    <input type="number" step="0.00000001" class="form-control" 
                                           id="exit_amount" name="exit_amount" 
                                           value="<?= htmlspecialchars($_POST['exit_amount'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="add_trade" class="btn btn-success btn-lg btn-modern">
                                        <i class="fas fa-save me-2"></i>Trade Ekle
                                    </button>
                                    <a href="<?= WEB_URL; ?>/admin/trades" class="btn btn-secondary btn-lg ms-2">
                                        <i class="fas fa-times me-2"></i>İptal
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

    <style>
        #pairSuggestions {
            border: 1px solid var(--border-color, #dee2e6);
            border-radius: 0.375rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            background: var(--card-bg, #fff);
        }
        #pairSuggestions .list-group-item {
            cursor: pointer;
            border: none;
            border-bottom: 1px solid var(--border-color, #dee2e6);
            padding: 0.5rem 1rem;
        }
        #pairSuggestions .list-group-item:last-child {
            border-bottom: none;
        }
        #pairSuggestions .list-group-item:hover,
        #pairSuggestions .list-group-item.active {
            background-color: var(--primary-color, #0d6efd);
            color: white;
        }
        #pairSuggestions .list-group-item.active .text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tradeModeSelect = document.getElementById('trade_mode');
            const positionTypeGroup = document.getElementById('position_type_group');
            const leverageGroup = document.getElementById('leverage_group');
            const spotTypeGroup = document.getElementById('spot_type_group');
            const typeSelect = document.getElementById('type');
            const leverageSelect = document.getElementById('leverage');
            const spotTypeSelect = document.getElementById('spot_type');
            
            const statusSelect = document.getElementById('status');
            const exitPriceGroup = document.getElementById('exit_price_group');
            const exitAmountGroup = document.getElementById('exit_amount_group');
            const exitPrice = document.getElementById('exit_price');
            const exitAmount = document.getElementById('exit_amount');
            
            function toggleTradeModeFields() {
                if (tradeModeSelect.value === 'margin') {
                    positionTypeGroup.style.display = 'block';
                    leverageGroup.style.display = 'block';
                    spotTypeGroup.style.display = 'none';
                    typeSelect.required = true;
                    typeSelect.removeAttribute('disabled');
                    leverageSelect.required = true;
                    leverageSelect.removeAttribute('disabled');
                    spotTypeSelect.required = false;
                    spotTypeSelect.setAttribute('disabled', 'disabled');
                    spotTypeSelect.value = '';
                } else {
                    positionTypeGroup.style.display = 'none';
                    leverageGroup.style.display = 'none';
                    spotTypeGroup.style.display = 'block';
                    typeSelect.required = false;
                    typeSelect.setAttribute('disabled', 'disabled');
                    typeSelect.value = '';
                    leverageSelect.required = false;
                    leverageSelect.setAttribute('disabled', 'disabled');
                    leverageSelect.value = '';
                    spotTypeSelect.required = true;
                    spotTypeSelect.removeAttribute('disabled');
                    spotTypeSelect.value = 'SPOT';
                }
                // Reload symbols when trade mode changes
                symbolsLoaded = false;
                loadBinanceSymbols();
            }
            
            function toggleExitFields() {
                if (statusSelect.value === 'closed') {
                    exitPriceGroup.style.display = 'block';
                    exitAmountGroup.style.display = 'block';
                    exitPrice.required = true;
                } else {
                    exitPriceGroup.style.display = 'none';
                    exitAmountGroup.style.display = 'none';
                    exitPrice.required = false;
                }
            }
            
            tradeModeSelect.addEventListener('change', toggleTradeModeFields);
            statusSelect.addEventListener('change', toggleExitFields);
            
            toggleTradeModeFields();
            toggleExitFields();
            
            // Initialize symbol autocomplete
            initializeSymbolAutocomplete();
        });
        
        let priceFetchTimeout;
        let isFetchingPrice = false;
        let binanceSymbols = [];
        let symbolsLoaded = false;
        
        // Load Binance symbols
        async function loadBinanceSymbols() {
            if (symbolsLoaded) return;
            
            try {
                const tradeModeSelect = document.getElementById('trade_mode');
                let apiUrl;
                
                if (tradeModeSelect && tradeModeSelect.value === 'margin') {
                    apiUrl = 'https://fapi.binance.com/fapi/v1/exchangeInfo';
                } else {
                    apiUrl = 'https://api.binance.com/api/v3/exchangeInfo';
                }
                
                const response = await fetch(apiUrl);
                const data = await response.json();
                
                if (data.symbols) {
                    binanceSymbols = data.symbols
                        .filter(s => s.status === 'TRADING')
                        .map(s => s.symbol)
                        .sort();
                    symbolsLoaded = true;
                }
            } catch (error) {
                console.error('Failed to load Binance symbols:', error);
            }
        }
        
        // Show symbol suggestions
        function showSymbolSuggestions(query) {
            const suggestionsDiv = document.getElementById('pairSuggestions');
            if (!suggestionsDiv) return;
            
            if (!query || query.length < 1) {
                suggestionsDiv.style.display = 'none';
                return;
            }
            
            if (binanceSymbols.length === 0) {
                loadBinanceSymbols().then(() => {
                    if (binanceSymbols.length > 0) {
                        showSymbolSuggestions(query);
                    }
                });
                return;
            }
            
            const upperQuery = query.toUpperCase().replace(/[\/\-]/g, '');
            const filtered = binanceSymbols.filter(symbol => {
                return symbol.includes(upperQuery);
            }).slice(0, 10); // Show max 10 suggestions
            
            if (filtered.length === 0) {
                suggestionsDiv.style.display = 'none';
                return;
            }
            
            suggestionsDiv.innerHTML = '';
            filtered.forEach(symbol => {
                // Convert to pair format (BTCUSDT -> BTC/USDT)
                let pairFormat = symbol;
                const quoteAssets = ['USDT', 'USDC', 'BTC', 'ETH', 'BNB', 'BUSD', 'EUR', 'GBP', 'TRY'];
                for (const quote of quoteAssets) {
                    if (symbol.endsWith(quote)) {
                        pairFormat = symbol.slice(0, -quote.length) + '/' + quote;
                        break;
                    }
                }
                
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `<strong>${pairFormat}</strong> <small class="text-muted">(${symbol})</small>`;
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const pairInput = document.getElementById('pair');
                    if (pairInput) {
                        pairInput.value = pairFormat;
                        suggestionsDiv.style.display = 'none';
                        fetchBinancePrice();
                    }
                });
                suggestionsDiv.appendChild(item);
            });
            
            suggestionsDiv.style.display = 'block';
        }
        
        // Initialize symbol autocomplete
        function initializeSymbolAutocomplete() {
            const pairInput = document.getElementById('pair');
            const tradeModeSelect = document.getElementById('trade_mode');
            const suggestionsDiv = document.getElementById('pairSuggestions');
            
            if (!pairInput || !suggestionsDiv) return;
            
            // Load symbols when trade mode changes
            if (tradeModeSelect) {
                tradeModeSelect.addEventListener('change', function() {
                    symbolsLoaded = false;
                    loadBinanceSymbols();
                });
            }
            
            // Initial load
            loadBinanceSymbols();
            
            // Show suggestions on input
            pairInput.addEventListener('input', function(e) {
                const query = e.target.value.trim();
                showSymbolSuggestions(query);
                fetchBinancePrice();
            });
            
            // Handle paste
            pairInput.addEventListener('paste', function() {
                setTimeout(function() {
                    const query = pairInput.value.trim();
                    showSymbolSuggestions(query);
                    fetchBinancePrice();
                }, 100);
            });
            
            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!pairInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                    suggestionsDiv.style.display = 'none';
                }
            });
            
            // Handle keyboard navigation
            pairInput.addEventListener('keydown', function(e) {
                const items = suggestionsDiv.querySelectorAll('.list-group-item');
                const activeItem = suggestionsDiv.querySelector('.list-group-item.active');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (activeItem) {
                        activeItem.classList.remove('active');
                        const next = activeItem.nextElementSibling || items[0];
                        if (next) next.classList.add('active');
                    } else if (items[0]) {
                        items[0].classList.add('active');
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (activeItem) {
                        activeItem.classList.remove('active');
                        const prev = activeItem.previousElementSibling || items[items.length - 1];
                        if (prev) prev.classList.add('active');
                    } else if (items[items.length - 1]) {
                        items[items.length - 1].classList.add('active');
                    }
                } else if (e.key === 'Enter' && activeItem) {
                    e.preventDefault();
                    activeItem.click();
                } else if (e.key === 'Escape') {
                    suggestionsDiv.style.display = 'none';
                }
            });
        }
        
        async function fetchBinancePrice() {
            const pairInput = document.getElementById('pair');
            const entryPriceInput = document.getElementById('entry_price');
            const tradeModeSelect = document.getElementById('trade_mode');
            const priceStatus = document.getElementById('priceStatus');
            
            const pair = pairInput.value.trim().toUpperCase();
            
            // Clear previous timeout
            if (priceFetchTimeout) {
                clearTimeout(priceFetchTimeout);
            }
            
            // If pair is empty or too short, clear status
            if (!pair || pair.length < 3) {
                priceStatus.innerHTML = '';
                return;
            }
            
            // Check if pair has valid format (contains / or is valid symbol)
            if (!pair.includes('/') && pair.length < 4) {
                return;
            }
            
            // Debounce: Wait 800ms after user stops typing
            priceFetchTimeout = setTimeout(async () => {
                if (isFetchingPrice) return;
                
                isFetchingPrice = true;
                priceStatus.innerHTML = '<i class="fas fa-spinner fa-spin text-primary"></i>';
                
                try {
                    // Convert BTC/USDT to BTCUSDT or handle other formats
                    let binanceSymbol = pair.replace('/', '').replace('-', '');
                    
                    // If no slash, assume it's already in Binance format
                    if (!pair.includes('/') && !pair.includes('-')) {
                        binanceSymbol = pair;
                    }
                    
                    let apiUrl;
                    if (tradeModeSelect.value === 'margin') {
                        // Futures API
                        apiUrl = `https://fapi.binance.com/fapi/v1/ticker/price?symbol=${binanceSymbol}`;
                    } else {
                        // Spot API
                        apiUrl = `https://api.binance.com/api/v3/ticker/price?symbol=${binanceSymbol}`;
                    }
                    
                    const response = await fetch(apiUrl);
                    const data = await response.json();
                    
                    if (data.price) {
                        const price = parseFloat(data.price);
                        entryPriceInput.value = price.toFixed(8);
                        priceStatus.innerHTML = '<i class="fas fa-check-circle text-success"></i> <small class="text-success">Fiyat: $' + price.toFixed(2) + '</small>';
                        
                        // Clear status after 3 seconds
                        setTimeout(() => {
                            priceStatus.innerHTML = '';
                        }, 3000);
                    } else if (data.msg) {
                        priceStatus.innerHTML = '<i class="fas fa-exclamation-circle text-danger"></i> <small class="text-danger">' + data.msg + '</small>';
                    } else {
                        priceStatus.innerHTML = '<i class="fas fa-exclamation-circle text-warning"></i> <small class="text-warning">Fiyat alınamadı</small>';
                    }
                } catch (error) {
                    console.error('Binance API Error:', error);
                    priceStatus.innerHTML = '<i class="fas fa-exclamation-circle text-danger"></i> <small class="text-danger">API hatası</small>';
                } finally {
                    isFetchingPrice = false;
                }
            }, 800);
        }
        
    </script>

<?php include(V_PATH."p/admin/footer.php"); ?>
