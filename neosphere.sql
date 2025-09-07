-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : dim. 07 sep. 2025 à 21:48
-- Version du serveur : 10.6.22-MariaDB-0ubuntu0.22.04.1
-- Version de PHP : 8.4.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `neosphere`
--

-- --------------------------------------------------------

--
-- Structure de la table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED NOT NULL,
  `slot_id` int(10) UNSIGNED DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `appointments`
--

INSERT INTO `appointments` (`id`, `user_id`, `service_id`, `slot_id`, `start_datetime`, `end_datetime`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 29, 42, '2025-09-08 09:45:00', '2025-09-08 10:30:00', '', NULL, '2025-09-04 10:15:57', '2025-09-04 10:20:11'),
(2, 1, 20, NULL, '2025-09-08 09:00:00', '2025-09-08 10:00:00', 'cancelled', 'Insertion test via insert_test_appointment.php', '2025-09-07 15:06:02', '2025-09-07 19:46:56'),
(3, 1, 20, NULL, '2025-09-07 10:00:00', '2025-09-07 11:00:00', 'cancelled', 'Insertion test via insert_test_appointment.php', '2025-09-07 19:46:23', '2025-09-07 19:46:54');

-- --------------------------------------------------------

--
-- Structure de la table `auth_logs`
--

