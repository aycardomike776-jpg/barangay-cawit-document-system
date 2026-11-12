
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "resident"
) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$full_name = $_SESSION["full_name"] ?? "Resident";

// Count unread notifications
$unread_count = 0;

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $unread_count = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $unread_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Resident Dashboard | Barangay Cawit</title>

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

header {
    position: relative;
    overflow: hidden;
    background: linear-gradient(110deg, #123b5d, #176b83, #25866b);
    color: white;
    padding: 20px 6%;
    display: flex;
    justify-content: space-between;
    align-items: center;
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

.brand h2 {
    margin: 0;
    font-size: 19px;
    color: white;
}

.brand p {
    margin: 4px 0 0;
    font-size: 12px;
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

/* MAIN CONTAINER */

.container {
    max-width: 1150px;
    margin: 35px auto;
    padding: 0 20px;
}

/* WELCOME BANNER */

.welcome {
    position: relative;
    overflow: hidden;
    background: linear-gradient(120deg, #dff4ff, #ffffff 55%, #c9ebff);
    color: #174b70;
    padding: 38px;
    border-radius: 16px;
    margin-bottom: 28px;
    border: 1px solid #c6e7f7;
    box-shadow: 0 8px 22px rgba(75, 151, 190, .13);
}

.welcome-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.welcome .small-title {
    color: #2586a8;
    letter-spacing: 2px;
    font-size: 12px;
    font-weight: bold;
}

.welcome h1 {
    margin: 12px 0;
    font-size: 28px;
    color: #174b70;
}

.welcome p {
    line-height: 1.7;
    max-width: 700px;
    color: #315b75;
}

.welcome-tree {
    font-size: 75px;
    animation: tree-sway 3s ease-in-out infinite;
    filter: drop-shadow(0 5px 5px rgba(23, 75, 112, .15));
}

.greeting {
    color: #25866b !important;
    font-weight: bold;
    margin-bottom: 0;
}

/* SECTION TITLE */

.section-title {
    color: #174b70;
    margin: 30px 0 18px;
}

/* SERVICE CARDS */

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
}

.card {
    position: relative;
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(63, 133, 170, .10);
    border: 1px solid #d8edf8;
    transition: transform .3s, box-shadow .3s;
}

.card:hover {
    transform: translateY(-6px);
    box-shadow: 0 9px 22px rgba(63, 133, 170, .17);
}

.card-icon {
    width: 50px;
    height: 50px;
    border-radius: 13px;
    background: #e0f4ff;
    border: 1px solid #d1eaf7;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 26px;
    margin-bottom: 15px;
}

.card h3 {
    color: #174b70;
    margin: 8px 0;
}

.card p {
    color: #647f91;
    line-height: 1.6;
    min-height: 48px;
}

.card a {
    display: inline-block;
    margin-top: 12px;
    background: #2586a8;
    color: white;
    padding: 11px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    transition: .3s;
    animation: winter-glow 3s ease-in-out infinite;
}

.card a:hover {
    background: #174b70;
    transform: translateY(-2px);
}

/* NOTIFICATION BADGE */

.notification-badge {
    position: absolute;
    top: 18px;
    right: 18px;
    background: #c9364b;
    color: white;
    min-width: 27px;
    height: 27px;
    padding: 4px 8px;
    border-radius: 20px;
    text-align: center;
    font-size: 13px;
    font-weight: bold;
    box-shadow: 0 2px 7px rgba(201, 54, 75, .25);
}

/* FOOTER */

footer {
    text-align: center;
    padding: 30px 15px;
    color: #647f91;
    font-size: 13px;
    background: rgba(223, 243, 255, .65);
    margin-top: 35px;
    line-height: 1.8;
}

/* ANIMATIONS */

@keyframes tree-sway {
    0%, 100% {
        transform: rotate(-3deg);
    }

    50% {
        transform: rotate(3deg);
    }
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

.snowflake {
    position: fixed;
    top: -30px;
    z-index: 9999;
    color: white;
    text-shadow: 0 0 5px #79bfe5;
    pointer-events: none;
    animation: snowfall linear infinite;
}

@keyframes winter-glow {
    0%, 100% {
        box-shadow: 0 0 0 rgba(101, 188, 232, 0);
    }

    50% {
        box-shadow: 0 0 12px rgba(101, 188, 232, .35);
    }
}

/* MOBILE RESPONSIVE */

@media(max-width: 600px) {

    header {
        padding: 18px 15px;
    }

    .brand h2 {
        font-size: 16px;
    }

    .container {
        padding: 0 12px;
    }

    .welcome {
        padding: 25px;
    }

    .welcome h1 {
        font-size: 23px;
    }

    .welcome-tree {
        font-size: 48px;
    }

    .welcome-content {
        align-items: flex-start;
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

<header>

    <div class="christmas-lights" aria-hidden="true"></div>

    <div class="brand">

        <img src="../assets/barangay_cawit_logo.png"
             alt="Barangay Cawit Logo">

        <div>
            <h2>BARANGAY CAWIT</h2>
            <p>Manito, Albay</p>
        </div>

    </div>

    <a class="logout" href="../logout.php">Logout</a>

</header>

<main class="container">

    <section class="welcome">

        <div class="welcome-content">

            <div>

                <div class="small-title">
                    BARANGAY CAWIT ONLINE PORTAL ❄️
                </div>

                <h1>
                    Welcome,
                    <?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') ?>!
                </h1>

                <p>
                    Welcome to the Barangay Cawit Document Request
                    and Verifiable E-Certificate System.
                </p>

                <p>
                    You can request barangay documents, monitor your
                    applications, access your electronic certificates,
                    receive notifications, and manage your resident profile
                    through this portal.
                </p>

            </div>

            <div class="welcome-tree" aria-hidden="true">
                🎄
            </div>

        </div>

        <p class="greeting">
            ❄️ Season's Greetings from Barangay Cawit! 🎁
        </p>

    </section>

    <h2 class="section-title">
        ❄️ Resident Services
    </h2>

    <section class="cards">

        <div class="card">

            <div class="card-icon">📄</div>

            <h3>Request a Document</h3>

            <p>
                Submit an online request for an official
                barangay document.
            </p>

            <a href="../request_document.php">
                Request Now
            </a>

        </div>

        <div class="card">

            <div class="card-icon">📋</div>

            <h3>My Requests</h3>

            <p>
                Monitor the progress and status of your
                submitted document requests.
            </p>

            <a href="my_requests.php">
                View Requests
            </a>

        </div>

        <div class="card">

            <div class="card-icon">🏅</div>

            <h3>My E-Certificates</h3>

            <p>
                View your issued electronic certificates
                and their QR verification codes.
            </p>

            <a href="my_certificates.php">
                View Certificates
            </a>

        </div>

        <div class="card">

            <div class="card-icon">👤</div>

            <h3>My Profile</h3>

            <p>
                View and update your resident account
                information.
            </p>

            <a href="profile.php">
                My Profile
            </a>

        </div>

        <div class="card">

            <div class="card-icon">🔔</div>

            <?php if ($unread_count > 0): ?>

                <span class="notification-badge">
                    <?= $unread_count > 99 ? '99+' : $unread_count ?>
                </span>

            <?php endif; ?>

            <h3>My Notifications</h3>

            <p>
                Receive updates about your document requests,
                application status, and electronic certificates.
            </p>

            <a href="notifications.php">
                View Notifications
            </a>

        </div>

    </section>

</main>

<footer>

    <strong>🎄 BARANGAY CAWIT 🎄</strong><br>

    Municipality of Manito, Province of Albay<br>

    Barangay Document Request and Verifiable E-Certificate System<br>

    ❄️ Merry Christmas and Happy Holidays! ❄️

</footer>

<script>

// Decorative snowfall
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
