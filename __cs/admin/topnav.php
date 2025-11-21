<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="<?= WEB_URL; ?>/admin">
            <img src="<?= WEB_URL; ?>/vendor/logo.png" alt="CopyStar Logo" class="navbar-logo me-2">
            CopyStar <span class="badge bg-danger ms-2">Admin</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item nav-item-mobile me-2">
                    <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/admin">
                        <i class="fas fa-tachometer-alt me-2"></i><span class="nav-text-mobile">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item nav-item-mobile me-2">
                    <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/admin/traders">
                        <i class="fas fa-chart-line me-2"></i><span class="nav-text-mobile">Traders</span>
                    </a>
                </li>
                <li class="nav-item nav-item-mobile me-2">
                    <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/admin/trades">
                        <i class="fas fa-exchange-alt me-2"></i><span class="nav-text-mobile">Trades</span>
                    </a>
                </li>
                <li class="nav-item nav-item-mobile me-2">
                    <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/admin/users">
                        <i class="fas fa-users me-2"></i><span class="nav-text-mobile">Users</span>
                    </a>
                </li>
                <li class="nav-item nav-item-mobile me-2">
                    <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/dashboard">
                        <i class="fas fa-arrow-left me-2"></i><span class="nav-text-mobile">User Panel</span>
                    </a>
                </li>
                <li class="nav-item nav-item-mobile">
                    <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>/logout">
                        <i class="fas fa-sign-out-alt me-2"></i><span class="nav-text-mobile">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

