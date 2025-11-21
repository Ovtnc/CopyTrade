
    <title>Traders - CopyStar</title>
    
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

// Get all active traders from database with user info
$traders = [];
$stmt = $conn->prepare("SELECT t.id, t.user_id, t.username, t.name, t.description, t.avatar_url, t.roi_30d, t.followers, t.aum, t.mdd_30d, u.email as user_email, u.name_surname as user_name_surname FROM traders t LEFT JOIN users u ON t.user_id = u.id WHERE t.status = 'active' ORDER BY t.id ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Determine display name: if user has name_surname, use it, otherwise use email
    if (!empty($row['user_name_surname'])) {
        $row['display_name'] = $row['user_name_surname'];
    } elseif (!empty($row['user_email'])) {
        $row['display_name'] = $row['user_email'];
    } else {
        $row['display_name'] = $row['name'];
    }
    $traders[] = $row;
}
$stmt->close();

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

// Handle follow trader request
$followSuccess = false;
$followError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['follow_trader'])) {
    $traderId = isset($_POST['trader_id']) ? intval($_POST['trader_id']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    
    // Validation
    if ($traderId <= 0) {
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
            $checkStmt->bind_param("ii", $currentUser['id'], $traderId);
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
                        $historyStmt->bind_param("iiid", $existingFollow['id'], $currentUser['id'], $traderId, $amount);
                        $historyStmt->execute();
                        $historyStmt->close();
                    } else {
                        // Insert new follow record
                        $insertStmt = $conn->prepare("INSERT INTO followed_traders (user_id, trader_id, first_balance, current_balance, status) VALUES (?, ?, ?, ?, 'active')");
                        $insertStmt->bind_param("iidd", $currentUser['id'], $traderId, $amount, $amount);
                        $insertStmt->execute();
                        $followedTraderId = $conn->insert_id;
                        $insertStmt->close();
                        
                        // Record initial investment in investment_history
                        $historyStmt = $conn->prepare("INSERT INTO investment_history (followed_trader_id, user_id, trader_id, amount, type) VALUES (?, ?, ?, ?, 'initial')");
                        $historyStmt->bind_param("iiid", $followedTraderId, $currentUser['id'], $traderId, $amount);
                        $historyStmt->execute();
                        $historyStmt->close();
                        
                        // Update trader followers count (only for new follows)
                        $updateFollowersStmt = $conn->prepare("UPDATE traders SET followers = followers + 1 WHERE id = ?");
                        $updateFollowersStmt->bind_param("i", $traderId);
                        $updateFollowersStmt->execute();
                        $updateFollowersStmt->close();
                    }
                    
                    $conn->commit();
                    $followSuccess = true;
                    // Refresh user data
                    $currentUser = getCurrentUser();
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

// Get available balance (balance - total deposited amount)
$availableBalance = isset($currentUser['balance']) && $currentUser['balance'] !== null ? floatval($currentUser['balance']) : 0.00;
$formattedAvailableBalance = number_format($availableBalance, 2, '.', ',');

// Get existing follows for each trader to show in modal
$existingFollows = [];
$followStmt = $conn->prepare("SELECT trader_id, first_balance, current_balance FROM followed_traders WHERE user_id = ? AND status = 'active'");
$followStmt->bind_param("i", $currentUser['id']);
$followStmt->execute();
$followResult = $followStmt->get_result();
while ($row = $followResult->fetch_assoc()) {
    $existingFollows[$row['trader_id']] = $row;
}
$followStmt->close();
?>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Navigation -->
    <?php include(V_PATH."topnav.php"); ?>

    <!-- Traders Section -->
    <section class="top-traders-section" style="padding-top: 20px;">
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

            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-4 mb-4">
                        <div>
                            <h1 class="h2 fw-bold mb-2" data-key="copyTradingTitle">Copy Trading</h1>
                            <p class="text-muted mb-0" data-key="copyTradingSubtitle">Follow top traders and copy their strategies automatically</p>
                        </div>
                        <button class="btn btn-primary btn-modern" data-key="applyLeaderTrader" data-bs-toggle="modal" data-bs-target="#applyLeaderTraderModal">
                            <i class="fas fa-crown me-2"></i><span data-key="applyLeaderTrader">Apply to be a Leader Trader</span>
                        </button>
                    </div>
                    <!-- Modern Search Section -->
                    <div class="trader-search-container glass-card mb-4">
                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <div class="search-box-wrapper">
                                    <div class="search-icon-wrapper">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <input type="text" 
                                           class="form-control trader-search-input" 
                                           id="traderSearchInput" 
                                           placeholder="Trader ara... (isim, açıklama)" 
                                           autocomplete="off">
                                    <button type="button" class="search-clear-btn" id="searchClearBtn" style="display: none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="search-filter-wrapper">
                                    <label class="search-filter-label" for="traderSortSelect">
                                        <i class="fas fa-sort me-2"></i>
                                        <span data-key="sortBy">Sırala</span>
                                    </label>
                                    <select class="form-select trader-sort-select-modern" id="traderSortSelect">
                                        <option value="default" data-key="defaultSort">Varsayılan</option>
                                        <option value="roi-desc" data-key="roiHighToLow">ROI (Yüksek → Düşük)</option>
                                        <option value="roi-asc" data-key="roiLowToHigh">ROI (Düşük → Yüksek)</option>
                                        <option value="followers-desc" data-key="followersHighToLow">Takipçi (Çok → Az)</option>
                                        <option value="followers-asc" data-key="followersLowToHigh">Takipçi (Az → Çok)</option>
                                        <option value="aum-desc" data-key="aumHighToLow">AUM (Yüksek → Düşük)</option>
                                        <option value="aum-asc" data-key="aumLowToHigh">AUM (Düşük → Yüksek)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="search-results-info mt-3" id="searchResultsInfo" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="searchResultsText"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4" id="tradersContainer">
                <?php if (empty($traders)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0" data-key="noTradersAvailable">Henüz trader bulunmamaktadır.</p>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($traders as $trader): 
                    $roi = floatval($trader['roi_30d']);
                    $followers = intval($trader['followers']);
                    $aum = floatval($trader['aum']);
                    $mdd = floatval($trader['mdd_30d']);
                    $formattedAUM = formatAUM($aum);
                    $avatarUrl = !empty($trader['avatar_url']) ? $trader['avatar_url'] : 'vendor/trader.png';
                ?>
                <div class="col-12 col-lg-6 trader-item" data-roi="<?php echo htmlspecialchars($roi); ?>" data-followers="<?php echo htmlspecialchars($followers); ?>" data-aum="<?php echo htmlspecialchars($aum); ?>">
                    <div class="trader-card glass-card trader-card-modern">
                        <div class="trader-header-modern mb-4">
                            <div class="d-flex align-items-start gap-3">
                                <div class="trader-avatar-modern flex-shrink-0">
                                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="<?php echo htmlspecialchars($trader['name']); ?>" class="trader-avatar-img-modern">
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <h3 class="h5 fw-bold mb-1"><?php echo htmlspecialchars($trader['display_name'] ?? $trader['name']); ?></h3>
                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($trader['description'] ?? ''); ?></p>
                                </div>
                                <div class="d-flex gap-2 flex-shrink-0">
                                    <a href="<?php echo WEB_URL; ?>/trader/<?php echo intval($trader['id']); ?>" class="btn btn-sm btn-outline-modern" data-key="details">
                                        <i class="fas fa-eye me-2"></i><span data-key="details">Detaylar</span>
                                    </a>
                                    <?php 
                                    $hasExistingFollow = isset($existingFollows[$trader['id']]);
                                    $buttonKey = $hasExistingFollow ? 'addBalance' : 'copy';
                                    $buttonText = $hasExistingFollow ? 'Ekle' : 'Kopyala';
                                    ?>
                                    <button class="btn btn-sm btn-modern" onclick="openFollowModal(<?php echo intval($trader['id']); ?>, '<?php echo htmlspecialchars(addslashes($trader['name'])); ?>', <?php echo $hasExistingFollow ? 'true' : 'false'; ?>, <?php echo $hasExistingFollow ? floatval($existingFollows[$trader['id']]['first_balance']) : 0; ?>)" data-key="<?php echo $buttonKey; ?>">
                                        <i class="fas fa-<?php echo $hasExistingFollow ? 'plus' : 'copy'; ?> me-2"></i><span data-key="<?php echo $buttonKey; ?>"><?php echo htmlspecialchars($buttonText); ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-6 col-md-3 text-center">
                                <?php 
                                $roiClass = $roi >= 0 ? 'text-success' : 'text-danger';
                                $roiSign = $roi >= 0 ? '+' : '';
                                ?>
                                <div class="h4 fw-bold <?php echo $roiClass; ?> mb-1"><?php echo $roiSign; ?><?php echo number_format($roi, 1); ?>%</div>
                                <div class="text-muted small" data-key="roi30D">30D ROI</div>
                            </div>
                            <div class="col-6 col-md-3 text-center">
                                <div class="h4 fw-bold mb-1"><?php echo number_format($followers); ?></div>
                                <div class="text-muted small" data-key="followers">Followers</div>
                            </div>
                            <div class="col-6 col-md-3 text-center">
                                <div class="h4 fw-bold mb-1"><?php echo htmlspecialchars($formattedAUM); ?></div>
                                <div class="text-muted small" data-key="aum">AUM</div>
                            </div>
                            <div class="col-6 col-md-3 text-center">
                                <div class="h4 fw-bold mb-1"><?php echo number_format($mdd, 2); ?>%</div>
                                <div class="text-muted small" data-key="mdd30D">30D MDD</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
                                </div>
                            </div>
    </section>

    <!-- Follow Trader Modal -->
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

    <!-- Apply Leader Trader Modal -->
    <div class="modal fade" id="applyLeaderTraderModal" tabindex="-1" aria-labelledby="applyLeaderTraderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card" style="border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title fw-bold" id="applyLeaderTraderModalLabel" data-key="applyLeaderTrader">Lider Trader Olmak İçin Başvur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h6 class="fw-bold mb-2" data-key="applicationNotEligible">Hesabınız Başvuru İçin Gerekli Koşulları Karşılamıyor</h6>
                        <p class="text-muted mb-0" data-key="applicationNotEligibleMessage">Lider trader olmak için gerekli koşulları karşılamıyorsunuz. Lütfen daha sonra tekrar deneyin.</p>
                    </div>
                    <button type="button" class="btn btn-modern" data-bs-dismiss="modal" data-key="close">
                        <i class="fas fa-times me-2"></i><span data-key="close">Kapat</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Open follow modal function
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
                // Get current language
                let currentLang = 'tr';
                const langAttr = document.documentElement.getAttribute('lang');
                if (langAttr) {
                    currentLang = langAttr;
                } else {
                    const storedLang = localStorage.getItem('selectedLanguage');
                    if (storedLang) {
                        currentLang = storedLang;
                    }
                }
                
                // Get translation
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
        
        // Amount validation
        document.addEventListener('DOMContentLoaded', function() {
            const amountInput = document.getElementById('followAmount');
            const availableBalance = parseFloat("<?php echo htmlspecialchars($availableBalance); ?>");
            
            if (amountInput) {
                amountInput.addEventListener('input', function() {
                    let value = parseFloat(this.value) || 0;
                    
                    if (value > availableBalance) {
                        this.value = availableBalance.toFixed(2);
                        value = availableBalance;
                    }
                    
                    if (value < 0.01) {
                        this.setCustomValidity('Minimum yatırım miktarı $0.01\'dir.');
                    } else {
                        this.setCustomValidity('');
                    }
                });
                
                // Form submission validation
                const form = document.getElementById('followTraderForm');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        const amount = parseFloat(amountInput.value) || 0;
                        
                        if (amount <= 0) {
                            e.preventDefault();
                            alert('Lütfen geçerli bir miktar giriniz.');
                            return false;
                        }
                        
                        if (amount > availableBalance) {
                            e.preventDefault();
                            // Get translation for insufficient balance
                            let errorMsg = 'Yetersiz bakiye. Kullanılabilir bakiye: $' + availableBalance.toFixed(2);
                            if (typeof translations !== 'undefined') {
                                let currentLang = document.documentElement.getAttribute('lang') || localStorage.getItem('selectedLanguage') || 'tr';
                                if (translations[currentLang] && translations[currentLang].insufficientBalance) {
                                    errorMsg = translations[currentLang].insufficientBalance.replace('{balance}', '$' + availableBalance.toFixed(2));
                                }
                            }
                            alert(errorMsg);
                            return false;
                        }
                    });
                }
            }
        });
        // Enhanced Trader Search and Sort Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('traderSearchInput');
            const tradersContainer = document.getElementById('tradersContainer');
            const sortSelect = document.getElementById('traderSortSelect');
            const searchClearBtn = document.getElementById('searchClearBtn');
            const searchResultsInfo = document.getElementById('searchResultsInfo');
            const searchResultsText = document.getElementById('searchResultsText');
            
            let allTraderItems = [];
            let visibleTraderItems = [];
            
            // Initialize: collect all trader items
            if (tradersContainer) {
                allTraderItems = Array.from(tradersContainer.querySelectorAll('.trader-item'));
                visibleTraderItems = [...allTraderItems];
            }
            
            // Update search results info
            function updateSearchResultsInfo() {
                const visibleCount = visibleTraderItems.filter(item => item.style.display !== 'none').length;
                const totalCount = allTraderItems.length;
                
                if (searchInput && searchInput.value.trim() !== '') {
                    searchResultsInfo.style.display = 'block';
                    searchResultsText.textContent = `${visibleCount} trader bulundu (toplam ${totalCount})`;
                } else {
                    searchResultsInfo.style.display = 'none';
                }
            }
            
            // Show/hide clear button
            function toggleClearButton() {
                if (searchInput && searchClearBtn) {
                    if (searchInput.value.trim() !== '') {
                        searchClearBtn.style.display = 'flex';
                    } else {
                        searchClearBtn.style.display = 'none';
                    }
                }
            }
            
            // Search functionality
            function performSearch() {
                if (!searchInput || !tradersContainer) return;
                
                const searchTerm = searchInput.value.toLowerCase().trim();
                visibleTraderItems = [];
                
                allTraderItems.forEach(item => {
                    // Get trader name and description for search
                    const traderName = item.querySelector('h3')?.textContent.toLowerCase() || '';
                    const traderDescription = item.querySelector('p')?.textContent.toLowerCase() || '';
                    
                    // Check if search term matches
                    if (searchTerm === '' || traderName.includes(searchTerm) || traderDescription.includes(searchTerm)) {
                        item.style.display = '';
                        visibleTraderItems.push(item);
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                // Apply current sort after search
                performSort();
                updateSearchResultsInfo();
            }
            
            // Sort functionality
            function performSort() {
                if (!sortSelect || !tradersContainer) return;
                
                const sortValue = sortSelect.value;
                const itemsToSort = visibleTraderItems.length > 0 ? visibleTraderItems : allTraderItems;
                
                // Create array with items and their sort values
                const itemsWithData = itemsToSort.map(item => {
                    const roi = parseFloat(item.getAttribute('data-roi')) || 0;
                    const followers = parseFloat(item.getAttribute('data-followers')) || 0;
                    const aum = parseFloat(item.getAttribute('data-aum')) || 0;
                    return { item, roi, followers, aum };
                });
                
                // Sort based on selected option
                itemsWithData.sort((a, b) => {
                    switch(sortValue) {
                        case 'roi-desc':
                            return b.roi - a.roi;
                        case 'roi-asc':
                            return a.roi - b.roi;
                        case 'followers-desc':
                            return b.followers - a.followers;
                        case 'followers-asc':
                            return a.followers - b.followers;
                        case 'aum-desc':
                            return b.aum - a.aum;
                        case 'aum-asc':
                            return a.aum - b.aum;
                        default:
                            return 0; // Keep original order
                    }
                });
                
                // Reorder DOM elements
                itemsWithData.forEach(({ item }) => {
                    tradersContainer.appendChild(item);
                });
            }
            
            // Event listeners
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    performSearch();
                    toggleClearButton();
                });
                
                searchInput.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                searchInput.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            }
            
            if (searchClearBtn) {
                searchClearBtn.addEventListener('click', function() {
                    if (searchInput) {
                        searchInput.value = '';
                        searchInput.focus();
                        performSearch();
                        toggleClearButton();
                    }
                });
            }
            
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    performSort();
                });
            }
            
            // Initialize
            toggleClearButton();
        });
    </script>

