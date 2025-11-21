
    <title>Wallet - CopyStar</title>

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

// Handle withdrawal request
$withdrawalSuccess = false;
$withdrawalError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_request'])) {
    // Check KYC verification
    if (!isset($currentUser['kyc_verified']) || $currentUser['kyc_verified'] == 0) {
        $withdrawalError = 'kycRequiredForWithdrawal';
    } else {
        $network = isset($_POST['network']) ? trim($_POST['network']) : '';
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $networkFee = isset($_POST['network_fee']) ? floatval($_POST['network_fee']) : 0;
        $receiveAmount = isset($_POST['receive_amount']) ? floatval($_POST['receive_amount']) : 0;
        
        // Validation
        if (empty($network) || !in_array($network, ['eth', 'bnb', 'trx'])) {
            $withdrawalError = 'invalidNetwork';
        } elseif ($amount <= 0) {
            $withdrawalError = 'invalidAmount';
        } elseif ($amount < 10) {
            $withdrawalError = 'minWithdrawAmount';
        } elseif ($amount > $currentUser['balance']) {
            $withdrawalError = 'insufficientBalance';
        } elseif (empty($address)) {
            $withdrawalError = 'addressRequired';
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert withdrawal request
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, network, token, amount, address, network_fee, receive_amount, status) VALUES (?, 'withdraw', ?, 'usdt', ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("isdsdd", $currentUser['id'], $network, $amount, $address, $networkFee, $receiveAmount);
                
                if ($stmt->execute()) {
                    $transactionId = $conn->insert_id;
                    $stmt->close();
                    
                    // Deduct from user's balance immediately (reserve the amount)
                    $deductStmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
                    $deductStmt->bind_param("did", $amount, $currentUser['id'], $amount);
                    $deductStmt->execute();
                    
                    if ($deductStmt->affected_rows > 0) {
                        $conn->commit();
                        $withdrawalSuccess = true;
                        // Refresh user data
                        $currentUser = getCurrentUser();
                    } else {
                        $conn->rollback();
                        $withdrawalError = 'insufficientBalance';
                    }
                    $deductStmt->close();
                } else {
                    $conn->rollback();
                    $withdrawalError = 'withdrawalRequestFailed';
                    $stmt->close();
                }
            } catch (Exception $e) {
                $conn->rollback();
                $withdrawalError = 'withdrawalRequestFailed';
            }
        }
    }
}

