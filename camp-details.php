
<?php
require_once "database.php";

// Read search and filter values.
$search = trim($_GET["search"] ?? "");
$status = trim($_GET["status"] ?? "");

$allowedStatuses = ["Planned", "Active", "Completed"];

if (!in_array($status, $allowedStatuses, true)) {
    $status = "";
}

// Fetch camps using the search and status filters.
$sql = "SELECT *
        FROM health_camps
        WHERE 1 = 1";

$params = [];

if ($search !== "") {
    $sql .= " AND (camp_name LIKE ? OR location LIKE ?)";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
}

if ($status !== "") {
    $sql .= " AND status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY camp_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$camps = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Health Camps | Smart Community Health Camp</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .camp-page {
            background: #f0f7ff;
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .camp-hero {
            padding: 42px 5%;
            background: linear-gradient(120deg, #dceeff, #e1f8f4);
            border-radius: 0 0 25px 25px;
        }

        .camp-hero h1 {
            color: #075bb5;
            font-size: clamp(28px, 4vw, 40px);
            margin: 0 0 10px;
        }

        .camp-hero p {
            color: #52657c;
            margin: 0;
        }

        .camp-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 22px;
        }

        .camp-search-panel {
            background: #fff;
            padding: 25px;
            border: 1px solid #dce8f5;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(24, 70, 120, 0.07);
            margin-bottom: 32px;
        }

        .camp-search-panel h2 {
            color: #075bb5;
            margin-top: 0;
        }

        .camp-search-form {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) auto;
            align-items: end;
            gap: 16px;
        }

        .camp-search-form label {
            display: block;
            font-weight: 600;
            margin-bottom: 7px;
            color: #183153;
        }

        .camp-search-form input,
        .camp-search-form select {
            width: 100%;
            min-height: 46px;
            padding: 11px 13px;
            border: 1px solid #cbdced;
            border-radius: 9px;
            font: inherit;
            background: #fff;
            color: #183153;
        }

        .camp-search-form button,
        .camp-clear-button {
            display: inline-block;
            padding: 12px 17px;
            border-radius: 9px;
            font: inherit;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }

        .camp-search-form button {
            background: #0878e8;
            color: #fff;
            border: none;
        }

        .camp-search-form button:hover {
            background: #075bb5;
        }

        .camp-filter-actions {
            margin-top: 16px;
        }

        .camp-clear-button {
            color: #075bb5;
            background: #e8f3ff;
        }

        .camp-section-title {
            color: #183153;
            margin-bottom: 20px;
        }

        .camp-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
        }

        .camp-item {
            display: flex;
            flex-direction: column;
            background: #fff;
            border: 1px solid #dce8f5;
            border-top: 5px solid #0878e8;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(24, 70, 120, 0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            min-width: 0;
        }

        .camp-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(24, 70, 120, 0.14);
        }

        .camp-item h3 {
            color: #075bb5;
            font-size: 21px;
            margin: 0 0 16px;
            overflow-wrap: anywhere;
        }

        .camp-info {
            margin: 8px 0;
            color: #52657c;
            overflow-wrap: anywhere;
        }

        .camp-info strong {
            color: #183153;
        }

        .camp-description {
            margin-top: 12px;
            color: #64748b;
        }

        .camp-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            background: #e5efff;
            color: #075bb5;
            font-size: 13px;
            font-weight: 700;
        }

        .camp-status.active {
            background: #d9f8e9;
            color: #087443;
        }

        .camp-status.completed {
            background: #e8eaf0;
            color: #525b6b;
        }

        .camp-register-btn {
            display: inline-block;
            margin-top: auto;
            padding: 12px 16px;
            border-radius: 9px;
            background: #0878e8;
            color: #fff !important;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
        }

        .camp-register-btn:hover {
            background: #075bb5;
        }

        .camp-no-results {
            padding: 25px;
            background: #fff;
            border: 1px solid #dce8f5;
            border-radius: 15px;
            color: #52657c;
        }

        @media (max-width: 950px) {
            .camp-list {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .camp-search-form {
                grid-template-columns: 1fr 1fr;
            }

            .camp-search-form button {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 600px) {
            .camp-hero {
                padding: 30px 20px;
            }

            .camp-container {
                padding: 0 14px;
                margin: 20px auto;
            }

            .camp-search-panel {
                padding: 18px;
            }

            .camp-search-form,
            .camp-list {
                grid-template-columns: 1fr;
            }

            .camp-search-form button {
                grid-column: auto;
                width: 100%;
            }

            .camp-item {
                padding: 20px;
            }
        }
    </style>
</head>

<body class="camp-page">

<header>
    <h2>♥ Smart Community Health Camp</h2>

    <nav>
        <a href="index.php">Home</a>
        <a href="camp-details.php">Camp Details</a>
        <a href="register.php">Register</a>
        <a href="login.php">Login</a>
    </nav>
</header>

<section class="camp-hero">
    <h1>Find Your Health Camp</h1>
    <p>
        Explore community health camps, check their details,
        and register for an available camp.
    </p>
</section>

<main class="camp-container">

    <section class="camp-search-panel">
        <h2>Find a Health Camp</h2>

        <form class="camp-search-form" method="get" action="camp-details.php">

            <div>
                <label for="search">Search by camp name or location</label>
                <input
                    type="search"
                    id="search"
                    name="search"
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Enter camp name or location"
                >
            </div>

            <div>
                <label for="status">Filter by Status</label>
                <select id="status" name="status">
                    <option value="">All Statuses</option>

                    <?php foreach ($allowedStatuses as $option): ?>
                        <option
                            value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $status === $option ? "selected" : "" ?>
                        >
                            <?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit">Search Camps</button>
        </form>

        <div class="camp-filter-actions">
            <a class="camp-clear-button" href="camp-details.php">
                Clear Filters
            </a>
        </div>
    </section>

    <section>
        <h2 class="camp-section-title">Available Health Camps</h2>

        <?php if (count($camps) > 0): ?>

            <div class="camp-list">

                <?php foreach ($camps as $camp): ?>

                    <?php
                    $campStatus = $camp["status"] ?? "Planned";
                    $statusClass = strtolower($campStatus);

                    $campId = (int) $camp["camp_id"];
                    $campDate = $camp["camp_date"] ?? "";
                    $today = date("Y-m-d");

                    $canRegister =
                        in_array($campStatus, ["Planned", "Active"], true)
                        && $campDate >= $today;
                    ?>

                    <article class="camp-item">

                        <h3>
                            <?= htmlspecialchars($camp["camp_name"] ?? "", ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <p class="camp-info">
                            <strong>📍 Location:</strong>
                            <?= htmlspecialchars($camp["location"] ?? "", ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <p class="camp-info">
                            <strong>📅 Date:</strong>
                            <?= htmlspecialchars($campDate, ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <p class="camp-info">
                            <strong>🕒 Time:</strong>
                            <?= htmlspecialchars(substr($camp["start_time"] ?? "", 0, 5), ENT_QUOTES, 'UTF-8') ?>
                            –
                            <?= htmlspecialchars(substr($camp["end_time"] ?? "", 0, 5), ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <p class="camp-info">
                            <strong>Organizer:</strong>
                            <?= htmlspecialchars($camp["organizer"] ?? "", ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <p class="camp-info">
                            <strong>Status:</strong>
                            <span class="camp-status <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($campStatus, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </p>

                        <?php if (!empty($camp["description"])): ?>
                            <p class="camp-description">
                                <?= htmlspecialchars($camp["description"], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($canRegister): ?>
                            <a
                                class="camp-register-btn"
                                href="register.php?camp_id=<?= $campId ?>"
                            >
                                Register for This Camp →
                            </a>
                        <?php else: ?>
                            <span class="camp-info">
                                Registration is unavailable for this camp.
                            </span>
                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="camp-no-results">
                <h3>No health camps found</h3>
                <p>Try another camp name, location, or status filter.</p>
                <a class="camp-clear-button" href="camp-details.php">
                    Show All Camps
                </a>
            </div>

        <?php endif; ?>

    </section>

</main>

<footer>
    <p>Smart Community Health Camp Monitoring System</p>
</footer>

</body>
</html>
