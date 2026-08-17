<?php
// ============================================================
//  includes/functions.php — Shared Helper Functions
//  College Bill Generation System — GCEA
// ============================================================

// ── Output helpers ───────────────────────────────────────────
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function formatINR(float $amount): string {
    return '₹' . number_format($amount, 2);
}

function fmtDate(string $date, string $fmt = 'd M Y'): string {
    if (!$date || $date === '0000-00-00') return '—';
    $ts = strtotime($date);
    return $ts ? date($fmt, $ts) : '—';
}

// ── Flash messages ───────────────────────────────────────────
function setFlash(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['flash'])) return '';
    ['type' => $type, 'msg' => $msg] = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $icons = ['success'=>svgIcon('check'),'error'=>svgIcon('close'),'warning'=>svgIcon('warning'),'info'=>svgIcon('info')];
    $cls   = match($type) {
        'success' => 'alert-success',
        'error'   => 'alert-error',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    return '<div class="alert ' . $cls . ' auto-dismiss">' . ($icons[$type] ?? '') . ' ' . e($msg) . '</div>';
}

// ── Activity log ─────────────────────────────────────────────
function logActivity(PDO $pdo, int $userId, string $action, string $desc = ''): void {
    $pdo->prepare(
        "INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?,?,?,?)"
    )->execute([$userId, $action, $desc, $_SERVER['REMOTE_ADDR'] ?? '']);
}

// ── Badges ───────────────────────────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'draft'     => ['badge-draft',     '● Draft'],
        'pending'   => ['badge-pending',   '● Pending'],
        'approved'  => ['badge-approved',  '● Approved'],
        'rejected'  => ['badge-rejected',  '● Rejected'],
        'finalized' => ['badge-finalized', '● Finalized'],
    ];
    [$cls, $label] = $map[$status] ?? ['badge-pending', ucfirst($status)];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

