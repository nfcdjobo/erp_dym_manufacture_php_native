-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3312
-- Généré le : sam. 06 sep. 2025 à 22:02
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `erp_dym_manufacture`
--

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `user_id` char(36) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `name`, `is_active`, `description`, `user_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
('07be49c5-0955-4a1d-be6a-12e9f8b50909', 'SPORT', 1, 'dffdfd fdfdfd', 'da939faa-d53d-4923-a91d-3021221d96fb', '2025-09-05 22:03:49', '2025-09-06 20:00:18', NULL),
('14d95600-8775-4382-b10c-6d7b70f07a64', 'INFORMATIQUES', 1, 'Electro', 'da939faa-d53d-4923-a91d-3021221d96fb', '2025-09-05 19:33:15', '2025-09-06 19:59:56', NULL),
('1672a924-1f82-4620-a81b-9ffbca44f544', 'MODE', 1, '', 'da939faa-d53d-4923-a91d-3021221d96fb', '2025-09-05 22:03:22', '2025-09-06 20:00:06', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2014_10_12_100000_create_password_reset_tokens_table', 1),
(3, '2019_08_19_000000_create_failed_jobs_table', 1),
(4, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(5, '2025_09_01_155636_create_categories_table', 1),
(6, '2025_09_01_155711_create_produits_table', 1);

-- --------------------------------------------------------

--
-- Structure de la table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produits`
--

