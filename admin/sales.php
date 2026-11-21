<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/order_status.php';

// ---- Ubah status pesanan ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $new = $_POST['status'] ?? '';
    if ($id > 0 && isset(ORDER_STATUSES[$new])) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new, $id);
        $stmt->execute();
        flash('ok', "Pesanan #$id sekarang: " . ORDER_STATUSES[$new] . '.');
    } else {
        flash('err', 'Status tidak valid.');
    }
    $back = $_POST['back'] ?? '';
    header("Location: sales.php" . (isset(ORDER_STATUSES[$back]) ? "?status=$back" : ''));
    exit;
}

$statuses = ORDER_STATUSES;
$filter = $_GET['status'] ?? '';
if (!isset($statuses[$filter])) $filter = '';

if ($filter !== '') {
    $stmt = $conn->prepare(
        "SELECT o.id, o.total, o.status, o.created_at, u.username
         FROM orders o LEFT JOIN users u ON u.id = o.user_id
         WHERE o.status = ? ORDER BY o.id DESC LIMIT 200");
    $stmt->bind_param("s", $filter);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $orders = $conn->query(
        "SELECT o.id, o.total, o.status, o.created_at, u.username
         FROM orders o LEFT JOIN users u ON u.id = o.user_id
         ORDER BY o.id DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);
}

// Ambil semua item untuk pesanan yang tampil dalam satu query
$items = [];
if ($orders) {
    $ids = array_map(fn($o) => (int)$o['id'], $orders);
    $in = implode(',', $ids); // semua nilai sudah (int)
    $res = $conn->query("SELECT order_id, name, price, qty FROM order_items WHERE order_id IN ($in) ORDER BY id");
    while ($r = $res->fetch_assoc()) $items[(int)$r['order_id']][] = $r;
}

$sum = $conn->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> 'dibatalkan'")->fetch_row()[0];

admin_head('Daftar Penjualan', 'sales');
?>
<section class="admin-card">
    <div class="admin-card-head">
        <h2>Pesanan (<?= count($orders) ?>)</h2>
        <div class="admin-filters">
            <a href="sales.php" class="admin-chip <?= $filter === '' ? 'active' : '' ?>">Semua</a>
            <?php foreach ($statuses as $key => $label): ?>
                <a href="?status=<?= $key ?>" class="admin-chip <?= $filter === $key ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <p class="admin-note">Total penjualan (tanpa pesanan dibatalkan): <strong><?= rupiah($sum) ?></strong></p>

    <?php if (!$orders): ?>
        <p class="admin-empty">Belum ada pesanan. Pesanan muncul di sini setelah pelanggan checkout.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>No. Pesanan</th><th>Waktu</th><th>Pelanggan</th><th>Item</th><th>Total</th><th>Status</th><th>Ubah Status</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?= (int)$o['id'] ?></td>
                    <td><?= esc($o['created_at']) ?></td>
                    <td><?= $o['username'] ? esc($o['username']) : '<em>user dihapus</em>' ?></td>
                    <td>
                        <?php foreach ($items[(int)$o['id']] ?? [] as $it): ?>
                            <div><?= esc($it['name']) ?> × <?= (int)$it['qty'] ?> <small>(<?= rupiah($it['price']) ?>)</small></div>
                        <?php endforeach; ?>
                    </td>
                    <td><?= rupiah($o['total']) ?></td>
                    <td><span class="admin-badge st-<?= esc($o['status']) ?>"><?= esc($statuses[$o['status']]) ?></span></td>
                    <td>
                        <form method="POST" class="admin-status-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                            <input type="hidden" name="back" value="<?= esc($filter) ?>">
                            <select name="status">
                                <?php foreach ($statuses as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $o['status'] === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="admin-save">Simpan</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php admin_foot();
