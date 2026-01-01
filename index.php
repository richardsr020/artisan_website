<?php
require_once 'config.php';

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclure la configuration du système d'abonnement pour vérifier l'authentification
require_once __DIR__ . '/subscription/config.php';

// Liste des pages autorisées
$allowed_pages = [
    'home' => 'pages/home.php',
    'features' => 'pages/features.php',
    'download' => 'pages/download.php',
    'subscription' => 'pages/subscription.php',
    'contact' => 'pages/contact.php',
    '404' => 'pages/404.php',
    'download_file' => 'download.php', // Route pour le téléchargement du fichier
    // Routes pour le système d'abonnement
    'login' => 'subscription/login.php',
    'register' => 'subscription/register.php',
    'dashboard' => 'subscription/dashboard.php',
    'recharge' => 'subscription/recharge.php',
    'admin' => 'subscription/admin.php',
    'logout' => 'subscription/logout.php',
    'download_key' => 'subscription/download_key.php',
];

// Récupérer la page demandée
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Vérifier si la page existe
if (!isset($allowed_pages[$page])) {
    $page = '404';
}

// Définir la constante ROUTED pour permettre l'inclusion
if (!defined('ROUTED')) {
    define('ROUTED', true);
}

// Vérifier si l'utilisateur est connecté
$is_logged_in = is_logged_in();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo SITE_TAGLINE; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <link rel="icon" href="images/icon.png" type="image/png">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-top">
                <div class="logo-header">
                    <img src="images/icon.png" alt="Logo Artisan_ND" class="logo">
                    <h1><?php echo SITE_NAME; ?></h1>
                </div>
                <button class="mobile-menu-toggle" aria-label="Menu" id="mobileMenuToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
            <nav id="mainNav">
                <ul>
                    <li><a href="<?php echo url('home'); ?>" <?php echo ($page == 'home') ? 'class="active"' : ''; ?>>Accueil</a></li>
                    <li><a href="<?php echo url('download'); ?>" <?php echo ($page == 'download') ? 'class="active"' : ''; ?>>Téléchargement</a></li>
                    <li><a href="<?php echo url('subscription'); ?>" <?php echo ($page == 'subscription') ? 'class="active"' : ''; ?>>Devenir Partenaire</a></li>
                    <?php if ($is_logged_in): ?>
                        <li><a href="<?php echo url('dashboard'); ?>" <?php echo ($page == 'dashboard') ? 'class="active"' : ''; ?>>Tableau de bord</a></li>
                        <?php if ($page == 'dashboard'): ?>
                            <li><a href="<?php echo url('recharge'); ?>" <?php echo ($page == 'recharge') ? 'class="active"' : ''; ?>>Nouvelle recharge</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo url('logout'); ?>">Déconnexion</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo url('login'); ?>" <?php echo ($page == 'login') ? 'class="active"' : ''; ?>>Connexion</a></li>
                        <li><a href="<?php echo url('register'); ?>" <?php echo ($page == 'register') ? 'class="active"' : ''; ?>>Inscription</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <?php
            // Chargement de la page demandée
            if (isset($allowed_pages[$page])) {
                // Vérifier si c'est une page subscription (nécessite un layout différent)
                if (in_array($page, ['login', 'register', 'dashboard', 'recharge', 'admin'])) {
                    // Les pages subscription ont leur propre layout, on les inclut directement
                    include $allowed_pages[$page];
                } else {
                    // Pages publiques avec le layout standard
                    include $allowed_pages[$page];
                }
            } else {
                include 'pages/404.php';
            }
            ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="images/icon.png" alt="Logo Artisan_ND" class="footer-logo-img">
                    <h3><?php echo SITE_NAME; ?></h3>
                    <p><?php echo SITE_TAGLINE; ?></p>
                </div>
                <div class="footer-links">
                    <h4>Liens rapides</h4>
                    <ul>
                        <li><a href="<?php echo url('home'); ?>">Accueil</a></li>
                        <li><a href="<?php echo url('download'); ?>">Télécharger</a></li>
                        <li><a href="<?php echo url('subscription'); ?>">Devenir Partenaire</a></li>
                      
                    </ul>
                </div>
                <div class="footer-contact">
                    <h4>Contact</h4>
                    <p>Email: <?php echo CONTACT_EMAIL; ?></p>
                    <p>Téléphone: whatsapp <?php echo CONTACT_PHONE; ?></p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo COPYRIGHT_YEAR; ?> <?php echo SITE_NAME; ?>. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>