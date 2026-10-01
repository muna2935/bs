<?php
session_start();

$conn = new mysqli("localhost", "root", "", "billing_system");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username == "" || $password == "") {

        $error = "Please enter username and password.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, username, password FROM users WHERE username = ? LIMIT 1"
        );

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows == 1) {

            $user = $result->fetch_assoc();
            if (password_verify($password, $user["password"])) {

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["logged_in"] = true;

                header("Location: dashboard.php");
                exit;

            }
            elseif ($password === $user["password"]) {

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["logged_in"] = true;

                header("Location: dashboard.php");
                exit;

            } else {

                $error = "Invalid username or password.";
            }

        } else {

            $error = "Invalid username or password.";
        }

        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Billing System - Login</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family: Arial, Helvetica, sans-serif;

    height: 100vh;

    display: flex;

    justify-content: center;

    align-items: center;

  background-image:
    linear-gradient(
        rgba(18, 31, 55, 0.48),
        rgba(18, 31, 55, 0.48)
    ),
    url("picture.jpg");

background-size: cover;
background-position: center;
background-repeat: no-repeat;
             url("picture.jpg")
    background-size: cover;

    background-position: center;

    background-repeat: no-repeat;

}
.login-box {

    width: 330px;

    background: rgba(31, 22, 76, 0.95);

    border: 1px solid rgba(255,255,255,0.25);

    border-radius: 10px;

    padding: 30px;

    box-shadow:
        0 10px 35px rgba(0,0,0,0.45);

}
.logo {

    text-align: center;

    font-size: 29px;

    font-weight: 900;

    color: white;

    margin-bottom: 5px;

    letter-spacing: -1px;

}

.logo span {

    color: #ff9d00;

}


.subtitle {

    text-align: center;

    color: white;

    font-size: 9px;

    letter-spacing: 0.8px;

    margin-bottom: 25px;

}
.login-title {

    color: white;

    font-size: 17px;

    font-weight: bold;

    margin-bottom: 14px;

}

.error {

    background: #ffdddd;

    color: #b00000;

    padding: 8px;

    border-radius: 5px;

    font-size: 12px;

    margin-bottom: 12px;

    text-align: center;

}
.input-box {

    position: relative;

    margin-bottom: 13px;

}

.input-box input {

    width: 100%;

    height: 42px;

    border: none;

    outline: none;

    border-radius: 5px;

    padding: 0 40px 0 14px;

    background: #edf3ff;

    color: #222;

    font-size: 13px;

}

.input-box input:focus {

    box-shadow: 0 0 0 2px #ff9d00;

}
.input-icon {

    position: absolute;

    right: 12px;

    top: 50%;

    transform: translateY(-50%);

    font-size: 19px;

}
.remember {

    display: flex;

    align-items: center;

    gap: 7px;

    color: white;

    font-size: 12px;

    margin: 4px 0 20px 0;

}

.remember input {

    width: 13px;

    height: 13px;

    accent-color: #ff9d00;

}
.login-button {

    width: 100%;

    height: 42px;

    border: none;

    border-radius: 5px;

    background: white;

    color: #21184f;

    font-size: 14px;

    font-weight: bold;

    cursor: pointer;

    transition: 0.2s;

}

.login-button:hover {

    background: #ff9d00;

    color: white;

}
.footer {

    text-align: center;

    margin-top: 20px;

    color: white;

    font-size: 9px;

}

.footer .orange {

    color: #ff9d00;

    font-weight: bold;

}

.footer .dot {

    margin: 0 5px;

}
@media (max-width: 500px) {

    .login-box {

        width: 90%;

        max-width: 330px;

    }

}

</style>

</head>


<body>


<div class="login-box">

    <div class="logo">
        BILLING<span>SYSTEM</span>
    </div>


    <div class="subtitle">
        SECURE BILLING MANAGEMENT
    </div>

    <div class="login-title">
        Log in
    </div>

    <?php if ($error != ""): ?>

        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <form method="POST" action="login.php">

        <div class="input-box">

            <input
                type="text"
                name="username"
                placeholder="Username"
                autocomplete="username"
                required
            >

            <span class="input-icon">👤</span>

        </div>

        <div class="input-box">

            <input
                type="password"
                name="password"
                placeholder="Password"
                autocomplete="current-password"
                required
            >

            <span class="input-icon">🔒</span>

        </div>


        <!-- REMEMBER ME -->

        <label class="remember">

            <input
                type="checkbox"
                name="remember"
            >

            <span>Remember me</span>

        </label>


        <!-- LOGIN BUTTON -->

        <button
            type="submit"
            class="login-button"
        >
            Log on
        </button>


    </form>


    <!-- FOOTER -->

    <div class="footer">

        <span class="orange">Billing System</span>

        <span class="dot">•</span>

        Secure Billing Management

        <span class="dot">•</span>

        © 2026

    </div>


</div>


</body>

</html>