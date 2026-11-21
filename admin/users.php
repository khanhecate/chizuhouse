<?php
require_once __DIR__ . '/_bootstrap.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$myId = (int)$_SESSION['user_id'];

// ---- Aksi POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        if ($id === $myId) {
            flash('err', 'Kamu tidak bisa menghapus akunmu sendiri.');
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            flash('ok', 'User dihapus.');
        }
        header("Location: users.php");
        exit;
    }

    if ($action === 'update' && $id > 0) {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? '';
        $newPass  = $_POST['new_password'] ?? '';

        $err = null;
        if (mb_strlen($fullName) > 100) $err = 'Nama maksimal 100 karakter.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) $err = 'Email tidak valid.';
        elseif (!in_array($role, ['user', 'admin'], true)) $err = 'Role tidak valid.';
        elseif ($id === $myId && $role !== 'admin') $err = 'Kamu tidak bisa mencabut hak admin akunmu sendiri.';
        elseif ($newPass !== '' && strlen($newPass) < 6) $err = 'Password baru minimal 6 karakter.';

        if (!$err) {
            try {
                if ($newPass !== '') {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, role=?, password_hash=? WHERE id=?");
                    $stmt->bind_param("ssssi", $fullName, $email, $role, $hash, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, role=? WHERE id=?");
                    $stmt->bind_param("sssi", $fullName, $email, $role, $id);
                }
                $stmt->execute();
                flash('ok', 'Data user diperbarui.' . ($newPass !== '' ? ' Password diganti.' : ''));
            } catch (mysqli_sql_exception $e) {
                $err = $e->getCode() === 1062 ? 'Email sudah dipakai user lain.' : 'Gagal menyimpan data user.';
            }
        }
        if ($err) {
            flash('err', $err);
            header("Location: users.php?edit=$id");
            exit;
        }
    }
    header("Location: users.php");
    exit;
}

// ---- Tampilan ----
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $conn->prepare("SELECT id, username, email, full_name, role, created_at FROM users
                            WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ? ORDER BY id DESC");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $users = $conn->query("SELECT id, username, email, full_name, role, created_at FROM users ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
}

$edit = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT id, username, email, full_name, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

admin_head('Management User', 'users');
?>
<div class="admin-split <?= $edit ? '' : 'single' ?>">
    <?php if ($edit): ?>
    <section class="admin-card">
        <h2>Edit User: <?= esc($edit['username']) ?></h2>
        <form method="POST" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <label>Username
                <input type="text" value="<?= esc($edit['username']) ?>" disabled>
            </label>
            <label>Nama lengkap
                <input type="text" name="full_name" maxlength="100" value="<?= esc($edit['full_name']) ?>">
            </label>
            <label>Email
                <input type="text" name="email" maxlength="100" required value="<?= esc($edit['email']) ?>">
            </label>
            <label>Role
                <select name="role" <?= (int)$edit['id'] === $myId ? 'disabled' : '' ?>>
                    <option value="user" <?= $edit['role'] === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="admin" <?= $edit['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
                <?php if ((int)$edit['id'] === $myId): ?>
                    <input type="hidden" name="role" value="admin">
                    <small>Role akunmu sendiri tidak bisa diubah.</small>
                <?php endif; ?>
            </label>
            <label class="wide">Password baru
                <input type="password" name="new_password" autocomplete="new-password" placeholder="Kosongkan jika tidak diganti">
                <small>Minimal 6 karakter.</small>
            </label>
            <div class="wide admin-actions">
                <button type="submit" class="primary-btn">Simpan Perubahan</button>
                <a href="users.php" class="admin-link">Batal</a>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <section class="admin-card">
        <div class="admin-card-head">
            <h2>Daftar User (<?= count($users) ?>)</h2>
            <form method="GET" class="admin-search">
                <input type="search" name="q" value="<?= esc($q) ?>" placeholder="Cari username, nama, email">
            </form>
        </div>
        <?php if (!$users): ?>
            <p class="admin-empty">User tidak ditemukan.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Username</th><th>Nama</th><th>Email</th><th>Role</th><th>Terdaftar</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td><strong><?= esc($u['username']) ?></strong><?= (int)$u['id'] === $myId ? ' <small>(kamu)</small>' : '' ?></td>
                        <td><?= esc($u['full_name']) ?></td>
                        <td><?= esc($u['email']) ?></td>
                        <td><span class="admin-badge role-<?= esc($u['role']) ?>"><?= esc($u['role']) ?></span></td>
                        <td><?= esc($u['created_at']) ?></td>
                        <td class="admin-row-actions">
                            <a href="?edit=<?= (int)$u['id'] ?>" class="admin-link">Edit</a>
                            <?php if ((int)$u['id'] !== $myId): ?>
                            <form method="POST" onsubmit="return confirm('Hapus user ini? Riwayat pesanannya tetap tersimpan.')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="admin-link danger">Hapus</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php admin_foot();
