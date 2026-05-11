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

$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true) ?? [];

$action = $_GET["action"] ?? null;
$id = $_GET["id"] ?? null;
$topicId = $_GET["topic_id"] ?? null;

function getAllTopics(PDO $db): void
{
    $query = "
        SELECT
            id,
            subject,
            message,
            author,
            created_at
        FROM topics
    ";

    $params = [];

    if (!empty($_GET["search"])) {

        $query .= "
            WHERE
                subject LIKE :search
                OR message LIKE :search
                OR author LIKE :search
        ";

        $params[":search"] =
            "%" . $_GET["search"] . "%";
    }

    $allowedSort = [
        "subject",
        "author",
        "created_at"
    ];

    $sort = in_array(
        $_GET["sort"] ?? "",
        $allowedSort
    )
        ? $_GET["sort"]
        : "created_at";

    $order = strtolower(
        $_GET["order"] ?? "desc"
    );

    $order =
        $order === "asc"
            ? "ASC"
            : "DESC";

    $query .= "
        ORDER BY $sort $order
    ";

    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {

        $stmt->bindValue($key, $value);
    }

    $stmt->execute();

    $topics =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        "success" => true,
        "data" => $topics
    ]);
}

function getTopicById(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {

        sendResponse([
            "success" => false,
            "message" => "Invalid topic ID"
        ], 400);
    }

    $stmt = $db->prepare("
        SELECT
            id,
            subject,
            message,
            author,
            created_at
        FROM topics
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $topic =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$topic) {

        sendResponse([
            "success" => false,
            "message" => "Topic not found"
        ], 404);
    }

    sendResponse([
        "success" => true,
        "data" => $topic
    ]);
}

function createTopic(PDO $db, array $data): void
{
    if (
        empty($data["subject"]) ||
        empty($data["message"]) ||
        empty($data["author"])
    ) {

        sendResponse([
            "success" => false,
            "message" => "Missing required fields"
        ], 400);
    }

    $subject =
        sanitizeInput($data["subject"]);

    $message =
        sanitizeInput($data["message"]);

    $author =
        sanitizeInput($data["author"]);

    $stmt = $db->prepare("
        INSERT INTO topics
        (subject, message, author)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $subject,
        $message,
        $author
    ]);

    if ($stmt->rowCount() > 0) {

        sendResponse([
            "success" => true,
            "message" => "Topic created",
            "id" => $db->lastInsertId()
        ], 201);
    }

    sendResponse([
        "success" => false,
        "message" => "Failed to create topic"
    ], 500);
}

