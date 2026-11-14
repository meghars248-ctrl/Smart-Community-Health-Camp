
<?php
require_once "database.php";

$message = "";
$error = "";

// Add a new medicine or update an existing medicine's stock.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    try {
        if ($action === "add") {
            $name = trim($_POST["medicine_name"] ?? "");
            $quantity = filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT);
            $unit = trim($_POST["unit"] ?? "tablets");
            $expiry = trim($_POST["expiry_date"] ?? "");
            $limit = filter_input(INPUT_POST, "low_stock_limit", FILTER_VALIDATE_INT);

            if (
                $name === "" ||
                strlen($name) > 150 ||
                $quantity === false || $quantity === null || $quantity < 0 ||
                $unit === "" || strlen($unit) > 30 ||
                $limit === false || $limit === null || $limit < 0
            ) {
                throw new InvalidArgumentException("Please enter valid medicine details.");
            }

            if ($expiry !== "") {
                $date = DateTime::createFromFormat("!Y-m-d", $expiry);
                if (!$date || $date->format("Y-m-d") !== $expiry) {
                    throw new InvalidArgumentException("Please enter a valid expiry date.");
                }
            } else {
                $expiry = null;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO medicines
                 (medicine_name, quantity, unit, expiry_date, low_stock_limit)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$name, $quantity, $unit, $expiry, $limit]);

            $message = "Medicine added successfully.";

        } elseif ($action === "update") {
            $id = filter_input(INPUT_POST, "medicine_id", FILTER_VALIDATE_INT);
            $quantity = filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT);

            if (
                !$id ||
                $quantity === false || $quantity === null || $quantity < 0
            ) {
                throw new InvalidArgumentException("Please enter a valid stock quantity.");
            }

            $stmt = $pdo->prepare(
                "UPDATE medicines SET quantity = ? WHERE medicine_id = ?"
            );
            $stmt->execute([$quantity, $id]);

            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare(
                    "SELECT medicine_id FROM medicines WHERE medicine_id = ?"
                );
                $check->execute([$id]);

                if (!$check->fetch()) {
                    throw new InvalidArgumentException("Medicine record not found.");
                }
            }

            $message = "Medicine stock updated successfully.";

        } elseif ($action === "delete") {
            $id = filter_input(INPUT_POST, "medicine_id", FILTER_VALIDATE_INT);

            if (!$id) {
                throw new InvalidArgumentException("Invalid medicine record.");
            }

            $stmt = $pdo->prepare(
                "DELETE FROM medicines WHERE medicine_id = ?"
            );
            $stmt->execute([$id]);

            $message = $stmt->rowCount()
                ? "Medicine deleted successfully."
                : "Medicine record not found.";
        }

    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = "Database operation failed. Please check the medicines table.";
    }
}

