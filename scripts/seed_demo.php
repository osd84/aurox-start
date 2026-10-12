<?php
// -----------------------------------------------------------------------------
// Crée quelques articles de démonstration, signés par le premier admin.
//
//   php scripts/seed_demo.php --dry-run   affiche ce qui serait fait
//   php scripts/seed_demo.php             crée les articles
//
// Idempotent : un article dont le slug existe déjà est ignoré, relancer ne duplique rien.
// -----------------------------------------------------------------------------

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../aurox.php';

use App\Models\PostModel;
use App\Models\UserModel;
use OsdAurox\Dbo;

$dryRun = in_array('--dry-run', $argv, true);

$pdo = Dbo::getPdo();

$admins = UserModel::getAllBy($pdo, 'role', UserModel::ROLE_ADMIN, orderBy: 'id', limit: 1);
if (!$admins) {
    echo "Aucun admin : lancez d'abord php scripts/create_admin.php\n";
    exit(1);
}
$authorId = (int) $admins[0]['id'];

// Les articles, écrits en clair. Les dates s'étalent dans le passé pour que l'ordre du blog ait un sens.
$posts = [
    [
        'title' => 'Bienvenue sur Aurox Start',
        'excerpt' => 'Ce blog est la démo : chaque page que vous lisez est un fichier PHP de public/, à lire de haut en bas.',
        'content' => "Aurox Start est une petite application complète pour apprendre Aurox.\n\n"
            . "Ouvrez public/post.php à côté de cette page : le bootstrap, la lecture du slug avec Sec::getParam(), "
            . "la requête du modèle, puis le HTML. Rien d'autre.\n\n"
            . "Connectez-vous avec le compte créé par scripts/create_admin.php pour modifier cet article.",
        'status' => PostModel::STATUS_PUBLISHED,
        'days_ago' => 1,
    ],
    [
        'title' => 'Le trajet d\'une requête',
        'excerpt' => 'Apache sert public/, la page inclut aurox.php, aurox.php prépare tout, la page fait son travail.',
        'content' => "1. Apache reçoit /blog.php et exécute public/blog.php.\n"
            . "2. La page inclut aurox.php : conf.php, session, en-têtes de sécurité, base, WAF, i18n.\n"
            . "3. AppUrls::existOr404() vérifie que l'URL est déclarée dans app/AppUrls.php.\n"
            . "4. Partie Controller : paramètres, requêtes, redirections.\n"
            . "5. Partie View : le header, le HTML, le footer.\n\n"
            . "Pas de routeur, pas de conteneur, pas de middleware : un fichier par page.",
        'status' => PostModel::STATUS_PUBLISHED,
        'days_ago' => 2,
    ],
    [
        'title' => 'Un modèle, c\'est du SQL écrit en entier',
        'excerpt' => 'BaseModel donne get(), getBy(), delete(), check_uniq(). L\'INSERT et l\'UPDATE, on les écrit.',
        'content' => "Ouvrez app/Models/PostModel.php.\n\n"
            . "Chaque méthode est statique et reçoit le PDO. Chaque colonne est nommée une par une, "
            . "chaque valeur passe par un paramètre PDO (:title, :slug...).\n\n"
            . "Ajouter une colonne : la migration, l'INSERT, l'UPDATE, une ligne dans la page d'édition, un champ dans la vue. "
            . "C'est plus long qu'un ORM, et on voit tout.",
        'status' => PostModel::STATUS_PUBLISHED,
        'days_ago' => 3,
    ],
    [
        'title' => 'Un formulaire à plat',
        'excerpt' => 'Lire, valider, enregistrer, rediriger. Et en cas d\'erreur, réafficher la saisie.',
        'content' => "La recette est dans public/admin/post_edit.php :\n\n"
            . "- \$postData : une ligne Sec::getParam() par colonne\n"
            . "- \$errors['champ'][] = 'message' : une condition par règle\n"
            . "- pas d'erreur : insert() ou update(), Flash::success(), Base::redirect()\n"
            . "- des erreurs : FormValidator les porte, Forms les affiche sous chaque champ.",
        'status' => PostModel::STATUS_PUBLISHED,
        'days_ago' => 4,
    ],
    [
        'title' => 'Sécurisé par défaut',
        'excerpt' => 'CSRF, échappement, rate-limit, CSP avec nonce, WAF : ce que fait aurox sans qu\'on le demande.',
        'content' => "Filtrer en entrée : Sec::getParam('id', 'int').\n"
            . "Échapper en sortie : Sec::hNoHtml(\$valeur), partout.\n"
            . "Protéger les formulaires : Csrf::protect() en tête de page, Csrf::inputHtml() dans le form.\n"
            . "Limiter : Sec::setRateLimit() sur la connexion et l'inscription.\n\n"
            . "Essayez d'écrire <script>alert(1)</script> dans un article : il s'affiche, il ne s'exécute pas.",
        'status' => PostModel::STATUS_PUBLISHED,
        'days_ago' => 5,
    ],
    [
        'title' => 'Paginer sans rien installer',
        'excerpt' => 'Paginator prend deux requêtes (les lignes, le total) et s\'occupe de LIMIT / OFFSET.',
        'content' => "Ce blog affiche 5 articles par page. Regardez l'URL en changeant de page : ?per_page=5&page=2.\n\n"
            . "Le modèle rend query, count_query et bind_params. Le Paginator compte, découpe et dessine les liens.",
        'status' => PostModel::STATUS_PUBLISHED,
        'days_ago' => 6,
    ],
    [
        'title' => 'Ajax, à la jQuery',
        'excerpt' => 'Un endpoint dans public/ajax/, une réponse OsdAurox\\Api, un $.post() dans la page.',
        'content' => "Dans l'administration, cliquez sur le statut d'un article : il passe de Brouillon à Publié sans recharger.\n\n"
            . "Côté serveur : public/ajax/post_toggle_status.php. Côté navigateur : le <script nonce> en bas de public/admin/posts.php.",
        'status' => PostModel::STATUS_PUBLISHED,
        'days_ago' => 7,
    ],
    [
        'title' => 'Brouillon : invisible du public',
        'excerpt' => 'Cet article est un brouillon. Il est dans l\'administration, pas sur le blog.',
        'content' => "PostModel::getPublishedBySlug() filtre sur status = 'published' : même avec la bonne URL, un brouillon renvoie un 404.",
        'status' => PostModel::STATUS_DRAFT,
        'days_ago' => 0,
    ],
];

$created = 0;
$skipped = 0;

foreach ($posts as $post) {
    $slug = PostModel::slugify($post['title']);

    if (!PostModel::check_uniq($pdo, 'slug', $slug)) {
        echo "= existe déjà : $slug\n";
        $skipped++;
        continue;
    }

    $publishedAt = null;
    if ($post['status'] === PostModel::STATUS_PUBLISHED) {
        $publishedAt = date('Y-m-d H:i:s', strtotime('-' . $post['days_ago'] . ' days'));
    }

    if ($dryRun) {
        echo "+ (dry-run) $slug\n";
        $created++;
        continue;
    }

    PostModel::insert($pdo, [
        'title' => $post['title'],
        'slug' => $slug,
        'excerpt' => $post['excerpt'],
        'content' => $post['content'],
        'status' => $post['status'],
        'published_at' => $publishedAt,
    ], $authorId);

    echo "+ créé : $slug\n";
    $created++;
}

echo ($dryRun ? '[dry-run] ' : '') . "$created créé(s), $skipped ignoré(s).\n";
