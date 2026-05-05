<?php
/**
 * Authentication Handler for Login Form
 */

// --- Session Management ---
session_start();

// --- Set Response Headers ---
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// --- Check Request Method ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// --- Get POST Data ---
$rawData = file_get_contents("php://input");
$data    = json_decode($rawData, true);

// --- Extract and check fields ---
if (!isset($data["email"]) || !isset($data["password"])) {
    echo json_encode(["success" => false, "message" => "Email and password are required"]);
    exit;
}

$email    = trim($data["email"]);
$password = $data["password"];

// --- Server-Side Validation ---
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email format"]);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(["success" => false, "message" => "Password must be at least 8 characters"]);
    exit;
}

// --- Database Connection ---
$pdo = getDBConnection();

// --- Database Operations ---
try {
    // --- Prepare SQL Query ---
    $sql = "SELECT id, name, email, password, is_admin FROM users WHERE email = ? LIMIT 1";

    // --- Prepare the Statement ---
    $stmt = $pdo->prepare($sql);

    // --- Execute the Query ---
    $stmt->execute([$email]);

    // --- Fetch User Data ---
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Verify User Exists and Password Matches ---
    if ($user && password_verify($password, $user["password"])) {

        // --- Handle Successful Authentication ---

        // Store user information in session variables
        $_SESSION["user_id"]    = $user["id"];
        $_SESSION["user_name"]  = $user["name"];
        $_SESSION["user_email"] = $user["email"];
        $_SESSION["is_admin"]   = $user["is_admin"];
        $_SESSION["logged_in"]  = true;

        // Prepare a success response (NO password)
        $response = [
            "success" => true,
            "message" => "Login successful",
            "user"    => [
                "id"       => $user["id"],
                "name"     => $user["name"],
                "email"    => $user["email"],
                "is_admin" => $user["is_admin"],
            ]
        ];

        echo json_encode($response);
        exit;

    } else {

        // --- Handle Failed Authentication ---
        echo json_encode(["success" => false, "message" => "Invalid email or password"]);
        exit;
    }

} catch (PDOException $e) {

    // Log the error for debugging
    error_log($e->getMessage());

    // Return a generic error message
    echo json_encode(["success" => false, "message" => "A server error occurred. Please try again."]);
    exit;
}
?>
