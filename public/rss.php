<?php
// -----------------------------------------------------------------------------
// Flux RSS 2.0 des articles publiés : /rss.php
//
// Pas de lib XML : le flux est écrit à la main, chaque valeur passée par
// htmlspecialchars(ENT_XML1). Les liens sont absolus, construits depuis 'appUrl' de conf.php.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../aurox.php';

use App\AppUrls;
use App\Models\PostModel;
use OsdAurox\AppConfig;
use OsdAurox\Dbo;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

$posts = PostModel::getForRss($pdo, 20);

// safe: true = valeurs brutes de conf.php ; on les échappe nous-mêmes pour du XML
$appUrl = rtrim(AppConfig::get('appUrl', safe: true), '/');
$appName = AppConfig::get('appName', safe: true);
$appDescription = AppConfig::get('appDescription', safe: true);

// Échappement pour du XML (et non du HTML) : &, <, >, " et '
function xml(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// Date du flux = date du dernier article publié
$lastBuildDate = $posts ? date(DATE_RSS, strtotime($posts[0]['published_at'])) : date(DATE_RSS);

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
header('Content-Type: application/rss+xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
    <title><?= xml($appName) ?></title>
    <link><?= xml($appUrl . AppUrls::BLOG) ?></link>
    <description><?= xml($appDescription) ?></description>
    <language>fr</language>
    <lastBuildDate><?= xml($lastBuildDate) ?></lastBuildDate>
    <atom:link href="<?= xml($appUrl . AppUrls::RSS) ?>" rel="self" type="application/rss+xml"/>
<?php foreach ($posts as $post): ?>
<?php $postUrl = $appUrl . AppUrls::POST . '?slug=' . $post['slug']; ?>
    <item>
        <title><?= xml($post['title']) ?></title>
        <link><?= xml($postUrl) ?></link>
        <guid isPermaLink="true"><?= xml($postUrl) ?></guid>
        <pubDate><?= xml(date(DATE_RSS, strtotime($post['published_at']))) ?></pubDate>
        <dc:creator><?= xml($post['author_username']) ?></dc:creator>
        <description><?= xml($post['excerpt']) ?></description>
    </item>
<?php endforeach; ?>
</channel>
</rss>
