<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexion.php';

const CMS_ALLOWED_FIELDS = [
    'title', 'subtitle', 'heading', 'lead', 'content', 'button_text', 'button_url',
    'url', 'icon', 'color', 'number', 'category', 'position', 'twitter',
    'facebook', 'instagram', 'linkedin', 'name_label', 'email_label',
    'subject_label', 'message_label', 'filters',
];

function cms_json(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }
    try {
        $value = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        return is_array($value) ? $value : [];
    } catch (JsonException) {
        return [];
    }
}

function cms_clean_text(mixed $value, int $limit = 10000): string
{
    $text = trim((string) $value);
    $text = preg_replace('/<\s*\/?\s*(script|style|iframe|object|embed)[^>]*>/iu', '', $text) ?? '';
    $text = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/iu', '', $text) ?? '';
    return mb_substr(strip_tags($text), 0, $limit);
}

function cms_clean_url(mixed $value): string
{
    $url = trim((string) $value);
    if ($url === '' || str_starts_with($url, '#')) {
        return mb_substr($url, 0, 500);
    }
    if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
        return '';
    }
    if (preg_match('#^https?://#i', $url)) {
        return filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 500) : '';
    }
    return preg_match('#^[a-zA-Z0-9_./?=&%+\-]+$#', $url) ? mb_substr($url, 0, 500) : '';
}

function cms_clean_payload(array $payload): array
{
    $clean = [];
    foreach (CMS_ALLOWED_FIELDS as $field) {
        if (!array_key_exists($field, $payload)) {
            continue;
        }
        if ($field === 'filters' && is_array($payload[$field])) {
            $clean[$field] = array_map(static fn ($value): string => cms_clean_text($value, 80), $payload[$field]);
        } elseif (in_array($field, ['url', 'button_url', 'twitter', 'facebook', 'instagram', 'linkedin'], true)) {
            $clean[$field] = cms_clean_url($payload[$field]);
        } else {
            $clean[$field] = cms_clean_text($payload[$field], $field === 'content' ? 20000 : 500);
        }
    }
    return $clean;
}

function cms_media_row(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    $statement = db_connection()->prepare(
        'SELECT id, relative_path, thumbnail_path, medium_path, large_path, width, height, alt_text
         FROM media_library WHERE id = :id AND deleted_at IS NULL'
    );
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row ?: null;
}

function cms_media_url(?array $media, string $size = 'original'): string
{
    if (!$media) {
        return sietelsa_logo_url();
    }
    $column = ['thumbnail' => 'thumbnail_path', 'medium' => 'medium_path', 'large' => 'large_path'][$size] ?? 'relative_path';
    $path = (string) ($media[$column] ?: $media['relative_path']);
    return app_url($path);
}

function cms_snapshot(string $slug = 'inicio', bool $draft = false): array
{
    $draft = $draft && auth_is_admin();
    $mode = $draft ? 'draft' : 'published';
    $db = db_connection();
    $pageStatement = $db->prepare("SELECT *, {$mode}_seo AS selected_seo FROM pages WHERE slug = :slug LIMIT 1");
    $pageStatement->execute(['slug' => $slug]);
    $page = $pageStatement->fetch();
    if (!$page) {
        return ['page' => [], 'sections' => [], 'settings' => [], 'navigation' => []];
    }
    $page['seo'] = cms_json($page['selected_seo']);

    $sectionStatement = $db->prepare(
        "SELECT s.*, s.{$mode}_data AS selected_data, s.{$mode}_media_id AS selected_media_id,
                s.{$mode}_sort_order AS selected_sort_order, s.{$mode}_visible AS selected_visible
         FROM page_sections s WHERE s.page_id = :page_id
         ORDER BY s.{$mode}_sort_order, s.id"
    );
    $sectionStatement->execute(['page_id' => (int) $page['id']]);
    $sections = [];
    $itemStatement = $db->prepare(
        "SELECT i.*, i.{$mode}_data AS selected_data, i.{$mode}_media_id AS selected_media_id,
                i.{$mode}_sort_order AS selected_sort_order, i.{$mode}_visible AS selected_visible
         FROM content_items i WHERE i.section_id = :section_id
         ORDER BY i.{$mode}_sort_order, i.id"
    );
    foreach ($sectionStatement->fetchAll() as $section) {
        if (!$draft && !(int) $section['selected_visible']) {
            continue;
        }
        $section['data'] = cms_json($section['selected_data']);
        $section['media'] = cms_media_row($section['selected_media_id'] ? (int) $section['selected_media_id'] : null);
        $section['items'] = [];
        $itemStatement->execute(['section_id' => (int) $section['id']]);
        foreach ($itemStatement->fetchAll() as $item) {
            if (!$draft && !(int) $item['selected_visible']) {
                continue;
            }
            $item['data'] = cms_json($item['selected_data']);
            $item['media'] = cms_media_row($item['selected_media_id'] ? (int) $item['selected_media_id'] : null);
            $section['items'][] = $item;
        }
        $sections[$section['section_key']] = $section;
    }

    $settings = [];
    foreach ($db->query("SELECT setting_key, {$mode}_value AS selected_value FROM site_settings WHERE is_public = 1")->fetchAll() as $row) {
        $decoded = cms_json($row['selected_value']);
        $settings[$row['setting_key']] = $decoded['value'] ?? '';
    }
    $navigation = $db->query(
        "SELECT id, nav_key, {$mode}_label AS label, {$mode}_url AS url, {$mode}_target AS target,
                {$mode}_access AS access_level, {$mode}_visible AS visible, is_system
         FROM navigation_items ORDER BY {$mode}_sort_order, id"
    )->fetchAll();

    return compact('page', 'sections', 'settings', 'navigation');
}

