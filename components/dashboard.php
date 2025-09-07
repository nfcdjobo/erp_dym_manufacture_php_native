<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../connect/database.php';

// Récupérer les données utilisateur
try {
    $db = new Data();
    $pdo = $db->connect();
    
    // Informations utilisateur connecté
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_STR);
    $stmt->execute();
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Statistiques générales
    $stats = [];
    
    // Nombre total d'utilisateurs
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL");
    $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nombre total de catégories
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories WHERE deleted_at IS NULL");
    $stats['categories'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nombre total de produits
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produits WHERE deleted_at IS NULL");
    $stats['products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Produits actifs
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produits WHERE is_active = 1 AND deleted_at IS NULL");
    $stats['active_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Valeur totale du stock
    $stmt = $pdo->query("SELECT SUM(price * stock_quantity) as total FROM produits WHERE deleted_at IS NULL");
    $stats['stock_value'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Derniers produits ajoutés
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name 
        FROM produits p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.deleted_at IS NULL 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $recentProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Catégories avec nombre de produits
    $stmt = $pdo->query("
        SELECT c.*, COUNT(p.id) as product_count 
        FROM categories c 
        LEFT JOIN produits p ON c.id = p.category_id AND p.deleted_at IS NULL 
        WHERE c.deleted_at IS NULL 
        GROUP BY c.id 
        ORDER BY product_count DESC 
        LIMIT 5
    ");
    $topCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
}

// Gestion de la déconnexion
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Définir les variables pour le header
$currentPage = 'dashboard';
$pageSubtitle = 'Système ERP';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <title>Dashboard - ERP DYM Manufacture</title>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-in': 'slideIn 0.3s ease-out',
                        'bounce-in': 'bounceIn 0.6s ease-out'
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 min-h-screen" x-data="dashboard()">
    
    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Contenu principal -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        
        <!-- En-tête -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900">Tableau de bord</h2>
            <p class="mt-2 text-gray-600">Bienvenue, <?php echo htmlspecialchars($currentUser['first_name']); ?>! Voici un aperçu de votre système ERP.</p>
        </div>

        <?php if (isset($error)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Utilisateurs -->
            <div class="bg-white overflow-hidden shadow rounded-lg animate-bounce-in">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Utilisateurs</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['users']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Catégories -->
            <div class="bg-white overflow-hidden shadow rounded-lg animate-bounce-in" style="animation-delay: 0.1s;">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Catégories</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['categories']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Produits -->
            <div class="bg-white overflow-hidden shadow rounded-lg animate-bounce-in" style="animation-delay: 0.2s;">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Produits total</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['products']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valeur du stock -->
            <div class="bg-white overflow-hidden shadow rounded-lg animate-bounce-in" style="animation-delay: 0.3s;">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Valeur stock</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo number_format($stats['stock_value'], 0, ',', ' '); ?> FCFA</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenu en deux colonnes -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Derniers produits -->
            <div class="bg-white shadow rounded-lg animate-fade-in">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Derniers produits ajoutés</h3>
                    <?php if (!empty($recentProducts)): ?>
                    <div class="space-y-3">
                        <?php foreach ($recentProducts as $product): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></h4>
                                <p class="text-xs text-gray-500">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'Sans catégorie'); ?> • 
                                    Stock: <?php echo $product['stock_quantity']; ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900"><?php echo number_format($product['price'], 2, ',', ' '); ?> FCFA</p>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $product['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $product['is_active'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-500 text-center py-4">Aucun produit trouvé</p>
                    <?php endif; ?>
                    <div class="mt-4">
                        <a href="./produit.php" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">Voir tous les produits →</a>
                    </div>
                </div>
            </div>

            <!-- Top catégories -->
            <div class="bg-white shadow rounded-lg animate-fade-in" style="animation-delay: 0.2s;">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Top catégories</h3>
                    <?php if (!empty($topCategories)): ?>
                    <div class="space-y-3">
                        <?php foreach ($topCategories as $index => $category): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></h4>
                                    <p class="text-xs text-gray-500">
                                        <?php echo $category['product_count']; ?> produit<?php echo $category['product_count'] > 1 ? 's' : ''; ?>
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $category['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $category['is_active'] ? 'Actif' : 'Inactif'; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-500 text-center py-4">Aucune catégorie trouvée</p>
                    <?php endif; ?>
                    <div class="mt-4">
                        <a href="./categories.php" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">Voir toutes les catégories →</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="mt-8">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Actions rapides</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <button class="flex flex-col items-center p-4 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition duration-200 group">
                    <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center group-hover:bg-blue-600 transition duration-200">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </div>
                    <span class="mt-2 text-sm font-medium text-gray-900">Ajouter produit</span>
                </button>

                <button class="flex flex-col items-center p-4 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition duration-200 group">
                    <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center group-hover:bg-green-600 transition duration-200">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <span class="mt-2 text-sm font-medium text-gray-900">Nouvelle catégorie</span>
                </button>

                <button class="flex flex-col items-center p-4 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition duration-200 group">
                    <div class="w-10 h-10 bg-yellow-500 rounded-lg flex items-center justify-center group-hover:bg-yellow-600 transition duration-200">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4"></path>
                        </svg>
                    </div>
                    <span class="mt-2 text-sm font-medium text-gray-900">Rapport stock</span>
                </button>

                <button class="flex flex-col items-center p-4 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition duration-200 group">
                    <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center group-hover:bg-purple-600 transition duration-200">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <span class="mt-2 text-sm font-medium text-gray-900">Paramètres</span>
                </button>
            </div>
        </div>
    </main>

    <script>
        function dashboard() {
            return {
                // Données du dashboard
            }
        }

        // Animation d'entrée pour les éléments
        document.addEventListener('DOMContentLoaded', function() {
            // Effet de compteur animé pour les statistiques
            const counters = document.querySelectorAll('[data-count]');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                const count = +counter.innerText;
                const increment = target / 200;
                
                if (count < target) {
                    counter.innerText = Math.ceil(count + increment);
                    setTimeout(() => {
                        counter.click();
                    }, 1);
                } else {
                    counter.innerText = target;
                }
            });
        });
    </script>
</body>
</html>