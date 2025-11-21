<nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= WEB_URL; ?>">
                <img src="<?= WEB_URL; ?>/vendor/logo.png" alt="CopyStar Logo" class="navbar-logo me-2">
                CopyStar
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item nav-item-mobile me-2">
                        <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/dashboard">
                            <i class="fas fa-home me-2"></i><span class="nav-text-mobile" data-key="dashboardTitle">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item nav-item-mobile me-2">
                        <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/account">
                            <i class="fas fa-user me-2"></i><span class="nav-text-mobile" data-key="account">Hesap</span>
                        </a>
                    </li>
                    <li class="nav-item nav-item-mobile me-2">
                        <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/traders">
                            <i class="fas fa-users me-2"></i><span class="nav-text-mobile" data-key="traders">Traders</span>
                        </a>
                    </li>
                    <li class="nav-item nav-item-mobile me-2">
                        <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/wallet">
                            <i class="fas fa-wallet me-2"></i><span class="nav-text-mobile" data-key="wallet">Cüzdan</span>
                        </a>
                    </li>
                    <li class="nav-item nav-item-mobile me-2">
                        <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/refer">
                            <i class="fas fa-user-plus me-2"></i><span class="nav-text-mobile" data-key="refer">Refer</span>
                        </a>
                    </li>
                    <li class="nav-item nav-item-mobile me-2">
                        <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/help">
                            <i class="fas fa-question-circle me-2"></i><span class="nav-text-mobile" data-key="help">Yardım</span>
                        </a>
                    </li>
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
                        <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/logout">
                            <i class="fas fa-sign-out-alt me-2"></i><span class="nav-text-mobile" data-key="logout">Çıkış Yap</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>