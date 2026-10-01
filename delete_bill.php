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

if (isset($_GET['id'])) {

    $bill_id = intval($_GET['id']);

    // First delete bill items
    $stmt = $conn->prepare(
        "DELETE FROM bill_items WHERE bill_id = ?"
    );

    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $stmt->close();


    // Then delete the bill
    $stmt = $conn->prepare(
        "DELETE FROM bills WHERE id = ?"
    );

    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

header("Location: bills.php?deleted=1");
exit;

?>