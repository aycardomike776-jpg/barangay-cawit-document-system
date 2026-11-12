
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

// Get admin activity logs
$stmt = $pdo->query("
    SELECT
        al.id,
        al.admin_id,
        al.action,
        al.description,
        al.created_at,
        u.full_name
    FROM admin_activity_logs al
    LEFT JOIN users u ON al.admin_id = u.id
    ORDER BY al.created_at DESC
");

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Barangay Cawit | Admin Activity Logs</title>

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
    font-size: 20px;
    margin: 0;
}

.brand small {
    display: block;
    margin-top: 4px;
    color: #dbeafe;
}

.back {
    background: #126b62;
    color: white;
    padding: 11px 18px;
    border-radius: 7px;
    text-decoration: none;
    font-weight: bold;
}

.container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}

.intro {
    background: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 22px;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
}

.intro h2 {
    color: #123b5d;
    margin-top: 0;
}

.intro p {
    color: #64748b;
    line-height: 1.6;
}

.summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.summary-card {
    background: white;
    padding: 22px;
    border-radius: 12px;
    border-left: 5px solid #126b62;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
}

.summary-card h3 {
    color: #64748b;
    font-size: 14px;
    margin: 0 0 10px;
}

.summary-card p {
    color: #123b5d;
    font-size: 28px;
    font-weight: bold;
    margin: 0;
}

.logs-section {
    background: white;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
    overflow-x: auto;
}

.logs-section h2 {
    color: #123b5d;
    margin-top: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th, td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

th {
    background: #eaf1f8;
    color: #123b5d;
}

.action {
    display: inline-block;
    background: #dbeafe;
    color: #1e40af;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.empty {
    text-align: center;
    padding: 35px;
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

    .container {
        padding: 0 12px;
    }

    .brand h1 {
        font-size: 17px;
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

    <a href="dashboard.php" class="back">
        Back to Dashboard
    </a>

</header>

<main class="container">

    <section class="intro">
        <h2>Admin Activity Logs</h2>

        <p>
            Monitor administrative activities performed in the
            Barangay Cawit Document Request and Verifiable
            E-Certificate System.
        </p>
    </section>

    <section class="summary">

        <div class="summary-card">
            <h3>Total Activity Records</h3>
            <p><?= count($logs) ?></p>
        </div>

    </section>

    <section class="logs-section">

        <h2>Activity History</h2>

        <?php if (count($logs) > 0): ?>

        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Administrator</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Date and Time</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($logs as $log): ?>

                <tr>
                    <td><?= (int)$log['id'] ?></td>

                    <td>
                        <?= htmlspecialchars(
                            $log['full_name'] ?? 'Unknown Administrator'
                        ) ?>
                    </td>

                    <td>
                        <span class="action">
                            <?= htmlspecialchars($log['action']) ?>
                        </span>
                    </td>

                    <td>
                        <?= htmlspecialchars($log['description']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($log['created_at']) ?>
                    </td>
                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>

        <?php else: ?>

        <div class="empty">
            <h3>No Activity Records Yet</h3>
            <p>
                Wala pang recorded administrator activities.
            </p>
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
