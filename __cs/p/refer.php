
    <title>Refer - CopyStar</title>

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

// Get total referrals count
$totalReferrals = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE referred_by = ?");
$stmt->bind_param("i", $currentUser['id']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $totalReferrals = $row['count'];
}
$stmt->close();

// Get referral code and link
$referralCode = isset($currentUser['referral_code']) ? $currentUser['referral_code'] : '';
$referralLink = WEB_URL . '/?ref=' . urlencode($referralCode);
?>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Navigation -->
    <?php include(V_PATH."topnav.php"); ?>

    <!-- Refer Section -->
    <section class="dashboard-section" style="padding-top: 20px;">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h2 fw-bold mb-0" data-key="referralProgram">Referans Programı</h1>
                </div>
            </div>

            <!-- Commission Info Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="glass-card p-4">
                        <div class="d-flex align-items-start gap-3">
                            <div class="flex-shrink-0">
                                <div class="balance-icon text-primary">
                                    <i class="fas fa-percent fa-2x"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="h5 fw-bold mb-2" data-key="commissionInfo">Komisyon Bilgisi</h3>
                                <p class="text-muted mb-0" data-key="commissionDescription">
                                    You receive 40% commission from the trading fees your referrals pay to the platform when opening/closing positions.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Total Referrals Card -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h6 fw-bold mb-0" data-key="totalReferrals">Toplam Referans</h3>
                            <i class="fas fa-users text-primary"></i>
                        </div>
                        <div class="mb-2">
                            <h2 class="h3 fw-bold mb-0" id="totalReferralsCount"><?php echo htmlspecialchars($totalReferrals); ?></h2>
                        </div>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-arrow-up text-success me-1"></i>
                            <span data-key="thisMonth">Bu Ay:</span> <span id="monthlyReferrals">0</span>
                        </p>
                    </div>
                </div>

                <!-- Total Earnings Card -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h6 fw-bold mb-0" data-key="totalEarnings">Toplam Kazanç</h3>
                            <i class="fas fa-dollar-sign text-success"></i>
                        </div>
                        <div class="mb-2">
                            <h2 class="h3 fw-bold mb-0" id="totalEarningsAmount">$0.00</h2>
                        </div>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-arrow-up text-success me-1"></i>
                            <span data-key="thisMonth">Bu Ay:</span> <span id="monthlyEarnings">$0.00</span>
                        </p>
                    </div>
                </div>

                <!-- Active Referrals Card -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h6 fw-bold mb-0" data-key="activeReferrals">Aktif Referanslar</h3>
                            <i class="fas fa-user-check text-info"></i>
                        </div>
                        <div class="mb-2">
                            <h2 class="h3 fw-bold mb-0" id="activeReferralsCount">0</h2>
                        </div>
                        <p class="text-muted small mb-0">
                            <span data-key="trading">İşlem Yapan</span>
                        </p>
                    </div>
                </div>

                <!-- Pending Earnings Card -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="glass-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h6 fw-bold mb-0" data-key="pendingEarnings">Bekleyen Kazanç</h3>
                            <i class="fas fa-clock text-warning"></i>
                        </div>
                        <div class="mb-2">
                            <h2 class="h3 fw-bold mb-0" id="pendingEarningsAmount">$0.00</h2>
                        </div>
                        <p class="text-muted small mb-0">
                            <span data-key="nextPayout">Sonraki Ödeme:</span> <span id="nextPayoutDate">-</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Referral Link Card -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="glass-card p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="h5 fw-bold mb-0" data-key="referralLink">Referans Linki</h3>
                            <i class="fas fa-link text-primary"></i>
                        </div>
                        <div class="row g-4">
                            <div class="col-12 col-md-8">
                                <div class="mb-3">
                                    <label class="form-label small text-muted mb-2" data-key="yourReferralLink">Referans Linkiniz</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control form-control-modern font-monospace" id="referralLinkInput" value="<?php echo htmlspecialchars($referralLink); ?>" readonly>
                                        <button class="btn btn-modern" type="button" onclick="copyReferralLink()" data-key="copyLink">
                                            <i class="fas fa-copy me-1"></i><span data-key="copyLink">Kopyala</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="alert alert-info small mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <span data-key="referralLinkInfo">Bu linki arkadaşlarınızla paylaşın. Linke tıklayıp kayıt olan herkes sizin referansınız olacaktır.</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="text-center">
                                    <label class="form-label small text-muted mb-2" data-key="qrCode">QR Kod</label>
                                    <div class="qr-code-container mb-2" id="referralQRCode">
                                        <img id="referralQRCodeImg" src="" alt="QR Code" class="img-fluid" style="max-width: 200px; height: auto;">
                                    </div>
                                    <small class="text-muted" data-key="scanToShare">Paylaşmak için tara</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Share Buttons -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="glass-card p-4">
                        <h3 class="h5 fw-bold mb-3" data-key="shareReferralLink">Referans Linkini Paylaş</h3>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-outline-modern" onclick="shareOnWhatsApp()">
                                <i class="fab fa-whatsapp me-2"></i><span data-key="whatsapp">WhatsApp</span>
                            </button>
                            <button class="btn btn-outline-modern" onclick="shareOnTelegram()">
                                <i class="fab fa-telegram me-2"></i><span data-key="telegram">Telegram</span>
                            </button>
                            <button class="btn btn-outline-modern" onclick="shareOnTwitter()">
                                <i class="fab fa-twitter me-2"></i><span data-key="twitter">Twitter</span>
                            </button>
                            <button class="btn btn-outline-modern" onclick="shareOnFacebook()">
                                <i class="fab fa-facebook me-2"></i><span data-key="facebook">Facebook</span>
                            </button>
                            <button class="btn btn-outline-modern" onclick="copyToClipboard('referralLinkInput')">
                                <i class="fas fa-link me-2"></i><span data-key="copyLink">Linki Kopyala</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <script>
        // Copy referral link function
        function copyReferralLink() {
            const input = document.getElementById('referralLinkInput');
            input.select();
            input.setSelectionRange(0, 99999);
            
            navigator.clipboard.writeText(input.value).then(function() {
                const btn = event.target.closest('button');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check me-1"></i><span data-key="copied">Kopyalandı</span>';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-modern');
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-modern');
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy:', err);
                document.execCommand('copy');
            });
        }

        // Copy to clipboard function (generic)
        function copyToClipboard(inputId) {
            const input = document.getElementById(inputId);
            input.select();
            input.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Show feedback
            const btn = event.target.closest('button');
            if (btn) {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i>';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-outline-modern');
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-modern');
                }, 2000);
            }
        }

        // Share functions
        function shareOnWhatsApp() {
            const link = document.getElementById('referralLinkInput').value;
            const text = encodeURIComponent('CopyStar referans programına katıl! ' + link);
            window.open('https://wa.me/?text=' + text, '_blank');
        }

        function shareOnTelegram() {
            const link = document.getElementById('referralLinkInput').value;
            const text = encodeURIComponent('CopyStar referans programına katıl! ' + link);
            window.open('https://t.me/share/url?url=' + encodeURIComponent(link) + '&text=' + text, '_blank');
        }

        function shareOnTwitter() {
            const link = document.getElementById('referralLinkInput').value;
            const text = encodeURIComponent('CopyStar referans programına katıl! ' + link);
            window.open('https://twitter.com/intent/tweet?text=' + text, '_blank');
        }

        function shareOnFacebook() {
            const link = document.getElementById('referralLinkInput').value;
            window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(link), '_blank');
        }

        // Generate QR Code for referral link using API
        function updateReferralQRCode() {
            const referralLink = document.getElementById('referralLinkInput').value;
            const qrImg = document.getElementById('referralQRCodeImg');
            
            if (qrImg && referralLink) {
                // Use QR Server API
                const encodedLink = encodeURIComponent(referralLink);
                qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodedLink;
            }
        }

        // Initialize QR code on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateReferralQRCode();
        });
    </script>

