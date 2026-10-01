<?php
session_start();

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit;
}
?>
<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "billing_system";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
$result = $conn->query("SELECT COUNT(*) AS total FROM customers");
$totalCustomers = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT COUNT(*) AS total FROM products");
$totalProducts = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT COUNT(*) AS total FROM bills");
$totalBills = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT COUNT(*) AS total FROM payments");
$totalPayments = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM payments");
$totalRevenue = $result->fetch_assoc()['total'];
$recentBills = $conn->query("
    SELECT *
    FROM bills
    ORDER BY 1 DESC
    LIMIT 5
");
$recentPayments = $conn->query("
    SELECT *
    FROM payments
    ORDER BY 1 DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Billing System - Dashboard</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #f4f6f9;
            color: #333;
        }

        .sidebar {
            width: 240px;
            height: 100vh;
            background: #1e293b;
            position: fixed;
            left: 0;
            top: 0;
            padding-top: 20px;
        }

        .logo {
            color: white;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 35px;
        }

        .logo span {
            color: #38bdf8;
        }

        .menu {
            list-style: none;
        }

        .menu li {
            margin-bottom: 5px;
        }

        .menu li a {
            display: block;
            color: #cbd5e1;
            text-decoration: none;
            padding: 14px 25px;
            font-size: 15px;
            transition: 0.3s;
        }

        .menu li a:hover,
        .menu li a.active {
            background: #0ea5e9;
            color: white;
        }

        .menu li a i {
            margin-right: 10px;
        }

        .main {
            margin-left: 240px;
            min-height: 100vh;
        }
        .topbar {
            height: 70px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .topbar h2 {
            font-size: 22px;
            color: #1e293b;
        }

        .admin {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-circle {
            width: 40px;
            height: 40px;
            background: #0ea5e9;
            color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
        }
        .content {
            padding: 30px;
        }

        .welcome {
            margin-bottom: 25px;
        }

        .welcome h1 {
            font-size: 26px;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .welcome p {
            color: #64748b;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-info h3 {
            font-size: 28px;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .card-info p {
            color: #64748b;
            font-size: 14px;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 23px;
        }

        .customers-icon {
            background: #dbeafe;
        }

        .products-icon {
            background: #dcfce7;
        }

        .bills-icon {
            background: #fef3c7;
        }

        .payments-icon {
            background: #fce7f3;
        }
        .revenue {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }

        .revenue h3 {
            color: #64748b;
            font-size: 15px;
            margin-bottom: 8px;
        }

        .revenue h1 {
            color: #16a34a;
            font-size: 30px;
        }
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            color: #1e293b;
        }

        .view-all {
            text-decoration: none;
            color: #0ea5e9;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th,
        table td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        table th {
            background: #f8fafc;
            color: #475569;
            font-size: 14px;
        }

        table td {
            font-size: 14px;
            color: #475569;
        }

        .status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            background: #dcfce7;
            color: #166534;
        }
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 25px;
        }
        @media screen and (max-width: 1000px) {

            .cards {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media screen and (max-width: 700px) {

            .sidebar {
                width: 70px;
            }

            .logo {
                font-size: 0;
            }

            .logo span {
                font-size: 20px;
            }

            .menu li a {
                text-align: center;
                padding: 15px 5px;
            }

            .menu li a span {
                display: none;
            }

            .main {
                margin-left: 70px;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .two-columns {
                grid-template-columns: 1fr;
            }

            .content {
                padding: 15px;
            }

        }

    </style>
</head>

<body>
<div class="sidebar">

    <div class="logo">
        Billing<span>System</span>
    </div>

    <ul class="menu">

        <li>
            <a href="dashboard.php" class="active">
                📊 <span>Dashboard</span>
            </a>
        </li>

        <li>
            <a href="customers.php">
                👥 <span>Customers</span>
            </a>
        </li>

        <li>
            <a href="products.php">
                📦 <span>Products</span>
            </a>
        </li>

        <li>
            <a href="create_bill.php">
                🧾 <span>Create Bill</span>
            </a>
        </li>

        <li>
            <a href="bills.php">
                📄 <span>Bills</span>
            </a>
        </li>

        <li>
            <a href="payments.php">
                💰 <span>Payments</span>
            </a>
        </li>

        <li>
            <a href="users.php">
                👤 <span>Users</span>
            </a>
        </li>
        <li>
        <a href="reports.php">
    📊 Reports
</a>
</li>
        <li>
            <a href="logout.php">
                🚪 <span>Logout</span>
            </a>
        </li>

    </ul>

</div>
<div class="main">
    <div class="topbar">

        <h2>Dashboard</h2>

        <div class="admin">

            <div class="admin-circle">
                A
            </div>

            <span>Admin</span>

        </div>

    </div>

    <div class="content">


        <!-- WELCOME -->

        <div class="welcome">

            <h1>Welcome, Admin 👋</h1>

            <p>
                Here's what's happening with your billing system today.
            </p>

        </div>
        <div class="cards">
            <div class="card">

                <div class="card-info">

                    <h3>
                        <?php echo $totalCustomers; ?>
                    </h3>

                    <p>Total Customers</p>

                </div>

                <div class="card-icon customers-icon">
                    👥
                </div>

            </div>

            <div class="card">

                <div class="card-info">

                    <h3>
                        <?php echo $totalProducts; ?>
                    </h3>

                    <p>Total Products</p>

                </div>

                <div class="card-icon products-icon">
                    📦
                </div>

            </div>

            <div class="card">

                <div class="card-info">

                    <h3>
                        <?php echo $totalBills; ?>
                    </h3>

                    <p>Total Bills</p>

                </div>

                <div class="card-icon bills-icon">
                    🧾
                </div>

            </div>

            <div class="card">

                <div class="card-info">

                    <h3>
                        <?php echo $totalPayments; ?>
                    </h3>

                    <p>Total Payments</p>

                </div>

                <div class="card-icon payments-icon">
                    💰
                </div>

            </div>

        </div>
        <div class="revenue">

            <h3>Total Revenue</h3>

            <h1>
                Rs. <?php echo number_format($totalRevenue, 2); ?>
            </h1>

        </div>
        <div class="table-container">

            <div class="table-header">

                <h3>Recent Bills</h3>

                <a href="bills.php" class="view-all">
                    View All
                </a>

            </div>


            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Bill Details</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>

                </thead>

                <tbody>

                <?php

                if ($recentBills && $recentBills->num_rows > 0) {

                    while ($row = $recentBills->fetch_assoc()) {

                        echo "<tr>";

                        echo "<td>" .
                            htmlspecialchars($row['id'] ?? '') .
                            "</td>";

                        echo "<td>Bill #" .
                            htmlspecialchars($row['id'] ?? '') .
                            "</td>";

                        echo "<td>Rs. " .
                            number_format(
                                (float)($row['total_amount'] ?? $row['amount'] ?? 0),
                                2
                            ) .
                            "</td>";

                        echo "<td>
                                <span class='status'>Completed</span>
                              </td>";

                        echo "</tr>";
                    }

                } else {

                    echo "<tr>
                            <td colspan='4'>
                                No bills found.
                            </td>
                          </tr>";
                }

                ?>

                </tbody>

            </table>

        </div>
        <div class="table-container" style="margin-top:25px;">

            <div class="table-header">

                <h3>Recent Payments</h3>

                <a href="payments.php" class="view-all">
                    View All
                </a>

            </div>


            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Payment Amount</th>
                        <th>Payment Date</th>
                    </tr>

                </thead>

                <tbody>

                <?php

                if ($recentPayments && $recentPayments->num_rows > 0) {

                    while ($row = $recentPayments->fetch_assoc()) {

                        echo "<tr>";

                        echo "<td>" .
                            htmlspecialchars($row['id'] ?? '') .
                            "</td>";

                        echo "<td>Rs. " .
                            number_format(
                                (float)($row['amount'] ?? 0),
                                2
                            ) .
                            "</td>";

                        echo "<td>" .
                            htmlspecialchars(
                                $row['payment_date'] ??
                                $row['created_at'] ??
                                '-'
                            ) .
                            "</td>";

                        echo "</tr>";
                    }

                } else {

                    echo "<tr>
                            <td colspan='3'>
                                No payments found.
                            </td>
                          </tr>";
                }

                ?>

                </tbody>

            </table>

        </div>


    </div>

</div>

</body>
</html>

<?php
$conn->close();
?>