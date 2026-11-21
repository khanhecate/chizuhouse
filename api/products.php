<?php
// Daftar menu untuk halaman toko (publik, hanya baca)
require_once __DIR__ . '/../koneksi.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$rows = [];
$res = $conn->query("SELECT id, name, category, price, description, image FROM products ORDER BY id");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'id'          => (int)$r['id'],
            'name'        => $r['name'],
            'category'    => $r['category'],
            'price'       => (int)$r['price'],
            'description' => (string)$r['description'],
            'image'       => (string)$r['image'],
        ];
    }
}
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
