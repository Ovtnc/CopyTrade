<title>404 - Page Not Found | CopyStar</title>
</head>
<body>
<div class="animated-bg"></div>
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= WEB_URL; ?>">
                <img src="./vendor/logo.png" alt="CopyStar Logo" class="navbar-logo me-2">
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
                    <li class="nav-item nav-item-mobile">
                        <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>">
                            <i class="fas fa-home me-2"></i><span class="nav-text-mobile" data-key="goHome">Ana Sayfa</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Error Section -->
    <section class="error-section">
        <div class="container">
            <div class="error-content">
                <div class="error-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="error-code">404</div>
                <div class="glass-card p-5 mb-4">
                    <h1 class="h2 fw-bold mb-3" data-key="pageNotFound">Sayfa Bulunamadı</h1>
                    <p class="lead text-muted mb-4" data-key="pageNotFoundDescription">
                        Aradığınız sayfa mevcut değil veya taşınmış olabilir. Lütfen ana sayfaya dönün veya menüden istediğiniz sayfayı seçin.
                    </p>
                    <div class="d-flex flex-column flex-md-row gap-3 justify-content-center">
                        <a href="<?= WEB_URL; ?>" class="btn btn-modern btn-lg" data-key="goHome">
                            <i class="fas fa-home me-2"></i><span data-key="goHome">Ana Sayfaya Dön</span>
                        </a>
                        <a href="<?= WEB_URL; ?>/dashboard" class="btn btn-outline-modern btn-lg" data-key="viewDashboard">
                            <i class="fas fa-chart-bar me-2"></i><span data-key="viewDashboard">Paneli Görüntüle</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>