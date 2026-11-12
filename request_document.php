
<?php
session_start();
require_once __DIR__ . "/config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "resident"
) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

$error = "";
$success = "";
$reference_no = "";

if (empty($_SESSION["request_csrf"])) {
    $_SESSION["request_csrf"] = bin2hex(random_bytes(32));
}

$stmt = $pdo->query(
    "SELECT id, document_name, description, fee
     FROM document_types
     WHERE is_active = 1
     ORDER BY document_name ASC"
);

$documents = $stmt->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf_token"] ?? "";

    $document_type_id = filter_input(
        INPUT_POST,
        "document_type_id",
        FILTER_VALIDATE_INT
    );

    $purpose = trim($_POST["purpose"] ?? "");

    if (!hash_equals($_SESSION["request_csrf"], $csrf)) {
        $error = "Invalid form submission. Please refresh the page.";
    } elseif (!$document_type_id || $purpose === "") {
        $error = "Please select a document and enter your purpose.";
    } elseif (strlen($purpose) > 2000) {
        $error = "Your purpose must not exceed 2000 characters.";
    } else {

        $check = $pdo->prepare(
            "SELECT id, document_name
             FROM document_types
             WHERE id = ? AND is_active = 1"
        );

        $check->execute([$document_type_id]);
        $selected_document = $check->fetch(PDO::FETCH_ASSOC);

        if (!$selected_document) {
            $error = "The selected document is not available.";
        } else {
            try {
                $reference_no = "BRG-" .
                    date("Ymd") . "-" .
                    strtoupper(bin2hex(random_bytes(4)));

                // Start transaction
                $pdo->beginTransaction();

                // Save document request
                $insert = $pdo->prepare(
                    "INSERT INTO document_requests
                    (reference_no, user_id, document_type_id, purpose)
                    VALUES (?, ?, ?, ?)"
                );

                $insert->execute([
                    $reference_no,
                    $user_id,
                    $document_type_id,
                    $purpose
                ]);

                // Create resident notification
                $notification_title = "Document Request Submitted";

                $notification_message =
                    "Your request for " .
                    $selected_document["document_name"] .
                    " has been successfully submitted. " .
                    "Your Reference Number is " .
                    $reference_no .
                    ". Your request is currently Pending.";

                $notify = $pdo->prepare(
                    "INSERT INTO notifications
                    (user_id, title, message)
                    VALUES (?, ?, ?)"
                );

                $notify->execute([
                    $user_id,
                    $notification_title,
                    $notification_message
                ]);

                // Save both operations
                $pdo->commit();

                $success = "Your document request has been submitted successfully!";

                $_SESSION["request_csrf"] = bin2hex(random_bytes(32));

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log("Document request error: " . $e->getMessage());

                $error = "Unable to submit your request. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Request a Document | Barangay Document System</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

.container {
    max-width: 650px;
    margin: 30px auto;
    background: white;
    padding: 35px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,.08);
}

h1 {
    color: #123b5d;
    margin-top: 0;
}

.subtitle {
    color: #64748b;
    margin-bottom: 28px;
}

label {
    display: block;
    font-weight: bold;
    margin: 18px 0 8px;
}

select, textarea {
    width: 100%;
    padding: 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 15px;
    font-family: Arial, sans-serif;
}

textarea {
    min-height: 130px;
    resize: vertical;
}

button {
    width: 100%;
    margin-top: 22px;
    padding: 14px;
    background: #126b62;
    border: none;
    border-radius: 8px;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #0d514b;
}

.message {
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.back {
    display: inline-block;
    margin-top: 20px;
    color: #126b62;
    text-decoration: none;
    font-weight: bold;
}
</style>
</head>

<body>

<div class="container">

    <h1>Request a Document</h1>

    <p class="subtitle">
        Barangay Document Request and E-Certificate System
    </p>

    <?php if ($error): ?>
        <div class="message error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="message success">
            <strong><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></strong>
            <p>Your Reference Number:</p>
            <strong><?= htmlspecialchars($reference_no, ENT_QUOTES, 'UTF-8') ?></strong>
            <p>Please save this number for tracking your request.</p>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION["request_csrf"], ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="document_type_id">
            Select Document *
        </label>

        <select name="document_type_id" id="document_type_id" required>
            <option value="">-- Choose a document --</option>

            <?php foreach ($documents as $document): ?>
                <option value="<?= (int)$document["id"] ?>">
                    <?= htmlspecialchars($document["document_name"], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>

        </select>

        <label for="purpose">Purpose of Request *</label>

        <textarea
            name="purpose"
            id="purpose"
            maxlength="2000"
            placeholder="Explain why you need this document..."
            required
        ></textarea>

        <button type="submit">Submit Document Request</button>

    </form>

    <a class="back" href="resident/dashboard.php">
        ← Back to Dashboard
    </a>

</div>

</body>
</html>
