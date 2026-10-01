<?php
session_start();

if (!isset($_SESSION["logged_in"]) || $_SESSION["logged_in"] !== true) {
    header("Location: login.php");
    exit;
}
?>
<?php
session_start();
$host = "localhost";
$username = "root";
$password = "";
$database = "billing_system";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
if (isset($_POST['add_customer'])) {

    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);

    if ($name == "") {

        $_SESSION['message'] = "Customer name is required.";
        $_SESSION['message_type'] = "error";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO customers
            (name, phone, address, email)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssss",
            $name,
            $phone,
            $address,
            $email
        );

        if ($stmt->execute()) {

            $_SESSION['message'] =
                "Customer added successfully.";

            $_SESSION['message_type'] = "success";

        } else {

            $_SESSION['message'] =
                "Error adding customer: " . $stmt->error;

            $_SESSION['message_type'] = "error";
        }

        $stmt->close();
    }

    header("Location: customers.php");
    exit();
}
if (isset($_POST['update_customer'])) {

    $customer_id = intval($_POST['customer_id']);

    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);

    if ($name == "") {

        $_SESSION['message'] =
            "Customer name is required.";

        $_SESSION['message_type'] = "error";

    } else {

        $stmt = $conn->prepare("
            UPDATE customers
            SET name = ?,
                phone = ?,
                address = ?,
                email = ?
            WHERE customer_id = ?
        ");

        $stmt->bind_param(
            "ssssi",
            $name,
            $phone,
            $address,
            $email,
            $customer_id
        );

        if ($stmt->execute()) {

            $_SESSION['message'] =
                "Customer updated successfully.";

            $_SESSION['message_type'] = "success";

        } else {

            $_SESSION['message'] =
                "Error updating customer: " . $stmt->error;

            $_SESSION['message_type'] = "error";
        }

        $stmt->close();
    }

    header("Location: customers.php");
    exit();
}
if (isset($_GET['delete'])) {

    $customer_id = intval($_GET['delete']);

    $stmt = $conn->prepare("
        DELETE FROM customers
        WHERE customer_id = ?
    ");

    $stmt->bind_param("i", $customer_id);

    if ($stmt->execute()) {

        $_SESSION['message'] =
            "Customer deleted successfully.";

        $_SESSION['message_type'] = "success";

    } else {

        $_SESSION['message'] =
            "Unable to delete customer. "
            . $stmt->error;

        $_SESSION['message_type'] = "error";
    }

    $stmt->close();

    header("Location: customers.php");
    exit();
}
$search = "";

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

if ($search != "") {

    $searchValue = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT
            customer_id,
            name,
            phone,
            address,
            email
        FROM customers
        WHERE name LIKE ?
           OR phone LIKE ?
           OR address LIKE ?
           OR email LIKE ?
        ORDER BY customer_id DESC
    ");

    $stmt->bind_param(
        "ssss",
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );

    $stmt->execute();

    $customers = $stmt->get_result();

} else {

    $customers = $conn->query("
        SELECT
            customer_id,
            name,
            phone,
            address,
            email
        FROM customers
        ORDER BY customer_id DESC
    ");
}
$editCustomer = null;

if (isset($_GET['edit'])) {

    $customer_id = intval($_GET['edit']);

    $stmt = $conn->prepare("
        SELECT
            customer_id,
            name,
            phone,
            address,
            email
        FROM customers
        WHERE customer_id = ?
    ");

    $stmt->bind_param("i", $customer_id);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $editCustomer = $result->fetch_assoc();
    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Billing System - Customers</title>


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
    font-size: 23px;
    font-weight: bold;
    margin-bottom: 35px;
}

.logo span {
    color: #ff9900;
}

.menu {
    list-style: none;
}

.menu li {
    margin-bottom: 5px;
}

.menu a {
    display: block;
    padding: 14px 25px;
    color: #cbd5e1;
    text-decoration: none;
    font-size: 15px;
}

.menu a:hover,
.menu a.active {
    background: #ff9900;
    color: white;
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
    background: #241b4b;
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

.page-title {
    margin-bottom: 25px;
}

.page-title h1 {
    color: #172554;
    margin-bottom: 5px;
    font-size: 32px;
}

.page-title p {
    color: #64748b;
}
.message {
    padding: 13px 18px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}
.form-box {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
    margin-bottom: 25px;
}

.form-box h3 {
    color: #241b4b;
    margin-bottom: 20px;
    font-size: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 7px;
    font-weight: bold;
    font-size: 14px;
    color: #334155;
}

.form-group input {
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    outline: none;
    font-size: 14px;
}

.form-group input:focus {
    border-color: #ff9900;
}
.buttons {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

.btn {
    border: none;
    padding: 11px 20px;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
}

.btn-add {
    background: #ff9900;
    color: white;
}

.btn-add:hover {
    background: #e68900;
}

.btn-update {
    background: #241b4b;
    color: white;
}

.btn-cancel {
    background: #64748b;
    color: white;
}
.search-box {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
    margin-bottom: 25px;
}

.search-form {
    display: flex;
    gap: 10px;
}

.search-form input {
    flex: 1;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    outline: none;
}

.search-btn {
    background: #241b4b;
    color: white;
    border: none;
    padding: 12px 22px;
    border-radius: 5px;
    cursor: pointer;
}

.clear-btn {
    background: #64748b;
    color: white;
    padding: 12px 20px;
    border-radius: 5px;
    text-decoration: none;
}
.table-box {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
    overflow-x: auto;
}

.table-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.table-title h3 {
    color: #1e293b;
    font-size: 19px;
}

.table-title span {
    color: #64748b;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #241b4b;
    color: white;
    padding: 13px;
    text-align: left;
    font-size: 14px;
}

td {
    padding: 13px;
    border-bottom: 1px solid #e2e8f0;
    color: #475569;
    font-size: 14px;
}

tr:hover {
    background: #f8fafc;
}

.edit-btn {
    background: #ff9900;
    color: white;
    padding: 7px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
    margin-right: 5px;
}

.delete-btn {
    background: #dc2626;
    color: white;
    padding: 7px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
}

@media (max-width: 900px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 700px) {

    .sidebar {
        width: 70px;
    }

    .logo {
        font-size: 0;
    }

    .logo span {
        font-size: 20px;
    }

    .menu a {
        text-align: center;
        padding: 15px 5px;
    }

    .main {
        margin-left: 70px;
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
        BILLING<span>SYSTEM</span>
    </div>

    <ul class="menu">

        <li>
            <a href="dashboard.php">
                📊 Dashboard
            </a>
        </li>

        <li>
            <a href="customers.php" class="active">
                👥 Customers
            </a>
        </li>

        <li>
            <a href="products.php">
                📦 Products
            </a>
        </li>

        <li>
            <a href="create_bill.php">
                🧾 Create Bill
            </a>
        </li>

        <li>
            <a href="bills.php">
                📄 Bills
            </a>
        </li>

        <li>
            <a href="payments.php">
                💰 Payments
            </a>
        </li>

        <li>
            <a href="users.php">
                👤 Users
            </a>
        </li>

        <li>
            <a href="logout.php">
                🚪 Logout
            </a>
        </li>

    </ul>

</div>
<div class="main">

    <div class="topbar">

        <h2>Customers</h2>

        <div class="admin">

            <div class="admin-circle">
                A
            </div>

            <span>Admin</span>

        </div>

    </div>
    <div class="content">


        <!-- PAGE TITLE -->

        <div class="page-title">

            <h1>Customer Management</h1>

            <p>
                Add, edit, delete and search customers.
            </p>

        </div>

        <?php if (isset($_SESSION['message'])): ?>

            <div class="message
                <?php echo $_SESSION['message_type']; ?>">

                <?php
                echo htmlspecialchars($_SESSION['message']);
                ?>

            </div>

            <?php
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>

        <?php endif; ?>
        <div class="form-box">

        <?php if ($editCustomer): ?>

            <h3>✏️ Edit Customer</h3>

            <form method="POST">

                <input
                    type="hidden"
                    name="customer_id"
                    value="<?php
                    echo $editCustomer['customer_id'];
                    ?>"
                >

                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Customer Name *
                        </label>

                        <input
                            type="text"
                            name="name"
                            value="<?php
                            echo htmlspecialchars(
                                $editCustomer['name']
                            );
                            ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                            value="<?php
                            echo htmlspecialchars(
                                $editCustomer['phone'] ?? ''
                            );
                            ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Address
                        </label>

                        <input
                            type="text"
                            name="address"
                            value="<?php
                            echo htmlspecialchars(
                                $editCustomer['address'] ?? ''
                            );
                            ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            value="<?php
                            echo htmlspecialchars(
                                $editCustomer['email'] ?? ''
                            );
                            ?>"
                        >

                    </div>

                </div>


                <div class="buttons">

                    <button
                        type="submit"
                        name="update_customer"
                        class="btn btn-update"
                    >
                        Update Customer
                    </button>

                    <a
                        href="customers.php"
                        class="btn btn-cancel"
                    >
                        Cancel
                    </a>

                </div>

            </form>


        <?php else: ?>


            <h3>➕ Add New Customer</h3>

            <form method="POST">

                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Customer Name *
                        </label>

                        <input
                            type="text"
                            name="name"
                            placeholder="Enter customer name"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                            placeholder="Enter phone number"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Address
                        </label>

                        <input
                            type="text"
                            name="address"
                            placeholder="Enter address"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            placeholder="Enter email address"
                        >

                    </div>

                </div>


                <div class="buttons">

                    <button
                        type="submit"
                        name="add_customer"
                        class="btn btn-add"
                    >
                        + Add Customer
                    </button>

                </div>

            </form>

        <?php endif; ?>

        </div>
        <div class="search-box">

            <form
                method="GET"
                class="search-form"
            >

                <input
                    type="text"
                    name="search"
                    placeholder="Search by name, phone, address or email..."
                    value="<?php
                    echo htmlspecialchars($search);
                    ?>"
                >

                <button
                    type="submit"
                    class="search-btn"
                >
                    🔍 Search
                </button>

                <?php if ($search != ""): ?>

                    <a
                        href="customers.php"
                        class="clear-btn"
                    >
                        Clear
                    </a>

                <?php endif; ?>

            </form>

        </div>
        <div class="table-box">

            <div class="table-title">

                <h3>
                    Customer List
                </h3>

                <span>
                    Total:
                    <?php
                    echo $customers->num_rows;
                    ?>
                </span>

            </div>


            <table>

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Name</th>

                        <th>Phone</th>

                        <th>Address</th>

                        <th>Email</th>

                        <th>Action</th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($customers->num_rows > 0): ?>

                    <?php while (
                        $customer =
                        $customers->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $customer['customer_id']
                                );
                                ?>
                            </td>


                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $customer['name']
                                );
                                ?>
                            </td>


                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $customer['phone'] ?? '-'
                                );
                                ?>
                            </td>


                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $customer['address'] ?? '-'
                                );
                                ?>
                            </td>


                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $customer['email'] ?? '-'
                                );
                                ?>
                            </td>


                            <td>

                                <a
                                    href="customers.php?edit=<?php
                                    echo $customer['customer_id'];
                                    ?>"
                                    class="edit-btn"
                                >
                                    Edit
                                </a>


                                <a
                                    href="customers.php?delete=<?php
                                    echo $customer['customer_id'];
                                    ?>"
                                    class="delete-btn"
                                    onclick="return confirm(
                                        'Are you sure you want to delete this customer?'
                                    );"
                                >
                                    Delete
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="6"
                            style="text-align:center;"
                        >
                            No customers found.
                        </td>

                    </tr>

                <?php endif; ?>

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