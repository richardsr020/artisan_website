<?php
/**
 * Script d'initialisation de la base de données SQLite
 * À exécuter une seule fois pour créer la structure de la base
 */

require_once 'config.php';

// Créer le dossier db s'il n'existe pas
$db_dir = __DIR__ . '/db';
if (!is_dir($db_dir)) {
    mkdir($db_dir, 0755, true);
}

$db = get_db_connection();

// Lire et exécuter le fichier SQL
$sql_file = __DIR__ . '/database.sql';
if (file_exists($sql_file)) {
    $sql = file_get_contents($sql_file);
    
    // Diviser les requêtes SQL (séparées par ;)
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($queries as $query) {
        if (!empty($query)) {
            try {
                $db->exec($query);
            } catch (PDOException $e) {
                echo "Erreur lors de l'exécution de la requête: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Créer l'utilisateur admin par défaut si nécessaire
    $admin_check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $admin_check->execute();
    
    if ($admin_check->fetchColumn() == 0) {
        // Hash du mot de passe par défaut: admin123
        $admin_password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        
        $admin_insert = $db->prepare("
            INSERT INTO users (username, password_hash, email, company_name, subscription_type, is_active) 
            VALUES ('admin', ?, 'admin@artisan-nd.com', 'Artisan_ND Admin', 'admin', 1)
        ");
        $admin_insert->execute([$admin_password_hash]);
        
        echo "✓ Utilisateur admin créé (username: admin, password: admin123)\n";
    }
    
    echo "✓ Base de données initialisée avec succès!\n";
} else {
    die("Erreur: Le fichier database.sql n'existe pas!\n");
}
?>


