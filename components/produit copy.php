<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../connect/database.php';

// Fonction pour gérer l'upload des images
function handleImageUpload($files, $productId) {
    $uploadDir = '../uploads/products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $images = [];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    
    foreach ($files['tmp_name'] as $key => $tmpName) {
        if ($files['error'][$key] === UPLOAD_ERR_OK) {
            // Vérifications
            if ($files['size'][$key] > $maxFileSize) {
                throw new Exception('Fichier trop volumineux: ' . $files['name'][$key]);
            }
            
            // Vérifier le type MIME réel
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpName);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                throw new Exception('Type de fichier non autorisé: ' . $files['name'][$key]);
            }
            
            // Générer un nom unique
            $extension = pathinfo($files['name'][$key], PATHINFO_EXTENSION);
            $filename = $productId . '_' . uniqid() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($tmpName, $filepath)) {
                // Redimensionner l'image si nécessaire
                resizeImage($filepath, $filepath, 800, 600);
                
                $images[] = [
                    'filename' => $filename,
                    'original_name' => $files['name'][$key],
                    'is_main' => empty($images), // Le premier devient l'image principale
                    'path' => 'uploads/products/' . $filename
                ];
            }
        }
    }
    
    return $images;
}

// Fonction pour redimensionner les images
function resizeImage($source, $destination, $maxWidth = 800, $maxHeight = 600) {
    $imageInfo = getimagesize($source);
    if (!$imageInfo) return false;
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $mime = $imageInfo['mime'];
    
    // Si l'image est déjà plus petite, ne pas redimensionner
    if ($sourceWidth <= $maxWidth && $sourceHeight <= $maxHeight) {
        return true;
    }
    
    // Calculer les nouvelles dimensions
    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
    $newWidth = round($sourceWidth * $ratio);
    $newHeight = round($sourceHeight * $ratio);
    
    // Créer l'image source
    switch ($mime) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($source);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) return false;
    
    // Créer la nouvelle image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Préserver la transparence pour PNG et GIF
    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
    }
    
    // Redimensionner
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
    
    // Sauvegarder
    $success = false;
    switch ($mime) {
        case 'image/jpeg':
            $success = imagejpeg($newImage, $destination, 90);
            break;
        case 'image/png':
            $success = imagepng($newImage, $destination);
            break;
        case 'image/gif':
            $success = imagegif($newImage, $destination);
            break;
        case 'image/webp':
            $success = imagewebp($newImage, $destination);
            break;
    }
    
    // Nettoyer la mémoire
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $success;
}

