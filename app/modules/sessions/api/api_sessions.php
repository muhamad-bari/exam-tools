<?php
require_once __DIR__ . '/../../../bootstrap.php';
app_require_router_request(true);

header('Content-Type: application/json; charset=utf-8');

require_once PROJECT_ROOT . '/app/shared/lib/database.php';

try {
    $db = getDatabaseConnection();
    $action = $_GET['action'] ?? '';

    if ($action === 'list') {
        $folders = $db->query('SELECT id, name, parent_id, created_at FROM folders ORDER BY name ASC')->fetchAll();
        foreach ($folders as &$folder) {
            $folder['id'] = (int) $folder['id'];
            $folder['parent_id'] = normalizeRootId($folder['parent_id'] ?? null);
        }
        unset($folder);

        $sessions = $db->query('SELECT id, name, created_at, folder_id FROM sessions ORDER BY created_at DESC, id DESC')->fetchAll();
        foreach ($sessions as &$session) {
            $session['id'] = (int) $session['id'];
            $session['folder_id'] = normalizeRootId($session['folder_id'] ?? null);
        }
        unset($session);

        echo json_encode(['success' => true, 'data' => $sessions, 'folders' => $folders]);
        exit;
    }

    if ($action === 'load') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Session ID tidak valid');
        }

        $stmt = $db->prepare('SELECT id, name, data, folder_id FROM sessions WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $session = $stmt->fetch();

        if (!$session) {
            throw new RuntimeException('Session tidak ditemukan');
        }

        $decoded = json_decode($session['data'], true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        echo json_encode([
            'success' => true,
            'id' => (int) $session['id'],
            'name' => $session['name'],
            'folder_id' => normalizeRootId($session['folder_id'] ?? null),
            'data' => $decoded,
        ]);
        exit;
    }

    if ($action === 'save') {
        ensurePostRequest();
        $input = readJsonInput();
        if (!$input) {
            $input = $_POST;
        }

        $name = trim((string) ($input['name'] ?? ''));
        $rawData = $input['data'] ?? null;
        $id = intval($input['id'] ?? 0);
        $folderId = normalizeRootId($input['folder_id'] ?? null);

        if ($name === '') {
            throw new RuntimeException('Nama session wajib diisi');
        }

        if (is_array($rawData)) {
            $payload = json_encode($rawData, JSON_UNESCAPED_UNICODE);
        } else {
            $decoded = json_decode((string) $rawData, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Data session tidak valid');
            }
            $payload = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        if ($id > 0) {
            $stmt = $db->prepare('UPDATE sessions SET name = :name, data = :data, folder_id = :folder_id WHERE id = :id');
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':data', $payload, PDO::PARAM_STR);
            if ($folderId === null) {
                $stmt->bindValue(':folder_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':folder_id', $folderId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $sessionId = $id;
            $message = 'Session berhasil diperbarui';
        } else {
            $stmt = $db->prepare('INSERT INTO sessions (name, data, folder_id) VALUES (:name, :data, :folder_id)');
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':data', $payload, PDO::PARAM_STR);
            if ($folderId === null) {
                $stmt->bindValue(':folder_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':folder_id', $folderId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $sessionId = (int) $db->lastInsertId();
            $message = 'Session berhasil disimpan';
        }

        echo json_encode(['success' => true, 'id' => $sessionId, 'message' => $message]);
        exit;
    }

    if ($action === 'delete') {
        ensurePostRequest();
        $input = readJsonInput();
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Session ID tidak valid');
        }

        $stmt = $db->prepare('DELETE FROM sessions WHERE id = :id');
        $stmt->execute([':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Session berhasil dihapus']);
        exit;
    }

    if ($action === 'rename') {
        ensurePostRequest();
        $input = readJsonInput();
        $id = intval($input['id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        if ($id <= 0 || $name === '') {
            throw new RuntimeException('Data rename tidak valid');
        }

        $stmt = $db->prepare('UPDATE sessions SET name = :name WHERE id = :id');
        $stmt->execute([':name' => $name, ':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Session berhasil diubah']);
        exit;
    }

    if ($action === 'duplicate') {
        ensurePostRequest();
        $input = readJsonInput();
        $id = intval($input['id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        if ($id <= 0 || $name === '') {
            throw new RuntimeException('Data duplikasi tidak valid');
        }

        $stmt = $db->prepare('SELECT data, folder_id FROM sessions WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $session = $stmt->fetch();
        if (!$session) {
            throw new RuntimeException('Session sumber tidak ditemukan');
        }

        $insert = $db->prepare('INSERT INTO sessions (name, data, folder_id) VALUES (:name, :data, :folder_id)');
        $insert->bindValue(':name', $name, PDO::PARAM_STR);
        $insert->bindValue(':data', $session['data'], PDO::PARAM_STR);
        if (($session['folder_id'] ?? null) === null || $session['folder_id'] === '') {
            $insert->bindValue(':folder_id', null, PDO::PARAM_NULL);
        } else {
            $insert->bindValue(':folder_id', intval($session['folder_id']), PDO::PARAM_INT);
        }
        $insert->execute();

        echo json_encode(['success' => true, 'id' => (int) $db->lastInsertId(), 'message' => 'Session berhasil diduplikasi']);
        exit;
    }

    if ($action === 'duplicate_folder') {
        ensurePostRequest();
        $input = readJsonInput();
        $id = intval($input['id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        if ($id <= 0 || $name === '') {
            throw new RuntimeException('Data duplikasi folder tidak valid');
        }

        $newFolderId = duplicateFolderTree($db, $id, $name);

        echo json_encode(['success' => true, 'id' => $newFolderId, 'message' => 'Folder berhasil diduplikasi']);
        exit;
    }

    if ($action === 'rename_folder') {
        ensurePostRequest();
        $input = readJsonInput();
        $id = intval($input['id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        if ($id <= 0 || $name === '') {
            throw new RuntimeException('Data folder tidak valid');
        }

        $stmt = $db->prepare('UPDATE folders SET name = :name WHERE id = :id');
        $stmt->execute([':name' => $name, ':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Folder berhasil diubah']);
        exit;
    }

    if ($action === 'create_folder') {
        ensurePostRequest();
        $input = readJsonInput();
        $name = trim((string) ($input['name'] ?? 'New Folder'));
        $parentId = normalizeRootId($input['parent_id'] ?? null);
        if ($name === '') {
            throw new RuntimeException('Nama folder wajib diisi');
        }

        $stmt = $db->prepare('INSERT INTO folders (name, parent_id) VALUES (:name, :parent_id)');
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        if ($parentId === null) {
            $stmt->bindValue(':parent_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
        }
        $stmt->execute();

        echo json_encode(['success' => true, 'id' => (int) $db->lastInsertId(), 'message' => 'Folder berhasil dibuat']);
        exit;
    }

    if ($action === 'delete_folder') {
        ensurePostRequest();
        $input = readJsonInput();
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Folder ID tidak valid');
        }

        $db->beginTransaction();
        try {
            $updateSessions = $db->prepare('UPDATE sessions SET folder_id = NULL WHERE folder_id = :id');
            $updateSessions->execute([':id' => $id]);

            $updateFolders = $db->prepare('UPDATE folders SET parent_id = NULL WHERE parent_id = :id');
            $updateFolders->execute([':id' => $id]);

            $deleteFolder = $db->prepare('DELETE FROM folders WHERE id = :id');
            $deleteFolder->execute([':id' => $id]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        echo json_encode(['success' => true, 'message' => 'Folder berhasil dihapus']);
        exit;
    }

    if ($action === 'move_item') {
        ensurePostRequest();
        $input = readJsonInput();
        $type = trim((string) ($input['type'] ?? ''));
        $id = intval($input['id'] ?? 0);
        $targetId = normalizeRootId($input['target_id'] ?? null);

        if ($id <= 0 || ($type !== 'session' && $type !== 'folder')) {
            throw new RuntimeException('Data pemindahan tidak valid');
        }

        if ($type === 'session') {
            $stmt = $db->prepare('UPDATE sessions SET folder_id = :target WHERE id = :id');
            if ($targetId === null) {
                $stmt->bindValue(':target', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':target', $targetId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            if ($id === $targetId) {
                throw new RuntimeException('Folder tidak dapat dipindahkan ke dirinya sendiri');
            }

            if ($targetId !== null) {
                $current = $targetId;
                while ($current !== null) {
                    if ($current === $id) {
                        throw new RuntimeException('Folder tidak dapat dipindahkan ke subfolder sendiri');
                    }
                    $check = $db->prepare('SELECT parent_id FROM folders WHERE id = :id LIMIT 1');
                    $check->execute([':id' => $current]);
                    $row = $check->fetch();
                    $current = $row && $row['parent_id'] !== null ? intval($row['parent_id']) : null;
                }
            }

            $stmt = $db->prepare('UPDATE folders SET parent_id = :target WHERE id = :id');
            if ($targetId === null) {
                $stmt->bindValue(':target', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':target', $targetId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
        }

        echo json_encode(['success' => true, 'message' => 'Item berhasil dipindahkan']);
        exit;
    }

    throw new RuntimeException('Invalid action');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

function ensurePostRequest()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Invalid request method');
    }
}

function readJsonInput()
{
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function duplicateFolderTree(PDO $db, $sourceFolderId, $newRootName)
{
    $sourceFolderId = (int) $sourceFolderId;
    $newRootName = trim((string) $newRootName);

    $stmt = $db->prepare('SELECT id, name, parent_id FROM folders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $sourceFolderId]);
    $folder = $stmt->fetch();

    if (!$folder) {
        throw new RuntimeException('Folder sumber tidak ditemukan');
    }

    $db->beginTransaction();
    try {
        $newFolderId = duplicateFolderNode($db, $sourceFolderId, normalizeRootId($folder['parent_id'] ?? null), $newRootName);
        $db->commit();
        return $newFolderId;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function duplicateFolderNode(PDO $db, $sourceFolderId, $targetParentId, $folderNameOverride = null)
{
    $sourceFolderId = (int) $sourceFolderId;
    $targetParentId = normalizeRootId($targetParentId);

    $folderStmt = $db->prepare('SELECT id, name FROM folders WHERE id = :id LIMIT 1');
    $folderStmt->execute([':id' => $sourceFolderId]);
    $folder = $folderStmt->fetch();

    if (!$folder) {
        throw new RuntimeException('Folder sumber tidak ditemukan');
    }

    $insertFolder = $db->prepare('INSERT INTO folders (name, parent_id) VALUES (:name, :parent_id)');
    $insertFolder->bindValue(':name', $folderNameOverride !== null ? trim((string) $folderNameOverride) : $folder['name'], PDO::PARAM_STR);
    if ($targetParentId === null) {
        $insertFolder->bindValue(':parent_id', null, PDO::PARAM_NULL);
    } else {
        $insertFolder->bindValue(':parent_id', $targetParentId, PDO::PARAM_INT);
    }
    $insertFolder->execute();

    $newFolderId = (int) $db->lastInsertId();

    $sessionStmt = $db->prepare('SELECT name, data FROM sessions WHERE folder_id = :folder_id ORDER BY created_at ASC, id ASC');
    $sessionStmt->execute([':folder_id' => $sourceFolderId]);
    $sessions = $sessionStmt->fetchAll();

    if ($sessions) {
        $insertSession = $db->prepare('INSERT INTO sessions (name, data, folder_id) VALUES (:name, :data, :folder_id)');
        foreach ($sessions as $session) {
            $insertSession->bindValue(':name', $session['name'], PDO::PARAM_STR);
            $insertSession->bindValue(':data', $session['data'], PDO::PARAM_STR);
            $insertSession->bindValue(':folder_id', $newFolderId, PDO::PARAM_INT);
            $insertSession->execute();
        }
    }

    $childStmt = $db->prepare('SELECT id FROM folders WHERE parent_id = :parent_id ORDER BY created_at ASC, id ASC');
    $childStmt->execute([':parent_id' => $sourceFolderId]);
    $children = $childStmt->fetchAll();

    foreach ($children as $child) {
        duplicateFolderNode($db, (int) $child['id'], $newFolderId);
    }

    return $newFolderId;
}
