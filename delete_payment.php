<?php
require_once "db.php";
if (isset($_GET['id'])) {

    $payment_id = (int) $_GET['id'];
    if ($payment_id > 0) {
        $stmt = $conn->prepare(
            "DELETE FROM payments WHERE id = ?"
        );

        if ($stmt) {

            $stmt->bind_param("i", $payment_id);

            if ($stmt->execute()) {

                $stmt->close();
                header("Location: payments.php");
                exit;

            } else {

                $stmt->close();

                die("Error deleting payment.");
            }

        } else {

            die("Database error: " . $conn->error);
        }

    } else {

        header("Location: payments.php");
        exit;
    }

} else {

    header("Location: payments.php");
    exit;
}

?>