CREATE TABLE `produits` (
  `id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `category_id` char(36) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `produits`
--

INSERT INTO `produits` (`id`, `name`, `description`, `images`, `price`, `stock_quantity`, `category_id`, `is_active`, `deleted_at`, `created_at`, `updated_at`) VALUES
('351bac5a-d526-403e-8ba5-6ee408c0c73f', 'Ordinateur Portable', 'Ordi', '[{\"filename\":\"351bac5a-d526-403e-8ba5-6ee408c0c73f_68bc9203d004e.jpg\",\"original_name\":\"IMG-20231002-WA0018.jpg\",\"is_main\":false,\"path\":\"uploads\\/products\\/351bac5a-d526-403e-8ba5-6ee408c0c73f_68bc9203d004e.jpg\"},{\"filename\":\"351bac5a-d526-403e-8ba5-6ee408c0c73f_68bc9203ef56c.jpg\",\"original_name\":\"IMG-20231002-WA0019.jpg\",\"is_main\":false,\"path\":\"uploads\\/products\\/351bac5a-d526-403e-8ba5-6ee408c0c73f_68bc9203ef56c.jpg\"},{\"filename\":\"351bac5a-d526-403e-8ba5-6ee408c0c73f_68bc920417041.jpg\",\"original_name\":\"IMG-20231002-WA0026.jpg\",\"is_main\":false,\"path\":\"uploads\\/products\\/351bac5a-d526-403e-8ba5-6ee408c0c73f_68bc920417041.jpg\"},{\"filename\":\"351bac5a-d526-403e-8ba5-6ee408c0c73f_68bc92043a6a4.jpg\",\"original_name\":\"IMG-20231002-WA0028.jpg\",\"is_main\":false,\"path\":\"uploads\\/products\\/351bac5a-d526-403e-8ba5-6ee408c0c73f_68bc92043a6a4.jpg\"},{\"filename\":\"351bac5a-d526-403e-8ba5-6ee408c0c73f_68bc920455843.jpg\",\"original_name\":\"IMG-20231002-WA0030.jpg\",\"is_main\":true,\"path\":\"uploads\\/products\\/351bac5a-d526-403e-8ba5-6ee408c0c73f_68bc920455843.jpg\"}]', 850000.00, 153, '14d95600-8775-4382-b10c-6d7b70f07a64', 1, NULL, '2025-09-05 19:34:31', '2025-09-06 19:57:05'),
('91fe8cfe-84c9-45f6-a92d-949feff225d4', 'Chemise manche courte', 'df dfdddfd', '[{\"filename\":\"91fe8cfe-84c9-45f6-a92d-949feff225d4_68bc912883750.webp\",\"original_name\":\"chemise-100-coton-chemise-a-manches-courtes-oxfor (1).webp\",\"is_main\":false,\"path\":\"uploads\\/products\\/91fe8cfe-84c9-45f6-a92d-949feff225d4_68bc912883750.webp\"},{\"filename\":\"91fe8cfe-84c9-45f6-a92d-949feff225d4_68bc9128a0a74.webp\",\"original_name\":\"chemise-100-coton-chemise-a-manches-courtes-oxfor (2).webp\",\"is_main\":false,\"path\":\"uploads\\/products\\/91fe8cfe-84c9-45f6-a92d-949feff225d4_68bc9128a0a74.webp\"},{\"filename\":\"91fe8cfe-84c9-45f6-a92d-949feff225d4_68bc9128c01f8.webp\",\"original_name\":\"chemise-100-coton-chemise-a-manches-courtes-oxfor (3).webp\",\"is_main\":false,\"path\":\"uploads\\/products\\/91fe8cfe-84c9-45f6-a92d-949feff225d4_68bc9128c01f8.webp\"},{\"filename\":\"91fe8cfe-84c9-45f6-a92d-949feff225d4_68bc9128e294c.webp\",\"original_name\":\"chemise-100-coton-chemise-a-manches-courtes-oxfor.webp\",\"is_main\":true,\"path\":\"uploads\\/products\\/91fe8cfe-84c9-45f6-a92d-949feff225d4_68bc9128e294c.webp\"}]', 2500.00, 1500, '1672a924-1f82-4620-a81b-9ffbca44f544', 1, NULL, '2025-09-05 22:05:30', '2025-09-06 19:53:29'),
('9fcf062a-a2e5-4ccb-92ef-402ad60194ea', 'Chaussures', 'dfdfd dfd', '[{\"filename\":\"9fcf062a-a2e5-4ccb-92ef-402ad60194ea_68bc87f988360.webp\",\"original_name\":\"casual-chaussure-homme-basket-homme-respirant-d (1).webp\",\"is_main\":false,\"path\":\"uploads\\/products\\/9fcf062a-a2e5-4ccb-92ef-402ad60194ea_68bc87f988360.webp\"},{\"filename\":\"9fcf062a-a2e5-4ccb-92ef-402ad60194ea_68bc87f988c75.webp\",\"original_name\":\"casual-chaussure-homme-basket-homme-respirant-d (2).webp\",\"is_main\":false,\"path\":\"uploads\\/products\\/9fcf062a-a2e5-4ccb-92ef-402ad60194ea_68bc87f988c75.webp\"},{\"filename\":\"9fcf062a-a2e5-4ccb-92ef-402ad60194ea_68bc87f989222.webp\",\"original_name\":\"casual-chaussure-homme-basket-homme-respirant-d (3).webp\",\"is_main\":false,\"path\":\"uploads\\/products\\/9fcf062a-a2e5-4ccb-92ef-402ad60194ea_68bc87f989222.webp\"},{\"filename\":\"9fcf062a-a2e5-4ccb-92ef-402ad60194ea_68bc87f9896db.webp\",\"original_name\":\"casual-chaussure-homme-basket-homme-respirant-d.webp\",\"is_main\":true,\"path\":\"uploads\\/products\\/9fcf062a-a2e5-4ccb-92ef-402ad60194ea_68bc87f9896db.webp\"},{\"filename\":\"9fcf062a-a2e5-4ccb-92ef-402ad60194ea_68bc90950b68d.webp\",\"original_name\":\"chemise-100-coton-chemise-a-manches-courtes-oxfor (1).webp\",\"is_main\":false,\"path\":\"uploads\\/products\\/9fcf062a-a2e5-4ccb-92ef-402ad60194ea_68bc90950b68d.webp\"}]', 5025.00, 250, '1672a924-1f82-4620-a81b-9ffbca44f544', 1, NULL, '2025-09-05 23:31:45', '2025-09-06 19:51:43'),
('b6e10dae-79c6-4f38-b00a-dd719dbaf9c5', 'Batterie notebook HP TPN-Q113, TPN-Q114, TPN-Q115', 'Batterie de remplacement pour ordinateur portable, notebook HP TPN-Q113, TPN-Q114, TPN-Q115 - Remplace: 694864-851, HSTNN-YB4D, VK04, . - Technologie: Li-Ion. - Capacité: 2200 mAh (14.4V - 31.68Wh). - De la marque vhbw. - Batterie de remplacement compatible avec votre notebook. Idéale en tant que deuxième batterie lors de vos déplacements. Vous pouvez continuer à utiliser votre chargeur habituel. , article non fourni par le fournisseur d\'origine. ; Accessoire compatible de qualité et de forte capacité de la marque vhbw. Contenu de la livraison: ; 1 x Batterie Données techniques: ; Technologie: Li-Ion ; Capacité: 2200 mAh ; Tension: 14.4 V ; Puissance: 31.68 Wh ; Couleur: schwarz S\'adapte aux modèles d\'appareils suivants: ; HP TPN-Q113 ; TPN-Q114 ; TPN-Q115 Afin de vérifier que les produits sont compatibles, nous vous conseillons de vérifier si la batterie que vous utilisez actuellement est listée dans la rubrique Remplace les modèles de batterie d\'origine suivants:. Remplace les modèles de batterie d\'origine suivants: ; 694864-851, HSTNN-YB4D, VK04 Vous trouverez des batteries, chargeurs, câbles data, trépieds pour vos autres appareils électroniques dans nos autres offres. ;', '[{\"filename\":\"b6e10dae-79c6-4f38-b00a-dd719dbaf9c5_68bc91aabddc5.webp\",\"original_name\":\"batterie-de-remplacement-pour-ordinateur-portable (1).webp\",\"is_main\":true,\"path\":\"uploads\\/products\\/b6e10dae-79c6-4f38-b00a-dd719dbaf9c5_68bc91aabddc5.webp\"},{\"filename\":\"b6e10dae-79c6-4f38-b00a-dd719dbaf9c5_68bc91aad7214.webp\",\"original_name\":\"batterie-de-remplacement-pour-ordinateur-portable (2).webp\",\"is_main\":false,\"path\":\"uploads\\/products\\/b6e10dae-79c6-4f38-b00a-dd719dbaf9c5_68bc91aad7214.webp\"},{\"filename\":\"b6e10dae-79c6-4f38-b00a-dd719dbaf9c5_68bc91aaf31a4.webp\",\"original_name\":\"batterie-de-remplacement-pour-ordinateur-portable (3).webp\",\"is_main\":false,\"path\":\"uploads\\/products\\/b6e10dae-79c6-4f38-b00a-dd719dbaf9c5_68bc91aaf31a4.webp\"},{\"filename\":\"b6e10dae-79c6-4f38-b00a-dd719dbaf9c5_68bc91ab10a88.webp\",\"original_name\":\"batterie-de-remplacement-pour-ordinateur-portable (4).webp\",\"is_main\":false,\"path\":\"uploads\\/products\\/b6e10dae-79c6-4f38-b00a-dd719dbaf9c5_68bc91ab10a88.webp\"},{\"filename\":\"b6e10dae-79c6-4f38-b00a-dd719dbaf9c5_68bc91ab2344b.webp\",\"original_name\":\"batterie-de-remplacement-pour-ordinateur-portable.webp\",\"is_main\":false,\"path\":\"uploads\\/products\\/b6e10dae-79c6-4f38-b00a-dd719dbaf9c5_68bc91ab2344b.webp\"},{\"filename\":\"b6e10dae-79c6-4f38-b00a-dd719dbaf9c5_68bc91ab383b1.webp\",\"original_name\":\"batterie-pc-portable-pour-hp-593553-001.webp\",\"is_main\":false,\"path\":\"uploads\\/products\\/b6e10dae-79c6-4f38-b00a-dd719dbaf9c5_68bc91ab383b1.webp\"}]', 30000.00, 50, '14d95600-8775-4382-b10c-6d7b70f07a64', 1, NULL, '2025-09-05 22:00:24', '2025-09-06 19:55:23');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` char(36) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `phone`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) VALUES
('da939faa-d53d-4923-a91d-3021221d96fb', 'N\'DRI', 'DJOBO', 'nfcdjobo@gmail.com', '+2250140940330', NULL, '$2y$10$v/MgycWBhD8A903iZG0jZuCPKhgSxI7ub9qKq17ibAP4LbLHx8Mky', NULL, NULL, NULL, NULL);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categories_user_id_foreign` (`user_id`);

--
-- Index pour la table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Index pour la table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Index pour la table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Index pour la table `produits`
--
ALTER TABLE `produits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produits_category_id_foreign` (`category_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_phone_unique` (`phone`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `produits`
--
ALTER TABLE `produits`
  ADD CONSTRAINT `produits_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
