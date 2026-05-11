<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../../common/db.php";

$db = getDBConnection();

$method = $_SERVER["REQUEST_METHOD"];

$raw = file_get_contents("php://input");
$data = json_decode($raw, true) ?? [];

$action       = $_GET["action"] ?? "";
$id           = isset($_GET["id"]) ? (int) $_GET["id"] : null;
$assignmentId = isset($_GET["assignment_id"])
    ? (int) $_GET["assignment_id"]
    : null;
$commentId    = isset($_GET["comment_id"])
    ? (int) $_GET["comment_id"]
    : null;

function getAllAssignments($db)
{
    $search = trim($_GET["search"] ?? "");
    $sort   = trim($_GET["sort"] ?? "due_date");
    $order  = trim($_GET["order"] ?? "asc");

    $allowedSort  = ["title", "due_date", "created_at"];
    $allowedOrder = ["asc", "desc"];

    if (!in_array($sort, $allowedSort)) {
        $sort = "due_date";
    }

    if (!in_array(strtolower($order), $allowedOrder)) {
        $order = "asc";
    }

    $sql = "
        SELECT
            id,
            title,
            description,
            due_date,
            files,
            created_at,
            updated_at
        FROM assignments
    ";

    $params = [];

    if ($search !== "") {

        $sql .= "
            WHERE
                title LIKE :search
                OR description LIKE :search2
        ";

        $params[":search"]  = "%" . $search . "%";
        $params[":search2"] = "%" . $search . "%";
    }

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);

    $stmt->execute($params);

    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($assignments as &$assignment) {

        $assignment["files"] =
            json_decode($assignment["files"], true) ?? [];
    }

    sendResponse([
        "success" => true,
        "data"    => $assignments
    ]);
}

function getAssignmentById($db, $id)
{
    if (!$id || !is_numeric($id)) {

        sendResponse([
            "success" => false,
            "message" => "Invalid assignment ID."
        ], 400);
    }

    $stmt = $db->prepare("
        SELECT
            id,
            title,
            description,
            due_date,
            files,
            created_at,
            updated_at
        FROM assignments
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {

        sendResponse([
            "success" => false,
            "message" => "Assignment not found."
        ], 404);
    }

    $assignment["files"] =
        json_decode($assignment["files"], true) ?? [];

    sendResponse([
        "success" => true,
        "data"    => $assignment
    ]);
}

function createAssignment($db, $data)
{
    $title       = trim($data["title"] ?? "");
    $description = trim($data["description"] ?? "");
    $due_date    = trim($data["due_date"] ?? "");
    $files       = $data["files"] ?? [];

    if (!$title || !$description || !$due_date) {

        sendResponse([
            "success" => false,
            "message" => "Missing required fields."
        ], 400);
    }

    if (!validateDate($due_date)) {

        sendResponse([
            "success" => false,
            "message" => "Invalid due date."
        ], 400);
    }

    $filesJson = json_encode($files);

    $stmt = $db->prepare("
        INSERT INTO assignments
        (title, description, due_date, files)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        sanitizeInput($title),
        sanitizeInput($description),
        $due_date,
        $filesJson
    ]);

    if ($stmt->rowCount() > 0) {

        $newId = $db->lastInsertId();

        http_response_code(201);

        echo json_encode([
            "success" => true,
            "message" => "Assignment created.",
            "id"      => $newId
        ]);

        exit;
    }

    sendResponse([
        "success" => false,
        "message" => "Failed to create assignment."
    ], 500);
}