function teacherTypeBadge(string $type): string {
    $map = [
        'regular'          => ['badge-regular',   'Regular'],
        'expert'           => ['badge-expert',    'Expert'],
        'sectional_expert' => ['badge-sectional', 'Sectional Expert'],
        'adjunct'          => ['badge-adjunct',   'Adjunct'],
    ];
    [$cls, $label] = $map[$type] ?? ['badge-regular', ucfirst($type)];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

function modeBadge(string $mode): string {
    $map = [
        'theory'    => ['badge-theory',    'Theory'],
        'practical' => ['badge-practical', 'Practical'],
        'theory & practical' => ['badge-both', 'Theory &amp; Practical'],
    ];
    [$cls, $label] = $map[$mode] ?? ['badge-theory', ucfirst($mode)];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

// Category badge for the unified All Bills list — which table a bill came from
function billTypeBadge(string $source): string {
    $map = [
        'teacher' => ['badge-cat-teacher', 'Teacher'],
        'student' => ['badge-cat-student', 'Student'],
        'other'   => ['badge-cat-other',   'Other'],
    ];
    [$cls, $label] = $map[$source] ?? ['badge-cat-other', ucfirst($source)];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

// ── Base path helper (from any subfolder) ───────────────────
function base(string $file = ''): string {
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $root   = realpath(__DIR__ . '/..');
    return $root . '/' . ltrim($file, '/');
}

function assetUrl(string $file): string {
    // Works from admin/, hod/, teacher/, student/, pdf/ (one level deep)
    // and from root index.php
    $depth = substr_count(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/') - 1;
    $prefix = $depth <= 1 ? '' : str_repeat('../', $depth - 1);
    return $prefix . 'assets/' . ltrim($file, '/');
}

function rootPath(string $file = ''): string {
    $depth = substr_count(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/') - 1;
    $prefix = $depth <= 1 ? '' : str_repeat('../', $depth - 1);
    return $prefix . ltrim($file, '/');
}

// ── HTML head + open body + navbar ───────────────────────────
function renderHead(string $title, int $depth = 1): void {
    $asset = $depth === 0 ? 'assets/' : str_repeat('../', $depth) . 'assets/';
    $root  = $depth === 0 ? '' : str_repeat('../', $depth);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — BGS | GCEA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $asset ?>css/style.css?v=4">
</head>
<body>
<!-- TOP NAVBAR -->
<!-- Navbar -->
<nav class="navbar">
    <div class="navbar-brand">
        <img src="../assets/images/logo.png" alt="GCEA Logo" class="navbar-logo"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="navbar-logo-fallback" style="display:none"><?= svgIcon('dashboard') ?></div>

        <div class="navbar-titles">
            <span class="navbar-college-en">Government College of Engineering, Chhatrapati Sambhajinagar</span>
            <span class="navbar-college-hi">शासकीय अभियांत्रिकी महाविद्यालय, छत्रपती संभाजीनगर</span>
        </div>
    </div>

    <div class="navbar-emblems">
        <img src="../assets/images/img2.gif"  alt="Maharashtra Skill Development Department" class="navbar-emblem">
        <img src="../assets/images/img3.png"    alt="Government of Maharashtra Seal" class="navbar-emblem navbar-emblem--crop">
        <img src="../assets/images/img4.jpg"  alt="Government of India Emblem" class="navbar-emblem">
    </div>
</nav>
    <?php
}

function renderFooter(int $depth = 1): void {
    $asset = $depth === 0 ? 'assets/' : str_repeat('../', $depth) . 'assets/';
    ?>
    <script src="<?= $asset ?>js/app.js"></script>
</body>
</html>
    <?php
}

// ── SVG icon loader ───────────────────────────────────────────
function svgIcon(string $name): string {
    $path = __DIR__ . '/../assets/svg/' . $name . '.svg';
    if (!file_exists($path)) return '';
    $svg = file_get_contents($path);

    // Add class for CSS sizing control
    $svg = preg_replace('/<svg\b/', '<svg class="icon"', $svg, 1);

    // Remove hardcoded width/height — CSS controls size
    $svg = preg_replace('/\s*width="[^"]*"/', '', $svg, 1);
    $svg = preg_replace('/\s*height="[^"]*"/', '', $svg, 1);

    // Replace hardcoded colors on shape elements with currentColor
    $svg = preg_replace('/(<(?:path|circle|rect|ellipse|line|polyline|polygon|g)[^>]*)\bfill="#[0-9a-fA-F]{3,6}"/', '$1fill="currentColor"', $svg);
    $svg = preg_replace('/(<(?:path|circle|rect|ellipse|line|polyline|polygon|g)[^>]*)\bstroke="#[0-9a-fA-F]{3,6}"/', '$1stroke="currentColor"', $svg);

    return $svg;
}

// ── Sidebar renderer ─────────────────────────────────────────
function renderSidebar(string $active, string $role, array $user): void {
    $navs = [
        'admin' => [
            ['key'=>'dashboard',     'href'=>'dashboard.php',     'icon'=>svgIcon('dashboard'), 'label'=>'Dashboard'],
            ['key'=>'departments',   'href'=>'departments.php',   'icon'=>svgIcon('departments'), 'label'=>'Departments'],
            ['key'=>'classes',       'href'=>'classes.php',       'icon'=>svgIcon('classes'), 'label'=>'Classes'],
            ['key'=>'subjects',      'href'=>'subjects.php',      'icon'=>svgIcon('subjects'), 'label'=>'Subjects'],
            ['key'=>'manage-hods',   'href'=>'manage-hods.php',   'icon'=>svgIcon('manage-hods'), 'label'=>'Manage HODs'],
            ['key'=>'fund-requests', 'href'=>'fund-requests.php', 'icon'=>svgIcon('fund-requests'), 'label'=>'Fund Requests'],
            ['key'=>'profile',       'href'=>'profile.php',       'icon'=>svgIcon('profile'), 'label'=>'Profile'],
        ],
        'hod' => [
            ['key'=>'dashboard',     'href'=>'dashboard.php',     'icon'=>svgIcon('dashboard'), 'label'=>'Dashboard'],
            ['key'=>'classes',       'href'=>'classes.php',       'icon'=>svgIcon('classes'), 'label'=>'Classes'],
            ['key'=>'subjects',      'href'=>'subjects.php',      'icon'=>svgIcon('subjects'), 'label'=>'Subjects'],
            ['key'=>'manage-users',  'href'=>'manage-users.php',  'icon'=>svgIcon('manage-users'), 'label'=>'Manage Users'],
            ['key'=>'requests',      'href'=>'requests.php',      'icon'=>svgIcon('pending'), 'label'=>'Pending Requests'],
            ['key'=>'fund-request',  'href'=>'fund-request.php',  'icon'=>svgIcon('fund-requests'), 'label'=>'Request Funds'],
            ['key'=>'all-bills',     'href'=>'all-bills.php',     'icon'=>svgIcon('all-bills'), 'label'=>'All Bills'],
            ['key'=>'manual-bill',   'href'=>'manual-bill.php',   'icon'=>svgIcon('manual-bill'), 'label'=>'Manual Bill'],
            ['key'=>'other-bills',   'href'=>'other-bills.php',   'icon'=>svgIcon('other-bills'), 'label'=>'Other Bills'],
            // ['key'=>'timetable',     'href'=>'timetable.php',     'icon'=>svgIcon('timetable'), 'label'=>'Time Table'],
            ['key'=>'profile',       'href'=>'profile.php',       'icon'=>svgIcon('profile'), 'label'=>'Profile'],
        ],
        'teacher' => [
            ['key'=>'dashboard',     'href'=>'dashboard.php',     'icon'=>svgIcon('dashboard'), 'label'=>'Dashboard'],
            ['key'=>'lectures',      'href'=>'lectures.php',      'icon'=>svgIcon('lectures'), 'label'=>'My Lectures'],
            ['key'=>'generate-bill', 'href'=>'generate-bill.php', 'icon'=>svgIcon('generate-bill'), 'label'=>'Generate Bill'],
            ['key'=>'my-bills',      'href'=>'my-bills.php',      'icon'=>svgIcon('all-bills'), 'label'=>'My Bills'],
            ['key'=>'profile',       'href'=>'profile.php',       'icon'=>svgIcon('profile'), 'label'=>'Profile'],
        ],
        'student' => [
            ['key'=>'dashboard',     'href'=>'dashboard.php',     'icon'=>svgIcon('dashboard'), 'label'=>'Dashboard'],
            ['key'=>'add-work',      'href'=>'add-work.php',      'icon'=>svgIcon('work'), 'label'=>'Add Work'],
            ['key'=>'generate-bill', 'href'=>'generate-bill.php', 'icon'=>svgIcon('generate-bill'), 'label'=>'Generate Bill'],
            ['key'=>'my-bills',      'href'=>'my-bills.php',      'icon'=>svgIcon('all-bills'), 'label'=>'My Bills'],
            ['key'=>'profile',       'href'=>'profile.php',       'icon'=>svgIcon('profile'), 'label'=>'Profile'],
        ],
    ];

    $items    = $navs[$role] ?? [];
    $initials = getInitials($user['name']);
    $profilePhoto = $user['profile_photo'] ?? null;
    $roleLabel = match($role) {
        'admin'   => 'Super Admin',
        'hod'     => 'HOD',
        'teacher' => 'Teacher',
        'student' => 'Earn & Learn',
        default   => ucfirst($role),
    };
    ?>
    <aside class="sidebar">
        <div class="sidebar-user">
            <div class="user-avatar"><?php if($profilePhoto): ?><img src="../assets/uploads/profiles/<?= e($profilePhoto) ?>" alt="Photo" class="user-avatar-img"><?php else: ?><?= e($initials) ?><?php endif; ?></div>
            <div class="user-info">
                <div class="user-name"><?= e($user['name']) ?></div>
                <div class="user-role"><?= $roleLabel ?></div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Menu</div>
            <?php foreach ($items as $item): ?>
            <a href="<?= e($item['href']) ?>"
               class="nav-item <?= $active === $item['key'] ? 'active' : '' ?>">
                <span class="nav-icon"><?= $item['icon'] ?></span>
                <?= e($item['label']) ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php" class="btn-logout"
               onclick="return confirmAction('Sign out of your account?')">
                <?= svgIcon('logout') ?> Sign Out
            </a>
        </div>
    </aside>
    <?php
}

// ── Topbar inside dashboard pages ────────────────────────────
// $breadcrumbs: [['label'=>'Home','href'=>'../index.php'], ['label'=>'HOD'], ['label'=>'Dashboard']]
function renderTopbar(string $title, array $breadcrumbs = []): void {
    ?>
    <div class="topbar">
        <div class="topbar-left">
            <?php if ($breadcrumbs): ?>
            <nav class="topbar-breadcrumb" aria-label="Breadcrumb">
                <?php foreach ($breadcrumbs as $i => $crumb): ?>
                    <?php if ($i > 0): ?><span class="topbar-bc-sep">›</span><?php endif; ?>
                    <?php if (!empty($crumb['href'])): ?>
                        <a href="<?= e($crumb['href']) ?>" class="topbar-bc-link"><?= e($crumb['label']) ?></a>
                    <?php else: ?>
                        <span class="topbar-bc-current"><?= e($crumb['label']) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php else: ?>
            <div class="topbar-title"><?= e($title) ?></div>
            <?php endif; ?>
        </div>
        <div class="topbar-right">
            <span class="topbar-date"><?= date('l, d F Y') ?></span>
        </div>
    </div>
    <?php
}

// ── Full app layout wrappers ──────────────────────────────────
function openLayout(string $active, string $role, array $user): void {
    echo '<div class="app-layout">';
    renderSidebar($active, $role, $user);
    echo '<div class="main-content">';
    renderTopbar(ucwords(str_replace('-', ' ', $active)));
    echo '<div class="page-body">';
}

function closeLayout(int $depth = 1): void {
    echo '</div></div></div>';
    renderFooter($depth);
}

// ── Department name helper ────────────────────────────────────
function deptName(PDO $pdo, int $id): string {
    static $cache = [];
    if (!$id) return '—';
    if (!isset($cache[$id])) {
        $s = $pdo->prepare("SELECT name FROM departments WHERE id=? LIMIT 1");
        $s->execute([$id]);
        $cache[$id] = $s->fetchColumn() ?: '—';
    }
    return $cache[$id];
}

function subjectLabel(PDO $pdo, int $id): string {
    static $cache = [];
    if (!$id) return '—';
    if (!isset($cache[$id])) {
        $s = $pdo->prepare("SELECT subject_name, subject_code FROM subjects WHERE id=? LIMIT 1");
        $s->execute([$id]);
        $row = $s->fetch();
        $cache[$id] = $row ? $row['subject_name'] . ' (' . $row['subject_code'] . ')' : '—';
    }
    return $cache[$id];
}

// ── Student Bill Number Generator ────────────────────────────
// Format: EL-YYYY-MM-NNNNN  (e.g. EL-2026-08-00012)
function generateStudentBillNumber(string $periodFrom, int $billId): string {
    $ts = strtotime($periodFrom);
    $ym = $ts ? date('Y-m', $ts) : date('Y-m');
    return 'EL-' . $ym . '-' . str_pad($billId, 5, '0', STR_PAD_LEFT);
}
