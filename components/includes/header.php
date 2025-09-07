<?php
/**
 * Header uniforme avec menu hamburger pour toutes les pages PHP
 * À inclure dans chaque fichier PHP après avoir défini $currentUser et $currentPage
 */

// Variables par défaut si non définies
$currentPage = $currentPage ?? '';
$pageSubtitle = $pageSubtitle ?? 'Système ERP';
?>

<!-- Navigation uniforme -->
<nav class="bg-white shadow-lg border-b" x-data="{ mobileMenuOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
            <!-- Logo et titre -->
            <div class="flex items-center">
                <div class="flex-shrink-0 flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-xl">D</span>
                    </div>
                    <div class="ml-3">
                        <h1 class="text-xl font-semibold text-gray-900">DYM Manufacture</h1>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($pageSubtitle); ?></p>
                    </div>
                </div>
            </div>

            <!-- Menu navigation desktop -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="./dashboard.php"
                   class="<?php echo $currentPage === 'dashboard' ? 'text-blue-600 hover:text-blue-500 font-bold relative' : 'text-gray-700 hover:text-blue-600 transition duration-200 font-medium'; ?>">
                    Dashboard
                    <?php if ($currentPage === 'dashboard'): ?>
                    <span class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-blue-500 to-purple-500"></span>
                    <?php endif; ?>
                </a>
                <a href="./categories.php"
                   class="<?php echo $currentPage === 'categories' ? 'text-blue-600 hover:text-blue-500 font-bold relative' : 'text-gray-700 hover:text-blue-600 transition duration-200 font-medium'; ?>">
                    Catégories
                    <?php if ($currentPage === 'categories'): ?>
                    <span class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-blue-500 to-purple-500"></span>
                    <?php endif; ?>
                </a>
                <a href="./produit.php"
                   class="<?php echo $currentPage === 'products' ? 'text-blue-600 hover:text-blue-500 font-bold relative' : 'text-gray-700 hover:text-blue-600 transition duration-200 font-medium'; ?>">
                    Produits
                    <?php if ($currentPage === 'products'): ?>
                    <span class="absolute bottom-0 left-0 w-full h-0.5 bg-gradient-to-r from-blue-500 to-purple-500"></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Actions à droite -->
            <div class="flex items-center space-x-4">
                <!-- Menu hamburger mobile -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" 
                        class="md:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                        :class="{ 'bg-gray-100': mobileMenuOpen }">
                    <span class="sr-only">Ouvrir le menu principal</span>
                    <!-- Icône hamburger -->
                    <svg x-show="!mobileMenuOpen" class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <!-- Icône X -->
                    <svg x-show="mobileMenuOpen" class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Profil utilisateur -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center space-x-3 text-gray-700 hover:text-gray-900 focus:outline-none">
                        <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-medium">
                                <?php echo strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1)); ?>
                            </span>
                        </div>
                        <div class="hidden sm:block text-left">
                            <p class="text-sm font-medium"><?php echo htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')); ?></p>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></p>
                        </div>
                        <svg class="w-4 h-4 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <!-- Menu déroulant profil -->
                    <div x-show="open" @click.away="open = false"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50"
                         style="display: none;">
                        <!-- Infos utilisateur sur mobile -->
                        <div class="px-4 py-2 text-sm text-gray-500 border-b border-gray-100 sm:hidden">
                            <p class="font-medium"><?php echo htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')); ?></p>
                            <p class="text-xs"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></p>
                        </div>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profil</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Paramètres</a>
                        <div class="border-t border-gray-100"></div>
                        <form method="POST" class="block">
                            <button type="submit" name="logout" class="w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                Se déconnecter
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Menu mobile -->
    <div x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform -translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform -translate-y-2"
         class="md:hidden border-t border-gray-200 bg-white"
         style="display: none;">
        <div class="px-2 pt-2 pb-3 space-y-1">
            <a href="./dashboard.php"
               class="<?php echo $currentPage === 'dashboard' ? 'bg-blue-50 border-blue-500 text-blue-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900 block pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium'; ?>">
                Dashboard
            </a>
            <a href="./categories.php"
               class="<?php echo $currentPage === 'categories' ? 'bg-blue-50 border-blue-500 text-blue-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900 block pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium'; ?>">
                Catégories
            </a>
            <a href="./produit.php"
               class="<?php echo $currentPage === 'products' ? 'bg-blue-50 border-blue-500 text-blue-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900 block pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium'; ?>">
                Produits
            </a>
        </div>
    </div>
</nav>

<script>
// Script pour gérer la fermeture du menu mobile lors du clic sur un lien
document.addEventListener('DOMContentLoaded', function() {
    const mobileLinks = document.querySelectorAll('[x-data] a[href]');
    mobileLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Fermer le menu mobile
            const nav = document.querySelector('[x-data*="mobileMenuOpen"]');
            if (nav && nav.__x) {
                nav.__x.$data.mobileMenuOpen = false;
            }
        });
    });
});
</script>