<?php
/**
 * Script de nettoyage pour supprimer les colonnes inutiles de la table recharges
 * À exécuter une seule fois pour nettoyer la structure
 * 
 * ATTENTION: SQLite ne supporte pas DROP COLUMN directement
 * Il faut recréer la table avec la nouvelle structure
 */

require_once 'config.php';

$db = get_db_connection();

try {
    // Vérifier si les colonnes à supprimer existent
    $stmt = $db->query("PRAGMA table_info(recharges)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $column_names = array_column($columns, 'name');
    
    $columns_to_remove = ['client_phone', 'client_email', 'public_key_pem', 'encrypted_message'];
    $has_columns_to_remove = false;
    
    foreach ($columns_to_remove as $col) {
        if (in_array($col, $column_names)) {
            $has_columns_to_remove = true;
            break;
        }
    }
    
    if (!$has_columns_to_remove) {
        echo "✓ La table recharges est déjà nettoyée.\n";
        exit(0);
    }
    
    echo "⚠️  SQLite ne supporte pas DROP COLUMN directement.\n";
    echo "⚠️  Nous allons recréer la table avec la nouvelle structure.\n";
    echo "⚠️  Les données existantes seront préservées.\n\n";
    
    // Démarrer une transaction
    $db->beginTransaction();
    
    // 1. Créer une table temporaire avec la nouvelle structure
    $db->exec("
        CREATE TABLE IF NOT EXISTS recharges_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            client_phone TEXT NOT NULL,
            client_email TEXT NOT NULL,
            quota_units INTEGER NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            encrypted_file_path TEXT,
            transaction_id TEXT UNIQUE,
            status TEXT DEFAULT 'pending',
            error_message TEXT,
            recharge_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_date TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    // 2. Copier les données existantes (seulement les colonnes à conserver)
    // Note: Si les colonnes client_phone et client_email n'existent pas, elles seront NULL
    $db->exec("
        INSERT INTO recharges_new (
            id, user_id, client_phone, client_email, quota_units, amount, 
            encrypted_file_path, transaction_id, status, 
            error_message, recharge_date, completed_date
        )
        SELECT 
            id, user_id, 
            COALESCE(client_phone, '') as client_phone,
            COALESCE(client_email, '') as client_email,
            quota_units, amount, 
            encrypted_file_path, transaction_id, status, 
            error_message, recharge_date, completed_date
        FROM recharges
    ");
    
    // 3. Supprimer l'ancienne table
    $db->exec("DROP TABLE recharges");
    
    // 4. Renommer la nouvelle table
    $db->exec("ALTER TABLE recharges_new RENAME TO recharges");
    
    // Valider la transaction
    $db->commit();
    
    echo "✓ Table recharges mise à jour avec succès!\n";
    echo "✓ Structure finale: id, user_id, client_phone, client_email, quota_units, amount, encrypted_file_path, transaction_id, status, error_message, recharge_date, completed_date\n";
    
} catch (PDOException $e) {
    $db->rollBack();
    echo "✗ Erreur lors du nettoyage: " . $e->getMessage() . "\n";
    exit(1);
}
?>

