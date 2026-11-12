
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'resident') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];


$certificate_no = trim($_GET['certificate_no'] ?? '');

if ($certificate_no === '') {
    exit("Invalid certificate number.");
}


$sql = "SELECT
            ec.certificate_no,
            ec.issued_at,
            ec.verification_token,
            dr.reference_no,
            dt.document_name,
            u.full_name
        FROM e_certificates ec
        INNER JOIN document_requests dr ON ec.request_id = dr.id
        INNER JOIN document_types dt ON dr.document_type_id = dt.id
        INNER JOIN users u ON dr.user_id = u.id
        WHERE dr.user_id = :user_id
          AND ec.certificate_no = :certificate_no
          AND ec.revoked_at IS NULL
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'user_id' => $user_id,
    'certificate_no' => $certificate_no
]);

$certificate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    exit("Certificate not found or is no longer valid.");
}

// Verification URL
$baseUrl = "http://" . $_SERVER['HTTP_HOST']
         . "/barangay_document_system/verification/index.php?token=";

$verificationUrl = $baseUrl . urlencode(
    $certificate['verification_token']
);

// QR Code image URL
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?format=png&size=300x300&data="
       . urlencode($verificationUrl);

$issuedDate = date("F d, Y", strtotime($certificate['issued_at']));

$logoPath = "../assets/barangay_cawit_logo.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Barangay Cawit E-Certificate</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px;
    background: #e2e8f0;
    font-family: Georgia, "Times New Roman", serif;
    color: #1e293b;
}

.toolbar {
    text-align: center;
    margin-bottom: 20px;
}

.toolbar button {
    padding: 12px 22px;
    border: none;
    border-radius: 6px;
    background: #123b68;
    color: white;
    font-size: 15px;
    cursor: pointer;
}

.certificate {
    width: 100%;
    max-width: 1000px;
    min-height: 620px;
    margin: auto;
    padding: 30px;
    background: white;
    border: 10px double #b28b36;
    text-align: center;
}

.header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    margin-bottom: 12px;
}

.logo {
    width: 105px;
    height: 105px;
    object-fit: contain;
}

.header-text {
    text-align: center;
}

.top-label {
    color: #64748b;
    font-size: 13px;
    letter-spacing: 2px;
    margin: 4px;
}

.barangay-name {
    color: #123b68;
    font-size: 25px;
    font-weight: bold;
    letter-spacing: 2px;
    margin: 8px 0;
}

h1 {
    color: #123b68;
    font-size: 34px;
    margin: 10px 0;
    letter-spacing: 2px;
}

h2 {
    color: #9b762b;
    font-size: 23px;
    margin: 8px 0 15px;
}

.intro {
    font-size: 16px;
    margin-top: 18px;
}

.resident-name {
    font-size: 29px;
    font-weight: bold;
    color: #123b68;
    margin: 12px 0;
    text-transform: uppercase;
}

.description {
    font-size: 16px;
    line-height: 1.5;
    max-width: 680px;
    margin: 12px auto;
}

.details {
    margin-top: 15px;
    font-size: 13px;
    line-height: 1.7;
}

.qr-section {
    margin-top: 12px;
}

.qr-section img {
    width: 110px;
    height: 110px;
    display: block;
    margin: auto;
    object-fit: contain;
}

.qr-section p {
    font-size: 11px;
    margin: 5px 0;
    font-weight: bold;
}

.signature-section {
    width: 240px;
    margin: 18px 35px 0 auto;
    text-align: center;
}

.signature-line {
    border-top: 1px solid #1e293b;
    padding-top: 5px;
}

.signature-name {
    font-size: 14px;
    font-weight: bold;
    color: #123b68;
    text-transform: uppercase;
    margin: 2px 0;
}

.signature-title {
    font-size: 12px;
    margin: 2px 0;
}

.footer {
    margin-top: 10px;
    font-size: 11px;
    color: #64748b;
}

