<?php
session_start();

require_once "db.php";
if (isset($_GET['delete'])) {

    $payment_id = (int) $_GET['delete'];

    if ($payment_id > 0) {

        $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");

        if ($stmt) {
            $stmt->bind_param("i", $payment_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: payments.php");
    exit;
}
$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $bill_id = isset($_POST['bill_id']) ? (int) $_POST['bill_id'] : 0;

    $payment_method = isset($_POST['payment_method'])
        ? trim($_POST['payment_method'])
        : "";

    $amount = isset($_POST['amount'])
        ? (float) $_POST['amount']
        : 0;

    $payment_status = isset($_POST['payment_status'])
        ? trim($_POST['payment_status'])
        : "Paid";


    if ($bill_id <= 0) {

        $error = "Please select a bill.";

    } elseif ($payment_method === "") {

        $error = "Please select a payment method.";

    } elseif ($amount <= 0) {

        $error = "Please enter a valid payment amount.";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO payments
            (bill_id, payment_method, amount, payment_status)
            VALUES (?, ?, ?, ?)
        ");

        if ($stmt) {

            $stmt->bind_param(
                "isds",
                $bill_id,
                $payment_method,
                $amount,
                $payment_status
            );

            if ($stmt->execute()) {

                /*
                 * If payment is Paid, update the bill status.
                 */
                if (strtolower($payment_status) === "paid") {

                    $updateBill = $conn->prepare("
                        UPDATE bills
                        SET status = 'Paid'
                        WHERE id = ?
                    ");

                    if ($updateBill) {
                        $updateBill->bind_param("i", $bill_id);
                        $updateBill->execute();
                        $updateBill->close();
                    }
                }

                $message = "Payment recorded successfully.";

            } else {

                $error = "Error recording payment: " . $stmt->error;
            }

            $stmt->close();

        } else {

            $error = "Database error: " . $conn->error;
        }
    }
}
$bills = $conn->query("
    SELECT
        b.id,
        b.customer_id,
        b.amount,
        b.discount,
        b.vat,
        c.name AS customer_name
    FROM bills b
    LEFT JOIN customers c
        ON b.customer_id = c.customer_id
    ORDER BY b.id DESC
");
$payments = $conn->query("
    SELECT
        p.id,
        p.bill_id,
        p.payment_method,
        p.amount,
        p.payment_status,
        p.payment_date,
        c.name AS customer_name
    FROM payments p
    LEFT JOIN bills b
        ON p.bill_id = b.id
    LEFT JOIN customers c
        ON b.customer_id = c.customer_id
    ORDER BY p.payment_date DESC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Payments - Billing System</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f6fa;
            color: #172033;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 244px;
            height: 100vh;
            background: #1d2a3d;
            padding-top: 20px;
        }

        .logo {
            color: white;
            font-size: 22px;
            font-weight: bold;
            padding: 0 24px 30px;
        }

        .logo span {
            color: #ff9800;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 16px 28px;
            font-size: 15px;
        }

        .sidebar a:hover {
            background: #26364e;
        }

        .sidebar a.active {
            background: #ff9800;
        }
        .main {
            margin-left: 244px;
            min-height: 100vh;
        }

        .topbar {
            height: 70px;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 28px;
            border-bottom: 1px solid #e1e6ed;
        }

        .topbar h1 {
            margin: 0;
            font-size: 25px;
        }

        .admin {
            font-weight: bold;
            color: #333;
        }
        .content {
            padding: 30px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 26px;
            margin-bottom: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 22px;
            font-size: 20px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: bold;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #2196f3;
        }
        .btn {
            display: inline-block;
            border: none;
            border-radius: 6px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-payment {
            background: #ff9800;
            color: white;
            margin-top: 22px;
        }

        .btn-payment:hover {
            background: #e68900;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            padding: 7px 12px;
            font-size: 13px;
        }

        .btn-delete:hover {
            background: #bb2d3b;
        }
        .success {
            background: #d1e7dd;
            color: #0f5132;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .error {
            background: #f8d7da;
            color: #842029;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #1d2a3d;
            color: white;
            padding: 13px 12px;
            text-align: left;
            font-size: 14px;
        }

        td {
            padding: 13px 12px;
            border-bottom: 1px solid #e1e6ed;
            font-size: 14px;
        }

        tr:hover td {
            background: #f8fafc;
        }
        .status-paid {
            color: #08a045;
            font-weight: bold;
        }

        .status-pending {
            color: #e69500;
            font-weight: bold;
        }

        .status-unpaid {
            color: #dc3545;
            font-weight: bold;
        }
        @media (max-width: 800px) {

            .sidebar {
                width: 190px;
            }

            .main {
                margin-left: 190px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .content {
                padding: 20px;
            }
        }

    </style>

</head>


<body>
<div class="sidebar">

    <div class="logo">
        BILLING<span>SYSTEM</span>
    </div>

    <a href="dashboard.php">
        Dashboard
    </a>

    <a href="customers.php">
        Customers
    </a>

    <a href="products.php">
        Products
    </a>

    <a href="create_bill.php">
        Create Bill
    </a>

    <a href="bills.php">
        Bills
    </a>

    <a href="payments.php" class="active">
        Payments
    </a>

    <a href="users.php">
        Users
    </a>

    <a href="reports.php">
        Reports
    </a>

    <a href="Logout.php">
        Logout
    </a>

</div>
<div class="main">


    <!-- TOP BAR -->

    <div class="topbar">

        <h1>Payments</h1>

        <div class="admin">
            Admin
        </div>

    </div>

    <div class="content">


        <!-- MESSAGE -->

        <?php if ($message != ""): ?>

            <div class="success">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>


        <?php if ($error != ""): ?>

            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>
        <div class="card">

            <h2>Record Payment</h2>


            <form method="POST"
                  action="payments.php">


                <div class="form-grid">

                    <div class="form-group">

                        <label>
                            Select Bill
                        </label>

                        <select name="bill_id"
                                required>

                            <option value="">
                                Select Bill
                            </option>

                            <?php

                            if ($bills && $bills->num_rows > 0):

                                while ($bill = $bills->fetch_assoc()):

                                    $bill_total =
                                        (float)$bill['amount']
                                        - (float)$bill['discount'];

                                    $bill_total =
                                        $bill_total
                                        + (
                                            $bill_total
                                            * (float)$bill['vat']
                                            / 100
                                        );

                            ?>

                                <option value="<?php echo $bill['id']; ?>">

                                    Bill #<?php echo $bill['id']; ?>

                                    -
                                    <?php echo htmlspecialchars(
                                        $bill['customer_name'] ?? 'Unknown'
                                    ); ?>

                                    -
                                    Rs.
                                    <?php echo number_format(
                                        $bill_total,
                                        2
                                    ); ?>

                                </option>

                            <?php

                                endwhile;

                            endif;

                            ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label>
                            Payment Amount
                        </label>

                        <input
                            type="number"
                            name="amount"
                            step="0.01"
                            min="0.01"
                            placeholder="Enter payment amount"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Payment Method
                        </label>

                        <select
                            name="payment_method"
                            required
                        >

                            <option value="">
                                Select Method
                            </option>

                            <option value="Cash">
                                Cash
                            </option>

                            <option value="Bank Transfer">
                                Bank Transfer
                            </option>

                            <option value="eSewa">
                                eSewa
                            </option>

                            <option value="Khalti">
                                Khalti
                            </option>

                            <option value="Card">
                                Card
                            </option>

                        </select>

                    </div>

                    <div class="form-group">

                        <label>
                            Payment Status
                        </label>

                        <select
                            name="payment_status"
                            required
                        >

                            <option value="Paid">
                                Paid
                            </option>

                            <option value="Pending">
                                Pending
                            </option>

                            <option value="Unpaid">
                                Unpaid
                            </option>

                        </select>

                    </div>


                </div>


                <button
                    type="submit"
                    class="btn btn-payment"
                >
                    💰 Record Payment
                </button>


            </form>

        </div>
        <div class="card">

            <h2>
                Payment History
            </h2>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Bill ID</th>

                            <th>Customer</th>

                            <th>Amount</th>

                            <th>Payment Method</th>

                            <th>Status</th>

                            <th>Date</th>

                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if ($payments && $payments->num_rows > 0): ?>


                        <?php while ($payment = $payments->fetch_assoc()): ?>


                            <tr>


                                <!-- ID -->

                                <td>
                                    <?php
                                    echo $payment['id'];
                                    ?>
                                </td>


                                <!-- BILL ID -->

                                <td>
                                    #
                                    <?php
                                    echo $payment['bill_id'];
                                    ?>
                                </td>


                                <!-- CUSTOMER -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $payment['customer_name']
                                        ?? 'Unknown'
                                    );
                                    ?>

                                </td>


                                <!-- AMOUNT -->

                                <td>

                                    Rs.
                                    <?php
                                    echo number_format(
                                        $payment['amount'],
                                        2
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $payment['payment_method']
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php

                                    $status =
                                        $payment['payment_status'];

                                    if ($status === "Paid"):

                                    ?>

                                        <span class="status-paid">
                                            Paid
                                        </span>

                                    <?php
                                    elseif ($status === "Pending"):
                                    ?>

                                        <span class="status-pending">
                                            Pending
                                        </span>

                                    <?php else: ?>

                                        <span class="status-unpaid">
                                            Unpaid
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $payment['payment_date']
                                    );
                                    ?>

                                </td>


                                <!-- DELETE -->

                                <td>

                                    <a
                                        href="delete_payment.php?id=<?php echo $payment['id']; ?>"
                                        class="btn btn-delete"
                                        onclick="return confirm('Are you sure you want to delete this payment?');"
                                    >
                                        Delete
                                    </a>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="8"
                                style="text-align:center;"
                            >
                                No payments recorded yet.
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