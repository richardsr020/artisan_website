<?php
/**
 * Script de migration pour mettre à jour la table recharges
 * À exécuter une seule fois pour ajouter les nouveaux champs
 */

require_once 'config.php';

$db = get_db_connection();

try {
    $db->exec(
        "CREATE TABLE IF NOT EXISTS software ("
        . "id INTEGER PRIMARY KEY AUTOINCREMENT, "
        . "name TEXT NOT NULL, "
        . "version TEXT, "
        . "description TEXT, "
        . "file_name TEXT NOT NULL, "
        . "file_path TEXT NOT NULL, "
        . "file_size INTEGER, "
        . "uploaded_by_user_id INTEGER, "
        . "is_active BOOLEAN DEFAULT 1, "
        . "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, "
        . "FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id)"
        . ")"
    );

    // Vérifier si les colonnes existent déjà
    $stmt = $db->query("PRAGMA table_info(recharges)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $column_names = array_column($columns, 'name');
    
    $alterations = [];
    
    // Ajouter les nouvelles colonnes si elles n'existent pas (seulement les essentielles)
    if (!in_array('encrypted_file_path', $column_names)) {
        $alterations[] = "ADD COLUMN encrypted_file_path TEXT";
    }
    
    if (!in_array('error_message', $column_names)) {
        $alterations[] = "ADD COLUMN error_message TEXT";
    }
    
    if (!in_array('completed_date', $column_names)) {
        $alterations[] = "ADD COLUMN completed_date TIMESTAMP";
    }
    
    // Exécuter les modifications
    if (!empty($alterations)) {
        foreach ($alterations as $alteration) {
            $db->exec("ALTER TABLE recharges " . $alteration);
            echo "✓ Colonne ajoutée: " . str_replace("ADD COLUMN ", "", $alteration) . "\n";
        }
        echo "\n✓ Migration terminée avec succès!\n";
    } else {
        echo "✓ La base de données est déjà à jour.\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Erreur lors de la migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>

