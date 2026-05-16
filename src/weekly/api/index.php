<?php
/**
 * Weekly Course Breakdown API
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
$rawData = file_get_contents("php://input");
$data    = json_decode($rawData, true) ?? [];

$action    = $_GET["action"]     ?? null;
$id        = $_GET["id"]         ?? null;
$weekId    = $_GET["week_id"]    ?? null;
$commentId = $_GET["comment_id"] ?? null;

// ============================================================================
// WEEKS FUNCTIONS
// ============================================================================

function getAllWeeks(PDO $db): void
{
    $search = isset($_GET["search"]) ? trim($_GET["search"]) : "";
    $sort   = isset($_GET["sort"])   ? trim($_GET["sort"])   : "start_date";
    $order  = isset($_GET["order"])  ? trim($_GET["order"])  : "asc";

    $allowedSort  = ["title", "start_date"];
    $allowedOrder = ["asc", "desc"];
    if (!in_array($sort,  $allowedSort))  $sort  = "start_date";
    if (!in_array($order, $allowedOrder)) $order = "asc";

    $sql    = "SELECT id, title, start_date, description, links, created_at FROM weeks";
    $params = [];

    if ($search !== "") {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
        $params[":search"] = "%" . $search . "%";
    }

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($weeks as &$row) {
        $row["links"] = json_decode($row["links"], true) ?? [];
    }

    sendResponse(["success" => true, "data" => $weeks]);
}

function getWeekById(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(["success" => false, "message" => "Invalid week ID."], 400);
    }

    $stmt = $db->prepare(
        "SELECT id, title, start_date, description, links, created_at FROM weeks WHERE id = ?"
    );
    $stmt->execute([$id]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$week) {
        sendResponse(["success" => false, "message" => "Week not found."], 404);
    }

    $week["links"] = json_decode($week["links"], true) ?? [];
    sendResponse(["success" => true, "data" => $week]);
}

function createWeek(PDO $db, array $data): void
{
    $title       = trim($data["title"]       ?? "");
    $start_date  = trim($data["start_date"]  ?? "");
    $description = trim($data["description"] ?? "");
    $links       = $data["links"] ?? [];

    if (!$title)      sendResponse(["success" => false, "message" => "Title is required."], 400);
    if (!$start_date) sendResponse(["success" => false, "message" => "Start date is required."], 400);

    if (!validateDate($start_date)) {
        sendResponse(["success" => false, "message" => "Invalid date format. Use YYYY-MM-DD."], 400);
    }

    if (!is_array($links)) $links = [];
    $linksJson = json_encode($links);

    $stmt = $db->prepare(
        "INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$title, $start_date, $description, $linksJson]);

    if ($stmt->rowCount() > 0) {
        $newId = $db->lastInsertId();
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Week created.", "id" => $newId]);
        exit;
    }

    sendResponse(["success" => false, "message" => "Failed to create week."], 500);
}

function updateWeek(PDO $db, array $data): void
{
    $id = isset($data["id"]) ? (int)$data["id"] : 0;
    if (!$id) sendResponse(["success" => false, "message" => "ID is required."], 400);

    $chk = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) sendResponse(["success" => false, "message" => "Week not found."], 404);

    $fields = [];
    $params = [];

    if (isset($data["title"]) && trim($data["title"]) !== "") {
        $fields[] = "title = ?";
        $params[] = trim($data["title"]);
    }
    if (isset($data["start_date"]) && trim($data["start_date"]) !== "") {
        $sd = trim($data["start_date"]);
        if (!validateDate($sd)) {
            sendResponse(["success" => false, "message" => "Invalid date format."], 400);
        }
        $fields[] = "start_date = ?";
        $params[] = $sd;
    }
    if (isset($data["description"])) {
        $fields[] = "description = ?";
        $params[] = trim($data["description"]);
    }
    if (isset($data["links"])) {
        $fields[] = "links = ?";
        $params[] = json_encode(is_array($data["links"]) ? $data["links"] : []);
    }

    if (empty($fields)) {
        sendResponse(["success" => false, "message" => "No fields to update."], 400);
    }

    $params[] = $id;
    $stmt = $db->prepare("UPDATE weeks SET " . implode(", ", $fields) . " WHERE id = ?");
    $stmt->execute($params);

    sendResponse(["success" => true, "message" => "Week updated successfully."]);
}

function deleteWeek(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(["success" => false, "message" => "Invalid week ID."], 400);
    }

    $chk = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) sendResponse(["success" => false, "message" => "Week not found."], 404);

    $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(["success" => true, "message" => "Week deleted successfully."]);
    }
    sendResponse(["success" => false, "message" => "Failed to delete week."], 500);
}

// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

function getCommentsByWeek(PDO $db, $weekId): void
{
    if (!$weekId || !is_numeric($weekId)) {
        sendResponse(["success" => false, "message" => "Invalid week ID."], 400);
    }

    $stmt = $db->prepare(
        "SELECT id, week_id, author, text, created_at
         FROM comments_week WHERE week_id = ? ORDER BY created_at ASC"
    );
    $stmt->execute([$weekId]);
    sendResponse(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment(PDO $db, array $data): void
{
    $weekId = isset($data["week_id"]) ? (int)$data["week_id"] : 0;
    $author = trim($data["author"] ?? "");
    $text   = trim($data["text"]   ?? "");

    if (!$weekId || !$text) {
        sendResponse(["success" => false, "message" => "week_id and text are required."], 400);
    }
    if (!$author) $author = "Anonymous";

    $chk = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $chk->execute([$weekId]);
    if (!$chk->fetch()) sendResponse(["success" => false, "message" => "Week not found."], 404);

    $stmt = $db->prepare(
        "INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)"
    );
    $stmt->execute([$weekId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId = $db->lastInsertId();
        $fetch = $db->prepare(
            "SELECT id, week_id, author, text, created_at FROM comments_week WHERE id = ?"
        );
        $fetch->execute([$newId]);
        $comment = $fetch->fetch(PDO::FETCH_ASSOC);
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Comment created.", "id" => $newId, "data" => $comment]);
        exit;
    }

    sendResponse(["success" => false, "message" => "Failed to create comment."], 500);
}

function deleteComment(PDO $db, $commentId): void
{
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(["success" => false, "message" => "Invalid comment ID."], 400);
    }

    $chk = $db->prepare("SELECT id FROM comments_week WHERE id = ?");
    $chk->execute([$commentId]);
    if (!$chk->fetch()) sendResponse(["success" => false, "message" => "Comment not found."], 404);

    $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(["success" => true, "message" => "Comment deleted."]);
    }
    sendResponse(["success" => false, "message" => "Failed to delete comment."], 500);
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === "GET") {
        if ($action === "comments") {
            getCommentsByWeek($db, $weekId);
        } elseif ($id !== null) {
            getWeekById($db, $id);
        } else {
            getAllWeeks($db);
        }

    } elseif ($method === "POST") {
        if ($action === "comment") {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }

    } elseif ($method === "PUT") {
        updateWeek($db, $data);

    } elseif ($method === "DELETE") {
        if ($action === "delete_comment") {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }

    } else {
        sendResponse(["success" => false, "message" => "Method not allowed."], 405);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(["success" => false, "message" => "A database error occurred."], 500);

} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(["success" => false, "message" => $e->getMessage()], 500);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat("Y-m-d", $date);
    return $d && $d->format("Y-m-d") === $date;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, "UTF-8");
}
?>
