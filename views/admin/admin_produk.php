<?php
require __DIR__ . '/../../config/database.php'; // koneksi database

// Pastikan hanya admin toko yang bisa akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin_toko') {
    header("Location: ../views/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil data toko admin
$stmt = $pdo->prepare("SELECT * FROM toko WHERE admin_id = ?");
$stmt->execute([$user_id]);
$toko = $stmt->fetch();

if (!$toko) {
    die("Toko tidak ditemukan. Hubungi admin.");
}

$toko_id = $toko['id'];

// Proses tambah produk
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_barang = $_POST['nama_barang'];
    $deskripsi = $_POST['deskripsi'];
    $harga = (int) $_POST['harga'];
    $stok = (int) $_POST['stok'];
    $kategori_nama = trim($_POST['kategori_nama']);

    // Cek kategori, buat baru jika belum ada
    $kategori_id = null;
    if ($kategori_nama !== '') {
        $stmt = $pdo->prepare("SELECT id FROM kategori WHERE LOWER(nama_kategori) = LOWER(?)");
        $stmt->execute([$kategori_nama]);
        $kategori = $stmt->fetch();
        if ($kategori) {
            $kategori_id = $kategori['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
            $stmt->execute([$kategori_nama]);
            $kategori_id = $pdo->lastInsertId();
        }
    }

    // Upload gambar
    $gambar = null;
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
        $uploadDir = __DIR__ . '/../../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = time() . '-' . basename($_FILES['gambar']['name']);
        $targetFile = $uploadDir . $filename;

        // Validasi ekstensi file gambar
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) {
            $error_message = "Format gambar tidak didukung. Hanya jpg, jpeg, png, gif, webp.";
        } elseif (move_uploaded_file($_FILES['gambar']['tmp_name'], $targetFile)) {
            $gambar = $filename;
        } else {
            $error_message = "Upload gambar gagal.";
        }
    }

    if (!$error_message) {
        $stmt = $pdo->prepare("INSERT INTO barang (toko_id, kategori_id, nama_barang, deskripsi, harga, stok, gambar) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$toko_id, $kategori_id, $nama_barang, $deskripsi, $harga, $stok, $gambar]);
        $success_message = "Produk berhasil ditambahkan.";
    }
}

// Ambil produk toko
$stmt = $pdo->prepare("SELECT b.*, k.nama_kategori FROM barang b LEFT JOIN kategori k ON b.kategori_id = k.id WHERE b.toko_id = ?");
$stmt->execute([$toko_id]);
$produk = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kelola Produk - <?= htmlspecialchars($toko['nama_toko']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 text-gray-800 p-6">
    <?php include __DIR__ . '../components/header.php'; ?>
    <div class="p-4 sm:ml-64">
        <div class="p-4 border-2 border-gray-200 border-dashed rounded-lg mt-14 bg-white">
            <div class="">

                <h1 class="text-2xl font-bold mb-4">Produk Toko: <?= htmlspecialchars($toko['nama_toko']) ?></h1>

                <?php if ($success_message): ?>
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded">
                        <?= $success_message ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded">
                        <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white p-6 rounded shadow mb-8">
                    <h2 class="text-xl font-semibold mb-4">Tambah Produk Baru</h2>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label class="block font-medium">Nama Barang</label>
                            <input type="text" name="nama_barang" required class="w-full border rounded px-3 py-2">
                        </div>
                        <div>
                            <label class="block font-medium">Kategori</label>
                            <input type="text" name="kategori_nama" required class="w-full border rounded px-3 py-2" placeholder="Masukkan nama kategori">
                        </div>
                        <div>
                            <label class="block font-medium">Deskripsi</label>
                            <textarea name="deskripsi" rows="3" class="w-full border rounded px-3 py-2"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block font-medium">Harga</label>
                                <input type="number" name="harga" required class="w-full border rounded px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-medium">Stok</label>
                                <input type="number" name="stok" required class="w-full border rounded px-3 py-2">
                            </div>
                        </div>
                        <div>
                            <label class="block font-medium">Gambar</label>
                            <input type="file" name="gambar" accept="image/*" class="block w-full text-sm">
                        </div>
                        <div>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded">
                                Tambah Produk
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Daftar produk -->
                <div class="bg-white p-6 rounded shadow">
                    <h2 class="text-lg font-semibold mb-4">Daftar Produk</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 border">Nama Barang</th>
                                    <th class="px-4 py-2 border">Kategori</th>
                                    <th class="px-4 py-2 border">Harga</th>
                                    <th class="px-4 py-2 border">Stok</th>
                                    <th class="px-4 py-2 border">Gambar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produk as $row): ?>
                                    <tr>
                                        <td class="px-4 py-2 border"><?= htmlspecialchars($row['nama_barang']) ?></td>
                                        <td class="px-4 py-2 border"><?= htmlspecialchars($row['nama_kategori'] ?? '-') ?></td>
                                        <td class="px-4 py-2 border">Rp<?= number_format($row['harga'], 0, ',', '.') ?></td>
                                        <td class="px-4 py-2 border"><?= $row['stok'] ?></td>
                                        <td class="px-4 py-2 border">
                                            <?php if (!empty($row['gambar']) && file_exists(__DIR__ . '/../../uploads/' . $row['gambar'])): ?>
                                                <img src="/marketplace/uploads/<?= htmlspecialchars($row['gambar']) ?>" alt="Gambar" class="w-16 h-16 object-cover rounded">
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- End daftar produk -->
            </div>
        </div>
    </div>
</body>
</html>