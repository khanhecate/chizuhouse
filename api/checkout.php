<?php
// Membuat pesanan dari keranjang. Harga selalu diambil dari database, bukan dari browser.
session_start();
require_once __DIR__ . '/../koneksi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
header('Content-Type: application/json; charset=utf-8');

function out(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok' => false, 'error' => 'Metode tidak diizinkan.']);
if (empty($_SESSION['user_id'])) out(401, ['ok' => false, 'error' => 'Silakan login terlebih dahulu.']);
if (!hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    out(400, ['ok' => false, 'error' => 'Sesi tidak valid. Muat ulang halaman.']);
}

$body = json_decode(file_get_contents('php://input'), true);
$rawItems = is_array($body['items'] ?? null) ? $body['items'] : [];
if (!$rawItems || count($rawItems) > 50) out(422, ['ok' => false, 'error' => 'Keranjang kosong atau terlalu banyak item.']);

// Gabungkan id yang sama dan validasi jumlah
$qtyById = [];
foreach ($rawItems as $it) {
    $id  = filter_var($it['id'] ?? null, FILTER_VALIDATE_INT);
    $qty = filter_var($it['qty'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1 || $qty === false || $qty < 1 || $qty > 99) {
        out(422, ['ok' => false, 'error' => 'Data keranjang tidak valid.']);
    }
    $qtyById[$id] = ($qtyById[$id] ?? 0) + $qty;
}
if (max($qtyById) > 99) out(422, ['ok' => false, 'error' => 'Jumlah per menu maksimal 99.']);

try {
    $in = implode(',', array_map('intval', array_keys($qtyById)));
    $products = [];
    $res = $conn->query("SELECT id, name, price FROM products WHERE id IN ($in)");
    while ($r = $res->fetch_assoc()) $products[(int)$r['id']] = $r;
    if (count($products) !== count($qtyById)) {
        out(422, ['ok' => false, 'error' => 'Ada menu yang sudah tidak tersedia. Muat ulang halaman.']);
    }

    $total = 0;
    foreach ($qtyById as $id => $qty) $total += (int)$products[$id]['price'] * $qty;

    $conn->begin_transaction();
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total, status) VALUES (?, ?, 'diterima')");
    $stmt->bind_param("ii", $uid, $total);
    $stmt->execute();
    $orderId = $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, name, price, qty) VALUES (?, ?, ?, ?, ?)");
    foreach ($qtyById as $id => $qty) {
        $name = $products[$id]['name'];
        $price = (int)$products[$id]['price'];
        $stmt->bind_param("iisii", $orderId, $id, $name, $price, $qty);
        $stmt->execute();
    }
    $stmt->close();
    $conn->commit();

    out(200, ['ok' => true, 'order_id' => $orderId, 'total' => $total]);
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('checkout gagal: ' . $e->getMessage());
    out(500, ['ok' => false, 'error' => 'Pesanan gagal disimpan. Coba lagi.']);
}
