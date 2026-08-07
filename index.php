<?php
// ============================================================
//  index.php — Single Login Page
//  College Bill Generation System — GCEA
// ============================================================
session_start();

// Already logged in → go to dashboard
if (!empty($_SESSION['user_id'])) {
    $map = [
        'admin'   => 'admin/dashboard.php',
        'hod'     => 'hod/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'student' => 'student/dashboard.php',
    ];
    header('Location: ' . ($map[$_SESSION['user_role']] ?? 'index.php'));
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT u.*, d.name AS dept_name
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.email = ? AND u.is_active = 1
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email']= $user['email'];
            $_SESSION['dept_id']   = $user['department_id'] ?? 0;
            $_SESSION['dept_name'] = $user['dept_name']     ?? '';

            $_SESSION['profile_photo'] = $user['profile_photo'] ?? '';

            // Log login
            $pdo->prepare(
                "INSERT INTO activity_log (user_id, action, description, ip_address)
                 VALUES (?, 'login', 'User logged in', ?)"
            )->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '']);

            // Redirect by role
            $map = [
                'admin'   => 'admin/dashboard.php',
                'hod'     => 'hod/dashboard.php',
                'teacher' => 'teacher/dashboard.php',
                'student' => 'student/dashboard.php',
            ];
            header('Location: ' . ($map[$user['role']] ?? 'index.php'));
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — College Bill Generation System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="navbar-brand">
        <img src="assets/images/logo.png" alt="GCEA Logo" class="navbar-logo"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="navbar-logo-fallback" style="display:none"><?= svgIcon('dashboard') ?></div>

        <div class="navbar-titles">
            <span class="navbar-college-en">Government College of Engineering, Chhatrapati Sambhajinagar</span>
            <span class="navbar-college-hi">शासकीय अभियांत्रिकी महाविद्यालय, छत्रपती संभाजीनगर</span>
        </div>
    </div>

    <div class="navbar-emblems">
        <img src="assets/images/img2.gif"  alt="Maharashtra Skill Development Department" class="navbar-emblem">
        <img src="assets/images/img3.png"    alt="Government of Maharashtra Seal" class="navbar-emblem navbar-emblem--crop">
        <img src="assets/images/img4.jpg"  alt="Government of India Emblem" class="navbar-emblem">
    </div>
</nav>

<div class="login-page">
    <div class="login-card">

        <!-- Header -->
        <h2>Welcome back</h2>
        <p class="subtitle">Sign in to the Bill Generation System</p>

        <!-- Session expired message -->
        <?php if ($msg === 'login_required'): ?>
        <div class="alert alert-warning"><?= svgIcon('warning') ?> Your session expired. Please sign in again.</div>
        <?php endif; ?>

        <!-- Error -->
        <?php if ($error): ?>
        <div class="alert alert-error"><?= svgIcon('close') ?> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="index.php">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control <?= $error ? 'is-invalid' : '' ?>"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    placeholder="Enter your email"
                    required
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control <?= $error ? 'is-invalid' : '' ?>"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="pw-toggle"
                            onclick="togglePw('password','pw-eye')">
                        <span id="pw-eye" data-on='<?= svgIcon('eye') ?>' data-off='<?= svgIcon('eye-off') ?>'><?= svgIcon('eye') ?></span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"
                    style="width:100%;justify-content:center;padding:10px;font-size:.9rem">
                Sign In →
            </button>
        </form>

        <!-- Demo credentials -->
        <div class="demo-box">
            <strong style="color:var(--text)">Demo credentials:</strong><br>
            Admin &nbsp;→ <span class="demo-fill" onclick="fillDemo('admin@gcea.edu','admin@1234')">admin@gcea.edu / admin@1234</span><br>
            HOD &nbsp;&nbsp;&nbsp;→ <span class="demo-fill" onclick="fillDemo('hod.cse@gcea.edu','hod@1234')">hod.cse@gcea.edu / hod@1234</span><br>
            Teacher → <span class="demo-fill" onclick="fillDemo('anjali@gcea.edu','teacher@1234')">anjali@gcea.edu / teacher@1234</span><br>
            Student → <span class="demo-fill" onclick="fillDemo('rahul@gcea.edu','student@1234')">rahul@gcea.edu / student@1234</span>
        </div>

    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
