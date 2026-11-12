
<?php
session_start();
require_once __DIR__ . "/config/database.php";

$errors = [];
$full_name = "";
$email = "";
$contact_number = "";
$address = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $contact_number = trim($_POST["contact_number"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($full_name === "" || $email === "" || $password === "") {
        $errors[] = "Please complete all required fields.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetch()) {
            $errors[] = "This email is already registered.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $insert = $pdo->prepare(
                "INSERT INTO users
                (full_name, email, password_hash, role, contact_number, address)
                VALUES (?, ?, ?, 'resident', ?, ?)"
            );

            $insert->execute([
                $full_name,
                $email,
                $password_hash,
                $contact_number,
                $address
            ]);

            header("Location: login.php?registered=1");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Resident Registration | Barangay Cawit</title>

<link rel="stylesheet" href="assets/christmas-theme.css">

<style>

* {
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    margin: 0;
    min-height: 100vh;
    background: linear-gradient(135deg, #dff4ff, #bce5f8, #effaff);
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 35px 15px;
    overflow-x: hidden;
    position: relative;
}

/* CHRISTMAS LIGHTS */

.christmas-lights {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 28px;
    border-top: 3px solid #8aaebe;
    background:
        radial-gradient(circle, #e94c5d 5px, transparent 6px)
        0 0 / 65px 20px repeat-x,
        radial-gradient(circle, #f5ca58 5px, transparent 6px)
        22px 0 / 65px 20px repeat-x,
        radial-gradient(circle, #48a981 5px, transparent 6px)
        44px 0 / 65px 20px repeat-x;
    z-index: 10;
    pointer-events: none;
}

/* REGISTRATION CARD */

.container {
    width: 100%;
    max-width: 540px;
    position: relative;
    z-index: 2;
    background: rgba(255, 255, 255, .95);
    padding: 32px 38px;
    border-radius: 22px;
    border: 1px solid #c8e8f8;
    box-shadow: 0 15px 40px rgba(45, 110, 150, .18);
    backdrop-filter: blur(12px);
    animation: appear .7s ease;
}

@keyframes appear {
    from {
        opacity: 0;
        transform: translateY(25px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* DECORATIONS */

.tree-decoration {
    position: absolute;
    top: 15px;
    right: 18px;
    font-size: 35px;
    animation: sway 3s ease-in-out infinite;
}

.snow-decoration {
    position: absolute;
    top: 18px;
    left: 18px;
    font-size: 27px;
    animation: sway 4s ease-in-out infinite;
}

@keyframes sway {
    0%, 100% {
        transform: rotate(-5deg);
    }
    50% {
        transform: rotate(5deg);
    }
}

/* LOGO */

.logo-container {
    text-align: center;
    margin-bottom: 12px;
}

.logo-container img {
    width: 85px;
    height: 85px;
    object-fit: contain;
    background: white;
    border-radius: 50%;
    padding: 4px;
    border: 3px solid #d8edf8;
    box-shadow: 0 5px 15px rgba(63, 133, 170, .15);
}

/* BRAND */

.brand-name {
    text-align: center;
    color: #174b70;
    font-size: 21px;
    font-weight: bold;
    letter-spacing: 1px;
}

.location {
    text-align: center;
    color: #71899a;
    font-size: 13px;
    margin-top: 4px;
}

.decoration {
    text-align: center;
    font-size: 22px;
    margin: 13px 0;
}

/* HEADING */

h1 {
    text-align: center;
    color: #174b70;
    font-size: 25px;
    margin: 0 0 8px;
}

.subtitle {
    text-align: center;
    color: #71899a;
    font-size: 13px;
    line-height: 1.5;
    margin-bottom: 23px;
}

/* FORM */

label {
    display: block;
    margin: 14px 0 7px;
    font-weight: bold;
    color: #315b75;
    font-size: 14px;
}

input, textarea {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #c9dfea;
    border-radius: 9px;
    font-size: 14px;
    outline: none;
    background: #f8fcff;
    transition: .3s;
}

input:focus, textarea:focus {
    border-color: #65bce8;
    background: white;
    box-shadow: 0 0 0 3px rgba(101, 188, 232, .15);
}

textarea {
    resize: vertical;
    min-height: 75px;
}

.required {
    color: #c9364b;
}

/* REGISTER BUTTON */

button {
    width: 100%;
    margin-top: 23px;
    padding: 14px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(120deg, #2586a8, #174b70);
    color: white;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    transition: .3s;
    animation: glow 3s ease-in-out infinite;
}

button:hover {
    background: linear-gradient(120deg, #174b70, #25866b);
    transform: translateY(-3px);
    box-shadow: 0 8px 18px rgba(37, 134, 168, .25);
}

@keyframes glow {
    0%, 100% {
        box-shadow: 0 0 0 rgba(101, 188, 232, 0);
    }
    50% {
        box-shadow: 0 0 13px rgba(101, 188, 232, .4);
    }
}

/* ERRORS */

.errors {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px 18px;
    border-radius: 9px;
    margin-bottom: 17px;
    border: 1px solid #fecaca;
    font-size: 13px;
}

.errors p {
    margin: 5px 0;
    line-height: 1.5;
}

/* LOGIN LINK */

.login-link {
    text-align: center;
    margin-top: 22px;
    color: #647f91;
    font-size: 14px;
}

a {
    color: #2586a8;
    font-weight: bold;
    text-decoration: none;
}

a:hover {
    color: #174b70;
    text-decoration: underline;
}

/* FOOTER */

.footer {
    text-align: center;
    color: #7892a3;
    font-size: 11px;
    margin-top: 20px;
    line-height: 1.7;
}

/* SNOWFALL */

.snowflake {
    position: fixed;
    top: -30px;
    z-index: 1;
    color: white;
    text-shadow: 0 0 6px #79bfe5;
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

/* MOBILE */

@media(max-width: 480px) {

    .container {
        padding: 28px 22px;
    }

    .logo-container img {
        width: 75px;
        height: 75px;
    }

    h1 {
        font-size: 22px;
    }

    .tree-decoration {
        font-size: 27px;
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

<div class="christmas-lights" aria-hidden="true"></div>

<div class="container">

    <div class="snow-decoration" aria-hidden="true">❄️</div>
    <div class="tree-decoration" aria-hidden="true">🎄</div>

    <div class="logo-container">
        <img src="assets/barangay_cawit_logo.png"
             alt="Barangay Cawit Logo">
    </div>

    <div class="brand-name">
        BARANGAY CAWIT
    </div>

    <div class="location">
        Manito, Albay
    </div>

    <div class="decoration" aria-hidden="true">
        🎁 ❄️ 🎄 ❄️ 🎁
    </div>

    <h1>Resident Registration</h1>

    <p class="subtitle">
        Create your account to access<br>
        Barangay Cawit Online Services
    </p>

    <?php if (!empty($errors)): ?>

        <div class="errors">

            <?php foreach ($errors as $error): ?>

                <p>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </p>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <form method="POST" action="">

        <label for="full_name">
            Full Name <span class="required">*</span>
        </label>

        <input
            type="text"
            id="full_name"
            name="full_name"
            value="<?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') ?>"
            placeholder="Enter your full name"
            autocomplete="name"
            required
        >

        <label for="email">
            Email Address <span class="required">*</span>
        </label>

        <input
            type="email"
            id="email"
            name="email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
            placeholder="Enter your email address"
            autocomplete="email"
            required
        >

        <label for="contact_number">
            Contact Number
        </label>

        <input
            type="text"
            id="contact_number"
            name="contact_number"
            value="<?= htmlspecialchars($contact_number, ENT_QUOTES, 'UTF-8') ?>"
            placeholder="Enter your contact number"
            autocomplete="tel"
        >

        <label for="address">
            Complete Address
        </label>

        <textarea
            id="address"
            name="address"
            placeholder="Enter your complete address"
        ><?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?></textarea>

        <label for="password">
            Password <span class="required">*</span>
        </label>

        <input
            type="password"
            id="password"
            name="password"
            placeholder="Create a password"
            minlength="8"
            autocomplete="new-password"
            required
        >

        <label for="confirm_password">
            Confirm Password <span class="required">*</span>
        </label>

        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            placeholder="Confirm your password"
            minlength="8"
            autocomplete="new-password"
            required
        >

        <button type="submit">
            🎁 Create Resident Account
        </button>

    </form>

    <p class="login-link">
        Already have an account?
        <a href="login.php">Login here</a>
    </p>

    <div class="footer">
        <strong>🎄 BARANGAY CAWIT 🎄</strong><br>
        Municipality of Manito, Province of Albay<br>
        ❄️ Merry Christmas and Happy Holidays! ❄️
    </div>

</div>

<script>

// Decorative snowfall
(function () {

    const symbols = ["❄", "❅", "❆"];
    const snowCount = 30;

    for (let i = 0; i < snowCount; i++) {

        const snow = document.createElement("span");

        snow.className = "snowflake";

        snow.textContent =
            symbols[Math.floor(Math.random() * symbols.length)];

        snow.style.left = Math.random() * 100 + "vw";

        snow.style.fontSize =
            (10 + Math.random() * 15) + "px";

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
