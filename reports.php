<?php
require_once __DIR__ . '/db.php';

$report = $_GET['report'] ?? 'dashboard';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

function totalBill($amount, $discount, $vat)
{
    $x = max(0, (float)$amount - (float)$discount);
    return $x + ($x * ((float)$vat / 100));
}

function h($x)
{
    return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
}

function rs($x)
{
    return 'Rs. ' . number_format((float)$x, 2);
}

/* =========================
   SUMMARY
========================= */

$totalCustomers = 0;
$totalProducts  = 0;
$totalBills     = 0;
$totalRevenue   = 0;
$totalPayments  = 0;

if ($r = $conn->query("SELECT COUNT(*) AS total FROM customers")) {
    $totalCustomers = (int)$r->fetch_assoc()['total'];
}

if ($r = $conn->query("SELECT COUNT(*) AS total FROM products")) {
    $totalProducts = (int)$r->fetch_assoc()['total'];
}

if ($r = $conn->query("SELECT COUNT(*) AS total FROM bills")) {
    $totalBills = (int)$r->fetch_assoc()['total'];
}

if ($r = $conn->query("
    SELECT COALESCE(
        SUM((amount-discount)+((amount-discount)*vat/100)),0
    ) AS revenue
    FROM bills
")) {
    $totalRevenue = (float)$r->fetch_assoc()['revenue'];
}

if ($r = $conn->query("
    SELECT COALESCE(SUM(amount),0) AS total
    FROM payments
    WHERE payment_status='Paid'
")) {
    $totalPayments = (float)$r->fetch_assoc()['total'];
}

$totalDue = max(0, $totalRevenue - $totalPayments);


/* =========================
   DAILY SALES
========================= */

$daily = [];

$sql = "
SELECT
    DATE(b.date) AS sale_date,
    COUNT(*) AS total_bills,

    COALESCE(
        SUM(
            (b.amount-b.discount)
            +
            ((b.amount-b.discount)*b.vat/100)
        ),0
    ) AS sales,

    COALESCE(
        (
            SELECT SUM(p.amount)
            FROM payments p
            WHERE p.payment_status='Paid'
            AND p.bill_id IN
            (
                SELECT id
                FROM bills
                WHERE DATE(date)=DATE(b.date)
            )
        ),0
    ) AS paid

FROM bills b
GROUP BY DATE(b.date)
ORDER BY sale_date DESC
LIMIT 7
";

if ($r = $conn->query($sql)) {

    while ($x = $r->fetch_assoc()) {

        $x['pending'] =
            max(0, $x['sales'] - $x['paid']);

        $daily[] = $x;
    }
}


/* =========================
   RECENT BILLS
========================= */

$recent = [];

$sql = "
SELECT
    b.id,
    b.date,
    b.status,
    c.name AS customer,

    (
        (b.amount-b.discount)
        +
        ((b.amount-b.discount)*b.vat/100)
    ) AS total,

    COALESCE(
        (
            SELECT SUM(p.amount)
            FROM payments p
            WHERE p.bill_id=b.id
            AND p.payment_status='Paid'
        ),0
    ) AS paid

FROM bills b

LEFT JOIN customers c
ON c.customer_id=b.customer_id

ORDER BY b.date DESC,b.id DESC

LIMIT 8
";

if ($r = $conn->query($sql)) {

    while ($x = $r->fetch_assoc()) {
        $recent[] = $x;
    }
}


/* =========================
   CUSTOMER REPORT
========================= */

$customerRows = [];

if ($report === 'customers') {

    $sql = "
    SELECT
        c.customer_id,
        c.name,
        c.phone,
        c.address,
        c.email,

        COUNT(DISTINCT b.id) AS bills,

        COALESCE(
            SUM(
                CASE
                WHEN p.payment_status='Paid'
                THEN p.amount
                ELSE 0
                END
            ),0
        ) AS paid

    FROM customers c

    LEFT JOIN bills b
    ON c.customer_id=b.customer_id

    LEFT JOIN payments p
    ON b.id=p.bill_id
    ";

    if ($search !== '') {

        $s = $conn->real_escape_string($search);

        $sql .= "
        WHERE
            c.name LIKE '%$s%'
            OR c.phone LIKE '%$s%'
            OR c.email LIKE '%$s%'
        ";
    }

    $sql .= "
    GROUP BY
        c.customer_id,
        c.name,
        c.phone,
        c.address,
        c.email

    ORDER BY c.customer_id DESC
    ";

    if ($r = $conn->query($sql)) {

        while ($x = $r->fetch_assoc()) {
            $customerRows[] = $x;
        }
    }
}


/* =========================
   BILL REPORT
========================= */

$billRows = [];
$grandTotal = 0;

if ($report === 'bills') {

    $sql = "
    SELECT
        b.id,
        b.date,
        b.description,
        b.amount,
        b.discount,
        b.vat,
        b.status,
        c.name AS customer

    FROM bills b

    LEFT JOIN customers c
    ON c.customer_id=b.customer_id

    WHERE 1=1
    ";

    if ($from !== '') {

        $sql .= "
        AND DATE(b.date) >= '"
        . $conn->real_escape_string($from)
        . "'";
    }

    if ($to !== '') {

        $sql .= "
        AND DATE(b.date) <= '"
        . $conn->real_escape_string($to)
        . "'";
    }

    if ($status !== '') {

        $sql .= "
        AND b.status='"
        . $conn->real_escape_string($status)
        . "'";
    }

    $sql .= "
    ORDER BY b.date DESC,b.id DESC
    ";

    if ($r = $conn->query($sql)) {

        while ($x = $r->fetch_assoc()) {

            $x['total'] = totalBill(
                $x['amount'],
                $x['discount'],
                $x['vat']
            );

            $grandTotal += $x['total'];

            $billRows[] = $x;
        }
    }
}


/* =========================
   PAYMENT REPORT
========================= */

$paymentRows = [];
$paymentTotal = 0;

if ($report === 'payments') {

    $sql = "
    SELECT
        p.id,
        p.bill_id,
        p.payment_method,
        p.amount,
        p.payment_status,
        p.payment_date,
        c.name AS customer

    FROM payments p

    LEFT JOIN bills b
    ON p.bill_id=b.id

    LEFT JOIN customers c
    ON b.customer_id=c.customer_id

    WHERE 1=1
    ";

    if ($from !== '') {

        $sql .= "
        AND DATE(p.payment_date) >= '"
        . $conn->real_escape_string($from)
        . "'";
    }

    if ($to !== '') {

        $sql .= "
        AND DATE(p.payment_date) <= '"
        . $conn->real_escape_string($to)
        . "'";
    }

    $sql .= "
    ORDER BY p.payment_date DESC
    ";

    if ($r = $conn->query($sql)) {

        while ($x = $r->fetch_assoc()) {

            if ($x['payment_status'] === 'Paid') {
                $paymentTotal += (float)$x['amount'];
            }

            $paymentRows[] = $x;
        }
    }
}


/* =========================
   DUE REPORT
========================= */

$dueRows = [];
$dueTotal = 0;

if ($report === 'due') {

    $sql = "
    SELECT
        b.id,
        b.date,
        b.amount,
        b.discount,
        b.vat,
        c.name AS customer,

        COALESCE(
            (
                SELECT SUM(p.amount)
                FROM payments p
                WHERE p.bill_id=b.id
                AND p.payment_status='Paid'
            ),0
        ) AS paid

    FROM bills b

    LEFT JOIN customers c
    ON b.customer_id=c.customer_id

    ORDER BY b.date DESC
    ";

    if ($r = $conn->query($sql)) {

        while ($x = $r->fetch_assoc()) {

            $x['billtotal'] = totalBill(
                $x['amount'],
                $x['discount'],
                $x['vat']
            );

            $x['due'] =
                max(0, $x['billtotal'] - $x['paid']);

            if ($x['due'] > 0) {

                $dueTotal += $x['due'];

                $dueRows[] = $x;
            }
        }
    }
}


/* =========================
   PRODUCT REPORT
========================= */

$productRows = [];
$stockValue = 0;

if ($report === 'products') {

    $sql = "
    SELECT
        id,
        name,
        category,
        price,
        quantity,
        created_at

    FROM products

    ORDER BY id DESC
    ";

    if ($r = $conn->query($sql)) {

        while ($x = $r->fetch_assoc()) {

            $x['stockvalue'] =
                $x['price'] * $x['quantity'];

            $stockValue += $x['stockvalue'];

            $productRows[] = $x;
        }
    }
}


/* =========================
   SALES REPORT
========================= */

$salesRows = [];
$salesTotal = 0;

if ($report === 'sales') {

    $sql = "
    SELECT
        p.name,
        p.category,
        SUM(bi.quantity) AS qty,
        SUM(bi.subtotal) AS sales

    FROM bill_items bi

    JOIN products p
    ON p.id=bi.product_id

    JOIN bills b
    ON b.id=bi.bill_id

    WHERE 1=1
    ";

    if ($from !== '') {

        $sql .= "
        AND DATE(b.date) >= '"
        . $conn->real_escape_string($from)
        . "'";
    }

    if ($to !== '') {

        $sql .= "
        AND DATE(b.date) <= '"
        . $conn->real_escape_string($to)
        . "'";
    }

    $sql .= "
    GROUP BY
        p.id,
        p.name,
        p.category

    ORDER BY sales DESC
    ";

    if ($r = $conn->query($sql)) {

        while ($x = $r->fetch_assoc()) {

            $salesTotal += $x['sales'];

            $salesRows[] = $x;
        }
    }
}


/* =========================
   MONTHLY REPORT
========================= */

$monthlyRows = [];

if ($report === 'monthly') {

    $sql = "
    SELECT
        DATE_FORMAT(date,'%Y-%m') AS month,
        COUNT(*) AS bills,

        SUM(
            (amount-discount)
            +
            ((amount-discount)*vat/100)
        ) AS revenue

    FROM bills

    GROUP BY DATE_FORMAT(date,'%Y-%m')

    ORDER BY month DESC
    ";

    if ($r = $conn->query($sql)) {

        while ($x = $r->fetch_assoc()) {

            $month =
                $conn->real_escape_string($x['month']);

            $x['paid'] = 0;

            if ($p = $conn->query("
                SELECT COALESCE(SUM(amount),0) AS paid
                FROM payments
                WHERE payment_status='Paid'
                AND DATE_FORMAT(payment_date,'%Y-%m')='$month'
            ")) {

                $x['paid'] =
                    $p->fetch_assoc()['paid'];
            }

            $x['due'] =
                max(0, $x['revenue'] - $x['paid']);

            $monthlyRows[] = $x;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Billing System - Reports</title>

<style>

*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:Arial,Helvetica,sans-serif;
    background:#f3f6fb;
    color:#1f2937;
}

/* SIDEBAR */

.sidebar{
    position:fixed;
    left:0;
    top:0;
    width:240px;
    height:100vh;
    background:#14283f;
    color:white;
}

.brand{
    height:74px;
    background:#124c82;
    display:flex;
    align-items:center;
    padding:0 24px;
    font-size:22px;
    font-weight:bold;
}

.nav{
    padding-top:15px;
}

.nav a{
    display:flex;
    align-items:center;
    gap:13px;
    padding:15px 24px;
    color:white;
    text-decoration:none;
    font-size:15px;
}

.nav a:hover{
    background:#1d3c5c;
}

.nav a.active{
    background:#2d8df0;
    font-weight:bold;
}

.nav .icon{
    width:22px;
    text-align:center;
}

/* MAIN */

.main{
    margin-left:240px;
    min-height:100vh;
}

.topbar{
    height:74px;
    background:#1674c9;
    color:white;
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 28px;
}

.topbar .menu{
    font-size:25px;
}

.content{
    padding:30px;
}

/* HEADER */

.page-head{
    background:white;
    border-radius:9px;
    padding:22px;
    margin-bottom:20px;
    box-shadow:0 2px 10px rgba(0,0,0,.05);
}

.page-head h1{
    margin:0 0 8px;
    font-size:30px;
}

.page-head p{
    margin:0;
    color:#64748b;
}

/* CARDS */

.cards{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:20px;
    margin-bottom:22px;
}

.card{
    color:white;
    padding:20px;
    min-height:125px;
    border-radius:9px;
    box-shadow:0 4px 12px rgba(0,0,0,.08);
}

.card:nth-child(1){
    background:#2b8eea;
}

.card:nth-child(2){
    background:#22a650;
}

.card:nth-child(3){
    background:#149ec0;
}

.card:nth-child(4){
    background:#f39212;
}

.card-title{
    font-size:14px;
    font-weight:bold;
    margin-bottom:14px;
}

.card-value{
    font-size:25px;
    font-weight:bold;
}

.card-sub{
    margin-top:8px;
    font-size:12px;
}

/* BOX */

.box{
    background:white;
    border-radius:9px;
    padding:20px;
    margin-bottom:20px;
    box-shadow:0 2px 10px rgba(0,0,0,.05);
}

/* TABS */

.tabs{
    display:flex;
    overflow-x:auto;
    border-bottom:1px solid #e5e7eb;
    margin:-20px -20px 20px;
}

.tabs a{
    padding:18px 20px;
    text-decoration:none;
    color:#48617e;
    font-size:14px;
    font-weight:bold;
    white-space:nowrap;
    border-bottom:3px solid transparent;
}

.tabs a:hover,
.tabs a.active{
    color:#1674c9;
    border-bottom-color:#1674c9;
}

/* FILTER */

.filters{
    display:flex;
    flex-wrap:wrap;
    gap:15px;
    align-items:end;
    background:#f8fafc;
    padding:16px;
    border:1px solid #e5e7eb;
    border-radius:8px;
}

.field{
    display:flex;
    flex-direction:column;
    gap:6px;
    min-width:150px;
    flex:1;
}

.field label{
    font-size:12px;
    font-weight:bold;
}

input,
select{
    height:40px;
    border:1px solid #d3dce7;
    border-radius:6px;
    padding:0 10px;
    background:white;
}

.btn{
    height:40px;
    padding:0 18px;
    border:0;
    border-radius:6px;
    color:white;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    font-weight:bold;
}

.primary{
    background:#1986e8;
}

.success{
    background:#16a34a;
}

.secondary{
    background:#64748b;
}

/* TABLE */

.section-title{
    font-size:18px;
    margin:0 0 15px;
    color:#203a5a;
}

.table-wrap{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;
}

th{
    background:#f1f5f9;
    color:#294462;
    text-align:left;
    font-weight:bold;
}

th,
td{
    padding:13px 11px;
    border-bottom:1px solid #e5e7eb;
}

tr:hover td{
    background:#fafcff;
}

.total-row td{
    font-weight:bold;
    background:#f8fafc;
}

/* STATUS */

.status{
    display:inline-block;
    padding:5px 10px;
    border-radius:20px;
    font-size:11px;
    font-weight:bold;
}

.status-paid{
    background:#dcfce7;
    color:#15803d;
}

.status-pending{
    background:#ffedd5;
    color:#c2410c;
}

.empty{
    text-align:center;
    color:#718096;
    padding:25px;
}

/* TWO COLUMNS */

.grid2{
    display:grid;
    grid-template-columns:1fr 1.2fr;
    gap:20px;
}

/* CHART */

.chart{
    height:250px;
    display:flex;
    align-items:flex-end;
    gap:20px;
    padding:20px;
    border-left:1px solid #dbe4ee;
    border-bottom:1px solid #dbe4ee;
}

.bar-group{
    flex:1;
    height:100%;
    display:flex;
    align-items:flex-end;
    justify-content:center;
    gap:4px;
}

.bar{
    width:25%;
    border-radius:4px 4px 0 0;
}

.bar.sales{
    background:#2b8eea;
}

.bar.paid{
    background:#22a650;
}

.bar.pending{
    background:#f39212;
}

.footer{
    text-align:center;
    color:#728096;
    font-size:12px;
    padding:20px;
}

/* RESPONSIVE */

@media(max-width:1000px){

    .cards{
        grid-template-columns:repeat(2,1fr);
    }

    .grid2{
        grid-template-columns:1fr;
    }
}

@media(max-width:700px){

    .sidebar{
        width:190px;
    }

    .main{
        margin-left:190px;
    }

    .content{
        padding:15px;
    }

    .cards{
        grid-template-columns:1fr;
    }
}

/* PRINT */

@media print{

    .sidebar,
    .topbar,
    .tabs,
    .filters,
    .no-print{
        display:none !important;
    }

    .main{
        margin-left:0;
    }

    .content{
        padding:10px;
    }
}

</style>

</head>

<body>

<!-- SIDEBAR -->

<aside class="sidebar">

<div class="brand">
    Billing System
</div>

<nav class="nav">

<a href="dashboard.php">
    <span class="icon">⌂</span>
    Dashboard
</a>

<a href="customers.php">
    <span class="icon">♟</span>
    Customers
</a>

<a href="products.php">
    <span class="icon">▣</span>
    Products
</a>

<a href="create_bill.php">
    <span class="icon">＋</span>
    Create Bill
</a>

<a href="bills.php">
    <span class="icon">▤</span>
    Bills
</a>

<a href="payments.php">
    <span class="icon">▣</span>
    Payments
</a>

<a href="reports.php" class="active">
    <span class="icon">▥</span>
    Reports
</a>

<a href="users.php">
    <span class="icon">♟</span>
    Users
</a>

<a href="Logout.php">
    <span class="icon">⇥</span>
    Logout
</a>

</nav>

</aside>


<!-- MAIN -->

<main class="main">

<div class="topbar">

<div class="menu">
    ☰
</div>

<div>
    ◉ &nbsp; Admin
</div>

</div>


<div class="content">


<!-- PAGE HEADER -->

<div class="page-head">

<h1>
    ▥ &nbsp; Reports
</h1>

<p>
    View and manage your billing reports
</p>

</div>


<!-- SUMMARY CARDS -->

<div class="cards">

<div class="card">

<div class="card-title">
    Total Bills
</div>

<div class="card-value">
    <?php echo $totalBills; ?>
</div>

<div class="card-sub">
    All bills
</div>

</div>


<div class="card">

<div class="card-title">
    Total Revenue
</div>

<div class="card-value">
    <?php echo rs($totalRevenue); ?>
</div>

<div class="card-sub">
    All time
</div>

</div>


<div class="card">

<div class="card-title">
    Paid Amount
</div>

<div class="card-value">
    <?php echo rs($totalPayments); ?>
</div>

<div class="card-sub">
    Payment received
</div>

</div>


<div class="card">

<div class="card-title">
    Pending Amount
</div>

<div class="card-value">
    <?php echo rs($totalDue); ?>
</div>

<div class="card-sub">
    Remaining due
</div>

</div>

</div>


<!-- REPORT MENU -->

<div class="box">

<div class="tabs">

<a href="reports.php"
class="<?php echo $report==='dashboard'?'active':''; ?>">
Daily Sales
</a>

<a href="?report=monthly"
class="<?php echo $report==='monthly'?'active':''; ?>">
Monthly Sales
</a>

<a href="?report=bills"
class="<?php echo $report==='bills'?'active':''; ?>">
Bill Report
</a>

<a href="?report=payments"
class="<?php echo $report==='payments'?'active':''; ?>">
Payment Report
</a>

<a href="?report=due"
class="<?php echo $report==='due'?'active':''; ?>">
Due / Pending
</a>

<a href="?report=customers"
class="<?php echo $report==='customers'?'active':''; ?>">
Customer Report
</a>

<a href="?report=sales"
class="<?php echo $report==='sales'?'active':''; ?>">
Sales Report
</a>

<a href="?report=products"
class="<?php echo $report==='products'?'active':''; ?>">
Product Report
</a>

</div>


<?php if($report==='dashboard'): ?>


<form class="filters no-print"
method="get">

<input
type="hidden"
name="report"
value="dashboard">


<div class="field">

<label>
From Date
</label>

<input
type="date"
name="from"
value="<?php echo h($from); ?>">

</div>


<div class="field">

<label>
To Date
</label>

<input
type="date"
name="to"
value="<?php echo h($to); ?>">

</div>


<button
class="btn primary"
type="submit">

Show Report

</button>


<button
class="btn success"
type="button"
onclick="window.print()">

Print

</button>

</form>


<h2
class="section-title"
style="margin-top:20px">

Daily Sales Report

</h2>


<div class="table-wrap">

<table>

<tr>

<th>Date</th>

<th>Total Bills</th>

<th>Total Sales</th>

<th>Paid Amount</th>

<th>Pending Amount</th>

</tr>


<?php if(!$daily): ?>

<tr>

<td
colspan="5"
class="empty">

No sales data found.

</td>

</tr>

<?php else: ?>

<?php foreach($daily as $row): ?>

<tr>

<td>
<?php echo h($row['sale_date']); ?>
</td>

<td>
<?php echo h($row['total_bills']); ?>
</td>

<td>
<?php echo rs($row['sales']); ?>
</td>

<td>
<?php echo rs($row['paid']); ?>
</td>

<td>
<?php echo rs($row['pending']); ?>
</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</table>

</div>

<?php endif; ?>


<?php if($report==='customers'): ?>

<form
class="filters no-print"
method="get">

<input
type="hidden"
name="report"
value="customers">


<div class="field">

<label>
Search Customer
</label>

<input
type="text"
name="search"
placeholder="Name / Phone / Email"
value="<?php echo h($search); ?>">

</div>


<button class="btn primary">
Search
</button>


<a
class="btn secondary"
href="?report=customers">

Reset

</a>


<button
type="button"
class="btn success"
onclick="window.print()">

Print

</button>

</form>


<h2
class="section-title"
style="margin-top:20px">

Customer Transaction Report

</h2>


<div class="table-wrap">

<table>

<tr>

<th>ID</th>
<th>Name</th>
<th>Phone</th>
<th>Address</th>
<th>Email</th>
<th>Total Bills</th>
<th>Total Paid</th>

</tr>


<?php if(!$customerRows): ?>

<tr>

<td colspan="7"
class="empty">

No customers found.

</td>

</tr>

<?php else: ?>

<?php foreach($customerRows as $row): ?>

<tr>

<td>
<?php echo h($row['customer_id']); ?>
</td>

<td>
<?php echo h($row['name']); ?>
</td>

<td>
<?php echo h($row['phone']); ?>
</td>

<td>
<?php echo h($row['address']); ?>
</td>

<td>
<?php echo h($row['email']); ?>
</td>

<td>
<?php echo h($row['bills']); ?>
</td>

<td>
<?php echo rs($row['paid']); ?>
</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</table>

</div>

<?php endif; ?>


<?php if($report==='bills'): ?>

<form
class="filters no-print"
method="get">

<input
type="hidden"
name="report"
value="bills">


<div class="field">

<label>
From Date
</label>

<input
type="date"
name="from"
value="<?php echo h($from); ?>">

</div>


<div class="field">

<label>
To Date
</label>

<input
type="date"
name="to"
value="<?php echo h($to); ?>">

</div>


<div class="field">

<label>
Status
</label>

<select name="status">

<option value="">
All
</option>

<option value="Paid"
<?php if($status==='Paid') echo 'selected'; ?>>

Paid

</option>

<option value="Unpaid"
<?php if($status==='Unpaid') echo 'selected'; ?>>

Unpaid

</option>

<option value="Pending"
<?php if($status==='Pending') echo 'selected'; ?>>

Pending

</option>

</select>

</div>


<button class="btn primary">
Show Report
</button>


<button
type="button"
class="btn success"
onclick="window.print()">

Print

</button>

</form>


<h2
class="section-title"
style="margin-top:20px">

Billing Report

</h2>


<div class="table-wrap">

<table>

<tr>

<th>Bill ID</th>
<th>Customer</th>
<th>Date</th>
<th>Description</th>
<th>Amount</th>
<th>Discount</th>
<th>VAT</th>
<th>Total</th>
<th>Status</th>

</tr>


<?php if(!$billRows): ?>

<tr>

<td
colspan="9"
class="empty">

No bills found.

</td>

</tr>

<?php else: ?>

<?php foreach($billRows as $row): ?>

<tr>

<td>
<?php echo h($row['id']); ?>
</td>

<td>
<?php echo h($row['customer']); ?>
</td>

<td>
<?php echo h($row['date']); ?>
</td>

<td>
<?php echo h($row['description']); ?>
</td>

<td>
<?php echo rs($row['amount']); ?>
</td>

<td>
<?php echo rs($row['discount']); ?>
</td>

<td>
<?php echo h($row['vat']); ?>%
</td>

<td>
<strong>
<?php echo rs($row['total']); ?>
</strong>
</td>

<td>

<span class="status
<?php echo $row['status']==='Paid'
? ' status-paid'
: ' status-pending'; ?>">

<?php echo h($row['status']); ?>

</span>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>


<tr class="total-row">

<td colspan="7">
Grand Total
</td>

<td colspan="2">
<?php echo rs($grandTotal); ?>
</td>

</tr>

</table>

</div>

<?php endif; ?>


<?php if($report==='payments'): ?>

<form
class="filters no-print"
method="get">

<input
type="hidden"
name="report"
value="payments">


<div class="field">

<label>
From Date
</label>

<input
type="date"
name="from"
value="<?php echo h($from); ?>">

</div>


<div class="field">

<label>
To Date
</label>

<input
type="date"
name="to"
value="<?php echo h($to); ?>">

</div>


<button class="btn primary">
Show Report
</button>


<button
type="button"
class="btn success"
onclick="window.print()">

Print

</button>

</form>


<h2
class="section-title"
style="margin-top:20px">

Payment Report

</h2>


<div class="table-wrap">

<table>

<tr>

<th>Payment ID</th>
<th>Bill ID</th>
<th>Customer</th>
<th>Method</th>
<th>Amount</th>
<th>Status</th>
<th>Date</th>

</tr>


<?php if(!$paymentRows): ?>

<tr>

<td
colspan="7"
class="empty">

No payments found.

</td>

</tr>

<?php else: ?>

<?php foreach($paymentRows as $row): ?>

<tr>

<td>
<?php echo h($row['id']); ?>
</td>

<td>
<?php echo h($row['bill_id']); ?>
</td>

<td>
<?php echo h($row['customer']); ?>
</td>

<td>
<?php echo h($row['payment_method']); ?>
</td>

<td>
<?php echo rs($row['amount']); ?>
</td>

<td>

<span class="status
<?php echo $row['payment_status']==='Paid'
? ' status-paid'
: ' status-pending'; ?>">

<?php echo h($row['payment_status']); ?>

</span>

</td>

<td>
<?php echo h($row['payment_date']); ?>
</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>


<tr class="total-row">

<td colspan="4">
Total Payment Received
</td>

<td colspan="3">
<?php echo rs($paymentTotal); ?>
</td>

</tr>

</table>

</div>

<?php endif; ?>


<?php if($report==='due'): ?>

<div
class="no-print"
style="text-align:right;margin-bottom:15px">

<button
class="btn success"
onclick="window.print()">

Print

</button>

</div>


<h2 class="section-title">

Due / Pending Payment Report

</h2>


<div class="table-wrap">

<table>

<tr>

<th>Bill ID</th>
<th>Customer</th>
<th>Date</th>
<th>Bill Total</th>
<th>Paid</th>
<th>Due</th>
<th>Status</th>

</tr>


<?php if(!$dueRows): ?>

<tr>

<td colspan="7"
class="empty">

No pending payments found.

</td>

</tr>

<?php else: ?>

<?php foreach($dueRows as $row): ?>

<tr>

<td>
<?php echo h($row['id']); ?>
</td>

<td>
<?php echo h($row['customer']); ?>
</td>

<td>
<?php echo h($row['date']); ?>
</td>

<td>
<?php echo rs($row['billtotal']); ?>
</td>

<td>
<?php echo rs($row['paid']); ?>
</td>

<td>
<strong>
<?php echo rs($row['due']); ?>
</strong>
</td>

<td>

<span class="status status-pending">
Pending
</span>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>


<tr class="total-row">

<td colspan="5">
Total Due
</td>

<td colspan="2">
<?php echo rs($dueTotal); ?>
</td>

</tr>

</table>

</div>

<?php endif; ?>


<?php if($report==='products'): ?>

<div
class="no-print"
style="text-align:right;margin-bottom:15px">

<button
class="btn success"
onclick="window.print()">

Print

</button>

</div>


<h2 class="section-title">

Product Report

</h2>


<div class="table-wrap">

<table>

<tr>

<th>ID</th>
<th>Product</th>
<th>Category</th>
<th>Price</th>
<th>Quantity</th>
<th>Stock Value</th>
<th>Created</th>

</tr>


<?php if(!$productRows): ?>

<tr>

<td colspan="7"
class="empty">

No products found.

</td>

</tr>

<?php else: ?>

<?php foreach($productRows as $row): ?>

<tr>

<td>
<?php echo h($row['id']); ?>
</td>

<td>
<?php echo h($row['name']); ?>
</td>

<td>
<?php echo h($row['category']); ?>
</td>

<td>
<?php echo rs($row['price']); ?>
</td>

<td>
<?php echo h($row['quantity']); ?>
</td>

<td>
<?php echo rs($row['stockvalue']); ?>
</td>

<td>
<?php echo h($row['created_at']); ?>
</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>


<tr class="total-row">

<td colspan="5">
Total Stock Value
</td>

<td colspan="2">
<?php echo rs($stockValue); ?>
</td>

</tr>

</table>

</div>

<?php endif; ?>


<?php if($report==='sales'): ?>

<form
class="filters no-print"
method="get">

<input
type="hidden"
name="report"
value="sales">


<div class="field">

<label>
From Date
</label>

<input
type="date"
name="from"
value="<?php echo h($from); ?>">

</div>


<div class="field">

<label>
To Date
</label>

<input
type="date"
name="to"
value="<?php echo h($to); ?>">

</div>


<button class="btn primary">
Show Report
</button>


<button
type="button"
class="btn success"
onclick="window.print()">

Print

</button>

</form>


<h2
class="section-title"
style="margin-top:20px">

Sales Report

</h2>


<div class="table-wrap">

<table>

<tr>

<th>Product</th>
<th>Category</th>
<th>Quantity Sold</th>
<th>Total Sales</th>

</tr>


<?php if(!$salesRows): ?>

<tr>

<td colspan="4"
class="empty">

No sales found.

</td>

</tr>

<?php else: ?>

<?php foreach($salesRows as $row): ?>

<tr>

<td>
<?php echo h($row['name']); ?>
</td>

<td>
<?php echo h($row['category']); ?>
</td>

<td>
<?php echo h($row['qty']); ?>
</td>

<td>
<?php echo rs($row['sales']); ?>
</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>


<tr class="total-row">

<td colspan="3">
Total Sales
</td>

<td>
<?php echo rs($salesTotal); ?>
</td>

</tr>

</table>

</div>

<?php endif; ?>


<?php if($report==='monthly'): ?>

<div
class="no-print"
style="text-align:right;margin-bottom:15px">

<button
class="btn success"
onclick="window.print()">

Print

</button>

</div>


<h2 class="section-title">

Monthly Revenue Report

</h2>


<div class="table-wrap">

<table>

<tr>

<th>Month</th>
<th>Total Bills</th>
<th>Revenue</th>
<th>Payment Received</th>
<th>Due</th>

</tr>


<?php if(!$monthlyRows): ?>

<tr>

<td colspan="5"
class="empty">

No monthly data found.

</td>

</tr>

<?php else: ?>

<?php foreach($monthlyRows as $row): ?>

<tr>

<td>
<?php echo h($row['month']); ?>
</td>

<td>
<?php echo h($row['bills']); ?>
</td>

<td>
<?php echo rs($row['revenue']); ?>
</td>

<td>
<?php echo rs($row['paid']); ?>
</td>

<td>
<?php echo rs($row['due']); ?>
</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</table>

</div>

<?php endif; ?>


</div>


<!-- DASHBOARD LOWER SECTION -->

<?php if($report==='dashboard'): ?>

<div class="grid2">


<div class="box">

<h2 class="section-title">

Sales Summary

</h2>

<div class="table-wrap">

<table>

<tr>

<th>Date</th>
<th>Sales</th>
<th>Paid</th>
<th>Pending</th>

</tr>

<?php foreach($daily as $row): ?>

<tr>

<td>
<?php echo h($row['sale_date']); ?>
</td>

<td>
<?php echo rs($row['sales']); ?>
</td>

<td>
<?php echo rs($row['paid']); ?>
</td>

<td>
<?php echo rs($row['pending']); ?>
</td>

</tr>

<?php endforeach; ?>

</table>

</div>

</div>


<div class="box">

<h2 class="section-title">

Recent Bills

</h2>

<div class="table-wrap">

<table>

<tr>

<th>Bill</th>
<th>Customer</th>
<th>Total</th>
<th>Paid</th>
<th>Status</th>

</tr>


<?php if(!$recent): ?>

<tr>

<td colspan="5"
class="empty">

No bills found.

</td>

</tr>

<?php else: ?>

<?php foreach($recent as $row): ?>

<tr>

<td>
<?php echo h($row['id']); ?>
</td>

<td>
<?php echo h($row['customer']); ?>
</td>

<td>
<?php echo rs($row['total']); ?>
</td>

<td>
<?php echo rs($row['paid']); ?>
</td>

<td>
    <?php
$billTotal = (float)$row['total'];
$paidAmount = (float)$row['paid'];
$pendingAmount = max(0, $billTotal - $paidAmount);

if ($pendingAmount <= 0) {
    $displayStatus = 'Paid';
    $statusClass = 'status-paid';
} else {
    $displayStatus = 'Pending';
    $statusClass = 'status-pending';
}
?>

<span class="status <?php echo $statusClass; ?>">
    <?php echo $displayStatus; ?>
</span>

</span>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</table>

</div>

</div>


</div>

<?php endif; ?>


<div class="footer">

© 2026 Billing System. All rights reserved.

</div>


</div>

</main>

</body>

</html>