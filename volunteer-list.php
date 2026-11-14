
<?php
require_once "database.php";

$message = "";
$error = "";

// Add a volunteer
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["volunteer_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $campIdInput = $_POST["assigned_camp_id"] ?? "";
    $campId = $campIdInput === "" ? null : filter_var($campIdInput, FILTER_VALIDATE_INT);

    if (
        $name === "" || strlen($name) > 100 ||
        $username === "" || strlen($username) > 50 ||
        ($phone !== "" && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) ||
        ($campIdInput !== "" && (!$campId || $campId < 1))
    ) {
        $error = "Please enter valid volunteer details.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO volunteers
                 (volunteer_name, username, phone, assigned_camp_id)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([
                $name,
                $username,
                $phone !== "" ? $phone : null,
                $campId
            ]);

            $message = "Volunteer added successfully.";
        } catch (PDOException $e) {
            $error = "Could not add volunteer. Check whether the username already exists and the database table is correct.";
        }
    }
}

// Load camps for the assignment dropdown
$camps = [];
$volunteers = [];

try {
    $camps = $pdo->query(
        "SELECT camp_id, camp_name, camp_date
         FROM health_camps
         ORDER BY camp_date DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $volunteers = $pdo->query(
        "SELECT
            v.volunteer_id,
            v.volunteer_name,
            v.username,
            v.phone,
            c.camp_name,
            c.camp_date
         FROM volunteers v
         LEFT JOIN health_camps c
            ON v.assigned_camp_id = c.camp_id
         ORDER BY v.volunteer_name"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Unable to load volunteers. Check your database table.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Volunteer List</title>
<link rel="stylesheet" href="css/style.css">
<style>
body{margin:0;background:#f0f7ff;color:#24344d;font-family:Arial,sans-serif}
header{background:linear-gradient(120deg,#075bb5,#0878e8);color:white;padding:20px 5%}
header h1{margin-top:0}
nav{display:flex;gap:18px;flex-wrap:wrap}
nav a{color:white;text-decoration:none;font-weight:bold}
main{max-width:1100px;margin:30px auto;padding:0 18px}
.panel{background:white;padding:24px;margin-bottom:24px;border-radius:12px;border-top:5px solid #0878e8;box-shadow:0 4px 14px #00000010}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
label{display:block;font-weight:bold;margin-bottom:7px}
input,select{box-sizing:border-box;width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:7px;font-size:15px}
button{margin-top:18px;padding:12px 18px;background:#0878e8;color:white;border:0;border-radius:7px;cursor:pointer;font-weight:bold}
.notice{padding:13px;border-radius:7px;background:#dcfce7;color:#166534;margin-bottom:18px}
.error{background:#fee2e2;color:#991b1b}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:650px}
th,td{text-align:left;padding:12px;border-bottom:1px solid #e2e8f0}
th{background:#e7f2ff;color:#075bb5}
@media(max-width:600px){.panel{padding:16px}}
</style>
</head>
<body>
<header>
<h1>Smart Community Health Camp</h1>
<nav>
<a href="dashboard.php">Dashboard</a>
<a href="camp-details.php">Camp Details</a>
<a href="register.php">Patient Registration</a>
<a href="manage-queue.php">Patient Queue</a>
<a href="manage-medicines.php">Medicine Inventory</a>
<a href="volunteer-list.php">Volunteer List</a>
<a href="logout.php">Logout</a>
</nav>
</header>

<main>
<h1>Volunteer Management</h1>
<p>Add volunteers and assign them to health camps.</p>

<?php if ($message !== ""): ?>
<div class="notice"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
<?php endif; ?>

<?php if ($error !== ""): ?>
<div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
<?php endif; ?>

<section class="panel">
<h2>Add a Volunteer</h2>
<form method="post" action="volunteer-list.php">
<div class="grid">
<div>
<label for="volunteer_name">Volunteer Name *</label>
<input id="volunteer_name" name="volunteer_name" maxlength="100" required>
</div>
<div>
<label for="username">Username *</label>
<input id="username" name="username" maxlength="50" required>
</div>
<div>
<label for="phone">Phone Number</label>
<input id="phone" name="phone" type="tel" maxlength="20">
</div>
<div>
<label for="assigned_camp_id">Assigned Health Camp</label>
<select id="assigned_camp_id" name="assigned_camp_id">
<option value="">Not assigned</option>
<?php foreach ($camps as $camp): ?>
<option value="<?= (int)$camp["camp_id"] ?>">
<?= htmlspecialchars($camp["camp_name"], ENT_QUOTES, "UTF-8") ?>
— <?= htmlspecialchars($camp["camp_date"], ENT_QUOTES, "UTF-8") ?>
</option>
<?php endforeach; ?>
</select>
</div>
</div>
<button type="submit">Add Volunteer</button>
</form>
</section>

<section class="panel">
<h2>Volunteer List</h2>

<?php if (!$volunteers): ?>
<p>No volunteers have been added yet.</p>
<?php else: ?>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Name</th>
<th>Username</th>
<th>Phone</th>
<th>Assigned Camp</th>
</tr>
</thead>
<tbody>
<?php foreach ($volunteers as $volunteer): ?>
<tr>
<td><?= htmlspecialchars($volunteer["volunteer_name"], ENT_QUOTES, "UTF-8") ?></td>
<td><?= htmlspecialchars($volunteer["username"], ENT_QUOTES, "UTF-8") ?></td>
<td><?= htmlspecialchars($volunteer["phone"] ?? "", ENT_QUOTES, "UTF-8") ?: "Not provided" ?></td>
<td>
<?php if ($volunteer["camp_name"]): ?>
<?= htmlspecialchars($volunteer["camp_name"], ENT_QUOTES, "UTF-8") ?>
(<?= htmlspecialchars($volunteer["camp_date"], ENT_QUOTES, "UTF-8") ?>)
<?php else: ?>
Not assigned
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</section>
</main>
</body>
</html>
