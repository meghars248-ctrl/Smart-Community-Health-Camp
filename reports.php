
<?php
session_start();
require_once __DIR__ . "/database.php";

// This page uses the PDO connection from database.php.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit("Database connection error. Check database.php.");
}

function getCount(PDO $pdo, string $sql): int {
    try {
        return (int) $pdo->query($sql)->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

$totalCamps = getCount($pdo, "SELECT COUNT(*) FROM health_camps");
$totalPatients = getCount($pdo, "SELECT COUNT(*) FROM patients");

$totalRegistrations = getCount(
    $pdo,
    "SELECT COUNT(*) FROM registrations"
);

$waitingPatients = getCount(
    $pdo,
    "SELECT COUNT(*) FROM registrations
     WHERE queue_status = 'Waiting'"
);

$consultations = getCount(
    $pdo,
    "SELECT COUNT(*) FROM registrations
     WHERE queue_status = 'In Consultation'"
);

$completedPatients = getCount(
    $pdo,
    "SELECT COUNT(*) FROM registrations
     WHERE queue_status = 'Completed'"
);

// Count total medicine units if the medicine table and
// a recognized quantity column exist.
$totalMedicineStock = 0;
$medicineStockAvailable = false;

try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    if (in_array("medicines", $tables, true)) {
        $columns = $pdo->query("SHOW COLUMNS FROM medicines")
                       ->fetchAll(PDO::FETCH_COLUMN);

        $quantityColumn = null;

        foreach (
            ["available_quantity", "quantity", "stock_quantity", "available_qty"]
            as $candidate
        ) {
            if (in_array($candidate, $columns, true)) {
                $quantityColumn = $candidate;
                break;
            }
        }

        if ($quantityColumn !== null) {
            // Column name comes only from the recognized database column list.
            $totalMedicineStock = getCount(
                $pdo,
                "SELECT COALESCE(SUM(`" . $quantityColumn . "`), 0)
                 FROM medicines"
            );
            $medicineStockAvailable = true;
        }
    }
} catch (PDOException $e) {
    // Keep the rest of the reports available if medicine data is unavailable.
}

// Camp-wise report.
$campReports = [];

try {
    $sql = "
        SELECT
            hc.camp_id,
            hc.camp_name,
            hc.location,
            hc.camp_date,
            hc.status,
            COUNT(r.registration_id) AS registrations,
            COALESCE(SUM(
                CASE WHEN r.queue_status = 'Waiting'
                THEN 1 ELSE 0 END
            ), 0) AS waiting,
            COALESCE(SUM(
                CASE WHEN r.queue_status = 'In Consultation'
                THEN 1 ELSE 0 END
            ), 0) AS in_consultation,
            COALESCE(SUM(
                CASE WHEN r.queue_status = 'Completed'
                THEN 1 ELSE 0 END
            ), 0) AS completed
        FROM health_camps hc
        LEFT JOIN registrations r ON hc.camp_id = r.camp_id
        GROUP BY
            hc.camp_id, hc.camp_name, hc.location,
            hc.camp_date, hc.status
        ORDER BY hc.camp_date DESC, hc.camp_id DESC
    ";

    $campReports = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reportError = "Unable to load the camp-wise report. Check your database tables.";
}

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Camp Reports</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f0f6fc;
            color: #203047;
            font-family: Arial, sans-serif;
        }

        header {
            background: #075bd8;
            color: white;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        header h2 { margin: 0; font-size: 22px; }

        nav {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        nav a:hover { text-decoration: underline; }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .intro, .panel {
            background: white;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 5px 20px rgba(20, 50, 90, .07);
        }

        h1 { color: #124c9c; margin-top: 0; }
        h2 { color: #174b85; }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .stat {
            background: white;
            padding: 22px;
            border-radius: 14px;
            border-top: 5px solid #087be0;
            box-shadow: 0 4px 16px rgba(20, 50, 90, .06);
        }

        .stat:nth-child(2) { border-top-color: #0e9384; }
        .stat:nth-child(3) { border-top-color: #e59a31; }
        .stat:nth-child(4) { border-top-color: #6556c9; }
        .stat:nth-child(5) { border-top-color: #db6b58; }
        .stat:nth-child(6) { border-top-color: #2388b5; }

        .stat p { margin: 0 0 10px; color: #52657b; }
        .number { font-size: 32px; font-weight: bold; color: #173c72; }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #e1e9f2;
            text-align: left;
        }

        th { background: #e6f2ff; color: #164a83; }
        tr:hover td { background: #f7fbff; }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: #e6f2ff;
            color: #164a83;
            font-size: 13px;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .actions a, .print-btn {
            display: inline-block;
            padding: 11px 16px;
            border: 0;
            border-radius: 7px;
            background: #087be0;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .actions a:hover, .print-btn:hover { background: #064eaa; }

        .note {
            padding: 12px;
            background: #fff5d9;
            border-radius: 8px;
            color: #704f00;
        }

        footer {
            text-align: center;
            padding: 22px;
            color: #61738a;
        }

        @media print {
            header, .actions, .print-btn, footer { display: none; }
            body { background: white; }
            .container { margin: 0; max-width: none; }
            .intro, .panel, .stat { box-shadow: none; }
        }
    </style>
</head>
<body>

<header>
    <h2>♥ Smart Community Health Camp</h2>
    <nav>
        <a href="index.php">Home</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="camp-details.php">Camp Details</a>
        <a href="manage-queue.php">Patient Queue</a>
        <a href="manage-medicines.php">Medicine Inventory</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="container">
    <section class="intro">
        <h1>Health Camp Reports Dashboard</h1>
        <p>Monitor health camps, patient registrations, consultation progress,
           and medicine inventory in one place.</p>
        <p>Report generated: <?= h(date("d M Y, h:i A")) ?></p>
        <button class="print-btn" onclick="window.print()">
            Print / Save as PDF
        </button>
    </section>

    <section class="stats">
        <div class="stat">
            <p>Total Health Camps</p>
            <div class="number"><?= $totalCamps ?></div>
        </div>

        <div class="stat">
            <p>Registered Patients</p>
            <div class="number"><?= $totalPatients ?></div>
        </div>

        <div class="stat">
            <p>Total Registrations</p>
            <div class="number"><?= $totalRegistrations ?></div>
        </div>

        <div class="stat">
            <p>Patients Waiting</p>
            <div class="number"><?= $waitingPatients ?></div>
        </div>

        <div class="stat">
            <p>In Consultation</p>
            <div class="number"><?= $consultations ?></div>
        </div>

        <div class="stat">
            <p>Completed Consultations</p>
            <div class="number"><?= $completedPatients ?></div>
        </div>

        <div class="stat">
            <p>Medicine Units in Stock</p>
            <div class="number">
                <?= $medicineStockAvailable ? $totalMedicineStock : "N/A" ?>
            </div>
            <?php if (!$medicineStockAvailable): ?>
                <small>Check the medicines table and quantity column.</small>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <h2>Camp-wise Registration Report</h2>
        <p>See the number of patients and their consultation progress for each camp.</p>

        <?php if (isset($reportError)): ?>
            <p class="note"><?= h($reportError) ?></p>
        <?php elseif (count($campReports) === 0): ?>
            <p>No health camps found. Add a camp first using Manage Health Camps.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Camp Name</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Registrations</th>
                            <th>Waiting</th>
                            <th>In Consultation</th>
                            <th>Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($campReports as $camp): ?>
                        <tr>
                            <td><?= h($camp["camp_name"]) ?></td>
                            <td><?= h($camp["location"]) ?></td>
                            <td><?= h($camp["camp_date"]) ?></td>
                            <td><span class="status"><?= h($camp["status"]) ?></span></td>
                            <td><?= (int) $camp["registrations"] ?></td>
                            <td><?= (int) $camp["waiting"] ?></td>
                            <td><?= (int) $camp["in_consultation"] ?></td>
                            <td><?= (int) $camp["completed"] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Quick Actions</h2>
        <div class="actions">
            <a href="manage-camps.php">Manage Health Camps</a>
            <a href="manage-queue.php">Manage Patient Queue</a>
            <a href="volunteer-list.php">Volunteer List</a>
            <a href="manage-medicines.php">Medicine Inventory</a>
        </div>
    </section>
</main>

<footer>
    <p>Smart Community Health Camp Monitoring System</p>
</footer>

</body>
</html>
