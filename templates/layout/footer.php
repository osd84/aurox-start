<?php

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) {
    die('Illegal request');
}

use App\AppUrls;
use OsdAurox\AppConfig;
use OsdAurox\Base;
use OsdAurox\Sec;

?>
</div> <!-- fin de .ax-page -->

<footer class="ax-footer">
    <div class="container ax-footer-inner">
        <p>
            <?= Sec::hNoHtml(AppConfig::get('appName')) ?> · licence MIT<br>
            Construit avec <a href="<?= AppUrls::AUROX_DOC ?>" target="_blank" rel="noopener">Aurox</a>, framework PHP brutaliste
        </p>
        <p>
            <a href="<?= AppUrls::RSS ?>">Flux RSS</a> · <a href="<?= AppUrls::AUROX_GITHUB ?>" target="_blank" rel="noopener">aurox-dist sur GitHub ↗</a><br>
            <?php if (AppConfig::isDebug()): ?>
                <span class="ax-debug">debug = true · requête <?= Sec::hNoHtml(substr(REQUEST_ID, 0, 8)) ?></span>
            <?php else: ?>
                Powered by Aurox · Another Brutalism Design Library
            <?php endif; ?>
        </p>
    </div>
</footer>

<!-- Scripts communes à toutes les pages -->
<?= Base::scriptTag('/js/script.js') ?>
</body>
</html>
