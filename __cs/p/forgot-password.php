
    <title>Forgot Password - CopyStar</title>

</head>
<body>
    <!-- Animated Background -->
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
                        <a class="nav-link nav-link-mobile" href="<?= WEB_URL; ?>" data-key="backToHome">
                            <i class="fas fa-home me-2"></i><span class="nav-text-mobile" data-key="backToHome">Back to Home</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Forgot Password Section -->
    <section class="login-section">
        <div class="container">
            <div class="row justify-content-center align-items-center">
                <div class="col-lg-5 col-md-7">
                    <div class="login-card glass-card">
                        <div class="text-center mb-4">
                            <div class="forgot-password-icon mb-3">
                                <i class="fas fa-key"></i>
                            </div>
                            <h1 class="h3 fw-bold mb-2" data-key="forgotPasswordTitle">Forgot Password</h1>
                            <p class="text-muted" data-key="forgotPasswordSubtitle">Enter your email address and we'll send you a password reset link.</p>
                        </div>

                        <form id="forgotPasswordForm">
                            <div class="mb-4">
                                <label for="email" class="form-label" data-key="emailLabel">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" placeholder="example@email.com" data-key="emailPlaceholder" required>
                                </div>
                                <div class="invalid-feedback" id="emailError"></div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 btn-modern mb-3" data-key="resetPasswordButton">
                                <i class="fas fa-paper-plane me-2"></i><span data-key="resetPasswordButton">Send Reset Email</span>
                            </button>

                            <div class="text-center mt-4">
                                <a href="<?= WEB_URL; ?>/login" class="text-decoration-none" data-key="backToLogin">
                                    <i class="fas fa-arrow-left me-2"></i><span data-key="backToLogin">Back to Login</span>
                                </a>
                            </div>
                        </form>

                        <!-- Success Message (hidden by default) -->
                        <div id="successMessage" class="alert alert-success d-none mt-4" role="alert">
                            <div class="text-center">
                                <i class="fas fa-check-circle fa-2x mb-3"></i>
                                <h5 class="fw-bold" data-key="resetEmailSent">Reset email sent!</h5>
                                <p class="mb-0" data-key="resetEmailSentText">We've sent a password reset link to your email address. Please check your inbox.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Forgot Password Form Handler
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const emailInput = document.getElementById('email');
            const emailError = document.getElementById('emailError');
            const successMessage = document.getElementById('successMessage');
            const form = this;
            
            // Reset previous states
            emailInput.classList.remove('is-invalid');
            emailError.textContent = '';
            
            // Basic validation
            if (!email) {
                emailInput.classList.add('is-invalid');
                emailError.textContent = 'Please enter your email address.';
                return;
            }
            
            // Email format validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                emailInput.classList.add('is-invalid');
                emailError.textContent = 'Please enter a valid email address.';
                return;
            }
            
            // Here you would typically send the data to the server
            // Example: fetch('/api/forgot-password', { method: 'POST', body: JSON.stringify({ email: email }) });
            
            // Show success message
            form.style.display = 'none';
            successMessage.classList.remove('d-none');
            
            // Optional: Reset form after 5 seconds
            setTimeout(function() {
                form.style.display = 'block';
                successMessage.classList.add('d-none');
                form.reset();
            }, 5000);
        });
    </script>

