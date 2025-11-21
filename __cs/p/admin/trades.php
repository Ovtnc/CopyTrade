<?php
requireAdmin();

$pageTitle = "Trade Yönetimi";
$breadcrumbs = [
    ['name' => 'Admin', 'url' => WEB_URL . '/admin'],
    ['name' => 'Trades', 'url' => WEB_URL . '/admin/trades']
];

$success = '';
$error = '';

// Handle trade close
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_trade'])) {
    $trade_id = intval($_POST['trade_id'] ?? 0);
    $pair = trim($_POST['pair'] ?? '');
    $trade_type = $_POST['trade_type'] ?? 'SPOT';
    $entry_price = floatval($_POST['entry_price'] ?? 0);
    $entry_amount = floatval($_POST['entry_amount'] ?? 0);
    $leverage = trim($_POST['leverage'] ?? '');
    $exit_price = floatval($_POST['exit_price'] ?? 0);
    
    if ($trade_id <= 0) {
        $error = "Geçersiz trade ID";
    } elseif (empty($pair)) {
        $error = "Pair bilgisi bulunamadı";
    } elseif ($entry_price <= 0) {
        $error = "Entry price bilgisi bulunamadı";
    } elseif ($exit_price <= 0) {
        $error = "Exit price gerekli. Lütfen geçerli bir fiyat girin.";
    } else {
        // Exit price is provided from form (either from Binance or manually entered)
        // Calculate profit
        $profit = 0;
        $profit_percent = 0;
        $exit_amount = $entry_amount;
        
        if ($trade_type === 'LONG') {
            $profit = ($exit_price - $entry_price) * $entry_amount;
            $profit_percent = (($exit_price - $entry_price) / $entry_price) * 100;
        } elseif ($trade_type === 'SHORT') {
            $profit = ($entry_price - $exit_price) * $entry_amount;
            $profit_percent = (($entry_price - $exit_price) / $entry_price) * 100;
        } else {
            // SPOT
            $profit = ($exit_price - $entry_price) * $entry_amount;
            $profit_percent = (($exit_price - $entry_price) / $entry_price) * 100;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // First get trader_id from the trade
            $getTradeStmt = $conn->prepare("SELECT trader_id FROM trades WHERE id = ?");
            $getTradeStmt->bind_param("i", $trade_id);
            $getTradeStmt->execute();
            $tradeResult = $getTradeStmt->get_result();
            $tradeData = $tradeResult->fetch_assoc();
            $getTradeStmt->close();
            
            if (!$tradeData) {
                throw new Exception("Trade bulunamadı");
            }
            
            $trader_id = intval($tradeData['trader_id']);
            
            // Update trade
            $updateStmt = $conn->prepare("UPDATE trades SET status = 'closed', exit_price = ?, exit_amount = ?, profit = ?, profit_percent = ?, exit_time = NOW() WHERE id = ?");
            $updateStmt->bind_param("ddddi", $exit_price, $exit_amount, $profit, $profit_percent, $trade_id);
            
            if (!$updateStmt->execute()) {
                throw new Exception("Trade güncellenemedi: " . $conn->error);
            }
            $updateStmt->close();
            
            // Get all users following this trader who have open trades for this trade_id
            $getUsersStmt = $conn->prepare("
                SELECT 
                    ut.id as user_trade_id,
                    ut.user_id,
                    ut.followed_trader_id,
                    ut.entry_amount,
                    ft.current_balance
                FROM user_trades ut
                INNER JOIN followed_traders ft ON ut.followed_trader_id = ft.id
                WHERE ut.trade_id = ? AND ut.status = 'open' AND ut.trader_id = ?
            ");
            $getUsersStmt->bind_param("ii", $trade_id, $trader_id);
            $getUsersStmt->execute();
            $usersResult = $getUsersStmt->get_result();
            $userTradesToUpdate = [];
            
            while ($userRow = $usersResult->fetch_assoc()) {
                $userTradesToUpdate[] = $userRow;
            }
            $getUsersStmt->close();
            
            // Update each user's trade
            foreach ($userTradesToUpdate as $userTrade) {
                $userEntryAmount = floatval($userTrade['entry_amount']);
                
                // Calculate user's profit based on trader's profit percentage
                $userProfit = ($profit_percent / 100) * $userEntryAmount;
                $userExitAmount = $userEntryAmount + $userProfit;
                $userExitPrice = $exit_price; // Same exit price as trader
                
                // Total amount to add back to user's balance: entry_amount (yatırdığı para) + profit (kar)
                $totalReturnAmount = $userEntryAmount + $userProfit;
                
                // Update user_trades
                $updateUserTradeStmt = $conn->prepare("
                    UPDATE user_trades 
                    SET status = 'closed', 
                        exit_price = ?, 
                        exit_amount = ?, 
                        profit = ?, 
                        profit_percent = ?, 
                        exit_time = NOW() 
                    WHERE id = ?
                ");
                $updateUserTradeStmt->bind_param("ddddi", $userExitPrice, $userExitAmount, $userProfit, $profit_percent, $userTrade['user_trade_id']);
                
                if (!$updateUserTradeStmt->execute()) {
                    throw new Exception("User trade güncellenemedi: " . $conn->error);
                }
                $updateUserTradeStmt->close();
                
                // Update followed_traders current_balance (add profit)
                $newCurrentBalance = floatval($userTrade['current_balance']) + $userProfit;
                $updateBalanceStmt = $conn->prepare("
                    UPDATE followed_traders 
                    SET current_balance = ? 
                    WHERE id = ?
                ");
                $updateBalanceStmt->bind_param("di", $newCurrentBalance, $userTrade['followed_trader_id']);
                
                if (!$updateBalanceStmt->execute()) {
                    throw new Exception("Bakiye güncellenemedi: " . $conn->error);
                }
                $updateBalanceStmt->close();
                
                // Update user's balance and withdrawable_balance
                // Add back to balance: entry_amount (yatırdığı para) + profit (kar)
                // Deduct from withdrawable_balance: entry_amount (trader'a yatırılan para geri dönüyor)
                $entryAmount = floatval($userTrade['entry_amount']);
                $updateUserBalanceStmt = $conn->prepare("
                    UPDATE users 
                    SET balance = balance + ?, 
                        withdrawable_balance = withdrawable_balance - ? 
                    WHERE id = ? AND withdrawable_balance >= ?
                ");
                $updateUserBalanceStmt->bind_param("ddid", $totalReturnAmount, $entryAmount, $userTrade['user_id'], $entryAmount);
                
                if (!$updateUserBalanceStmt->execute()) {
                    throw new Exception("Kullanıcı bakiyesi güncellenemedi: " . $conn->error);
                }
                $updateUserBalanceStmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            
            $usersCount = count($userTradesToUpdate);
            $success = "Trade başarıyla kapatıldı. Exit Price: $" . number_format($exit_price, 8) . ", Profit: $" . number_format($profit, 2) . ". " . $usersCount . " kullanıcının işlemi güncellendi.";
            
            // Redirect to prevent form resubmission
            header("Location: " . WEB_URL . "/admin/trades?success=1");
            exit;
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error = "Hata: " . $e->getMessage();
            error_log("Trade close error: " . $e->getMessage());
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "Trade başarıyla kapatıldı";
}

// Get trades with trader info
$trades = [];
$totalTrades = 0;

try {
    // First check total trades count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM trades");
    if ($countStmt) {
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $countData = $countResult->fetch_assoc();
        $totalTrades = intval($countData['total'] ?? 0);
        $countStmt->close();
    }
    
    // Get trades with trader info
    $stmt = $conn->prepare("
        SELECT t.*, tr.username, tr.name as trader_name 
        FROM trades t 
        LEFT JOIN traders tr ON t.trader_id = tr.id 
        ORDER BY t.created_at DESC, t.entry_time DESC 
        LIMIT 100
    ");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $trades = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error = "Sorgu hazırlanırken hata oluştu: " . $conn->error;
    }
} catch (Exception $e) {
    $error = "Hata: " . $e->getMessage();
    error_log("Admin trades query error: " . $e->getMessage());
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
                        <?php if ($totalTrades > 0): ?>
                            <span class="text-muted">Toplam <?= $totalTrades ?> trade bulundu</span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= WEB_URL; ?>/admin/trades/add" class="btn btn-success btn-modern">
                        <i class="fas fa-plus me-2"></i>Yeni Trade Ekle
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
                                        <th>Trader</th>
                                        <th>Pair</th>
                                        <th>Type</th>
                                        <th>Leverage</th>
                                        <th>Entry Price</th>
                                        <th>Exit Price</th>
                                        <th>Profit</th>
                                        <th>Profit %</th>
                                        <th>Status</th>
                                        <th>Entry Time</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($trades)): ?>
                                        <tr>
                                            <td colspan="12" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                Henüz trade eklenmemiş
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($trades as $trade): ?>
                                            <tr>
                                                <td><?= $trade['id'] ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($trade['trader_name'] ?? 'Bilinmeyen Trader') ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($trade['username'] ?? '-') ?></small>
                                                </td>
                                                <td><strong><?= htmlspecialchars($trade['pair']) ?></strong></td>
                                                <td>
                                                    <span class="badge bg-<?= $trade['type'] == 'LONG' ? 'success' : ($trade['type'] == 'SHORT' ? 'danger' : 'info') ?>">
                                                        <?= $trade['type'] ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($trade['leverage'] ?? '-') ?></td>
                                                <td>$<?= number_format(floatval($trade['entry_price']), 8) ?></td>
                                                <td>
                                                    <?= $trade['exit_price'] ? '$' . number_format(floatval($trade['exit_price']), 8) : '-' ?>
                                                </td>
                                                <td>
                                                    <span class="text-<?= floatval($trade['profit']) >= 0 ? 'success' : 'danger' ?>">
                                                        $<?= number_format(floatval($trade['profit']), 2) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-<?= floatval($trade['profit_percent']) >= 0 ? 'success' : 'danger' ?>">
                                                        <?= number_format(floatval($trade['profit_percent']), 2) ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $trade['status'] == 'open' ? 'warning' : 'success' ?>">
                                                        <?= ucfirst($trade['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($trade['entry_time'])) {
                                                        echo date('d.m.Y H:i', strtotime($trade['entry_time']));
                                                    } elseif (!empty($trade['created_at'])) {
                                                        echo date('d.m.Y H:i', strtotime($trade['created_at']));
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($trade['status'] == 'open'): ?>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="closeTrade(<?= $trade['id'] ?>, '<?= htmlspecialchars(addslashes($trade['pair'])) ?>', '<?= $trade['type'] ?>', <?= floatval($trade['entry_price']) ?>, <?= floatval($trade['entry_amount']) ?>, '<?= htmlspecialchars(addslashes($trade['leverage'] ?? '')) ?>')"
                                                                title="İşlemi Sonlandır">
                                                            <i class="fas fa-times-circle"></i> Sonlandır
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Kapatıldı</span>
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

<!-- Close Trade Modal -->
<div class="modal fade" id="closeTradeModal" tabindex="-1" aria-labelledby="closeTradeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="closeTradeModalLabel">İşlemi Sonlandır</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="closeTradeForm">
                <input type="hidden" name="close_trade" value="1">
                <input type="hidden" name="trade_id" id="closeTradeId">
                <input type="hidden" name="pair" id="closeTradePair">
                <input type="hidden" name="trade_type" id="closeTradeType">
                <input type="hidden" name="entry_price" id="closeEntryPrice">
                <input type="hidden" name="entry_amount" id="closeEntryAmount">
                <input type="hidden" name="leverage" id="closeLeverage">
                <input type="hidden" name="exit_price" id="closeExitPriceHidden">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Pair</label>
                        <input type="text" class="form-control" id="closeTradePairDisplay" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Entry Price</label>
                        <input type="text" class="form-control" id="closeEntryPriceDisplay" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Exit Price</label>
                        <div class="input-group">
                            <input type="number" step="0.00000001" class="form-control" id="closeExitPriceDisplay" name="exit_price" placeholder="Binance'den çekiliyor..." required>
                            <button type="button" class="btn btn-outline-primary" id="refreshPriceBtn" onclick="refreshBinancePrice()" title="Binance'den Fiyat Çek">
                                <i class="fas fa-sync-alt" id="refreshPriceIcon"></i>
                            </button>
                        </div>
                        <small class="text-muted">Binance API'den güncel fiyat otomatik çekilecek veya elle girebilirsiniz</small>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Bu işlem Binance'den güncel fiyatı çekerek trade'i kapatacaktır. Profit otomatik hesaplanacaktır.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger" id="closeTradeSubmitBtn">
                        <i class="fas fa-times-circle me-2"></i>İşlemi Sonlandır
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentPair = '';
let currentLeverage = '';

async function closeTrade(tradeId, pair, tradeType, entryPrice, entryAmount, leverage) {
    const modal = new bootstrap.Modal(document.getElementById('closeTradeModal'));
    const pairInput = document.getElementById('closeTradePair');
    const pairDisplay = document.getElementById('closeTradePairDisplay');
    const typeInput = document.getElementById('closeTradeType');
    const entryPriceInput = document.getElementById('closeEntryPrice');
    const entryPriceDisplay = document.getElementById('closeEntryPriceDisplay');
    const entryAmountInput = document.getElementById('closeEntryAmount');
    const leverageInput = document.getElementById('closeLeverage');
    const tradeIdInput = document.getElementById('closeTradeId');
    const exitPriceDisplay = document.getElementById('closeExitPriceDisplay');
    const exitPriceHidden = document.getElementById('closeExitPriceHidden');
    const submitBtn = document.getElementById('closeTradeSubmitBtn');
    
    // Store for refresh function
    currentPair = pair;
    currentLeverage = leverage || '';
    
    // Set form values
    tradeIdInput.value = tradeId;
    pairInput.value = pair;
    pairDisplay.value = pair;
    typeInput.value = tradeType;
    entryPriceInput.value = entryPrice;
    entryPriceDisplay.value = parseFloat(entryPrice).toFixed(8);
    entryAmountInput.value = entryAmount;
    leverageInput.value = leverage || '';
    
    // Reset exit price
    exitPriceDisplay.value = '';
    exitPriceDisplay.placeholder = 'Binance\'den çekiliyor...';
    submitBtn.disabled = true;
    
    // Show modal
    modal.show();
    
    // Fetch current price from Binance
    await refreshBinancePrice();
}

async function refreshBinancePrice() {
    const exitPriceDisplay = document.getElementById('closeExitPriceDisplay');
    const refreshPriceIcon = document.getElementById('refreshPriceIcon');
    const submitBtn = document.getElementById('closeTradeSubmitBtn');
    
    if (!currentPair) {
        return;
    }
    
    // Show loading
    refreshPriceIcon.classList.add('fa-spin');
    exitPriceDisplay.placeholder = 'Binance\'den çekiliyor...';
    submitBtn.disabled = true;
    
    try {
        const binanceSymbol = currentPair.toUpperCase().replace(/[\/\-]/g, '');
        const isMargin = currentLeverage && currentLeverage !== '' && currentLeverage !== 'NULL';
        
        let apiUrl;
        if (isMargin) {
            apiUrl = `https://fapi.binance.com/fapi/v1/ticker/price?symbol=${binanceSymbol}`;
        } else {
            apiUrl = `https://api.binance.com/api/v3/ticker/price?symbol=${binanceSymbol}`;
        }
        
        const response = await fetch(apiUrl);
        const data = await response.json();
        
        if (data.price) {
            const exitPrice = parseFloat(data.price);
            exitPriceDisplay.value = exitPrice.toFixed(8);
            document.getElementById('closeExitPriceHidden').value = exitPrice;
            exitPriceDisplay.placeholder = '';
            submitBtn.disabled = false;
        } else {
            exitPriceDisplay.value = '';
            exitPriceDisplay.placeholder = 'Fiyat alınamadı: ' + (data.msg || 'Bilinmeyen hata');
            submitBtn.disabled = false; // Allow manual entry
        }
    } catch (error) {
        console.error('Binance API Error:', error);
        exitPriceDisplay.value = '';
        exitPriceDisplay.placeholder = 'API hatası: Elle girebilirsiniz';
        submitBtn.disabled = false; // Allow manual entry
    } finally {
        refreshPriceIcon.classList.remove('fa-spin');
    }
}

// Update hidden field when user manually changes exit price
document.addEventListener('DOMContentLoaded', function() {
    const exitPriceDisplay = document.getElementById('closeExitPriceDisplay');
    const exitPriceHidden = document.getElementById('closeExitPriceHidden');
    
    if (exitPriceDisplay) {
        exitPriceDisplay.addEventListener('input', function() {
            const value = parseFloat(this.value);
            if (!isNaN(value) && value > 0) {
                exitPriceHidden.value = value;
            }
        });
        
        // Also update on form submit
        const closeTradeForm = document.getElementById('closeTradeForm');
        if (closeTradeForm) {
            closeTradeForm.addEventListener('submit', function(e) {
                const exitPrice = parseFloat(exitPriceDisplay.value);
                if (isNaN(exitPrice) || exitPrice <= 0) {
                    e.preventDefault();
                    alert('Lütfen geçerli bir exit price girin');
                    return false;
                }
                exitPriceHidden.value = exitPrice;
            });
        }
    }
});
</script>

<?php include(V_PATH."p/admin/footer.php"); ?>
