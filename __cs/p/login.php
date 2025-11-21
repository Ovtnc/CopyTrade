    <title>Login - CopyStar</title>
</head>
<body>
<?php
// Redirect to dashboard if user is already logged in
if (isset($currentUser) && $currentUser) {
    header("Location: " . WEB_URL . "/dashboard");
    exit;
}

// Login processing
$loginErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Get form data
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $rememberMe = isset($_POST['rememberMe']) ? true : false;
    
    // Validation
    if (empty($email)) {
        $loginErrors['email'] = 'emailRequired';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $loginErrors['email'] = 'emailInvalidFormat';
    }
    
    if (empty($password)) {
        $loginErrors['password'] = 'passwordRequired';
    }
    
    // If no errors, proceed with login
    if (empty($loginErrors)) {
        // Check user credentials
        $stmt = $conn->prepare("SELECT id, email, password, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $loginErrors['general'] = 'invalidEmailOrPassword';
        } else {
            $user = $result->fetch_assoc();
            
            // Verify password (decode from base64)
            $decodedPassword = base64_decode($user['password']);
            if ($password !== $decodedPassword) {
                $loginErrors['general'] = 'invalidEmailOrPassword';
            } else {
                // Check account status
                if ($user['status'] !== 'active') {
                    $statusMessage = '';
                    switch ($user['status']) {
                        case 'banned':
                            $statusMessage = 'accountBanned';
                            break;
                        case 'suspended':
                            $statusMessage = 'accountSuspended';
                            break;
                        default:
                            $statusMessage = 'accountNotActive';
                    }
                    $loginErrors['general'] = $statusMessage;
                } else {
                    // Login user
                    loginUser($user['id'], $user['email'], $rememberMe);
                    
                    // Success - redirect to dashboard
                    header("Location: " . WEB_URL . "/dashboard");
                    exit;
                }
            }
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
                    <li class="nav-item nav-item-mobile">
                        <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>" data-key="backToHome">
                            <i class="fas fa-home me-2"></i><span class="nav-text-mobile" data-key="backToHome">Back to Home</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Login Section -->
    <section class="login-section">
        <div class="container">
            <div class="row justify-content-center align-items-center">
                <div class="col-lg-5 col-md-7">
                    <div class="login-card glass-card">
                        <div class="text-center mb-4">
                            <img src="<?= WEB_URL; ?>/vendor/logo.png" alt="CopyStar Logo" class="login-logo mb-3">
                            <h1 class="h3 fw-bold mb-2" data-key="loginTitle">Sign In to Your Account</h1>
                            <p class="text-muted" data-key="loginSubtitle">Welcome back to CopyStar. Sign in to continue.</p>
                        </div>
                        
                        <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
                            <div class="alert alert-success mb-4">
                                <i class="fas fa-check-circle me-2"></i>Registration successful! Please sign in with your credentials.
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($loginErrors['general'])): ?>
                            <div class="alert alert-danger mb-4">
                                <span data-key="<?= htmlspecialchars($loginErrors['general']) ?>"><?= htmlspecialchars($loginErrors['general']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <form id="loginForm" method="POST" action="<?= WEB_URL; ?>/login">
                            <div class="mb-3">
                                <label for="email" class="form-label" data-key="emailLabel">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control <?= isset($loginErrors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" placeholder="example@email.com" data-key="emailPlaceholder" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                                </div>
                                <?php if (isset($loginErrors['email'])): ?>
                                    <div class="invalid-feedback d-block"><span data-key="<?= htmlspecialchars($loginErrors['email']) ?>"><?= htmlspecialchars($loginErrors['email']) ?></span></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label" data-key="passwordLabel">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control <?= isset($loginErrors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" placeholder="Enter your password" data-key="passwordPlaceholder" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye" id="passwordIcon"></i>
                                    </button>
                                </div>
                                <?php if (isset($loginErrors['password'])): ?>
                                    <div class="invalid-feedback d-block"><span data-key="<?= htmlspecialchars($loginErrors['password']) ?>"><?= htmlspecialchars($loginErrors['password']) ?></span></div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe" value="1">
                                    <label class="form-check-label" for="rememberMe" data-key="rememberMe">
                                        Remember Me
                                    </label>
                                </div>
                                <a href="<?= WEB_URL; ?>/forgot-password" class="text-decoration-none" data-key="forgotPassword">Forgot Password?</a>
                            </div>

                            <input type="hidden" name="login" value="1">
                            <button type="submit" class="btn btn-primary btn-lg w-100 btn-modern mb-3" data-key="loginButton">
                                <i class="fas fa-sign-in-alt me-2"></i><span data-key="loginButton">Sign In</span>
                            </button>

                            <div class="text-center mt-4">
                                <p class="mb-0">
                                    <span data-key="noAccount">Don't have an account?</span> 
                                    <a href="<?= WEB_URL; ?>/register" class="text-decoration-none fw-bold" data-key="createAccount">Create Account</a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (togglePassword && passwordInput && passwordIcon) {
                togglePassword.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        passwordIcon.classList.remove('fa-eye');
                        passwordIcon.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        passwordIcon.classList.remove('fa-eye-slash');
                        passwordIcon.classList.add('fa-eye');
                    }
                });
            }

            // Form submission - just show loading state
            // Note: We don't preventDefault() - form will submit normally via POST
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    // Show loading state (but don't prevent form submission)
                    const submitButton = this.querySelector('button[type="submit"]');
                    if (submitButton) {
                        const originalText = submitButton.innerHTML;
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing in...';
                    }
                    // Form will submit normally via POST - no e.preventDefault()
                });
            }
        });
        
        // Translate error messages after page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (typeof updateLanguage === 'function' && typeof currentLang !== 'undefined') {
                    updateLanguage(currentLang);
                } else if (typeof updateLanguage === 'function') {
                    const lang = localStorage.getItem('language') || 'tr';
                    updateLanguage(lang);
                }
            }, 200);
        });
    </script>
