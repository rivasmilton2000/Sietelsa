<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/content.php';
auth_require_admin();
header('Content-Type: application/json; charset=UTF-8');

function api_reply(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $db = db_connection();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $entity = (string) ($_GET['entity'] ?? '');
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $tables = ['section' => 'page_sections', 'item' => 'content_items'];
        if (!isset($tables[$entity]) || !$id) api_reply(['ok' => false, 'message' => 'Solicitud no válida.'], 422);
        $statement = $db->prepare("SELECT * FROM {$tables[$entity]} WHERE id=:id");
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!$row) api_reply(['ok' => false, 'message' => 'Contenido no encontrado.'], 404);
        api_reply(['ok' => true, 'entity' => $entity, 'id' => (int) $id, 'data' => cms_json($row['draft_data']), 'media_id' => $row['draft_media_id'], 'sort_order' => $row['draft_sort_order'], 'visible' => (bool) $row['draft_visible'], 'status' => $row['status'], 'updated_at' => $row['updated_at']]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_reply(['ok' => false, 'message' => 'Método no permitido.'], 405);
    $input = json_decode(file_get_contents('php://input') ?: '{}', true, 32, JSON_THROW_ON_ERROR);
    if (!auth_validate_csrf($input['csrf_token'] ?? null)) api_reply(['ok' => false, 'message' => 'La solicitud expiró.'], 419);
    $action = (string) ($input['action'] ?? 'save');
    if ($action === 'restore') {
        $versionId = (int) ($input['version_id'] ?? 0);
        $statement = $db->prepare('SELECT * FROM content_versions WHERE id=:id');
        $statement->execute(['id' => $versionId]);
        $version = $statement->fetch();
        if (!$version) api_reply(['ok' => false, 'message' => 'Versión no encontrada.'], 404);
        $old = cms_json($version['old_data']);
        $map = ['section' => 'page_sections', 'item' => 'content_items', 'navigation' => 'navigation_items', 'setting' => 'site_settings', 'page' => 'pages'];
        if (!isset($map[$version['entity_type']]) || !$old) api_reply(['ok' => false, 'message' => 'Esta versión no puede restaurarse.'], 422);
        $table = $map[$version['entity_type']];
        $allowed = [
            'section' => ['draft_data','published_data','draft_media_id','published_media_id','draft_sort_order','published_sort_order','draft_visible','published_visible','status'],
            'item' => ['draft_data','published_data','draft_media_id','published_media_id','draft_sort_order','published_sort_order','draft_visible','published_visible','status'],
            'navigation' => ['draft_label','published_label','draft_url','published_url','draft_target','published_target','draft_access','published_access','draft_sort_order','published_sort_order','draft_visible','published_visible','status'],
            'setting' => ['draft_value','published_value','status'],
            'page' => ['draft_seo','published_seo','status','is_visible'],
        ][$version['entity_type']];
        $sets = []; $params = ['id' => (int) $version['entity_id']];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $old)) { $sets[] = "$column=:$column"; $params[$column] = $old[$column]; }
        }
        if (!$sets) api_reply(['ok' => false, 'message' => 'La versión no contiene datos restaurables.'], 422);
        $db->prepare("UPDATE {$table} SET " . implode(',', $sets) . ' WHERE id=:id')->execute($params);
        cms_version($db, $version['entity_type'], (int) $version['entity_id'], $version['entity_key'], 'restore', [], $old);
        cms_audit($db, 'restore', $version['entity_type'], (int) $version['entity_id'], ['version_id' => $versionId]);
        api_reply(['ok' => true, 'message' => 'Versión restaurada.']);
    }
    cms_save_content(
        (string) ($input['entity'] ?? ''),
        (int) ($input['id'] ?? 0),
        is_array($input['data'] ?? null) ? $input['data'] : [],
        ($input['media_id'] ?? null) === null ? null : (int) $input['media_id'],
        (int) ($input['sort_order'] ?? 0),
        (bool) ($input['visible'] ?? true),
        $action === 'publish'
    );
    api_reply(['ok' => true, 'message' => $action === 'publish' ? 'Contenido publicado.' : 'Borrador guardado.']);
} catch (Throwable $exception) {
    error_log('[SIETELSA] API CMS: ' . $exception->getMessage());
    api_reply(['ok' => false, 'message' => 'No fue posible procesar la solicitud.'], 500);
}
