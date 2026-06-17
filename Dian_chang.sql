-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： localhost
-- 產生時間： 2026 年 06 月 16 日 15:52
-- 伺服器版本： 10.4.28-MariaDB
-- PHP 版本： 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `Dian_chang`
--

-- --------------------------------------------------------

--
-- 資料表結構 `商品`
--

CREATE TABLE `商品` (
  `商品編號` varchar(20) NOT NULL,
  `商品名稱` varchar(100) NOT NULL,
  `銷售單價` decimal(10,2) NOT NULL,
  `供應狀態` varchar(20) DEFAULT NULL,
  `商品類型` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `商品`
--

INSERT INTO `商品` (`商品編號`, `商品名稱`, `銷售單價`, `供應狀態`, `商品類型`) VALUES
('P001', '人參', 500.00, '充足', '藥材'),
('P002', '枇杷膏', 150.00, '充足', '成藥'),
('P003', '十全大補配方', 800.00, '充足', '配方');

-- --------------------------------------------------------

--
-- 資料表結構 `商品_成藥`
--

CREATE TABLE `商品_成藥` (
  `商品編號` varchar(20) NOT NULL,
  `存放位置編號` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `商品_藥材`
--

CREATE TABLE `商品_藥材` (
  `商品編號` varchar(20) NOT NULL,
  `存放位置編號` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `商品_配方`
--

CREATE TABLE `商品_配方` (
  `商品編號` varchar(20) NOT NULL,
  `存放位置編號` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `存放位置`
--

CREATE TABLE `存放位置` (
  `位置編號` varchar(20) NOT NULL,
  `區域名稱` varchar(50) NOT NULL,
  `樓層` int(11) NOT NULL,
  `平面座標` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `藥材組成配方`
--

CREATE TABLE `藥材組成配方` (
  `藥材商品編號` varchar(20) NOT NULL,
  `配方商品編號` varchar(20) NOT NULL,
  `數量` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `訂單`
--

CREATE TABLE `訂單` (
  `訂單編號` varchar(20) NOT NULL,
  `顧客姓名` varchar(50) NOT NULL,
  `訂單日期` date NOT NULL,
  `顧客電話` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `訂單包含商品`
--

CREATE TABLE `訂單包含商品` (
  `訂單編號` varchar(20) NOT NULL,
  `商品編號` varchar(20) NOT NULL,
  `數量` decimal(10,2) NOT NULL,
  `成交單價` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `商品`
--
ALTER TABLE `商品`
  ADD PRIMARY KEY (`商品編號`);

--
-- 資料表索引 `商品_成藥`
--
ALTER TABLE `商品_成藥`
  ADD PRIMARY KEY (`商品編號`),
  ADD KEY `存放位置編號` (`存放位置編號`);

--
-- 資料表索引 `商品_藥材`
--
ALTER TABLE `商品_藥材`
  ADD PRIMARY KEY (`商品編號`),
  ADD KEY `存放位置編號` (`存放位置編號`);

--
-- 資料表索引 `商品_配方`
--
ALTER TABLE `商品_配方`
  ADD PRIMARY KEY (`商品編號`),
  ADD KEY `存放位置編號` (`存放位置編號`);

--
-- 資料表索引 `存放位置`
--
ALTER TABLE `存放位置`
  ADD PRIMARY KEY (`位置編號`);

--
-- 資料表索引 `藥材組成配方`
--
ALTER TABLE `藥材組成配方`
  ADD PRIMARY KEY (`藥材商品編號`,`配方商品編號`),
  ADD KEY `配方商品編號` (`配方商品編號`);

--
-- 資料表索引 `訂單`
--
ALTER TABLE `訂單`
  ADD PRIMARY KEY (`訂單編號`);

--
-- 資料表索引 `訂單包含商品`
--
ALTER TABLE `訂單包含商品`
  ADD PRIMARY KEY (`訂單編號`,`商品編號`),
  ADD KEY `商品編號` (`商品編號`);

--
-- 已傾印資料表的限制式
--

--
-- 資料表的限制式 `商品_成藥`
--
ALTER TABLE `商品_成藥`
  ADD CONSTRAINT `商品_成藥_ibfk_1` FOREIGN KEY (`商品編號`) REFERENCES `商品` (`商品編號`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `商品_成藥_ibfk_2` FOREIGN KEY (`存放位置編號`) REFERENCES `存放位置` (`位置編號`) ON UPDATE CASCADE;

--
-- 資料表的限制式 `商品_藥材`
--
ALTER TABLE `商品_藥材`
  ADD CONSTRAINT `商品_藥材_ibfk_1` FOREIGN KEY (`商品編號`) REFERENCES `商品` (`商品編號`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `商品_藥材_ibfk_2` FOREIGN KEY (`存放位置編號`) REFERENCES `存放位置` (`位置編號`) ON UPDATE CASCADE;

--
-- 資料表的限制式 `商品_配方`
--
ALTER TABLE `商品_配方`
  ADD CONSTRAINT `商品_配方_ibfk_1` FOREIGN KEY (`商品編號`) REFERENCES `商品` (`商品編號`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `商品_配方_ibfk_2` FOREIGN KEY (`存放位置編號`) REFERENCES `存放位置` (`位置編號`) ON UPDATE CASCADE;

--
-- 資料表的限制式 `藥材組成配方`
--
ALTER TABLE `藥材組成配方`
  ADD CONSTRAINT `藥材組成配方_ibfk_1` FOREIGN KEY (`藥材商品編號`) REFERENCES `商品_藥材` (`商品編號`) ON UPDATE CASCADE,
  ADD CONSTRAINT `藥材組成配方_ibfk_2` FOREIGN KEY (`配方商品編號`) REFERENCES `商品_配方` (`商品編號`) ON UPDATE CASCADE;

--
-- 資料表的限制式 `訂單包含商品`
--
ALTER TABLE `訂單包含商品`
  ADD CONSTRAINT `訂單包含商品_ibfk_1` FOREIGN KEY (`訂單編號`) REFERENCES `訂單` (`訂單編號`) ON UPDATE CASCADE,
  ADD CONSTRAINT `訂單包含商品_ibfk_2` FOREIGN KEY (`商品編號`) REFERENCES `商品` (`商品編號`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
