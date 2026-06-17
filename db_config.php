<?php
$host = 'localhost';
$user = 'root'; // 依照你的環境修改
$password = ''; // 依照你的環境修改
$dbname = 'Dian_chang';

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

// 設定編碼為 utf8mb4
$conn->set_charset("utf8mb4");
?>