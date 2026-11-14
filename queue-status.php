
<?php
require_once "database.php";

$result = null;
$error = "";

$campId = filter_input(INPUT_GET, "camp_id", FILTER_VALIDATE_INT);
$token = filter_input(INPUT_GET, "token", FILTER_VALIDATE_INT);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $campId = filter_input(INPUT_POST, "camp_id", FILTER_VALIDATE_INT);
    $token = filter_input(INPUT_POST, "token", FILTER_VALIDATE_INT);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!$campId || !$token || $token < 1) {
        $error = "Please select a health camp and enter a valid token number.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT
                    r.token_number,
                    r.registration_type,
                    r.queue_status,
                    c.camp_name,
                    c.location,
                    c.camp_date,
                    c.start_time,
                    c.end_time
                 FROM registrations r
                 INNER JOIN health_camps c ON r.camp_id = c.camp_id
                 WHERE r.camp_id = ? AND r.token_number = ?
                 LIMIT 1"
            );

            $stmt->execute([$campId, $token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                $error = "No registration was found for that camp and token number.";
            }
        } catch (PDOException $e) {
            $error = "Unable to check queue status. Please try again.";
        }
    }
}

$stmt = $pdo->query(
    "SELECT camp_id, camp_name, camp_date
     FROM health_camps
     ORDER BY camp_date DESC"
);
$camps = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Queue Status</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            margin: 0;
            background: #f0f7ff;
            color: #24344d;
            font-family: Arial, sans-serif;
        }
        header {
            background: linear-gradient(120deg, #075bb5, #0878e8);
            color: white;
            padding: 20px 5%;
        }
        header h2 { margin-top: 0; }
        nav { display: flex; flex-wrap: wrap; gap: 20px; }
        nav a { color: white; text-decoration: none; font-weight: bold; }
        main { max-width: 850px; margin: 35px auto; padding: 0 18px; }
        .card {
            background: white;
            padding: 28px;
            margin-bottom: 24px;
            border-radius: 14px;
            border-top: 5px solid #0878e8;
            box-shadow: 0 4px 15px #00000010;
        }
        label { display: block; margin: 16px 0 8px; font-weight: bold; }
        select, input {
            box-sizing: border-box;
            width: 100%;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 16px;
        }
        button {
            width: 100%;
            margin-top: 22px;
            padding: 14px;
            background: #0878e8;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .notice { padding: 14px; border-radius: 8px; margin-bottom: 18px; }
        .error { background: #fee2e2; color: #991b1b; }
        .status { font-size: 20px; font-weight: bold; color: #075bb5; }
        .completed { color: #15803d; }
        .waiting { color: #b45309; }
        .consultation { color: #1d4ed8; }
        .details { line-height: 2; }
    </style>
</head>
<body>
<header>
    <h2>Smart Community Health Camp</h2>
    <nav>
        <a href="index.php">Home</a>
        <a href="camp-details.php">Camp Details</a>
        <a href="register.php">Patient Registration</a>
        <a href="queue-status.php">Queue Status</a>
        <a href="login.php">Login</a>
    </nav>
</header>

<main>
    <section class="card">
        <h1>Check Your Queue Status</h1>
        <p>Enter your health camp and token number to check your status.</p>

        <?php if ($error !== ""): ?>
            <div class="notice error">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form method="post" action="queue-status.php">
            <label for="camp_id">Select Health Camp</label>
            <select id="camp_id" name="camp_id" required>
                <option value="">Choose a health camp</option>
                <?php foreach ($camps as $camp): ?>
                    <option
                        value="<?= (int)$camp["camp_id"] ?>"
                        <?= $campId === (int)$camp["camp_id"] ? "selected" : "" ?>
                    >
                        <?= htmlspecialchars($camp["camp_name"], ENT_QUOTES, "UTF-8") ?>
                        — <?= htmlspecialchars($camp["camp_date"], ENT_QUOTES, "UTF-8") ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="token">Token Number</label>
            <input
                type="number"
                id="token"
                name="token"
                min="1"
                required
                value="<?= $token ? (int)$token : "" ?>"
                placeholder="Enter your token number"
            >

            <button type="submit">Check Queue Status</button>
        </form>
    </section>

    <?php if ($result): ?>
        <?php
            $statusClass = "waiting";
            if ($result["queue_status"] === "Completed") {
                $statusClass = "completed";
            } elseif ($result["queue_status"] === "In Consultation") {
                $statusClass = "consultation";
            }
        ?>

        <section class="card">
            <h2>Your Token: #<?= (int)$result["token_number"] ?></h2>
            <div class="details">
                <strong>Camp:</strong>
                <?= htmlspecialchars($result["camp_name"], ENT_QUOTES, "UTF-8") ?><br>

                <strong>Location:</strong>
                <?= htmlspecialchars($result["location"], ENT_QUOTES, "UTF-8") ?><br>

                <strong>Date:</strong>
                <?= htmlspecialchars($result["camp_date"], ENT_QUOTES, "UTF-8") ?><br>

                <strong>Time:</strong>
                <?= htmlspecialchars(substr($result["start_time"], 0, 5)) ?>
                – <?= htmlspecialchars(substr($result["end_time"], 0, 5)) ?><br>

                <strong>Registration:</strong>
                <?= htmlspecialchars($result["registration_type"], ENT_QUOTES, "UTF-8") ?><br>

                <strong>Current Status:</strong>
                <span class="status <?= $statusClass ?>">
                    <?= htmlspecialchars($result["queue_status"], ENT_QUOTES, "UTF-8") ?>
                </span>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
