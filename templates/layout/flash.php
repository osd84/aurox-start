<?php

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) {
    die('Illegal request');
}

use OsdAurox\Flash;
use OsdAurox\Sec;

// Flash::success('...') / error() / warning() / info() rangent un message en session.
// On les lit ici une seule fois (clear: true) : affichés sur la page qui suit le redirect.
$flashMessages = Flash::get(clear: true);

$flashTitles = [
    'success' => 'OK',
    'info' => 'Info',
    'warning' => 'Attention',
    'danger' => 'Erreur',
];

?>
<?php foreach ($flashMessages as $type => $messages): ?>
    <?php foreach ($messages as $message): ?>
        <div class="ax-flash ax-flash-<?= Sec::hNoHtml($type) ?>" role="alert">
            <strong><?= Sec::hNoHtml($flashTitles[$type] ?? $type) ?></strong>
            <?= Sec::hNoHtml($message) ?>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>
