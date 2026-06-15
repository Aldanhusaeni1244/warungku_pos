<?php
// ========== KONEKSI DATABASE ==========
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'db_warungku_pos';

$conn = mysqli_connect($host, $user, $password, $dbname);
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// ========== FUNGSI BANTU ==========
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}
function formatTanggal($tanggal) {
    return date('d/m/Y', strtotime($tanggal));
}

// ========== CEK STRUKTUR TABEL & SESUAIKAN ==========
// Ambil daftar kolom di tabel transactions
$columns = [];
$colQuery = "SHOW COLUMNS FROM transactions";
$colRes = mysqli_query($conn, $colQuery);
if ($colRes) {
    while ($col = mysqli_fetch_assoc($colRes)) {
        $columns[] = $col['Field'];
    }
}

// Mapping kolom yang mungkin berbeda
$invoiceCol = in_array('invoiceNo', $columns) ? 'invoiceNo' : (in_array('invoice_number', $columns) ? 'invoice_number' : 'id');
$cashierCol = in_array('cashierId', $columns) ? 'cashierId' : (in_array('cashier_id', $columns) ? 'cashier_id' : 'user_id');
$customerCol = in_array('customerId', $columns) ? 'customerId' : (in_array('customer_id', $columns) ? 'customer_id' : 'customer_id');
$discountTypeCol = in_array('discount_type', $columns) ? 'discount_type' : 'discount_type';

// ========== FILTER & PAGING ==========
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where = "WHERE 1=1";
if (!empty($search)) {
    $where .= " AND t.$invoiceCol LIKE '%$search%'";
}
if (!empty($start_date)) {
    $where .= " AND t.date >= '$start_date'";
}
if (!empty($end_date)) {
    $where .= " AND t.date <= '$end_date'";
}

// Total data
$totalQuery = "SELECT COUNT(*) as total FROM transactions t $where";
$totalRes = mysqli_query($conn, $totalQuery);
$totalRow = mysqli_fetch_assoc($totalRes);
$totalRows = $totalRow['total'];
$totalPages = ceil($totalRows / $limit);

