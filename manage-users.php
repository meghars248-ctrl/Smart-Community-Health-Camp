
<?php
session_start();
require_once __DIR__ . "/database.php";

// Admin access only
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

function findColumn($columns, $options) {
    foreach ($options as $option) {
        if (in_array($option, $columns, true)) {
            return $option;
        }
    }
    return null;
}

$message = "";
$messageType = "success";
$users = [];
$totalUsers = 0;
$totalDoctors = 0;
$totalVolunteers = 0;
$totalAdmins = 0;
$search = trim($_GET["search"] ?? "");
$roleFilter = trim($_GET["role"] ?? "");

try {
    // Detect existing database columns.
    $columnInfo = $pdo->query("SHOW COLUMNS FROM users")
                      ->fetchAll(PDO::FETCH_ASSOC);
    $columns = array_column($columnInfo, "Field");

    $idCol = findColumn($columns, ["user_id", "id"]);
    $nameCol = findColumn($columns, ["full_name", "name", "fullname"]);
    $usernameCol = findColumn($columns, ["username", "user_name"]);
    $passwordCol = findColumn(
        $columns,
        ["password_hash", "password", "user_password"]
    );
    $roleCol = findColumn($columns, ["role", "user_role"]);
    $createdCol = findColumn(
        $columns,
        ["created_at", "created_on", "registration_date"]
    );

    if (!$idCol || !$nameCol || !$usernameCol || !$passwordCol || !$roleCol) {
        throw new Exception("Required columns are missing from the users table.");
    }

    // Create a Doctor or Volunteer account.
    if (
        $_SERVER["REQUEST_METHOD"] === "POST" &&
        ($_POST["action"] ?? "") === "create"
    ) {
        $fullName = trim($_POST["full_name"] ?? "");
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";
        $role = $_POST["new_role"] ?? "";

        if (
            strlen($fullName) < 2 ||
            strlen($fullName) > 100 ||
            !preg_match('/^[A-Za-z0-9_]{4,50}$/', $username) ||
            strlen($password) < 8 ||
            !in_array($role, ["Doctor", "Volunteer"], true)
        ) {
            $message = "Enter a valid name, username, password and role.";
            $messageType = "error";
        } else {
            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM users WHERE `$usernameCol` = ?"
            );
            $check->execute([$username]);

            if ((int)$check->fetchColumn() > 0) {
                $message = "This username already exists. Choose another.";
                $messageType = "error";
            } else {
                $insertColumns = [
                    $nameCol,
                    $usernameCol,
                    $passwordCol,
                    $roleCol
                ];

                $values = [
                    $fullName,
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    $role
                ];

                if ($createdCol) {
                    $insertColumns[] = $createdCol;
                    $values[] = date("Y-m-d H:i:s");
                }

                $quotedColumns = array_map(
                    fn($column) => "`$column`",
                    $insertColumns
                );

                $placeholders = implode(
                    ", ",
                    array_fill(0, count($values), "?")
                );

                $sql = "INSERT INTO users (" .
                       implode(", ", $quotedColumns) .
                       ") VALUES ($placeholders)";

                $insert = $pdo->prepare($sql);
                $insert->execute($values);

                header("Location: manage-users.php?created=1");
                exit;
            }
        }
    }

    // Success messages
    if (isset($_GET["created"])) {
        $message = "Staff account created successfully!";
        $messageType = "success";
    }

    elseif (isset($_GET["deleted"])) {
        $message = "User account deleted successfully!";
        $messageType = "success";
    }

    elseif (isset($_GET["edited"])) {
        $message = "User details updated successfully!";
        $messageType = "success";
    }

    elseif (isset($_GET["delete_error"])) {
        $message = "The user could not be deleted. The account may be protected or linked to other records.";
        $messageType = "error";
    }

    // User statistics
    $stats = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(LOWER(`$roleCol`) = 'doctor'), 0) AS doctors,
            COALESCE(SUM(LOWER(`$roleCol`) = 'volunteer'), 0) AS volunteers,
            COALESCE(SUM(LOWER(`$roleCol`) = 'admin'), 0) AS admins
         FROM users"
    )->fetch(PDO::FETCH_ASSOC);

    $totalUsers = (int)$stats["total"];
    $totalDoctors = (int)$stats["doctors"];
    $totalVolunteers = (int)$stats["volunteers"];
    $totalAdmins = (int)$stats["admins"];

    // Search and role filter
    $conditions = [];
    $params = [];

    if ($search !== "") {
        $conditions[] = "(`$nameCol` LIKE ? OR `$usernameCol` LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (in_array($roleFilter, ["Admin", "Doctor", "Volunteer"], true)) {
        $conditions[] = "LOWER(`$roleCol`) = LOWER(?)";
        $params[] = $roleFilter;
    } else {
        $roleFilter = "";
    }

    $where = count($conditions) > 0
        ? " WHERE " . implode(" AND ", $conditions)
        : "";

    $selectColumns = "`$idCol` AS user_id,
                      `$nameCol` AS full_name,
                      `$usernameCol` AS username,
                      `$roleCol` AS role";

    if ($createdCol) {
        $selectColumns .= ", `$createdCol` AS created_at";
    }

    $orderBy = $createdCol
        ? " ORDER BY `$createdCol` DESC"
        : " ORDER BY `$idCol` DESC";

    $listSql = "SELECT $selectColumns FROM users" . $where . $orderBy;
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($params);
    $users = $listStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $message = "Unable to load users. Check your database connection and users table columns.";
    $messageType = "error";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Users | Health Camp</title>

