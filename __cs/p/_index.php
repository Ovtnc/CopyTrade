<title>CopyStar - Advanced Copy Trading Platform</title>
</head>
<body>
<?php
// Handle referral code from URL
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $refCode = trim($_GET['ref']);
    
    // Validate referral code exists in database
    if (isset($conn)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->bind_param("s", $refCode);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Valid referral code - store in session and cookie
            $_SESSION['referral_code'] = $refCode;
            setcookie('referral_code', $refCode, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }
        $stmt->close();
    }
}
?>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= WEB_URL; ?>">
                <img src="vendor/logo.png" alt="CopyStar Logo" class="navbar-logo me-2">
                CopyStar
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item nav-item-mobile me-3">
                        <button class="btn btn-theme-toggle nav-link-mobile" id="themeToggle" title="Toggle Theme">
                            <i class="fas fa-moon me-2" id="themeIcon"></i><span class="nav-text-mobile" data-key="toggleTheme">Toggle Theme</span>
                        </button>
                    </li>
                    <li class="nav-item dropdown nav-item-mobile me-3">
                        <button class="btn btn-lang-dropdown nav-link-mobile dropdown-toggle" type="button" id="langDropdown" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                            <i class="fas fa-language me-2"></i><span class="nav-text-mobile" id="currentLangText">TR</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="langDropdown">
                            <li><a class="dropdown-item lang-option" href="#" data-lang="tr"><span class="flag-emoji me-2">🇹🇷</span>Türkçe</a></li>
                            <li><a class="dropdown-item lang-option" href="#" data-lang="en"><span class="flag-emoji me-2">🇬🇧</span>English</a></li>
                            <li><a class="dropdown-item lang-option" href="#" data-lang="es"><span class="flag-emoji me-2">🇪🇸</span>Español</a></li>
                            <li><a class="dropdown-item lang-option" href="#" data-lang="it"><span class="flag-emoji me-2">🇮🇹</span>Italiano</a></li>
                        </ul>
                    </li>
                    <li class="nav-item nav-item-mobile me-3">
                        <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/login" data-key="signIn">
                            <i class="fas fa-sign-in-alt me-2"></i><span class="nav-text-mobile" data-key="signIn">Sign In</span>
                        </a>
                    </li>
                    <li class="nav-item nav-item-mobile">
                        <a class="nav-link nav-link-mobile nav-link-register" href="<?= WEB_URL; ?>/register" data-key="signUp">
                            <i class="fas fa-user-plus me-2"></i><span class="nav-text-mobile" data-key="signUp">Sign Up</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content animate-fade-in">
                        <div class="badge-custom mb-3">
                            <i class="fas fa-rocket me-2"></i><span data-key="platformBadge">Advanced Platform</span>
                        </div>
                        <h1 class="display-3 fw-bold mb-4 hero-title" data-key="heroTitle">
                            Advanced Copy Trading Platform
                        </h1>
                        <h2 class="h3 mb-4 text-gradient" data-key="welcomeTitle">Welcome to CopyStar</h2>
                        <p class="lead mb-4 hero-description" data-key="welcomeText">
                            Follow professional traders and automatically copy their successful strategies. Start your journey to profitable trading today.
                        </p>
                        <div class="d-flex gap-3 flex-wrap hero-buttons">
                            <a href="<?= WEB_URL; ?>/register" class="btn btn-primary btn-lg btn-modern" data-key="startTrading">
                                <i class="fas fa-play me-2"></i><span data-key="startTrading">Start Copy Trading</span>
                            </a>
                            <a href="<?= WEB_URL; ?>/login" class="btn btn-outline-modern btn-lg" data-key="viewDashboard">
                                <i class="fas fa-chart-bar me-2"></i><span data-key="viewDashboard">View Dashboard</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="stats-grid animate-slide-up">
                        <div class="stat-card glass-card">
                            <div class="stat-icon-wrapper">
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                            <div class="stat-number" data-key="copiersCount">50.000+</div>
                            <div class="stat-label" data-key="copiers">Copiers</div>
                            <div class="stat-trend">
                                <i class="fas fa-arrow-up text-success"></i> <span>+12%</span>
                            </div>
                        </div>
                        <div class="stat-card glass-card">
                            <div class="stat-icon-wrapper">
                                <div class="stat-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                            <div class="stat-number" data-key="volumeAmount">$129.4M+</div>
                            <div class="stat-label" data-key="totalVolume">Total 24h Trading Volume</div>
                            <div class="stat-trend">
                                <i class="fas fa-arrow-up text-success"></i> <span>+8%</span>
                            </div>
                        </div>
                        <div class="stat-card glass-card">
                            <div class="stat-icon-wrapper">
                                <div class="stat-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="stat-number" data-key="tradesCount">7000+</div>
                            <div class="stat-label" data-key="successfulTrades">Successfully Trades</div>
                            <div class="stat-trend">
                                <i class="fas fa-arrow-up text-success"></i> <span>+15%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section py-5">
        <div class="container">
            <div class="row mb-5">
                <div class="col-12 text-center">
                    <div class="section-badge mb-3">
                        <span data-key="featuresBadge">Features</span>
                    </div>
                    <h2 class="display-4 fw-bold mb-3" data-key="whatIsTitle">What is Copy Trading?</h2>
                    <p class="lead section-description" data-key="whatIsText">
                        Copy trading allows you to automatically replicate the trades of experienced traders, giving you the opportunity to profit from their expertise without needing deep market knowledge.
                    </p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card glass-card feature-card-hover">
                        <div class="feature-icon-wrapper">
                            <div class="feature-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                        </div>
                        <h3 class="h4 mb-3" data-key="secureTitle">Secure & Reliable</h3>
                        <p data-key="secureText">
                            Your funds are protected with bank-level security and advanced risk management systems.
                        </p>
                        <a href="#" class="btn btn-link-modern" data-key="viewProof">
                            <span data-key="viewProof">View Proof of Reserves</span> <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card glass-card feature-card-hover">
                        <div class="feature-icon-wrapper">
                            <div class="feature-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                        </div>
                        <h3 class="h4 mb-3" data-key="expertTitle">Expert Traders</h3>
                        <p data-key="expertText">
                            Follow verified professional traders with proven track records and consistent performance.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card glass-card feature-card-hover">
                        <div class="feature-icon-wrapper">
                            <div class="feature-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                        </div>
                        <h3 class="h4 mb-3" data-key="realtimeTitle">Real-time Copying</h3>
                        <p data-key="realtimeText">
                            Trades are executed instantly and proportionally to your allocated capital.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works-section py-5">
        <div class="container">
            <div class="row mb-5">
                <div class="col-12 text-center">
                    <div class="section-badge mb-3">
                        <span data-key="howItWorksBadge">How It Works</span>
                    </div>
                    <h2 class="display-4 fw-bold mb-3" data-key="howItWorksTitle">How Copy Trading Works</h2>
                    <p class="lead section-description" data-key="howItWorksText">
                        Get started with copy trading in just a few simple steps and start earning with professional traders.
                    </p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-3 col-sm-6">
                    <div class="step-card glass-card text-center">
                        <div class="step-number">1</div>
                        <div class="step-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h4 class="h5 mb-3" data-key="step1Title">Create Account</h4>
                        <p data-key="step1Text">Sign up and verify your account in minutes</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="step-card glass-card text-center">
                        <div class="step-number">2</div>
                        <div class="step-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h4 class="h5 mb-3" data-key="step2Title">Choose Trader</h4>
                        <p data-key="step2Text">Browse and select from verified expert traders</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="step-card glass-card text-center">
                        <div class="step-number">3</div>
                        <div class="step-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h4 class="h5 mb-3" data-key="step3Title">Allocate Capital</h4>
                        <p data-key="step3Text">Set your investment amount and risk level</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="step-card glass-card text-center">
                        <div class="step-number">4</div>
                        <div class="step-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4 class="h5 mb-3" data-key="step4Title">Start Earning</h4>
                        <p data-key="step4Text">Automatically copy trades and watch profits grow</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Top Traders Section -->
    <section class="top-traders-section py-5">
        <div class="container">
            <div class="row mb-5">
                <div class="col-12 text-center">
                    <div class="section-badge mb-3">
                        <span data-key="topTradersBadge">Top Traders</span>
                    </div>
                    <h2 class="display-4 fw-bold mb-3" data-key="topTradersTitle">Follow the Best Traders</h2>
                    <p class="lead section-description" data-key="topTradersText">
                        Discover our top-performing traders with proven track records and consistent profits.
                    </p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="trader-card glass-card">
                        <div class="trader-header">
                            <div class="trader-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="trader-info">
                                <h4 class="h5 mb-1">CryptoMaster</h4>
                                <p class="text-muted small mb-0" data-key="verifiedTrader">Verified Trader</p>
                            </div>
                            <div class="trader-rating">
                                <i class="fas fa-star text-warning"></i>
                                <span>4.9</span>
                            </div>
                        </div>
                        <div class="trader-stats">
                            <div class="stat-item">
                                <span class="stat-label" data-key="totalReturn">Total Return</span>
                                <span class="stat-value text-success">+245%</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label" data-key="copiers">Copiers</span>
                                <span class="stat-value">12,450</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label" data-key="winRate">Win Rate</span>
                                <span class="stat-value text-success">87%</span>
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 mt-3" data-key="followTrader">Follow Trader</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="trader-card glass-card">
                        <div class="trader-header">
                            <div class="trader-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="trader-info">
                                <h4 class="h5 mb-1">BitcoinPro</h4>
                                <p class="text-muted small mb-0" data-key="verifiedTrader">Verified Trader</p>
                            </div>
                            <div class="trader-rating">
                                <i class="fas fa-star text-warning"></i>
                                <span>4.8</span>
                            </div>
                        </div>
                        <div class="trader-stats">
                            <div class="stat-item">
                                <span class="stat-label" data-key="totalReturn">Total Return</span>
                                <span class="stat-value text-success">+198%</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label" data-key="copiers">Copiers</span>
                                <span class="stat-value">9,230</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label" data-key="winRate">Win Rate</span>
                                <span class="stat-value text-success">82%</span>
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 mt-3" data-key="followTrader">Follow Trader</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="trader-card glass-card">
                        <div class="trader-header">
                            <div class="trader-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="trader-info">
                                <h4 class="h5 mb-1">AltCoinExpert</h4>
                                <p class="text-muted small mb-0" data-key="verifiedTrader">Verified Trader</p>
                            </div>
                            <div class="trader-rating">
                                <i class="fas fa-star text-warning"></i>
                                <span>4.7</span>
                            </div>
                        </div>
                        <div class="trader-stats">
                            <div class="stat-item">
                                <span class="stat-label" data-key="totalReturn">Total Return</span>
                                <span class="stat-value text-success">+176%</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label" data-key="copiers">Copiers</span>
                                <span class="stat-value">7,890</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label" data-key="winRate">Win Rate</span>
                                <span class="stat-value text-success">79%</span>
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 mt-3" data-key="followTrader">Follow Trader</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section py-5">
        <div class="container">
            <div class="row mb-5">
                <div class="col-12 text-center">
                    <div class="section-badge mb-3">
                        <span data-key="faqBadge">FAQ</span>
                    </div>
                    <h2 class="display-4 fw-bold mb-3" data-key="faqTitle">Frequently Asked Questions</h2>
                    <p class="lead section-description" data-key="faqSubtitle">
                        Find answers to common questions about copy trading.
                    </p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item glass-card mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    <span data-key="faq1Question">What is copy trading?</span>
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body" data-key="faq1Answer">
                                    Copy trading allows you to automatically replicate trades from experienced traders. When they trade, your account trades the same positions proportionally.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item glass-card mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    <span data-key="faq2Question">How much money do I need to start?</span>
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body" data-key="faq2Answer">
                                    You can start copy trading with as little as $10. However, we recommend starting with at least $100 to better manage risk and diversify your portfolio.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item glass-card mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    <span data-key="faq3Question">Is copy trading safe?</span>
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body" data-key="faq3Answer">
                                    While copy trading can be profitable, all trading involves risk. We provide risk management tools and only feature verified traders, but you should never invest more than you can afford to lose.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item glass-card mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    <span data-key="faq4Question">Can I stop copying a trader anytime?</span>
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body" data-key="faq4Answer">
                                    Yes, you can stop copying any trader at any time. Your existing positions will remain open, but no new trades will be copied.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item glass-card mb-3">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                    <span data-key="faq5Question">What fees do you charge?</span>
                                </button>
                            </h2>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body" data-key="faq5Answer">
                                    We charge a small performance fee only when you make a profit. There are no monthly fees or hidden charges. The fee structure is transparent and displayed before you start copying.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section py-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <div class="cta-content glass-card">
                        <h2 class="display-4 fw-bold mb-4" data-key="readyTitle">Ready to Start Your Trading Journey?</h2>
                        <p class="lead mb-4 cta-description" data-key="readyText">
                            Join thousands of successful traders who are already earning with CopyStar. Start with as little as $10 and watch your portfolio grow.
                        </p>
                        <a href="#" class="btn btn-primary btn-lg btn-modern btn-cta" data-key="getStarted">
                            <i class="fas fa-rocket me-2"></i><span data-key="getStarted">Get Started Now</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4 mb-md-0">
                    <div class="footer-brand mb-3">
                        <img src="vendor/logo.png" alt="CopyStar Logo" class="footer-logo me-2">
                        <span class="fw-bold">CopyStar</span>
                    </div>
                    <p class="footer-text mb-3" data-key="footerDescription">
                        The most advanced copy trading platform. Follow expert traders and automate your crypto trading strategy.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-telegram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-discord"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-3 col-6 mb-4 mb-md-0">
                    <h5 class="footer-heading mb-3" data-key="company">Company</h5>
                    <ul class="footer-list">
                        <li><a href="#" data-key="aboutUs">About Us</a></li>
                        <li><a href="#" data-key="careers">Careers</a></li>
                        <li><a href="#" data-key="blog">Blog</a></li>
                        <li><a href="#" data-key="press">Press</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-3 col-6 mb-4 mb-md-0">
                    <h5 class="footer-heading mb-3" data-key="support">Support</h5>
                    <ul class="footer-list">
                        <li><a href="#" data-key="helpCenter">Help Center</a></li>
                        <li><a href="#" data-key="contact">Contact</a></li>
                        <li><a href="#" data-key="faq">FAQ</a></li>
                        <li><a href="#" data-key="api">API</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-3 col-6 mb-4 mb-md-0">
                    <h5 class="footer-heading mb-3" data-key="legal">Legal</h5>
                    <ul class="footer-list">
                        <li><a href="#" data-key="privacy">Privacy Policy</a></li>
                        <li><a href="#" data-key="terms">Terms of Service</a></li>
                        <li><a href="#" data-key="cookies">Cookies</a></li>
                        <li><a href="#" data-key="compliance">Compliance</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <h5 class="footer-heading mb-3" data-key="resources">Resources</h5>
                    <ul class="footer-list">
                        <li><a href="#" data-key="learn">Learn</a></li>
                        <li><a href="#" data-key="guides">Guides</a></li>
                        <li><a href="#" data-key="market">Market</a></li>
                        <li><a href="#" data-key="tools">Tools</a></li>
                    </ul>
                </div>
            </div>
            <div class="row mt-4 pt-4 border-top border-color">
                <div class="col-md-6 mb-3 mb-md-0">
                    <p class="footer-copyright mb-0">&copy; 2024 CopyStar. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="footer-copyright mb-0" data-key="disclaimer">
                        Trading involves risk. Only invest what you can afford to lose.
                    </p>
                </div>
            </div>
        </div>
    </footer>