CREATE TABLE `auth_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `auth_logs_corrupt`
--

CREATE TABLE `auth_logs_corrupt` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `carousel_images`
--

CREATE TABLE `carousel_images` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `carousel_images`
--

INSERT INTO `carousel_images` (`id`, `filename`, `title`, `created_at`) VALUES
(3, 'carousel_68bb280b2b40d5.42043153.jpg', NULL, '2025-09-05 20:12:27');

-- --------------------------------------------------------

--
-- Structure de la table `content_blocks`
--

CREATE TABLE `content_blocks` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(120) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `content_blocks`
--

INSERT INTO `content_blocks` (`id`, `slug`, `title`, `content`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'footer_brand_desc', 'Description footer', 'Votre partenaire santé et bien-être à Rebecq. Néosphère vous propose des soins modernes, personnalisés et accessibles, pour toute la famille', NULL, '2025-09-02 13:17:54', '2025-09-02 15:00:26'),
(2, 'footer_tagline', 'Tagline footer', 'by Lindsay Serkeyn', NULL, '2025-09-02 13:17:54', '2025-09-02 14:04:38'),
(17, 'contact_address', 'Adresse contact', '83 Chaussée Maïeur Habils<div>1430 Rebecq, Belgique</div>', NULL, '2025-09-02 14:04:38', '2025-09-05 15:05:55'),
(18, 'contact_phone', 'Téléphone contact', '+32 (0) 479.74.61.12', NULL, '2025-09-02 14:04:38', '2025-09-02 14:04:38'),
(19, 'contact_email', 'Email contact', 'contact@neosphere-ls.be', NULL, '2025-09-02 14:04:38', '2025-09-02 14:04:38'),
(20, 'contact_hours', 'Horaires contact', 'Lun - Ven : 09h00 - 17h00<br>Adaptable à la demande', NULL, '2025-09-02 14:04:38', '2025-09-02 19:11:45'),
(23, 'contact_info_block', 'Bloc infos contact', '<ul> <li class=\\\"info-item\\\"><div class=\\\"info-ico\\\">📍</div><div class=\\\"info-desc\\\"><span class=\\\"lbl\\\">Adresse</span><span class=\\\"val\\\">83 rue ...<br>1430 Rebecq, Belgique</span></div></li> ... </ul>', NULL, '2025-09-02 15:00:14', '2025-09-02 16:10:10'),
(24, 'team_intro', 'Intro équipe (ancien bloc global)', '<p>Après 15 ans dans le milieu hospitalier, j\'ai eu envie et besoin de pouvoir prendre soin d\'une autre façon. C\'est pourquoi j\'ai suivi la formation d<b>\'</b><font color=\"#d59307\"><b>infirmière conseil en esthétique, image de soi et bien-être</b>.</font> Grâce à cela, je peux vous recevoir en toute<b> sécurité</b>, que vous soyez malade, en traitement ou pas, car je reste avant tout infirmière. J\'ai à cœur de vous offrir une petite parenthèse de douceur, de détente et de lâcher prise dans mon cabinet alors n\'hésitez pas à franchir le pas et à réserver votre soin.</p><ul class=\"check-list\">\r\n</ul>', NULL, '2025-09-02 18:34:00', '2025-09-05 15:03:55'),
(25, 'footer_brand_name', 'Nom marque footer', 'Néosphère', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(26, 'contact_map_embed', 'Carte (iframe ou pb)', '', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(27, 'team_title', 'Titre équipe', 'Une infirmière spécialisée en <font color=\"#e29003\">esthétique</font>,<font color=\"#dd8d03\"> image de soi</font> et<font color=\"#d88a03\"> bien-être</font>&nbsp;à votre écoute.', NULL, '2025-09-02 19:11:45', '2025-09-05 14:53:34'),
(28, 'hero_badge', 'Badge hero', 'Expertise infirmière et bien-être', NULL, '2025-09-02 19:11:45', '2025-09-05 14:42:48'),
(29, 'hero_title', 'Titre hero', 'Votre <b>bien-être</b>, <font color=\"#d69405\">ma priorité</font>', NULL, '2025-09-02 19:11:45', '2025-09-05 14:43:59'),
(30, 'hero_subtitle', 'Sous-titre hero', 'Bienvenue chez <b><i>Néosphère</i></b>. Vous trouverez ici toute une gamme de soins axés sur la <font color=\"#db8206\">confiance en soi</font> et le<font color=\"#ee7cd9\"> bien-être</font>, adaptés à chacun. Au plaisir de faire votre connaissance !&nbsp;', NULL, '2025-09-02 19:11:45', '2025-09-05 14:47:42'),
(31, 'hero_cta_primary', 'CTA hero primaire', '📅 Prendre rendez-vous', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(32, 'hero_cta_secondary', 'CTA hero secondaire', 'Découvrir nos services', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(33, 'hero_feat1_title', 'Atout 1 titre', 'Horaires flexibles', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(34, 'hero_feat1_sub', 'Atout 1 sous', 'du Lun. au ven.', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(35, 'hero_feat2_title', 'Atout 2 titre', 'Localisé', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(36, 'hero_feat2_sub', 'Atout 2 sous', 'Rebecq', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(37, 'hero_feat3_title', 'Atout 3 titre', 'Expérience', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(38, 'hero_feat3_sub', 'Atout 3 sous', '15+ années', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(39, 'services_eyebrow', 'Eyebrow services', 'Les prestations proposées', NULL, '2025-09-02 19:11:45', '2025-09-02 21:05:01'),
(40, 'services_title', 'Titre services', 'Une gamme toujours en expansion de <span>soins <font color=\"#eeb25d\">adaptés</font> à chacun</span>', NULL, '2025-09-02 19:11:45', '2025-09-02 21:28:00'),
(41, 'services_intro', 'Intro services', 'Bénéficiez de l\'expertise infirmière mêlée à l\'envie et au besoin de soigner tant le corps que l\'esprit et même, l\'âme. Chez NéoSphère, nous prenons soin de tout le monde de la même manière, souffrant d\'une pathologie ou pas. Les soins proposés sont étudiés pour pouvoir être prodigués à tous. Les produits utilisés sont aussi choisis pour pouvoir convenir aux plus fragiles.', NULL, '2025-09-02 19:11:45', '2025-09-02 21:13:37'),
(42, 'services_cta_primary', 'CTA services primaire', '📅 Prendre rendez-vous maintenant', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(43, 'services_cta_secondary', 'CTA services secondaire', '👨‍⚕️ Voir tous les médecins', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(44, 'services_toggle_btn', 'Bouton toggle services', '➕ Afficher plus de soins', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(45, 'services_search_label', 'Label recherche services', 'Rechercher une prestation', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(46, 'service_details_more', 'Bouton détails +', 'Détails', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(47, 'service_details_less', 'Bouton détails -', 'Réduire', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(48, 'team_eyebrow', 'Eyebrow équipe', 'Qui suis-je ?', NULL, '2025-09-02 19:11:45', '2025-09-05 18:47:20'),
(49, 'team_button_contact', 'Bouton équipe contact', '📞 Contacter le secrétariat', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(50, 'team_intro_paragraph', 'Paragraphe équipe', 'Après 15 ans en milieu hospitalier, j\'ai eu l\'envie et le besoin de pouvoir soigner différemment. C\'est pourquoi j\'ai suivi la formation d\'<font color=\"#e0691a\">infirmière conseil en esthétique, image de soi et bien-être</font>. Grâce à cette formation, je vous offre un moment de détente, de lâcher prise et de relaxation tout en sécurité, que vous soyez malade, en traitement ou en bonne santé. Chez <b><i>Néosphère</i></b>, tout le monde est le bienvenu ! Alors n\'hésitez plus et réservez votre soin. A bientôt !&nbsp;', NULL, '2025-09-02 19:11:45', '2025-09-05 18:46:47'),
(51, 'team_intro_bullet_1', 'Équipe puce 1', 'Prise en charge personnalisée et confidentielle', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(52, 'team_intro_bullet_2', 'Équipe puce 2', 'Protocoles basés sur les dernières recommandations', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(53, 'team_intro_bullet_3', 'Équipe puce 3', 'Formation continue pour vous offrir les meilleurs soins', NULL, '2025-09-02 19:11:45', '2025-09-02 21:23:14'),
(54, 'team_intro_bullet_4', 'Équipe puce 4', 'Utilisation de produits parapharmaceutiques et de cosmétologie pour tous les soins', NULL, '2025-09-02 19:11:45', '2025-09-02 21:26:22'),
(55, 'team_stat1_number', 'Stat1 nombre', '5000+', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(56, 'team_stat1_label', 'Stat1 label', 'Patients suivis', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(57, 'team_stat2_number', 'Stat2 nombre', '15+', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(58, 'team_stat2_label', 'Stat2 label', 'Ans d\'expérience', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(59, 'team_stat3_number', 'Stat3 nombre', '24/7', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(60, 'team_stat3_label', 'Stat3 label', 'Assistance', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(61, 'team_stat4_number', 'Stat4 nombre', '98%', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(62, 'team_stat4_label', 'Stat4 label', 'Satisfaction', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(63, 'team_mission_title', 'Mission titre', 'Ma Mission', NULL, '2025-09-02 19:11:45', '2025-09-05 15:07:58'),
(64, 'team_mission_text', 'Mission texte', 'Accompagner chaque personne dans son processus de <font color=\"#e16d0e\">réappropriation du corps</font>, rendre <font color=\"#e36d0d\">l\'estime de soi</font> à ceux qui l\'ont perdu, valoriser l\'<font color=\"#e7700d\">image de soi</font><font color=\"#0a0a0a\">, apporter</font><font color=\"#e7700d\"> détente et relaxation </font><font color=\"#080808\">à ceux qui en ont besoin.</font><font color=\"#e7700d\">&nbsp;</font>', NULL, '2025-09-02 19:11:45', '2025-09-02 21:19:56'),
(65, 'contact_eyebrow', 'Eyebrow contact', 'Contactez-nous', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(66, 'contact_title', 'Titre contact', 'A votre <font color=\"#e37f0d\">écoute</font>', NULL, '2025-09-02 19:11:45', '2025-09-05 18:50:01'),
(67, 'contact_intro', 'Intro contact', 'Disponible pour répondre à vos questions, planifier un rendez-vous ou vous orienter vers le bon accompagnement.', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(68, 'contact_box_title', 'Titre boîte contact', 'Informations de contact', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(69, 'contact_quick_rdv_btn', 'Bouton rapide RDV', '📅 Prendre rendez-vous', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(70, 'contact_quick_call_btn', 'Bouton rapide Appel', '📞 Appeler maintenant', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(71, 'contact_form_title', 'Titre formulaire', 'Envoyez-nous un message', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(72, 'contact_form_label_name', 'Label nom', 'Nom complet', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(73, 'contact_form_label_email', 'Label email', 'Email', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(74, 'contact_form_label_phone', 'Label téléphone', 'Téléphone', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(75, 'contact_form_label_type', 'Label type', 'Type de demande', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(76, 'contact_form_label_message', 'Label message', 'Message', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(77, 'contact_form_submit', 'Bouton envoyer', '📨 Envoyer le message', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(78, 'contact_form_note', 'Note formulaire', '* Tous les champs obligatoires sont nécessaires pour un meilleur traitement.', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(79, 'contact_form_option_placeholder', 'Option placeholder', 'Préciser le motif', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(80, 'contact_form_option_rdv', 'Option RDV', 'Rendez-vous', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(81, 'contact_form_option_question', 'Option question', 'Questions', NULL, '2025-09-02 19:11:45', '2025-09-06 06:48:42'),
(82, 'contact_form_option_results', 'Option résultats', 'Résultats', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(83, 'contact_form_option_other', 'Option autre', 'Autre', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(84, 'map_open_link_text', 'Texte lien carte', 'Ouvrir dans Maps →', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(85, 'map_caption_prefix', 'Préfixe légende carte', 'Néosphère – ', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(86, 'footer_col_contact_title', 'Titre colonne contact', 'Contact', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(87, 'footer_col_hours_title', 'Titre colonne horaires', 'Horaires', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(88, 'footer_col_services_title', 'Titre colonne services', 'Services', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(89, 'footer_legal_mentions', 'Lien mentions', 'Mentions légales', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(90, 'footer_legal_confidentialite', 'Lien confidentialité', 'Confidentialité', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(91, 'footer_legal_cgu', 'Lien CGU', 'CGU', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(92, 'footer_copyright', 'Copyright', '&copy; 2024 Néosphère. Fait avec <span class=\"heart\">❤</span> pour votre santé.', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(93, 'meta_title', 'Meta Title', 'Néosphère - médical & bien-être à Rebecq', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(94, 'meta_description', 'Meta Description', 'Néosphère by Lindsay Serkeyn médical & bien-être moderne à Rebecq. Prenez rendez-vous dès maintenant.', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(95, 'meta_author', 'Meta Author', 'Néosphère', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(96, 'og_title', 'OG Title', 'Néosphère - médical & bien-être à Rebecq', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(97, 'og_description', 'OG Description', 'Néosphère, cabinet moderne offrant une gamme complète de services médicaux avec une approche humaine et personnalisée.', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(98, 'site_brand_name', 'Nom marque', 'Néosphère', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(99, 'nav_home', 'Menu Accueil', 'Accueil', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(100, 'nav_services', 'Menu Services', 'Services', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(101, 'nav_team', 'Menu Équipe', 'Équipe', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(102, 'nav_contact', 'Menu Contact', 'Contact', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(103, 'header_call_btn', 'Bouton en-tête Appeler', 'Appeler', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(104, 'header_rdv_btn', 'Bouton en-tête RDV', 'Rendez-vous', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(105, 'header_byline', 'Byline header', 'by Lindsay Serkeyn', NULL, '2025-09-02 19:11:45', '2025-09-02 19:11:45'),
(113, 'bliblablu', '', '<span style=\"font-size: 15.2px; font-style: normal; font-weight: 400;\">Bienvenue chez NéoSphère médical et bien-être. Vous trouverez ici toute une gamme de soins adaptés à chacun. A bientôt !</span>', NULL, '2025-09-02 20:53:32', '2025-09-02 20:53:32');

-- --------------------------------------------------------

--
-- Structure de la table `content_revisions`
--

CREATE TABLE `content_revisions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `block_id` int(10) UNSIGNED NOT NULL,
  `content` mediumtext NOT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `content_revisions`
