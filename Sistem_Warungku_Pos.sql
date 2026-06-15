-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 12 Jun 2026 pada 14.24
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `TA_PBD_4SI_Kelompok4_warungku_pos`
--
CREATE DATABASE IF NOT EXISTS `TA_PBD_4SI_Kelompok4_warungku_pos` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `TA_PBD_4SI_Kelompok4_warungku_pos`;

DELIMITER $$
--
-- Prosedur
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `restok_otomatis` ()   BEGIN
    DECLARE selesai INT DEFAULT 0;
    DECLARE v_id INT;
    DECLARE v_stok INT;
    DECLARE v_min_stok INT;
    
    DECLARE cur CURSOR FOR SELECT id, stock, minStock FROM products WHERE stock < minStock;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET selesai = 1;
        
    OPEN cur;
    FETCH cur INTO v_id, v_stok, v_min_stok;
    
    WHILE selesai = 0 DO
        IF v_stok < v_min_stok THEN
            UPDATE products SET stock = v_stok + (v_min_stok * 2) WHERE id = v_id;
        END IF;
        FETCH cur INTO v_id, v_stok, v_min_stok;
    END WHILE;
    CLOSE cur;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `update_status_stok` ()   BEGIN
    DECLARE selesai INT DEFAULT 0;
    DECLARE v_id INT;
    DECLARE v_stok INT;
    DECLARE v_min_stok INT;
    
    DECLARE cur CURSOR FOR SELECT id, stock, minStock FROM products;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET selesai = 1;
      
    OPEN cur;
    my_loop: LOOP
        FETCH cur INTO v_id, v_stok, v_min_stok;
        IF selesai = 1 THEN LEAVE my_loop; END IF;
        
        IF v_stok = 0 THEN
            UPDATE products SET status = 'HABIS' WHERE id = v_id;
        ELSEIF v_stok <= v_min_stok THEN
            UPDATE products SET status = 'STOK MENIPIS' WHERE id = v_id;
        ELSE
            UPDATE products SET status = 'TERSEDIA' WHERE id = v_id;
        END IF;
            END LOOP;
    CLOSE cur;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `categories`
--

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `desc` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `categories`
--

INSERT INTO `categories` (`id`, `name`, `desc`) VALUES
(1, 'Minuman', 'Minuman kemasan dan curah'),
(2, 'Makanan Ringan', 'Snack dan camilan'),
(3, 'Sembako', 'Kebutuhan pokok harian'),
(4, 'Kebersihan', 'Produk kebersihan rumah'),
(5, 'Rokok', 'Rokok dan tembakau'),
(6, 'Makanan', 'makanan berkuah'),
(7, 'Pakaian', 'Baju');

-- --------------------------------------------------------

--
-- Struktur dari tabel `customers`
--

CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `createdAt` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `customers`
--

INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `address`, `createdAt`) VALUES
(1, 'Dewi Lestari', '081234567890', 'dewi@email.com', 'Jl. Mawar No.5', '2025-01-10'),
(2, 'Eko Prasetyo', '082345678901', '', 'Jl. Melati No.12', '2025-02-05'),
(3, 'Fitri Handayani', '083456789012', 'fitri@email.com', 'Jl. Kenanga No.3', '2025-03-01'),
(4, 'Misbahul Munir', '081234567899', 'misbahul@email.com', 'Cimanggu, Cilacap', '2025-06-11');

-- --------------------------------------------------------

--
-- Struktur dari tabel `discount_rules`
--

