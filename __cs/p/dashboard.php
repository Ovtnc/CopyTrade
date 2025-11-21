    <title>Dashboard - CopyStar</title>

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
?>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Navigation -->
    <?php include(V_PATH."topnav.php"); ?>

    <!-- Dashboard Section -->
    <section class="dashboard-section">
        <div class="container">
            <?php
            // Email sent message
            if (isset($_GET['email_sent']) && $_GET['email_sent'] == '1') {
            ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Doğrulama Email'i Gönderildi</strong>
                        <p class="mb-0 mt-1">Email adresinize doğrulama linki gönderildi. Lütfen email kutunuzu kontrol edin.</p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <?php
            } elseif (isset($_GET['email_sent']) && $_GET['email_sent'] == '0') {
            ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Email Gönderilemedi</strong>
                        <p class="mb-0 mt-1">Doğrulama email'i gönderilemedi. Lütfen daha sonra tekrar deneyin veya hesap ayarlarından email doğrulama linkini tekrar gönderebilirsiniz.</p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <?php
            }
            
            // Email verification warning
            if (isset($currentUser['email_verified']) && $currentUser['email_verified'] == 0) {
            ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                            <div class="flex-grow-1">
                                <strong data-key="emailNotVerifiedTitle">E-posta Adresiniz Doğrulanmadı</strong>
                                <p class="mb-0 mt-1" data-key="emailNotVerifiedMessage">Henüz e-posta adresinizi doğrulamadınız. Lütfen e-postanızı kontrol edin ve doğrulayın.</p>
                                <div class="mt-2">
                                    <form method="POST" action="<?= WEB_URL; ?>/verify-email?resend=1" style="display: inline;">
                                        <input type="hidden" name="email" value="<?= htmlspecialchars($currentUser['email']) ?>">
                                        <button type="submit" name="resend_verification" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-paper-plane me-1"></i>Doğrulama Email'i Tekrar Gönder
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <?php
            }
            
            // Format balance
            $balance = isset($currentUser['balance']) && $currentUser['balance'] !== null ? floatval($currentUser['balance']) : 0.00;
            $formattedBalance = number_format($balance, 2, '.', ',');
            
            // Get user display name
            $userDisplayName = 'Kullanıcı';
            if (isset($currentUser['name_surname']) && !empty(trim($currentUser['name_surname']))) {
                $userDisplayName = trim($currentUser['name_surname']);
            } elseif (isset($currentUser['email']) && !empty($currentUser['email'])) {
                $emailParts = explode('@', $currentUser['email']);
                $userDisplayName = $emailParts[0];
            }
            ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h2 fw-bold mb-0"><span data-key="welcomeMessage">Hoşgeldin</span> <span id="userName"><?php echo htmlspecialchars($userDisplayName); ?></span></h1>
                </div>
            </div>

            <!-- Total Balance Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="balance-card glass-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-2" data-key="totalBalance">Total Balance</p>
                                <h2 class="h1 fw-bold mb-0">$<?php echo htmlspecialchars($formattedBalance); ?></h2>
                            </div>
                            <div class="balance-icon">
                                <i class="fas fa-wallet"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            // Get followed traders with trader details and user info
            $followedTraders = [];
            $stmt = $conn->prepare("
                SELECT 
                    ft.id,
                    ft.first_balance,
                    ft.current_balance,
                    ft.created_at,
                    t.id as trader_id,
                    t.name as trader_name,
                    t.user_id,
                    t.avatar_url as trader_avatar,
                    t.username as trader_username,
                    u.email as user_email,
                    u.name_surname as user_name_surname
                FROM followed_traders ft
                INNER JOIN traders t ON ft.trader_id = t.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE ft.user_id = ? AND ft.status = 'active'
                ORDER BY ft.created_at DESC
            ");
            $stmt->bind_param("i", $currentUser['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Calculate total first_balance and current_balance for overall return
            $totalFirstBalance = 0;
            $totalCurrentBalance = 0;
            
            while ($row = $result->fetch_assoc()) {
                $firstBalance = floatval($row['first_balance']);
                $currentBalance = floatval($row['current_balance']);
                
                // Calculate return percentage for this trader
                $returnPercent = $firstBalance > 0 ? (($currentBalance - $firstBalance) / $firstBalance) * 100 : 0;
                $row['return_percent'] = $returnPercent;
                
                // Determine display name: if user has name_surname, use it, otherwise use email, otherwise use trader name
                if (!empty($row['user_name_surname'])) {
                    $row['trader_display_name'] = $row['user_name_surname'];
                } elseif (!empty($row['user_email'])) {
                    $row['trader_display_name'] = $row['user_email'];
                } else {
                    $row['trader_display_name'] = $row['trader_name'];
                }
                
                // Add to totals
                $totalFirstBalance += $firstBalance;
                $totalCurrentBalance += $currentBalance;
                
                $followedTraders[] = $row;
            }
            $stmt->close();
            
            // Calculate overall return percentage
            $overallReturnPercent = $totalFirstBalance > 0 ? (($totalCurrentBalance - $totalFirstBalance) / $totalFirstBalance) * 100 : 0;
            
            // Get user's trade history (last 10 trades - both open and closed) with user info
            $userTrades = [];
            $tradesStmt = $conn->prepare("
                SELECT 
                    ut.id,
                    ut.pair,
                    ut.type,
                    ut.leverage,
                    ut.entry_price,
                    ut.exit_price,
                    ut.profit,
                    ut.profit_percent,
                    ut.entry_time,
                    ut.exit_time,
                    ut.status,
                    t.name as trader_name,
                    t.user_id,
                    u.email as user_email,
                    u.name_surname as user_name_surname
                FROM user_trades ut
                LEFT JOIN traders t ON ut.trader_id = t.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE ut.user_id = ?
                ORDER BY 
                    CASE WHEN ut.status = 'closed' AND ut.exit_time IS NOT NULL THEN 0 ELSE 1 END,
                    ut.exit_time DESC,
                    ut.entry_time DESC
                LIMIT 10
            ");
            $tradesStmt->bind_param("i", $currentUser['id']);
            $tradesStmt->execute();
            $tradesResult = $tradesStmt->get_result();
            while ($row = $tradesResult->fetch_assoc()) {
                // Determine display name: if user has name_surname, use it, otherwise use email, otherwise use trader name
                if (!empty($row['user_name_surname'])) {
                    $row['trader_display_name'] = $row['user_name_surname'];
                } elseif (!empty($row['user_email'])) {
                    $row['trader_display_name'] = $row['user_email'];
                } else {
                    $row['trader_display_name'] = $row['trader_name'] ?? 'Bilinmeyen Trader';
                }
                $userTrades[] = $row;
            }
            $tradesStmt->close();
            ?>
            <div class="row g-4">
                <!-- Followed Traders -->
                <div class="col-lg-4">
                    <div class="dashboard-card glass-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="h5 fw-bold mb-0" data-key="followedTraders">Followed Traders</h3>
                            <a href="<?php echo WEB_URL; ?>/traders" class="text-decoration-none small" data-key="viewAll">View All</a>
                        </div>
                        <div class="followed-traders-list-scroll">
                            <?php if (empty($followedTraders)): ?>
                            <div class="empty-state text-center py-5">
                                <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-3" data-key="noTradersYet">You are not following any traders</p>
                                <a href="<?php echo WEB_URL; ?>/traders" class="btn btn-primary btn-sm" data-key="startTrading">Start Trading</a>
                            </div>
                            <?php else: ?>
                            <?php foreach ($followedTraders as $followed): 
                                $traderAvatar = !empty($followed['trader_avatar']) ? $followed['trader_avatar'] : 'vendor/trader.png';
                                $returnPercent = floatval($followed['return_percent']);
                                $returnClass = $returnPercent >= 0 ? 'text-success' : 'text-danger';
                                $returnSign = $returnPercent >= 0 ? '+' : '';
                            ?>
                            <div class="trader-item mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="trader-avatar-small me-3">
                                        <img src="<?php echo htmlspecialchars($traderAvatar); ?>" alt="<?php echo htmlspecialchars($followed['trader_name']); ?>" class="trader-avatar-img">
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($followed['trader_display_name'] ?? $followed['trader_name']); ?></h6>
                                        <small class="<?php echo $returnClass; ?>"><?php echo $returnSign; ?><?php echo number_format($returnPercent, 2); ?>% Return</small>
                                    </div>
                                    <span class="badge bg-success" data-key="active">Aktif</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Trade History -->
                <div class="col-lg-8">
                    <div class="dashboard-card glass-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="h5 fw-bold mb-0" data-key="tradeHistory">Trade History</h3>
                            <a href="#" class="text-decoration-none small" data-key="viewAll">View All</a>
                        </div>
                        <div class="table-responsive trade-history-scroll">
                            <table class="table table-hover trade-table">
                                <thead>
                                    <tr>
                                        <th>Pair/Type</th>
                                        <th data-key="leverage">Leverage</th>
                                        <th data-key="entryPrice">Entry Price</th>
                                        <th data-key="exitPrice">Exit Price</th>
                                        <th data-key="profit">Profit</th>
                                        <th data-key="trader">Trader</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($userTrades)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                            <p class="text-muted mb-0" data-key="noTradesYet">No trade history yet</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($userTrades as $trade): 
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
                                            <?php if ($trade['status'] == 'closed' && !empty($trade['exit_price'])): ?>
                                                <div class="fw-medium">$<?php echo number_format(floatval($trade['exit_price']), 2, '.', ','); ?></div>
                                                <div class="text-muted small"><?php echo date('d.m.Y H:i', strtotime($trade['exit_time'])); ?></div>
                                            <?php else: ?>
                                                <div class="text-muted">-</div>
                                                <div class="text-muted small"><span class="badge bg-warning">Açık</span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php if ($trade['status'] == 'closed' && !empty($trade['exit_price'])): ?>
                                                <div>
                                                    <div class="fw-medium <?php echo $profitClass; ?>"><?php echo $profitSign; ?>$<?php echo number_format(abs($profit), 2, '.', ','); ?></div>
                                                    <div class="<?php echo $profitClass; ?> small"><?php echo $profitSign; ?><?php echo number_format($profitPercent, 2); ?>%</div>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-muted">-</div>
                                                <div class="text-muted small">Hesaplanıyor...</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="fw-medium"><?php echo htmlspecialchars($trade['trader_display_name'] ?? $trade['trader_name']); ?></div>
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

            <!-- ROI Chart -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="dashboard-card glass-card roi-card-enhanced">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h3 class="h5 fw-bold mb-1" data-key="last30DaysROI">Last 30 Days ROI</h3>
                                <p class="text-muted small mb-0" data-key="performanceOverview">Performance Overview</p>
                            </div>
                            <div class="roi-stats">
                                <div class="roi-stat-item">
                                    <span class="roi-stat-label" data-key="currentROI">Current ROI</span>
                                    <span class="roi-stat-value <?php echo $overallReturnPercent >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $overallReturnPercent >= 0 ? '+' : ''; ?><?php echo number_format($overallReturnPercent, 2); ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php if (empty($followedTraders)): ?>
                        <div class="chart-container-empty text-center py-5">
                            <i class="fas fa-chart-area fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0" data-key="noDataYet">Not enough data yet</p>
                        </div>
                        <?php else: ?>
                        <div class="chart-container chart-container-full">
                            <canvas id="roiChart"></canvas>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Initialize ROI Chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('roiChart');
            if (ctx && <?php echo !empty($followedTraders) ? 'true' : 'false'; ?>) {
                // Get theme colors
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                const textColor = isDark ? '#ffffff' : '#212529';
                const gridColor = isDark ? '#404040' : '#dee2e6';
                
                const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
                gradient.addColorStop(0, 'rgba(42, 79, 121, 0.3)');
                gradient.addColorStop(0.5, 'rgba(42, 79, 121, 0.15)');
                gradient.addColorStop(1, 'rgba(42, 79, 121, 0.05)');
                
                // Calculate ROI data for last 30 days (simplified - in real app, calculate from actual trades)
                const currentROI = <?php echo number_format($overallReturnPercent, 2); ?>;
                const roiData = [];
                for (let i = 0; i < 30; i++) {
                    // Linear progression from 0 to current ROI
                    roiData.push((currentROI / 30) * (i + 1));
                }
                
                const roiChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: Array.from({length: 30}, (_, i) => {
                            const date = new Date();
                            date.setDate(date.getDate() - (29 - i));
                            return date.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit' });
                        }),
                        datasets: [{
                            label: 'ROI %',
                            data: roiData,
                            borderColor: '#2A4F79',
                            backgroundColor: gradient,
                            borderWidth: 3,
                            fill: true,
                            tension: 0.5,
                            pointRadius: 0,
                            pointHoverRadius: 8,
                            pointHoverBackgroundColor: '#2A4F79',
                            pointHoverBorderColor: '#ffffff',
                            pointHoverBorderWidth: 2,
                            pointBackgroundColor: '#2A4F79',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                top: 10,
                                bottom: 10,
                                left: 10,
                                right: 10
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: isDark ? 'rgba(26, 35, 50, 0.98)' : 'rgba(255, 255, 255, 0.98)',
                                titleColor: textColor,
                                bodyColor: textColor,
                                borderColor: '#2A4F79',
                                borderWidth: 2,
                                padding: 12,
                                displayColors: false,
                                titleFont: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) {
                                        return 'ROI: ' + context.parsed.y.toFixed(2) + '%';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    color: textColor,
                                    maxRotation: 45,
                                    minRotation: 45,
                                    font: {
                                        size: 11
                                    }
                                },
                                grid: {
                                    color: gridColor,
                                    drawBorder: false,
                                    lineWidth: 1
                                },
                                border: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: textColor,
                                    font: {
                                        size: 12
                                    },
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                },
                                grid: {
                                    color: gridColor,
                                    drawBorder: false,
                                    lineWidth: 1
                                },
                                border: {
                                    display: false
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
                
                // Update chart on theme change
                const themeToggle = document.getElementById('themeToggle');
                if (themeToggle) {
                    themeToggle.addEventListener('click', function() {
                        setTimeout(() => {
                            roiChart.update();
                        }, 300);
                    });
                }
            }
        });
    </script>
