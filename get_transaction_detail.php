<?php
header('Content-Type: application/json');
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'db_warungku_pos';
$conn = mysqli_connect($host, $user, $password, $dbname);
if (!$conn) { echo json_encode(['error'=>'Koneksi gagal']); exit; }
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo json_encode([]); exit; }
$query = "SELECT td.product_id, td.quantity, td.price, COALESCE(p.name, 'Produk #'.td.product_id) as product_name
          FROM tx_details td
          LEFT JOIN products p ON td.product_id = p.id
          WHERE td.transaction_id = $id";
$result = mysqli_query($conn, $query);
if (!$result) { echo json_encode(['error'=>mysqli_error($conn)]); exit; }
$items = [];
while($row = mysqli_fetch_assoc($result)) {
    $items[] = [
        'product_id' => $row['product_id'],
        'product_name' => $row['product_name'],
        'quantity' => (int)$row['quantity'],
        'price' => (float)$row['price']
    ];
}
echo json_encode($items);
?>