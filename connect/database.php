<?php 
class Data {
    private $host = 'localhost';
    private $port = 3312; // Port XAMPP personnalisé
    private $dbname = 'erp_dym_manufacture';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    private $database;

    /**
     * Établit la connexion à la base de données
     * @return PDO
     * @throws PDOException
     */
    public function connect() {
        if ($this->database === null) {
            try {
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";
                
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
                ];
                
                $this->database = new PDO($dsn, $this->username, $this->password, $options);
                
            } catch (PDOException $e) {
                // Ne pas utiliser echo - lancer une exception à la place
                throw new PDOException("Connexion à la base de données échouée: " . $e->getMessage(), $e->getCode());
            }
        }
        
        return $this->database;
    }

    /**
     * Teste la connexion à la base de données
     * @return bool
     */
    public function testConnection() {
        try {
            $pdo = $this->connect();
            $stmt = $pdo->query('SELECT 1');
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Ferme la connexion à la base de données
     */
    public function disconnect() {
        $this->database = null;
    }

    /**
     * Obtient des informations sur la base de données
     * @return array
     */
    public function getInfo() {
        try {
            $pdo = $this->connect();
            return [
                'host' => $this->host,
                'port' => $this->port,
                'dbname' => $this->dbname,
                'charset' => $this->charset,
                'connected' => $pdo instanceof PDO
            ];
        } catch (Exception $e) {
            return [
                'host' => $this->host,
                'port' => $this->port,
                'dbname' => $this->dbname,
                'charset' => $this->charset,
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

/**
 * Script de création de table users (à exécuter une seule fois)
 * Décommentez et accédez à cette page pour créer la table
 */
/*
if (isset($_GET['create_table'])) {
    try {
        $db = new Data();
        $pdo = $db->connect();
        
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id VARCHAR(36) PRIMARY KEY,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            phone VARCHAR(20) NOT NULL,
            password VARCHAR(255) NOT NULL,
            id_role INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $pdo->exec($sql);
        
        // Créer les index pour de meilleures performances
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
            "CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone)"
        ];
        
        foreach ($indexes as $index) {
            $pdo->exec($index);
        }
        
        echo "✅ Table 'users' créée avec succès !<br>";
        echo "✅ Index créés avec succès !<br>";
        echo "<a href='?' style='color: blue;'>← Retour</a>";
        
    } catch (Exception $e) {
        echo "❌ Erreur lors de la création de la table: " . $e->getMessage();
    }
    exit;
}

// Interface simple pour créer la table
if (!isset($_GET['create_table'])) {
    echo "
    <h2>Configuration de la base de données</h2>
    <p><a href='?create_table=1' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Créer la table users</a></p>
    <p><em>Cliquez pour créer automatiquement la table users dans votre base de données.</em></p>
    ";
}
*/
?>