@media print {
    @page {
        size: A4 landscape;
        margin: 8mm;
    }

    html, body {
        width: 100%;
        height: auto;
        margin: 0;
        padding: 0;
        background: white;
    }

    .toolbar {
        display: none !important;
    }

    .certificate {
        width: 100%;
        max-width: none;
        height: 185mm;
        min-height: 0;
        margin: 0;
        padding: 15px;
        overflow: hidden;
        border: 8px double #b28b36;
        page-break-inside: avoid;
        break-inside: avoid;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .header {
        gap: 15px;
        margin-bottom: 8px;
    }

    .logo {
        width: 85px;
        height: 85px;
    }

    .top-label {
        font-size: 11px;
        margin: 3px;
    }

    .barangay-name {
        font-size: 21px;
        margin: 5px 0;
    }

    h1 {
        font-size: 28px;
        margin: 6px 0;
    }

    h2 {
        font-size: 19px;
        margin: 5px 0 10px;
    }

    .intro {
        font-size: 14px;
        margin-top: 10px;
    }

    .resident-name {
        font-size: 24px;
        margin: 8px 0;
    }

    .description {
        font-size: 13px;
        line-height: 1.35;
        margin: 8px auto;
    }

    .details {
        margin-top: 9px;
        font-size: 11px;
        line-height: 1.5;
    }

    .qr-section {
        margin-top: 7px;
    }

    .qr-section img {
        width: 90px;
        height: 90px;
    }

    .qr-section p {
        font-size: 9px;
        margin: 3px 0;
    }

    .signature-section {
        width: 220px;
        margin: 12px 35px 0 auto;
    }

    .signature-name {
        font-size: 12px;
    }

    .signature-title {
        font-size: 10px;
    }

    .footer {
        margin-top: 5px;
        font-size: 9px;
    }
}
</style>
</head>

<body>

<div class="toolbar">
    <button onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="certificate">

    <div class="header">

        <img
            src="<?= htmlspecialchars($logoPath) ?>"
            alt="Barangay Cawit Logo"
            class="logo"
        >

        <div class="header-text">
            <p class="top-label">REPUBLIC OF THE PHILIPPINES</p>
            <p class="top-label">PROVINCE OF ALBAY</p>
            <p class="top-label">MUNICIPALITY OF MANITO</p>

            <div class="barangay-name">BARANGAY CAWIT</div>
        </div>

    </div>

    <h1>BARANGAY CERTIFICATE</h1>

    <h2>
        <?= htmlspecialchars($certificate['document_name']) ?>
    </h2>

    <p class="intro">TO WHOM IT MAY CONCERN:</p>

    <p class="description">This is to certify that</p>

    <div class="resident-name">
        <?= htmlspecialchars($certificate['full_name']) ?>
    </div>

    <p class="description">
        has been issued this
        <strong><?= htmlspecialchars($certificate['document_name']) ?></strong>
        by the Office of Barangay Cawit, Municipality of Manito,
        Province of Albay, subject to the official records
        and information maintained by the barangay.
    </p>

    <p class="description">
        This electronic certificate may be verified by scanning
        the QR Code provided below.
    </p>

    <div class="details">
        <strong>Certificate No.:</strong>
        <?= htmlspecialchars($certificate['certificate_no']) ?>
        <br>

        <strong>Reference No.:</strong>
        <?= htmlspecialchars($certificate['reference_no']) ?>
        <br>

        <strong>Date Issued:</strong>
        <?= htmlspecialchars($issuedDate) ?>
    </div>

    <div class="qr-section">
        <img
            src="<?= htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8') ?>"
            alt="Certificate QR Code"
            width="110"
            height="110"
        >

        <p>SCAN TO VERIFY CERTIFICATE</p>
    </div>

    <div class="signature-section">
        <div class="signature-line">
            <div class="signature-name">ERNISTO DAEN</div>
            <p class="signature-title">
                Punong Barangay / Barangay Captain
            </p>
        </div>
    </div>

    <div class="footer">
        This is a digitally generated certificate.
        Verification is available through its QR Code.
    </div>

</div>

</body>
</html>
