<?php
// Buat akun admin atau jadikan akun yang sudah ada sebagai admin. Hanya lewat CLI.
//   php tools/create_admin.php <username>                       -> jadikan user yang sudah ada sebagai admin
//   php tools/create_admin.php <username> <email> <password>    -> buat akun admin baru
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../koneksi.php';

$username = $argv[1] ?? null;
if (!$username) {
    fwrite(STDERR, "Pemakaian: php tools/create_admin.php <username> [email password]\n");
    exit(1);
}

$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($exists) {
    $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
    $stmt->bind_param("i", $exists['id']);
    $stmt->execute();
    echo "User '$username' sekarang admin.\n";
    exit(0);
}

$email = $argv[2] ?? null;
$password = $argv[3] ?? null;
if (!$email || !$password) {
    fwrite(STDERR, "User '$username' belum ada. Tambahkan email dan password untuk membuatnya.\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, 'admin')");
$stmt->bind_param("ssss", $username, $email, $hash, $username);
$stmt->execute();
echo "Admin '$username' dibuat.\n";
