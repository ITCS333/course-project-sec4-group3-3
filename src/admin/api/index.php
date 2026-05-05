<?php
/**
 * User Management API
 */

// --- Headers ---
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// --- Preflight ---
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// --- DB Connection ---
$db = getDBConnection();

// --- Request Info ---
$method = $_SERVER["REQUEST_METHOD"];
$raw    = file_get_contents("php://input");
$data   = json_decode($raw, true) ?? [];

// --- Query Params ---
$id     = isset($_GET["id"])     ? (int)$_GET["id"]       : null;
$action = isset($_GET["action"]) ? trim($_GET["action"])   : "";
$search = isset($_GET["search"]) ? trim($_GET["search"])   : "";
$sort   = isset($_GET["sort"])   ? trim($_GET["sort"])     : "";
$order  = isset($_GET["order"])  ? trim($_GET["order"])    : "asc";


// ============================================================================
// FUNCTIONS
// ============================================================================

function getUsers($db) {
    $allowed = ["name", "email", "is_admin"];
    $sort    = isset($_GET["sort"])   ? trim($_GET["sort"])  : "";
    $order   = isset($_GET["order"])  ? trim($_GET["order"]) : "asc";
    $search  = isset($_GET["search"]) ? trim($_GET["search"]): "";

    $sql    = "SELECT id, name, email, is_admin, created_at FROM users";
    $params = [];

    if ($search !== "") {
        $sql     .= " WHERE name LIKE :search OR email LIKE :search2";
        $params[":search"]  = "%" . $search . "%";
        $params[":search2"] = "%" . $search . "%";
    }

    if (in_array($sort, $allowed)) {
        $dir  = strtolower($order) === "desc" ? "DESC" : "ASC";
        $sql .= " ORDER BY $sort $dir";
    } else {
        $sql .= " ORDER BY id ASC";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse($users, 200);
}

function getUserById($db, $id) {
    $stmt = $db->prepare(
        "SELECT id, name, email, is_admin, created_at FROM users WHERE id = ?"
    );
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }
    sendResponse($user, 200);
}

function createUser($db, $data) {
    $name     = trim($data["name"]     ?? "");
    $email    = trim($data["email"]    ?? "");
    $password = trim($data["password"] ?? "");
    $isAdmin  = isset($data["is_admin"]) ? (int)$data["is_admin"] : 0;

    if (!$name || !$email || !$password) {
        sendResponse("Name, email, and password are required", 400);
    }

    if (!validateEmail($email)) {
        sendResponse("Invalid email format", 400);
    }

    if (strlen($password) < 8) {
        sendResponse("Password must be at least 8 characters", 400);
    }

    // Check duplicate
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        sendResponse("Email already exists", 409);
    }

    $isAdmin = in_array($isAdmin, [0, 1]) ? $isAdmin : 0;
    $hash    = password_hash($password, PASSWORD_DEFAULT);

    $ins = $db->prepare(
        "INSERT INTO users (name, email, password, is_admin) VALUES (?, ?, ?, ?)"
    );
    $ok = $ins->execute([$name, $email, $hash, $isAdmin]);

    if ($ok) {
        sendResponse(["id" => $db->lastInsertId()], 201);
    } else {
        sendResponse("Failed to create user", 500);
    }
}

function updateUser($db, $data) {
    $id = isset($data["id"]) ? (int)$data["id"] : 0;

    if (!$id) {
        sendResponse("User ID is required", 400);
    }

    // Check exists
    $check = $db->prepare("SELECT id FROM users WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse("User not found", 404);
    }

    $fields = [];
    $params = [];

    if (isset($data["name"]) && trim($data["name"]) !== "") {
        $fields[]        = "name = ?";
        $params[]        = sanitizeInput($data["name"]);
    }

    if (isset($data["email"]) && trim($data["email"]) !== "") {
        $email = trim($data["email"]);
        if (!validateEmail($email)) {
            sendResponse("Invalid email format", 400);
        }
        // Check duplicate
        $dup = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $dup->execute([$email, $id]);
        if ($dup->fetch()) {
            sendResponse("Email already in use", 409);
        }
        $fields[] = "email = ?";
        $params[] = $email;
    }

    if (isset($data["is_admin"])) {
        $fields[] = "is_admin = ?";
        $params[] = (int)$data["is_admin"];
    }

    if (empty($fields)) {
        sendResponse("No fields to update", 200);
    }

    $params[] = $id;
    $sql      = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
    $stmt     = $db->prepare($sql);
    $stmt->execute($params);

    sendResponse("User updated", 200);
}

function deleteUser($db, $id) {
    if (!$id) {
        sendResponse("User ID is required", 400);
    }

    $check = $db->prepare("SELECT id FROM users WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse("User not found", 404);
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $ok   = $stmt->execute([$id]);

    if ($ok) {
        sendResponse("User deleted", 200);
    } else {
        sendResponse("Failed to delete user", 500);
    }
}

function changePassword($db, $data) {
    $id          = isset($data["id"])               ? (int)$data["id"]              : 0;
    $currentPass = trim($data["current_password"]   ?? "");
    $newPass     = trim($data["new_password"]        ?? "");

    if (!$id || !$currentPass || !$newPass) {
        sendResponse("ID, current password, and new password are required", 400);
    }

    if (strlen($newPass) < 8) {
        sendResponse("New password must be at least 8 characters", 400);
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendResponse("User not found", 404);
    }

    if (!password_verify($currentPass, $row["password"])) {
        sendResponse("Current password is incorrect", 401);
    }

    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $upd  = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $ok   = $upd->execute([$hash, $id]);

    if ($ok) {
        sendResponse("Password updated successfully", 200);
    } else {
        sendResponse("Failed to update password", 500);
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if ($statusCode < 400) {
        echo json_encode(["success" => true,  "data"    => $data]);
    } else {
        echo json_encode(["success" => false, "message" => $data]);
    }
    exit;
}

function validateEmail($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = strip_tags($data);
    return $data;
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === "GET") {
        if ($id) {
            getUserById($db, $id);
        } else {
            getUsers($db);
        }

    } elseif ($method === "POST") {
        if ($action === "change_password") {
            changePassword($db, $data);
        } else {
            createUser($db, $data);
        }

    } elseif ($method === "PUT") {
        updateUser($db, $data);

    } elseif ($method === "DELETE") {
        $id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
        deleteUser($db, $id);

    } else {
        sendResponse("Method not allowed", 405);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse("Database error. Please try again.", 500);

} catch (Exception $e) {
    sendResponse($e->getMessage(), 500);
}
?>
