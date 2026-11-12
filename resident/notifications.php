
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Resident access protection
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'resident'
) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$message = '';
$error = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Mark notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = "Invalid request. Please try again.";
    } else {
        $notification_id = filter_input(
            INPUT_POST,
            'notification_id',
            FILTER_VALIDATE_INT
        );

        if ($notification_id) {
            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notification_id, $user_id]);

            $message = "Notification marked as read.";
        } else {
            $error = "Invalid notification.";
        }
    }
}

// Get notifications
$stmt = $pdo->prepare("
    SELECT id, title, message, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread notifications
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = ? AND is_read = 0
");
$stmt->execute([$user_id]);
$unread_count = (int) $stmt->fetchColumn();

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications | Barangay Cawit</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #263445;
        }

        header {
            background: #123b70;
            color: white;
            padding: 18px 25px;
        }

        .header-content {
            max-width: 1100px;
            margin: auto;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 65px;
            height: 65px;
            object-fit: contain;
            background: white;
            border-radius: 50%;
            padding: 5px;
        }

        header h1 {
            margin: 0 0 5px;
            font-size: 23px;
        }

        header p {
            margin: 0;
            font-size: 13px;
        }

        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 18px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        h2 {
            margin: 0;
            color: #123b70;
        }

        .back-button {
            display: inline-block;
            padding: 10px 16px;
            background: #123b70;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
        }

        .summary {
            background: white;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }

        .summary strong {
            color: #123b70;
            font-size: 24px;
        }

        .notification {
            background: white;
            border-left: 5px solid #b8c5d6;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 13px;
            box-shadow: 0 2px 7px rgba(0,0,0,.05);
        }

        .notification.unread {
            border-left-color: #16834a;
            background: #fafffc;
        }

        .notification h3 {
            margin: 0 0 8px;
            color: #123b70;
            font-size: 17px;
        }

        .notification p {
            margin: 0 0 12px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .notification small {
            color: #687587;
        }

        .status {
            display: inline-block;
            font-size: 12px;
            font-weight: bold;
            padding: 5px 9px;
            border-radius: 20px;
            margin-bottom: 10px;
            background: #e5eaf0;
            color: #465365;
        }

        .unread .status {
            background: #d8f3e3;
            color: #176b3b;
        }

        .read-button {
            display: block;
            margin-top: 12px;
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            background: #16834a;
            color: white;
            cursor: pointer;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .success {
            background: #d9f4e2;
            color: #176b3b;
        }

        .error {
            background: #ffe0e0;
            color: #a32121;
        }

        .empty {
            background: white;
            padding: 45px 20px;
            text-align: center;
            border-radius: 10px;
            color: #687587;
        }

        footer {
            text-align: center;
            padding: 20px;
            color: #687587;
            font-size: 12px;
        }

        @media (max-width: 600px) {
            header h1 {
                font-size: 18px;
            }

            .logo {
                width: 52px;
                height: 52px;
            }

            .container {
                margin-top: 20px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="header-content">
        <img
            src="../assets/barangay_cawit_logo.png"
            alt="Barangay Cawit Logo"
            class="logo"
        >

        <div>
            <h1>BARANGAY CAWIT</h1>
            <p>Manito, Albay | Resident Portal</p>
        </div>
    </div>
</header>

<main class="container">

    <div class="top-bar">
        <h2>My Notifications</h2>
        <a href="dashboard.php" class="back-button">
            Back to Dashboard
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="summary">
        <strong><?= $unread_count ?></strong>
        <div>Unread Notifications</div>
    </div>

    <?php if (count($notifications) > 0): ?>

        <?php foreach ($notifications as $notification): ?>

            <div class="notification <?= !$notification['is_read'] ? 'unread' : '' ?>">

                <span class="status">
                    <?= $notification['is_read'] ? 'READ' : 'UNREAD' ?>
                </span>

                <h3><?= e($notification['title']) ?></h3>

                <p><?= nl2br(e($notification['message'])) ?></p>

                <small>
                    Received:
                    <?= date('F d, Y - h:i A', strtotime($notification['created_at'])) ?>
                </small>

                <?php if (!$notification['is_read']): ?>
                    <form method="POST">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['csrf_token']) ?>"
                        >

                        <input
                            type="hidden"
                            name="notification_id"
                            value="<?= (int) $notification['id'] ?>"
                        >

                        <button type="submit" class="read-button">
                            Mark as Read
                        </button>
                    </form>
                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="empty">
            <h3>No Notifications Yet</h3>
            <p>You will see updates about your document requests and e-certificates here.</p>
        </div>

    <?php endif; ?>

</main>

<footer>
    Barangay Document Request and Verifiable E-Certificate System
    <br>
    Municipality of Manito, Province of Albay
</footer>

</body>
</html>
