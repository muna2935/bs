<?php
session_start();

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit;
}
?>
<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "billing_system";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

$success = isset($_GET["success"]);
$bill_id = isset($_GET["bill_id"]) ? intval($_GET["bill_id"]) : 0;
$sql = "
    SELECT 
        b.id,
        b.customer_id,
        b.date,
        b.description,
        b.amount,
        b.discount,
        b.vat,
        b.status,
        c.name AS customer_name
    FROM bills b
    LEFT JOIN customers c
        ON b.customer_id = c.customer_id
    ORDER BY b.id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Error loading bills: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Billing System - Bills</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6fa;
    color: #17243b;
}
.btn-delete {
    background: #dc2626;
    color: white;
    padding: 7px 12px;
    font-size: 13px;
    text-decoration: none;
    border-radius: 6px;
    margin-left: 5px;
}

.btn-delete:hover {
    background: #b91c1c;
}
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 240px;
    height: 100vh;
    background: #1e293b;
    color: white;
}

.logo {
    padding: 22px 25px;
    font-size: 24px;
    font-weight: bold;
}

.logo span {
    color: #ff9d00;
}

.menu {
    margin-top: 30px;
}

.menu a {
    display: block;
    padding: 15px 30px;
    color: white;
    text-decoration: none;
    font-size: 16px;
}

.menu a:hover {
    background: #334155;
}

.menu a.active {
    background: #ff9d00;
}
.main {
    margin-left: 240px;
    min-height: 100vh;
}

.topbar {
    height: 70px;
    background: white;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    padding: 0 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.admin {
    display: flex;
    align-items: center;
    gap: 10px;
}

.admin-circle {
    width: 40px;
    height: 40px;
    background: #291d58;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}
.content {
    padding: 30px;
}

.content h1 {
    margin: 0 0 5px;
    font-size: 32px;
}

.subtitle {
    color: #64748b;
    margin-bottom: 25px;
}
.card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.06);
}

.success {
    background: #dcfce7;
    color: #166534;
    padding: 14px 18px;
    border-radius: 7px;
    margin-bottom: 20px;
    font-weight: bold;
}

.top-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.top-actions h2 {
    margin: 0;
    color: #291d58;
}

.btn {
    display: inline-block;
    padding: 11px 18px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
    border: none;
    cursor: pointer;
}

.btn-create {
    background: #ff9d00;
    color: white;
}

.btn-create:hover {
    background: #e68c00;
}

.btn-view {
    background: #291d58;
    color: white;
    padding: 7px 12px;
    font-size: 13px;
}
.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #291d58;
    color: white;
    padding: 13px;
    text-align: left;
}

td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
}

tr:hover {
    background: #f8fafc;
}

.status {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.unpaid {
    background: #fee2e2;
    color: #991b1b;
}

.paid {
    background: #dcfce7;
    color: #166534;
}

.pending {
    background: #fef3c7;
    color: #92400e;
}

</style>

</head>

<body>

<div class="sidebar">

    <div class="logo">
        BILLING<span>SYSTEM</span>
    </div>

    <div class="menu">

        <a href="dashboard.php">
            📊 Dashboard
        </a>

        <a href="customers.php">
            👥 Customers
        </a>

        <a href="products.php">
            📦 Products
        </a>

        <a href="create_bill.php">
            🧾 Create Bill
        </a>

        <a href="bills.php" class="active">
            📄 Bills
        </a>

        <a href="payments.php">
            💰 Payments
        </a>

        <a href="users.php">
            👤 Users
        </a>

        <a href="logout.php">
            🚪 Logout
        </a>

    </div>

</div>
<div class="main">

    <div class="topbar">

        <div class="admin">

            <div class="admin-circle">
                A
            </div>

            <span>Admin</span>

        </div>

    </div>


    <div class="content">

        <h1>Bill Management</h1>

        <div class="subtitle">
            View and manage all generated bills.
        </div>


        <?php if ($success): ?>

            <div class="success">

                ✅ Bill #<?= $bill_id ?> was created successfully!

            </div>

        <?php endif; ?>


        <div class="card">

            <div class="top-actions">

                <h2>📄 Bill List</h2>

                <a
                    href="create_bill.php"
                    class="btn btn-create"
                >
                    + Create New Bill
                </a>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Customer</th>

                            <th>Date</th>

                            <th>Description</th>

                            <th>Amount</th>

                            <th>Discount</th>

                            <th>VAT</th>

                            <th>Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if ($result->num_rows > 0): ?>

                        <?php while ($bill = $result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    #<?= $bill["id"] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $bill["customer_name"] ?? "Unknown"
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($bill["date"]) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $bill["description"]
                                    ) ?>
                                </td>

                                <td>
                                    Rs. <?= number_format(
                                        $bill["amount"],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        $bill["discount"],
                                        2
                                    ) ?>%
                                </td>

                                <td>
                                    <?= number_format(
                                        $bill["vat"],
                                        2
                                    ) ?>%
                                </td>

                                <td>

                                    <?php
                                    $status = strtolower(
                                        $bill["status"] ?? "unpaid"
                                    );

                                    $class = "unpaid";

                                    if ($status === "paid") {
                                        $class = "paid";
                                    } elseif ($status === "pending") {
                                        $class = "pending";
                                    }
                                    ?>

                                    <span class="status <?= $class ?>">

                                        <?= htmlspecialchars(
                                            $bill["status"]
                                        ) ?>

                                    </span>

                                </td>

                                <td>
                                    <a
    href="view_bill.php?id=<?= $bill["id"] ?>"
    class="btn btn-view"
>
    👁 View
</a>

<a
    href="delete_bill.php?id=<?= $bill["id"] ?>"
    class="btn btn-delete"
    onclick="return confirm('Are you sure you want to delete this bill?');"
>
    🗑 Delete
</a>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="9"
                                style="text-align:center;padding:30px;"
                            >
                                No bills found.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>

</html>

<?php
$conn->close();
?>