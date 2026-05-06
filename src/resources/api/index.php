<?php
/**
 * Course Resources API
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$db     = getDBConnection();
$method = $_SERVER["REQUEST_METHOD"];
$raw    = file_get_contents("php://input");
$data   = json_decode($raw, true) ?? [];

$action     = isset($_GET["action"])      ? trim($_GET["action"])      : "";
$id         = isset($_GET["id"])          ? (int)$_GET["id"]          : null;
$resourceId = isset($_GET["resource_id"]) ? (int)$_GET["resource_id"] : null;
$commentId  = isset($_GET["comment_id"])  ? (int)$_GET["comment_id"]  : null;

// ============================================================================
// RESOURCE FUNCTIONS
// ============================================================================

function getAllResources($db) {
    $search = isset($_GET["search"]) ? trim($_GET["search"]) : "";
    $sort   = isset($_GET["sort"])   ? trim($_GET["sort"])   : "created_at";
    $order  = isset($_GET["order"])  ? trim($_GET["order"])  : "desc";

    $allowedSort  = ["title", "created_at"];
    $allowedOrder = ["asc", "desc"];
    if (!in_array($sort,  $allowedSort))  $sort  = "created_at";
    if (!in_array($order, $allowedOrder)) $order = "desc";

    $sql    = "SELECT id, title, description, link, created_at FROM resources";
    $params = [];

    if ($search !== "") {
        $sql .= " WHERE title LIKE :search OR description LIKE :search2";
        $params[":search"]  = "%" . $search . "%";
        $params[":search2"] = "%" . $search . "%";
    }

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(["success" => true, "data" => $resources]);
}

function getResourceById($db, $resourceId) {
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(["success" => false, "message" => "Invalid resource ID"], 400);
    }

    $stmt = $db->prepare(
        "SELECT id, title, description, link, created_at FROM resources WHERE id = ?"
    );
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {
        sendResponse(["success" => false, "message" => "Resource not found."], 404);
    }

    sendResponse(["success" => true, "data" => $resource]);
}

function createResource($db, $data) {
    $title       = trim($data["title"]       ?? "");
    $description = trim($data["description"] ?? "");
    $link        = trim($data["link"]        ?? "");

    if (!$title) sendResponse(["success" => false, "message" => "Title is required."], 400);
    if (!$link)  sendResponse(["success" => false, "message" => "Link is required."], 400);

    if (!filter_var($link, FILTER_VALIDATE_URL)) {
        sendResponse(["success" => false, "message" => "Invalid URL."], 400);
    }

    $stmt = $db->prepare(
        "INSERT INTO resources (title, description, link) VALUES (?, ?, ?)"
    );
    $stmt->execute([$title, $description, $link]);

    if ($stmt->rowCount() > 0) {
        $newId = $db->lastInsertId();
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Resource created.", "id" => $newId]);
        exit;
    }

    sendResponse(["success" => false, "message" => "Failed to create resource."], 500);
}

function updateResource($db, $data) {
    $id = isset($data["id"]) ? (int)$data["id"] : 0;

    if (!$id) sendResponse(["success" => false, "message" => "ID is required."], 400);

    $chk = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) sendResponse(["success" => false, "message" => "Resource not found."], 404);

    $fields = [];
    $params = [];

    if (isset($data["title"]) && trim($data["title"]) !== "") {
        $fields[] = "title = ?";
        $params[] = trim($data["title"]);
    }
    if (isset($data["description"])) {
        $fields[] = "description = ?";
        $params[] = trim($data["description"]);
    }
    if (isset($data["link"]) && trim($data["link"]) !== "") {
        $link = trim($data["link"]);
        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            sendResponse(["success" => false, "message" => "Invalid URL."], 400);
        }
        $fields[] = "link = ?";
        $params[] = $link;
    }

    if (empty($fields)) sendResponse(["success" => false, "message" => "No fields to update."], 400);

    $params[] = $id;
    $stmt = $db->prepare("UPDATE resources SET " . implode(", ", $fields) . " WHERE id = ?");
    $stmt->execute($params);

    sendResponse(["success" => true, "message" => "Resource updated successfully."]);
}

function deleteResource($db, $resourceId) {
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(["success" => false, "message" => "Invalid resource ID."], 400);
    }

    $chk = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $chk->execute([$resourceId]);
    if (!$chk->fetch()) sendResponse(["success" => false, "message" => "Resource not found."], 404);

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$resourceId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(["success" => true, "message" => "Resource deleted successfully."]);
    }
    sendResponse(["success" => false, "message" => "Failed to delete resource."], 500);
}

// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

function getCommentsByResourceId($db, $resourceId) {
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(["success" => false, "message" => "Invalid resource ID."], 400);
    }

    $stmt = $db->prepare(
        "SELECT id, resource_id, author, text, created_at
         FROM comments_resource WHERE resource_id = ? ORDER BY created_at ASC"
    );
    $stmt->execute([$resourceId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(["success" => true, "data" => $comments]);
}

function createComment($db, $data) {
    $resourceId = isset($data["resource_id"]) ? (int)$data["resource_id"] : 0;
    $author     = trim($data["author"] ?? "");
    $text       = trim($data["text"]   ?? "");

    if (!$resourceId || !$text) {
        sendResponse(["success" => false, "message" => "resource_id and text are required."], 400);
    }

    if (!is_numeric($resourceId)) {
        sendResponse(["success" => false, "message" => "Invalid resource ID."], 400);
    }

    $chk = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $chk->execute([$resourceId]);
    if (!$chk->fetch()) sendResponse(["success" => false, "message" => "Resource not found."], 404);

    if (!$author) $author = "Anonymous";

    $stmt = $db->prepare(
        "INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)"
    );
    $stmt->execute([$resourceId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId = $db->lastInsertId();
        $fetch = $db->prepare(
            "SELECT id, resource_id, author, text, created_at FROM comments_resource WHERE id = ?"
        );
        $fetch->execute([$newId]);
        $comment = $fetch->fetch(PDO::FETCH_ASSOC);
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Comment created.", "id" => $newId, "data" => $comment]);
        exit;
    }

    sendResponse(["success" => false, "message" => "Failed to create comment."], 500);
}

function deleteComment($db, $commentId) {
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(["success" => false, "message" => "Invalid comment ID."], 400);
    }

    $chk = $db->prepare("SELECT id FROM comments_resource WHERE id = ?");
    $chk->execute([$commentId]);
    if (!$chk->fetch()) sendResponse(["success" => false, "message" => "Comment not found."], 404);

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(["success" => true, "message" => "Comment deleted successfully."]);
    }
    sendResponse(["success" => false, "message" => "Failed to delete comment."], 500);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if (!is_array($data)) $data = ["success" => false, "message" => $data];
    echo json_encode($data);
    exit;
}

function validateUrl($url) {
    return (bool) filter_var($url, FILTER_VALIDATE_URL);
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, "UTF-8");
    return $data;
}

function validateRequiredFields($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || $data[$field] === "") {
            $missing[] = $field;
        }
    }
    return ["valid" => count($missing) === 0, "missing" => $missing];
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === "GET") {
        if ($action === "comments" && $resourceId) {
            getCommentsByResourceId($db, $resourceId);
        } elseif ($id) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }

    } elseif ($method === "POST") {
        if ($action === "comment") {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }

    } elseif ($method === "PUT") {
        updateResource($db, $data);

    } elseif ($method === "DELETE") {
        if ($action === "delete_comment") {
            deleteComment($db, $commentId);
        } else {
            deleteResource($db, $id);
        }

    } else {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method not allowed."]);
        exit;
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(["success" => false, "message" => "A database error occurred."], 500);

} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(["success" => false, "message" => $e->getMessage()], 500);
}
?>
