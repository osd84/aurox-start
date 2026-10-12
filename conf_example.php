<?php

// -----------------------------------------------------------------------------
// conf_example.php : modèle de configuration.
// Copier en conf.php (jamais versionné) puis remplir.
// Toutes les clés sont lues par OsdAurox\AppConfig::get('cle').
// -----------------------------------------------------------------------------

return [
    // Identité du site
    'appName' => 'Aurox Start',
    'appTitle' => 'Aurox Start',
    'appAuthor' => '-',
    'appDescription' => 'Application de départ avec le framework Aurox',
    'appKeywords' => 'aurox, php, brutalisme',
    'appLang' => 'fr',
    'appVersion' => '1.0.0',
    'lang' => ['fr', 'en'],

    // URLs
    'devUrl' => 'http://127.0.0.1:8000',
    'prodUrl' => 'https://mon-site.example',
    'appUrl' => 'http://127.0.0.1:8000',   // l'URL de CETTE installation

    // Dev / prod
    'debug' => true,                   // false en production, toujours
    'disableHttpsRedirect' => true,    // false en production : HTTPS forcé
    'devIp' => '127.0.0.1',
    'publicDevIp' => '<ip publique du développeur>',

    // Base de données : 'mysql' (MySQL / MariaDB) ou 'pgsql' (PostgreSQL)
    'dbActive' => true,
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => '3306',                  // 5432 pour pgsql
    'db' => 'aurox_start',
    'user' => 'aurox_start',
    'pass' => '<changeMe>',
    'charset' => 'utf8mb4',
    // SSL vers la base. Obligatoire pour une base distante.
    // false n'est accepté par Dbo que pour 127.0.0.1 / localhost.
    'dbSsl' => false,

    // Sécurité
    'salt' => '<un sel long et aléatoire>',  // php -r "echo bin2hex(random_bytes(32));"
    'nonce' => true,                   // CSP avec nonce : chaque <script> inline porte nonce=Sec::noneCsp()
    'passwordMinLength' => 10,
    'passwordMaxLength' => 255,
    'adminFolder' => 'admin',

    // Fonctionnalités de l'application
    'featureRegister' => true,         // inscription publique ouverte (rôle 'user')

    // Mails (PHPMailer via OsdAurox\Mailer), facultatif
    'mailHost' => '<changeMe>',
    'mailPort' => 465,
    'mailTls' => false,
    'mailSsl' => true,
    'mailUser' => '<changeMe>',
    'mailPass' => '<changeMe>',
    'mailFrom' => '<changeMe>',
    'mailContactDest' => '<changeMe>',
    'mailSupportDest' => '<changeMe>',

    // Services externes : vide = désactivé, aucune donnée ne sort du serveur
    'ipInfoApiUrl' => '',
    'abuseIpApiUrl' => '',
    'whiteListWafIp' => [],            // IP de monitoring ignorées par le WAF
    'discordWebhook' => '',            // alertes (connexion admin, comptes bloqués)
];
