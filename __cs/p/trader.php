
    <title>Trader Details - CopyStar</title>

</head>
<body>
<?php
// Require authentication
if (!isset($currentUser) || !$currentUser) {
    // Clear any invalid cookies
    if (isset($_COOKIE['auth_key'])) {
        setcookie('auth_key', '', time() - 3600, '/');
    }
    header("Location: " . WEB_URL . "/login");
    exit;
}

// Get trader ID from URL parameter
$traderId = isset($_GET['trader_id']) ? intval($_GET['trader_id']) : 0;

if ($traderId <= 0) {
    header("Location: " . WEB_URL . "/traders");
    exit;
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $followSuccess = true;
}

// Handle follow trader request (same logic as traders.php)
$followSuccess = false;
$followError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['follow_trader'])) {
    $postTraderId = isset($_POST['trader_id']) ? intval($_POST['trader_id']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    
    // Validation
    if ($postTraderId <= 0) {
        $followError = 'invalidTrader';
    } elseif ($amount <= 0) {
        $followError = 'invalidAmount';
    } else {
        // Get balance (total deposited amount - this is available balance)
        $balance = isset($currentUser['balance']) && $currentUser['balance'] !== null ? floatval($currentUser['balance']) : 0.00;
        
        if ($amount > $balance) {
            $followError = 'insufficientBalance';
        } else {
            // Check if already following this trader
            $checkStmt = $conn->prepare("SELECT id, first_balance, current_balance FROM followed_traders WHERE user_id = ? AND trader_id = ? AND status = 'active'");
            $checkStmt->bind_param("ii", $currentUser['id'], $postTraderId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $existingFollow = $checkResult->fetch_assoc();
            $checkStmt->close();
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Deduct from balance, add to withdrawable_balance (trader'a yatırılan para çekilemez)
                $updateStmt = $conn->prepare("UPDATE users SET balance = balance - ?, withdrawable_balance = withdrawable_balance + ? WHERE id = ? AND balance >= ?");
                $updateStmt->bind_param("ddid", $amount, $amount, $currentUser['id'], $amount);
                $updateStmt->execute();
                
                if ($updateStmt->affected_rows > 0) {
                    if ($existingFollow) {
                        // Update existing follow record - add to both first_balance and current_balance
                        $newFirstBalance = floatval($existingFollow['first_balance']) + $amount;
                        $newCurrentBalance = floatval($existingFollow['current_balance']) + $amount;
                        
                        $updateStmt2 = $conn->prepare("UPDATE followed_traders SET first_balance = ?, current_balance = ? WHERE id = ?");
                        $updateStmt2->bind_param("ddi", $newFirstBalance, $newCurrentBalance, $existingFollow['id']);
                        $updateStmt2->execute();
                        $updateStmt2->close();
                        
                        // Record this addition in investment_history
                        $historyStmt = $conn->prepare("INSERT INTO investment_history (followed_trader_id, user_id, trader_id, amount, type) VALUES (?, ?, ?, ?, 'addition')");
                        $historyStmt->bind_param("iiid", $existingFollow['id'], $currentUser['id'], $postTraderId, $amount);
                        $historyStmt->execute();
                        $historyStmt->close();
                    } else {
                        // Insert new follow record
                        $insertStmt = $conn->prepare("INSERT INTO followed_traders (user_id, trader_id, first_balance, current_balance, status) VALUES (?, ?, ?, ?, 'active')");
                        $insertStmt->bind_param("iidd", $currentUser['id'], $postTraderId, $amount, $amount);
                        $insertStmt->execute();
                        $followedTraderId = $conn->insert_id;
                        $insertStmt->close();
                        
                        // Record initial investment in investment_history
                        $historyStmt = $conn->prepare("INSERT INTO investment_history (followed_trader_id, user_id, trader_id, amount, type) VALUES (?, ?, ?, ?, 'initial')");
                        $historyStmt->bind_param("iiid", $followedTraderId, $currentUser['id'], $postTraderId, $amount);
                        $historyStmt->execute();
                        $historyStmt->close();
                        
                        // Update trader followers count (only for new follows)
                        $updateFollowersStmt = $conn->prepare("UPDATE traders SET followers = followers + 1 WHERE id = ?");
                        $updateFollowersStmt->bind_param("i", $postTraderId);
                        $updateFollowersStmt->execute();
                        $updateFollowersStmt->close();
                    }
                    
                    $conn->commit();
                    // Refresh user data
                    $currentUser = getCurrentUser();
                    // Redirect to same page to show updated data with success message
                    header("Location: " . WEB_URL . "/trader/" . $postTraderId . "?success=1");
                    exit;
                } else {
                    $conn->rollback();
                    $followError = 'insufficientBalance';
                }
                $updateStmt->close();
            } catch (Exception $e) {
                $conn->rollback();
                $followError = 'followFailed';
                error_log("Follow trader failed: " . $e->getMessage());
            }
        }
    }
}

// Get trader details with user info
$trader = null;
$stmt = $conn->prepare("SELECT t.id, t.user_id, t.username, t.name, t.description, t.avatar_url, t.roi_30d, t.followers, t.aum, t.mdd_30d, t.status, u.email as user_email, u.name_surname as user_name_surname FROM traders t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ? AND t.status = 'active'");
$stmt->bind_param("i", $traderId);
$stmt->execute();
$result = $stmt->get_result();
$trader = $result->fetch_assoc();
$stmt->close();

// Determine display name: if user has name_surname, use it, otherwise use email, otherwise use trader name
if ($trader) {
    if (!empty($trader['user_name_surname'])) {
        $trader['display_name'] = $trader['user_name_surname'];
    } elseif (!empty($trader['user_email'])) {
        $trader['display_name'] = $trader['user_email'];
    } else {
        $trader['display_name'] = $trader['name'];
    }
}

if (!$trader) {
    header("Location: " . WEB_URL . "/traders");
    exit;
}

// Format AUM for display
function formatAUM($aum) {
    if ($aum >= 1000000) {
        return '$' . number_format($aum / 1000000, 2) . 'M';
    } elseif ($aum >= 1000) {
        return '$' . number_format($aum / 1000, 1) . 'K';
    } else {
        return '$' . number_format($aum, 0);
    }
}

// Get user's investment in this trader (refresh after POST)
$userInvestment = null;
$stmt = $conn->prepare("SELECT id, first_balance, current_balance, created_at FROM followed_traders WHERE user_id = ? AND trader_id = ? AND status = 'active'");
$stmt->bind_param("ii", $currentUser['id'], $traderId);
$stmt->execute();
$result = $stmt->get_result();
$userInvestment = $result->fetch_assoc();
$stmt->close();

// Get investment history from database
$investmentHistory = [];
if ($userInvestment) {
    $historyStmt = $conn->prepare("SELECT amount, type, created_at FROM investment_history WHERE followed_trader_id = ? ORDER BY created_at ASC");
    $historyStmt->bind_param("i", $userInvestment['id']);
    $historyStmt->execute();
    $historyResult = $historyStmt->get_result();
    while ($row = $historyResult->fetch_assoc()) {
        $investmentHistory[] = [
            'type' => $row['type'],
            'amount' => floatval($row['amount']),
            'date' => $row['created_at'],
            'description' => $row['type'] === 'initial' ? 'initialInvestment' : 'balanceAddition'
        ];
    }
    $historyStmt->close();
}

// Get trader's trade history from database
$traderTrades = [];
$tradesStmt = $conn->prepare("
    SELECT 
        id,
        pair,
        type,
        leverage,
        entry_price,
        exit_price,
        profit,
        profit_percent,
        entry_time,
        exit_time,
        status
    FROM trades
    WHERE trader_id = ? AND status = 'closed' AND exit_time IS NOT NULL
    ORDER BY exit_time DESC
    LIMIT 20
");
$tradesStmt->bind_param("i", $traderId);
$tradesStmt->execute();
$tradesResult = $tradesStmt->get_result();
while ($row = $tradesResult->fetch_assoc()) {
    $traderTrades[] = $row;
}
$tradesStmt->close();

$avatarUrl = !empty($trader['avatar_url']) ? $trader['avatar_url'] : 'vendor/trader.png';
$roi = floatval($trader['roi_30d']);
$followers = intval($trader['followers']);
$aum = floatval($trader['aum']);
$mdd = floatval($trader['mdd_30d']);
$formattedAUM = formatAUM($aum);

// Calculate return if user has investment
$returnPercent = 0;
$totalInvested = 0;
$currentValue = 0;
if ($userInvestment) {
    $totalInvested = floatval($userInvestment['first_balance']);
    $currentValue = floatval($userInvestment['current_balance']);
    $returnPercent = $totalInvested > 0 ? (($currentValue - $totalInvested) / $totalInvested) * 100 : 0;
}
?>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Navigation -->
    <?php include(V_PATH."topnav.php"); ?>

    <!-- Trader Detail Section -->
    <section class="top-traders-section" style="padding-top: 120px;">
        <div class="container">
            <?php if ($followSuccess): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <span data-key="traderFollowedSuccess">Trader başarıyla takip edilmeye başlandı!</span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($followError): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span data-key="<?php echo htmlspecialchars($followError); ?>">Hata oluştu!</span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Back Button -->
            <div class="row mb-4">
                <div class="col-12">
                    <a href="<?php echo WEB_URL; ?>/traders" class="btn btn-outline-modern mb-3">
                        <i class="fas fa-arrow-left me-2"></i><span data-key="back">Geri</span>
                    </a>
                </div>
            </div>

            <!-- Trader Header Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="glass-card trader-card-modern">
                        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-4">
                            <div class="trader-avatar-modern flex-shrink-0">
                                <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="<?php echo htmlspecialchars($trader['name']); ?>" class="trader-avatar-img-modern" style="width: 120px; height: 120px;">
                            </div>
                            <div class="flex-grow-1">
                                <h1 class="h2 fw-bold mb-2"><?php echo htmlspecialchars($trader['display_name'] ?? $trader['name']); ?></h1>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars($trader['description'] ?? ''); ?></p>
                                <div class="d-flex flex-wrap gap-3">
                                    <div>
                                        <div class="h4 fw-bold text-success mb-1">+<?php echo number_format($roi, 1); ?>%</div>
                                        <div class="text-muted small" data-key="roi30D">30D ROI</div>
                                    </div>
                                    <div>
                                        <div class="h4 fw-bold mb-1"><?php echo number_format($followers); ?></div>
                                        <div class="text-muted small" data-key="followers">Followers</div>
                                    </div>
                                    <div>
                                        <div class="h4 fw-bold mb-1"><?php echo htmlspecialchars($formattedAUM); ?></div>
                                        <div class="text-muted small" data-key="aum">AUM</div>
                                    </div>
                                    <div>
                                        <div class="h4 fw-bold mb-1"><?php echo number_format($mdd, 2); ?>%</div>
                                        <div class="text-muted small" data-key="mdd30D">30D MDD</div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <?php 
                                $hasExistingFollow = $userInvestment !== null;
                                $buttonKey = $hasExistingFollow ? 'addBalance' : 'copy';
                                $buttonText = $hasExistingFollow ? 'Ekle' : 'Kopyala';
                                $existingBalance = $hasExistingFollow ? floatval($userInvestment['first_balance']) : 0;
                                ?>
                                <button class="btn btn-modern" onclick="openFollowModal(<?php echo intval($trader['id']); ?>, '<?php echo htmlspecialchars(addslashes($trader['name'])); ?>', <?php echo $hasExistingFollow ? 'true' : 'false'; ?>, <?php echo $existingBalance; ?>)" data-key="<?php echo $buttonKey; ?>">
                                    <i class="fas fa-<?php echo $hasExistingFollow ? 'plus' : 'copy'; ?> me-2"></i><span data-key="<?php echo $buttonKey; ?>"><?php echo htmlspecialchars($buttonText); ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Investment Card (if invested) -->
            <?php if ($userInvestment): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="glass-card">
                        <h3 class="h5 fw-bold mb-4" data-key="myInvestment">Yatırımım</h3>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="h4 fw-bold text-primary mb-1">$<?php echo number_format($totalInvested, 2, '.', ','); ?></div>
                                    <div class="text-muted small" data-key="totalInvested">Toplam Yatırım</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="h4 fw-bold <?php echo $returnPercent >= 0 ? 'text-success' : 'text-danger'; ?> mb-1">
                                        <?php echo $returnPercent >= 0 ? '+' : ''; ?><?php echo number_format($returnPercent, 2); ?>%
                                    </div>
                                    <div class="text-muted small" data-key="return">Return</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="h4 fw-bold mb-1">$<?php echo number_format($currentValue, 2, '.', ','); ?></div>
                                    <div class="text-muted small" data-key="currentValue">Güncel Değer</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Investment History -->
                        <?php if (!empty($investmentHistory)): ?>
                        <div class="mt-4">
                            <h5 class="h6 fw-bold mb-3" data-key="investmentHistory">Yatırım Geçmişi</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th data-key="date">Tarih</th>
                                            <th data-key="type">Tür</th>
                                            <th data-key="amount">Miktar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($investmentHistory as $history): ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y H:i', strtotime($history['date'])); ?></td>
                                            <td><span data-key="<?php echo htmlspecialchars($history['description']); ?>"><?php echo $history['type'] === 'initial' ? 'İlk Yatırım' : 'Bakiye Ekleme'; ?></span></td>
                                            <td class="fw-bold">$<?php echo number_format($history['amount'], 2, '.', ','); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Trade History -->
            <div class="row">
                <div class="col-12">
                    <div class="glass-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="h5 fw-bold mb-0" data-key="tradeHistory">İşlem Geçmişi</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover trade-table">
                                <thead>
                                    <tr>
                                        <th>Pair/Type</th>
                                        <th data-key="leverage">Leverage</th>
                                        <th data-key="entryPrice">Entry Price</th>
                                        <th data-key="exitPrice">Exit Price</th>
                                        <th data-key="profit">Profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($traderTrades)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                            <p class="text-muted mb-0" data-key="noTradesYet">Henüz işlem geçmişi yok</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($traderTrades as $trade): 
                                        $typeClass = $trade['type'] === 'LONG' ? 'bg-success' : ($trade['type'] === 'SHORT' ? 'bg-danger' : 'bg-primary');
                                        $profit = floatval($trade['profit']);
                                        $profitPercent = floatval($trade['profit_percent']);
                                        $profitClass = $profit >= 0 ? 'text-success' : 'text-danger';
                                        $profitSign = $profit >= 0 ? '+' : '';
                                        $leverage = !empty($trade['leverage']) ? $trade['leverage'] : '-';
                                    ?>
                                    <tr class="trade-row">
                                        <td class="py-3 px-4">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <span class="badge <?php echo $typeClass; ?>" data-key="<?php echo strtolower($trade['type']); ?>"><?php echo htmlspecialchars($trade['type']); ?></span>
                                                <span class="fw-medium"><?php echo htmlspecialchars($trade['pair']); ?></span>
                                            </div>
                                            <div class="text-muted small"><?php echo date('d.m.Y H:i', strtotime($trade['entry_time'])); ?></div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="fw-medium"><?php echo htmlspecialchars($leverage); ?></div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="fw-medium">$<?php echo number_format(floatval($trade['entry_price']), 2, '.', ','); ?></div>
                                            <div class="text-muted small"><?php echo date('d.m.Y H:i', strtotime($trade['entry_time'])); ?></div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="fw-medium">$<?php echo number_format(floatval($trade['exit_price']), 2, '.', ','); ?></div>
                                            <div class="text-muted small"><?php echo date('d.m.Y H:i', strtotime($trade['exit_time'])); ?></div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div>
                                                <div class="fw-medium <?php echo $profitClass; ?>"><?php echo $profitSign; ?>$<?php echo number_format(abs($profit), 2, '.', ','); ?></div>
                                                <div class="<?php echo $profitClass; ?> small"><?php echo $profitSign; ?><?php echo number_format($profitPercent, 2); ?>%</div>
                                            </div>
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
        </div>
    </section>

    <!-- Follow Trader Modal (same as traders.php) -->
    <?php
    // Get available balance (balance - total deposited amount)
    $availableBalance = isset($currentUser['balance']) && $currentUser['balance'] !== null ? floatval($currentUser['balance']) : 0.00;
    $formattedAvailableBalance = number_format($availableBalance, 2, '.', ',');
    ?>
    <div class="modal fade" id="followTraderModal" tabindex="-1" aria-labelledby="followTraderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card" style="border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title fw-bold" id="followTraderModalLabel" data-key="followTrader">Trader Takip Et</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="followTraderForm" method="POST" action="">
                        <input type="hidden" name="follow_trader" value="1">
                        <input type="hidden" name="trader_id" id="modalTraderId" value="">
                        
                        <div class="mb-3" id="existingBalanceInfo" style="display: none;">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <span id="existingBalanceText" data-key="existingBalanceInfo">Bu trader'a şu ana kadar <strong id="existingBalanceAmount">$0.00</strong> yatırdınız.</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <span id="modalTraderInfo" data-key="followTraderInfo">X hesabı takip edeceksiniz. Tüm işlemlerini (long/short/spot/margin) yatırdığınız miktarca takip edeceksiniz.</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="followAmount" class="form-label fw-bold" data-key="followAmount">Yatırılacak Miktar (USD)</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-dollar-sign"></i>
                                </span>
                                <input type="number" class="form-control form-control-modern" id="followAmount" name="amount" step="0.01" min="0.01" max="<?php echo htmlspecialchars($availableBalance); ?>" required>
                                <span class="input-group-text">USD</span>
                            </div>
                            <small class="text-muted"><span data-key="availableBalance">Kullanılabilir Bakiye:</span> $<span id="modalAvailableBalance"><?php echo htmlspecialchars($formattedAvailableBalance); ?></span></small>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted" style="font-size: 0.85rem;">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <span data-key="followTraderWarning">Bu noktada takip ederek yaptırdığınız copy trade işleminin kar/zarar durumundan platform sorumlu değildir. Yatırımcıyı kendiniz analiz ederek karar veriniz.</span>
                            </small>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-modern" data-bs-dismiss="modal" data-key="cancel">
                                <i class="fas fa-times me-2"></i><span data-key="cancel">İptal</span>
                            </button>
                            <button type="submit" class="btn btn-modern flex-grow-1" data-key="confirmFollow">
                                <i class="fas fa-check me-2"></i><span data-key="confirmFollow">Onayla ve Takip Et</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Open follow modal function (same as traders.php)
        function openFollowModal(traderId, traderName, hasExistingFollow, existingBalance) {
            const modal = new bootstrap.Modal(document.getElementById('followTraderModal'));
            document.getElementById('modalTraderId').value = traderId;
            
            // Show/hide existing balance info
            const existingBalanceInfo = document.getElementById('existingBalanceInfo');
            const existingBalanceText = document.getElementById('existingBalanceText');
            const existingBalanceAmount = document.getElementById('existingBalanceAmount');
            if (hasExistingFollow && existingBalance > 0) {
                existingBalanceInfo.style.display = 'block';
                const formattedAmount = '$' + parseFloat(existingBalance).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                existingBalanceAmount.textContent = formattedAmount;
                
                // Update text with translation
                if (typeof translations !== 'undefined') {
                    let currentLang = document.documentElement.getAttribute('lang') || localStorage.getItem('selectedLanguage') || 'tr';
                    if (translations[currentLang] && translations[currentLang].existingBalanceInfo) {
                        existingBalanceText.textContent = translations[currentLang].existingBalanceInfo.replace('{amount}', formattedAmount);
                    }
                }
            } else {
                existingBalanceInfo.style.display = 'none';
            }
            
            // Update trader info text
            const infoText = document.getElementById('modalTraderInfo');
            if (infoText) {
                let currentLang = document.documentElement.getAttribute('lang') || localStorage.getItem('selectedLanguage') || 'tr';
                let followInfoText = traderName + ' hesabı takip edeceksiniz. Tüm işlemlerini (long/short/spot/margin) yatırdığınız miktarca takip edeceksiniz.';
                
                if (typeof translations !== 'undefined' && translations[currentLang] && translations[currentLang].followTraderInfo) {
                    followInfoText = translations[currentLang].followTraderInfo.replace('X', traderName);
                }
                
                infoText.textContent = followInfoText;
            }
            
            // Reset form
            document.getElementById('followTraderForm').reset();
            document.getElementById('modalTraderId').value = traderId;
            
            // Set max amount
            const amountInput = document.getElementById('followAmount');
            const availableBalance = parseFloat("<?php echo htmlspecialchars($availableBalance); ?>");
            if (amountInput) {
                amountInput.setAttribute('max', availableBalance);
            }
            
            modal.show();
        }
    </script>