CREATE TABLE IF NOT EXISTS `discount_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Nama aturan',
  `type` enum('flat','percent') NOT NULL DEFAULT 'percent' COMMENT 'flat = nominal Rp, percent = persen',
  `value` int(11) NOT NULL COMMENT 'Nilai diskon (contoh: 10 untuk 10%, atau 5000 untuk Rp5.000)',
  `min_purchase` int(11) NOT NULL DEFAULT 0 COMMENT 'Minimal belanja agar diskon berlaku',
  `max_discount` int(11) DEFAULT NULL COMMENT 'Maksimal potongan (opsional)',
  `is_active` tinyint(1) DEFAULT 1,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `discount_rules`
--

INSERT INTO `discount_rules` (`id`, `name`, `type`, `value`, `min_purchase`, `max_discount`, `is_active`, `start_date`, `end_date`, `created_at`) VALUES
(1, 'Diskon 5% untuk belanja ≥ 100.000', 'percent', 5, 100000, NULL, 1, NULL, NULL, '2026-06-11 06:57:27'),
(2, 'Diskon 10% untuk belanja ≥ 200.000', 'percent', 10, 200000, NULL, 1, NULL, NULL, '2026-06-11 06:57:27'),
(3, 'Diskon Rp 10.000 untuk belanja ≥ 50.000', 'flat', 10000, 50000, NULL, 1, NULL, NULL, '2026-06-11 06:57:27'),
(4, 'Diskon Rp 25.000 untuk belanja ≥ 100.000', 'flat', 25000, 100000, NULL, 1, NULL, NULL, '2026-06-11 06:57:27');

-- --------------------------------------------------------

--
-- Struktur dari tabel `products`
--

CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `categoryId` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `cost` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `minStock` int(11) DEFAULT 5,
  `unit` varchar(20) DEFAULT 'pcs',
  `status` varchar(20) DEFAULT 'TERSEDIA',
  PRIMARY KEY (`id`),
  KEY `categoryId` (`categoryId`)
) ENGINE=InnoDB AUTO_INCREMENT=786527 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `products`
--

INSERT INTO `products` (`id`, `name`, `categoryId`, `barcode`, `cost`, `price`, `stock`, `minStock`, `unit`, `status`) VALUES
(1, 'Aqua Botol 600ml', 1, '8999999001', 2500, 3500, 44, 10, 'pcs', 'TERSEDIA'),
(2, 'Teh Botol Sosro', 1, '8999999002', 3000, 4500, 35, 10, 'pcs', 'TERSEDIA'),
(3, 'Indomie Goreng', 3, '8999999003', 2800, 3500, 100, 20, 'pcs', 'TERSEDIA'),
(4, 'Chitato Original', 2, '8999999004', 8000, 10000, 24, 5, 'pcs', 'TERSEDIA'),
(5, 'Sabun Lifebuoy', 4, '8999999005', 3500, 5000, 30, 5, 'pcs', 'TERSEDIA'),
(6, 'Gudang Garam Filter', 5, '8999999006', 22000, 25000, 41, 10, 'bks', 'TERSEDIA'),
(7, 'Beras Super 1kg', 3, '8999999007', 12500, 15000, 14, 5, 'kg', 'TERSEDIA'),
(8, 'Gula Pasir 1kg', 3, '8999999008', 13000, 15500, 20, 5, 'kg', 'TERSEDIA'),
(9, 'Minyak Goreng 1L', 3, '8999999009', 14000, 17000, 13, 5, 'liter', 'TERSEDIA'),
(10, 'Pocari Sweat', 1, '8999999010', 5500, 7000, 24, 6, 'pcs', 'TERSEDIA'),
(11, 'Rexona Men', 4, '8999999011', 15000, 20000, 8, 3, 'pcs', 'TERSEDIA'),
(12, 'Pepsodent 75g', 4, '8999999012', 8500, 11000, 20, 4, 'pcs', 'TERSEDIA'),
(13, 'Bakso', 1, '8809123465', 4500, 5500, 32, 21, 'bungkus', 'TERSEDIA'),
(786524, 'Jubah', 7, '12345678', 150000, 150000, 4, 1, 'pcs', 'TERSEDIA'),
(786525, 'Sop Iga Sapi', 6, '142387', 15600, 16500, 100, 1, 'Kg', 'TERSEDIA'),
(786526, 'kaos', 7, '345362', 35000, 35500, 1, 1, 'pcs', 'STOK MENIPIS');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transactions`
--

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoiceNo` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `cashierId` int(11) NOT NULL,
  `customerId` int(11) DEFAULT NULL,
  `subtotal` int(11) NOT NULL,
  `discount` int(11) DEFAULT 0,
  `total` int(11) NOT NULL,
  `paid` int(11) NOT NULL,
  `change` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'completed',
  `discount_type` enum('flat','percent') DEFAULT 'flat',
  `discount_value` int(11) DEFAULT 0,
  `discount_amount` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoiceNo` (`invoiceNo`),
  KEY `cashierId` (`cashierId`),
  KEY `customerId` (`customerId`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transactions`
--

INSERT INTO `transactions` (`id`, `invoiceNo`, `date`, `time`, `cashierId`, `customerId`, `subtotal`, `discount`, `total`, `paid`, `change`, `status`, `discount_type`, `discount_value`, `discount_amount`) VALUES
(1, 'INV-0001', '2026-06-11', '09:30:00', 1, 1, 35000, 0, 35000, 50000, 15000, 'completed', 'flat', 0, 0),
(2, 'INV-0002', '2026-06-11', '10:15:00', 2, NULL, 22500, 0, 22500, 30000, 7500, 'completed', 'flat', 0, 0),
(3, 'INV-0003', '2026-06-10', '14:20:00', 1, 2, 87000, 5000, 82000, 100000, 18000, 'completed', 'flat', 0, 0),
(4, 'INV-0004', '2026-06-09', '11:45:00', 2, 3, 15000, 0, 15000, 20000, 5000, 'completed', 'flat', 0, 0),
(5, 'INV-0005', '2026-06-08', '16:10:00', 1, NULL, 42000, 2000, 40000, 50000, 10000, 'completed', 'flat', 0, 0),
(6, 'INV-1781160401248', '2026-06-11', '13:46:00', 1, NULL, 20000, 0, 20000, 25000, 5000, 'completed', 'flat', 0, 0),
(7, 'INV-1781160446582', '2026-06-11', '13:47:00', 1, NULL, 3500, 0, 3500, 5000, 1500, 'completed', 'flat', 0, 0),
(8, 'INV-1781161627352', '2026-06-11', '14:07:00', 1, NULL, 60000, 10000, 50000, 60000, 10000, 'completed', 'flat', 10000, 10000),
(9, 'INV-1781161664822', '2026-06-11', '14:07:00', 1, NULL, 100000, 5000, 95000, 100000, 5000, 'completed', 'percent', 5, 5000),
(10, 'INV-1781174748384', '2026-06-11', '17:45:00', 1, NULL, 50000, 10000, 40000, 50000, 10000, 'completed', 'flat', 10000, 10000),
(11, 'INV-1781199734092', '2026-06-11', '00:42:00', 1, NULL, 3500, 0, 3500, 5000, 1500, 'completed', 'flat', 0, 0),
(12, 'INV-1781200461771', '2026-06-11', '00:54:00', 1, NULL, 3500, 0, 3500, 5000, 1500, 'completed', 'flat', 0, 0),
(13, 'INV-1781200834014', '2026-06-11', '01:00:00', 1, NULL, 4500, 0, 4500, 5000, 500, 'completed', 'flat', 0, 0),
(14, 'INV-1781255324032', '2026-06-12', '16:08:00', 1, NULL, 150000, 7500, 142500, 150000, 7500, 'completed', 'percent', 5, 0),
(15, 'INV-1781256861223', '2026-06-12', '16:34:00', 1, 4, 3500, 0, 3500, 5000, 1500, 'completed', 'flat', 0, 0),
(16, 'INV-1781258892328', '2026-06-12', '17:08:00', 4, NULL, 75000, 10000, 65000, 100000, 35000, 'completed', 'flat', 10000, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tx_details`
--

CREATE TABLE IF NOT EXISTS `tx_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `txId` int(11) NOT NULL,
  `productId` int(11) NOT NULL,
  `productName` varchar(200) NOT NULL,
  `qty` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `subtotal` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `txId` (`txId`),
  KEY `productId` (`productId`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tx_details`
--

INSERT INTO `tx_details` (`id`, `txId`, `productId`, `productName`, `qty`, `price`, `subtotal`) VALUES
(1, 1, 1, 'Aqua Botol 600ml', 2, 3500, 7000),
(2, 1, 3, 'Indomie Goreng', 8, 3500, 28000),
(3, 2, 2, 'Teh Botol Sosro', 3, 4500, 13500),
(4, 2, 5, 'Sabun Lifebuoy', 2, 4500, 9000),
(5, 3, 6, 'Gudang Garam Filter', 2, 25000, 50000),
(6, 3, 10, 'Pocari Sweat', 2, 7000, 14000),
(7, 3, 11, 'Rexona Men', 1, 20000, 20000),
(8, 4, 4, 'Chitato Original', 1, 10000, 10000),
(9, 4, 8, 'Gula Pasir 1kg', 1, 5000, 5000),
(10, 5, 7, 'Beras Super 1kg', 2, 15000, 30000),
(11, 5, 12, 'Pepsodent 75g', 1, 11000, 11000),
(12, 6, 11, 'Rexona Men', 1, 20000, 20000),
(13, 7, 1, 'Aqua Botol 600ml', 1, 3500, 3500),
(14, 8, 11, 'Rexona Men', 3, 20000, 60000),
(15, 9, 6, 'Gudang Garam Filter', 4, 25000, 100000),
(16, 10, 6, 'Gudang Garam Filter', 2, 25000, 50000),
(17, 11, 1, 'Aqua Botol 600ml', 1, 3500, 3500),
(18, 14, 786524, 'Jubah', 1, 150000, 150000),
(19, 15, 1, 'Aqua Botol 600ml', 1, 3500, 3500),
(20, 16, 6, 'Gudang Garam Filter', 3, 25000, 75000);

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','kasir') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `createdAt` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `status`, `createdAt`) VALUES
(1, 'Administrator', 'admin', '0192023a7bbd73250516f069df18b500', 'admin', 'active', '2025-01-01'),
(2, 'Siti Rahayu', 'kasir1', 'de28f8f7998f23ab4194b51a6029416f', 'kasir', 'active', '2025-01-15'),
(3, 'Budi Santoso', 'kasir2', 'de28f8f7998f23ab4194b51a6029416f', 'kasir', 'active', '2025-02-01'),
(4, 'Aldan', 'jokowi', '2289387438e5d6f55335b1bca94a512d', 'kasir', 'active', '2026-06-12');

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `v_laporan_penjualan`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE IF NOT EXISTS `v_laporan_penjualan` (
`invoiceNo` varchar(50)
,`date` date
,`time` time
,`kasir` varchar(100)
,`pelanggan` varchar(100)
,`subtotal` int(11)
,`discount` int(11)
,`total` int(11)
,`paid` int(11)
,`change` int(11)
);

-- --------------------------------------------------------

--
-- Struktur untuk view `v_laporan_penjualan`
--
DROP TABLE IF EXISTS `v_laporan_penjualan`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_laporan_penjualan`  AS SELECT `t`.`invoiceNo` AS `invoiceNo`, `t`.`date` AS `date`, `t`.`time` AS `time`, `u`.`name` AS `kasir`, coalesce(`c`.`name`,'UMUM') AS `pelanggan`, `t`.`subtotal` AS `subtotal`, `t`.`discount` AS `discount`, `t`.`total` AS `total`, `t`.`paid` AS `paid`, `t`.`change` AS `change` FROM ((`transactions` `t` left join `users` `u` on(`t`.`cashierId` = `u`.`id`)) left join `customers` `c` on(`t`.`customerId` = `c`.`id`)) ;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`categoryId`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`cashierId`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`customerId`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `tx_details`
--
ALTER TABLE `tx_details`
  ADD CONSTRAINT `tx_details_ibfk_1` FOREIGN KEY (`txId`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tx_details_ibfk_2` FOREIGN KEY (`productId`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ========== QUERY PENGUJIAN ==========
SELECT * FROM users;
SELECT * FROM customers;
SELECT * FROM products;
SELECT t.invoiceNo, u.name AS kasir, COALESCE(c.name,'UMUM') AS pelanggan, t.total
FROM transactions t
LEFT JOIN users u ON t.cashierId = u.id
LEFT JOIN customers c ON t.customerId = c.id
ORDER BY t.date DESC;
SELECT td.productName, SUM(td.qty) AS terjual, SUM(td.subtotal) AS pendapatan
FROM tx_details td
GROUP BY td.productName ORDER BY pendapatan DESC LIMIT 5;
SELECT c.name, AVG(t.total) AS rata_rata_belanja
FROM customers c JOIN transactions t ON c.id = t.customerId GROUP BY c.id;
SELECT * FROM v_laporan_penjualan;
CALL update_status_stok();
SELECT name, stock, minStock, status FROM products WHERE stock <= minStock;
