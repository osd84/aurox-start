<?php

namespace App\Models;

use OsdAurox\BaseModel;
use OsdAurox\Dbo;
use OsdAurox\Sec;
use PDO;

/**
 * Les articles du blog.
 *
 * Même recette que UserModel : méthodes statiques, SQL écrit en entier,
 * chaque colonne nommée une par une.
 */
class PostModel extends BaseModel
{
    public const TABLE = 'post';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public static function getSelectStatus(): array
    {
        return [
            ['id' => self::STATUS_DRAFT, 'name' => 'Brouillon'],
            ['id' => self::STATUS_PUBLISHED, 'name' => 'Publié'],
        ];
    }

    /**
     * Requêtes du blog public (articles publiés, du plus récent au plus ancien), pour le Paginator.
     * L'auteur vient d'une jointure : on récupère son nom dans la même requête.
     */
    public static function listPublishedQueries(): array
    {
        $query = "SELECT p.id, p.title, p.slug, p.excerpt, p.published_at, u.username AS author_username
                  FROM post p
                  JOIN users u ON u.id = p.user_id
                  WHERE p.status = 'published'
                  ORDER BY p.published_at DESC, p.id DESC";
        $countQuery = "SELECT COUNT(*) FROM post WHERE status = 'published'";

        return [
            'query' => $query,
            'count_query' => $countQuery,
            'bind_params' => [],
        ];
    }

    /**
     * Requêtes de la liste admin, pour le Paginator.
     *
     * @param string|null $searchLike motif LIKE déjà préparé ('%TEXTE%'), null = pas de filtre
     */
    public static function listQueries(?string $searchLike): array
    {
        $where = '';
        $bindParams = [];
        if ($searchLike) {
            $where = ' WHERE UPPER(p.title) LIKE :s1 OR UPPER(p.slug) LIKE :s2';
            $bindParams = [':s1' => $searchLike, ':s2' => $searchLike];
        }

        $query = 'SELECT p.id, p.title, p.slug, p.status, p.published_at, p.created_at, u.username AS author_username
                  FROM post p
                  JOIN users u ON u.id = p.user_id' . $where . '
                  ORDER BY p.id DESC';
        $countQuery = 'SELECT COUNT(*) FROM post p' . $where;

        return [
            'query' => $query,
            'count_query' => $countQuery,
            'bind_params' => $bindParams,
        ];
    }

    /**
     * Un article publié, par son slug, avec le nom de son auteur. null si absent ou brouillon.
     */
    public static function getPublishedBySlug(PDO $pdo, string $slug): ?array
    {
        $stmt = $pdo->prepare("SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.published_at, p.updated_at,
                                      u.username AS author_username
                               FROM post p
                               JOIN users u ON u.id = p.user_id
                               WHERE p.slug = :slug AND p.status = 'published'");
        $stmt->execute(['slug' => $slug]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        return $post ?: null;
    }

    /**
     * Les derniers articles publiés, pour la page d'accueil.
     */
    public static function getLatestPublished(PDO $pdo, int $limit = 3): array
    {
        $stmt = $pdo->prepare("SELECT id, title, slug, excerpt, published_at
                               FROM post
                               WHERE status = 'published'
                               ORDER BY published_at DESC, id DESC
                               LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Les derniers articles publiés pour le flux RSS, avec le nom de l'auteur.
     */
    public static function getForRss(PDO $pdo, int $limit = 20): array
    {
        $stmt = $pdo->prepare("SELECT p.id, p.title, p.slug, p.excerpt, p.published_at, u.username AS author_username
                               FROM post p
                               JOIN users u ON u.id = p.user_id
                               WHERE p.status = 'published'
                               ORDER BY p.published_at DESC, p.id DESC
                               LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function countByStatus(PDO $pdo, string $status): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM post WHERE status = :status');
        $stmt->execute(['status' => $status]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return int id de l'article créé
     */
    public static function insert(PDO $pdo, array $post, int $byUserId): int
    {
        $sql = 'INSERT INTO post (title, slug, excerpt, content, status, published_at, user_id, created_at, created_by)
                VALUES (:title, :slug, :excerpt, :content, :status, :published_at, :user_id, :created_at, :created_by)';
        // lastInsertId() demande un nom de séquence en PostgreSQL : on préfère RETURNING id
        if (Dbo::isPgsql()) {
            $sql .= ' RETURNING id';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'title' => $post['title'],
            'slug' => $post['slug'],
            'excerpt' => $post['excerpt'],
            'content' => $post['content'],
            'status' => $post['status'],
            'published_at' => $post['published_at'],
            'user_id' => $byUserId,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $byUserId,
        ]);

        if (Dbo::isPgsql()) {
            return (int) $stmt->fetchColumn();
        }
        return (int) $pdo->lastInsertId();
    }

    /**
     * L'auteur (user_id) ne change pas à la mise à jour : seul updated_by trace qui a modifié.
     */
    public static function update(PDO $pdo, int $id, array $post, int $byUserId): void
    {
        $stmt = $pdo->prepare('UPDATE post SET
                    title = :title,
                    slug = :slug,
                    excerpt = :excerpt,
                    content = :content,
                    status = :status,
                    published_at = :published_at,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE id = :id');
        $stmt->execute([
            'title' => $post['title'],
            'slug' => $post['slug'],
            'excerpt' => $post['excerpt'],
            'content' => $post['content'],
            'status' => $post['status'],
            'published_at' => $post['published_at'],
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $byUserId,
            'id' => $id,
        ]);
    }

    /**
     * Change uniquement le statut (bouton publier / dépublier de la liste admin, en ajax).
     * La date de publication est posée au premier passage en "publié", puis conservée.
     */
    public static function updateStatus(PDO $pdo, int $id, string $status, int $byUserId): void
    {
        $stmt = $pdo->prepare('UPDATE post SET
                    status = :status,
                    published_at = COALESCE(published_at, :published_at),
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'published_at' => $status === self::STATUS_PUBLISHED ? date('Y-m-d H:i:s') : null,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $byUserId,
            'id' => $id,
        ]);
    }

    /**
     * Fabrique un slug à partir d'un titre : "Été à Paris !" -> "ete-a-paris".
     * Sec::sanitize(..., 'slug') ne garde que a-z, 0-9 et "-" : on remplace donc les accents avant,
     * avec une table écrite en clair (iconv //TRANSLIT dépend de la locale du serveur).
     */
    public static function slugify(string $title): string
    {
        $title = mb_strtolower($title, 'UTF-8');
        $title = strtr($title, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
            'ñ' => 'n',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ÿ' => 'y', 'ý' => 'y',
            'æ' => 'ae', 'œ' => 'oe', 'ß' => 'ss',
        ]);
        return Sec::sanitize($title, 'slug');
    }
}
