<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$database = "billing_system";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

$message = "";
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_bill"])) {

    $customer_id = intval($_POST["customer_id"]);
    $description = trim($_POST["description"]);
    $discount = floatval($_POST["discount"]);
    $vat = floatval($_POST["vat"]);

    $product_ids = $_POST["product_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];

    if ($customer_id <= 0) {
        $error = "Please select a customer.";
    } elseif (empty($product_ids)) {
        $error = "Please add at least one product.";
    } else {

        $conn->begin_transaction();

        try {

            $items = [];
            $subtotal_total = 0;

            for ($i = 0; $i < count($product_ids); $i++) {

                $product_id = intval($product_ids[$i]);
                $quantity = intval($quantities[$i]);

                if ($product_id <= 0 || $quantity <= 0) {
                    continue;
                }

                $stmt = $conn->prepare(
                    "SELECT id, name, price, quantity
                     FROM products
                     WHERE id = ?
                     FOR UPDATE"
                );

                $stmt->bind_param("i", $product_id);
                $stmt->execute();

                $result = $stmt->get_result();
                $product = $result->fetch_assoc();

                $stmt->close();

                if (!$product) {
                    throw new Exception("Product not found.");
                }

                if ($quantity > $product["quantity"]) {
                    throw new Exception(
                        "Not enough stock for " . $product["name"] .
                        ". Available: " . $product["quantity"]
                    );
                }

                $price = floatval($product["price"]);
                $item_subtotal = $price * $quantity;

                $subtotal_total += $item_subtotal;

                $items[] = [
                    "product_id" => $product_id,
                    "quantity" => $quantity,
                    "price" => $price,
                    "subtotal" => $item_subtotal
                ];
            }

            if (empty($items)) {
                throw new Exception("Please add valid products.");
            }
            if ($discount < 0) {
                $discount = 0;
            }

            if ($vat < 0) {
                $vat = 0;
            }

            $discount_amount = ($subtotal_total * $discount) / 100;

            $after_discount = $subtotal_total - $discount_amount;

            $vat_amount = ($after_discount * $vat) / 100;

            $grand_total = $after_discount + $vat_amount;

            $description = $description !== ""
                ? $description
                : "Product Sale";

            $status = "Unpaid";

            $date = date("Y-m-d");

            $stmt = $conn->prepare(
                "INSERT INTO bills
                (customer_id, date, description, amount, discount, vat, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "issddds",
                $customer_id,
                $date,
                $description,
                $grand_total,
                $discount,
                $vat,
                $status
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to create bill.");
            }

            $bill_id = $conn->insert_id;

            $stmt->close();

            foreach ($items as $item) {

                $stmt = $conn->prepare(
                    "INSERT INTO bill_items
                    (bill_id, product_id, quantity, price, subtotal)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "iiidd",
                    $bill_id,
                    $item["product_id"],
                    $item["quantity"],
                    $item["price"],
                    $item["subtotal"]
                );

                if (!$stmt->execute()) {
                    throw new Exception("Failed to save bill item.");
                }

                $stmt->close();
                $stmt = $conn->prepare(
                    "UPDATE products
                     SET quantity = quantity - ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "ii",
                    $item["quantity"],
                    $item["product_id"]
                );

                if (!$stmt->execute()) {
                    throw new Exception("Failed to update product stock.");
                }

                $stmt->close();
            }

            $conn->commit();

            header("Location: bills.php?success=1&bill_id=" . $bill_id);
            exit;

        } catch (Exception $e) {

            $conn->rollback();

            $error = $e->getMessage();
        }
    }
}
$customers = [];

$result = $conn->query(
    "SELECT customer_id, name
     FROM customers
     ORDER BY name ASC"
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

$products = [];

$result = $conn->query(
    "SELECT id, name, category, price, quantity
     FROM products
     WHERE quantity > 0
     ORDER BY name ASC"
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Billing System - Create Bill</title>

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
    margin-bottom: 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.06);
}

.card h2 {
    margin-top: 0;
    color: #20194d;
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
    margin-bottom: 7px;
    font-weight: bold;
}

input,
select,
textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 15px;
}

textarea {
    resize: vertical;
}

.full {
    grid-column: 1 / -1;
}
.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

th {
    background: #291d58;
    color: white;
    padding: 12px;
    text-align: left;
}

