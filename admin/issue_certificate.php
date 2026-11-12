
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

$message = '';
$error = '';

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrf_token) ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)
    ) {
        $error = "Invalid request. Please refresh the page.";

    } else {

        $action = $_POST['action'] ?? '';

        // =====================================
        // ISSUE CERTIFICATE
        // =====================================
        if ($action === 'issue') {

            $request_id = filter_var(
                $_POST['request_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$request_id) {
                $error = "Invalid document request.";

            } else {

                try {
                    $pdo->beginTransaction();

                    $check = $pdo->prepare("
                        SELECT
                            dr.id,
                            dr.reference_no,
                            dr.status,
                            u.full_name,
                            dt.document_name
                        FROM document_requests dr
                        INNER JOIN users u ON dr.user_id = u.id
                        INNER JOIN document_types dt
                            ON dr.document_type_id = dt.id
                        WHERE dr.id = :id
                        FOR UPDATE
                    ");

                    $check->execute(['id' => $request_id]);
                    $request = $check->fetch(PDO::FETCH_ASSOC);

                    if (!$request) {
                        throw new Exception(
                            "Document request not found."
                        );
                    }

                    if ($request['status'] !== 'Approved') {
                        throw new Exception(
                            "Only approved requests can receive certificates."
                        );
                    }

                    $existing = $pdo->prepare("
                        SELECT id
                        FROM e_certificates
                        WHERE request_id = :request_id
                        LIMIT 1
                    ");

                    $existing->execute([
                        'request_id' => $request_id
                    ]);

                    if ($existing->fetch()) {
                        throw new Exception(
                            "A certificate has already been issued for this request."
                        );
                    }

                    $certificate_no = 'BRG-CERT-' .
                        date('Ymd') . '-' .
                        strtoupper(bin2hex(random_bytes(4)));

                    $verification_token = bin2hex(random_bytes(32));

                    $insert = $pdo->prepare("
                        INSERT INTO e_certificates
                        (
                            request_id,
                            certificate_no,
                            verification_token,
                            issued_by,
                            issued_at
                        )
                        VALUES
                        (
                            :request_id,
                            :certificate_no,
                            :verification_token,
                            :issued_by,
                            NOW()
                        )
                    ");

                    $insert->execute([
                        'request_id' => $request_id,
                        'certificate_no' => $certificate_no,
                        'verification_token' => $verification_token,
                        'issued_by' => $_SESSION['user_id']
                    ]);

                    // AUTOMATIC ACTIVITY LOG
                    $log = $pdo->prepare("
                        INSERT INTO admin_activity_logs
                        (
                            admin_id,
                            action,
                            description
                        )
                        VALUES
                        (
                            :admin_id,
                            :action,
                            :description
                        )
                    ");

                    $log_description =
                        "Certificate No.: " . $certificate_no .
                        " | Reference No.: " . $request['reference_no'] .
                        " | Resident: " . $request['full_name'] .
                        " | Document: " . $request['document_name'] .
                        " | Status: Valid";

                    $log->execute([
                        'admin_id' => $_SESSION['user_id'],
                        'action' => 'E-Certificate Issued',
                        'description' => $log_description
                    ]);

                    $pdo->commit();

                    $message = "Certificate issued successfully! Certificate No.: " .
                        $certificate_no;

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $error = $e instanceof PDOException
                        ? "Unable to issue certificate. Please check the database and try again."
                        : $e->getMessage();
                }
            }

        // =====================================
        // REVOKE CERTIFICATE
        // =====================================
        } elseif ($action === 'revoke') {

            $certificate_id = filter_var(
                $_POST['certificate_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$certificate_id) {
                $error = "Invalid certificate selected.";

            } else {

                try {
                    $pdo->beginTransaction();

                    // Lock certificate record
                    $check = $pdo->prepare("
                        SELECT
                            ec.id,
                            ec.certificate_no,
                            ec.revoked_at,
                            dr.reference_no,
                            u.full_name,
                            dt.document_name
                        FROM e_certificates ec
                        INNER JOIN document_requests dr
                            ON ec.request_id = dr.id
                        INNER JOIN users u
                            ON dr.user_id = u.id
                        INNER JOIN document_types dt
                            ON dr.document_type_id = dt.id
                        WHERE ec.id = :id
                        FOR UPDATE
                    ");

                    $check->execute([
                        'id' => $certificate_id
                    ]);

                    $certificate = $check->fetch(PDO::FETCH_ASSOC);

                    if (!$certificate) {
                        throw new Exception(
                            "Certificate not found."
                        );
                    }

                    if ($certificate['revoked_at'] !== null) {
                        throw new Exception(
                            "This certificate has already been revoked."
                        );
                    }

                    // Revoke certificate
                    $update = $pdo->prepare("
                        UPDATE e_certificates
                        SET revoked_at = NOW()
                        WHERE id = :id
                          AND revoked_at IS NULL
                    ");

                    $update->execute([
                        'id' => $certificate_id
                    ]);

                    if ($update->rowCount() !== 1) {
                        throw new Exception(
                            "Unable to revoke certificate. Please try again."
                        );
                    }

                    // AUTOMATIC ACTIVITY LOG
                    $log = $pdo->prepare("
                        INSERT INTO admin_activity_logs
                        (
                            admin_id,
                            action,
                            description
                        )
                        VALUES
                        (
                            :admin_id,
                            :action,
                            :description
                        )
                    ");

                    $log_description =
                        "Certificate No.: " .
                        $certificate['certificate_no'] .
                        " | Reference No.: " .
                        $certificate['reference_no'] .
                        " | Resident: " .
                        $certificate['full_name'] .
                        " | Document: " .
                        $certificate['document_name'] .
                        " | Status: Revoked";

                    $log->execute([
                        'admin_id' => $_SESSION['user_id'],
                        'action' => 'E-Certificate Revoked',
                        'description' => $log_description
                    ]);

                    $pdo->commit();

                    $message = "Certificate successfully revoked! Certificate No.: " .
                        $certificate['certificate_no'];

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $error = $e instanceof PDOException
                        ? "Unable to revoke certificate. Please try again."
                        : $e->getMessage();
                }
            }

        } else {
            $error = "Invalid action.";
        }
    }
}

// =====================================
// APPROVED REQUESTS WITHOUT CERTIFICATES
// =====================================
$stmt = $pdo->query("
    SELECT
        dr.id,
        dr.reference_no,
        dr.requested_at,
        u.full_name,
        dt.document_name
    FROM document_requests dr
    INNER JOIN users u ON dr.user_id = u.id
    INNER JOIN document_types dt
        ON dr.document_type_id = dt.id
    LEFT JOIN e_certificates ec ON ec.request_id = dr.id
    WHERE dr.status = 'Approved'
      AND ec.id IS NULL
    ORDER BY dr.requested_at ASC
");

$approved_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================
// ALL ISSUED CERTIFICATES
// =====================================
$stmt = $pdo->query("
    SELECT
        ec.id,
        ec.certificate_no,
        ec.issued_at,
        ec.revoked_at,
        dr.reference_no,
        u.full_name,
        dt.document_name
    FROM e_certificates ec
    INNER JOIN document_requests dr
        ON ec.request_id = dr.id
    INNER JOIN users u ON dr.user_id = u.id
    INNER JOIN document_types dt
        ON dr.document_type_id = dt.id
    ORDER BY ec.issued_at DESC
");

$issued_certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_issued = count($issued_certificates);
$total_available = count($approved_requests);

$total_valid = 0;
$total_revoked = 0;

foreach ($issued_certificates as $certificate) {
    if ($certificate['revoked_at'] === null) {
        $total_valid++;
    } else {
        $total_revoked++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Issue E-Certificates | Barangay Cawit</title>

<style>
* {
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    margin: 0;
    background: #f1f5f9;
    color: #1e293b;
}

.header {
    background: #123b5d;
    color: white;
    padding: 15px 5%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand img {
    width: 55px;
    height: 55px;
    object-fit: contain;
    background: white;
    border-radius: 50%;
    padding: 3px;
}

.brand h1 {
    margin: 0;
    font-size: 20px;
}

.brand small {
    display: block;
    margin-top: 4px;
    color: #dbeafe;
}

.back {
    background: #126b62;
    color: white;
    padding: 11px 16px;
    border-radius: 7px;
    text-decoration: none;
    font-weight: bold;
}

.container {
    max-width: 1250px;
    margin: 30px auto;
    padding: 0 20px;
}

.welcome {
    background: linear-gradient(120deg, #123b5d, #126b62);
    color: white;
    padding: 28px;
    border-radius: 14px;
    margin-bottom: 25px;
}

.welcome h2 {
    margin-top: 0;
}

.welcome p {
    line-height: 1.6;
}

.message, .error {
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow-wrap: anywhere;
}

.message {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 22px;
    border-radius: 12px;
    border-left: 5px solid #126b62;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
}

.stat-card h3 {
    margin: 0 0 12px;
    color: #64748b;
    font-size: 14px;
}

.stat-card p {
    margin: 0;
    color: #123b5d;
    font-size: 30px;
    font-weight: bold;
}

.section {
    background: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
}

.section h2 {
    margin-top: 0;
    color: #123b5d;
}

.request {
    padding: 20px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    margin-bottom: 15px;
}

.request h3 {
    color: #123b5d;
    margin-top: 0;
}

.request p {
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.issue-button {
    padding: 12px 18px;
    background: #126b62;
    color: white;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-weight: bold;
}

.issue-button:hover {
    background: #0d514b;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 950px;
}

th, td {
    padding: 13px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

th {
    background: #eaf1f8;
    color: #123b5d;
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

.revoke-button {
    padding: 9px 13px;
    background: #b91c1c;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
}

.revoke-button:hover {
    background: #991b1b;
}

.no-action {
    color: #94a3b8;
    font-size: 13px;
}

.empty {
    text-align: center;
    padding: 25px;
    color: #64748b;
}

footer {
    text-align: center;
    padding: 30px 15px;
    color: #64748b;
    font-size: 13px;
}

@media(max-width: 600px) {
    .header {
        padding: 15px;
    }

    .brand h1 {
        font-size: 17px;
    }

    .container {
        padding: 0 12px;
    }

    .welcome {
        padding: 22px;
    }

    .section {
        padding: 16px;
    }
}
</style>
</head>

<body>

<header class="header">

    <div class="brand">
        <img src="../assets/barangay_cawit_logo.png"
             alt="Barangay Cawit Logo">

        <div>
            <h1>BARANGAY CAWIT</h1>
            <small>Manito, Albay | Admin Portal</small>
        </div>
    </div>

    <a href="dashboard.php" class="back">Back to Dashboard</a>

</header>

<main class="container">

    <section class="welcome">
        <h2>Issue and Manage E-Certificates</h2>
        <p>
            Manage approved document requests, issue verifiable
            electronic certificates, and revoke certificates when necessary.
        </p>
    </section>

    <?php if ($message): ?>
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <section class="stats">

        <div class="stat-card">
            <h3>Approved Requests Available</h3>
            <p><?= $total_available ?></p>
        </div>

        <div class="stat-card">
            <h3>Total Issued Certificates</h3>
            <p><?= $total_issued ?></p>
        </div>

        <div class="stat-card">
            <h3>Valid Certificates</h3>
            <p><?= $total_valid ?></p>
        </div>

        <div class="stat-card">
            <h3>Revoked Certificates</h3>
            <p><?= $total_revoked ?></p>
        </div>

    </section>

    <!-- APPROVED REQUESTS -->
    <section class="section">

        <h2>Approved Requests</h2>

        <?php if ($total_available > 0): ?>

            <?php foreach ($approved_requests as $request): ?>

                <div class="request">

                    <h3>
                        <?= htmlspecialchars($request['document_name']) ?>
                    </h3>

                    <p>
                        <strong>Resident:</strong>
                        <?= htmlspecialchars($request['full_name']) ?>
                    </p>

                    <p>
                        <strong>Reference No.:</strong>
                        <?= htmlspecialchars($request['reference_no']) ?>
                    </p>

                    <p>
                        <strong>Date Requested:</strong>
                        <?= htmlspecialchars($request['requested_at']) ?>
                    </p>

                    <form method="POST"
                          onsubmit="return confirm('Are you sure you want to issue this certificate?');">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <input type="hidden"
                               name="action"
                               value="issue">

                        <input type="hidden"
                               name="request_id"
                               value="<?= (int)$request['id'] ?>">

                        <button class="issue-button" type="submit">
                            Issue Certificate
                        </button>

                    </form>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty">
                <h3>No Approved Requests Available</h3>
                <p>
                    Wala pang approved request na maaaring
                    bigyan ng bagong certificate.
                </p>
            </div>

        <?php endif; ?>

    </section>

    <!-- ISSUED CERTIFICATES -->
    <section class="section">

        <h2>Issued Certificates</h2>

        <?php if ($total_issued > 0): ?>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Certificate No.</th>
                            <th>Resident</th>
                            <th>Document</th>
                            <th>Reference No.</th>
                            <th>Date Issued</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($issued_certificates as $certificate): ?>

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
                                    <?php if ($certificate['revoked_at'] === null): ?>

                                        <span class="status valid">Valid</span>

                                    <?php else: ?>

                                        <span class="status revoked">Revoked</span>

                                    <?php endif; ?>
                                </td>

                                <td>

                                    <?php if ($certificate['revoked_at'] === null): ?>

                                        <form method="POST"
                                              onsubmit="return confirm('WARNING: Are you sure you want to REVOKE this certificate? It will become INVALID and cannot be verified as authentic.');">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="revoke">

                                            <input type="hidden"
                                                   name="certificate_id"
                                                   value="<?= (int)$certificate['id'] ?>">

                                            <button type="submit" class="revoke-button">
                                                Revoke Certificate
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span class="no-action">
                                            Already Revoked
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty">
                <p>Wala pang na-issue na e-Certificate.</p>
            </div>

        <?php endif; ?>

    </section>

</main>

<footer>
    <strong>BARANGAY CAWIT</strong><br>
    Municipality of Manito, Province of Albay<br>
    Barangay Document Request and Verifiable E-Certificate System
</footer>

</body>
</html>
