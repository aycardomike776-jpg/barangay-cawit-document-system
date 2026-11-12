
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

// Kunin ang impormasyon ng resident
$stmt = $pdo->prepare("
    SELECT id, full_name, email, contact_number, address
    FROM users
    WHERE id = ? AND role = 'resident'
    LIMIT 1
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    exit("Resident account not found.");
}

// I-save ang updated profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($full_name === '' || $email === '') {
        $error = "Full Name and Email Address are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {

        // Tingnan kung ginagamit na ng ibang account ang email
        $check = $pdo->prepare("
            SELECT id FROM users
            WHERE email = ? AND id != ?
            LIMIT 1
        ");
        $check->execute([$email, $user_id]);

        if ($check->fetch()) {
            $error = "This email address is already used by another account.";
        } else {

            $update = $pdo->prepare("
                UPDATE users
                SET full_name = ?,
                    email = ?,
                    contact_number = ?,
                    address = ?
                WHERE id = ? AND role = 'resident'
            ");

            $update->execute([
                $full_name,
                $email,
                $contact_number,
                $address,
                $user_id
            ]);

            // I-update rin ang session name kung ginagamit ito sa dashboard
            $_SESSION['full_name'] = $full_name;

            // Kunin ulit ang updated information
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $success = "Profile updated successfully!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Profile | Barangay Cawit</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 30px 15px;
    min-height: 100vh;
    font-family: Arial, sans-serif;
    background: #eef3f8;
    color: #1e293b;
}

.container {
    width: 100%;
    max-width: 650px;
    margin: 30px auto;
    background: white;
    padding: 30px;
    border-radius: 14px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}

.header {
    text-align: center;
    margin-bottom: 25px;
}

.header img {
    width: 85px;
    height: 85px;
    object-fit: contain;
}

h1 {
    color: #123b68;
    margin: 10px 0;
}

.subtitle {
    color: #64748b;
    font-size: 14px;
}

label {
    display: block;
    margin: 17px 0 7px;
    font-weight: bold;
    color: #334155;
}

input, textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 15px;
    font-family: Arial, sans-serif;
}

textarea {
    min-height: 90px;
    resize: vertical;
}

input:focus, textarea:focus {
    outline: none;
    border-color: #126b62;
    box-shadow: 0 0 0 2px rgba(18,107,98,0.12);
}

button, .back-button {
    display: inline-block;
    padding: 12px 20px;
    border: none;
    border-radius: 7px;
    background: #126b62;
    color: white;
    font-size: 14px;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
}

button {
    width: 100%;
    margin-top: 22px;
}

button:hover, .back-button:hover {
    background: #0d514b;
}

.back-area {
    text-align: center;
    margin-top: 18px;
}

.success {
    padding: 12px;
    background: #dcfce7;
    color: #166534;
    border-radius: 7px;
    margin-bottom: 15px;
}

.error {
    padding: 12px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 7px;
    margin-bottom: 15px;
}

.note {
    margin-top: 18px;
    color: #64748b;
    font-size: 12px;
    text-align: center;
}

@media (max-width: 500px) {
    .container {
        padding: 20px;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="header">
        <img src="../assets/barangay_cawit_logo.png"
             alt="Barangay Cawit Logo">

        <h1>My Profile</h1>

        <p class="subtitle">
            Barangay Cawit, Manito, Albay
        </p>
    </div>

    <?php if ($success !== ""): ?>
        <div class="success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <label>Full Name *</label>
        <input
            type="text"
            name="full_name"
            value="<?= htmlspecialchars($user['full_name'] ?? '') ?>"
            required
        >

        <label>Email Address *</label>
        <input
            type="email"
            name="email"
            value="<?= htmlspecialchars($user['email'] ?? '') ?>"
            required
        >

        <label>Contact Number</label>
        <input
            type="text"
            name="contact_number"
            value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>"
        >

        <label>Complete Address</label>
        <textarea name="address"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>

        <button type="submit">Save Profile Changes</button>

    </form>

    <div class="back-area">
        <a class="back-button" href="dashboard.php">
            Back to Dashboard
        </a>
    </div>

    <p class="note">
        Your account information is managed by the
        Barangay Cawit Document System.
    </p>

</div>

</body>
</html>
