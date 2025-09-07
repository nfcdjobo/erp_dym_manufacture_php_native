<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../connect/database.php';

// Gestion des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    ob_start();
    $response = ['success' => false, 'message' => ''];
    
    try {
        $db = new Data();
        $pdo = $db->connect();
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name)) {
                    throw new Exception('Le nom de la catégorie est obligatoire');
                }
                
                // Vérifier si la catégorie existe déjà
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = :name AND deleted_at IS NULL");
                $stmt->bindParam(':name', $name);
                $stmt->execute();
                
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Une catégorie avec ce nom existe déjà');
                }
                
                // Générer un UUID
                $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
                
                $stmt = $pdo->prepare("INSERT INTO categories (id, name, description, is_active, user_id, created_at, updated_at) VALUES (:id, :name, :description, :is_active, :user_id, NOW(), NOW())");
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Catégorie créée avec succès'];
                } else {
                    throw new Exception('Erreur lors de la création de la catégorie');
                }
                break;
                
            case 'update':
                $id = $_POST['id'] ?? '';
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($id) || empty($name)) {
                    throw new Exception('ID et nom de la catégorie sont obligatoires');
                }
                
                // Vérifier si la catégorie existe (autre que celle en cours d'édition)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = :name AND id != :id AND deleted_at IS NULL");
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Une catégorie avec ce nom existe déjà');
                }
                
                $stmt = $pdo->prepare("UPDATE categories SET name = :name, description = :description, is_active = :is_active, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL");
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Catégorie modifiée avec succès'];
                } else {
                    throw new Exception('Erreur lors de la modification de la catégorie');
                }
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? '';
                
                if (empty($id)) {
                    throw new Exception('ID de la catégorie obligatoire');
                }
                
                // Vérifier s'il y a des produits liés à cette catégorie
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE category_id = :id AND deleted_at IS NULL");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $productCount = $stmt->fetchColumn();
                
                if ($productCount > 0) {
                    throw new Exception("Impossible de supprimer la catégorie car elle contient $productCount produit(s)");
                }
                
                // Soft delete
                $stmt = $pdo->prepare("UPDATE categories SET deleted_at = NOW() WHERE id = :id");
                $stmt->bindParam(':id', $id);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Catégorie supprimée avec succès'];
                } else {
                    throw new Exception('Erreur lors de la suppression de la catégorie');
                }
                break;
                
            case 'get':
                $id = $_POST['id'] ?? '';
                
                if (empty($id)) {
                    throw new Exception('ID de la catégorie obligatoire');
                }
                
                $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id AND deleted_at IS NULL");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $category = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($category) {
                    $response = ['success' => true, 'data' => $category];
                } else {
                    throw new Exception('Catégorie introuvable');
                }
                break;
                
            default:
                throw new Exception('Action non reconnue');
        }
        
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