td {
    padding: 10px;
    border-bottom: 1px solid #e2e8f0;
}

.product-select {
    min-width: 220px;
}

.qty {
    width: 100px;
}
.btn {
    border: none;
    padding: 12px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 15px;
    font-weight: bold;
}

.btn-add {
    background: #ff9d00;
    color: white;
    margin-top: 15px;
}

.btn-add:hover {
    background: #e68c00;
}

.btn-save {
    background: #291d58;
    color: white;
}

.btn-save:hover {
    background: #1f1645;
}

.btn-delete {
    background: #dc3545;
    color: white;
    padding: 8px 12px;
}

.actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}
.summary {
    margin-left: auto;
    width: 350px;
    margin-top: 20px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
}

.grand-total {
    border-top: 2px solid #291d58;
    margin-top: 8px;
    padding-top: 12px;
    font-size: 20px;
    font-weight: bold;
    color: #291d58;
}
.success {
    background: #dcfce7;
    color: #166534;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
}
@media(max-width: 800px) {

    .sidebar {
        width: 190px;
    }

    .main {
        margin-left: 190px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .summary {
        width: 100%;
    }
}

</style>
</head>

<body>
<div class="sidebar">

    <div class="logo">
        BILLING<span>SYSTEM</span>
    </div>

    <div class="menu">

        <a href="dashboard.php">📊 Dashboard</a>

        <a href="customers.php">👥 Customers</a>

        <a href="products.php">📦 Products</a>

        <a href="create_bill.php" class="active">
            🧾 Create Bill
        </a>

        <a href="bills.php">📄 Bills</a>

        <a href="payments.php">💰 Payments</a>

        <a href="users.php">👤 Users</a>

        <a href="logout.php">🚪 Logout</a>

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

        <h1>Create Bill</h1>

        <div class="subtitle">
            Create a new bill for your customer.
        </div>


        <?php if ($error): ?>

            <div class="error">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>
        <form method="POST" id="billForm">

            <div class="card">

                <h2>🧾 Bill Information</h2>

                <div class="form-grid">

                    <div class="form-group">

                        <label>
                            Customer *
                        </label>

                        <select name="customer_id" required>

                            <option value="">
                                -- Select Customer --
                            </option>

                            <?php foreach ($customers as $customer): ?>

                                <option value="<?= $customer["customer_id"] ?>">

                                    <?= htmlspecialchars($customer["name"]) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            Date
                        </label>

                        <input
                            type="date"
                            value="<?= date('Y-m-d') ?>"
                            disabled
                        >

                    </div>


                    <div class="form-group full">

                        <label>
                            Description
                        </label>

                        <textarea
                            name="description"
                            rows="2"
                            placeholder="Enter bill description..."
                        ></textarea>

                    </div>

                </div>

            </div>
            <div class="card">

                <h2>📦 Products</h2>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>Product</th>

                                <th>Available</th>

                                <th>Price</th>

                                <th>Quantity</th>

                                <th>Subtotal</th>

                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody id="productRows">

                        </tbody>

                    </table>

                </div>


                <button
                    type="button"
                    class="btn btn-add"
                    onclick="addProductRow()"
                >
                    + Add Product
                </button>

            </div>
            <div class="card">

                <h2>💰 Bill Summary</h2>

                <div class="form-grid">

                    <div class="form-group">

                        <label>
                            Discount (%)
                        </label>

                        <input
                            type="number"
                            name="discount"
                            id="discount"
                            value="0"
                            min="0"
                            max="100"
                            step="0.01"
                            oninput="calculateTotal()"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            VAT (%)
                        </label>

                        <input
                            type="number"
                            name="vat"
                            id="vat"
                            value="13"
                            min="0"
                            max="100"
                            step="0.01"
                            oninput="calculateTotal()"
                        >

                    </div>

                </div>


                <div class="summary">

                    <div class="summary-row">

                        <span>
                            Subtotal:
                        </span>

                        <strong>
                            Rs. <span id="subtotal">0.00</span>
                        </strong>

                    </div>


                    <div class="summary-row">

                        <span>
                            Discount:
                        </span>

                        <strong>
                            Rs. <span id="discountAmount">0.00</span>
                        </strong>

                    </div>


                    <div class="summary-row">

                        <span>
                            VAT:
                        </span>

                        <strong>
                            Rs. <span id="vatAmount">0.00</span>
                        </strong>

                    </div>


                    <div class="summary-row grand-total">

                        <span>
                            Grand Total:
                        </span>

                        <strong>
                            Rs. <span id="grandTotal">0.00</span>
                        </strong>

                    </div>

                </div>


                <div class="actions">

                    <a
                        href="dashboard.php"
                        class="btn"
                        style="background:#e2e8f0;text-decoration:none;color:#17243b;"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        name="save_bill"
                        class="btn btn-save"
                    >
                        💾 Save Bill
                    </button>

                </div>

            </div>

        </form>

    </div>

</div>


<script>
const products = <?= json_encode($products) ?>;
function addProductRow() {

    const tbody = document.getElementById("productRows");

    const row = document.createElement("tr");

    let options = `
        <option value="">-- Select Product --</option>
    `;

    products.forEach(product => {

        options += `
            <option
                value="${product.id}"
                data-price="${product.price}"
                data-stock="${product.quantity}"
            >
                ${escapeHtml(product.name)}
            </option>
        `;

    });


    row.innerHTML = `

        <td>

            <select
                name="product_id[]"
                class="product-select"
                onchange="productChanged(this)"
                required
            >

                ${options}

            </select>

        </td>


        <td class="available">
            -
        </td>


        <td class="price">
            0.00
        </td>


        <td>

            <input
                type="number"
                name="quantity[]"
                class="qty"
                value="1"
                min="1"
                onchange="calculateRow(this)"
                oninput="calculateRow(this)"
                required
            >

        </td>


        <td class="row-subtotal">
            0.00
        </td>


        <td>

            <button
                type="button"
                class="btn btn-delete"
                onclick="removeRow(this)"
            >
                Delete
            </button>

        </td>

    `;

    tbody.appendChild(row);

    calculateTotal();
}
function productChanged(select) {

    const row = select.closest("tr");

    const option = select.options[select.selectedIndex];

    const price = parseFloat(option.dataset.price || 0);

    const stock = parseInt(option.dataset.stock || 0);

    row.querySelector(".price").textContent =
        price.toFixed(2);

    row.querySelector(".available").textContent =
        stock;

    const quantity =
        row.querySelector(".qty");

    quantity.max = stock;

    if (parseInt(quantity.value) > stock) {
        quantity.value = stock;
    }

    calculateRow(quantity);
}
function calculateRow(input) {

    const row = input.closest("tr");

    const select =
        row.querySelector(".product-select");

    const option =
        select.options[select.selectedIndex];

    const price =
        parseFloat(option.dataset.price || 0);

    const quantity =
        parseInt(input.value || 0);

    const stock =
        parseInt(option.dataset.stock || 0);


    if (quantity > stock && stock > 0) {
        input.value = stock;
    }


    const finalQuantity =
        parseInt(input.value || 0);

    const subtotal =
        price * finalQuantity;


    row.querySelector(".row-subtotal").textContent =
        subtotal.toFixed(2);


    calculateTotal();
}
function calculateTotal() {

    let subtotal = 0;

    document.querySelectorAll(".row-subtotal")
        .forEach(cell => {

            subtotal +=
                parseFloat(cell.textContent) || 0;

        });


    const discount =
        parseFloat(
            document.getElementById("discount").value
        ) || 0;


    const vat =
        parseFloat(
            document.getElementById("vat").value
        ) || 0;


    const discountAmount =
        subtotal * discount / 100;


    const afterDiscount =
        subtotal - discountAmount;


    const vatAmount =
        afterDiscount * vat / 100;


    const grandTotal =
        afterDiscount + vatAmount;


    document.getElementById("subtotal").textContent =
        subtotal.toFixed(2);


    document.getElementById("discountAmount").textContent =
        discountAmount.toFixed(2);


    document.getElementById("vatAmount").textContent =
        vatAmount.toFixed(2);


    document.getElementById("grandTotal").textContent =
        grandTotal.toFixed(2);
}
function removeRow(button) {

    button.closest("tr").remove();

    calculateTotal();
}
function escapeHtml(text) {

    const div = document.createElement("div");

    div.textContent = text;

    return div.innerHTML;
}
document.addEventListener("DOMContentLoaded", function() {

    addProductRow();

});

</script>

</body>
</html>