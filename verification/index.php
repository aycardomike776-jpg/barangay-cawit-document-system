
<?php
require_once __DIR__ . '/../config/database.php';

$token = trim($_GET['token'] ?? '');
$certificate = null;
$error = '';

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = "Invalid verification link.";
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT
                ec.certificate_no,
                ec.issued_at,
                ec.revoked_at,
                dr.reference_no,
                dt.document_name,
                u.full_name
            FROM e_certificates ec
            INNER JOIN document_requests dr ON ec.request_id = dr.id
            INNER JOIN document_types dt ON dr.document_type_id = dt.id
            INNER JOIN users u ON dr.user_id = u.id
            WHERE ec.verification_token = :token
            LIMIT 1
        ");

        $stmt->execute(['token' => $token]);
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$certificate) {
            $error = "Certificate not found. The verification token is invalid.";
        } elseif ($certificate['revoked_at'] !== null) {
            $error = "This certificate has been revoked.";
            $certificate = null;
        }

    } catch (PDOException $e) {
        $error = "Unable to verify certificate. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Cawit - Certificate Verification</title>

    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #e8f0fa, #f8fafc);
            color: #1e293b;
        }

        .container {
            width: 100%;
            max-width: 720px;
            background: #fff;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(15, 45, 75, .12);
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 22px;
            margin-bottom: 25px;
        }

        .logo {
            width: 95px;
            height: 95px;
            object-fit: contain;
            margin-bottom: 10px;
        }

        .header h1 {
            color: #123b68;
            font-size: 25px;
            margin: 5px 0;
        }

        .header h2 {
            color: #123b68;
            font-size: 18px;
            margin: 8px 0;
        }

        .header p {
            color: #64748b;
            margin: 5px 0;
            font-size: 14px;
        }

        .valid {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
            padding: 20px;
            text-align: center;
            border-radius: 12px;
            font-size: 21px;
            font-weight: bold;
            margin-bottom: 25px;
        }

        .valid small {
            display: block;
            font-size: 13px;
            font-weight: normal;
            margin-top: 7px;
        }

        .invalid {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
            padding: 20px;
            text-align: center;
            border-radius: 12px;
            font-weight: bold;
        }

        .invalid p {
            font-size: 14px;
            font-weight: normal;
        }

        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 17px;
            border-radius: 10px;
            overflow-wrap: anywhere;
        }

        .item strong {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .item span {
            color: #123b68;
            font-weight: bold;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            color: #64748b;
            font-size: 12px;
            line-height: 1.7;
            border-top: 1px solid #e2e8f0;
            padding-top: 18px;
            margin-top: 28px;
        }

        @media (max-width: 550px) {
            body {
                padding: 12px;
            }

            .container {
                padding: 22px;
            }

            .details {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 21px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <img
            src="../assets/barangay_cawit_logo.png"
            alt="Barangay Cawit Logo"
            class="logo"
        >

        <h1>BARANGAY CAWIT</h1>
        <p>Municipality of Manito, Province of Albay</p>
        <h2>Certificate Verification</h2>
        <p>Official Electronic Certificate Authentication</p>
    </div>

    <?php if ($certificate): ?>

        <div class="valid">
            ✓ VALID CERTIFICATE
            <small>This electronic certificate is authentic and has not been revoked.</small>
        </div>

        <div class="details">

            <div class="item">
                <strong>Certificate Number</strong>
                <span><?= htmlspecialchars($certificate['certificate_no']) ?></span>
            </div>

            <div class="item">
                <strong>Resident Name</strong>
                <span><?= htmlspecialchars($certificate['full_name']) ?></span>
            </div>

            <div class="item">
                <strong>Document Type</strong>
                <span><?= htmlspecialchars($certificate['document_name']) ?></span>
            </div>

            <div class="item">
                <strong>Reference Number</strong>
                <span><?= htmlspecialchars($certificate['reference_no']) ?></span>
            </div>

            <div class="item">
                <strong>Date Issued</strong>
                <span><?= htmlspecialchars($certificate['issued_at']) ?></span>
            </div>

            <div class="item">
                <strong>Verification Status</strong>
                <span>Valid and Not Revoked</span>
            </div>

        </div>

    <?php else: ?>

        <div class="invalid">
            ✕ INVALID CERTIFICATE
            <p><?= htmlspecialchars($error) ?></p>
        </div>

    <?php endif; ?>

    <div class="footer">
        <strong>BARANGAY CAWIT</strong><br>
        Municipality of Manito, Province of Albay<br>
        Barangay Document Request and Verifiable E-Certificate System<br>
        <small>QR Code Authentication and Verification</small>
    </div>

</div>

</body>
</html>
