<?php
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/includes/order_status.php';

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
function esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rupiah($n): string { return 'Rp ' . number_format((int)$n, 0, ',', '.'); }

$uid = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, total, status, created_at, updated_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 100");
$stmt->bind_param("i", $uid);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$items = [];
if ($orders) {
    $ids = implode(',', array_map(fn($o) => (int)$o['id'], $orders));
    $res = $conn->query("SELECT order_id, name, price, qty FROM order_items WHERE order_id IN ($ids) ORDER BY id");
    while ($r = $res->fetch_assoc()) $items[(int)$r['order_id']][] = $r;
}
$flow = array_keys(ORDER_FLOW);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <title>Pesanan Saya - Chizu House</title>
    <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
    <link rel="stylesheet" href="css/orders.css?v=<?= @filemtime(__DIR__ . '/css/orders.css') ?>">
</head>
<body class="orders-page">
    <header class="orders-header">
        <a href="index.php" class="logo logo-brand"><img src="assets/brand/chizu-house-logo.png" alt="Logo Chizu House"><span>chizu house</span></a>
        <div>
            <a href="index.php#products" class="orders-link">Belanja Lagi</a>
            <a href="logout.php" class="orders-link danger">Keluar</a>
        </div>
    </header>
    <main class="orders-main">
        <h1>Pesanan Saya</h1>
        <p class="orders-note">Halaman ini diperbarui otomatis setiap 30 detik.</p>

        <?php if (!$orders): ?>
            <div class="order-card empty">
                <p>Kamu belum punya pesanan.</p>
                <a href="index.php#products" class="primary-btn">Lihat Menu</a>
            </div>
        <?php endif; ?>

        <?php foreach ($orders as $o):
            $status = $o['status'];
            $cancelled = $status === 'dibatalkan';
            $current = array_search($status, $flow, true);
        ?>
            <article class="order-card">
                <div class="order-head">
                    <div>
                        <strong>Pesanan #<?= (int)$o['id'] ?></strong>
                        <small><?= esc(date('d M Y, H:i', strtotime($o['created_at']))) ?></small>
                    </div>
                    <span class="order-badge st-<?= esc($status) ?>"><?= esc(ORDER_STATUSES[$status] ?? $status) ?></span>
                </div>

                <?php if ($cancelled): ?>
                    <p class="order-cancelled">Pesanan ini dibatalkan.</p>
                <?php else: ?>
                    <ol class="order-steps">
                        <?php foreach ($flow as $i => $key): ?>
                            <li class="<?= $i < $current ? 'done' : ($i === $current ? 'current' : '') ?>">
                                <span class="dot"><?= $i <= $current ? '✓' : $i + 1 ?></span>
                                <span class="label"><?= esc(ORDER_FLOW[$key]) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <ul class="order-items">
                    <?php foreach ($items[(int)$o['id']] ?? [] as $it): ?>
                        <li>
                            <span><?= esc($it['name']) ?> × <?= (int)$it['qty'] ?></span>
                            <span><?= rupiah($it['price'] * $it['qty']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="order-total"><span>Total</span><strong><?= rupiah($o['total']) ?></strong></div>
                <small class="order-updated">Status diperbarui <?= esc(date('d M Y, H:i', strtotime($o['updated_at']))) ?></small>
            </article>
        <?php endforeach; ?>
    </main>
</body>
</html>