// Fonction pour supprimer les images physiques
function deleteProductImages($images) {
    if (is_string($images)) {
        $images = json_decode($images, true);
    }
    
    if (is_array($images)) {
        foreach ($images as $image) {
            $filepath = '../' . $image['path'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }
}

// Fonction helper pour afficher les images dans le HTML
function getMainImage($images) {
    if (empty($images)) return null;
    
    $imagesArray = is_string($images) ? json_decode($images, true) : $images;
    if (!is_array($imagesArray)) return null;
    
    foreach ($imagesArray as $image) {
        if ($image['is_main'] ?? false) {
            return $image;
        }
    }
    
    // Si aucune image principale définie, retourner la première
    return $imagesArray[0] ?? null;
}

function getAllImages($images) {
    if (empty($images)) return [];
    
    $imagesArray = is_string($images) ? json_decode($images, true) : $images;
    return is_array($imagesArray) ? $imagesArray : [];
}

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
                $price = floatval($_POST['price'] ?? 0);
                $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
                $category_id = trim($_POST['category_id'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($name)) {
                    throw new Exception('Le nom du produit est obligatoire');
                }
                
                if ($price <= 0) {
                    throw new Exception('Le prix doit être supérieur à 0');
                }
                
                if (empty($category_id)) {
                    throw new Exception('Veuillez sélectionner une catégorie');
                }
                
                if ($stock_quantity < 0) {
                    throw new Exception('Le stock ne peut pas être négatif');
                }
                
                // Vérifier si la catégorie existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = :id AND deleted_at IS NULL");
                $stmt->bindParam(':id', $category_id);
                $stmt->execute();
                
                if ($stmt->fetchColumn() == 0) {
                    throw new Exception('La catégorie sélectionnée n\'existe pas');
                }
                
                // Générer un UUID
                $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
                
                // Gérer les images
                $images = null;
                if (isset($_FILES['images']) && !empty($_FILES['images']['tmp_name'][0])) {
                    $uploadedImages = handleImageUpload($_FILES['images'], $id);
                    $images = json_encode($uploadedImages);
                }
                
                $stmt = $pdo->prepare("INSERT INTO produits (id, name, description, price, stock_quantity, category_id, is_active, images, created_at, updated_at) VALUES (:id, :name, :description, :price, :stock_quantity, :category_id, :is_active, :images, NOW(), NOW())");
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':price', $price);
                $stmt->bindParam(':stock_quantity', $stock_quantity, PDO::PARAM_INT);
                $stmt->bindParam(':category_id', $category_id);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                $stmt->bindParam(':images', $images);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Produit créé avec succès'];
                } else {
                    throw new Exception('Erreur lors de la création du produit');
                }
                break;
                
            case 'update':
                $id = $_POST['id'] ?? '';
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price = floatval($_POST['price'] ?? 0);
                $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
                $category_id = trim($_POST['category_id'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($id) || empty($name)) {
                    throw new Exception('ID et nom du produit sont obligatoires');
                }
                
                if ($price <= 0) {
                    throw new Exception('Le prix doit être supérieur à 0');
                }
                
                if (empty($category_id)) {
                    throw new Exception('Veuillez sélectionner une catégorie');
                }
                
                if ($stock_quantity < 0) {
                    throw new Exception('Le stock ne peut pas être négatif');
                }
                
                // Vérifier si la catégorie existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = :id AND deleted_at IS NULL");
                $stmt->bindParam(':id', $category_id);
                $stmt->execute();
                
                if ($stmt->fetchColumn() == 0) {
                    throw new Exception('La catégorie sélectionnée n\'existe pas');
                }
                
                // Récupérer les images actuelles
                $stmt = $pdo->prepare("SELECT images FROM produits WHERE id = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $currentProduct = $stmt->fetch(PDO::FETCH_ASSOC);
                $currentImages = $currentProduct['images'] ? json_decode($currentProduct['images'], true) : [];
                
                // Gérer les nouvelles images
                $updatedImages = $currentImages;
                if (isset($_FILES['images']) && !empty($_FILES['images']['tmp_name'][0])) {
                    $newImages = handleImageUpload($_FILES['images'], $id);
                    $updatedImages = array_merge($currentImages, $newImages);
                }
                
                // Gérer la suppression d'images si demandée
                if (isset($_POST['remove_images'])) {
                    $imagesToRemove = json_decode($_POST['remove_images'], true);
                    if (is_array($imagesToRemove)) {
                        foreach ($imagesToRemove as $imageIndex) {
                            if (isset($updatedImages[$imageIndex])) {
                                deleteProductImages([$updatedImages[$imageIndex]]);
                                unset($updatedImages[$imageIndex]);
                            }
                        }
                        $updatedImages = array_values($updatedImages); // Réindexer le tableau
                    }
                }
                
                $imagesJson = empty($updatedImages) ? null : json_encode($updatedImages);
                
                $stmt = $pdo->prepare("UPDATE produits SET name = :name, description = :description, price = :price, stock_quantity = :stock_quantity, category_id = :category_id, is_active = :is_active, images = :images, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL");
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':price', $price);
                $stmt->bindParam(':stock_quantity', $stock_quantity, PDO::PARAM_INT);
                $stmt->bindParam(':category_id', $category_id);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                $stmt->bindParam(':images', $imagesJson);
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Produit modifié avec succès'];
                } else {
                    throw new Exception('Erreur lors de la modification du produit');
                }
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? '';
                
                if (empty($id)) {
                    throw new Exception('ID du produit obligatoire');
                }
                
                // Récupérer les images avant suppression
                $stmt = $pdo->prepare("SELECT images FROM produits WHERE id = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Soft delete
                $stmt = $pdo->prepare("UPDATE produits SET deleted_at = NOW() WHERE id = :id");
                $stmt->bindParam(':id', $id);
                
                if ($stmt->execute()) {
                    // Supprimer les images physiques
                    if ($product && $product['images']) {
                        deleteProductImages($product['images']);
                    }
                    $response = ['success' => true, 'message' => 'Produit supprimé avec succès'];
                } else {
                    throw new Exception('Erreur lors de la suppression du produit');
                }
                break;
                
            case 'get':
                $id = $_POST['id'] ?? '';
                
                if (empty($id)) {
                    throw new Exception('ID du produit obligatoire');
                }
                
                $stmt = $pdo->prepare("
                    SELECT p.*, c.name as category_name 
                    FROM produits p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.id = :id AND p.deleted_at IS NULL
                ");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    $response = ['success' => true, 'data' => $product];
                } else {
                    throw new Exception('Produit introuvable');
                }
                break;
                
            case 'get_categories':
                $stmt = $pdo->query("SELECT id, name FROM categories WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name ASC");
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response = ['success' => true, 'data' => $categories];
                break;
                
            case 'set_main_image':
                $id = $_POST['id'] ?? '';
                $imageIndex = intval($_POST['image_index'] ?? 0);
                
                if (empty($id)) {
                    throw new Exception('ID du produit obligatoire');
                }
                
                $stmt = $pdo->prepare("SELECT images FROM produits WHERE id = :id AND deleted_at IS NULL");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product && $product['images']) {
                    $images = json_decode($product['images'], true);
                    
                    // Enlever le statut principal de toutes les images
                    foreach ($images as &$image) {
                        $image['is_main'] = false;
                    }
                    
                    // Définir la nouvelle image principale
                    if (isset($images[$imageIndex])) {
                        $images[$imageIndex]['is_main'] = true;
                        
                        $imagesJson = json_encode($images);
                        $stmt = $pdo->prepare("UPDATE produits SET images = :images, updated_at = NOW() WHERE id = :id");
                        $stmt->bindParam(':id', $id);
                        $stmt->bindParam(':images', $imagesJson);
                        
                        if ($stmt->execute()) {
                            $response = ['success' => true, 'message' => 'Image principale mise à jour'];
                        } else {
                            throw new Exception('Erreur lors de la mise à jour');
                        }
                    } else {
                        throw new Exception('Image non trouvée');
                    }
                } else {
                    throw new Exception('Produit non trouvé');
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
    
    // Filtres avancés
    $search = $_GET['search'] ?? '';
    $category_filter = $_GET['category_filter'] ?? '';
    $status_filter = $_GET['status_filter'] ?? '';
    $price_min = $_GET['price_min'] ?? '';
    $price_max = $_GET['price_max'] ?? '';
    $stock_min = $_GET['stock_min'] ?? '';
    $stock_max = $_GET['stock_max'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $sort = $_GET['sort'] ?? 'created_at';
    $order = $_GET['order'] ?? 'DESC';
    
    // Construire la requête avec les filtres
    $countQuery = "SELECT COUNT(*) FROM produits p LEFT JOIN categories c ON p.category_id = c.id WHERE p.deleted_at IS NULL";
    $countParams = [];
    
    $conditions = [];
    
    // Recherche textuelle
    if (!empty($search)) {
        $conditions[] = "(p.name LIKE :search1 OR p.description LIKE :search2 OR c.name LIKE :search3)";
        $searchPattern = "%$search%";
        $countParams[':search1'] = $searchPattern;
        $countParams[':search2'] = $searchPattern;
        $countParams[':search3'] = $searchPattern;
    }
    
    // Filtre par catégorie
    if (!empty($category_filter)) {
        $conditions[] = "p.category_id = :category_filter";
        $countParams[':category_filter'] = $category_filter;
    }
    
    // Filtre par statut
    if ($status_filter !== '') {
        $conditions[] = "p.is_active = :status_filter";
        $countParams[':status_filter'] = $status_filter;
    }
    
    // Filtre par prix
    if (!empty($price_min)) {
        $conditions[] = "p.price >= :price_min";
        $countParams[':price_min'] = floatval($price_min);
    }
    if (!empty($price_max)) {
        $conditions[] = "p.price <= :price_max";
        $countParams[':price_max'] = floatval($price_max);
    }
    
    // Filtre par stock
    if (!empty($stock_min)) {
        $conditions[] = "p.stock_quantity >= :stock_min";
        $countParams[':stock_min'] = intval($stock_min);
    }
    if (!empty($stock_max)) {
        $conditions[] = "p.stock_quantity <= :stock_max";
        $countParams[':stock_max'] = intval($stock_max);
    }
    
    // Filtre par date
    if (!empty($date_from)) {
        $conditions[] = "DATE(p.created_at) >= :date_from";
        $countParams[':date_from'] = $date_from;
    }
    if (!empty($date_to)) {
        $conditions[] = "DATE(p.created_at) <= :date_to";
        $countParams[':date_to'] = $date_to;
    }
    
    // Ajouter les conditions à la requête
    if (!empty($conditions)) {
        $countQuery .= " AND " . implode(" AND ", $conditions);
    }
    
    $stmt = $pdo->prepare($countQuery);
    foreach ($countParams as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $totalProducts = $stmt->fetchColumn();
    $totalPages = ceil($totalProducts / $limit);
    
    // Requête pour récupérer les produits avec tri
    $validSortColumns = ['name', 'price', 'stock_quantity', 'created_at', 'category_name'];
    $validOrders = ['ASC', 'DESC'];
    
    if (!in_array($sort, $validSortColumns)) $sort = 'created_at';
    if (!in_array($order, $validOrders)) $order = 'DESC';
    
    $query = "SELECT p.*, c.name as category_name 
              FROM produits p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.deleted_at IS NULL";
    
    $queryParams = [];
    
    // Ajouter les mêmes conditions
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
        $queryParams = $countParams; // Copier les paramètres
    }
    
    $query .= " ORDER BY ";
    if ($sort === 'category_name') {
        $query .= "c.name";
    } else {
        $query .= "p." . $sort;
    }
    $query .= " " . $order . " LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    
    // Lier les paramètres de filtre
    foreach ($queryParams as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    
    // Lier les paramètres de pagination
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les catégories pour les filtres et select
    $stmt = $pdo->query("SELECT id, name FROM categories WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name ASC");
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

// Fonction pour construire les URLs avec filtres
function buildFilterUrl($newParams = []) {
    global $search, $category_filter, $status_filter, $price_min, $price_max, $stock_min, $stock_max, $date_from, $date_to, $sort, $order;
    
    $params = array_merge([
        'search' => $search,
        'category_filter' => $category_filter,
        'status_filter' => $status_filter,
        'price_min' => $price_min,
        'price_max' => $price_max,
        'stock_min' => $stock_min,
        'stock_max' => $stock_max,
        'date_from' => $date_from,
        'date_to' => $date_to,
        'sort' => $sort,
        'order' => $order
    ], $newParams);
    
    // Enlever les paramètres vides
    $params = array_filter($params, function($value) {
        return $value !== '';
    });
    
    return '?' . http_build_query($params);
}

$currentPage = 'products';
$pageSubtitle = 'Gestion des Produits';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <title>Gestion des Produits - ERP DYM Manufacture</title>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { 
            animation: fadeIn 0.3s ease-out; 
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .floating-label {
            transform: translateY(0.5rem);
            transition: all 0.3s ease;
        }
        
        .form-input:focus + .floating-label,
        .form-input:not(:placeholder-shown) + .floating-label {
            transform: translateY(-1.5rem) scale(0.85);
        }
        
        .drag-over {
            border-color: #3b82f6 !important;
            background-color: #dbeafe !important;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 min-h-screen" x-data="productsManager()">

    <!-- Navigation -->
    <?php include 'includes/header.php'; ?>

    <!-- Contenu principal -->
    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        
        <!-- En-tête avec statistiques -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="flex-1 min-w-0 mb-6 lg:mb-0">
                    <h2 class="text-3xl font-bold bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent">Gestion des Produits</h2>
                    <p class="mt-2 text-gray-600">Gérez votre catalogue de produits avec des filtres avancés</p>
                    
                    <!-- Statistiques rapides -->
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="glass-effect rounded-xl p-4 shadow-lg">
                            <div class="text-2xl font-bold text-blue-600"><?php echo $totalProducts; ?></div>
                            <div class="text-sm text-gray-600">Produits trouvés</div>
                        </div>
                        <div class="glass-effect rounded-xl p-4 shadow-lg">
                            <div class="text-2xl font-bold text-green-600">
                                <?php 
                                $activeProducts = array_filter($products, function($p) { return $p['is_active']; });
                                echo count($activeProducts);
                                ?>
                            </div>
                            <div class="text-sm text-gray-600">Produits actifs</div>
                        </div>
                        <div class="glass-effect rounded-xl p-4 shadow-lg">
                            <div class="text-2xl font-bold text-orange-600">
                                <?php 
                                $lowStock = array_filter($products, function($p) { return $p['stock_quantity'] < 10; });
                                echo count($lowStock);
                                ?>
                            </div>
                            <div class="text-sm text-gray-600">Stock faible</div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <button @click="toggleFilters()" 
                            class="inline-flex items-center px-6 py-3 border border-transparent rounded-xl shadow-lg text-sm font-medium text-white bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"></path>
                        </svg>
                        <span x-text="showFilters ? 'Masquer filtres' : 'Filtres avancés'"></span>
                    </button>
                    <button @click="openCreateModal()" 
                            class="inline-flex items-center px-6 py-3 border border-transparent rounded-xl shadow-lg text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Nouveau Produit
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtres avancés -->
        <div x-show="showFilters" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="mb-8">
            <div class="glass-effect rounded-2xl p-6 shadow-xl">
                <form method="GET" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Recherche textuelle -->
                        <div class="lg:col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Recherche globale</label>
                            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Rechercher par nom, description ou catégorie..." 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                        </div>
                        
                        <!-- Catégorie -->
                        <div>
                            <label for="category_filter" class="block text-sm font-medium text-gray-700 mb-2">Catégorie</label>
                            <select name="category_filter" id="category_filter" 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $category_filter === $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Statut -->
                        <div>
                            <label for="status_filter" class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                            <select name="status_filter" id="status_filter" 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                <option value="">Tous les statuts</option>
                                <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Actif</option>
                                <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Prix -->
                        <div>
                            <label for="price_min" class="block text-sm font-medium text-gray-700 mb-2">Prix minimum</label>
                            <input type="number" name="price_min" id="price_min" step="0.01" min="0" 
                                   value="<?php echo htmlspecialchars($price_min); ?>" 
                                   placeholder="0.00"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                        </div>
                        <div>
                            <label for="price_max" class="block text-sm font-medium text-gray-700 mb-2">Prix maximum</label>
                            <input type="number" name="price_max" id="price_max" step="0.01" min="0" 
                                   value="<?php echo htmlspecialchars($price_max); ?>" 
                                   placeholder="999.99"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                        </div>
                        
                        <!-- Stock -->
                        <div>
                            <label for="stock_min" class="block text-sm font-medium text-gray-700 mb-2">Stock minimum</label>
                            <input type="number" name="stock_min" id="stock_min" min="0" 
                                   value="<?php echo htmlspecialchars($stock_min); ?>" 
                                   placeholder="0"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                        </div>
                        <div>
                            <label for="stock_max" class="block text-sm font-medium text-gray-700 mb-2">Stock maximum</label>
                            <input type="number" name="stock_max" id="stock_max" min="0" 
                                   value="<?php echo htmlspecialchars($stock_max); ?>" 
                                   placeholder="999"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Dates -->
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">Date de création - Du</label>
                            <input type="date" name="date_from" id="date_from" 
                                   value="<?php echo htmlspecialchars($date_from); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                        </div>
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">Au</label>
                            <input type="date" name="date_to" id="date_to" 
                                   value="<?php echo htmlspecialchars($date_to); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <div class="flex space-x-3">
                            <button type="submit" 
                                    class="inline-flex items-center px-6 py-3 border border-transparent rounded-xl shadow-lg text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                Rechercher
                            </button>
                            <a href="?" 
                               class="inline-flex items-center px-6 py-3 border border-gray-300 rounded-xl shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Réinitialiser
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($error)): ?>
        <div class="mb-8 p-4 bg-red-50 border border-red-200 rounded-xl">
            <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>

        <!-- Tableau des produits -->
        <div class="glass-effect rounded-2xl overflow-hidden shadow-xl">
            <?php if (!empty($products)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50/50 backdrop-blur">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                <a href="<?php echo buildFilterUrl(['sort' => 'name', 'order' => ($sort === 'name' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>" 
                                   class="group inline-flex items-center hover:text-gray-900">
                                    Produit
                                    <?php if ($sort === 'name'): ?>
                                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <?php if ($order === 'ASC'): ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path>
                                            <?php else: ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"></path>
                                            <?php endif; ?>
                                        </svg>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                <a href="<?php echo buildFilterUrl(['sort' => 'category_name', 'order' => ($sort === 'category_name' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>" 
                                   class="group inline-flex items-center hover:text-gray-900">
                                    Catégorie
                                    <?php if ($sort === 'category_name'): ?>
                                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <?php if ($order === 'ASC'): ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path>
                                            <?php else: ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"></path>
                                            <?php endif; ?>
                                        </svg>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                <a href="<?php echo buildFilterUrl(['sort' => 'price', 'order' => ($sort === 'price' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>" 
                                   class="group inline-flex items-center hover:text-gray-900">
                                    Prix
                                    <?php if ($sort === 'price'): ?>
                                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <?php if ($order === 'ASC'): ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path>
                                            <?php else: ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"></path>
                                            <?php endif; ?>
                                        </svg>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                <a href="<?php echo buildFilterUrl(['sort' => 'stock_quantity', 'order' => ($sort === 'stock_quantity' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>" 
                                   class="group inline-flex items-center hover:text-gray-900">
                                    Stock
                                    <?php if ($sort === 'stock_quantity'): ?>
                                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <?php if ($order === 'ASC'): ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path>
                                            <?php else: ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"></path>
                                            <?php endif; ?>
                                        </svg>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Statut</th>
                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                <a href="<?php echo buildFilterUrl(['sort' => 'created_at', 'order' => ($sort === 'created_at' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>" 
                                   class="group inline-flex items-center hover:text-gray-900">
                                    Date
                                    <?php if ($sort === 'created_at'): ?>
                                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <?php if ($order === 'ASC'): ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path>
                                            <?php else: ?>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"></path>
                                            <?php endif; ?>
                                        </svg>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white/30 divide-y divide-gray-200">
                        <?php foreach ($products as $product): ?>
                        <tr class="hover:bg-white/50 animate-fade-in transition-all duration-200">
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-4">
                                    <?php 
                                    $mainImage = getMainImage($product['images']);
                                    if ($mainImage): 
                                    ?>
                                    <div class="flex-shrink-0 w-16 h-16">
                                        <img src="../<?php echo htmlspecialchars($mainImage['path']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             class="w-16 h-16 object-cover rounded-lg border border-gray-200">
                                    </div>
                                    <?php else: ?>
                                    <div class="flex-shrink-0 w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-semibold text-gray-900 truncate">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-600 max-w-xs truncate">
                                            <?php echo htmlspecialchars($product['description'] ?? 'Aucune description'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gradient-to-r from-blue-100 to-indigo-100 text-blue-800 border border-blue-200">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'Sans catégorie'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900">
                                    <?php echo number_format($product['price'], 2, ',', ' '); ?> FCFA
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $product['stock_quantity'] > 10 ? 'bg-green-100 text-green-800 border border-green-200' : ($product['stock_quantity'] > 0 ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' : 'bg-red-100 text-red-800 border border-red-200'); ?>">
                                    <?php echo $product['stock_quantity']; ?> unité<?php echo $product['stock_quantity'] > 1 ? 's' : ''; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $product['is_active'] ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>">
                                    <?php echo $product['is_active'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo date('d/m/Y', strtotime($product['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">
                                    <button @click="openShowModal('<?php echo $product['id']; ?>')" 
                                            class="text-blue-600 hover:text-blue-900 transition duration-200 p-2 rounded-lg hover:bg-blue-50" title="Voir">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </button>
                                    <button @click="openEditModal('<?php echo $product['id']; ?>')" 
                                            class="text-indigo-600 hover:text-indigo-900 transition duration-200 p-2 rounded-lg hover:bg-indigo-50" title="Modifier">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button @click="openDeleteModal('<?php echo $product['id']; ?>', '<?php echo htmlspecialchars($product['name']); ?>')" 
                                            class="text-red-600 hover:text-red-900 transition duration-200 p-2 rounded-lg hover:bg-red-50" title="Supprimer">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

            <!-- Pagination améliorée -->
            <?php if ($totalPages > 1): ?>
            <div class="bg-white/50 px-6 py-4 flex items-center justify-between border-t border-gray-200">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                    <a href="<?php echo buildFilterUrl(['page' => $page - 1]); ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Précédent
                    </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                    <a href="<?php echo buildFilterUrl(['page' => $page + 1]); ?>" 
                       class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Suivant
                    </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Affichage de <span class="font-medium"><?php echo ($offset + 1); ?></span> à <span class="font-medium"><?php echo min($offset + $limit, $totalProducts); ?></span>
                            sur <span class="font-medium"><?php echo $totalProducts; ?></span> résultats
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                            <a href="<?php echo buildFilterUrl(['page' => $page - 1]); ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="<?php echo buildFilterUrl(['page' => $i]); ?>" 
                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $page ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <a href="<?php echo buildFilterUrl(['page' => $page + 1]); ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="text-center py-16">
                <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">Aucun produit trouvé</h3>
                <p class="mt-2 text-gray-500">
                    <?php echo !empty($search) || !empty($category_filter) || !empty($status_filter) || !empty($price_min) || !empty($price_max) || !empty($stock_min) || !empty($stock_max) || !empty($date_from) || !empty($date_to) ? 'Aucun résultat ne correspond à vos critères de recherche.' : 'Commencez par créer votre premier produit.'; ?>
                </p>
                <div class="mt-6 flex justify-center space-x-3">
                    <?php if (!empty($search) || !empty($category_filter) || !empty($status_filter) || !empty($price_min) || !empty($price_max) || !empty($stock_min) || !empty($stock_max) || !empty($date_from) || !empty($date_to)): ?>
                    <a href="?" 
                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Réinitialiser filtres
                    </a>
                    <?php else: ?>
                    <button @click="openCreateModal()" 
                            class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Nouveau Produit
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Créer/Éditer avec design amélioré -->
    <div x-show="showFormModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 backdrop-blur-sm transition-opacity"></div>

            <div class="inline-block align-bottom glass-effect rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                
                <div @submit.prevent="submitForm()">
                    <div class="px-6 pt-6 pb-4">
                        <div class="flex items-center mb-6">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg mr-4">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent" x-text="isEditing ? 'Modifier le produit' : 'Nouveau produit'"></h3>
                                <p class="text-gray-600 mt-1">Remplissez les informations du produit</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Colonne gauche -->
                            <div class="space-y-6">
                                <!-- Nom -->
                                <div class="relative">
                                    <input type="text" id="product-name" x-model="formData.name" required
                                           placeholder=" "
                                           class="peer w-full px-4 py-4 border-2 border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-0 focus:border-blue-500 transition-all duration-200 form-input">
                                    <label for="product-name" class="floating-label absolute left-4 text-gray-600 font-medium pointer-events-none">
                                        Nom du produit *
                                    </label>
                                </div>

                                <!-- Description -->
                                <div class="relative">
                                    <textarea id="product-description" x-model="formData.description" rows="4"
                                              placeholder=" "
                                              class="peer w-full px-4 py-4 border-2 border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-0 focus:border-blue-500 transition-all duration-200 resize-none form-input"></textarea>
                                    <label for="product-description" class="floating-label absolute left-4 text-gray-600 font-medium pointer-events-none">
                                        Description du produit
                                    </label>
                                </div>

                                <!-- Catégorie -->
                                <div class="relative">
                                    <select id="product-category" x-model="formData.category_id" required
                                            class="w-full px-4 py-4 border-2 border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-0 focus:border-blue-500 transition-all duration-200 bg-white">
                                        <option value="">Sélectionner une catégorie</option>
                                        <template x-for="category in categories" :key="category.id">
                                            <option :value="category.id" x-text="category.name"></option>
                                        </template>
                                    </select>
                                    <label for="product-category" class="absolute left-4 -top-2 text-sm text-gray-600 font-medium bg-white px-2">
                                        Catégorie *
                                    </label>
                                </div>

                                <!-- Prix -->
                                <div class="relative">
                                    <input type="number" id="product-price" x-model="formData.price" step="0.01" min="0" required
                                           placeholder=" "
                                           class="peer w-full px-4 py-4 pr-12 border-2 border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-0 focus:border-blue-500 transition-all duration-200 form-input">
                                    <label for="product-price" class="floating-label absolute left-4 text-gray-600 font-medium pointer-events-none">
                                        Prix *
                                    </label>
                                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                        <span class="text-gray-500 font-medium">FCFA</span>
                                    </div>
                                </div>

                                <!-- Stock -->
                                <div class="relative">
                                    <input type="number" id="product-stock" x-model="formData.stock_quantity" min="0" required
                                           placeholder=" "
                                           class="peer w-full px-4 py-4 border-2 border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-0 focus:border-blue-500 transition-all duration-200 form-input">
                                    <label for="product-stock" class="floating-label absolute left-4 text-gray-600 font-medium pointer-events-none">
                                        Quantité en stock *
                                    </label>
                                </div>
                            </div>

                            <!-- Colonne droite -->
                            <div class="space-y-6">
                                <!-- Images du produit -->
                                <div class="relative">
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Images du produit</label>
                                    
                                    <!-- Zone de drop pour les images -->
                                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-500 transition-colors duration-200"
                                         x-data="{ dragOver: false }"
                                         @dragover.prevent="dragOver = true"
                                         @dragleave.prevent="dragOver = false"
                                         @drop.prevent="dragOver = false; handleImageDrop($event)"
                                         :class="{ 'border-blue-500 bg-blue-50': dragOver }">
                                        
                                        <input type="file" id="product-images" name="images[]" multiple accept="image/*"
                                               @change="handleImageSelect($event)"
                                               class="hidden">
                                        
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        
                                        <p class="mt-2 text-sm text-gray-600">
                                            <label for="product-images" class="font-medium text-blue-600 hover:text-blue-500 cursor-pointer">
                                                Cliquez pour sélectionner
                                            </label>
                                            ou glissez-déposez vos images
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">PNG, JPG, GIF jusqu'à 5MB chacune</p>
                                    </div>
                                    
                                    <!-- Prévisualisation des images -->
                                    <div x-show="selectedImages.length > 0 || (isEditing && formData.images)" class="mt-4">
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                            <!-- Images existantes en mode édition -->
                                             <template x-if="isEditing && formData.images">
                                            <template x-for="(image, index) in getImagesArray(formData.images)" :key="index">
                                                <div class="relative group">
                                                    <img :src="'../' + image.path" :alt="image.original_name" 
                                                         class="w-full h-24 object-cover rounded-lg border-2"
                                                         :class="image.is_main ? 'border-blue-500' : 'border-gray-200'">
                                                    
                                                    <!-- Badge image principale -->
                                                    <div x-show="image.is_main" class="absolute top-1 left-1">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                                            Principale
                                                        </span>
                                                    </div>
                                                    
                                                    <!-- Actions -->
                                                    <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <div class="flex space-x-1">
                                                            <button type="button" @click="setMainImage(index)" 
                                                                    x-show="!image.is_main"
                                                                    class="p-1 bg-white rounded-full shadow-lg hover:bg-blue-50"
                                                                    title="Définir comme principale">
                                                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                                                </svg>
                                                            </button>
                                                            <button type="button" @click="removeExistingImage(index)"
                                                                    class="p-1 bg-white rounded-full shadow-lg hover:bg-red-50"
                                                                    title="Supprimer">
                                                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            </template>
                                            
                                            <!-- Nouvelles images sélectionnées -->
                                            <template x-for="(image, index) in selectedImages" :key="'new-' + index">
                                                <div class="relative group">
                                                    <img :src="image.preview" :alt="image.file.name" 
                                                         class="w-full h-24 object-cover rounded-lg border-2 border-gray-200">
                                                    
                                                    <!-- Badge nouvelle image -->
                                                    <div class="absolute top-1 left-1">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                                            Nouvelle
                                                        </span>
                                                    </div>
                                                    
                                                    <!-- Action supprimer -->
                                                    <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <button type="button" @click="removeSelectedImage(index)"
                                                                class="p-1 bg-white rounded-full shadow-lg hover:bg-red-50"
                                                                title="Supprimer">
                                                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Statut -->
                                <div class="flex items-center p-4 bg-gray-50 rounded-xl">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" id="product-active" x-model="formData.is_active"
                                               class="w-5 h-5 text-indigo-600 border-2 border-gray-300 rounded focus:ring-indigo-500 focus:ring-2">
                                    </div>
                                    <div class="ml-3">
                                        <label for="product-active" class="font-medium text-gray-900">
                                            Produit actif
                                        </label>
                                        <p class="text-sm text-gray-500">Le produit sera visible dans le catalogue</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50/50 px-6 py-4 flex justify-end space-x-3">
                        <button type="button" @click="closeFormModal()"
                                class="px-6 py-3 border border-gray-300 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                            Annuler
                        </button>
                        <button type="button" @click="submitForm()" :disabled="loading"
                                class="px-6 py-3 border border-transparent rounded-xl shadow-lg text-sm font-medium text-white bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 transition-all duration-200">
                            <span x-show="!loading" class="flex items-center">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span x-text="isEditing ? 'Modifier' : 'Créer'"></span>
                            </span>
                            <span x-show="loading" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Traitement...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Affichage -->
    <div x-show="showViewModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 backdrop-blur-sm transition-opacity"></div>

            <div class="inline-block align-bottom glass-effect rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="px-6 pt-6 pb-4">
                    <div class="flex items-center mb-6">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg mr-4">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent">Détails du produit</h3>
                            <p class="text-gray-600 mt-1">Informations complètes du produit</p>
                        </div>
                    </div>
                    
                    <div x-show="viewData" class="space-y-6">
    <!-- Images du produit - Version corrigée -->
    <template x-if="viewData && viewData.images && viewData.images !== 'null' && viewData.images !== ''">
        <div class="glass-effect p-4 rounded-xl">
            <label class="block text-sm font-semibold text-gray-700 mb-4">Images du produit</label>
            
            <!-- Container pour les images avec Alpine data local -->
            <div x-data="{ 
                currentImageIndex: 0,
                get imagesList() {
                    try {
                        if (!viewData || !viewData.images) return [];
                        const images = typeof viewData.images === 'string' ? JSON.parse(viewData.images) : viewData.images;
                        return Array.isArray(images) ? images : [];
                    } catch (e) {
                        console.error('Erreur parsing images:', e);
                        return [];
                    }
                }
            }" class="space-y-4">
                
                <!-- Image principale -->
                <div class="relative" x-show="imagesList.length > 0">
                    <template x-for="(image, index) in imagesList" :key="index">
                        <img x-show="index === currentImageIndex" 
                             :src="'../'+ image.path" 
                             :alt="image.original_name || 'Image produit'"
                             class="w-full h-64 object-cover rounded-lg border border-gray-200"
                             @error="console.error('Erreur chargement image:', $event.target.src)">
                    </template>
                    
                    <!-- Boutons de navigation -->
                    <template x-if="imagesList.length > 1">
                        <div>
                            <button @click="currentImageIndex = currentImageIndex > 0 ? currentImageIndex - 1 : imagesList.length - 1"
                                    class="absolute left-2 top-1/2 transform -translate-y-1/2 p-2 bg-black bg-opacity-50 text-white rounded-full hover:bg-opacity-70 focus:outline-none">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                            </button>
                            <button @click="currentImageIndex = currentImageIndex < imagesList.length - 1 ? currentImageIndex + 1 : 0"
                                    class="absolute right-2 top-1/2 transform -translate-y-1/2 p-2 bg-black bg-opacity-50 text-white rounded-full hover:bg-opacity-70 focus:outline-none">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
                
                <!-- Miniatures -->
                <template x-if="imagesList.length > 1">
                    <div class="flex space-x-2 overflow-x-auto pb-2">
                        <template x-for="(image, index) in imagesList" :key="'thumb-' + index">
                            <button @click="currentImageIndex = index"
                                    class="flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 transition-all focus:outline-none"
                                    :class="index === currentImageIndex ? 'border-blue-500' : 'border-gray-200'">
                                <img :src="'../' + image.path" 
                                     :alt="image.original_name || 'Miniature'"
                                     class="w-full h-full object-cover"
                                     @error="console.error('Erreur miniature:', $event.target.src)">
                            </button>
                        </template>
                    </div>
                </template>
                
                <!-- Message si aucune image trouvée -->
                <div x-show="imagesList.length === 0" class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <p class="text-gray-500">Images non disponibles</p>
                </div>
            </div>
        </div>
    </template>
    
    <!-- Message si pas d'images du tout -->
    <template x-if="!viewData || !viewData.images || viewData.images === 'null' || viewData.images === ''">
        <div class="glass-effect p-4 rounded-xl text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <p class="text-gray-500">Aucune image disponible pour ce produit</p>
        </div>
    </template>
    
    <!-- Informations textuelles (gardez le code existant) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Colonne gauche -->
        <div class="space-y-6">
            <div class="glass-effect p-4 rounded-xl">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Nom</label>
                <p class="text-lg text-gray-900 font-medium" x-text="viewData?.name || 'Non défini'"></p>
            </div>

            <div class="glass-effect p-4 rounded-xl" x-show="viewData?.description">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <p class="text-gray-900" x-text="viewData?.description"></p>
            </div>

            <div class="glass-effect p-4 rounded-xl">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Catégorie</label>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gradient-to-r from-blue-100 to-indigo-100 text-blue-800 border border-blue-200" x-text="viewData?.category_name || 'Sans catégorie'"></span>
            </div>
        </div>

        <!-- Colonne droite -->
        <div class="space-y-6">
            <div class="glass-effect p-4 rounded-xl">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Prix</label>
                <p class="text-2xl text-gray-900 font-bold" x-text="viewData?.price ? parseFloat(viewData.price).toFixed(2) + ' FCFA' : 'Non défini'"></p>
            </div>

            <div class="glass-effect p-4 rounded-xl">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Stock</label>
                <div class="flex items-center space-x-3">
                    <p class="text-xl text-gray-900 font-semibold" x-text="viewData?.stock_quantity || 0"></p>
                    <span class="text-gray-600">unité<span x-text="(viewData?.stock_quantity || 0) > 1 ? 's' : ''"></span></span>
                </div>
            </div>

            <div class="glass-effect p-4 rounded-xl">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Statut</label>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                      :class="(viewData?.is_active == 1) ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'"
                      x-text="(viewData?.is_active == 1) ? 'Actif' : 'Inactif'"></span>
            </div>

            <div class="glass-effect p-4 rounded-xl">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Date de création</label>
                <p class="text-gray-600" x-text="viewData?.created_at ? new Date(viewData.created_at).toLocaleDateString('fr-FR') : 'Non définie'"></p>
            </div>
        </div>
    </div>
</div>

                </div>
                
                <div class="bg-gray-50/50 px-6 py-4 flex justify-end">
                    <button @click="closeViewModal()"
                            class="px-6 py-3 border border-gray-300 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                        Fermer
                    </button>
                </div>
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
                                    Êtes-vous sûr de vouloir supprimer le produit "<span x-text="deleteItem.name" class="font-medium"></span>" ? 
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
        <div class="glass-effect rounded-xl p-4 shadow-2xl" :class="notification.type === 'success' ? 'border-l-4 border-green-500' : 'border-l-4 border-red-500'">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg x-show="notification.type === 'success'" class="h-6 w-6 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <svg x-show="notification.type === 'error'" class="h-6 w-6 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-semibold" :class="notification.type === 'success' ? 'text-green-800' : 'text-red-800'" 
                       x-text="notification.message"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function productsManager() {
            return {
                showFormModal: false,
                showViewModal: false,
                showDeleteModal: false,
                showFilters: false,
                isEditing: false,
                loading: false,
                categories: <?php echo json_encode($categories); ?>,
                selectedImages: [],
                imagesToRemove: [],
                formData: {
                    id: '',
                    name: '',
                    description: '',
                    price: '',
                    stock_quantity: 0,
                    category_id: '',
                    is_active: true,
                    images: null
                },
                viewData: null,
                deleteItem: {
                    id: '',
                    name: ''
                },
                notification: {
                    show: false,
                    type: '',
                    message: ''
                },

                init() {
                    // Charger les catégories au démarrage
                    this.loadCategories();
                },

                toggleFilters() {
                    this.showFilters = !this.showFilters;
                },

                async loadCategories() {
                    try {
                        const response = await this.makeRequest('get_categories', {});
                        if (response.success) {
                            this.categories = response.data;
                        }
                    } catch (error) {
                        console.error('Erreur lors du chargement des catégories:', error);
                    }
                },

                // Gestion des images
                handleImageSelect(event) {
                    this.addImages(event.target.files);
                },

                handleImageDrop(event) {
                    this.addImages(event.dataTransfer.files);
                },

                addImages(files) {
                    const maxFiles = 5;
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    
                    Array.from(files).forEach(file => {
                        if (this.selectedImages.length >= maxFiles) {
                            this.showNotification('error', `Maximum ${maxFiles} images autorisées`);
                            return;
                        }
                        
                        if (file.size > maxSize) {
                            this.showNotification('error', `${file.name} est trop volumineux (max 5MB)`);
                            return;
                        }
                        
                        if (!allowedTypes.includes(file.type)) {
                            this.showNotification('error', `${file.name} n'est pas un format d'image autorisé`);
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.selectedImages.push({
                                file: file,
                                preview: e.target.result
                            });
                        };
                        reader.readAsDataURL(file);
                    });
                },

                removeSelectedImage(index) {
                    this.selectedImages.splice(index, 1);
                },

                removeExistingImage(index) {
                    if (!this.imagesToRemove.includes(index)) {
                        this.imagesToRemove.push(index);
                    }
                    // Marquer l'image comme supprimée visuellement
                    if (this.formData.images) {
                        let images = this.getImagesArray(this.formData.images);
                        images.splice(index, 1);
                        this.formData.images = JSON.stringify(images);
                    }
                },

                setMainImage(index) {
                    if (this.formData.images) {
                        let images = this.getImagesArray(this.formData.images);
                        images.forEach((img, i) => {
                            img.is_main = (i === index);
                        });
                        this.formData.images = JSON.stringify(images);
                    }
                },

                getImagesArray(images) {
                    if (!images) return [];
                    return typeof images === 'string' ? JSON.parse(images) : images;
                },

                openCreateModal() {
                    this.isEditing = false;
                    this.selectedImages = [];
                    this.imagesToRemove = [];
                    this.formData = {
                        id: '',
                        name: '',
                        description: '',
                        price: '',
                        stock_quantity: 0,
                        category_id: '',
                        is_active: true,
                        images: null
                    };
                    this.showFormModal = true;
                },

                async openEditModal(id) {
                    this.loading = true;
                    try {
                        const response = await this.makeRequest('get', { id });
                        if (response.success) {
                            this.isEditing = true;
                            this.selectedImages = [];
                            this.imagesToRemove = [];
                            this.formData = {
                                id: response.data.id,
                                name: response.data.name,
                                description: response.data.description || '',
                                price: response.data.price,
                                stock_quantity: response.data.stock_quantity,
                                category_id: response.data.category_id,
                                is_active: response.data.is_active == 1,
                                images: response.data.images
                            };
                            this.showFormModal = true;
                        } else {
                            this.showNotification('error', response.message);
                        }
                    } catch (error) {
                        this.showNotification('error', 'Erreur lors du chargement du produit');
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

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    return await response.json();
                },

                showNotification(type, message) {
                    this.notification = { show: true, type, message };
                    setTimeout(() => {
                        this.notification.show = false;
                    }, 5000);
                },

                async openShowModal(id) {
                    this.loading = true;
                    try {
                        const response = await this.makeRequest('get', { id });
                        if (response.success) {
                            this.viewData = response.data;
                            this.showViewModal = true;
                        } else {
                            this.showNotification('error', response.message);
                        }
                    } catch (error) {
                        this.showNotification('error', 'Erreur lors du chargement du produit');
                    } finally {
                        this.loading = false;
                    }
                },

                openDeleteModal(id, name) {
                    this.deleteItem = { id, name };
                    this.showDeleteModal = true;
                },

                closeFormModal() {
                    this.showFormModal = false;
                },

                closeViewModal() {
                    this.showViewModal = false;
                    this.viewData = null;
                },

                async submitForm() {
                    if (!this.formData.name.trim()) {
                        this.showNotification('error', 'Le nom du produit est obligatoire');
                        return;
                    }

                    if (!this.formData.price || parseFloat(this.formData.price) <= 0) {
                        this.showNotification('error', 'Le prix doit être supérieur à 0');
                        return;
                    }

                    if (!this.formData.category_id) {
                        this.showNotification('error', 'Veuillez sélectionner une catégorie');
                        return;
                    }

                    this.loading = true;
                    try {
                        const formData = new FormData();
                        const action = this.isEditing ? 'update' : 'create';
                        formData.append('action', action);
                        
                        // Ajouter les données du formulaire
                        for (const key in this.formData) {
                           
                            if (key === 'is_active') {
                                 
                                if (this.formData[key]) formData.append(key, true);
                            } else if (key !== 'images') {
                                formData.append(key, this.formData[key]);
                            }
                        }
                        
                        // Ajouter les nouvelles images
                        this.selectedImages.forEach(imageData => {
                            formData.append('images[]', imageData.file);
                        });
                        
                        // Ajouter les images à supprimer
                        if (this.imagesToRemove.length > 0) {
                            formData.append('remove_images', JSON.stringify(this.imagesToRemove));
                        }
                        
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        const data = await response.json();
                        
                        if (data.success) {
                            this.showNotification('success', data.message);
                            this.showFormModal = false;
                            // setTimeout(() => window.location.reload(), 1000);
                        } else {
                            this.showNotification('error', data.message);
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
            }
        }

        // Initialiser les filtres au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus sur le champ de recherche si les filtres sont visibles
            const searchInput = document.getElementById('search');
            if (searchInput && searchInput.value) {
                // Si il y a une recherche, montrer les filtres par défaut
                const filtersToggle = document.querySelector('[\\@click="toggleFilters()"]');
                if (filtersToggle) {
                    // Déclencher l'affichage des filtres si une recherche est active
                    setTimeout(() => {
                        const hasActiveFilters = window.location.search.includes('search=') || 
                                               window.location.search.includes('category_filter=') ||
                                               window.location.search.includes('status_filter=') ||
                                               window.location.search.includes('price_min=') ||
                                               window.location.search.includes('price_max=') ||
                                               window.location.search.includes('stock_min=') ||
                                               window.location.search.includes('stock_max=') ||
                                               window.location.search.includes('date_from=') ||
                                               window.location.search.includes('date_to=');
                        
                        if (hasActiveFilters) {
                            // Forcer l'affichage des filtres si des filtres sont actifs
                            Alpine.store('filters', { show: true });
                        }
                    }, 100);
                }
            }
        });
    </script>
</body>
</html>