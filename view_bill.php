<?php

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "billing_system"
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if (!isset($_GET['id'])) {
    die("Bill ID not provided.");
}

$bill_id = intval($_GET['id']);
$stmt = $conn->prepare("
    SELECT
        b.id,
        b.date,
        b.description,
        b.amount,
        b.discount,
        b.vat,
        b.status,
        c.name AS customer_name,
        c.phone,
        c.email,
        c.address
    FROM bills b
    LEFT JOIN customers c
        ON b.customer_id = c.customer_id
    WHERE b.id = ?
");

$stmt->bind_param("i", $bill_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Bill not found.");
}

$bill = $result->fetch_assoc();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>View Bill #<?= $bill['id'] ?></title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #17243b;
}

.invoice {
    width: 800px;
    max-width: 90%;
    margin: 40px auto;
    background: white;
    padding: 35px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border-radius: 10px;
}

.header {
    display: flex;
    justify-content: space-between;
    border-bottom: 2px solid #291d58;
    padding-bottom: 20px;
}

.logo {
    font-size: 28px;
    font-weight: bold;
}

.logo span {
    color: #ff9d00;
}

.invoice-title {
    text-align: right;
}

.invoice-title h1 {
    margin: 0;
    color: #291d58;
}

.customer {
    margin-top: 30px;
}

.customer h3 {
    color: #291d58;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 30px;
}

th {
    background: #291d58;
    color: white;
    padding: 12px;
    text-align: left;
}

td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
}

.total {
    margin-top: 25px;
    text-align: right;
    font-size: 20px;
}

.status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 15px;
    background: #fef3c7;
    color: #92400e;
}

.buttons {
    margin-top: 30px;
    text-align: center;
}

button,
.back {
    padding: 11px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    font-weight: bold;
}

.print {
    background: #291d58;
    color: white;
}

.back {
    background: #ff9d00;
    color: white;
}

@media print {

    body {
        background: white;
    }

    .invoice {
        width: 100%;
        max-width: none;
        margin: 0;
        box-shadow: none;
    }

    .buttons {
        display: none;
    }

}

</style>

</head>

<body>

<div class="invoice">

    <div class="header">

        <div class="logo">
            BILLING<span>SYSTEM</span>
        </div>

        <div class="invoice-title">

            <h1>INVOICE</h1>

            <p>
                Bill #<?= $bill['id'] ?>
            </p>

            <p>
                Date: <?= htmlspecialchars($bill['date']) ?>
            </p>

        </div>

    </div>


    <div class="customer">

        <h3>Customer Information</h3>

        <p>
            <strong>Name:</strong>
            <?= htmlspecialchars($bill['customer_name'] ?? 'Unknown') ?>
        </p>

        <p>
            <strong>Phone:</strong>
            <?= htmlspecialchars($bill['phone'] ?? '') ?>
        </p>

        <p>
            <strong>Email:</strong>
            <?= htmlspecialchars($bill['email'] ?? '') ?>
        </p>

        <p>
            <strong>Address:</strong>
            <?= htmlspecialchars($bill['address'] ?? '') ?>
        </p>

    </div>


    <table>

        <tr>

            <th>Description</th>

            <th>Amount</th>

        </tr>

        <tr>

            <td>
                <?= htmlspecialchars($bill['description']) ?>
            </td>

            <td>
                Rs.
                <?= number_format($bill['amount'], 2) ?>
            </td>

        </tr>

    </table>


    <div class="total">

        <p>
            Amount:
            <strong>
                Rs. <?= number_format($bill['amount'], 2) ?>
            </strong>
        </p>

        <p>
            Discount:
            <strong>
                <?= number_format($bill['discount'], 2) ?>%
            </strong>
        </p>

        <p>
            VAT:
            <strong>
                <?= number_format($bill['vat'], 2) ?>%
            </strong>
        </p>

        <p>

            Status:

            <span class="status">
                <?= htmlspecialchars($bill['status']) ?>
            </span>

        </p>

    </div>


    <div class="buttons">

        <button
            class="print"
            onclick="window.print()"
        >
            🖨 Print Bill
        </button>

        <a
            href="bills.php"
            class="back"
        >
            ← Back to Bills
        </a>

    </div>

</div>

</body>

</html>

<?php
$conn->close();
?>