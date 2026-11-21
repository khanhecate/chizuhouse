<?php
// Default: container Docker (shared-db).
// Di VPS, install.sh membuat config.local.php yang menimpa nilai ini.
$host = getenv('DB_HOST') ?: "shared-db";
$user = getenv('DB_USER') ?: "webuser";
$pass = getenv('DB_PASS') ?: "webuser123";
$db   = getenv('DB_NAME') ?: "chizuhouse";

if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

$conn = new mysqli($host, $user, $pass, $db);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
