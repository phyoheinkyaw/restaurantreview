   <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- AlertifyJS CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Google Translate CSS -->
    <style>
        /* Additional translation styles */
        .language-selector {
            position: relative;
        }
        .language-selector select {
            padding-left: 28px;
            background-color: transparent;
            border: none;
            color: #fff;
            cursor: pointer;
            font-size: 0.8rem;
        }
        .language-selector::before {
            content: "\f0ac";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #fff;
        }
        .language-selector select:focus {
            box-shadow: none;
            border: none;
        }
        .language-selector select option {
            color: #333;
            background-color: #fff;
        }
        /* RTL support */
        body.rtl-language .navbar-nav,
        body.rtl-language .row {
            flex-direction: row-reverse;
        }
        body.rtl-language .ms-auto {
            margin-right: auto !important;
            margin-left: 0 !important;
        }
        body.rtl-language .ms-2,
        body.rtl-language .ms-3 {
            margin-right: 0.5rem !important;
            margin-left: 0 !important;
        }
        body.rtl-language .me-2,
        body.rtl-language .me-3 {
            margin-left: 0.5rem !important;
            margin-right: 0 !important;
        }
    </style>
    <!-- Translator Script -->
    <script src="assets/js/translator.js"></script>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar bg-dark text-light py-2">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small><i class="fas fa-phone me-2"></i>+1 234 567 890</small>
                    <small class="ms-3"><i class="fas fa-envelope me-2"></i>support@restaurantreview.com</small>
                </div>
                <div class="col-md-6 text-end">
                    <div class="language-selector d-inline-block me-3" title="Change language">
                        <select class="form-select form-select-sm bg-dark text-light" aria-label="Language selector">
                            <option value="en">English</option>
                            <option value="es">Español</option>
                            <option value="fr">Français</option>
                            <option value="de">Deutsch</option>
                            <option value="it">Italiano</option>
                            <option value="zh-CN">中文</option>
                            <option value="ja">日本語</option>
                            <option value="ko">한국어</option>
                            <option value="ru">Русский</option>
                            <option value="ar">العربية</option>
                        </select>
                    </div>
                    <div class="currency-selector d-inline-block">
                        <select class="form-select form-select-sm">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-utensils text-primary me-2"></i>
                <span data-i18n="restaurantReview">Restaurant Review</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php" data-i18n="home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php" data-i18n="search">Search</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php" data-i18n="about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php" data-i18n="contact">Contact</a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="dropdown me-3">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-2"></i><span data-i18n="myAccount">My Account</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php" data-i18n="profile">Profile</a></li>
                                <li><a class="dropdown-item" href="reservations.php" data-i18n="reservations">My Reservations</a></li>
                                <li><a class="dropdown-item" href="reviews.php" data-i18n="reviews">My Reviews</a></li>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/index.php">Admin Dashboard</a></li>
                                <?php endif; ?>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'owner'): ?>
                                    <li><a class="dropdown-item" href="owner/index.php">Owner Dashboard</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php" data-i18n="logout">Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-primary me-2" data-i18n="login">Login</a>
                        <a href="register.php" class="btn btn-primary" data-i18n="signup">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>