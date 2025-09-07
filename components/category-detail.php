<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../connect/database.php';

// Récupérer l'ID de la catégorie depuis l'URL
$category_id = $_GET['id'] ?? '';

if (empty($category_id)) {
    header('Location: ./categories.php');
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
    
    // Récupérer les détails de la catégorie
    $stmt = $pdo->prepare("
        SELECT c.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM produits p WHERE p.category_id = c.id AND p.deleted_at IS NULL) as product_count
        FROM categories c 
        LEFT JOIN users u ON c.user_id = u.id 
        WHERE c.id = :id AND c.deleted_at IS NULL
    ");
    $stmt->bindParam(':id', $category_id);
    $stmt->execute();
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        throw new Exception('Catégorie introuvable');
    }
    
    // Pagination pour les produits
    $page = $_GET['page'] ?? 1;
    $limit = 12;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    
    // Requête pour compter le total des produits
    $countQuery = "SELECT COUNT(*) FROM produits WHERE category_id = :category_id AND deleted_at IS NULL";
    $countParams = [':category_id' => $category_id];
   
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
    
    $totalProducts = $stmt->fetchColumn();
    $totalPages = ceil($totalProducts / $limit);
    
    // Requête pour récupérer les produits
    $query = "
        SELECT p.* 
        FROM produits p 
        WHERE p.category_id = :category_id AND p.deleted_at IS NULL
    ";
    
    $queryParams = [':category_id' => $category_id];
    
    if (!empty($search)) {
        $query .= " AND (p.name LIKE :search1 OR p.description LIKE :search2)";
        $searchPattern = "%$search%";
        $queryParams[':search1'] = $searchPattern;
        $queryParams[':search2'] = $searchPattern;
    }
    
    $query .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    
    // Lier les paramètres de recherche
    foreach ($queryParams as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    
    // Lier les paramètres de pagination
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
}

// Gestion de la déconnexion
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Fonction pour obtenir l'image principale d'un produit
function getMainImage($imagesJson) {
    if (empty($imagesJson)) return null;
    
    $images = json_decode($imagesJson, true);
    if (!is_array($images)) return null;
    
    // Chercher l'image marquée comme principale
    foreach ($images as $image) {
        if (isset($image['is_main']) && $image['is_main']) {
            return $image['path'] ?? $image['url'] ?? null;
        }
    }
    
    // Si aucune image principale, retourner la première
    return isset($images[0]) ? ($images[0]['path'] ?? $images[0]['url'] ?? null) : null;
}

// Fonction pour formater le prix
function formatPrice($price) {
    return number_format($price, 2, ',', ' ') . ' FCFA';
}