try {
    $stmt = $pdo->query(
        "SELECT * FROM medicines ORDER BY medicine_name ASC"
    );
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $medicines = [];
    $error = $error ?: "Unable to load medicines. Check that the medicines table exists.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medicine Inventory</title>
<link rel="stylesheet" href="css/style.css">
<style>
body{margin:0;background:#f0f7ff;color:#24344d;font-family:Arial,sans-serif}
header{background:linear-gradient(120deg,#075bb5,#0878e8);color:white;padding:20px 5%}
header h1{margin-top:0}
nav{display:flex;gap:18px;flex-wrap:wrap}
nav a{color:white;text-decoration:none;font-weight:bold}
main{max-width:1150px;margin:30px auto;padding:0 18px}
.panel{background:white;padding:24px;margin-bottom:25px;border-radius:12px;border-top:5px solid #0878e8;box-shadow:0 4px 14px #00000010}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
label{display:block;font-weight:bold;margin-bottom:7px}
input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:7px;font-size:15px}
button{padding:10px 14px;border:0;border-radius:6px;background:#0878e8;color:white;cursor:pointer;font-weight:bold}
button:hover{background:#075bb5}
.danger{background:#b91c1c}
.notice{padding:13px;border-radius:7px;background:#dcfce7;color:#166534;margin-bottom:18px}
.error{background:#fee2e2;color:#991b1b}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:750px}
th,td{text-align:left;padding:12px;border-bottom:1px solid #e2e8f0}
th{background:#e7f2ff;color:#075bb5}
.low{color:#b91c1c;font-weight:bold}
.ok{color:#15803d;font-weight:bold}
.inline-form{display:flex;gap:7px;align-items:center}
.inline-form input{width:100px}
@media(max-width:600px){main{padding:0 12px}.panel{padding:16px}}
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
<a href="reports.php">Reports</a>
<a href="manage-medicines.php">Medicine Inventory</a>
<a href="logout.php">Logout</a>
</nav>
</header>

<main>
<h1>Medicine Inventory</h1>
<p>Add medicines, update stock, and monitor low inventory.</p>

<?php if ($message !== ""): ?>
<div class="notice"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
<?php endif; ?>

<?php if ($error !== ""): ?>
<div class="notice error"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
<?php endif; ?>

<section class="panel">
<h2>Add a New Medicine</h2>
<form method="post" action="manage-medicines.php">
<input type="hidden" name="action" value="add">

<div class="form-grid">
<div>
<label for="medicine_name">Medicine Name *</label>
<input id="medicine_name" name="medicine_name" maxlength="150" required>
</div>
<div>
<label for="quantity">Available Quantity *</label>
<input id="quantity" name="quantity" type="number" min="0" required>
</div>
<div>
<label for="unit">Unit *</label>
<input id="unit" name="unit" maxlength="30" value="tablets" required>
</div>
<div>
<label for="expiry_date">Expiry Date</label>
<input id="expiry_date" name="expiry_date" type="date">
</div>
<div>
<label for="low_stock_limit">Low Stock Warning At *</label>
<input id="low_stock_limit" name="low_stock_limit" type="number" min="0" value="10" required>
</div>
</div>

<p><button type="submit">Add Medicine</button></p>
</form>
</section>

<section class="panel">
<h2>Available Medicines</h2>

<?php if (!$medicines): ?>
<p>No medicines added yet. Use the form above to add a medicine.</p>
<?php else: ?>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Name</th>
<th>Quantity</th>
<th>Expiry Date</th>
<th>Stock Alert</th>
<th>Update Stock</th>
<th>Delete</th>
</tr>
</thead>
<tbody>
<?php foreach ($medicines as $medicine): ?>
<tr>
<td><?= htmlspecialchars($medicine["medicine_name"], ENT_QUOTES, "UTF-8") ?></td>
<td>
<?= (int)$medicine["quantity"] ?>
<?= htmlspecialchars($medicine["unit"], ENT_QUOTES, "UTF-8") ?>
</td>
<td>
<?= $medicine["expiry_date"]
    ? htmlspecialchars($medicine["expiry_date"], ENT_QUOTES, "UTF-8")
    : "Not entered" ?>
</td>
<td>
<?php if ((int)$medicine["quantity"] <= (int)$medicine["low_stock_limit"]): ?>
<span class="low">Low stock</span>
<?php else: ?>
<span class="ok">In stock</span>
<?php endif; ?>
</td>
<td>
<form class="inline-form" method="post" action="manage-medicines.php">
<input type="hidden" name="action" value="update">
<input type="hidden" name="medicine_id" value="<?= (int)$medicine["medicine_id"] ?>">
<input type="number" name="quantity" min="0" value="<?= (int)$medicine["quantity"] ?>" required aria-label="New stock quantity">
<button type="submit">Update</button>
</form>
</td>
<td>
<form method="post" action="manage-medicines.php"
onsubmit="return confirm('Delete this medicine record?');">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="medicine_id" value="<?= (int)$medicine["medicine_id"] ?>">
<button type="submit" class="danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</section>
</main>
</body>
</html>
