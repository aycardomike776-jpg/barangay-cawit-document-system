
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Admin access protection
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header("Location: ../login.php");
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

// Update request status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = "Invalid request. Please refresh the page.";
    } else {

        $request_id = filter_input(
            INPUT_POST,
            'request_id',
            FILTER_VALIDATE_INT
        );

        $status = $_POST['status'] ?? '';
        $remarks = trim($_POST['admin_remarks'] ?? '');

        $allowed_statuses = [
            'Pending',
            'Under Review',
            'Approved',
            'Rejected',
            'Completed'
        ];

        if (!$request_id || !in_array($status, $allowed_statuses, true)) {

            $error = "Invalid request information.";

        } else {

            try {

                $pdo->beginTransaction();

                // Get existing request information
                $check = $pdo->prepare("
                    SELECT
                        dr.reference_no,
                        dr.user_id,
                        dr.status,
                        dr.admin_remarks,
                        u.full_name,
                        dt.document_name
                    FROM document_requests dr
                    INNER JOIN users u ON dr.user_id = u.id
                    INNER JOIN document_types dt
                        ON dr.document_type_id = dt.id
                    WHERE dr.id = :request_id
                    FOR UPDATE
                ");

                $check->execute([
                    'request_id' => $request_id
                ]);

                $oldRequest = $check->fetch(PDO::FETCH_ASSOC);

                if (!$oldRequest) {
                    throw new RuntimeException("Request not found.");
                }

                $oldStatus = $oldRequest['status'];
                $oldRemarks = $oldRequest['admin_remarks'] ?? '';

                $statusChanged = ($oldStatus !== $status);
                $remarksChanged = ($oldRemarks !== $remarks);

                // Update document request
                $update = $pdo->prepare("
                    UPDATE document_requests
                    SET
                        status = :status,
                        admin_remarks = :remarks,
                        processed_by = :admin_id,
                        updated_at = NOW()
                    WHERE id = :request_id
                ");

                $update->execute([
                    'status' => $status,
                    'remarks' => $remarks,
                    'admin_id' => $_SESSION['user_id'],
                    'request_id' => $request_id
                ]);

                // Create resident notification only if status changed
                if ($statusChanged) {

                    $documentName = $oldRequest['document_name'];
                    $referenceNo = $oldRequest['reference_no'];

                    switch ($status) {

                        case 'Pending':
                            $notificationTitle = 'Request Status Updated';
                            $notificationMessage =
                                "Your request for {$documentName} has been returned to Pending status.";
                            break;

                        case 'Under Review':
                            $notificationTitle = 'Request Under Review';
                            $notificationMessage =
                                "Your request for {$documentName} is now under review.";
                            break;

                        case 'Approved':
                            $notificationTitle = 'Document Request Approved';
                            $notificationMessage =
                                "Congratulations! Your request for {$documentName} has been approved.";
                            break;

                        case 'Rejected':
                            $notificationTitle = 'Document Request Rejected';
                            $notificationMessage =
                                "Your request for {$documentName} has been rejected.";
                            break;

                        case 'Completed':
                            $notificationTitle = 'Document Request Completed';
                            $notificationMessage =
                                "Your request for {$documentName} has been marked as completed.";
                            break;

                        default:
                            $notificationTitle = 'Request Status Updated';
                            $notificationMessage =
                                "The status of your document request has been updated.";
                    }

                    $notificationMessage .=
                        " Reference Number: {$referenceNo}.";

                    if ($remarks !== '') {
                        $notificationMessage .=
                            " Admin Remarks: {$remarks}";
                    }

                    $notify = $pdo->prepare("
                        INSERT INTO notifications
                            (user_id, title, message)
                        VALUES
                            (:user_id, :title, :message)
                    ");

                    $notify->execute([
                        'user_id' => $oldRequest['user_id'],
                        'title' => $notificationTitle,
                        'message' => $notificationMessage
                    ]);
                }

                // Record admin activity if status or remarks changed
                if ($statusChanged || $remarksChanged) {

                    $action = $statusChanged
                        ? 'Request Status Updated'
                        : 'Request Remarks Updated';

                    $description =
                        "Reference No.: " . $oldRequest['reference_no'] .
                        " | Resident: " . $oldRequest['full_name'] .
                        " | Document: " . $oldRequest['document_name'];

                    if ($statusChanged) {
                        $description .=
                            " | Previous Status: " . $oldStatus .
                            " | New Status: " . $status;
                    }

                    if ($remarksChanged && $remarks !== '') {
                        $description .=
                            " | Admin Remarks: " . $remarks;
                    }

                    $log = $pdo->prepare("
                        INSERT INTO admin_activity_logs
                            (admin_id, action, description)
                        VALUES
                            (:admin_id, :action, :description)
                    ");

                    $log->execute([
                        'admin_id' => $_SESSION['user_id'],
                        'action' => $action,
                        'description' => $description
                    ]);
                }

                // Save all database operations
                $pdo->commit();

                $message = $statusChanged
                    ? "Request updated successfully! Resident notification has been sent."
                    : "Request information saved successfully.";

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    "Manage request error: " . $e->getMessage()
                );

                $error = "Unable to update request. Please try again.";
            }
        }
    }
}

// Get all document requests
$stmt = $pdo->query("
    SELECT
        dr.id,
        dr.reference_no,
        dr.purpose,
        dr.status,
        dr.admin_remarks,
        dr.requested_at,
        dr.updated_at,
        u.full_name,
        u.email,
        u.contact_number,
        u.address,
        dt.document_name
    FROM document_requests dr
    INNER JOIN users u ON dr.user_id = u.id
    INNER JOIN document_types dt ON dr.document_type_id = dt.id
    ORDER BY dr.requested_at DESC
");

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Document Requests | Barangay Cawit</title>

    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #f1f5f9;
            color: #1e293b;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 25px;
        }

        h1 {
            color: #123b68;
            margin: 0;
        }

        .back {
            background: #123b68;
            color: white;
            padding: 11px 16px;
            text-decoration: none;
            border-radius: 7px;
        }

        .message, .error {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 18px;
        }

        .message {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .request-card {
            background: white;
            padding: 22px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        .request-card h2 {
            color: #123b68;
            font-size: 19px;
            margin-top: 0;
        }

        .details {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(220px, 1fr)
            );
            gap: 12px;
            margin: 18px 0;
        }

        .detail {
            background: #f8fafc;
            padding: 12px;
            border-radius: 7px;
            overflow-wrap: anywhere;
        }

        .detail strong {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .detail span {
            font-size: 14px;
        }

        .remarks {
            margin: 15px 0;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        select, textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            margin-bottom: 14px;
        }

        textarea {
            min-height: 85px;
            resize: vertical;
        }

        button {
            background: #123b68;
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 7px;
            cursor: pointer;
        }

        button:hover {
            background: #1e5187;
        }

        .empty {
            background: white;
            padding: 35px;
            text-align: center;
            border-radius: 10px;
            color: #64748b;
        }

        .footer {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            margin-top: 30px;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Manage Document Requests</h1>

        <a href="dashboard.php" class="back">
            Back to Dashboard
        </a>
    </div>

    <?php if ($message): ?>
        <div class="message">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (count($requests) > 0): ?>

        <?php foreach ($requests as $request): ?>

            <div class="request-card">

                <h2>
                    <?= htmlspecialchars(
                        $request['document_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h2>

                <div class="details">

                    <div class="detail">
                        <strong>Reference Number</strong>
                        <span>
                            <?= htmlspecialchars($request['reference_no'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="detail">
                        <strong>Resident Name</strong>
                        <span>
                            <?= htmlspecialchars($request['full_name'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="detail">
                        <strong>Email</strong>
                        <span>
                            <?= htmlspecialchars($request['email'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="detail">
                        <strong>Contact Number</strong>
                        <span>
                            <?= htmlspecialchars($request['contact_number'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="detail">
                        <strong>Address</strong>
                        <span>
                            <?= htmlspecialchars($request['address'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="detail">
                        <strong>Date Requested</strong>
                        <span>
                            <?= htmlspecialchars($request['requested_at'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="detail">
                        <strong>Current Status</strong>
                        <span>
                            <?= htmlspecialchars($request['status'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="detail">
                        <strong>Purpose</strong>
                        <span>
                            <?= htmlspecialchars($request['purpose'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <input
                        type="hidden"
                        name="request_id"
                        value="<?= (int)$request['id'] ?>"
                    >

                    <div class="remarks">

                        <label>Update Request Status</label>

                        <select name="status" required>

                            <?php
                            $statuses = [
                                'Pending',
                                'Under Review',
                                'Approved',
                                'Rejected',
                                'Completed'
                            ];
                            ?>

                            <?php foreach ($statuses as $status): ?>

                                <option
                                    value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $request['status'] === $status ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <label>Admin Remarks</label>

                        <textarea
                            name="admin_remarks"
                            placeholder="Enter remarks or reason for decision..."
                        ><?= htmlspecialchars($request['admin_remarks'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

                    </div>

                    <button type="submit">
                        Save Changes
                    </button>

                </form>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="empty">
            <h2>No Document Requests</h2>
            <p>Wala pang document requests mula sa mga resident.</p>
        </div>

    <?php endif; ?>

    <div class="footer">
        Barangay Document Request and Verifiable E-Certificate System<br>
        Municipality of Manito, Province of Albay
    </div>

</div>

</body>
</html>