function updateAssignment($db, $data)
{
    $id = isset($data["id"])
        ? (int) $data["id"]
        : 0;

    if (!$id) {

        sendResponse([
            "success" => false,
            "message" => "ID is required."
        ], 400);
    }

    $check = $db->prepare("
        SELECT id FROM assignments WHERE id = ?
    ");

    $check->execute([$id]);

    if (!$check->fetch()) {

        sendResponse([
            "success" => false,
            "message" => "Assignment not found."
        ], 404);
    }

    $fields = [];
    $params = [];

    if (isset($data["title"])) {

        $fields[] = "title = ?";

        $params[] =
            sanitizeInput(trim($data["title"]));
    }

    if (isset($data["description"])) {

        $fields[] = "description = ?";

        $params[] =
            sanitizeInput(trim($data["description"]));
    }

    if (isset($data["due_date"])) {

        if (!validateDate($data["due_date"])) {

            sendResponse([
                "success" => false,
                "message" => "Invalid due date."
            ], 400);
        }

        $fields[] = "due_date = ?";

        $params[] = trim($data["due_date"]);
    }

    if (isset($data["files"])) {

        $fields[] = "files = ?";

        $params[] = json_encode($data["files"]);
    }

    if (empty($fields)) {

        sendResponse([
            "success" => false,
            "message" => "No fields to update."
        ], 400);
    }

    $params[] = $id;

    $sql = "
        UPDATE assignments
        SET " . implode(", ", $fields) . "
        WHERE id = ?
    ";

    $stmt = $db->prepare($sql);

    $stmt->execute($params);

    sendResponse([
        "success" => true,
        "message" => "Assignment updated successfully."
    ]);
}

function deleteAssignment($db, $id)
{
    if (!$id || !is_numeric($id)) {

        sendResponse([
            "success" => false,
            "message" => "Invalid assignment ID."
        ], 400);
    }

    $check = $db->prepare("
        SELECT id FROM assignments WHERE id = ?
    ");

    $check->execute([$id]);

    if (!$check->fetch()) {

        sendResponse([
            "success" => false,
            "message" => "Assignment not found."
        ], 404);
    }

    $stmt = $db->prepare("
        DELETE FROM assignments WHERE id = ?
    ");

    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {

        sendResponse([
            "success" => true,
            "message" => "Assignment deleted successfully."
        ]);
    }

    sendResponse([
        "success" => false,
        "message" => "Failed to delete assignment."
    ], 500);
}

function getCommentsByAssignment($db, $assignmentId)
{
    if (!$assignmentId || !is_numeric($assignmentId)) {

        sendResponse([
            "success" => false,
            "message" => "Invalid assignment ID."
        ], 400);
    }

    $stmt = $db->prepare("
        SELECT
            id,
            assignment_id,
            author,
            text,
            created_at
        FROM comments_assignment
        WHERE assignment_id = ?
        ORDER BY created_at ASC
    ");

    $stmt->execute([$assignmentId]);

    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        "success" => true,
        "data"    => $comments
    ]);
}

function createComment($db, $data)
{
    $assignmentId =
        isset($data["assignment_id"])
            ? (int) $data["assignment_id"]
            : 0;

    $author =
        trim($data["author"] ?? "");

    $text =
        trim($data["text"] ?? "");

    if (!$assignmentId || !$text) {

        sendResponse([
            "success" => false,
            "message" => "assignment_id and text are required."
        ], 400);
    }

    $check = $db->prepare("
        SELECT id FROM assignments WHERE id = ?
    ");

    $check->execute([$assignmentId]);

    if (!$check->fetch()) {

        sendResponse([
            "success" => false,
            "message" => "Assignment not found."
        ], 404);
    }

    if (!$author) {
        $author = "Anonymous";
    }

    $stmt = $db->prepare("
        INSERT INTO comments_assignment
        (assignment_id, author, text)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $assignmentId,
        sanitizeInput($author),
        sanitizeInput($text)
    ]);

    if ($stmt->rowCount() > 0) {

        $newId = $db->lastInsertId();

        $fetch = $db->prepare("
            SELECT
                id,
                assignment_id,
                author,
                text,
                created_at
            FROM comments_assignment
            WHERE id = ?
        ");

        $fetch->execute([$newId]);

        $comment = $fetch->fetch(PDO::FETCH_ASSOC);

        http_response_code(201);

        echo json_encode([
            "success" => true,
            "message" => "Comment created.",
            "id"      => $newId,
            "data"    => $comment
        ]);

        exit;
    }

    sendResponse([
        "success" => false,
        "message" => "Failed to create comment."
    ], 500);
}

function deleteComment($db, $commentId)
{
    if (!$commentId || !is_numeric($commentId)) {

        sendResponse([
            "success" => false,
            "message" => "Invalid comment ID."
        ], 400);
    }

    $check = $db->prepare("
        SELECT id FROM comments_assignment WHERE id = ?
    ");

    $check->execute([$commentId]);

    if (!$check->fetch()) {

        sendResponse([
            "success" => false,
            "message" => "Comment not found."
        ], 404);
    }

    $stmt = $db->prepare("
        DELETE FROM comments_assignment WHERE id = ?
    ");

    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {

        sendResponse([
            "success" => true,
            "message" => "Comment deleted successfully."
        ]);
    }

    sendResponse([
        "success" => false,
        "message" => "Failed to delete comment."
    ], 500);
}

function sendResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);

    echo json_encode($data);

    exit;
}

function validateDate($date)
{
    $d = DateTime::createFromFormat("Y-m-d", $date);

    return $d && $d->format("Y-m-d") === $date;
}

function sanitizeInput($data)
{
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars(
        $data,
        ENT_QUOTES,
        "UTF-8"
    );

    return $data;
}

try {

    if ($method === "GET") {

        if (
            $action === "comments"
            && $assignmentId
        ) {

            getCommentsByAssignment(
                $db,
                $assignmentId
            );

        } elseif ($id) {

            getAssignmentById($db, $id);

        } else {

            getAllAssignments($db);
        }

    } elseif ($method === "POST") {

        if ($action === "comment") {

            createComment($db, $data);

        } else {

            createAssignment($db, $data);
        }

    } elseif ($method === "PUT") {

        updateAssignment($db, $data);

    } elseif ($method === "DELETE") {

        if (
            $action === "delete_comment"
        ) {

            deleteComment($db, $commentId);

        } else {

            deleteAssignment($db, $id);
        }

    } else {

        sendResponse([
            "success" => false,
            "message" => "Method not allowed."
        ], 405);
    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    sendResponse([
        "success" => false,
        "message" => "Database error."
    ], 500);

} catch (Exception $e) {

    error_log($e->getMessage());

    sendResponse([
        "success" => false,
        "message" => "Server error."
    ], 500);
}
?>
