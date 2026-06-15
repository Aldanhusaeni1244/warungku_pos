<?php
header('Content-Type: application/json');
error_reporting(0);

$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'db_warungku_pos';

// Coba koneksi dengan port default 3306
$conn = mysqli_connect($host, $user, $password, $dbname);
if (!$conn) {
    // Jika gagal, coba dengan port yang umum (3306 sudah default)
    // Tampilkan pesan error yang jelas
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal: ' . mysqli_connect_error() . '. Pastikan MySQL di XAMPP sedang berjalan.']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ========== LOGIN (MD5) ==========
if ($action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim(mysqli_real_escape_string($conn, $input['username']));
    $password = trim($input['password']);
    $hashed = md5($password);
    $query = "SELECT * FROM users WHERE username = '$username' AND status = 'active' AND password = '$hashed'";
    $res = mysqli_query($conn, $query);
    if ($res && mysqli_num_rows($res) > 0) {
        $user = mysqli_fetch_assoc($res);
        unset($user['password']);
        echo json_encode(['status' => 'success', 'data' => $user]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Username atau password salah']);
    }
    exit;
}

// ========== DASHBOARD STATS ==========
if ($action === 'get_stats') {
    $today = date('Y-m-d');
    $revenueQuery = "SELECT COALESCE(SUM(total),0) as total FROM transactions WHERE date = '$today'";
    $revenueRes = mysqli_query($conn, $revenueQuery);
    $todayRevenue = $revenueRes ? mysqli_fetch_assoc($revenueRes)['total'] : 0;

    $productQuery = "SELECT COUNT(*) as total FROM products";
    $productRes = mysqli_query($conn, $productQuery);
    $totalProducts = $productRes ? mysqli_fetch_assoc($productRes)['total'] : 0;

    $customerQuery = "SELECT COUNT(*) as total FROM customers";
    $customerRes = mysqli_query($conn, $customerQuery);
    $totalCustomers = $customerRes ? mysqli_fetch_assoc($customerRes)['total'] : 0;

    $recentQuery = "SELECT t.invoiceNo, t.total, t.customerId, u.name as cashierName 
                    FROM transactions t 
                    LEFT JOIN users u ON t.cashierId = u.id 
                    ORDER BY t.date DESC, t.time DESC LIMIT 5";
    $recentRes = mysqli_query($conn, $recentQuery);
    $recent = [];
    if ($recentRes) while ($row = mysqli_fetch_assoc($recentRes)) $recent[] = $row;

    $lowStockQuery = "SELECT name, stock FROM products WHERE stock <= minStock ORDER BY stock LIMIT 5";
    $lowRes = mysqli_query($conn, $lowStockQuery);
    $lowStock = [];
    if ($lowRes) while ($row = mysqli_fetch_assoc($lowRes)) $lowStock[] = $row;

    echo json_encode([
        'status' => 'success',
        'data' => [
            'today_revenue' => $todayRevenue,
            'total_products' => $totalProducts,
            'total_customers' => $totalCustomers,
            'recent_transactions' => $recent,
            'low_stock' => $lowStock
        ]
    ]);
    exit;
}

// ========== PRODUK ==========
if ($action === 'get_products') {
    $query = "SELECT p.*, c.name as categoryName FROM products p LEFT JOIN categories c ON p.categoryId = c.id ORDER BY p.name";
    $res = mysqli_query($conn, $query);
    $data = [];
    if ($res) while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

if ($action === 'save_product') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $name = mysqli_real_escape_string($conn, $data['name']);
    $categoryId = (int)$data['categoryId'];
    $barcode = mysqli_real_escape_string($conn, $data['barcode']);
    $cost = (int)$data['cost'];
    $price = (int)$data['price'];
    $stock = (int)$data['stock'];
    $minStock = (int)$data['minStock'];
    $unit = mysqli_real_escape_string($conn, $data['unit']);

    if ($id > 0) {
        $query = "UPDATE products SET name='$name', categoryId=$categoryId, barcode='$barcode', cost=$cost, price=$price, stock=$stock, minStock=$minStock, unit='$unit' WHERE id=$id";
    } else {
        $query = "INSERT INTO products (name, categoryId, barcode, cost, price, stock, minStock, unit) VALUES ('$name', $categoryId, '$barcode', $cost, $price, $stock, $minStock, '$unit')";
    }
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'message' => $id > 0 ? 'Produk diperbarui' : 'Produk ditambahkan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

if ($action === 'delete_product') {
    $id = (int)$_GET['id'];
    $query = "DELETE FROM products WHERE id=$id";
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'message' => 'Produk dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

// ========== KATEGORI ==========
if ($action === 'get_categories') {
    $query = "SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.categoryId GROUP BY c.id ORDER BY c.name";
    $res = mysqli_query($conn, $query);
    $data = [];
    if ($res) while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

if ($action === 'create_category') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = mysqli_real_escape_string($conn, $data['name']);
    $desc = mysqli_real_escape_string($conn, $data['desc']);
    $query = "INSERT INTO categories (name, `desc`) VALUES ('$name', '$desc')";
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'message' => 'Kategori ditambahkan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

if ($action === 'update_category') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)$data['id'];
    $name = mysqli_real_escape_string($conn, $data['name']);
    $desc = mysqli_real_escape_string($conn, $data['desc']);
    $query = "UPDATE categories SET name='$name', `desc`='$desc' WHERE id=$id";
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'message' => 'Kategori diperbarui']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

if ($action === 'delete_category') {
    $id = (int)$_GET['id'];
    $check = "SELECT COUNT(*) as total FROM products WHERE categoryId=$id";
    $res = mysqli_query($conn, $check);
    $row = mysqli_fetch_assoc($res);
    if ($row['total'] > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Kategori tidak bisa dihapus karena masih memiliki produk']);
        exit;
    }
    $query = "DELETE FROM categories WHERE id=$id";
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'message' => 'Kategori dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

// ========== DISKON DINAMIS ==========
if ($action === 'calculate_discount') {
    $subtotal = (float)$_GET['subtotal'];
    $discount = 0;
    $discount_type = 'flat';
    $discount_value = 0;
    $today = date('Y-m-d');
    $query = "SELECT * FROM discount_rules WHERE is_active = 1 
              AND (start_date IS NULL OR start_date <= '$today')
              AND (end_date IS NULL OR end_date >= '$today')
              AND min_purchase <= $subtotal
              ORDER BY min_purchase DESC LIMIT 1";
    $res = mysqli_query($conn, $query);
    if ($res && mysqli_num_rows($res) > 0) {
        $rule = mysqli_fetch_assoc($res);
        $discount_type = $rule['type'];
        $discount_value = (float)$rule['value'];
        if ($discount_type == 'percent') {
            $discount = $subtotal * ($discount_value / 100);
            if ($rule['max_discount'] && $discount > $rule['max_discount']) {
                $discount = $rule['max_discount'];
            }
        } else {
            $discount = $discount_value;
        }
        $discount = min($discount, $subtotal);
    }
    $total = $subtotal - $discount;
    echo json_encode([
        'status' => 'success',
        'data' => [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'discount_type' => $discount_type,
            'discount_value' => $discount_value,
            'total' => $total
        ]
    ]);
    exit;
}

// ========== SIMPAN TRANSAKSI ==========
if ($action === 'save_transaction') {
    $data = json_decode(file_get_contents('php://input'), true);
    $invoiceNo = mysqli_real_escape_string($conn, $data['invoiceNo']);
    $date = $data['date'];
    $time = $data['time'];
    $cashierId = (int)$data['cashierId'];
    $customerId = isset($data['customerId']) && $data['customerId'] ? (int)$data['customerId'] : 'NULL';
    $subtotal = (float)$data['subtotal'];
    $discount_type = mysqli_real_escape_string($conn, $data['discount_type']);
    $discount_value = (float)$data['discount_value'];
    $discount = (float)$data['discount'];
    $total = (float)$data['total'];
    $paid = (float)$data['paid'];
    $change = (float)$data['change'];
    $status = 'completed';

    $query = "INSERT INTO transactions (invoiceNo, date, time, cashierId, customerId, subtotal, discount_type, discount_value, discount, total, paid, `change`, status) 
              VALUES ('$invoiceNo', '$date', '$time', $cashierId, $customerId, $subtotal, '$discount_type', $discount_value, $discount, $total, $paid, $change, '$status')";
    if (mysqli_query($conn, $query)) {
        $transId = mysqli_insert_id($conn);
        foreach ($data['items'] as $item) {
            $productId = (int)$item['productId'];
            $productName = mysqli_real_escape_string($conn, $item['name']);
            $qty = (int)$item['qty'];
            $price = (float)$item['price'];
            $sub = (float)$item['subtotal'];
            $detailQuery = "INSERT INTO tx_details (txId, productId, productName, qty, price, subtotal) VALUES ($transId, $productId, '$productName', $qty, $price, $sub)";
            mysqli_query($conn, $detailQuery);
            $updateStock = "UPDATE products SET stock = stock - $qty WHERE id = $productId";
            mysqli_query($conn, $updateStock);
        }
        echo json_encode(['status' => 'success', 'message' => 'Transaksi berhasil', 'invoiceNo' => $invoiceNo]);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

// ========== RIWAYAT TRANSAKSI ==========
if ($action === 'get_transactions') {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $where = "WHERE 1=1";
    if (!empty($search)) $where .= " AND t.invoiceNo LIKE '%$search%'";
    if (!empty($start_date)) $where .= " AND t.date >= '$start_date'";
    if (!empty($end_date)) $where .= " AND t.date <= '$end_date'";

    $countQuery = "SELECT COUNT(*) as total FROM transactions t $where";
    $countRes = mysqli_query($conn, $countQuery);
    $total = mysqli_fetch_assoc($countRes)['total'];
    $totalPages = ceil($total / $limit);

    $query = "SELECT t.id, t.invoiceNo, t.date, t.time, 
                     COALESCE(u.name, 'Kasir') as kasir,
                     COALESCE(c.name, 'Umum') as pelanggan,
                     t.subtotal, t.discount, t.discount_type, t.total, t.paid, t.change, t.status,
                     (SELECT COUNT(*) FROM tx_details WHERE txId = t.id) as items
              FROM transactions t
              LEFT JOIN users u ON t.cashierId = u.id
              LEFT JOIN customers c ON t.customerId = c.id
              $where
              ORDER BY t.date DESC, t.time DESC
              LIMIT $limit OFFSET $offset";
    $res = mysqli_query($conn, $query);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    echo json_encode(['status' => 'success', 'data' => $data, 'total' => $total, 'totalPages' => $totalPages, 'currentPage' => $page]);
    exit;
}

if ($action === 'get_transaction_detail') {
    $id = (int)$_GET['id'];
    $query = "SELECT td.productId, td.qty as quantity, td.price, td.productName as product_name
              FROM tx_details td
              WHERE td.txId = $id";
    $res = mysqli_query($conn, $query);
    $items = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $items[] = [
            'product_id' => $row['productId'],
            'product_name' => $row['product_name'],
            'quantity' => (int)$row['quantity'],
            'price' => (float)$row['price']
        ];
    }
    echo json_encode(['status' => 'success', 'items' => $items]);
    exit;
}

if ($action === 'export_transactions') {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

    $where = "WHERE 1=1";
    if (!empty($search)) $where .= " AND t.invoiceNo LIKE '%$search%'";
    if (!empty($start_date)) $where .= " AND t.date >= '$start_date'";
    if (!empty($end_date)) $where .= " AND t.date <= '$end_date'";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="transactions.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['NO. TRANSAKSI', 'TANGGAL', 'KASIR', 'PELANGGAN', 'ITEMS', 'SUBTOTAL', 'DISKON', 'TIPE DISKON', 'TOTAL', 'BAYAR', 'KEMBALIAN']);

    $query = "SELECT t.invoiceNo, t.date, t.time, 
                     COALESCE(u.name,'-') as kasir, 
                     COALESCE(c.name,'Umum') as pelanggan,
                     (SELECT COUNT(*) FROM tx_details WHERE txId = t.id) as items,
                     t.subtotal, t.discount, t.discount_type, t.total, t.paid, t.change
              FROM transactions t
              LEFT JOIN users u ON t.cashierId = u.id
              LEFT JOIN customers c ON t.customerId = c.id
              $where
              ORDER BY t.date DESC";
    $res = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($res)) {
        fputcsv($out, [
            $row['invoiceNo'],
            date('d/m/Y', strtotime($row['date'])) . ' ' . $row['time'],
            $row['kasir'],
            $row['pelanggan'],
            $row['items'],
            $row['subtotal'],
            $row['discount'],
            $row['discount_type'],
            $row['total'],
            $row['paid'],
            $row['change']
        ]);
    }
    fclose($out);
    exit;
}

// ========== CUSTOMERS (CRM) ==========
if ($action === 'get_customers') {
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
    $where = "";
    if (!empty($search)) $where = "WHERE name LIKE '%$search%' OR phone LIKE '%$search%'";
    $query = "SELECT id, name, phone, email, address, createdAt FROM customers $where ORDER BY name";
    $res = mysqli_query($conn, $query);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as tx_count, COALESCE(SUM(total),0) as total_spent FROM transactions WHERE customerId = ".$row['id']));
        $row['total_transactions'] = $stats['tx_count'];
        $row['total_spent'] = $stats['total_spent'];
        $data[] = $row;
    }
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

if ($action === 'save_customer') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $name = mysqli_real_escape_string($conn, $data['name']);
    $phone = mysqli_real_escape_string($conn, $data['phone']);
    $email = mysqli_real_escape_string($conn, $data['email']);
    $address = mysqli_real_escape_string($conn, $data['address']);
    $createdAt = date('Y-m-d');
    
    if ($id > 0) {
        $query = "UPDATE customers SET name='$name', phone='$phone', email='$email', address='$address' WHERE id=$id";
    } else {
        $query = "INSERT INTO customers (name, phone, email, address, createdAt) VALUES ('$name', '$phone', '$email', '$address', '$createdAt')";
    }
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'message' => $id > 0 ? 'Pelanggan diperbarui' : 'Pelanggan ditambahkan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

if ($action === 'delete_customer') {
    $id = (int)$_GET['id'];
    $check = "SELECT COUNT(*) as total FROM transactions WHERE customerId=$id";
    $res = mysqli_query($conn, $check);
    $row = mysqli_fetch_assoc($res);
    if ($row['total'] > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Pelanggan tidak bisa dihapus karena memiliki riwayat transaksi']);
        exit;
    }
    $query = "DELETE FROM customers WHERE id=$id";
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'message' => 'Pelanggan dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

if ($action === 'get_customer_detail') {
    $id = (int)$_GET['id'];
    $custQuery = "SELECT * FROM customers WHERE id=$id";
    $custRes = mysqli_query($conn, $custQuery);
    $customer = mysqli_fetch_assoc($custRes);
    
    $txQuery = "SELECT t.invoiceNo, t.date, t.time, t.total, u.name as kasir
                FROM transactions t
                LEFT JOIN users u ON t.cashierId = u.id
                WHERE t.customerId = $id
                ORDER BY t.date DESC, t.time DESC";
    $txRes = mysqli_query($conn, $txQuery);
    $transactions = [];
    while ($row = mysqli_fetch_assoc($txRes)) $transactions[] = $row;
    
    echo json_encode(['status' => 'success', 'customer' => $customer, 'transactions' => $transactions]);
    exit;
}

// ========== USERS (PENGGUNA) ==========
if ($action === 'get_users') {
    $query = "SELECT id, name, username, role, status, createdAt FROM users ORDER BY name";
    $res = mysqli_query($conn, $query);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

if ($action === 'save_user') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $name = mysqli_real_escape_string($conn, $data['name']);
    $username = mysqli_real_escape_string($conn, $data['username']);
    $role = mysqli_real_escape_string($conn, $data['role']);
    $status = mysqli_real_escape_string($conn, $data['status']);
    $password = isset($data['password']) ? $data['password'] : '';
    $hashed = !empty($password) ? md5($password) : '';
    $createdAt = date('Y-m-d');
    
    if ($id > 0) {
        if (!empty($password)) {
            $query = "UPDATE users SET name='$name', username='$username', role='$role', status='$status', password='$hashed' WHERE id=$id";
        } else {
            $query = "UPDATE users SET name='$name', username='$username', role='$role', status='$status' WHERE id=$id";
        }
    } else {
        if (empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Password wajib diisi untuk pengguna baru']);
            exit;
        }
        $query = "INSERT INTO users (name, username, password, role, status, createdAt) VALUES ('$name', '$username', '$hashed', '$role', '$status', '$createdAt')";
    }
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'message' => $id > 0 ? 'Pengguna diperbarui' : 'Pengguna ditambahkan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

if ($action === 'delete_user') {
    $id = (int)$_GET['id'];
    if ($id == 1) {
        echo json_encode(['status' => 'error', 'message' => 'User admin utama tidak bisa dihapus']);
        exit;
    }
    $check = "SELECT COUNT(*) as total FROM transactions WHERE cashierId=$id";
    $res = mysqli_query($conn, $check);
    $row = mysqli_fetch_assoc($res);
    if ($row['total'] > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Pengguna tidak bisa dihapus karena memiliki riwayat transaksi']);
        exit;
    }
    $query = "DELETE FROM users WHERE id=$id";
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success', 'message' => 'Pengguna dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

// ========== LAPORAN PENJUALAN ==========
if ($action === 'get_reports') {
    $period = isset($_GET['period']) ? $_GET['period'] : 'month';
    $today = date('Y-m-d');
    if ($period == 'today') {
        $startDate = $today;
        $endDate = $today;
    } elseif ($period == 'week') {
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = $today;
    } elseif ($period == 'month') {
        $startDate = date('Y-m-01');
        $endDate = $today;
    } else {
        $startDate = '1970-01-01';
        $endDate = $today;
    }
    
    $query = "SELECT * FROM v_laporan_penjualan WHERE date BETWEEN '$startDate' AND '$endDate' ORDER BY date DESC";
    $res = mysqli_query($conn, $query);
    $transactions = [];
    while ($row = mysqli_fetch_assoc($res)) $transactions[] = $row;
    
    $totalRevenue = 0;
    $totalTransactions = count($transactions);
    foreach ($transactions as $tx) { $totalRevenue += $tx['total']; }
    $average = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;
    
    $topQuery = "SELECT td.productName as name, SUM(td.qty) as terjual, SUM(td.subtotal) as pendapatan
                 FROM tx_details td
                 JOIN transactions t ON td.txId = t.id
                 WHERE t.date BETWEEN '$startDate' AND '$endDate'
                 GROUP BY td.productId, td.productName
                 ORDER BY terjual DESC LIMIT 5";
    $topRes = mysqli_query($conn, $topQuery);
    $topProducts = [];
    while ($row = mysqli_fetch_assoc($topRes)) $topProducts[] = $row;
    
    $chartQuery = "SELECT date, SUM(total) as total FROM transactions WHERE date BETWEEN '$startDate' AND '$endDate' GROUP BY date ORDER BY date";
    $chartRes = mysqli_query($conn, $chartQuery);
    $chartData = [];
    while ($row = mysqli_fetch_assoc($chartRes)) $chartData[] = $row;
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'transactions' => $transactions,
            'total_revenue' => $totalRevenue,
            'total_transactions' => $totalTransactions,
            'average' => $average,
            'top_products' => $topProducts,
            'chart_data' => $chartData
        ]
    ]);
    exit;
}

if ($action === 'export_report') {
    $period = isset($_GET['period']) ? $_GET['period'] : 'month';
    $today = date('Y-m-d');
    if ($period == 'today') {
        $startDate = $today;
        $endDate = $today;
    } elseif ($period == 'week') {
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = $today;
    } elseif ($period == 'month') {
        $startDate = date('Y-m-01');
        $endDate = $today;
    } else {
        $startDate = '1970-01-01';
        $endDate = $today;
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_penjualan.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['NO. TRANSAKSI', 'TANGGAL', 'KASIR', 'PELANGGAN', 'SUBTOTAL', 'DISKON', 'TOTAL', 'BAYAR', 'KEMBALIAN']);
    
    $query = "SELECT * FROM v_laporan_penjualan WHERE date BETWEEN '$startDate' AND '$endDate' ORDER BY date DESC";
    $res = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($res)) {
        fputcsv($out, [
            $row['invoiceNo'],
            date('d/m/Y', strtotime($row['date'])) . ' ' . $row['time'],
            $row['kasir'],
            $row['pelanggan'],
            $row['subtotal'],
            $row['discount'],
            $row['total'],
            $row['paid'],
            $row['change']
        ]);
    }
    fclose($out);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action tidak ditemukan']);
?>