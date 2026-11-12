
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'resident') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT
            dr.reference_no,
            dt.document_name,
            ec.certificate_no,
            ec.issued_at,
            ec.verification_token
        FROM e_certificates ec
        INNER JOIN document_requests dr ON ec.request_id = dr.id
        INNER JOIN document_types dt ON dr.document_type_id = dt.id
        WHERE dr.user_id = :user_id
          AND ec.revoked_at IS NULL
        ORDER BY ec.issued_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $user_id]);
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$baseUrl = "http://" . $_SERVER['HTTP_HOST']
         . "/barangay_document_system/verification/index.php?token=";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My E-Certificates</title>

    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            padding: 30px;
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
            gap: 15px;
            margin-bottom: 25px;
        }

        h1 {
            margin: 0;
            color: #123b68;
        }

        .back {
            display: inline-block;
            padding: 11px 18px;
            background: #123b68;
            color: white;
            text-decoration: none;
            border-radius: 7px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #eaf1f8;
            color: #123b68;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #dcfce7;
            color: #166534;
            font-size: 13px;
        }

        .verify {
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }

        .qr {
            width: 100px;
            height: 100px;
            display: block;
            margin: auto;
        }

        .qr-label {
            font-size: 12px;
            text-align: center;
            margin-top: 5px;
            color: #64748b;
        }

        .print-btn {
            display: inline-block;
            padding: 9px 13px;
            background: #15803d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            white-space: nowrap;
        }

        .print-btn:hover {
            background: #166534;
        }

        .empty {
            text-align: center;
            padding: 35px 10px;
            color: #64748b;
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
        <h1>My E-Certificates</h1>
        <a href="dashboard.php" class="back">Back to Dashboard</a>
    </div>

    <div class="card">

        <?php if (count($certificates) > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>Certificate No.</th>
                        <th>Document</th>
                        <th>Reference No.</th>
                        <th>Date Issued</th>
                        <th>Status</th>
                        <th>QR Code</th>
                        <th>Verification</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($certificates as $certificate): ?>

                        <?php
                        $verifyUrl = $baseUrl . urlencode(
                            $certificate['verification_token']
                        );

                        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data="
                               . urlencode($verifyUrl);
                        ?>

                        <tr>
                            <td><?= htmlspecialchars($certificate['certificate_no']) ?></td>

                            <td><?= htmlspecialchars($certificate['document_name']) ?></td>

                            <td><?= htmlspecialchars($certificate['reference_no']) ?></td>

                            <td><?= htmlspecialchars($certificate['issued_at']) ?></td>

                            <td><span class="status">Valid</span></td>

                            <td>
                                <img
                                    class="qr"
                                    src="<?= htmlspecialchars($qrUrl) ?>"
                                    alt="Certificate QR Code"
                                >
                                <div class="qr-label">Scan to Verify</div>
                            </td>

                            <td>
                                <a class="verify"
                                   href="<?= htmlspecialchars($verifyUrl) ?>"
                                   target="_blank">
                                    Verify
                                </a>
                            </td>

                            <td>
                                <a class="print-btn"
                                   href="print_certificate.php?certificate_no=<?= urlencode($certificate['certificate_no']) ?>"
                                   target="_blank">
                                    Print Certificate
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>

        <?php else: ?>

            <div class="empty">
                <h3>No Certificates Yet</h3>
                <p>Wala ka pang issued e-Certificate.</p>
                <p>Lalabas dito ang iyong certificate kapag na-issue na ng administrator.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
