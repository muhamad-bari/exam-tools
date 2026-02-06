<?php
header('Content-Type: application/json');

try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Auto-initialize database schema if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        data TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Initialize Folders table
    $db->exec("CREATE TABLE IF NOT EXISTS folders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure sessions has folder_id
    $cols = $db->query("PRAGMA table_info(sessions)")->fetchAll();
    $hasFolderId = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'folder_id') {
            $hasFolderId = true;
            break;
        }
    }
    if (!$hasFolderId) {
        $db->exec("ALTER TABLE sessions ADD COLUMN folder_id INTEGER DEFAULT NULL");
    }

    // Ensure folders has parent_id
    $cols = $db->query("PRAGMA table_info(folders)")->fetchAll();
    $hasParentId = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'parent_id') {
            $hasParentId = true;
            break;
        }
    }
    if (!$hasParentId) {
        $db->exec("ALTER TABLE folders ADD COLUMN parent_id INTEGER DEFAULT NULL");
    }

    $action = $_GET['action'] ?? '';

    if ($action === 'list') {
        // Fetch all folders
        $foldersStmt = $db->query("SELECT * FROM folders ORDER BY name ASC");
        $folders = $foldersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Normalize parent_id to null for root folders
        foreach ($folders as &$folder) {
            if ($folder['parent_id'] === '' || $folder['parent_id'] === 0 || $folder['parent_id'] === '0') {
                $folder['parent_id'] = null;
            }
        }
        unset($folder); // Break reference

        // Fetch all sessions
        $stmt = $db->query("SELECT id, name, created_at, folder_id FROM sessions ORDER BY created_at DESC");
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Normalize folder_id to null for root sessions
        foreach ($sessions as &$session) {
            if ($session['folder_id'] === '' || $session['folder_id'] === 0 || $session['folder_id'] === '0') {
                $session['folder_id'] = null;
            }
        }
        unset($session); // Break reference
        
        echo json_encode(['success' => true, 'data' => $sessions, 'folders' => $folders]);
    }
    elseif ($action === 'rename_folder') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             throw new Exception('Invalid request method');
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? 0;
        $name = $input['name'] ?? '';
        
        if (empty($name)) throw new Exception('Name required');

        $stmt = $db->prepare("UPDATE folders SET name = :name WHERE id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Folder renamed']);
    }
    elseif ($action === 'move_item') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             throw new Exception('Invalid request method');
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? '';
        $id = isset($input['id']) ? intval($input['id']) : 0;
        $targetId = $input['target_id'] ?? null; // Null means root
        
        // Normalize target ID
        if ($targetId === 'root' || $targetId === 0 || $targetId === '0' || $targetId === '') {
            $targetId = null;
        } else {
            $targetId = intval($targetId);
        }

        if ($type === 'session') {
            $stmt = $db->prepare("UPDATE sessions SET folder_id = :target WHERE id = :id");
            $stmt->bindParam(':target', $targetId, $targetId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
        } 
        elseif ($type === 'folder') {
            // Strict comparison after normalization
            if ($id === $targetId) throw new Exception('Cannot move folder into itself');
            
            // Circular check: ensure target is not a descendant of id
            if ($targetId !== null) {
                $curr = $targetId;
                while ($curr !== null) {
                    if ($curr === $id) throw new Exception('Cannot move folder into its own subfolder');
                    $pStmt = $db->prepare("SELECT parent_id FROM folders WHERE id = :id");
                    $pStmt->bindParam(':id', $curr, PDO::PARAM_INT);
                    $pStmt->execute();
                    $res = $pStmt->fetch(PDO::FETCH_ASSOC);
                    $curr = $res && $res['parent_id'] !== null ? intval($res['parent_id']) : null;
                }
            }

            $stmt = $db->prepare("UPDATE folders SET parent_id = :target WHERE id = :id");
            $stmt->bindParam(':target', $targetId, $targetId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Item moved']);
    }
    elseif ($action === 'create_folder') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             throw new Exception('Invalid request method');
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? 'New Folder';
        $parentId = $input['parent_id'] ?? null;
        
        $stmt = $db->prepare("INSERT INTO folders (name, parent_id) VALUES (:name, :parent_id)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':parent_id', $parentId);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Folder created']);
    }
    elseif ($action === 'delete_folder') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             throw new Exception('Invalid request method');
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? intval($input['id']) : 0;
        
        // Update sessions in this folder to be in root (folder_id = NULL)
        $updateStmt = $db->prepare("UPDATE sessions SET folder_id = NULL WHERE folder_id = :id");
        $updateStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $updateStmt->execute();

        // Update subfolders to be in root (parent_id = NULL)
        $updateFolderStmt = $db->prepare("UPDATE folders SET parent_id = NULL WHERE parent_id = :id");
        $updateFolderStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $updateFolderStmt->execute();

        // Delete the folder itself
        $stmt = $db->prepare("DELETE FROM folders WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Folder deleted']);
    }
    elseif ($action === 'save') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid request method');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            // Fallback for FormData if not JSON body
             $input = $_POST;
        }

        $id = $input['id'] ?? null; // Check if ID is provided
        $name = $input['name'] ?? 'Untitled Session';
        $data = $input['data'] ?? '';
        $folderId = $input['folder_id'] ?? null;
        if ($folderId == '0' || $folderId === '') $folderId = null; // Normalize root

        if (empty($data)) {
            throw new Exception('Data cannot be empty');
        }

        if ($id) {
            // Update existing session
            $stmt = $db->prepare("UPDATE sessions SET name = :name, data = :data, folder_id = :folder_id, created_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':data', $data);
            $stmt->bindParam(':folder_id', $folderId);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $message = 'Session updated successfully';
        } else {
            // Create new session
            $stmt = $db->prepare("INSERT INTO sessions (name, data, folder_id) VALUES (:name, :data, :folder_id)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':data', $data);
            $stmt->bindParam(':folder_id', $folderId);
            $stmt->execute();
            $message = 'Session saved successfully';
        }

        echo json_encode(['success' => true, 'message' => $message]);
    } 
    elseif ($action === 'load') {
        $id = $_GET['id'] ?? 0;
        $stmt = $db->prepare("SELECT name, data, folder_id FROM sessions WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            echo json_encode([
                'success' => true, 
                'name' => $session['name'],
                'data' => json_decode($session['data'], true),
                'folder_id' => $session['folder_id']
            ]);
        } else {
            throw new Exception('Session not found');
        }
    } 
    elseif ($action === 'delete') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             throw new Exception('Invalid request method');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? $_POST['id'] ?? 0;

        $stmt = $db->prepare("DELETE FROM sessions WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Session deleted successfully']);
    } 
    elseif ($action === 'duplicate') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             throw new Exception('Invalid request method');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? 0;
        $newName = $input['name'] ?? null;

        if (!$id) throw new Exception('ID required');

        // Fetch original
        $stmt = $db->prepare("SELECT name, data, folder_id FROM sessions WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $original = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$original) throw new Exception('Original session not found');

        // Prepare new name
        $nameToUse = $newName ? $newName : ($original['name'] . ' (Copy)');
        $dataToUse = $original['data'];
        $folderId = $original['folder_id'];

        // Insert copy
        $stmt = $db->prepare("INSERT INTO sessions (name, data, folder_id) VALUES (:name, :data, :folder_id)");
        $stmt->bindParam(':name', $nameToUse);
        $stmt->bindParam(':data', $dataToUse);
        $stmt->bindParam(':folder_id', $folderId);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Session duplicated successfully']);
    }
    elseif ($action === 'rename') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             throw new Exception('Invalid request method');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? 0;
        $newName = $input['name'] ?? '';

        if (!$id || empty($newName)) throw new Exception('ID and new name required');

        $stmt = $db->prepare("UPDATE sessions SET name = :name WHERE id = :id");
        $stmt->bindParam(':name', $newName);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Session renamed successfully']);
    }
    else {
        throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>