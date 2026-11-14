
<?php
session_start();
require_once __DIR__ . "/database.php";

// Only administrators can edit staff accounts.
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    strtolower($_SESSION["role"]) !== "admin"
) {
    header("Location: login.php");
    exit;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$user = null;

try {
    $columns = $pdo->query("SHOW COLUMNS FROM users")
                   ->fetchAll(PDO::FETCH_COLUMN);

    function findEditColumn($columns, $choices) {
        foreach ($choices as $choice) {
            if (in_array($choice, $columns, true)) {
                return $choice;
            }
        }
        return null;
    }

    $idCol = findEditColumn($columns, ["user_id", "id"]);
    $nameCol = findEditColumn($columns, ["full_name", "name", "fullname"]);
    $usernameCol = findEditColumn($columns, ["username", "user_name"]);
    $passwordCol = findEditColumn($columns, ["password_hash", "password", "user_password"]);
    $roleCol = findEditColumn($columns, ["role", "user_role"]);

    if (!$idCol || !$nameCol || !$usernameCol || !$passwordCol || !$roleCol) {
        throw new Exception("Required columns are missing from the users table.");
    }

    $userId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $userId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);
        $csrf = $_POST["csrf_token"] ?? "";

        if (!is_string($csrf) ||
            !hash_equals($_SESSION["csrf_token"], $csrf)) {
            throw new Exception("Invalid request. Refresh the page and try again.");
        }
    }

    if (!$userId || $userId === (int)$_SESSION["user_id"]) {
        throw new Exception("Invalid user selection. You cannot edit your own account here.");
    }

    // Find the selected account.
    $stmt = $pdo->prepare(
        "SELECT `$idCol` AS user_id,
                `$nameCol` AS full_name,
                `$usernameCol` AS username,
                `$roleCol` AS role
         FROM users WHERE `$idCol` = ?"
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User account not found.");
    }

    // Admin accounts are protected.
    if (strtolower($user["role"]) === "admin") {
        throw new Exception("Admin accounts cannot be edited on this page.");
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $fullName = trim($_POST["full_name"] ?? "");
        $username = trim($_POST["username"] ?? "");
        $newPassword = $_POST["new_password"] ?? "";

        if (
            strlen($fullName) < 2 ||
            strlen($fullName) > 100 ||
            !preg_match('/^[A-Za-z0-9_]{4,50}$/', $username) ||
            ($newPassword !== "" && strlen($newPassword) < 8)
        ) {
            $message = "Enter a valid name and username. A new password must have at least 8 characters.";
        } else {
            // Check that another account is not using this username.
            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM users
                 WHERE `$usernameCol` = ? AND `$idCol` <> ?"
            );
            $check->execute([$username, $userId]);

            if ((int)$check->fetchColumn() > 0) {
                $message = "That username is already used by another account.";
            } else {
                $sql = "UPDATE users
                        SET `$nameCol` = ?, `$usernameCol` = ?";
                $values = [$fullName, $username];

                // Change the password only when a new one is supplied.
                if ($newPassword !== "") {
                    $sql .= ", `$passwordCol` = ?";
                    $values[] = password_hash($newPassword, PASSWORD_DEFAULT);
                }

                $sql .= " WHERE `$idCol` = ?";
                $values[] = $userId;

                $update = $pdo->prepare($sql);
                $update->execute($values);

                header("Location: manage-users.php?edited=1");
                exit;
            }
        }

        // Keep entered name and username if validation fails.
        $user["full_name"] = $fullName;
        $user["username"] = $username;
    }

} catch (Throwable $e) {
    if ($message === "") {
        $message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit User | Health Camp</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#f3f7fc;color:#243247;font-family:Arial,sans-serif}
header{background:linear-gradient(135deg,#0757a6,#1685dc);color:white;padding:25px 6%}
header h1{margin:0 0 8px;font-size:26px}
header p{margin:0;color:#e4f2ff}
nav{display:flex;flex-wrap:wrap;gap:8px;padding:14px 6%;background:white;box-shadow:0 2px 8px #dce5ef}
nav a{color:#0757a6;text-decoration:none;padding:10px 12px;border-radius:7px;font-weight:bold;font-size:14px}
nav a:hover{background:#e4f1ff}
.container{width:92%;max-width:650px;margin:35px auto}
.panel{background:white;padding:28px;border-radius:14px;box-shadow:0 5px 20px #1b416e12}
h2{color:#123d70;margin-top:0}
p{line-height:1.5}
.field{margin-bottom:19px}
label{display:block;font-weight:bold;margin-bottom:8px}
input{width:100%;padding:12px;border:1px solid #ccd8e6;border-radius:8px;font-size:15px}
input:focus{outline:2px solid #a9d3ff;border-color:#1685dc}
.hint{font-size:12px;color:#718096;margin-top:6px}
.button{display:inline-block;border:0;background:#0866bd;color:white;padding:12px 18px;border-radius:8px;font-weight:bold;text-decoration:none;cursor:pointer}
.button:hover{background:#064e91}
.cancel{background:#e6edf5;color:#243247;margin-left:7px}
.cancel:hover{background:#d4e0ed}
.message{padding:13px;background:#fff0f0;color:#a32222;border-radius:8px;margin-bottom:18px}
footer{text-align:center;color:#718096;padding:20px}
@media(max-width:500px){.panel{padding:20px}.cancel{margin:10px 0 0 0}}
</style>
</head>
<body>
<header>
    <h1>Health Camp Admin Panel</h1>
    <p>Edit staff account details</p>
</header>
<nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="manage-users.php">Manage Users</a>
    <a href="manage-camps.php">Manage Camps</a>
    <a href="manage-queue.php">Manage Queue</a>
    <a href="logout.php">Logout</a>
</nav>

<main class="container">
<section class="panel">
    <h2>Edit User Details</h2>

    <?php if ($message !== ""): ?>
        <div class="message"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($user && strtolower($user["role"]) !== "admin"
              && (int)$user["user_id"] !== (int)$_SESSION["user_id"]): ?>
        <p>Account role: <strong><?= h($user["role"]) ?></strong></p>

        <form method="POST" action="edit-user.php?id=<?= (int)$user["user_id"] ?>">
            <input type="hidden" name="user_id" value="<?= (int)$user["user_id"] ?>">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION["csrf_token"]) ?>">

            <div class="field">
                <label for="full_name">Full Name</label>
                <input id="full_name" name="full_name" type="text"
                       minlength="2" maxlength="100"
                       value="<?= h($user["full_name"]) ?>" required>
            </div>

            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text"
                       pattern="[A-Za-z0-9_]{4,50}"
                       title="Use 4–50 letters, numbers, or underscores."
                       value="<?= h($user["username"]) ?>" required>
            </div>

            <div class="field">
                <label for="new_password">New Password (optional)</label>
                <input id="new_password" name="new_password" type="password"
                       minlength="8" autocomplete="new-password"
                       placeholder="Leave blank to keep current password">
                <div class="hint">Enter at least 8 characters only if you want to reset the password.</div>
            </div>

            <button class="button" type="submit">Save Changes</button>
            <a class="button cancel" href="manage-users.php">Cancel</a>
        </form>
    <?php else: ?>
        <p>Select a valid Doctor or Volunteer account from Manage Users.</p>
        <a class="button" href="manage-users.php">Back to Manage Users</a>
    <?php endif; ?>
</section>
</main>
<footer>Smart Community Health Camp Monitoring System</footer>
</body>
</html>
