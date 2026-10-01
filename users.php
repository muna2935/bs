<?php

session_start();
if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {

    header("Location: login.php");
    exit;
}
if (!isset($_SESSION["username"]) || $_SESSION["username"] !== "admin") {

    header("Location: dashboard.php");
    exit;
}
$conn = new mysqli(
    "localhost",
    "root",
    "",
    "billing_system"
);

if ($conn->connect_error) {

    die("Database connection failed: " . $conn->connect_error);
}


$message = "";
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_user"])) {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Please enter username and password.";

    } elseif (strlen($username) < 3) {

        $error = "Username must contain at least 3 characters.";

    } elseif (strlen($password) < 4) {

        $error = "Password must contain at least 4 characters.";

    } else {

        /* Check whether username already exists */

        $check = $conn->prepare(
            "SELECT id FROM users WHERE username = ? LIMIT 1"
        );

        $check->bind_param("s", $username);

        $check->execute();

        $check->store_result();

        if ($check->num_rows > 0) {

            $error = "Username already exists.";

        } else {
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare(
                "INSERT INTO users (username, password)
                 VALUES (?, ?)"
            );

            $stmt->bind_param(
                "ss",
                $username,
                $hashedPassword
            );

            if ($stmt->execute()) {

                $message = "User added successfully.";

            } else {

                $error = "Unable to add user.";
            }

            $stmt->close();
        }

        $check->close();
    }
}
if (isset($_GET["delete"])) {

    $delete_id = intval($_GET["delete"]);

    if ($delete_id > 0) {

        /*
         * Find the username of the user being deleted.
         */

        $find = $conn->prepare(
            "SELECT username FROM users WHERE id = ? LIMIT 1"
        );

        $find->bind_param("i", $delete_id);

        $find->execute();

        $result = $find->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            $delete_username = $user["username"];
            if ($delete_username === $_SESSION["username"]) {

                $error = "You cannot delete your own admin account.";

            } else {
                $countResult = $conn->query(
                    "SELECT COUNT(*) AS total FROM users"
                );

                $countRow = $countResult->fetch_assoc();

                $totalUsers = $countRow["total"];
                if ($totalUsers <= 1) {

                    $error = "You cannot delete the last user.";

                } else {

                    $delete = $conn->prepare(
                        "DELETE FROM users WHERE id = ?"
                    );

                    $delete->bind_param(
                        "i",
                        $delete_id
                    );

                    if ($delete->execute()) {

                        $message = "User deleted successfully.";

                    } else {

                        $error = "Unable to delete user.";
                    }

                    $delete->close();
                }
            }

        } else {

            $error = "User not found.";
        }

        $find->close();
    }
}
$result = $conn->query(
    "SELECT id, username
     FROM users
     ORDER BY id ASC"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>User Management - Billing System</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {

            font-family: Arial, Helvetica, sans-serif;

            background: #f1f5f9;

            color: #1e293b;

        }
        .header {

            background: #2d4359;

            color: white;

            padding: 25px 30px;

        }

        .header h1 {

            font-size: 32px;

            margin-bottom: 15px;

        }

        .header p {

            font-size: 16px;

        }
        .container {

            padding: 30px;

        }
        .back-btn {

            display: inline-block;

            background: #555;

            color: white;

            text-decoration: none;

            padding: 11px 18px;

            border-radius: 5px;

            margin-bottom: 25px;

            font-size: 14px;

        }

        .back-btn:hover {

            background: #333;

        }
        .success {

            background: #dcfce7;

            color: #166534;

            border: 1px solid #bbf7d0;

            padding: 12px 15px;

            border-radius: 6px;

            margin-bottom: 20px;

        }


        .error {

            background: #fee2e2;

            color: #991b1b;

            border: 1px solid #fecaca;

            padding: 12px 15px;

            border-radius: 6px;

            margin-bottom: 20px;

        }
        .card {

            background: white;

            border-radius: 10px;

            padding: 25px;

            box-shadow:
                0 3px 12px rgba(0, 0, 0, 0.10);

            margin-bottom: 30px;

        }


        .card h2 {

            font-size: 24px;

            margin-bottom: 22px;

            color: #111827;

        }
        .form-group {

            margin-bottom: 18px;

            max-width: 500px;

        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            font-size: 15px;

            color: #111827;

        }


        .form-group input {

            width: 100%;

            height: 40px;

            padding: 8px 12px;

            border: 1px solid #cbd5e1;

            border-radius: 5px;

            background: #edf3ff;

            font-size: 14px;

            outline: none;

        }


        .form-group input:focus {

            border-color: #3498db;

            box-shadow:
                0 0 0 2px rgba(52, 152, 219, 0.15);

        }
        .add-btn {

            background: #3498db;

            color: white;

            border: none;

            padding: 11px 20px;

            border-radius: 5px;

            font-size: 14px;

            cursor: pointer;

        }


        .add-btn:hover {

            background: #2587c5;

        }
        .user-list-title {

            font-size: 24px;

            margin-bottom: 20px;

            color: #111827;

        }


        .table-container {

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            background: white;

        }


        th {

            background: #2d4359;

            color: white;

            text-align: left;

            padding: 14px;

            font-size: 14px;

        }


        td {

            padding: 13px 14px;

            border-bottom: 1px solid #e5e7eb;

            font-size: 14px;

        }


        tr:hover td {

            background: #f8fafc;

        }
        .delete-btn {

            display: inline-block;

            background: #ef4444;

            color: white;

            text-decoration: none;

            padding: 8px 13px;

            border-radius: 5px;

            font-size: 13px;

        }


        .delete-btn:hover {

            background: #dc2626;

        }
        @media (max-width: 700px) {

            .header {

                padding: 20px;

            }

            .header h1 {

                font-size: 26px;

            }

            .container {

                padding: 20px;

            }

            .card {

                padding: 20px;

            }

        }

    </style>

</head>


<body>
<div class="header">

    <h1>Billing System</h1>

    <p>User Management</p>

</div>


<div class="container">


    <!-- BACK BUTTON -->

    <a href="dashboard.php" class="back-btn">
        ← Back to Dashboard
    </a>


    <!-- SUCCESS MESSAGE -->

    <?php if ($message !== ""): ?>

        <div class="success">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <!-- ERROR MESSAGE -->

    <?php if ($error !== ""): ?>

        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>
    <div class="card">

        <h2>Add New User</h2>


        <form method="POST" action="users.php">


            <div class="form-group">

                <label for="username">
                    Username:
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter username"
                    required
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password:
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter password"
                    required
                >

            </div>


            <button
                type="submit"
                name="add_user"
                class="add-btn"
            >
                Add User
            </button>


        </form>

    </div>
    <h2 class="user-list-title">
        User List
    </h2>


    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>ID</th>

                    <th>Username</th>

                    <th>Action</th>

                </tr>

            </thead>


            <tbody>

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?php echo (int)$row["id"]; ?>
                        </td>


                        <td>
                            <?php echo htmlspecialchars($row["username"]); ?>
                        </td>


                        <td>

                            <?php if ($row["username"] !== $_SESSION["username"]): ?>

                                <a
                                    href="users.php?delete=<?php echo (int)$row["id"]; ?>"
                                    class="delete-btn"
                                    onclick="return confirm('Are you sure you want to delete this user?');"
                                >
                                    Delete
                                </a>

                            <?php else: ?>

                                <span style="color:#64748b;">
                                    Current Admin
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td colspan="3">
                        No users found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>


</div>


</body>

</html>

<?php

$conn->close();

?>