function cms_edit_attrs(string $entity, int $id, string $sectionKey, string $contentKey, string $field): string
{
    return sprintf(
        ' data-cms-entity="%s" data-cms-id="%d" data-section-key="%s" data-content-key="%s" data-content-type="%s"',
        auth_escape($entity),
        $id,
        auth_escape($sectionKey),
        auth_escape($contentKey),
        auth_escape($field)
    );
}

function cms_version(PDO $db, string $entityType, int $entityId, string $entityKey, string $action, array $old, array $new): void
{
    $statement = $db->prepare(
        'INSERT INTO content_versions (entity_type, entity_id, entity_key, action, old_data, new_data, created_by)
         VALUES (:type, :id, :entity_key, :action, :old_data, :new_data, :user_id)'
    );
    $statement->execute([
        'type' => $entityType, 'id' => $entityId, 'entity_key' => $entityKey, 'action' => $action,
        'old_data' => json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'new_data' => json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'user_id' => (int) (auth_current_user()['id'] ?? 0),
    ]);
}

function cms_audit(PDO $db, string $action, string $type, int|string $id, array $details = []): void
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $statement = $db->prepare(
        'INSERT INTO admin_audit_log (user_id, action, entity_type, entity_id, details, ip_hash)
         VALUES (:user_id, :action, :type, :id, :details, :ip_hash)'
    );
    $statement->execute([
        'user_id' => (int) (auth_current_user()['id'] ?? 0), 'action' => $action, 'type' => $type,
        'id' => (string) $id, 'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
        'ip_hash' => $ip === '' ? null : hash('sha256', $ip . session_id()),
    ]);
}

function cms_save_content(string $entity, int $id, array $payload, ?int $mediaId, int $sortOrder, bool $visible, bool $publish): void
{
    $map = [
        'section' => ['page_sections', 'section_key'],
        'item' => ['content_items', 'item_key'],
    ];
    if (!isset($map[$entity]) || $id < 1) {
        throw new InvalidArgumentException('Contenido no válido.');
    }
    [$table, $keyColumn] = $map[$entity];
    $db = db_connection();
    $db->beginTransaction();
    try {
        $select = $db->prepare("SELECT * FROM {$table} WHERE id = :id FOR UPDATE");
        $select->execute(['id' => $id]);
        $old = $select->fetch();
        if (!$old) {
            throw new RuntimeException('El contenido solicitado no existe.');
        }
        if ($mediaId !== null && !cms_media_row($mediaId)) {
            throw new RuntimeException('La imagen seleccionada no existe.');
        }
        $json = json_encode(cms_clean_payload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $userId = (int) auth_current_user()['id'];
        $sql = "UPDATE {$table} SET draft_data = :data, draft_media_id = :media_id,
                draft_sort_order = :sort_order, draft_visible = :visible, status = :status, updated_by = :user_id";
        if ($publish) {
            $sql .= ', published_data = :published_data, published_media_id = :published_media_id,
                     published_sort_order = :published_sort_order, published_visible = :published_visible';
        }
        $sql .= ' WHERE id = :id';
        $params = [
            'data' => $json, 'media_id' => $mediaId, 'sort_order' => $sortOrder,
            'visible' => $visible ? 1 : 0, 'status' => $publish ? 'published' : 'draft',
            'user_id' => $userId, 'id' => $id,
        ];
        if ($publish) {
            $params += [
                'published_data' => $json, 'published_media_id' => $mediaId,
                'published_sort_order' => $sortOrder, 'published_visible' => $visible ? 1 : 0,
            ];
        }
        $db->prepare($sql)->execute($params);
        $new = $old;
        $new['draft_data'] = $json;
        $new['draft_media_id'] = $mediaId;
        if ($publish) {
            $new['published_data'] = $json;
            $new['published_media_id'] = $mediaId;
        }
        cms_version($db, $entity, $id, (string) $old[$keyColumn], $publish ? 'publish' : 'save_draft', $old, $new);
        cms_audit($db, $publish ? 'publish' : 'save_draft', $entity, $id);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}