// Get latest transactions
$transactions = [];
$stmt = $conn->prepare("SELECT id, type, network, token, amount, status, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $currentUser['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

// Format balance
$balance = isset($currentUser['balance']) && $currentUser['balance'] !== null ? floatval($currentUser['balance']) : 0.00;
$formattedBalance = number_format($balance, 2, '.', ',');

$withdrawableBalance = isset($currentUser['withdrawable_balance']) && $currentUser['withdrawable_balance'] !== null ? floatval($currentUser['withdrawable_balance']) : 0.00;
$formattedWithdrawableBalance = number_format($withdrawableBalance, 2, '.', ',');

// Get wallet addresses
$ethWalletAddress = isset($currentUser['eth_wallet_address']) ? $currentUser['eth_wallet_address'] : '';
$tronWalletAddress = isset($currentUser['tron_wallet_address']) ? $currentUser['tron_wallet_address'] : '';
?>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Navigation -->
    <?php include(V_PATH."topnav.php"); ?>

    <!-- Wallet Section -->
    <section class="dashboard-section" style="padding-top: 20px;">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h2 fw-bold mb-0" data-key="wallet">Cüzdan</h1>
                </div>
            </div>

            <!-- Balance Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="balance-card glass-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-2" data-key="totalBalance">Toplam Bakiye</p>
                                <h2 class="h1 fw-bold mb-0">$<?php echo htmlspecialchars($formattedBalance); ?></h2>
                            </div>
                            <div class="balance-icon">
                                <i class="fas fa-wallet"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($withdrawalSuccess): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <span data-key="withdrawalRequestSubmitted">Para çekme talebiniz başarıyla gönderildi!</span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($withdrawalError): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span data-key="<?php echo htmlspecialchars($withdrawalError); ?>">Hata oluştu!</span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Deposit and Withdraw Tabs -->
            <div class="row">
                <div class="col-12">

                    <div class="tab-content" id="walletTabContent">
                        <!-- Deposit Tab -->
                        <div class="tab-pane fade show active" id="deposit" role="tabpanel">
                            <div class="glass-card p-4">
                                <div class="alert alert-info mb-4" role="alert">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <span data-key="depositInfo">Verilen adrese yatırılan para direk hesabınıza geçecektir. Tüm yatırımlar otomatik olarak USD'ye dönüştürülecektir.</span>
                                </div>

                                <!-- Network Selection -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold mb-2 small" data-key="selectNetwork">Ağ Seçin</label>
                                    <div class="row g-2" id="networkOptions">
                                        <div class="col-4 network-option" data-network="eth">
                                            <div class="network-card-compact glass-card p-2 text-center" data-network-value="eth">
                                                <img src="vendor/eth.png" alt="Ethereum" class="network-logo-compact mb-1">
                                                <div class="fw-bold small">ETH</div>
                                            </div>
                                        </div>
                                        <div class="col-4 network-option" data-network="bnb">
                                            <div class="network-card-compact glass-card p-2 text-center active" data-network-value="bnb">
                                                <img src="vendor/bnb.png" alt="BNB Chain" class="network-logo-compact mb-1">
                                                <div class="fw-bold small">BNB</div>
                                            </div>
                                        </div>
                                        <div class="col-4 network-option" data-network="trx">
                                            <div class="network-card-compact glass-card p-2 text-center" data-network-value="trx">
                                                <img src="vendor/trx.png" alt="Tron" class="network-logo-compact mb-1">
                                                <div class="fw-bold small">TRX</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Token Selection -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold mb-2 small" data-key="selectToken">Token Seçin</label>
                                    <div class="row g-2" id="tokenOptions">
                                        <!-- ETH Network Tokens -->
                                        <div class="col-4 token-option" data-network="eth" style="display: none;">
                                            <div class="token-card-compact glass-card p-2 text-center">
                                                <img src="vendor/eth.png" alt="ETH" class="token-logo-compact mb-1">
                                                <div class="fw-bold small">ETH</div>
                                            </div>
                                        </div>
                                        <div class="col-4 token-option" data-network="eth" style="display: none;">
                                            <div class="token-card-compact glass-card p-2 text-center">
                                                <img src="vendor/usdt.png" alt="USDT" class="token-logo-compact mb-1">
                                                <div class="fw-bold small">USDT</div>
                                            </div>
                                        </div>
                                        <div class="col-4 token-option" data-network="eth" style="display: none;">
                                            <div class="token-card-compact glass-card p-2 text-center">
                                                <img src="vendor/usdc.png" alt="USDC" class="token-logo-compact mb-1">
                                                <div class="fw-bold small">USDC</div>
                                            </div>
                                        </div>
                                        <!-- BNB Network Tokens -->
                                        <div class="col-4 token-option" data-network="bnb" style="display: block;">
                                            <div class="token-card-compact glass-card p-2 text-center">
                                                <img src="vendor/bnb.png" alt="BNB" class="token-logo-compact mb-1">
                                                <div class="fw-bold small">BNB</div>
                                            </div>
                                        </div>
                                        <div class="col-4 token-option" data-network="bnb" style="display: block;">
                                            <div class="token-card-compact glass-card p-2 text-center active">
                                                <img src="vendor/usdt.png" alt="USDT" class="token-logo-compact mb-1">
                                                <div class="fw-bold small">USDT</div>
                                            </div>
                                        </div>
                                        <div class="col-4 token-option" data-network="bnb" style="display: block;">
                                            <div class="token-card-compact glass-card p-2 text-center">
                                                <img src="vendor/usdc.png" alt="USDC" class="token-logo-compact mb-1">
                                                <div class="fw-bold small">USDC</div>
                                            </div>
                                        </div>
                                        <!-- TRX Network Tokens -->
                                        <div class="col-4 token-option" data-network="trx" style="display: none;">
                                            <div class="token-card-compact glass-card p-2 text-center">
                                                <img src="vendor/trx.png" alt="TRX" class="token-logo-compact mb-1">
                                                <div class="fw-bold small">TRX</div>
                                            </div>
                                        </div>
                                        <div class="col-4 token-option" data-network="trx" style="display: none;">
                                            <div class="token-card-compact glass-card p-2 text-center">
                                                <img src="vendor/usdt.png" alt="USDT" class="token-logo-compact mb-1">
                                                <div class="fw-bold small">USDT</div>
                                            </div>
                                        </div>
                                        <div class="col-4 token-option" data-network="trx" style="display: none;">
                                            <div class="token-card-compact glass-card p-2 text-center">
                                                <img src="vendor/usdc.png" alt="USDC" class="token-logo-compact mb-1">
                                                <div class="fw-bold small">USDC</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Deposit Address -->
                                <div class="deposit-address-section" id="depositAddressSection" style="display: none;">
                                    <div class="glass-card p-4 mb-3">
                                        <h5 class="fw-bold mb-3" data-key="depositAddress">Yatırım Adresi</h5>
                                        <div class="row g-4">
                                            <div class="col-12 col-md-6">
                                                <div class="qr-code-container text-center mb-3">
                                                    <img id="depositQRCodeImg" src="" alt="QR Code" class="img-fluid" style="max-width: 200px; height: auto; display: none;">
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label small text-muted mb-2" data-key="walletAddress">Cüzdan Adresi</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control font-monospace" id="depositAddress" readonly value="">
                                                        <button class="btn btn-modern" type="button" id="copyAddressBtn">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="alert alert-warning small mb-0">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <span data-key="depositWarning">Sadece seçilen ağ ve token ile yatırım yapın. Yanlış ağ veya token ile yatırım yaparsanız fonlarınız kaybolabilir.</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Withdraw Tab -->
                        <div class="tab-pane fade" id="withdraw" role="tabpanel">
                            <div class="glass-card p-4">
                                <div class="alert alert-info mb-4" role="alert">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <span data-key="withdrawInfo">Para çekme işlemleri sadece USDT olarak yapılabilmektedir.</span>
                                </div>

                                <!-- Network Selection for Withdraw -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold mb-2 small" data-key="selectNetwork">Ağ Seçin</label>
                                    <div class="row g-2" id="withdrawNetworkOptions">
                                        <div class="col-4 network-option" data-network="eth">
                                            <div class="network-card-compact glass-card p-2 text-center" data-network-value="eth">
                                                <img src="vendor/eth.png" alt="Ethereum" class="network-logo-compact mb-1">
                                                <div class="fw-bold small">ETH</div>
                                            </div>
                                        </div>
                                        <div class="col-4 network-option" data-network="bnb">
                                            <div class="network-card-compact glass-card p-2 text-center active" data-network-value="bnb">
                                                <img src="vendor/bnb.png" alt="BNB Chain" class="network-logo-compact mb-1">
                                                <div class="fw-bold small">BNB</div>
                                            </div>
                                        </div>
                                        <div class="col-4 network-option" data-network="trx">
                                            <div class="network-card-compact glass-card p-2 text-center" data-network-value="trx">
                                                <img src="vendor/trx.png" alt="Tron" class="network-logo-compact mb-1">
                                                <div class="fw-bold small">TRX</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Withdraw Form -->
                                <div class="withdraw-form">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold" data-key="withdrawAmount">Çekilecek Miktar (USDT)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-dollar-sign"></i>
                                            </span>
                                            <input type="number" class="form-control" id="withdrawAmount" placeholder="0.00" step="0.01" min="10" max="<?php echo $balance; ?>">
                                            <button type="button" class="btn btn-outline-primary" id="maxWithdrawBtn" title="Maksimum Çek">
                                                <i class="fas fa-arrow-up"></i> Max
                                            </button>
                                            <span class="input-group-text">USDT</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small class="text-muted" data-key="withdrawableBalance">
                                                Çekilebilir: <strong class="text-primary">$<span id="withdrawableBalanceAmount"><?php echo htmlspecialchars($formattedBalance); ?></span></strong>
                                            </small>
                                            <small class="text-muted">
                                                Min: <strong>$10.00</strong>
                                            </small>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold" data-key="withdrawAddress">Alıcı Cüzdan Adresi</label>
                                        <input type="text" class="form-control font-monospace" id="withdrawAddress" placeholder="0x..." required>
                                        <small class="text-muted" data-key="withdrawAddressHint">Yalnızca seçilen ağa uygun adres giriniz</small>
                                    </div>

                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted" data-key="networkFee">Ağ Ücreti</span>
                                            <span class="fw-bold" id="networkFeeAmount">~$5.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted" data-key="youWillReceive">Alacağınız Miktar</span>
                                            <span class="fw-bold text-success" id="receiveAmount">0.00 USDT</span>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between">
                                            <span class="fw-bold" data-key="total">Toplam</span>
                                            <span class="fw-bold" id="totalWithdraw">0.00 USDT</span>
                                        </div>
                                    </div>

                                    <button class="btn btn-primary btn-modern w-100" type="button" id="withdrawBtn" data-key="withdraw">Çek</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Switch Buttons (Outside tabs) -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div id="switchButtonContainer">
                                <!-- Switch to Withdraw Button (shown when deposit tab is active) -->
                                <button class="btn btn-outline-modern w-100" id="switchToWithdraw" style="display: block;">
                                    <i class="fas fa-arrow-up me-2"></i><span data-key="withdraw">Çek</span>
                                </button>
                                <!-- Switch to Deposit Button (shown when withdraw tab is active) -->
                                <button class="btn btn-outline-modern w-100" id="switchToDeposit" style="display: none;">
                                    <i class="fas fa-arrow-down me-2"></i><span data-key="deposit">Yatır</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Latest Transactions -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="glass-card p-4">
                        <h3 class="h4 fw-bold mb-4" data-key="latestTransactions">Son İşlemler</h3>
                        <div class="table-responsive">
                            <table class="table table-hover transactions-table">
                                <thead>
                                    <tr>
                                        <th data-key="date">Tarih</th>
                                        <th data-key="transactionType">İşlem</th>
                                        <th data-key="amount">Tutar</th>
                                        <th data-key="network">Ağ</th>
                                        <th data-key="status">Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted mb-0" data-key="noTransactionsYet">Henüz işlem geçmişi yok</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($transactions as $tx): 
                                        $date = new DateTime($tx['created_at']);
                                        $formattedDate = $date->format('d.m.Y');
                                        $formattedTime = $date->format('H:i');
                                        $formattedAmount = number_format($tx['amount'], 2, '.', ',');
                                        
                                        // Network logo mapping
                                        $networkLogos = [
                                            'eth' => 'vendor/eth.png',
                                            'bnb' => 'vendor/bnb.png',
                                            'trx' => 'vendor/trx.png'
                                        ];
                                        
                                        $networkNames = [
                                            'eth' => 'Ethereum',
                                            'bnb' => 'BNB Chain',
                                            'trx' => 'Tron'
                                        ];
                                        
                                        // Status badge mapping
                                        $statusBadges = [
                                            'pending' => ['class' => 'bg-warning', 'icon' => 'fa-clock', 'key' => 'pending'],
                                            'processing' => ['class' => 'bg-info', 'icon' => 'fa-spinner fa-spin', 'key' => 'processing'],
                                            'completed' => ['class' => 'bg-success', 'icon' => 'fa-check-circle', 'key' => 'completed'],
                                            'rejected' => ['class' => 'bg-danger', 'icon' => 'fa-times-circle', 'key' => 'rejected'],
                                            'cancelled' => ['class' => 'bg-secondary', 'icon' => 'fa-ban', 'key' => 'cancelled']
                                        ];
                                        
                                        $statusInfo = $statusBadges[$tx['status']] ?? $statusBadges['pending'];
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-medium"><?php echo htmlspecialchars($formattedDate); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($formattedTime); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($tx['type'] == 'deposit'): ?>
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="fas fa-arrow-down me-1"></i>
                                                <span data-key="deposit">Yatır</span>
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger">
                                                <i class="fas fa-arrow-up me-1"></i>
                                                <span data-key="withdraw">Çek</span>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold <?php echo $tx['type'] == 'deposit' ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $tx['type'] == 'deposit' ? '+' : '-'; ?>$<?php echo htmlspecialchars($formattedAmount); ?>
                                            </div>
                                            <small class="text-muted"><?php echo strtoupper($tx['token']); ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="<?php echo htmlspecialchars($networkLogos[$tx['network']]); ?>" alt="<?php echo htmlspecialchars($tx['network']); ?>" class="transaction-network-logo">
                                                <span class="small"><?php echo htmlspecialchars($networkNames[$tx['network']]); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo htmlspecialchars($statusInfo['class']); ?>">
                                                <i class="fas <?php echo htmlspecialchars($statusInfo['icon']); ?> me-1"></i>
                                                <span data-key="<?php echo htmlspecialchars($statusInfo['key']); ?>"><?php 
                                                    echo $statusInfo['key'] == 'pending' ? 'Beklemede' : 
                                                        ($statusInfo['key'] == 'processing' ? 'İşlemde' : 
                                                        ($statusInfo['key'] == 'completed' ? 'Tamamlandı' : 
                                                        ($statusInfo['key'] == 'rejected' ? 'Reddedildi' : 'İptal Edildi'))); 
                                                ?></span>
                                            </span>
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

    <script>
        // Wallet functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Deposit network selection
            const networkCards = document.querySelectorAll('#networkOptions .network-card-compact');
            const tokenOptions = document.querySelectorAll('.token-option');
            const depositAddressSection = document.getElementById('depositAddressSection');
            const depositAddressInput = document.getElementById('depositAddress');
            const qrCodeImg = document.getElementById('depositQRCodeImg');
            const copyAddressBtn = document.getElementById('copyAddressBtn');
            
            // Wallet addresses from database
            const addresses = {
                eth: {
                    eth: '<?php echo htmlspecialchars($ethWalletAddress); ?>',
                    usdt: '<?php echo htmlspecialchars($ethWalletAddress); ?>',
                    usdc: '<?php echo htmlspecialchars($ethWalletAddress); ?>'
                },
                bnb: {
                    bnb: '<?php echo htmlspecialchars($ethWalletAddress); ?>',
                    usdt: '<?php echo htmlspecialchars($ethWalletAddress); ?>',
                    usdc: '<?php echo htmlspecialchars($ethWalletAddress); ?>'
                },
                trx: {
                    trx: '<?php echo htmlspecialchars($tronWalletAddress); ?>',
                    usdt: '<?php echo htmlspecialchars($tronWalletAddress); ?>',
                    usdc: '<?php echo htmlspecialchars($tronWalletAddress); ?>'
                }
            };
            
            let selectedNetwork = 'bnb';
            let selectedToken = 'usdt';
            let isInitializing = true;
            
            // Network card click handler
            networkCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove active class from all network cards
                    document.querySelectorAll('#networkOptions .network-card-compact').forEach(c => {
                        c.classList.remove('active');
                    });
                    // Add active class to selected network
                    this.classList.add('active');
                    selectedNetwork = this.getAttribute('data-network-value');
                    selectedToken = null; // Reset token selection
                    isInitializing = false;
                    updateTokenOptions();
                });
            });
            
            // Set initial network as active (BNB)
            networkCards.forEach(card => {
                if (card.getAttribute('data-network-value') === 'bnb') {
                    card.classList.add('active');
                }
            });
            
            // Initialize with BNB network and USDT token selected
            updateTokenOptions();
            
            // Auto-select USDT token and show address after initialization
            setTimeout(function() {
                // Find USDT token in BNB network
                const bnbTokenOptions = document.querySelectorAll('.token-option[data-network="bnb"]');
                bnbTokenOptions.forEach(option => {
                    if (option.style.display !== 'none') {
                        const tokenCard = option.querySelector('.token-card-compact');
                        if (tokenCard) {
                            const tokenName = tokenCard.querySelector('.fw-bold').textContent.toLowerCase();
                            if (tokenName === 'usdt') {
                                tokenCard.classList.add('active');
                                selectedToken = 'usdt';
                                showDepositAddress();
                                isInitializing = false;
                            }
                        }
                    }
                });
            }, 200);
            
            // Token selection handler
            tokenOptions.forEach(option => {
                const tokenCard = option.querySelector('.token-card-compact');
                if (tokenCard) {
                    tokenCard.addEventListener('click', function(e) {
                        e.stopPropagation();
                        // Only allow selection if this token is visible for current network
                        if (option.style.display !== 'none') {
                            // Remove active class from all tokens
                            document.querySelectorAll('.token-card-compact').forEach(card => {
                                card.classList.remove('active');
                            });
                            // Add active class to selected token
                            this.classList.add('active');
                            
                            // Get token name from the card
                            const tokenName = this.querySelector('.fw-bold').textContent.toLowerCase();
                            selectedToken = tokenName;
                            showDepositAddress();
                        }
                    });
                }
            });
            
            function updateTokenOptions() {
                tokenOptions.forEach(option => {
                    if (option.getAttribute('data-network') === selectedNetwork) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                    }
                });
                
                // Only reset token selection if network actually changed (not during initialization)
                if (!isInitializing) {
                    depositAddressSection.style.display = 'none';
                    document.querySelectorAll('.token-card-compact').forEach(card => {
                        card.classList.remove('active');
                    });
                    selectedToken = null; // Reset token selection when network changes
                }
            }
            
            function showDepositAddress() {
                if (!selectedNetwork || !selectedToken) {
                    return;
                }
                const address = addresses[selectedNetwork][selectedToken];
                if (address) {
                    depositAddressInput.value = address;
                    depositAddressSection.style.display = 'block';
                    
                    // Generate QR Code using API
                    if (qrCodeImg) {
                        const encodedAddress = encodeURIComponent(address);
                        qrCodeImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodedAddress;
                        qrCodeImg.style.display = 'block';
                    }
                }
            }
            
            // Copy address button
            if (copyAddressBtn) {
                copyAddressBtn.addEventListener('click', function() {
                    const address = depositAddressInput.value;
                    if (address) {
                        navigator.clipboard.writeText(address).then(function() {
                            const originalIcon = copyAddressBtn.querySelector('i');
                            const originalClass = originalIcon.className;
                            originalIcon.className = 'fas fa-check';
                            copyAddressBtn.classList.remove('btn-modern');
                            copyAddressBtn.classList.add('btn-success');
                            setTimeout(function() {
                                originalIcon.className = originalClass;
                                copyAddressBtn.classList.remove('btn-success');
                                copyAddressBtn.classList.add('btn-modern');
                            }, 2000);
                        }).catch(function(err) {
                            console.error('Failed to copy:', err);
                            // Fallback for older browsers
                            depositAddressInput.select();
                            document.execCommand('copy');
                        });
                    }
                });
            }
            
            // Withdraw functionality
            const withdrawAmount = document.getElementById('withdrawAmount');
            const withdrawAddress = document.getElementById('withdrawAddress');
            const networkFeeAmount = document.getElementById('networkFeeAmount');
            const receiveAmount = document.getElementById('receiveAmount');
            const totalWithdraw = document.getElementById('totalWithdraw');
            const withdrawBtn = document.getElementById('withdrawBtn');
            const withdrawNetworkCards = document.querySelectorAll('#withdrawNetworkOptions .network-card-compact');
            
            let selectedWithdrawNetwork = 'bnb';
            const withdrawableBalance = <?php echo $balance; ?>; // From database - this is the withdrawable balance (balance field)
            const traderInvestments = <?php echo $withdrawableBalance; ?>; // Trader'lara yatırılan para
            const minWithdrawAmount = 10; // Minimum 10 USDT
            const networkFees = {
                eth: 5.00,
                bnb: 0.50,
                trx: 1.00
            };
            
            // Withdraw network card click handler
            withdrawNetworkCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove active class from all network cards
                    document.querySelectorAll('#withdrawNetworkOptions .network-card-compact').forEach(c => {
                        c.classList.remove('active');
                    });
                    // Add active class to selected network
                    this.classList.add('active');
                    selectedWithdrawNetwork = this.getAttribute('data-network-value');
                    updateWithdrawFee();
                });
            });
            
            // Set max amount for withdraw input
            const maxWithdrawBtn = document.getElementById('maxWithdrawBtn');
            
            if (maxWithdrawBtn) {
                maxWithdrawBtn.addEventListener('click', function() {
                    if (withdrawAmount) {
                        withdrawAmount.value = withdrawableBalance.toFixed(2);
                        calculateWithdraw();
                    }
                });
            }
            
            if (withdrawAmount) {
                withdrawAmount.setAttribute('max', withdrawableBalance);
                withdrawAmount.setAttribute('min', minWithdrawAmount);
                
                withdrawAmount.addEventListener('input', function() {
                    let value = parseFloat(this.value) || 0;
                    
                    // Check if exceeds withdrawable balance
                    if (value > withdrawableBalance) {
                        this.value = withdrawableBalance.toFixed(2);
                        value = withdrawableBalance;
                    }
                    
                    // Check if below minimum
                    if (value > 0 && value < minWithdrawAmount) {
                        this.setCustomValidity(`Minimum çekim miktarı ${minWithdrawAmount} USDT'dir.`);
                    } else {
                        this.setCustomValidity('');
                    }
                    
                    calculateWithdraw();
                });
            }
            
            function updateWithdrawFee() {
                const fee = networkFees[selectedWithdrawNetwork];
                networkFeeAmount.textContent = `~$${fee.toFixed(2)}`;
                calculateWithdraw();
            }
            
            function calculateWithdraw() {
                const amount = parseFloat(withdrawAmount.value) || 0;
                const fee = networkFees[selectedWithdrawNetwork];
                const receive = Math.max(0, amount - fee);
                
                receiveAmount.textContent = `${receive.toFixed(2)} USDT`;
                totalWithdraw.textContent = `${amount.toFixed(2)} USDT`;
            }
            
            if (withdrawAmount) {
                withdrawAmount.addEventListener('input', calculateWithdraw);
            }
            
            if (withdrawBtn) {
                withdrawBtn.addEventListener('click', function() {
                    const amount = parseFloat(withdrawAmount.value);
                    const address = withdrawAddress.value.trim();
                    const fee = networkFees[selectedWithdrawNetwork];
                    const receive = Math.max(0, amount - fee);
                    
                    if (!amount || amount <= 0) {
                        alert('Lütfen geçerli bir miktar giriniz.');
                        return;
                    }
                    
                    if (amount < minWithdrawAmount) {
                        alert(`Minimum çekim miktarı ${minWithdrawAmount} USDT'dir.`);
                        return;
                    }
                    
                    if (amount > withdrawableBalance) {
                        alert(`Çekilebilir bakiyeniz ${withdrawableBalance.toFixed(2)} USDT'dir.`);
                        return;
                    }
                    
                    if (!address) {
                        alert('Lütfen cüzdan adresini giriniz.');
                        return;
                    }
                    
                    // Create form and submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    
                    const fields = {
                        'withdraw_request': '1',
                        'network': selectedWithdrawNetwork,
                        'amount': amount,
                        'address': address,
                        'network_fee': fee,
                        'receive_amount': receive
                    };
                    
                    for (const [key, value] of Object.entries(fields)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        form.appendChild(input);
                    }
                    
                    document.body.appendChild(form);
                    form.submit();
                });
            }
            
            // Tab switching functionality
            const depositTab = document.getElementById('deposit');
            const withdrawTab = document.getElementById('withdraw');
            const switchToWithdraw = document.getElementById('switchToWithdraw');
            const switchToDeposit = document.getElementById('switchToDeposit');
            
            function showDepositTab() {
                depositTab.classList.add('show', 'active');
                withdrawTab.classList.remove('show', 'active');
                // Show switch to withdraw button, hide switch to deposit button
                if (switchToWithdraw) switchToWithdraw.style.display = 'block';
                if (switchToDeposit) switchToDeposit.style.display = 'none';
            }
            
            function showWithdrawTab() {
                withdrawTab.classList.add('show', 'active');
                depositTab.classList.remove('show', 'active');
                // Show switch to deposit button, hide switch to withdraw button
                if (switchToDeposit) switchToDeposit.style.display = 'block';
                if (switchToWithdraw) switchToWithdraw.style.display = 'none';
            }
            
            if (switchToWithdraw) {
                switchToWithdraw.addEventListener('click', function(e) {
                    e.preventDefault();
                    showWithdrawTab();
                });
            }
            
            if (switchToDeposit) {
                switchToDeposit.addEventListener('click', function(e) {
                    e.preventDefault();
                    showDepositTab();
                });
            }
            
            // Initialize
            updateWithdrawFee();
            calculateWithdraw();
        });
    </script>