<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f3f7fc;color:#243247}
header{padding:25px 6%;background:linear-gradient(135deg,#0757a6,#1685dc);color:white}
header h1{margin:0 0 8px;font-size:27px}
header p{margin:0;color:#e4f2ff;line-height:1.5}
nav{display:flex;flex-wrap:wrap;gap:8px;padding:14px 6%;background:white;box-shadow:0 2px 8px #dce5ef}
nav a{color:#0757a6;text-decoration:none;padding:10px 12px;border-radius:7px;font-weight:bold;font-size:14px}
nav a:hover,nav a.active{background:#e4f1ff}
.container{width:90%;max-width:1150px;margin:30px auto}
.heading{margin-bottom:22px}
.heading h2{color:#123d70;margin:0 0 8px}
.heading p{color:#64748b;margin:0;line-height:1.5}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:25px}
.stat-card{background:white;padding:20px;border-radius:12px;box-shadow:0 4px 15px #1b416e10;border:1px solid #e6edf5}
.stat-card p{margin:0 0 10px;color:#64748b;font-size:14px}
.stat-card h3{margin:0;color:#0757a6;font-size:30px}
.stat-card.doctors h3{color:#187343}
.stat-card.volunteers h3{color:#a56600}
.stat-card.admins h3{color:#6334a5}
.panel{background:white;padding:25px;border-radius:14px;margin-bottom:25px;box-shadow:0 5px 20px #1b416e12}
.panel h3{margin:0 0 20px;color:#123d70;font-size:21px}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px}
.field label{display:block;font-weight:bold;margin-bottom:8px;font-size:14px}
.field input,.field select,.search-input,.filter-select{width:100%;padding:12px;border:1px solid #ccd8e6;border-radius:8px;background:white;font-size:15px;color:#243247}
.field input:focus,.field select:focus,.search-input:focus,.filter-select:focus{outline:2px solid #a9d3ff;border-color:#1685dc}
.hint{display:block;color:#718096;font-size:12px;margin-top:6px}
.button{display:inline-block;border:0;background:#0866bd;color:white;padding:12px 18px;border-radius:8px;font-size:14px;font-weight:bold;cursor:pointer;text-decoration:none}
.button:hover{background:#064e91}
.create-button{margin-top:20px}
.delete-button{background:#dc2626;margin-top:10px}
.delete-button:hover{background:#a91d1d}
.edit-button{background:#087f8c;margin-top:10px}
.edit-button:hover{background:#06636d}
.message{padding:14px 16px;border-radius:8px;margin-bottom:20px;line-height:1.5}
.message.success{background:#e5f8ed;color:#17653b;border:1px solid #b7e8ca}
.message.error{background:#fff0f0;color:#a32222;border:1px solid #f0c1c1}
.users-heading{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px;margin-bottom:20px}
.users-heading h3{margin:0}
.filters{display:flex;flex-wrap:wrap;gap:8px;width:min(100%,600px)}
.search-input{flex:1;min-width:170px}
.filter-select{width:auto;min-width:135px}
.users-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.user-card{border:1px solid #e0e9f3;border-radius:12px;padding:19px;background:#fbfdff;overflow-wrap:anywhere}
.user-top{display:flex;align-items:center;gap:13px;margin-bottom:16px}
.avatar{width:47px;height:47px;flex-shrink:0;display:grid;place-items:center;border-radius:50%;background:#dceeff;color:#0757a6;font-size:19px;font-weight:bold}
.user-card h4{margin:0 0 7px;font-size:17px;color:#203b5d}
.role{display:inline-block;padding:5px 10px;border-radius:20px;font-size:12px;font-weight:bold}
.role.admin{background:#eee6ff;color:#6334a5}
.role.doctor{background:#dff7e9;color:#17653b}
.role.volunteer{background:#fff0d5;color:#875400}
.user-info{color:#52647a;font-size:14px;line-height:1.9}
.user-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.user-actions form{margin:0}
.user-actions .button{margin-top:0}
.empty{text-align:center;padding:30px;color:#718096;border:1px dashed #ccd8e6;border-radius:10px}
footer{text-align:center;color:#718096;font-size:13px;padding:10px 15px 25px}
@media(max-width:800px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){
 header{padding:22px 5%}
 header h1{font-size:23px}
 nav{padding:12px 4%;gap:5px}
 nav a{padding:9px;font-size:13px}
 .container{width:92%;margin:22px auto}
 .panel{padding:18px}
 .form-grid,.users-grid{grid-template-columns:1fr}
 .filters{width:100%}
 .search-input{flex-basis:100%}
 .filter-select{flex:1}
 .stats{gap:10px}
 .stat-card{padding:15px}
 .stat-card h3{font-size:25px}
}
</style>
</head>

<body>
<header>
    <h1>Health Camp Admin Panel</h1>
    <p>Manage staff accounts and access information in one place.</p>
</header>

<nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="manage-camps.php">Manage Camps</a>
    <a href="manage-queue.php">Manage Queue</a>
    <a href="manage-users.php" class="active">Manage Users</a>
    <a href="reports.php">Reports</a>
    <a href="logout.php">Logout</a>
</nav>

<main class="container">
    <div class="heading">
        <h2>Manage Users</h2>
        <p>Create staff accounts, view statistics, search users, and manage accounts.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?= h($messageType) ?>">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat-card">
            <p>Total Users</p>
            <h3><?= $totalUsers ?></h3>
        </div>
        <div class="stat-card doctors">
            <p>Doctors</p>
            <h3><?= $totalDoctors ?></h3>
        </div>
        <div class="stat-card volunteers">
            <p>Volunteers</p>
            <h3><?= $totalVolunteers ?></h3>
        </div>
        <div class="stat-card admins">
            <p>Admins</p>
            <h3><?= $totalAdmins ?></h3>
        </div>
    </section>

    <section class="panel">
        <h3>Create Staff Account</h3>

        <form method="POST" action="manage-users.php" autocomplete="off">
            <input type="hidden" name="action" value="create">

            <div class="form-grid">
                <div class="field">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name"
                           placeholder="Enter full name" minlength="2"
                           maxlength="100" required>
                </div>

                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           placeholder="e.g. doctor_123"
                           pattern="[A-Za-z0-9_]{4,50}"
                           title="Use 4–50 letters, numbers, or underscores."
                           required>
                    <span class="hint">4–50 letters, numbers, or underscores.</span>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           minlength="8" placeholder="Create a password"
                           autocomplete="new-password" required>
                    <span class="hint">At least 8 characters.</span>
                </div>

                <div class="field">
                    <label for="new_role">Staff Role</label>
                    <select id="new_role" name="new_role" required>
                        <option value="">Select Role</option>
                        <option value="Doctor">Doctor</option>
                        <option value="Volunteer">Volunteer</option>
                    </select>
                </div>
            </div>

            <button class="button create-button" type="submit">+ Create Account</button>
        </form>
    </section>

    <section class="panel">
        <div class="users-heading">
            <h3>Registered Users (<?= count($users) ?>)</h3>

            <form class="filters" method="GET" action="manage-users.php">
                <input class="search-input" type="search" name="search"
                       placeholder="Search name or username"
                       value="<?= h($search) ?>">

                <select class="filter-select" name="role" aria-label="Filter by role">
                    <option value="">All Roles</option>
                    <option value="Admin" <?= $roleFilter === "Admin" ? "selected" : "" ?>>Admin</option>
                    <option value="Doctor" <?= $roleFilter === "Doctor" ? "selected" : "" ?>>Doctor</option>
                    <option value="Volunteer" <?= $roleFilter === "Volunteer" ? "selected" : "" ?>>Volunteer</option>
                </select>

                <button class="button" type="submit">Search / Filter</button>
                <a class="button" href="manage-users.php" style="background:#64748b">Reset</a>
            </form>
        </div>

        <?php if (count($users) > 0): ?>
            <div class="users-grid">
                <?php foreach ($users as $user): ?>
                    <?php
                    $roleClass = strtolower($user["role"] ?? "");
                    if (!in_array($roleClass, ["admin", "doctor", "volunteer"], true)) {
                        $roleClass = "";
                    }

                    $initial = strtoupper(substr(
                        trim($user["full_name"] ?? "U"),
                        0,
                        1
                    ));

                    $isCurrentUser =
                        (int)$user["user_id"] === (int)$_SESSION["user_id"];

                    $isAdmin = strtolower($user["role"] ?? "") === "admin";
                    ?>

                    <article class="user-card">
                        <div class="user-top">
                            <div class="avatar"><?= h($initial) ?></div>
                            <div>
                                <h4><?= h($user["full_name"] ?? "") ?></h4>
                                <span class="role <?= h($roleClass) ?>">
                                    <?= h($user["role"] ?? "") ?>
                                </span>
                            </div>
                        </div>

                        <div class="user-info">
                            <div><strong>Username:</strong> <?= h($user["username"] ?? "") ?></div>

                            <?php if (!empty($user["created_at"])): ?>
                                <div><strong>Created:</strong> <?= h($user["created_at"]) ?></div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$isCurrentUser && !$isAdmin): ?>
                            <div class="user-actions">
                                <a class="button edit-button"
                                   href="edit-user.php?id=<?= (int)$user["user_id"] ?>">
                                    Edit User
                                </a>

                                <form method="POST" action="delete-user.php"
                                      onsubmit="return confirm('Delete account <?= h($user["username"] ?? "") ?>? This action cannot be undone.');">
                                    <input type="hidden" name="user_id"
                                           value="<?= (int)$user["user_id"] ?>">
                                    <button class="button delete-button" type="submit">
                                        Delete User
                                    </button>
                                </form>
                            </div>
                        <?php elseif ($isCurrentUser): ?>
                            <p class="hint">This is your account.</p>
                        <?php else: ?>
                            <p class="hint">Admin accounts are protected.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">
                No users found. Try another search or role filter.
            </div>
        <?php endif; ?>
    </section>
</main>

<footer>Smart Community Health Camp Monitoring System</footer>
</body>
</html>
