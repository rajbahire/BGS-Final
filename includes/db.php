<?php
// ============================================================
//  includes/db.php — PDO Database Connection
//  College Bill Generation System — GCEA
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'college_bill_system');
define('DB_USER',    'root');
define('DB_PASS',    '');          // XAMPP default: empty
define('DB_CHARSET', 'utf8mb4');

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(503);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>DB Error</title>
    <style>
        body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;
             min-height:100vh;background:#F1F5F9}
        .box{background:#fff;border:1px solid #E2E8F0;border-left:4px solid #EF4444;
             border-radius:10px;padding:2rem 2.5rem;max-width:500px;width:100%}
        h2{color:#1E293B;font-size:1.1rem;margin:0 0 .5rem}
        p{color:#64748B;font-size:.9rem;margin:.3rem 0}
        code{background:#F8FAFC;padding:2px 6px;border-radius:4px;color:#EF4444;font-size:.82rem}
    </style></head><body>
    <div class="box">
        <h2>Database connection failed</h2>
        <p>Make sure XAMPP MySQL is running and <code>database.sql</code> is imported.</p>
        <p>Verify credentials in <code>includes/db.php</code>.</p>
        <p style="margin-top:1rem;font-size:.78rem;color:#94A3B8">'
        . htmlspecialchars($e->getMessage()) . '</p>
    </div></body></html>';
    exit;
}
