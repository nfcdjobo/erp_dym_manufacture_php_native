<?php

require_once __DIR__ . '/connect/database.php';

class User {
    private $id;
    private $database;
    
    public function __construct() {
        $this->database = (new Data())->connect();
    }
    
    /**
     * Génère un UUID v4
     * @return string
     */
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Valide les données d'entrée
     * @param array $data
     * @return array|null
     */
    private function validateRegistrationData($data) {
        $errors = [];
        
        if (empty($data['first_name']) || strlen($data['first_name']) < 2) {
            $errors[] = "Le prénom doit contenir au moins 2 caractères";
        }
        
        if (empty($data['last_name']) || strlen($data['last_name']) < 2) {
            $errors[] = "Le nom doit contenir au moins 2 caractères";
        }
        
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "L'email n'est pas valide";
        }
        
        if (empty($data['phone']) || !preg_match('/^[0-9+\-\s]{8,15}$/', $data['phone'])) {
            $errors[] = "Le numéro de téléphone n'est pas valide";
        }
        
        if (empty($data['password']) || strlen($data['password']) < 8) {
            $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
        }
        
        return empty($errors) ? null : $errors;
    }
    
    /**
     * Inscription d'un nouvel utilisateur
     * @param string $first_name
     * @param string $last_name
     * @param string $email
     * @param string $phone
     * @param string $password
     * @return array
     */
    public function register($first_name, $last_name, $email, $phone, $password) {
        try {
            // Validation des données
            $validationErrors = $this->validateRegistrationData([
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password
            ]);
            
            if ($validationErrors) {
                return [
                    'success' => false,
                    'errors' => $validationErrors
                ];
            }
            
            // Vérifier si l'utilisateur existe déjà
            if ($this->userExists($email, $phone)) {
                return [
                    'success' => false,
                    'errors' => ['Un utilisateur avec cet email ou ce téléphone existe déjà']
                ];
            }
            
            // Générer l'UUID et hasher le mot de passe
            $this->id = $this->generateUUID();
            $passwordHashed = password_hash($password, PASSWORD_DEFAULT);
            
            // Requête d'insertion corrigée
            $query = 'INSERT INTO users (id, first_name, last_name, email, phone, password) VALUES (:id, :first_name, :last_name, :email, :phone, :password)';
            $stmt = $this->database->prepare($query);
            
            // Binding des paramètres
            $stmt->bindParam(':id', $this->id, PDO::PARAM_STR);
            $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
            $stmt->bindParam(':password', $passwordHashed, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Utilisateur créé avec succès',
                    'user_id' => $this->id
                ];
            } else {
                return [
                    'success' => false,
                    'errors' => ['Erreur lors de la création de l\'utilisateur']
                ];
            }
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'errors' => ['Erreur de base de données : ' . $e->getMessage()]
            ];
        }
    }
    
    /**
     * Vérifie si un utilisateur existe déjà
     * @param string $email
     * @param string $phone
     * @return bool
     */
    public function userExists($email, $phone) {
        try {
            $query = 'SELECT COUNT(*) FROM users WHERE email = :email OR phone = :phone';
            $stmt = $this->database->prepare($query);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
            $stmt->execute();
            
            return $stmt->fetchColumn() > 0;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Connexion de l'utilisateur
     * @param string $email
     * @param string $password
     * @return array
     */
    public function signIn($email, $password) {
        try {
            // Validation basique
            if (empty($email) || empty($password)) {
                return [
                    'success' => false,
                    'errors' => ['Email et mot de passe requis']
                ];
            }
            
            // Requête pour récupérer l'utilisateur
            $query = 'SELECT * FROM users WHERE email = :email';
            $stmt = $this->database->prepare($query);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Vérifier si l'utilisateur existe et si le mot de passe est correct
            if ($user && password_verify($password, $user['password'])) {
                // Ne pas démarrer la session ici - laisser le contrôleur le faire
                return [
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'email' => $user['email'],
                        'phone' => $user['phone'],
                        'id_role' => $user['id_role'] ?? null
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'errors' => ['Email ou mot de passe incorrect']
                ];
            }
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'errors' => ['Erreur de base de données : ' . $e->getMessage()]
            ];
        }
    }
    
    /**
     * Vérifie un utilisateur par son ID de session
     * @param string $sessionId
     * @return array|null
     */
    public function verify($sessionId) {
        try {
            if (empty($sessionId)) {
                return null;
            }
            
            $query = 'SELECT id, first_name, last_name, email, phone, id_role FROM users WHERE id = :id';
            $stmt = $this->database->prepare($query);
            $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Met à jour le mot de passe d'un utilisateur
     * @param string $userId
     * @param string $newPassword
     * @return bool
     */
    public function updatePassword($userId, $newPassword) {
        try {
            if (strlen($newPassword) < 8) {
                return false;
            }
            
            $passwordHashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $query = 'UPDATE users SET password = :password WHERE id = :id';
            $stmt = $this->database->prepare($query);
            $stmt->bindParam(':password', $passwordHashed, PDO::PARAM_STR);
            $stmt->bindParam(':id', $userId, PDO::PARAM_STR);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Supprime un utilisateur
     * @param string $userId
     * @return bool
     */
    public function deleteUser($userId) {
        try {
            $query = 'DELETE FROM users WHERE id = :id';
            $stmt = $this->database->prepare($query);
            $stmt->bindParam(':id', $userId, PDO::PARAM_STR);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>