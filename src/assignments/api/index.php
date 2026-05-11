<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?? [];

$action       = $_GET['action'] ?? null;
$id           = $_GET['id'] ?? null;
$assignmentId = $_GET['assignment_id'] ?? null;
$commentId    = $_GET['comment_id'] ?? null;

function getAllAssignments(PDO $db): void
{
    $query = "SELECT * FROM assignments";

    $params = [];

    if (!empty($_GET['search'])) {
        $query .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $allowedSort = ['title', 'due_date', 'created_at'];

    $sort = in_array($_GET['sort'] ?? '', $allowedSort)
        ? $_GET['sort']
        : 'due_date';

    $order = strtolower($_GET['order'] ?? 'asc');
    $order = $order === 'desc' ? 'DESC' : 'ASC';

    $query .= " ORDER BY $sort $order";

    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();

    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($assignments as &$assignment) {
        $assignment['files'] =
            json_decode($assignment['files'], true) ?? [];
    }

    sendResponse([
        'success' => true,
        'data' => $assignments
    ]);
}

function getAssignmentById(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid assignment ID'
        ], 400);
    }

    $stmt = $db->prepare("
        SELECT * FROM assignments WHERE id = ?
    ");

    $stmt->execute([$id]);

    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        sendResponse([
            'success' => false,
            'message' => 'Assignment not found'
        ], 404);
    }

    $assignment['files'] =
        json_decode($assignment['files'], true) ?? [];

    sendResponse([
        'success' => true,
        'data' => $assignment
    ]);
}

function createAssignment(PDO $db, array $data): void
{
    if (
        empty($data['title']) ||
        empty($data['description']) ||
        empty($data['due_date'])
    ) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields'
        ], 400);
    }

    $title = sanitizeInput($data['title']);
    $description = sanitizeInput($data['description']);
    $due_date = trim($data['due_date']);

    if (!validateDate($due_date)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid due date format'
        ], 400);
    }

    $files = json_encode($data['files'] ?? []);

    $stmt = $db->prepare("
        INSERT INTO assignments
        (title, description, due_date, files)
        VALUES (?, ?, ?, ?)
    ");

    $success = $stmt->execute([
        $title,
        $description,
        $due_date,
        $files
    ]);

    if ($success) {
        sendResponse([
            'success' => true,
            'message' => 'Assignment created',
            'id' => $db->lastInsertId()
        ], 201);
    }

    sendResponse([
        'success' => false,
        'message' => 'Failed to create assignment'
    ], 500);
}

function updateAssignment(PDO $db, array $data): void
{
    if (empty($data['id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Assignment ID required'
        ], 400);
    }

    $stmt = $db->prepare("
        SELECT id FROM assignments WHERE id = ?
    ");

    $stmt->execute([$data['id']]);

    if (!$stmt->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Assignment not found'
        ], 404);
    }

    $fields = [];
    $values = [];

    if (isset($data['title'])) {
        $fields[] = "title = ?";
        $values[] = sanitizeInput($data['title']);
    }

    if (isset($data['description'])) {
        $fields[] = "description = ?";
        $values[] = sanitizeInput($data['description']);
    }

    if (isset($data['due_date'])) {

        if (!validateDate($data['due_date'])) {
            sendResponse([
                'success' => false,
                'message' => 'Invalid due date'
            ], 400);
        }

        $fields[] = "due_date = ?";
        $values[] = trim($data['due_date']);
    }

    if (isset($data['files'])) {
        $fields[] = "files = ?";
        $values[] = json_encode($data['files']);
    }

    if (empty($fields)) {
        sendResponse([
            'success' => false,
            'message' => 'No fields to update'
        ], 400);
    }

    $values[] = $data['id'];

    $query = "
        UPDATE assignments
        SET " . implode(', ', $fields) . "
        WHERE id = ?
    ";

    $stmt = $db->prepare($query);

    if ($stmt->execute($values)) {
        sendResponse([
            'success' => true,
            'message' => 'Assignment updated'
        ]);
    }

    sendResponse([
        'success' => false,
        'message' => 'Update failed'
    ], 500);
}

function deleteAssignment(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid assignment ID'
        ], 400);
    }

    $stmt = $db->prepare("
        DELETE FROM assignments WHERE id = ?
    ");

    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Assignment deleted'
        ]);
    }

    sendResponse([
        'success' => false,
        'message' => 'Assignment not found'
    ], 404);
}

function getCommentsByAssignment(PDO $db, $assignmentId): void
{
    if (!$assignmentId || !is_numeric($assignmentId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid assignment ID'
        ], 400);
    }

    $stmt = $db->prepare("
        SELECT * FROM comments_assignment
        WHERE assignment_id = ?
        ORDER BY created_at ASC
    ");

    $stmt->execute([$assignmentId]);

    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'data' => $comments
    ]);
}

function createComment(PDO $db, array $data): void
{
    if (
        empty($data['assignment_id']) ||
        empty($data['author']) ||
        empty($data['text'])
    ) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields'
        ], 400);
    }

    $stmt = $db->prepare("
        SELECT id FROM assignments WHERE id = ?
    ");

    $stmt->execute([$data['assignment_id']]);

    if (!$stmt->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Assignment not found'
        ], 404);
    }

    $stmt = $db->prepare("
        INSERT INTO comments_assignment
        (assignment_id, author, text)
        VALUES (?, ?, ?)
    ");

    $success = $stmt->execute([
        $data['assignment_id'],
        sanitizeInput($data['author']),
        sanitizeInput($data['text'])
    ]);

    if ($success) {
        sendResponse([
            'success' => true,
            'message' => 'Comment added',
            'id' => $db->lastInsertId()
        ], 201);
    }

    sendResponse([
        'success' => false,
        'message' => 'Failed to add comment'
    ], 500);
}

function deleteComment(PDO $db, $commentId): void
{
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid comment ID'
        ], 400);
    }

    $stmt = $db->prepare("
        DELETE FROM comments_assignment WHERE id = ?
    ");

    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Comment deleted'
        ]);
    }

    sendResponse([
        'success' => false,
        'message' => 'Comment not found'
    ], 404);
}

try {

    if ($method === 'GET') {

        if ($action === 'comments') {

            getCommentsByAssignment($db, $assignmentId);

        } elseif ($id) {

            getAssignmentById($db, $id);

        } else {

            getAllAssignments($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {

            createComment($db, $data);

        } else {

            createAssignment($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateAssignment($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {

            deleteComment($db, $commentId);

        } else {

            deleteAssignment($db, $id);
        }

    } else {

        sendResponse([
            'success' => false,
            'message' => 'Method not allowed'
        ], 405);
    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    sendResponse([
        'success' => false,
        'message' => 'Database error'
    ], 500);

} catch (Exception $e) {

    error_log($e->getMessage());

    sendResponse([
        'success' => false,
        'message' => 'Server error'
    ], 500);
}

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);

    echo json_encode($data, JSON_PRETTY_PRINT);

    exit;
}

function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);

    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(
        strip_tags(trim($data)),
        ENT_QUOTES,
        'UTF-8'
    );
}
    // TODO: return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
