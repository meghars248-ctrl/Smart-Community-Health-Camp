
<?php
session_start();
require_once "database.php";

if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "Admin"
) {
    http_response_code(403);
    exit("Access denied. Please log in as Admin.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Use the delete confirmation form.");
}

$campId = filter_input(INPUT_POST, "camp_id", FILTER_VALIDATE_INT);
$confirm = $_POST["confirm"] ?? "";

if (!$campId || $confirm !== "yes") {
    exit("Invalid delete request.");
}

// Do not delete a camp that has patient registrations.
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM registrations WHERE camp_id = ?"
);
$stmt->execute([$campId]);

if ((int)$stmt->fetchColumn() > 0) {
    exit("This camp has patient registrations. Do not delete it; mark it Completed instead.");
}

$stmt = $pdo->prepare("DELETE FROM health_camps WHERE camp_id = ?");
$stmt->execute([$campId]);

header("Location: manage-camps.php?deleted=1");
exit;
?>