--

INSERT INTO `content_revisions` (`id`, `block_id`, `content`, `updated_by`, `note`, `created_at`) VALUES
(1, 1, 'Votre partenaire santé et bien-être à Rebecq. Néosphère vous propose des soins modernes, personnalisés et accessibles, pour toute la famille', NULL, 'Seed initial', '2025-09-02 13:17:54'),
(2, 2, 'by Lindsay Serkeyn', NULL, 'Seed initial', '2025-09-02 13:17:54');

-- --------------------------------------------------------

--
-- Structure de la table `grids`
--

CREATE TABLE `grids` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `owner` varchar(200) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `grids`
--

INSERT INTO `grids` (`id`, `name`, `owner`, `is_default`, `created_at`) VALUES
(1, 'grille 1', 'madum', 0, '2025-08-30 02:53:16'),
(3, 'Prestations et prix', 'madum', 1, '2025-08-30 03:01:07'),
(4, 'grille 3', 'admin', 0, '2025-08-30 04:24:56');

-- --------------------------------------------------------

--
-- Structure de la table `prestations`
--

CREATE TABLE `prestations` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `duree` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `prix_ttc` decimal(10,2) DEFAULT 0.00,
  `poids` int(11) DEFAULT 0,
  `grid_id` int(11) DEFAULT 0,
  `emoji` varchar(10) DEFAULT '?'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `prestations`
--

