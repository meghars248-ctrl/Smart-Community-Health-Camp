
<?php
session_start();
require_once __DIR__ . "/database.php";

// Only administrators may delete accounts.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    strtolower($_SESSION["role"]) !== "admin"
) {
    header("Location: login.php");
    exit;
}

// Deletion must be submitted through POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage-users.php");
    exit;
}

$userId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);

if (!$userId || $userId === (int)$_SESSION["user_id"]) {
    header("Location: manage-users.php?delete_error=invalid");
    exit;
}

try {
    // Detect the user table's ID and role columns.
    $columns = $pdo->query("SHOW COLUMNS FROM users")
                   ->fetchAll(PDO::FETCH_COLUMN);

    $idCol = in_array("user_id", $columns, true)
        ? "user_id"
        : (in_array("id", $columns, true) ? "id" : null);

    $roleCol = in_array("role", $columns, true)
        ? "role"
        : (in_array("user_role", $columns, true) ? "user_role" : null);

    if (!$idCol || !$roleCol) {
        throw new Exception("Could not identify the user ID or role column.");
    }

    // Do not delete Admin accounts.
    $stmt = $pdo->prepare(
        "SELECT `$roleCol` FROM users WHERE `$idCol` = ?"
    );
    $stmt->execute([$userId]);
    $role = $stmt->fetchColumn();

    if ($role === false || strtolower((string)$role) === "admin") {
        header("Location: manage-users.php?delete_error=protected");
        exit;
    }

    // Delete only the selected user account.
    $delete = $pdo->prepare(
        "DELETE FROM users WHERE `$idCol` = ?"
    );
    $delete->execute([$userId]);

    header("Location: manage-users.php?deleted=1");
    exit;

} catch (Throwable $e) {
    // A foreign-key relationship may prevent deleting this account.
    header("Location: manage-users.php?delete_error=failed");
    exit;
}
