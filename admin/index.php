<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/order_status.php';

$count = fn(string $sql) => (int)($conn->query($sql)->fetch_row()[0] ?? 0);
$totalMenu   = $count("SELECT COUNT(*) FROM products");
$totalUser   = $count("SELECT COUNT(*) FROM users WHERE role = 'user'");
$totalAdmin  = $count("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$totalOrder  = $count("SELECT COUNT(*) FROM orders WHERE status <> 'dibatalkan'");
$omzet       = $count("SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> 'dibatalkan'");
$baru        = $count("SELECT COUNT(*) FROM orders WHERE status = 'diterima'");

$recent = $conn->query(
    "SELECT o.id, o.total, o.status, o.created_at, u.username
     FROM orders o LEFT JOIN users u ON u.id = o.user_id
     ORDER BY o.id DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

admin_head('Dashboard', 'dashboard');
?>
<div class="admin-stats">
    <a class="admin-stat" href="menu.php"><span>Menu</span><strong><?= $totalMenu ?></strong></a>
    <a class="admin-stat" href="users.php"><span>User</span><strong><?= $totalUser ?></strong><small><?= $totalAdmin ?> admin</small></a>
    <a class="admin-stat" href="sales.php"><span>Total pesanan</span><strong><?= $totalOrder ?></strong><small><?= $baru ?> belum diproses</small></a>
    <a class="admin-stat" href="sales.php"><span>Total penjualan</span><strong><?= rupiah($omzet) ?></strong></a>
</div>

<section class="admin-card">
    <h2>Pesanan Terbaru</h2>
    <?php if (!$recent): ?>
        <p class="admin-empty">Belum ada pesanan.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>No. Pesanan</th><th>Pelanggan</th><th>Total</th><th>Status</th><th>Waktu</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $o): ?>
                    <tr>
                        <td>#<?= (int)$o['id'] ?></td>
                        <td><?= $o['username'] ? esc($o['username']) : '<em>user dihapus</em>' ?></td>
                        <td><?= rupiah($o['total']) ?></td>
                        <td><span class="admin-badge st-<?= esc($o['status']) ?>"><?= esc(ORDER_STATUSES[$o['status']] ?? $o['status']) ?></span></td>
                        <td><?= esc($o['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php admin_foot();