INSERT INTO `prestations` (`id`, `nom`, `duree`, `description`, `prix_ttc`, `poids`, `grid_id`, `emoji`) VALUES
(1, '3 nom', '1h30', 'description 3', 53.00, 0, 0, '🩺'),
(2, '2 nom', '1h30', 'decription 2', 52.00, 1, 0, '🩺'),
(3, '1 nom', '1h30', 'descrpiton 1', 50.00, 2, 0, '🩺'),
(4, 'Massage relaxant', '1h30', 'Description 1', 50.00, 0, 0, '🩺'),
(5, 'Soin visage', '45min', 'Description 2', 35.00, 1, 0, '🩺'),
(6, 'Gommage corps', '30min', 'Description 3', 25.00, 2, 0, '🩺'),
(7, 'test nom grille test', '1h', 'description nom grille test 1 prestia', 50.00, 3, 0, '🩺'),
(11, 'Soin visage express', '20 min', 'Nettoyage et lumière', 30.00, 3, 1, '🩺'),
(12, 'Bilan & conseils', '15 min', 'Consultation initiale', 0.00, 1, 1, '🩺'),
(16, 'Massage du dos', '60', 'massage relaxant et thérapeutique ciblé sur les problèmes présents', 60.00, 9, 1, '🩺'),
(17, 'Massage du dos', '60', 'Massage relaxant et thérapeutique qui cible les problèmes présents', 60.00, 2, 3, '🩺'),
(18, 'Massage du corps aux pierres chaudes', '60', 'Massage relaxant, pur moment de détente grâce aux pierres de lave chauffées placées avec soin sur tout votre corps', 75.00, 4, 3, '🩺'),
(19, 'Massage du corps avec des pochons d\'herbes thaï', '60', 'Massage relaxant et thérapeutique grâce aux effets des herbes', 75.00, 5, 3, '🩺'),
(20, 'Massage du visage', '45', 'Massage relaxant, drainant et raffermissant du visage, du cou, de la nuque et du crâne', 45.00, 0, 3, '👩‍⚕️'),
(21, 'Massage des bras et des mains', '45', 'Massage relaxant des membres supérieurs', 45.00, 1, 3, '🩺'),
(22, 'Massage jambes et pieds', '60', 'Massage relaxant et drainant des jambes (complètes) et des pieds. Possibilité d\'ajouter une session d\'acupression aux huiles essentielles ', 60.00, 3, 3, '🩺'),
(26, 'Pose de cils \"One by One\"', '150', 'Pour un résultat naturel. Tenue entre 4 et 6 semaines', 120.00, 14, 3, '🩺'),
(27, 'Lash lift', '45', 'Recourbement de cils avec un effet naturel. Tenue jusqu\'à 6 semaines', 50.00, 18, 3, '🩺'),
(28, 'Lash lift + teinture', '60', 'Recourbement des cils avec effet naturel. Tenue jusqu\'à 6 semaines. Teinture effet maquillage permanent. Tenue jusqu\'à 4 semaines', 65.00, 19, 3, '🩺'),
(29, 'Brow lift', '45', 'Rehaussement des sourcils avec un effet naturel. Tenue jusqu\'à 6 semaines', 50.00, 20, 3, '🩺'),
(30, 'Brow lift + teinture', '60', 'Rehaussement des sourcils avec effet naturel. Tenue jusqu\'à 6 semaines. Teinture effet maquillage permanent. Tenue jusqu\'à 4 semaines', 65.00, 21, 3, '🩺'),
(31, 'Brow and Lash lift + teinture', '75', 'Profitez de l\'offre DUO pour parfaire votre regard', 110.00, 22, 3, '🩺'),
(32, 'Teinture des cils', '45', 'Effet maquillage permanent, tenue jusqu\'à 4 semaines', 20.00, 23, 3, '🩺'),
(33, 'Teinture des sourcils', '45', 'Effet maquillage permanent, tenue jusqu\'à 4 semaines', 20.00, 24, 3, '🩺'),
(34, 'Retouche cils 2 semaines', '30', 'Pour garder un regard parfait, une petite retouche rapide', 40.00, 15, 3, '🩺'),
(35, 'Retouche cils 4 semaines', '60', '! Si trop de perte, une nouvelle pose sera facturée', 55.00, 16, 3, '🩺'),
(36, 'Dépose de cils ', '30', 'Comprend le nettoyage à la mousse et le soin régénérant après la dépose', 30.00, 17, 3, '🩺'),
(39, 'Maquillage de jour', '60', 'Maquillage naturel et bonne mine selon vos envies.', 40.00, 7, 3, '🩺'),
(40, 'Maquillage \"camouflage\"', '60', 'Grâce à certaines techniques, dites adieu aux taches, imperfections et autres cicatrices. Effet naturel et bonne mine.', 40.00, 9, 3, '🩺'),
(41, 'Maquillage de fête', '60', 'Même prestation que pour le maquillage de jour, avec des paillettes et du glamour en plus ! ', 40.00, 8, 3, '🩺'),
(42, 'Soin du visage personnalisé', '65', 'Soin complet pour redonner la pêche à votre peau. Nettoyage, gommage, sérum, soin du contour de l\'œil, luminothérapie, masque, crème de jour ', 50.00, 6, 3, '🩺'),
(43, 'Manucure \"mise en beauté\"', '45', 'Soin complet des mains et des ongles. Vernis inclus, VSP +5 €', 25.00, 10, 3, '🩺'),
(44, 'Pédicure \"mise en beauté\"', '45', 'Soin complet des pieds et des ongles. Vernis inclus, VSP +5 €', 25.00, 11, 3, '🩺'),
(45, 'Soin à la paraffine mains ou pieds', '45', 'Gommage, soin à la paraffine, masque, crème hydratante. VSP +5 €', 35.00, 12, 3, '🩺'),
(52, 'Massage du visage', '45', 'Massage relaxant, drainant et raffermissant du visage, du cou, de la nuque et du crâne', 45.00, 31, 3, '❤️');

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `name`, `created_at`) VALUES
(1, 'admin', '2025-08-30 00:42:55'),
(2, 'user', '2025-08-30 00:42:55');

