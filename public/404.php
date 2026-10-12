<?php
// -----------------------------------------------------------------------------
// 404 : AppUrls::existOr404() redirige ici toute URL absente de App\AppUrls.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../aurox.php';

use App\AppUrls;
use OsdAurox\AppConfig;

$title = AppConfig::get('appName') . ' - Page introuvable';

http_response_code(404);

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
<main>
    <h1>404</h1>
    <p>Cette page n'existe pas, ou plus.</p>
    <div class="ax-btns">
        <a class="ax-btn ax-btn-primary" href="<?= AppUrls::HOME ?>">Accueil</a>
        <a class="ax-btn" href="<?= AppUrls::BLOG ?>">Le blog</a>
    </div>
</main>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