function updateTopic(PDO $db, array $data): void
{
    if (empty($data["id"])) {

        sendResponse([
            "success" => false,
            "message" => "Topic ID required"
        ], 400);
    }

    $check = $db->prepare("
        SELECT id
        FROM topics
        WHERE id = ?
    ");

    $check->execute([$data["id"]]);

    if (!$check->fetch()) {

        sendResponse([
            "success" => false,
            "message" => "Topic not found"
        ], 404);
    }

    $fields = [];
    $values = [];

    if (isset($data["subject"])) {

        $fields[] = "subject = ?";

        $values[] =
            sanitizeInput($data["subject"]);
    }

    if (isset($data["message"])) {

        $fields[] = "message = ?";

        $values[] =
            sanitizeInput($data["message"]);
    }

    if (empty($fields)) {

        sendResponse([
            "success" => false,
            "message" => "No fields to update"
        ], 400);
    }

    $values[] = $data["id"];

    $query = "
        UPDATE topics
        SET " . implode(", ", $fields) . "
        WHERE id = ?
    ";

    $stmt = $db->prepare($query);

    if ($stmt->execute($values)) {

        sendResponse([
            "success" => true,
            "message" => "Topic updated"
        ]);
    }

    sendResponse([
        "success" => false,
        "message" => "Failed to update topic"
    ], 500);
}

function deleteTopic(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {

        sendResponse([
            "success" => false,
            "message" => "Invalid topic ID"
        ], 400);
    }

    $check = $db->prepare("
        SELECT id
        FROM topics
        WHERE id = ?
    ");

    $check->execute([$id]);

    if (!$check->fetch()) {

        sendResponse([
            "success" => false,
            "message" => "Topic not found"
        ], 404);
    }

    $stmt = $db->prepare("
        DELETE FROM topics
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {

        sendResponse([
            "success" => true,
            "message" => "Topic deleted"
        ]);
    }

    sendResponse([
        "success" => false,
        "message" => "Failed to delete topic"
    ], 500);
}

function getRepliesByTopicId(
    PDO $db,
    $topicId
): void {

    if (
        !$topicId ||
        !is_numeric($topicId)
    ) {

        sendResponse([
            "success" => false,
            "message" => "Invalid topic ID"
        ], 400);
    }

    $stmt = $db->prepare("
        SELECT
            id,
            topic_id,
            text,
            author,
            created_at
        FROM replies
        WHERE topic_id = ?
        ORDER BY created_at ASC
    ");

    $stmt->execute([$topicId]);

    $replies =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        "success" => true,
        "data" => $replies
    ]);
}

function createReply(PDO $db, array $data): void
{
    if (
        empty($data["topic_id"]) ||
        empty($data["text"]) ||
        empty($data["author"])
    ) {

        sendResponse([
            "success" => false,
            "message" => "Missing required fields"
        ], 400);
    }

    if (!is_numeric($data["topic_id"])) {

        sendResponse([
            "success" => false,
            "message" => "Invalid topic ID"
        ], 400);
    }

    $check = $db->prepare("
        SELECT id
        FROM topics
        WHERE id = ?
    ");

    $check->execute([
        $data["topic_id"]
    ]);

    if (!$check->fetch()) {

        sendResponse([
            "success" => false,
            "message" => "Topic not found"
        ], 404);
    }

    $stmt = $db->prepare("
        INSERT INTO replies
        (topic_id, text, author)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $data["topic_id"],
        sanitizeInput($data["text"]),
        sanitizeInput($data["author"])
    ]);

    if ($stmt->rowCount() > 0) {

        $replyId =
            $db->lastInsertId();

        $fetch = $db->prepare("
            SELECT
                id,
                topic_id,
                text,
                author,
                created_at
            FROM replies
            WHERE id = ?
        ");

        $fetch->execute([$replyId]);

        $reply =
            $fetch->fetch(PDO::FETCH_ASSOC);

        sendResponse([
            "success" => true,
            "message" => "Reply created",
            "id" => $replyId,
            "data" => $reply
        ], 201);
    }

    sendResponse([
        "success" => false,
        "message" => "Failed to create reply"
    ], 500);
}

function deleteReply(PDO $db, $replyId): void
{
    if (
        !$replyId ||
        !is_numeric($replyId)
    ) {

        sendResponse([
            "success" => false,
            "message" => "Invalid reply ID"
        ], 400);
    }

    $check = $db->prepare("
        SELECT id
        FROM replies
        WHERE id = ?
    ");

    $check->execute([$replyId]);

    if (!$check->fetch()) {

        sendResponse([
            "success" => false,
            "message" => "Reply not found"
        ], 404);
    }

    $stmt = $db->prepare("
        DELETE FROM replies
        WHERE id = ?
    ");

    $stmt->execute([$replyId]);

    if ($stmt->rowCount() > 0) {

        sendResponse([
            "success" => true,
            "message" => "Reply deleted"
        ]);
    }

    sendResponse([
        "success" => false,
        "message" => "Failed to delete reply"
    ], 500);
}

try {

    if ($method === "GET") {

        if ($action === "replies") {

            getRepliesByTopicId(
                $db,
                $topicId
            );

        } elseif ($id) {

            getTopicById($db, $id);

        } else {

            getAllTopics($db);
        }

    } elseif ($method === "POST") {

        if ($action === "reply") {

            createReply($db, $data);

        } else {

            createTopic($db, $data);
        }

    } elseif ($method === "PUT") {

        updateTopic($db, $data);

    } elseif ($method === "DELETE") {

        if (
            $action ===
            "delete_reply"
        ) {

            deleteReply($db, $id);

        } else {

            deleteTopic($db, $id);
        }

    } else {

        sendResponse([
            "success" => false,
            "message" => "Method not allowed"
        ], 405);
    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    sendResponse([
        "success" => false,
        "message" => "Database error"
    ], 500);

} catch (Exception $e) {

    error_log($e->getMessage());

    sendResponse([
        "success" => false,
        "message" => "Server error"
    ], 500);
}

function sendResponse(
    array $data,
    int $statusCode = 200
): void {

    http_response_code(
        $statusCode
    );

    echo json_encode(
        $data,
        JSON_PRETTY_PRINT
    );

    exit;
}

function sanitizeInput(
    string $data
): string {

    return htmlspecialchars(
        strip_tags(trim($data)),
        ENT_QUOTES,
        "UTF-8"
    );
}

?>