-- --------------------------------------------------------

--
-- Structure de la table `sent_mails`
--

CREATE TABLE `sent_mails` (
  `id` int(11) NOT NULL,
  `sender` varchar(255) DEFAULT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `sent_mails`
--

INSERT INTO `sent_mails` (`id`, `sender`, `recipient`, `subject`, `body`, `sent_at`, `ip`, `user_agent`) VALUES
(1, 'contact@neosphere-ls.be', 'contact@neosphere-ls.be', 'Contact site: Rendez-vous', '<!DOCTYPE html>\r\n<html lang=\"fr\">\r\n<head>\r\n<meta charset=\"utf-8\">\r\n<title>Message de contact</title>\r\n</head>\r\n<body style=\"margin:0;padding:0;background:#f6f8fa;font:14px/1.5 -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;color:#222;\">\r\n  <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#f6f8fa;padding:24px;\">\r\n    <tr>\r\n      <td align=\"center\">\r\n        <table role=\"presentation\" width=\"600\" cellpadding=\"0\" cellspacing=\"0\" style=\"max-width:600px;width:100%;background:#ffffff;border:1px solid #e2e6ea;border-radius:8px;overflow:hidden;\">\r\n          <tr>\r\n            <td style=\"background:#222;color:#fff;padding:16px 24px;font-size:18px;font-weight:600;\">\r\n              Nouveau message de contact – Néosphère\r\n            </td>\r\n          </tr>\r\n          <tr>\r\n            <td style=\"padding:24px;\">\r\n              <h2 style=\"margin:0 0 16px;font-size:20px;color:#222;\">Détails</h2>\r\n              <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" style=\"width:100%;border-collapse:collapse;font-size:14px;\">\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;width:140px;background:#fafbfc;border:1px solid #e5e9ec;\">Nom</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">gegrzg</td>\r\n                </tr>\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;\">Email</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">marin.dumont@proton.me</td>\r\n                </tr>\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;\">Téléphone</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">+32479746112</td>\r\n                </tr>\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;\">Type</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">Rendez-vous</td>\r\n                </tr>\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;\">Date</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">2025-09-05 23:41:35 CEST</td>\r\n                </tr>\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;\">IP</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">80.200.60.19</td>\r\n                </tr>\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;\">Agent</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36</td>\r\n                </tr>\r\n              </table>\r\n\r\n              <h3 style=\"margin:24px 0 8px;font-size:16px;\">Message</h3>\r\n              <div style=\"background:#fafbfc;border:1px solid #e5e9ec;padding:12px;border-radius:4px;white-space:pre-wrap;\">\r\n                egebn&quot;b rebgebergb erb rb eb rere\r\n              </div>\r\n\r\n              <p style=\"margin:24px 0 0;font-size:12px;color:#666;\">\r\n                Email automatique envoyé depuis le formulaire du site. Ne répondez que si pertinent.\r\n              </p>\r\n            </td>\r\n          </tr>\r\n          <tr>\r\n            <td style=\"background:#f0f2f4;padding:12px 24px;font-size:12px;color:#555;text-align:center;\">\r\n              © Néosphère – Généré automatiquement\r\n            </td>\r\n          </tr>\r\n        </table>\r\n      </td>\r\n    </tr>\r\n  </table>\r\n</body>\r\n</html>', '2025-09-05 23:41:35', '80.200.60.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'),
(2, 'contact@neosphere-ls.be', 'contact@neosphere-ls.be', 'Contact site: Questions', '<!DOCTYPE html>\r\n<html lang=\"fr\">\r\n<head>\r\n<meta charset=\"utf-8\">\r\n<title>Message de contact</title>\r\n</head>\r\n<body style=\"margin:0;padding:0;background:#f6f8fa;font:14px/1.5 -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;color:#222;\">\r\n  <table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#f6f8fa;padding:24px;\">\r\n    <tr>\r\n      <td align=\"center\">\r\n        <table role=\"presentation\" width=\"600\" cellpadding=\"0\" cellspacing=\"0\" style=\"max-width:600px;width:100%;background:#ffffff;border:1px solid #e2e6ea;border-radius:8px;overflow:hidden;\">\r\n          <tr>\r\n            <td style=\"background:#222;color:#fff;padding:16px 24px;font-size:18px;font-weight:600;\">\r\n              Nouveau message de contact – Néosphère\r\n            </td>\r\n          </tr>\r\n          <tr>\r\n            <td style=\"padding:24px;\">\r\n              <h2 style=\"margin:0 0 16px;font-size:20px;color:#222;\">Détails</h2>\r\n              <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" style=\"width:100%;border-collapse:collapse;font-size:14px;\">\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;width:140px;background:#fafbfc;border:1px solid #e5e9ec;\">Nom</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">Marin Dumont</td>\r\n                </tr>\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;\">Email</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">marin.dumont@gmail.com</td>\r\n                </tr>\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;\">Téléphone</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">+32479746112</td>\r\n                </tr>\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;\">Type</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">Questions</td>\r\n                </tr>\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;\">Date</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">2025-09-07 21:44:47 CEST</td>\r\n                </tr>\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;\">IP</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">80.200.60.19</td>\r\n                </tr>\r\n                <tr>\r\n                  <td style=\"padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;\">Agent</td>\r\n                  <td style=\"padding:6px 8px;border:1px solid #e5e9ec;\">Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36</td>\r\n                </tr>\r\n              </table>\r\n\r\n              <h3 style=\"margin:24px 0 8px;font-size:16px;\">Message</h3>\r\n              <div style=\"background:#fafbfc;border:1px solid #e5e9ec;padding:12px;border-radius:4px;white-space:pre-wrap;\">\r\n                test message\r\n              </div>\r\n\r\n              <p style=\"margin:24px 0 0;font-size:12px;color:#666;\">\r\n                Email automatique envoyé depuis le formulaire du site. Ne répondez que si pertinent.\r\n              </p>\r\n            </td>\r\n          </tr>\r\n          <tr>\r\n            <td style=\"background:#f0f2f4;padding:12px 24px;font-size:12px;color:#555;text-align:center;\">\r\n              © Néosphère – Généré automatiquement\r\n            </td>\r\n          </tr>\r\n        </table>\r\n      </td>\r\n    </tr>\r\n  </table>\r\n</body>\r\n</html>', '2025-09-07 21:44:47', '80.200.60.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Structure de la table `service_meta`
--

CREATE TABLE `service_meta` (
  `service_table` varchar(64) NOT NULL,
  `service_id` int(11) NOT NULL,
  `max_per_day` int(11) NOT NULL DEFAULT 8,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `service_slots`
--

CREATE TABLE `service_slots` (
  `id` int(10) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED NOT NULL,
  `slot_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 1,
  `booked_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('open','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `service_slots`
--

INSERT INTO `service_slots` (`id`, `service_id`, `slot_date`, `start_time`, `end_time`, `capacity`, `booked_count`, `status`, `created_at`, `updated_at`) VALUES
(1, 36, '2025-09-08', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(2, 36, '2025-09-08', '09:30:00', '10:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(3, 36, '2025-09-08', '10:00:00', '10:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(4, 36, '2025-09-08', '10:30:00', '11:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(5, 36, '2025-09-08', '11:00:00', '11:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(6, 36, '2025-09-08', '11:30:00', '12:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(7, 36, '2025-09-08', '12:00:00', '12:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(8, 36, '2025-09-08', '12:30:00', '13:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(9, 36, '2025-09-09', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(10, 36, '2025-09-09', '09:30:00', '10:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(11, 36, '2025-09-09', '10:00:00', '10:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(12, 36, '2025-09-09', '10:30:00', '11:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(13, 36, '2025-09-09', '11:00:00', '11:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(14, 36, '2025-09-09', '11:30:00', '12:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(15, 36, '2025-09-09', '12:00:00', '12:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(16, 36, '2025-09-09', '12:30:00', '13:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(17, 36, '2025-09-10', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(18, 36, '2025-09-10', '09:30:00', '10:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(19, 36, '2025-09-10', '10:00:00', '10:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(20, 36, '2025-09-10', '10:30:00', '11:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(21, 36, '2025-09-10', '11:00:00', '11:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(22, 36, '2025-09-10', '11:30:00', '12:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(23, 36, '2025-09-10', '12:00:00', '12:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(24, 36, '2025-09-10', '12:30:00', '13:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(25, 36, '2025-09-11', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(26, 36, '2025-09-11', '09:30:00', '10:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(27, 36, '2025-09-11', '10:00:00', '10:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(28, 36, '2025-09-11', '10:30:00', '11:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(29, 36, '2025-09-11', '11:00:00', '11:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(30, 36, '2025-09-11', '11:30:00', '12:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(31, 36, '2025-09-11', '12:00:00', '12:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(32, 36, '2025-09-11', '12:30:00', '13:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(33, 36, '2025-09-12', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(34, 36, '2025-09-12', '09:30:00', '10:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(35, 36, '2025-09-12', '10:00:00', '10:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(36, 36, '2025-09-12', '10:30:00', '11:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(37, 36, '2025-09-12', '11:00:00', '11:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(38, 36, '2025-09-12', '11:30:00', '12:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(39, 36, '2025-09-12', '12:00:00', '12:30:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(40, 36, '2025-09-12', '12:30:00', '13:00:00', 1, 0, 'open', '2025-09-04 09:02:34', '2025-09-04 09:02:34'),
(41, 29, '2025-09-08', '09:00:00', '09:45:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 23:01:39'),
(42, 29, '2025-09-08', '09:45:00', '10:30:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 23:01:43'),
(43, 29, '2025-09-08', '10:30:00', '11:15:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(44, 29, '2025-09-08', '11:15:00', '12:00:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(45, 29, '2025-09-08', '12:00:00', '12:45:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(46, 29, '2025-09-08', '12:45:00', '13:30:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(47, 29, '2025-09-08', '13:30:00', '14:15:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(48, 29, '2025-09-08', '14:15:00', '15:00:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(49, 29, '2025-09-09', '09:00:00', '09:45:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(50, 29, '2025-09-09', '09:45:00', '10:30:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(51, 29, '2025-09-09', '10:30:00', '11:15:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(52, 29, '2025-09-09', '11:15:00', '12:00:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(53, 29, '2025-09-09', '12:00:00', '12:45:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(54, 29, '2025-09-09', '12:45:00', '13:30:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(55, 29, '2025-09-09', '13:30:00', '14:15:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(56, 29, '2025-09-09', '14:15:00', '15:00:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(57, 29, '2025-09-10', '09:00:00', '09:45:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(58, 29, '2025-09-10', '09:45:00', '10:30:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(59, 29, '2025-09-10', '10:30:00', '11:15:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(60, 29, '2025-09-10', '11:15:00', '12:00:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(61, 29, '2025-09-10', '12:00:00', '12:45:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(62, 29, '2025-09-10', '12:45:00', '13:30:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(63, 29, '2025-09-10', '13:30:00', '14:15:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(64, 29, '2025-09-10', '14:15:00', '15:00:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(65, 29, '2025-09-11', '09:00:00', '09:45:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(66, 29, '2025-09-11', '09:45:00', '10:30:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(67, 29, '2025-09-11', '10:30:00', '11:15:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(68, 29, '2025-09-11', '11:15:00', '12:00:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(69, 29, '2025-09-11', '12:00:00', '12:45:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(70, 29, '2025-09-11', '12:45:00', '13:30:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(71, 29, '2025-09-11', '13:30:00', '14:15:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(72, 29, '2025-09-11', '14:15:00', '15:00:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(73, 29, '2025-09-12', '09:00:00', '09:45:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(74, 29, '2025-09-12', '09:45:00', '10:30:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(75, 29, '2025-09-12', '10:30:00', '11:15:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(76, 29, '2025-09-12', '11:15:00', '12:00:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(77, 29, '2025-09-12', '12:00:00', '12:45:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(78, 29, '2025-09-12', '12:45:00', '13:30:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(79, 29, '2025-09-12', '13:30:00', '14:15:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(80, 29, '2025-09-12', '14:15:00', '15:00:00', 5, 0, 'open', '2025-09-04 10:12:02', '2025-09-04 10:12:02'),
(81, 29, '2025-09-04', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:14', '2025-09-04 21:32:14'),
(84, 29, '2025-09-05', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(85, 29, '2025-09-06', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(86, 29, '2025-09-07', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(92, 29, '2025-09-13', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(93, 29, '2025-09-14', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(94, 29, '2025-09-15', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(95, 29, '2025-09-16', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(96, 29, '2025-09-17', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(97, 29, '2025-09-18', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(98, 29, '2025-09-19', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(99, 29, '2025-09-20', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(100, 29, '2025-09-21', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(101, 29, '2025-09-22', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(102, 29, '2025-09-23', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(103, 29, '2025-09-24', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(104, 29, '2025-09-25', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:32:35', '2025-09-04 21:32:35'),
(196, 31, '2025-09-04', '09:00:00', '17:00:00', 2, 0, 'open', '2025-09-04 21:40:41', '2025-09-04 21:40:41'),
(198, 31, '2025-09-05', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(199, 31, '2025-09-06', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(200, 31, '2025-09-07', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(201, 31, '2025-09-08', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(202, 31, '2025-09-09', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(203, 31, '2025-09-10', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(204, 31, '2025-09-11', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(205, 31, '2025-09-12', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(206, 31, '2025-09-13', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(207, 31, '2025-09-14', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(208, 31, '2025-09-15', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(209, 31, '2025-09-16', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(210, 31, '2025-09-17', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(211, 31, '2025-09-18', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(212, 31, '2025-09-19', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(213, 31, '2025-09-20', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(214, 31, '2025-09-21', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(215, 31, '2025-09-22', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(216, 31, '2025-09-23', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(217, 31, '2025-09-24', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(218, 31, '2025-09-25', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(219, 31, '2025-09-26', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(220, 31, '2025-09-27', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(221, 31, '2025-09-28', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(222, 31, '2025-09-29', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(223, 31, '2025-09-30', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(224, 31, '2025-10-01', '09:00:00', '09:30:00', 1, 0, 'open', '2025-09-04 21:40:58', '2025-09-04 21:40:58'),
(225, 33, '2025-09-07', '13:00:00', '13:45:00', 1, 0, 'open', '2025-09-05 18:38:21', '2025-09-05 18:38:21'),
(226, 33, '2025-09-07', '13:45:00', '14:30:00', 1, 0, 'open', '2025-09-05 18:38:21', '2025-09-05 18:38:21'),
(227, 33, '2025-09-07', '14:30:00', '15:15:00', 1, 0, 'open', '2025-09-05 18:38:21', '2025-09-05 18:38:21'),
(228, 33, '2025-09-07', '15:15:00', '16:00:00', 1, 0, 'open', '2025-09-05 18:38:21', '2025-09-05 18:38:21'),
(229, 33, '2025-09-07', '16:00:00', '16:45:00', 1, 0, 'open', '2025-09-05 18:38:21', '2025-09-05 18:38:21'),
(230, 52, '2025-09-08', '09:00:00', '09:45:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(231, 52, '2025-09-08', '09:45:00', '10:30:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(232, 52, '2025-09-08', '10:30:00', '11:15:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(233, 52, '2025-09-08', '11:15:00', '12:00:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(234, 52, '2025-09-08', '12:00:00', '12:45:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(235, 52, '2025-09-08', '12:45:00', '13:30:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(236, 52, '2025-09-08', '13:30:00', '14:15:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(237, 52, '2025-09-08', '14:15:00', '15:00:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(238, 52, '2025-09-09', '09:00:00', '09:45:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(239, 52, '2025-09-09', '09:45:00', '10:30:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(240, 52, '2025-09-09', '10:30:00', '11:15:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(241, 52, '2025-09-09', '11:15:00', '12:00:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(242, 52, '2025-09-09', '12:00:00', '12:45:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(243, 52, '2025-09-09', '12:45:00', '13:30:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(244, 52, '2025-09-09', '13:30:00', '14:15:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(245, 52, '2025-09-09', '14:15:00', '15:00:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(246, 52, '2025-09-10', '09:00:00', '09:45:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(247, 52, '2025-09-10', '09:45:00', '10:30:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(248, 52, '2025-09-10', '10:30:00', '11:15:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(249, 52, '2025-09-10', '11:15:00', '12:00:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(250, 52, '2025-09-10', '12:00:00', '12:45:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(251, 52, '2025-09-10', '12:45:00', '13:30:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(252, 52, '2025-09-10', '13:30:00', '14:15:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(253, 52, '2025-09-10', '14:15:00', '15:00:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(254, 52, '2025-09-11', '09:00:00', '09:45:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(255, 52, '2025-09-11', '09:45:00', '10:30:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(256, 52, '2025-09-11', '10:30:00', '11:15:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(257, 52, '2025-09-11', '11:15:00', '12:00:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(258, 52, '2025-09-11', '12:00:00', '12:45:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(259, 52, '2025-09-11', '12:45:00', '13:30:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(260, 52, '2025-09-11', '13:30:00', '14:15:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(261, 52, '2025-09-11', '14:15:00', '15:00:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(262, 52, '2025-09-12', '09:00:00', '09:45:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(263, 52, '2025-09-12', '09:45:00', '10:30:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(264, 52, '2025-09-12', '10:30:00', '11:15:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(265, 52, '2025-09-12', '11:15:00', '12:00:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(266, 52, '2025-09-12', '12:00:00', '12:45:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(267, 52, '2025-09-12', '12:45:00', '13:30:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(268, 52, '2025-09-12', '13:30:00', '14:15:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(269, 52, '2025-09-12', '14:15:00', '15:00:00', 8, 0, 'open', '2025-09-06 19:48:05', '2025-09-06 19:48:05'),
(270, 20, '2025-09-06', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(271, 20, '2025-09-07', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(272, 20, '2025-09-08', '09:00:00', '18:00:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:51:22'),
(273, 20, '2025-09-09', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(274, 20, '2025-09-10', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(275, 20, '2025-09-11', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(276, 20, '2025-09-12', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(277, 20, '2025-09-13', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(278, 20, '2025-09-14', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(279, 20, '2025-09-15', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(280, 20, '2025-09-16', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(281, 20, '2025-09-17', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(282, 20, '2025-09-18', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(283, 20, '2025-09-19', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(284, 20, '2025-09-20', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(285, 20, '2025-09-21', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(286, 20, '2025-09-22', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(287, 20, '2025-09-23', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(288, 20, '2025-09-24', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(289, 20, '2025-09-25', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(290, 20, '2025-09-26', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18'),
(291, 20, '2025-09-27', '09:00:00', '09:30:00', 8, 0, 'open', '2025-09-06 19:49:18', '2025-09-06 19:49:18');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `role_id` smallint(5) UNSIGNED NOT NULL DEFAULT 2,
  `verification_token` varchar(128) DEFAULT NULL,
  `verification_sent_at` datetime DEFAULT NULL,
  `password_reset_token` varchar(128) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `failed_login_attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `lock_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pseudo` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `email`, `username`, `password_hash`, `is_active`, `is_verified`, `role_id`, `verification_token`, `verification_sent_at`, `password_reset_token`, `password_reset_expires`, `failed_login_attempts`, `lock_until`, `last_login`, `created_at`, `updated_at`, `pseudo`) VALUES
(1, 'marin.dumont@gmail.com', 'madum', '$2y$10$Fze2OBha3MOCySGvOCsSBuQuLHTQS91Hcx8UQDNVN8xkL8upzxyHS', 1, 0, 1, NULL, NULL, NULL, NULL, 0, NULL, NULL, '2025-08-30 01:04:52', '2025-08-30 02:09:35', 'Madum'),
(2, '', 'Lindsay', '$2y$10$An/HC8Tpnpuy1L8SYWRKeuzf/FtV/u74C2fofTy5BQUOAVHQc4v8i', 1, 0, 1, NULL, NULL, NULL, NULL, 0, NULL, NULL, '2025-08-30 20:15:57', '2025-08-30 20:16:34', '');

-- --------------------------------------------------------

--
-- Structure de la table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `display_name` varchar(150) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `avatar_url` varchar(512) DEFAULT NULL,
  `locale` varchar(10) DEFAULT 'fr_FR',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` char(128) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_access` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appt_user` (`user_id`),
  ADD KEY `idx_appt_service` (`service_id`),
  ADD KEY `idx_appt_slot` (`slot_id`),
  ADD KEY `idx_appt_dates` (`start_datetime`);

--
-- Index pour la table `auth_logs`
--
ALTER TABLE `auth_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `auth_logs_corrupt`
--
ALTER TABLE `auth_logs_corrupt`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `carousel_images`
--
ALTER TABLE `carousel_images`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `content_blocks`
--
ALTER TABLE `content_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_content_slug` (`slug`),
  ADD KEY `idx_updated_by` (`updated_by`);

--
-- Index pour la table `content_revisions`
--
ALTER TABLE `content_revisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_block_created` (`block_id`,`created_at`),
  ADD KEY `idx_rev_user` (`updated_by`);

--
-- Index pour la table `grids`
--
ALTER TABLE `grids`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grids_is_default` (`is_default`);

--
-- Index pour la table `prestations`
--
ALTER TABLE `prestations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grid_poids` (`grid_id`,`poids`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Index pour la table `sent_mails`
--
ALTER TABLE `sent_mails`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `service_meta`
--
ALTER TABLE `service_meta`
  ADD PRIMARY KEY (`service_table`,`service_id`);

--
-- Index pour la table `service_slots`
--
ALTER TABLE `service_slots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_service_slot` (`service_id`,`slot_date`,`start_time`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `email_2` (`email`),
  ADD KEY `username_2` (`username`),
  ADD KEY `verification_token` (`verification_token`),
  ADD KEY `password_reset_token` (`password_reset_token`);

--
-- Index pour la table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Index pour la table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `auth_logs`
--
ALTER TABLE `auth_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `auth_logs_corrupt`
--
ALTER TABLE `auth_logs_corrupt`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `carousel_images`
--
ALTER TABLE `carousel_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `content_blocks`
--
ALTER TABLE `content_blocks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT pour la table `content_revisions`
--
ALTER TABLE `content_revisions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `grids`
--
ALTER TABLE `grids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `prestations`
--
ALTER TABLE `prestations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `sent_mails`
--
ALTER TABLE `sent_mails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `service_slots`
--
ALTER TABLE `service_slots`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=292;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `auth_logs`
--
ALTER TABLE `auth_logs`
  ADD CONSTRAINT `fk_auth_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `auth_logs_corrupt`
--
ALTER TABLE `auth_logs_corrupt`
  ADD CONSTRAINT `auth_logs_corrupt_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `content_blocks`
--
ALTER TABLE `content_blocks`
  ADD CONSTRAINT `fk_cb_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `content_revisions`
--
ALTER TABLE `content_revisions`
  ADD CONSTRAINT `fk_cr_block` FOREIGN KEY (`block_id`) REFERENCES `content_blocks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cr_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
