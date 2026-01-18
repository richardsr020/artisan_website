<?php
// Configuration principale du site
define('SITE_NAME', 'Artisan_ND');
define('OWNER_NAME', 'Nest Softwar Corporation');
define('SITE_TAGLINE', 'Système de numérotation de facturiers intelligent');
define('VERSION', '1.0.0');
define('COPYRIGHT_YEAR', date('Y'));

// Informations de contact
define('CONTACT_EMAIL', 'contact.nestcorp@gmail.com');
define('CONTACT_PHONE', '+243 840 149 027');

// Chemins des dossiers
define('SETUP_PATH', 'setup/');
define('SUBSCRIPTION_PATH', 'subscription/');
define('IMAGES_PATH', 'images/');
define('FILES_PATH', 'files/');
define('LOGS_PATH', 'logs/');
define('DOCS_PATH', 'docs/');
define('DOWNLOAD_FILE', 'files/downloads/code.deb');
define('DOWNLOAD_LOG', 'logs/downloads.log');

// Informations sur le logiciel
$software_info = [
    'name' => 'Artisan_ND',
    'version' => '1.0',
    'description' => 'Logiciel de numérotation de facturiers qui remplace le numéroteur classique manuel, mécanique et fatigant.',
    'system_requirements' => [
        'Système d\'exploitation' => 'Windows 10/11',
        'Processeur' => '1,8 GHz ou supérieur',
        'RAM' => '4 Go minimum',
        'Espace disque' => '500 Mo disponibles'
    ],
    'features' => [
        'Numérotation automatique des factures',
        'Gestion des séries de numérotation',
        'Export des données en PDF',
        'Interface intuitive'
    ]
];

// Configuration de la base de données (pour la partie abonnement)
$db_config = [
    'host' => 'localhost',
    'name' => 'artisan_nd_subscriptions',
    'user' => 'root',
    'pass' => ''
];

// Messages et textes réutilisables
$messages = [
    'welcome' => 'Bienvenue sur Artisan_ND',
    'download_title' => 'Téléchargez Artisan_ND gratuitement',
    'subscription_info' => 'Abonnez-vous pour accéder au système de recharge des quotas'
];

// Définir une constante pour indiquer que le fichier est inclus via index.php
if (!defined('ROUTED')) {
    define('ROUTED', true);
}

/**
 * Génère une URL pour une page du site
 * @param string $page Nom de la page
 * @param array $params Paramètres additionnels (optionnel)
 * @return string URL complète
 */
function url($page, $params = []) {
    $url = 'index.php?page=' . $page;
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $url .= '&' . $key . '=' . urlencode($value);
        }
    }
    return $url;
}

/**
 * Redirige vers une page 404 si accès direct
 */
function prevent_direct_access() {
    if (!defined('ROUTED')) {
        header('Location: index.php?page=404');
        exit;
    }
}
?>