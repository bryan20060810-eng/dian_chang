<?php
include 'db_config.php';

if (!isset($_GET['order_id'])) {
    echo "缺少訂單編號";
    exit;
}

$order_id = $conn->real_escape_string($_GET['order_id']);

// 聯合查詢：訂單包含商品 表 與 商品 表
$sql = "SELECT d.數量, d.成交單價, p.商品名稱, p.商品類型 
        FROM 訂單包含商品 d 
        JOIN 商品 p ON d.商品編號 = p.商品編號 
        WHERE d.訂單編號 = '$order_id'";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $total = 0;
    while($row = $result->fetch_assoc()) {
        $subtotal = $row['數量'] * $row['成交單價'];
        $total += $subtotal;
        
        // 如果是藥材，把存入的「錢」轉換回畫面上友善的顯示方式 (簡化版：直接顯示基準數量)
        $unit_display = ($row['商品類型'] == '藥材' || $row['商品類型'] == '配方') ? ' 錢' : ' 個';

        echo "<div class='detail-item'>";
        echo "  <div><strong>{$row['商品名稱']}</strong> <span style='color:#666; font-size:20px;'>(單價 {$row['成交單價']})</span></div>";
        echo "  <div><span style='color:#0056B3; font-weight:bold;'>{$row['數量']}{$unit_display}</span> = $" . number_format($subtotal) . "</div>";
        echo "</div>";
    }
    // 在底部顯示這張單的總計
    echo "<div style='text-align:right; font-size:28px; font-weight:bold; color:#D32F2F; margin-top:15px; padding-top:15px; border-top:2px solid #333;'>";
    echo "總金額：$" . number_format($total);
    echo "</div>";
} else {
    echo "<div style='text-align:center; color:#999;'>找不到任何商品明細。</div>";
}
?>