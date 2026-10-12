<?php

// Un template n'est jamais appelé directement : il est inclus par une page de public/
if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) {
    die('Illegal request');
}

use App\AppUrls;
use OsdAurox\AppConfig;
use OsdAurox\Base;
use OsdAurox\Sec;

// Variables que la page peut poser AVANT d'inclure le header :
//   $title       titre de l'onglet
//   $description meta description
//   $navActive   lien du menu à marquer actif (une constante AppUrls::)
$title = $title ?? AppConfig::get('appName');
$description = $description ?? AppConfig::get('appDescription');
$navActive = $navActive ?? null;

// Menu principal : libellé => URL. Le foreach AFFICHE des liens, il ne génère pas de code.
$navLinks = [
    'Accueil' => AppUrls::HOME,
    'Blog' => AppUrls::BLOG,
    'Comment ça marche' => AppUrls::GUIDE,
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Sec::hNoHtml($title) ?></title>
    <meta name="description" content="<?= Sec::hNoHtml($description) ?>">
    <link rel="icon" href="/favicon.ico">
    <link rel="alternate" type="application/rss+xml" title="<?= Sec::hNoHtml(AppConfig::get('appName')) ?>" href="<?= AppUrls::RSS ?>">

    <!-- jQuery, et oui ! encore et toujours -->
    <script src="/js/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap 5 : la grille et quelques utilitaires, le thème est dans style.css -->
    <link rel="stylesheet" href="/plugin/bootstrap-5.0.2-dist/css/bootstrap.min.css">
    <script src="/plugin/bootstrap-5.0.2-dist/js/bootstrap.bundle.min.js"></script>

    <!-- Thème Brutalism Dev Design (repris de aurox.fr). Base::cssTag() ajoute ?v=<date du fichier> -->
    <?= Base::cssTag('/css/style.css') ?>
</head>
<body>
<header class="ax-header">
    <div class="container ax-header-inner">
        <a href="<?= AppUrls::HOME ?>" class="ax-brand">
            <img src="/img/aurox_logo.svg" alt="Aurox" width="110" height="48">
            <span class="ax-tagline">aurox start<br>application de départ</span>
        </a>

        <button class="ax-burger d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav"
                aria-controls="main-nav" aria-expanded="false" aria-label="Menu">Menu</button>

        <nav class="collapse d-lg-block ax-nav" id="main-nav">
            <ul>
                <?php foreach ($navLinks as $label => $href): ?>
                    <li><a href="<?= $href ?>"<?= $navActive === $href ? ' class="active" aria-current="page"' : '' ?>><?= Sec::hNoHtml($label) ?></a></li>
                <?php endforeach; ?>

                <?php if (Sec::isAdminBool()): ?>
                    <li><a href="<?= AppUrls::ADMIN_HOME ?>"<?= $navActive === AppUrls::ADMIN_HOME ? ' class="active"' : '' ?>>Admin</a></li>
                <?php endif; ?>

                <?php if (Sec::isLoggedBool()): ?>
                    <li><a href="<?= AppUrls::MEMBER_PROFILE ?>"<?= $navActive === AppUrls::MEMBER_PROFILE ? ' class="active"' : '' ?>><?= Sec::hNoHtml($_SESSION['user']['username']) ?></a></li>
                    <li><a href="<?= AppUrls::LOGOUT ?>">Déconnexion</a></li>
                <?php else: ?>
                    <li><a href="<?= AppUrls::LOGIN ?>"<?= $navActive === AppUrls::LOGIN ? ' class="active"' : '' ?>>Connexion</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<div class="container ax-page">
    <?php require APP_ROOT . '/templates/layout/flash.php'; ?>