// Query utama
$query = "SELECT 
            t.id, 
            t.$invoiceCol as invoiceNo, 
            t.date, 
            t.time, 
            COALESCE(u.name, 'Kasir') as kasir,
            COALESCE(c.name, 'Umum') as pelanggan,
            t.subtotal, 
            t.discount, 
            t.$discountTypeCol as discount_type, 
            t.total, 
            t.paid, 
            t.change, 
            t.status,
            (SELECT COUNT(*) FROM tx_details WHERE transaction_id = t.id) as items
          FROM transactions t
          LEFT JOIN users u ON t.$cashierCol = u.id
          LEFT JOIN customers c ON t.$customerCol = c.id
          $where
          ORDER BY t.date DESC, t.time DESC
          LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// ========== EKSPOR CSV ==========
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="transactions.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['NO. TRANSAKSI', 'TANGGAL', 'KASIR', 'PELANGGAN', 'ITEMS', 'SUBTOTAL', 'DISKON', 'TIPE', 'TOTAL', 'BAYAR', 'KEMBALIAN']);
    $expQuery = "SELECT t.$invoiceCol as invoiceNo, t.date, COALESCE(u.name,'-') as kasir, COALESCE(c.name,'Umum') as pelanggan,
                    (SELECT COUNT(*) FROM tx_details WHERE transaction_id = t.id) as items,
                    t.subtotal, t.discount, t.$discountTypeCol as discount_type, t.total, t.paid, t.change
                 FROM transactions t
                 LEFT JOIN users u ON t.$cashierCol = u.id
                 LEFT JOIN customers c ON t.$customerCol = c.id
                 $where";
    $expRes = mysqli_query($conn, $expQuery);
    while ($row = mysqli_fetch_assoc($expRes)) {
        fputcsv($out, [
            $row['invoiceNo'], formatTanggal($row['date']), $row['kasir'], $row['pelanggan'],
            $row['items'], $row['subtotal'], $row['discount'], $row['discount_type'],
            $row['total'], $row['paid'], $row['change']
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Transaksi - WARTAN POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100">
<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-4 flex items-center gap-2"><i class="fa-solid fa-receipt"></i> Riwayat Transaksi</h1>

    <!-- Filter -->
    <div class="bg-white p-4 rounded shadow mb-6">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div><label class="block text-xs text-gray-500">Cari no. transaksi</label><input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="border p-2 rounded w-64"></div>
            <div><label class="block text-xs text-gray-500">Dari tanggal</label><input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="border p-2 rounded"></div>
            <div><label class="block text-xs text-gray-500">Sampai tanggal</label><input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="border p-2 rounded"></div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded"><i class="fas fa-search"></i> Filter</button>
            <a href="?" class="bg-gray-300 px-4 py-2 rounded">Reset</a>
            <a href="?export=1&<?= http_build_query(['search'=>$search, 'start_date'=>$start_date, 'end_date'=>$end_date]) ?>" class="bg-emerald-600 text-white px-4 py-2 rounded"><i class="fas fa-download"></i> Export CSV</a>
        </form>
    </div>

    <!-- Tabel -->
    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100">
                <tr><th class="p-2">NO. TRANSAKSI</th><th class="p-2">TANGGAL</th><th class="p-2">KASIR</th><th class="p-2">PELANGGAN</th><th class="p-2 text-center">ITEMS</th><th class="p-2 text-right">SUBTOTAL</th><th class="p-2 text-right">DISKON</th><th class="p-2 text-center">TIPE</th><th class="p-2 text-right">TOTAL</th><th class="p-2 text-right">BAYAR</th><th class="p-2 text-right">KEMBALIAN</th><th class="p-2 text-center">AKSI</th></tr></thead>
            <tbody>
                <?php if (mysqli_num_rows($result) == 0): ?>
                <tr><td colspan="12" class="text-center p-4">Tidak ada data transaksi</td></tr>
                <?php else: while ($row = mysqli_fetch_assoc($result)): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2"><?= htmlspecialchars($row['invoiceNo']) ?></td>
                    <td class="p-2"><?= formatTanggal($row['date']) . ' ' . $row['time'] ?></td>
                    <td class="p-2"><?= htmlspecialchars($row['kasir']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($row['pelanggan']) ?></td>
                    <td class="p-2 text-center"><?= $row['items'] ?></td>
                    <td class="p-2 text-right"><?= formatRupiah($row['subtotal']) ?></td>
                    <td class="p-2 text-right text-red-600"><?= formatRupiah($row['discount']) ?></td>
                    <td class="p-2 text-center uppercase"><?= $row['discount_type'] ?></td>
                    <td class="p-2 text-right font-bold"><?= formatRupiah($row['total']) ?></td>
                    <td class="p-2 text-right"><?= formatRupiah($row['paid']) ?></td>
                    <td class="p-2 text-right text-green-700"><?= formatRupiah($row['change']) ?></td>
                    <td class="p-2 text-center"><button onclick="showDetail(<?= htmlspecialchars(json_encode($row)) ?>)" class="text-blue-600"><i class="fas fa-eye"></i> Detail</button></td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-between mt-4 items-center">
        <span class="text-sm">Halaman <?= $page ?> dari <?= $totalPages ?></span>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="border px-3 py-1 rounded hover:bg-gray-100">Prev</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="border px-3 py-1 rounded hover:bg-gray-100">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Detail -->
<div id="detailModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between border-b px-6 py-4"><h3 class="text-lg font-semibold">Detail Transaksi</h3><button onclick="closeModal()" class="text-gray-500"><i class="fas fa-times"></i></button></div>
        <div class="p-6" id="modalContent"></div>
        <div class="border-t px-6 py-4 flex justify-end"><button onclick="closeModal()" class="bg-gray-200 px-4 py-2 rounded">Tutup</button></div>
    </div>
</div>

<script>
async function showDetail(tx) {
    document.getElementById('modalContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>';
    document.getElementById('detailModal').classList.remove('hidden');
    document.getElementById('detailModal').classList.add('flex');
    try {
        let res = await fetch(`get_transaction_detail.php?id=${tx.id}`);
        let items = await res.json();
        let rows = '';
        if (items.length === 0) rows = '<tr><td colspan="3" class="text-center">Tidak ada item</td></tr>';
        else items.forEach(item => {
            rows += `<tr class="border-b"><td class="px-2 py-1">${escapeHtml(item.product_name)}</td><td class="px-2 py-1 text-center">${item.quantity}</td><td class="px-2 py-1 text-right">${formatRupiah(item.price)}</td><td class="px-2 py-1 text-right">${formatRupiah(item.quantity * item.price)}</td></tr>`;
        });
        let html = `<div class="grid grid-cols-2 gap-3 text-sm mb-4"><div><span class="font-medium">No. Transaksi:</span> ${escapeHtml(tx.invoiceNo)}</div><div><span class="font-medium">Tanggal:</span> ${tx.date} ${tx.time}</div><div><span class="font-medium">Kasir:</span> ${escapeHtml(tx.kasir)}</div><div><span class="font-medium">Pelanggan:</span> ${escapeHtml(tx.pelanggan)}</div><div><span class="font-medium">Status:</span> ${tx.status}</div></div>
        <div class="mb-4"><h4 class="font-medium mb-2">Item yang dibeli:</h4><table class="min-w-full text-sm"><thead class="bg-gray-100"><tr><th class="px-2 py-1 text-left">Nama</th><th class="px-2 py-1 text-center">Qty</th><th class="px-2 py-1 text-right">Harga</th><th class="px-2 py-1 text-right">Subtotal</th></tr></thead><tbody>${rows}</tbody></table></div>
        <div class="border-t pt-3 space-y-1 text-sm"><div class="flex justify-between"><span>Subtotal:</span><span>${formatRupiah(tx.subtotal)}</span></div><div class="flex justify-between text-red-600"><span>Diskon (${tx.discount_type}):</span><span>${formatRupiah(tx.discount)}</span></div><div class="flex justify-between font-bold"><span>Total:</span><span>${formatRupiah(tx.total)}</span></div><div class="flex justify-between"><span>Bayar:</span><span>${formatRupiah(tx.paid)}</span></div><div class="flex justify-between text-green-700"><span>Kembalian:</span><span>${formatRupiah(tx.change)}</span></div></div>`;
        document.getElementById('modalContent').innerHTML = html;
    } catch(e) { document.getElementById('modalContent').innerHTML = '<div class="text-red-500">Gagal memuat detail</div>'; }
}
function closeModal() { document.getElementById('detailModal').classList.add('hidden'); document.getElementById('detailModal').classList.remove('flex'); }
function formatRupiah(angka) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(angka); }
function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }
</script>
</body>
</html>
<?php mysqli_close($conn); ?>