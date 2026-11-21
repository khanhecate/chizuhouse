<?php
session_start();
require_once __DIR__ . '/../koneksi.php';

// ---- Guard: hanya admin. Role dicek ke database di setiap request ----
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
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

const CATEGORIES = ['Snack' => 'Camilan (Snack)', 'Cheese' => 'Cheesy (Cheese)', 'Meal' => 'Makanan (Meal)'];
const UPLOAD_DIR = __DIR__ . '/../assets/images/uploads/';
const UPLOAD_URL = 'assets/images/uploads/';
const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function flash(string $type, string $msg): void { $_SESSION['flash'] = [$type, $msg]; }

// Simpan gambar upload. Return [path|null, error|null]
function saveImage(array $file): array {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'Ukuran gambar melebihi batas.' : 'Upload gambar gagal.'];
    }
    if ($file['size'] > MAX_IMAGE_BYTES) return [null, 'Ukuran gambar maksimal 2 MB.'];

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(IMAGE_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return [null, 'Format gambar harus JPG, PNG, atau WEBP.'];
    }
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) return [null, 'Folder upload tidak bisa dibuat.'];

    $name = bin2hex(random_bytes(8)) . '.' . IMAGE_TYPES[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        return [null, 'Gagal menyimpan gambar. Cek izin tulis folder assets/images/uploads.'];
    }
    return [UPLOAD_URL . $name, null];
}

// Hapus file hanya kalau berasal dari folder upload (gambar bawaan tidak disentuh)
function deleteImage(?string $path): void {
    if ($path && strpos($path, UPLOAD_URL) === 0 && basename($path) === substr($path, strlen(UPLOAD_URL))) {
        @unlink(UPLOAD_DIR . basename($path));
    }
}

function esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---- Aksi POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Token tidak valid. Muat ulang halaman.');
    }
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($row) deleteImage($row['image']);
        flash('ok', 'Menu dihapus.');
        header("Location: index.php");
        exit;
    }

    if ($action === 'add' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? '';
        $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000000]]);
        $desc = trim($_POST['description'] ?? '');

        $err = null;
        if ($name === '' || mb_strlen($name) > 100) $err = 'Nama menu wajib diisi (maks. 100 karakter).';
        elseif (!isset(CATEGORIES[$category])) $err = 'Kategori tidak valid.';
        elseif ($price === false) $err = 'Harga harus berupa angka bulat (rupiah), tanpa titik atau koma.';

        $newImage = null;
        if (!$err) [$newImage, $err] = saveImage($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        if (!$err && $action === 'add' && !$newImage) $err = 'Gambar menu wajib diunggah.';

        if ($err) {
            flash('err', $err);
            header("Location: index.php" . ($action === 'update' ? "?edit=$id" : ''));
            exit;
        }

        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO products (name, category, price, description, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiss", $name, $category, $price, $desc, $newImage);
            $stmt->execute();
            flash('ok', "Menu \"$name\" ditambahkan.");
        } else {
            $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $old = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$old) { flash('err', 'Menu tidak ditemukan.'); header("Location: index.php"); exit; }

            $image = $newImage ?: $old['image'];
            $stmt = $conn->prepare("UPDATE products SET name=?, category=?, price=?, description=?, image=? WHERE id=?");
            $stmt->bind_param("ssissi", $name, $category, $price, $desc, $image, $id);
            $stmt->execute();
            if ($newImage) deleteImage($old['image']);
            flash('ok', "Menu \"$name\" diperbarui.");
        }
        header("Location: index.php");
        exit;
    }
    header("Location: index.php");
    exit;
}

// ---- Tampilan ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $eid = (int)$_GET['edit'];
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
$products = $conn->query("SELECT * FROM products ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - Chizu House</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-page">
    <header class="admin-header">
        <a href="../index.php" class="admin-brand"><img src="../assets/brand/chizu-house-logo.png" alt="Logo"><span>chizu house · Admin</span></a>
        <div>
            <span class="admin-user"><?= esc($_SESSION['full_name'] ?: $_SESSION['username']) ?></span>
            <a href="../index.php" class="admin-link">Lihat Toko</a>
            <a href="../logout.php" class="admin-link danger">Keluar</a>
        </div>
    </header>

    <main class="admin-main">
        <?php if ($flash): ?>
            <div class="admin-flash <?= $flash[0] === 'ok' ? 'ok' : 'err' ?>"><?= esc($flash[1]) ?></div>
        <?php endif; ?>

        <section class="admin-card">
            <h2><?= $edit ? 'Edit Menu' : 'Tambah Menu Baru' ?></h2>
            <form method="POST" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="<?= $edit ? 'update' : 'add' ?>">
                <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

                <label>Nama menu
                    <input type="text" name="name" maxlength="100" required value="<?= esc($edit['name'] ?? '') ?>">
                </label>
                <label>Kategori
                    <select name="category" required>
                        <?php foreach (CATEGORIES as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($edit['category'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Harga (Rp)
                    <input type="number" name="price" min="0" max="100000000" step="1" required value="<?= esc($edit['price'] ?? '') ?>" placeholder="20000">
                </label>
                <label class="wide">Deskripsi
                    <textarea name="description" rows="3"><?= esc($edit['description'] ?? '') ?></textarea>
                </label>
                <label class="wide">Gambar <?= $edit ? '(kosongkan jika tidak ingin mengganti)' : '' ?>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" <?= $edit ? '' : 'required' ?>>
                    <small>JPG, PNG, atau WEBP, maksimal 2 MB.</small>
                </label>
                <?php if ($edit && $edit['image']): ?>
                    <div class="wide"><img class="admin-current" src="../<?= esc($edit['image']) ?>" alt="Gambar saat ini"></div>
                <?php endif; ?>

                <div class="wide admin-actions">
                    <button type="submit" class="primary-btn"><?= $edit ? 'Simpan Perubahan' : 'Tambah Menu' ?></button>
                    <?php if ($edit): ?><a href="index.php" class="admin-link">Batal</a><?php endif; ?>
                </div>
            </form>
        </section>

        <section class="admin-card">
            <h2>Daftar Menu (<?= count($products) ?>)</h2>
            <?php if (!$products): ?>
                <p>Belum ada menu.</p>
            <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Gambar</th><th>Nama</th><th>Kategori</th><th>Harga</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><img src="../<?= esc($p['image']) ?>" alt="" class="admin-thumb"
                                     onerror="this.src='https://placehold.co/80x60?text=?'"></td>
                            <td><strong><?= esc($p['name']) ?></strong><br><small><?= esc(mb_strimwidth((string)$p['description'], 0, 70, '…')) ?></small></td>
                            <td><?= esc($p['category']) ?></td>
                            <td>Rp <?= number_format((int)$p['price'], 0, ',', '.') ?></td>
                            <td class="admin-row-actions">
                                <a href="?edit=<?= (int)$p['id'] ?>" class="admin-link">Edit</a>
                                <form method="POST" onsubmit="return confirm('Hapus menu ini?')">
                                    <input type="hidden" name="csrf" value="<?= esc($_SESSION['csrf']) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="admin-link danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
