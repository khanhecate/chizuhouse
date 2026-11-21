<?php
// Dipakai semua halaman admin: guard, CSRF, flash, dan layout sidebar.
session_start();
require_once __DIR__ . '/../koneksi.php';

if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
// Role dicek ke database di setiap request, jadi pencabutan hak admin langsung berlaku
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$me || $me['role'] !== 'admin') {
    http_response_code(403);
    exit('Akses ditolak. Halaman ini hanya untuk admin.');
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rupiah($n): string { return 'Rp ' . number_format((int)$n, 0, ',', '.'); }
function flash(string $type, string $msg): void { $_SESSION['flash'] = [$type, $msg]; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . esc($_SESSION['csrf']) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Token tidak valid. Muat ulang halaman.');
    }
}

function admin_head(string $title, string $active): void {
    $nav = [
        'dashboard' => ['index.php', 'Dashboard', '▦'],
        'menu'      => ['menu.php', 'Tambah Menu', '＋'],
        'users'     => ['users.php', 'Management User', '☺'],
        'sales'     => ['sales.php', 'Daftar Penjualan', '▤'],
    ];
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> - Admin Chizu House</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-page">
<div class="admin-shell">
    <aside class="admin-side">
        <a href="index.php" class="admin-brand"><img src="../assets/brand/chizu-house-logo.png" alt="Logo"><span>chizu house<small>Admin</small></span></a>
        <nav class="admin-nav">
            <?php foreach ($nav as $key => [$href, $label, $icon]): ?>
                <a href="<?= $href ?>" class="<?= $key === $active ? 'active' : '' ?>"><span aria-hidden="true"><?= $icon ?></span><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-side-foot">
            <span class="admin-user"><?= esc($_SESSION['full_name'] ?: $_SESSION['username']) ?></span>
            <a href="../index.php" class="admin-link">Lihat Toko</a>
            <a href="../logout.php" class="admin-link danger">Keluar</a>
        </div>
    </aside>
    <main class="admin-content">
        <h1 class="admin-title"><?= esc($title) ?></h1>
        <?php if ($flash): ?>
            <div class="admin-flash <?= $flash[0] === 'ok' ? 'ok' : 'err' ?>"><?= esc($flash[1]) ?></div>
        <?php endif; ?>
    <?php
}

function admin_foot(): void {
    ?>
    </main>
</div>
</body>
</html>
    <?php
}
