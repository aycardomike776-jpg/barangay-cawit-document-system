
<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "resident"
) {
    header("Location: ../login.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        dr.reference_no,
        dr.purpose,
        dr.status,
        dr.requested_at,
        dt.document_name
    FROM document_requests dr
    INNER JOIN document_types dt
        ON dr.document_type_id = dt.id
    WHERE dr.user_id = ?
    ORDER BY dr.requested_at DESC
");

$stmt->execute([$_SESSION["user_id"]]);
$requests = $stmt->fetchAll();

function statusColor($status) {
    switch ($status) {
        case "Approved":
            return "#dcfce7";
        case "Rejected":
            return "#fee2e2";
        case "Completed":
            return "#dbeafe";
        default:
            return "#fef3c7";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Requests | Barangay Document System</title>

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
    max-width: 1050px;
    margin: 25px auto;
}

h1 {
    color: #123b5d;
}

.subtitle {
    color: #64748b;
    margin-bottom: 25px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,.06);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

th, td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

th {
    background: #123b5d;
    color: white;
}

.status {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #64748b;
}

.back {
    display: inline-block;
    margin-top: 20px;
    color: #126b62;
    text-decoration: none;
    font-weight: bold;
}

@media(max-width:600px) {
    body {
        padding: 12px;
    }

    .card {
        padding: 12px;
    }
}
</style>
</head>

<body>

<div class="container">

    <h1>My Document Requests</h1>

    <p class="subtitle">
        Track the status of your submitted barangay document requests.
    </p>

    <div class="card">

        <?php if (count($requests) > 0): ?>

        <table>
            <thead>
                <tr>
                    <th>Reference Number</th>
                    <th>Document</th>
                    <th>Purpose</th>
                    <th>Date Requested</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($requests as $request): ?>

                <tr>
                    <td>
                        <?= htmlspecialchars($request["reference_no"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($request["document_name"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($request["purpose"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($request["requested_at"]) ?>
                    </td>

                    <td>
                        <span class="status"
                        style="background: <?= statusColor($request["status"]) ?>;">
                            <?= htmlspecialchars($request["status"]) ?>
                        </span>
                    </td>
                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>

        <?php else: ?>

            <div class="empty">
                <h3>No Document Requests Yet</h3>
                <p>Your submitted requests will appear here.</p>
            </div>

        <?php endif; ?>

    </div>

    <a class="back" href="dashboard.php">
        ← Back to Dashboard
    </a>

</div>

</body>
</html>