// Récupération des données pour la page
try {
    $db = new Data();
    $pdo = $db->connect();
    
    // Informations utilisateur connecté
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Pagination
    $page = $_GET['page'] ?? 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    
    // Requête pour compter le total
    $countQuery = "SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL";
    $countParams = [];
   
    if (!empty($search)) {
        $countQuery .= " AND (name LIKE :search1 OR description LIKE :search2)";
        $searchPattern = "%$search%";
        $countParams[':search1'] = $searchPattern;
        $countParams[':search2'] = $searchPattern;
    }
    
    $stmt = $pdo->prepare($countQuery);
    
    foreach ($countParams as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    
    $stmt->execute();
    
    $totalCategories = $stmt->fetchColumn();
    $totalPages = ceil($totalCategories / $limit);
    
    // Requête pour récupérer les catégories
    $query = "SELECT c.*, u.first_name, u.last_name, 
              (SELECT COUNT(*) FROM produits p WHERE p.category_id = c.id AND p.deleted_at IS NULL) as product_count
              FROM categories c 
              LEFT JOIN users u ON c.user_id = u.id 
              WHERE c.deleted_at IS NULL";
    
    $queryParams = [];
    
    if (!empty($search)) {
        $query .= " AND (c.name LIKE :search1 OR c.description LIKE :search2)";
        $searchPattern = "%$search%";
        $queryParams[':search1'] = $searchPattern;
        $queryParams[':search2'] = $searchPattern;
    }
    
    $query .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    
    // Lier les paramètres de recherche
    foreach ($queryParams as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    
    // Lier les paramètres de pagination
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
}

// Gestion de la déconnexion
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$currentPage = 'categories';
$pageSubtitle = 'Gestion des Catégories';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <title>Gestion des Catégories - ERP DYM Manufacture</title>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 min-h-screen" x-data="categoriesManager()">

    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Contenu principal -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        
        <!-- En-tête -->
        <div class="md:flex md:items-center md:justify-between mb-8">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl">Gestion des Catégories</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Gérez vos catégories de produits
                </p>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <button @click="openCreateModal()" 
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Nouvelle Catégorie
                </button>
            </div>
        </div>

        <!-- Barre de recherche -->
        <div class="mb-6">
            <form method="GET" class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Rechercher une catégorie..." 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <button type="submit" 
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    Rechercher
                </button>
            </form>
        </div>

        <?php if (isset($error)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>

        <!-- Tableau des catégories -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-4 py-5 sm:p-6">
                <?php if (!empty($categories)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produits</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Créé par</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($categories as $category): ?>
                            <tr class="hover:bg-gray-50 animate-fade-in">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-xs truncate">
                                        <?php echo htmlspecialchars($category['description'] ?? 'Aucune description'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?php echo $category['product_count']; ?> produit<?php echo $category['product_count'] > 1 ? 's' : ''; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $category['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $category['is_active'] ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars(($category['first_name'] ?? '') . ' ' . ($category['last_name'] ?? '')); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d/m/Y', strtotime($category['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <a href="./category-detail.php?id=<?php echo $category['id']; ?>" 
                                        class="text-blue-600 hover:text-blue-900 transition duration-200"
                                        title="Voir le détail">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </a>
                                        <button @click="openEditModal('<?php echo $category['id']; ?>')" 
                                                class="text-indigo-600 hover:text-indigo-900 transition duration-200">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button @click="openDeleteModal('<?php echo $category['id']; ?>', '<?php echo htmlspecialchars($category['name']); ?>')" 
                                                class="text-red-600 hover:text-red-900 transition duration-200">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-6 flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Affichage de <?php echo ($offset + 1); ?> à <?php echo min($offset + $limit, $totalCategories); ?> sur <?php echo $totalCategories; ?> résultats
                    </div>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Précédent
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-3 py-2 border rounded-md text-sm font-medium <?php echo $i == $page ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Suivant
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Aucune catégorie</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        <?php echo !empty($search) ? 'Aucun résultat trouvé pour votre recherche.' : 'Commencez par créer votre première catégorie.'; ?>
                    </p>
                    <?php if (empty($search)): ?>
                    <div class="mt-6">
                        <button @click="openCreateModal()" 
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Nouvelle Catégorie
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Créer/Éditer -->
    <div x-show="showModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                
                <form @submit.prevent="submitForm()">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" x-text="isEditing ? 'Modifier la catégorie' : 'Nouvelle catégorie'"></h3>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label for="category-name" class="block text-sm font-medium text-gray-700">Nom *</label>
                                        <input type="text" id="category-name" x-model="formData.name" required
                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                               placeholder="Nom de la catégorie">
                                    </div>
                                    <div>
                                        <label for="category-description" class="block text-sm font-medium text-gray-700">Description</label>
                                        <textarea id="category-description" x-model="formData.description" rows="3"
                                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                  placeholder="Description de la catégorie (optionnel)"></textarea>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="checkbox" id="category-active" x-model="formData.is_active"
                                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <label for="category-active" class="ml-2 block text-sm text-gray-900">
                                            Catégorie active
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" :disabled="loading"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                            <span x-show="!loading" x-text="isEditing ? 'Modifier' : 'Créer'"></span>
                            <span x-show="loading" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Traitement...
                            </span>
                        </button>
                        <button type="button" @click="closeModal()"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Suppression -->
    <div x-show="showDeleteModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Confirmer la suppression</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Êtes-vous sûr de vouloir supprimer la catégorie "<span x-text="deleteItem.name" class="font-medium"></span>" ? 
                                    Cette action ne peut pas être annulée.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="confirmDelete()" :disabled="loading"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                        <span x-show="!loading">Supprimer</span>
                        <span x-show="loading">Suppression...</span>
                    </button>
                    <button @click="showDeleteModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Annuler
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Toast -->
    <div x-show="notification.show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed top-4 right-4 z-50 max-w-sm" 
         style="display: none;">
        <div class="rounded-md p-4" :class="notification.type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg x-show="notification.type === 'success'" class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <svg x-show="notification.type === 'error'" class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium" :class="notification.type === 'success' ? 'text-green-800' : 'text-red-800'" 
                       x-text="notification.message"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function categoriesManager() {
            return {
                showModal: false,
                showDeleteModal: false,
                isEditing: false,
                loading: false,
                formData: {
                    id: '',
                    name: '',
                    description: '',
                    is_active: true
                },
                deleteItem: {
                    id: '',
                    name: ''
                },
                notification: {
                    show: false,
                    type: '',
                    message: ''
                },

                openCreateModal() {
                    this.isEditing = false;
                    this.formData = {
                        id: '',
                        name: '',
                        description: '',
                        is_active: true
                    };
                    this.showModal = true;
                },

                async openEditModal(id) {
                    this.loading = true;
                    try {
                        const response = await this.makeRequest('get', { id });
                        if (response.success) {
                            this.isEditing = true;
                            this.formData = {
                                id: response.data.id,
                                name: response.data.name,
                                description: response.data.description || '',
                                is_active: response.data.is_active == 1
                            };
                            this.showModal = true;
                        } else {
                            this.showNotification('error', response.message);
                        }
                    } catch (error) {
                        this.showNotification('error', 'Erreur lors du chargement de la catégorie');
                    } finally {
                        this.loading = false;
                    }
                },

                openDeleteModal(id, name) {
                    this.deleteItem = { id, name };
                    this.showDeleteModal = true;
                },

                closeModal() {
                    this.showModal = false;
                },

                async submitForm() {
                    if (!this.formData.name.trim()) {
                        this.showNotification('error', 'Le nom de la catégorie est obligatoire');
                        return;
                    }

                    this.loading = true;
                    try {
                        const action = this.isEditing ? 'update' : 'create';
                        const response = await this.makeRequest(action, this.formData);
                        
                        if (response.success) {
                            this.showNotification('success', response.message);
                            this.showModal = false;
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            this.showNotification('error', response.message);
                        }
                    } catch (error) {
                        this.showNotification('error', 'Erreur lors de l\'opération');
                    } finally {
                        this.loading = false;
                    }
                },

                async confirmDelete() {
                    this.loading = true;
                    try {
                        const response = await this.makeRequest('delete', { id: this.deleteItem.id });
                        
                        if (response.success) {
                            this.showNotification('success', response.message);
                            this.showDeleteModal = false;
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            this.showNotification('error', response.message);
                        }
                    } catch (error) {
                        this.showNotification('error', 'Erreur lors de la suppression');
                    } finally {
                        this.loading = false;
                    }
                },

                async makeRequest(action, data) {
                    const formData = new FormData();
                    formData.append('action', action);
                    
                    for (const key in data) {
                        if (key === 'is_active') {
                            if (data[key]) formData.append(key, '1');
                        } else {
                            formData.append(key, data[key]);
                        }
                    }

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });

                    return await response.json();
                },

                showNotification(type, message) {
                    this.notification = { show: true, type, message };
                    setTimeout(() => {
                        this.notification.show = false;
                    }, 5000);
                }
            }
        }
    </script>
</body>
</html>