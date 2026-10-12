<?php

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) {
    die('Illegal request');
}

use App\AppUrls;
use OsdAurox\Sec;

// La page pose $sidebarActive (une constante AppUrls::) avant d'inclure ce menu
$sidebarActive = $sidebarActive ?? null;

$sidebarLinks = [
    'Tableau de bord' => AppUrls::ADMIN_HOME,
    'Articles' => AppUrls::ADMIN_POSTS,
    'Utilisateurs' => AppUrls::ADMIN_USERS,
];

?>
<aside class="ax-sidebar mb-4">
    <p class="ax-sidebar-title">Administration</p>
    <ul>
        <?php foreach ($sidebarLinks as $label => $href): ?>
            <li><a href="<?= $href ?>"<?= $sidebarActive === $href ? ' class="active"' : '' ?>><?= Sec::hNoHtml($label) ?></a></li>
        <?php endforeach; ?>
        <li><a href="<?= AppUrls::BLOG ?>">Voir le blog</a></li>
    </ul>
</aside>
