
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header("Location: ../login.php");
    exit;
}

// Request statistics
$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'Under Review') AS under_review,
        SUM(status = 'Approved') AS approved,
        SUM(status = 'Rejected') AS rejected,
        SUM(status = 'Completed') AS completed
    FROM document_requests
");

$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Recent requests
$stmt = $pdo->query("
    SELECT
        dr.reference_no,
        dr.purpose,
        dr.status,
        dr.requested_at,
        u.full_name,
        dt.document_name
    FROM document_requests dr
    INNER JOIN users u ON dr.user_id = u.id
    INNER JOIN document_types dt ON dr.document_type_id = dt.id
    ORDER BY dr.requested_at DESC
    LIMIT 10
");

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Barangay Cawit | Admin Dashboard</title>

<link rel="stylesheet" href="../assets/christmas-theme.css">

<style>
* {
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    margin: 0;
    background: linear-gradient(135deg, #f4fbff, #e4f5ff, #ffffff);
    color: #263b4a;
}

/* HEADER */
.header {
    position: relative;
    overflow: hidden;
    background: linear-gradient(110deg, #123b5d, #176b83, #25866b);
    color: white;
    padding: 20px 5%;
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
    position: relative;
    z-index: 2;
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
    color: white;
}

.brand small {
    display: block;
    margin-top: 4px;
    color: #dbeafe;
}

.logout {
    position: relative;
    z-index: 2;
    background: #c9364b;
    color: white;
    padding: 11px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    transition: .3s;
}

.logout:hover {
    background: #a9273a;
    transform: translateY(-2px);
}

/* CHRISTMAS LIGHTS */
.christmas-lights {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 18px;
    border-top: 3px solid #b7d9e8;
    background:
        radial-gradient(circle, #ff5964 4px, transparent 5px)
        0 0 / 55px 15px repeat-x,
        radial-gradient(circle, #ffe17a 4px, transparent 5px)
        18px 0 / 55px 15px repeat-x,
        radial-gradient(circle, #55d6a1 4px, transparent 5px)
        36px 0 / 55px 15px repeat-x;
    pointer-events: none;
}

.container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}

/* WELCOME BANNER */
.welcome {
    background: linear-gradient(120deg, #dff4ff, #ffffff 55%, #c9ebff);
    color: #174b70;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 25px;
    border: 1px solid #c6e7f7;
    box-shadow: 0 5px 18px rgba(75, 151, 190, .13);
    overflow: hidden;
}

.welcome-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.welcome h2 {
    margin-top: 0;
    color: #174b70;
    font-size: 27px;
}

.welcome p {
    line-height: 1.6;
    color: #315b75;
}

.welcome-tree {
    font-size: 75px;
    animation: tree-sway 3s ease-in-out infinite;
    filter: drop-shadow(0 5px 5px rgba(23, 75, 112, .15));
}

.greeting {
    font-weight: bold;
    color: #25866b !important;
}

/* SECTION TITLES */
.section-title {
    color: #174b70;
    margin: 25px 0 15px;
}

/* STATISTICS */
.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px;
}

.stat-card {
    background: white;
    padding: 22px;
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(63, 133, 170, .10);
    border: 1px solid #d8edf8;
    border-left: 5px solid #65bce8;
    transition: .3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(63, 133, 170, .17);
}

.stat-card h3 {
    color: #647f91;
    font-size: 14px;
    margin: 0 0 12px;
}

.stat-card p {
    color: #174b70;
    font-size: 30px;
    font-weight: bold;
    margin: 0;
}

/* ADMIN SERVICES */
.shortcuts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px;
}

.shortcut {
    background: white;
    padding: 22px;
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(63, 133, 170, .10);
    border: 1px solid #d8edf8;
    transition: .3s;
}

.shortcut:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(63, 133, 170, .17);
}

.shortcut .icon {
    font-size: 35px;
}

.shortcut h3 {
    color: #174b70;
    margin: 12px 0 8px;
}

.shortcut p {
    color: #647f91;
    line-height: 1.5;
    min-height: 42px;
}

.shortcut a {
    display: inline-block;
    background: #2586a8;
    color: white;
    padding: 10px 15px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    transition: .3s;
}

.shortcut a:hover {
    background: #174b70;
    transform: translateY(-2px);
}

/* RECENT REQUESTS */
.section {
    background: white;
    padding: 22px;
    border-radius: 14px;
    margin-top: 25px;
    box-shadow: 0 3px 12px rgba(63, 133, 170, .10);
    border: 1px solid #d8edf8;
    overflow-x: auto;
}

.section h2 {
    margin-top: 0;
    color: #174b70;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th, td {
    padding: 13px;
    text-align: left;
    border-bottom: 1px solid #e2edf3;
}

th {
    background: #dff3ff;
    color: #174b70;
}

tr:hover td {
    background: #f5fbff;
}

/* STATUS LABELS */
.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    background: #e2e8f0;
    color: #334155;
}

.status.pending {
    background: #fef3c7;
    color: #92400e;
}

.status.approved,
.status.completed {
    background: #dcfce7;
    color: #166534;
}

.status.rejected {
    background: #fee2e2;
    color: #991b1b;
}

.status.under-review {
    background: #dbeafe;
    color: #1e40af;
}

.empty {
    text-align: center;
    padding: 25px;
    color: #647f91;
}

/* FOOTER */
footer {
    text-align: center;
    padding: 30px 15px;
    color: #647f91;
    font-size: 13px;
    background: rgba(223, 243, 255, .65);
    margin-top: 35px;
}

/* TREE ANIMATION */
@keyframes tree-sway {
    0%, 100% {
        transform: rotate(-3deg);
    }
    50% {
        transform: rotate(3deg);
    }
}

/* SNOWFALL */
.snowflake {
    position: fixed;
    top: -30px;
    z-index: 9999;
    color: #ffffff;
    text-shadow: 0 0 5px #79bfe5;
    pointer-events: none;
    animation: snowfall linear infinite;
}

@keyframes snowfall {
    0% {
        transform: translateY(-10vh) rotate(0deg);
        opacity: .9;
    }
    100% {
        transform: translateY(110vh) rotate(360deg);
        opacity: .2;
    }
}

@keyframes winter-glow {
    0%, 100% {
        box-shadow: 0 0 0 rgba(101, 188, 232, 0);
    }
    50% {
        box-shadow: 0 0 12px rgba(101, 188, 232, .35);
    }
}

button, .shortcut a {
    transition: all .25s ease;
}

.shortcut a {
    animation: winter-glow 3s ease-in-out infinite;
}

/* MOBILE */
@media(max-width: 600px) {
    .header {
        padding: 18px 15px;
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

    .welcome h2 {
        font-size: 22px;
    }

    .welcome-tree {
        font-size: 48px;
    }
}

/* ACCESSIBILITY */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .01ms !important;
    }
}
</style>
</head>

<body>

<header class="header">

    <div class="christmas-lights" aria-hidden="true"></div>

    <div class="brand">
        <img src="../assets/barangay_cawit_logo.png"
             alt="Barangay Cawit Logo">

        <div>
            <h1>BARANGAY CAWIT</h1>
            <small>Manito, Albay | Admin Portal</small>
        </div>
    </div>

    <a href="../logout.php" class="logout">Logout</a>

</header>

<main class="container">

    <section class="welcome">

        <div class="welcome-content">

            <div>
                <h2>Welcome, Administrator! ❄️</h2>

                <p>
                    Manage and monitor the Barangay Cawit Document Request
                    and Verifiable E-Certificate System.
                </p>

                <p>
                    Review resident requests, issue electronic certificates,
                    and monitor document application status.
                </p>
            </div>

            <div class="welcome-tree" aria-hidden="true">🎄</div>

        </div>

        <p class="greeting">
            ❄️ Season's Greetings from Barangay Cawit! 🎁
        </p>

    </section>

    <h2 class="section-title">📊 Request Statistics</h2>

    <section class="stats">

        <div class="stat-card">
            <h3>Total Requests</h3>
            <p><?= (int)($stats['total'] ?? 0) ?></p>
        </div>

        <div class="stat-card">
            <h3>Pending</h3>
            <p><?= (int)($stats['pending'] ?? 0) ?></p>
        </div>

        <div class="stat-card">
            <h3>Under Review</h3>
            <p><?= (int)($stats['under_review'] ?? 0) ?></p>
        </div>

        <div class="stat-card">
            <h3>Approved</h3>
            <p><?= (int)($stats['approved'] ?? 0) ?></p>
        </div>

        <div class="stat-card">
            <h3>Rejected</h3>
            <p><?= (int)($stats['rejected'] ?? 0) ?></p>
        </div>

        <div class="stat-card">
            <h3>Completed</h3>
            <p><?= (int)($stats['completed'] ?? 0) ?></p>
        </div>

    </section>

    <h2 class="section-title">🎄 Admin Services</h2>

    <section class="shortcuts">

        <div class="shortcut">
            <div class="icon">📋</div>
            <h3>Manage Requests</h3>
            <p>Review and update resident document applications.</p>
            <a href="manage_requests.php">Manage Requests</a>
        </div>

        <div class="shortcut">
            <div class="icon">📜</div>
            <h3>Issue E-Certificate</h3>
            <p>Generate electronic certificates for approved requests.</p>
            <a href="issue_certificate.php">Issue Certificate</a>
        </div>

        <div class="shortcut">
            <div class="icon">🔍</div>
            <h3>Verify Certificates</h3>
            <p>Open the certificate verification page.</p>
            <a href="../verification/index.php">Verify Certificate</a>
        </div>

        <div class="shortcut">
            <div class="icon">📚</div>
            <h3>Certificate History</h3>
            <p>View all issued certificates, including valid and revoked certificates.</p>
            <a href="certificate_history.php">View History</a>
        </div>

        <div class="shortcut">
            <div class="icon">📝</div>
            <h3>Admin Activity Logs</h3>
            <p>Monitor and review administrative activities performed in the system.</p>
            <a href="activity_logs.php">View Activity Logs</a>
        </div>

    </section>

    <section class="section">

        <h2>❄️ Recent Document Requests</h2>

        <?php if (count($requests) > 0): ?>

        <table>
            <thead>
                <tr>
                    <th>Reference No.</th>
                    <th>Resident</th>
                    <th>Document</th>
                    <th>Purpose</th>
                    <th>Date Requested</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($requests as $request): ?>

                <?php
                $statusClass = strtolower(
                    str_replace(' ', '-', $request['status'])
                );
                ?>

                <tr>
                    <td><?= htmlspecialchars($request['reference_no']) ?></td>
                    <td><?= htmlspecialchars($request['full_name']) ?></td>
                    <td><?= htmlspecialchars($request['document_name']) ?></td>
                    <td><?= htmlspecialchars($request['purpose']) ?></td>
                    <td><?= htmlspecialchars($request['requested_at']) ?></td>

                    <td>
                        <span class="status <?= htmlspecialchars($statusClass) ?>">
                            <?= htmlspecialchars($request['status']) ?>
                        </span>
                    </td>
                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>

        <?php else: ?>

        <div class="empty">
            <h3>No Document Requests Yet</h3>
            <p>Wala pang requests mula sa mga resident.</p>
        </div>

        <?php endif; ?>

    </section>

</main>

<footer>
    <strong>🎄 BARANGAY CAWIT 🎄</strong><br>
    Municipality of Manito, Province of Albay<br>
    Barangay Document Request and Verifiable E-Certificate System<br>
    <span>❄️ Merry Christmas and Happy Holidays! ❄️</span>
</footer>

<script>
/* Decorative snowfall only */
(function () {

    const symbols = ["❄", "❅", "❆"];
    const snowCount = 24;

    for (let i = 0; i < snowCount; i++) {

        const snow = document.createElement("span");

        snow.className = "snowflake";

        snow.textContent =
            symbols[Math.floor(Math.random() * symbols.length)];

        snow.style.left = Math.random() * 100 + "vw";

        snow.style.fontSize =
            (10 + Math.random() * 14) + "px";

        snow.style.animationDuration =
            (8 + Math.random() * 12) + "s";

        snow.style.animationDelay =
            (-Math.random() * 15) + "s";

        document.body.appendChild(snow);
    }

})();
</script>

</body>
</html>
