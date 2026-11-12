
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Admin access protection
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$sql = "SELECT
            ec.id,
            ec.certificate_no,
            ec.verification_token,
            ec.issued_at,
            ec.revoked_at,
            dr.reference_no,
            u.full_name,
            dt.document_name
        FROM e_certificates ec
        INNER JOIN document_requests dr
            ON ec.request_id = dr.id
        INNER JOIN document_types dt
            ON dr.document_type_id = dt.id
        INNER JOIN users u
            ON dr.user_id = u.id
        ORDER BY ec.issued_at DESC";

$stmt = $pdo->query($sql);
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalCertificates = count($certificates);
$validCertificates = 0;
$revokedCertificates = 0;

foreach ($certificates as $certificate) {
    if ($certificate['revoked_at'] === null) {
        $validCertificates++;
    } else {
        $revokedCertificates++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Certificate History | Barangay Cawit</title>

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
    max-width: 1250px;
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

.brand {
    display: flex;
    align-items: center;
    gap: 15px;
}

.logo {
    width: 65px;
    height: 65px;
    object-fit: contain;
}

h1 {
    margin: 0;
    color: #123b68;
}

.subtitle {
    color: #64748b;
    margin-top: 5px;
}

.back {
    background: #123b68;
    color: white;
    padding: 11px 17px;
    text-decoration: none;
    border-radius: 7px;
}

.intro, .card, .stat {
    background: white;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}

.intro {
    padding: 22px;
    margin-bottom: 20px;
}

.intro h2 {
    margin-top: 0;
    color: #123b68;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat {
    padding: 20px;
    border-left: 5px solid #123b68;
}

.stat h3 {
    margin: 0 0 10px;
    color: #64748b;
    font-size: 14px;
}

.stat p {
    margin: 0;
    font-size: 28px;
    font-weight: bold;
}

.valid-count {
    border-left-color: #16a34a;
}

.revoked-count {
    border-left-color: #dc2626;
}

.card {
    padding: 22px;
    overflow-x: auto;
}

.card h2 {
    color: #123b68;
    margin-top: 0;
}

table {
    width: 100%;
    min-width: 1100px;
    border-collapse: collapse;
}

th, td {
    padding: 13px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
}

th {
    background: #eaf1f8;
    color: #123b68;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.valid {
    background: #dcfce7;
    color: #166534;
}

.revoked {
    background: #fee2e2;
    color: #991b1b;
}

.verify {
    color: #2563eb;
    font-weight: bold;
    text-decoration: none;
}

.empty {
    padding: 35px;
    text-align: center;
    color: #64748b;
}

.footer {
    margin-top: 25px;
    text-align: center;
    color: #64748b;
    font-size: 13px;
}

@media(max-width: 650px) {
    body {
        padding: 12px;
    }

    .stats {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="header">

        <div class="brand">
            <img
                src="../assets/barangay_cawit_logo.png"
                class="logo"
                alt="Barangay Cawit Logo"
            >

            <div>
                <h1>BARANGAY CAWIT</h1>
                <div class="subtitle">
                    Manito, Albay | Admin Portal
                </div>
            </div>
        </div>

        <a href="dashboard.php" class="back">
            Back to Dashboard
        </a>

    </div>

    <div class="intro">
        <h2>Certificate History</h2>

        <p>
            View and monitor all electronic certificates issued
            by Barangay Cawit, including valid and revoked certificates.
        </p>
    </div>

    <div class="stats">

        <div class="stat">
            <h3>Total Certificates</h3>
            <p><?= $totalCertificates ?></p>
        </div>

        <div class="stat valid-count">
            <h3>Valid Certificates</h3>
            <p><?= $validCertificates ?></p>
        </div>

        <div class="stat revoked-count">
            <h3>Revoked Certificates</h3>
            <p><?= $revokedCertificates ?></p>
        </div>

    </div>

    <div class="card">

        <h2>All Issued Certificates</h2>

        <?php if ($totalCertificates > 0): ?>

        <table>

            <thead>
                <tr>
                    <th>Certificate No.</th>
                    <th>Resident</th>
                    <th>Document</th>
                    <th>Reference No.</th>
                    <th>Date Issued</th>
                    <th>Date Revoked</th>
                    <th>Status</th>
                    <th>Verification</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($certificates as $certificate): ?>

                <?php
                $isRevoked = $certificate['revoked_at'] !== null;

                $verifyUrl = '../verification/index.php?token='
                    . urlencode($certificate['verification_token']);
                ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($certificate['certificate_no']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($certificate['full_name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($certificate['document_name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($certificate['reference_no']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($certificate['issued_at']) ?>
                    </td>

                    <td>
                        <?= $isRevoked
                            ? htmlspecialchars($certificate['revoked_at'])
                            : '—' ?>
                    </td>

                    <td>
                        <?php if ($isRevoked): ?>
                            <span class="status revoked">
                                Revoked
                            </span>
                        <?php else: ?>
                            <span class="status valid">
                                Valid
                            </span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <a
                            class="verify"
                            href="<?= htmlspecialchars($verifyUrl) ?>"
                            target="_blank"
                        >
                            Verify
                        </a>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

        <?php else: ?>

            <div class="empty">
                <h3>No Certificate Records Found</h3>
                <p>Wala pang naitalang electronic certificates.</p>
            </div>

        <?php endif; ?>

    </div>

    <div class="footer">
        <strong>BARANGAY CAWIT</strong><br>
        Municipality of Manito, Province of Albay<br>
        Barangay Document Request and Verifiable E-Certificate System
    </div>

</div>

</body>
</html>