$currentPage = 'categories';
$pageSubtitle = 'Détail Catégorie';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <title><?php echo isset($category) ? 'Catégorie: ' . htmlspecialchars($category['name']) : 'Catégorie'; ?> - ERP DYM Manufacture</title>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        
        .product-card {
            transition: all 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .image-placeholder {
            background: linear-gradient(45deg, #f3f4f6 25%, transparent 25%), 
                        linear-gradient(-45deg, #f3f4f6 25%, transparent 25%), 
                        linear-gradient(45deg, transparent 75%, #f3f4f6 75%), 
                        linear-gradient(-45deg, transparent 75%, #f3f4f6 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 min-h-screen">

    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Contenu principal -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        
        <?php if (isset($error)): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php elseif (isset($category)): ?>
        
        <!-- Breadcrumb -->
        <nav class="flex mb-6" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="./dashboard.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                        <svg class="w-3 h-3 mr-2.5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="m19.707 9.293-2-2-7-7a1 1 0 0 0-1.414 0l-7 7-2 2a1 1 0 0 0 1.414 1.414L9 3.414V19a1 1 0 0 0 2 0V3.414l7.293 7.293a1 1 0 0 0 1.414-1.414Z"/>
                        </svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-3 h-3 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                        <a href="./categories.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">Catégories</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-3 h-3 text-gray-400 mx-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2"><?php echo htmlspecialchars($category['name']); ?></span>
                    </div>
                </li>
            </ol>
        </nav>

        <!-- Informations de la catégorie -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <div class="sm:flex sm:items-center sm:justify-between">
                    <div class="sm:flex-1">
                        <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($category['name']); ?></h1>
                        <?php if (!empty($category['description'])): ?>
                        <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($category['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                <?php echo $category['product_count']; ?> produit<?php echo $category['product_count'] > 1 ? 's' : ''; ?>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Créé par <?php echo htmlspecialchars(($category['first_name'] ?? '') . ' ' . ($category['last_name'] ?? '')); ?>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <?php echo date('d/m/Y', strtotime($category['created_at'])); ?>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $category['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $category['is_active'] ? 'Actif' : 'Inactif'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0">
                        <div class="flex space-x-3">
                            <a href="./categories.php" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Retour aux catégories
                            </a>
                            <a href="./produit.php?category_id=<?php echo $category['id']; ?>" 
                               class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Ajouter un produit
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barre de recherche pour les produits -->
        <div class="mb-6">
            <form method="GET" class="flex flex-col sm:flex-row gap-4">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($category_id); ?>">
                <div class="flex-1">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Rechercher un produit dans cette catégorie..." 
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

        <!-- Liste des produits -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-6">
                    Produits de la catégorie 
                    <?php if (!empty($search)): ?>
                    - Résultats pour "<?php echo htmlspecialchars($search); ?>"
                    <?php endif; ?>
                </h2>
                
                <?php if (!empty($products)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($products as $product): ?>
                    <?php $mainImage = getMainImage($product['images']); ?>
                    <div class="product-card bg-white border border-gray-200 rounded-lg overflow-hidden animate-fade-in">
                        <!-- Image du produit -->
                        <div class="aspect-w-1 aspect-h-1 w-full">
                            <?php if ($mainImage): ?>
                            <img src="../<?php echo htmlspecialchars($mainImage); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 class="w-full h-48 object-cover">
                            <?php else: ?>
                            <div class="w-full h-48 image-placeholder flex items-center justify-center">
                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Contenu -->
                        <div class="p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-sm font-medium text-gray-900 truncate" title="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </h3>
                                    <?php if (!empty($product['description'])): ?>
                                    <p class="mt-1 text-xs text-gray-500 line-clamp-2" title="<?php echo htmlspecialchars($product['description']); ?>">
                                        <?php echo htmlspecialchars(substr($product['description'], 0, 80) . (strlen($product['description']) > 80 ? '...' : '')); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $product['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $product['is_active'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </div>
                            
                            <div class="mt-3 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?php echo formatPrice($product['price']); ?></p>
                                    <p class="text-xs text-gray-500">Stock: <?php echo $product['stock_quantity']; ?></p>
                                </div>
                                <div class="flex space-x-1">
                                    <button class="p-1 text-indigo-600 hover:text-indigo-800 transition duration-200" 
                                            title="Voir le détail">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </button>
                                    <button class="p-1 text-indigo-600 hover:text-indigo-800 transition duration-200" 
                                            title="Modifier">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-8 flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Affichage de <?php echo ($offset + 1); ?> à <?php echo min($offset + $limit, $totalProducts); ?> sur <?php echo $totalProducts; ?> produits
                    </div>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                        <a href="?id=<?php echo urlencode($category_id); ?>&page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Précédent
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?id=<?php echo urlencode($category_id); ?>&page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-3 py-2 border rounded-md text-sm font-medium <?php echo $i == $page ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?id=<?php echo urlencode($category_id); ?>&page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Aucun produit</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        <?php echo !empty($search) ? 'Aucun produit trouvé pour votre recherche.' : 'Cette catégorie ne contient aucun produit pour le moment.'; ?>
                    </p>
                    <?php if (empty($search)): ?>
                    <div class="mt-6">
                        <a href="./produit.php?category_id=<?php echo $category['id']; ?>" 
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Ajouter le premier produit
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php endif; ?>
    </main>
</body>
</html>