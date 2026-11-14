

<?php
session_start();
require_once "database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $error = "Please enter both username and password.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT user_id, username, full_name, role, password_hash
                 FROM users
                 WHERE username = ?
                 LIMIT 1"
            );

            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $user &&
                password_verify($password, $user["password_hash"])
            ) {
                session_regenerate_id(true);

                $_SESSION["user_id"] = (int) $user["user_id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["role"] = $user["role"];

                header("Location: dashboard.php");
                exit;
            }

            $error = "Incorrect username or password.";
        } catch (PDOException $e) {
            $error = "Unable to log in right now. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Smart Community Health Camp</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #19324d;
            background: linear-gradient(135deg, #e6f5ff, #f4f8fc);
        }

        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 18px;
        }

        .login-panel {
            width: 100%;
            max-width: 1000px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 18px 55px rgba(16, 54, 91, 0.15);
        }

        .login-welcome {
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: #ffffff;
            background: linear-gradient(145deg, #0787dc, #0751b5);
        }

        .login-logo {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 26px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.17);
            font-size: 30px;
        }

        .login-welcome h1 {
            margin: 0 0 18px;
            color: #ffffff;
            font-size: 34px;
            line-height: 1.25;
        }

        .login-welcome p {
            color: #e8f5ff;
            line-height: 1.8;
        }

        .benefits {
            margin: 22px 0 0;
            padding: 0;
            list-style: none;
        }

        .benefits li {
            margin: 20px 0;
            color: #ffffff;
            line-height: 1.5;
        }

        .login-form-panel {
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-form-panel h2 {
            margin: 0 0 10px;
            color: #163e70;
            font-size: 30px;
        }

        .subtitle {
            margin: 0 0 28px;
            color: #687b8f;
            line-height: 1.6;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .login-form label {
            margin-top: 10px;
            font-weight: 600;
            color: #263e56;
        }

        .login-form input {
            width: 100%;
            min-height: 48px;
            padding: 12px 14px;
            border: 1px solid #cfdae6;
            border-radius: 9px;
            background: #fbfdff;
            font-size: 16px;
        }

        .login-form input:focus {
            outline: 3px solid #dceeff;
            border-color: #0787dc;
        }

        .login-button {
            min-height: 50px;
            margin-top: 20px;
            padding: 12px;
            border: none;
            border-radius: 9px;
            background: linear-gradient(90deg, #0787dc, #075bb8);
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .login-button:hover {
            background: #064caa;
        }

        .error-message {
            margin-bottom: 18px;
            padding: 12px;
            border: 1px solid #f3b6b6;
            border-radius: 8px;
            background: #fff0f0;
            color: #a52222;
            line-height: 1.5;
        }

        .system-name {
            margin-top: 24px;
            color: #667b8e;
            font-size: 13px;
            line-height: 1.6;
            text-align: center;
        }

        .back-home {
            display: block;
            margin-top: 18px;
            color: #075bb8;
            text-align: center;
            text-decoration: none;
        }

        .back-home:hover {
            text-decoration: underline;
        }

        @media (max-width: 700px) {
            .login-page {
                padding: 14px;
            }

            .login-panel {
                grid-template-columns: 1fr;
                border-radius: 16px;
            }

            .login-welcome {
                padding: 30px 24px;
            }

            .login-welcome h1 {
                font-size: 28px;
            }

            .login-logo {
                margin-bottom: 18px;
            }

            .benefits {
                margin-top: 10px;
            }

            .benefits li {
                margin: 12px 0;
            }

            .login-form-panel {
                padding: 30px 24px;
            }

            .login-form-panel h2 {
                font-size: 26px;
            }
        }
    </style>
</head>

<body>
<main class="login-page">
    <div class="login-panel">

        <section class="login-welcome">
            <div class="login-logo" aria-hidden="true">♥</div>

            <h1>Together for a Healthier Community</h1>

            <p>
                Welcome to the Smart Community Health Camp Monitoring System.
                Sign in to manage community health camp activities.
            </p>

            <ul class="benefits">
                <li>✓ Health camp management</li>
                <li>✓ Patient registration and tokens</li>
                <li>✓ Queue status monitoring</li>
                <li>✓ Community healthcare support</li>
            </ul>
        </section>

        <section class="login-form-panel">
            <h2>Welcome Back!</h2>
            <p class="subtitle">Log in to access your account.</p>

            <?php if ($error !== ""): ?>
                <div class="error-message" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <form class="login-form" method="post" action="login.php">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    maxlength="100"
                    autocomplete="username"
                    required
                >

                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

                <button class="login-button" type="submit">
                    Log In →
                </button>
            </form>

            <p class="system-name">
                Smart Community Health Camp Monitoring System
            </p>

            <a class="back-home" href="index.php">
                ← Back to Home
            </a>
        </section>

    </div>
</main>
</body>
</html>
