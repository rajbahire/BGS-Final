<?php
// ============================================================
//  includes/auth.php — Authentication Guards
//  College Bill Generation System — GCEA
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Core guard: any logged-in user ───────────────────────────
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . rootUrl() . 'index.php?msg=login_required');
        exit;
    }
}

// Bill-generation gate (teacher / student only).
// A user whose profile is not yet complete is blocked from generating a
// bill — the bill pulls their bank / appointment / enrollment details,
// so those must be filled first — and is bounced to their profile page
// with an alert. Admin and HOD are exempt: they don't generate their
// own bills, and the user asked for no restrictions on those roles (an
// alert at login is enough for them). index.php sets 'profile_completed'
// at login from isProfileComplete(); each profile.php refreshes it after
// a save, so the moment the last required field is filled the gate opens.
// Sessions created before this feature existed lack the flag and are
// assumed complete, so we never trap someone who was already logged in.
function requireProfileForBilling(): void {
    if (!in_array($_SESSION['user_role'] ?? '', ['teacher', 'student'], true)) return;
    if (!array_key_exists('profile_completed', $_SESSION)) {
        $_SESSION['profile_completed'] = 1;
    }
    if (empty($_SESSION['profile_completed'])) {
        $root = rootUrl();
        $map  = [
            'teacher' => $root . 'teacher/profile.php',
            'student' => $root . 'student/profile.php',
        ];
        $_SESSION['flash'] = [
            'type' => 'warning',
            'msg'  => 'Please complete your profile before generating a bill.',
        ];
        header('Location: ' . ($map[$_SESSION['user_role']] ?? $root . 'index.php?msg=login_required'));
        exit;
    }
}

// ── Role-specific guards ─────────────────────────────────────
function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        redirectToDashboard();
    }
}

function requireHOD(): void {
    requireLogin();
    if ($_SESSION['user_role'] !== 'hod') {
        redirectToDashboard();
    }
}

function requireTeacher(): void {
    requireLogin();
    if ($_SESSION['user_role'] !== 'teacher') {
        redirectToDashboard();
    }
}

function requireStudent(): void {
    requireLogin();
    if ($_SESSION['user_role'] !== 'student') {
        redirectToDashboard();
    }
}

// Admin OR HOD (for shared pages)
function requireAdminOrHOD(): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'], ['admin', 'hod'], true)) {
        redirectToDashboard();
    }
}

// ── Redirect to correct dashboard based on role ──────────────
function redirectToDashboard(): never {
    $root = rootUrl();
    $map  = [
        'admin'   => $root . 'admin/dashboard.php',
        'hod'     => $root . 'hod/dashboard.php',
        'teacher' => $root . 'teacher/dashboard.php',
        'student' => $root . 'student/dashboard.php',
    ];
    $role = $_SESSION['user_role'] ?? 'admin';
    header('Location: ' . ($map[$role] ?? $root . 'index.php'));
    exit;
}

// ── Return the site root URL (works in subdirectory installs) ─
function rootUrl(): string {
    static $root = null;
    if ($root !== null) return $root;
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Walk up from current script until we hit college-bill-system root
    $dir = rtrim(str_replace(
        ['\\', '/admin', '/hod', '/teacher', '/student', '/pdf'],
        ['/', '', '', '', '', ''],
        dirname($script)
    ), '/') . '/';
    $root = $dir;
    return $root;
}

// ── Handy session helpers ────────────────────────────────────
function currentUser(): array {
    return [
        'id'         => (int)($_SESSION['user_id']   ?? 0),
        'name'       => $_SESSION['user_name']        ?? '',
        'role'       => $_SESSION['user_role']        ?? '',
        'email'      => $_SESSION['user_email']       ?? '',
        'dept_id'    => (int)($_SESSION['dept_id']    ?? 0),
        'dept_name'  => $_SESSION['dept_name']        ?? '',
        'profile_photo' => $_SESSION['profile_photo'] ?? '',
    ];
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

// ── Initials from full name (for avatar) ─────────────────────
function getInitials(string $name): string {
    $parts = array_filter(explode(' ', trim($name)));
    $init  = '';
    foreach (array_slice(array_values($parts), 0, 2) as $p) {
        $init .= strtoupper($p[0]);
    }
    return $init ?: '?';
}
