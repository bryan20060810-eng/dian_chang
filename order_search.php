<?php
include 'db_config.php';

// 1. 處理刪除訂單的請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order_id'])) {
    $del_id = $_POST['delete_order_id'];
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM 訂單包含商品 WHERE 訂單編號 = '$del_id'");
        $conn->query("DELETE FROM 訂單 WHERE 訂單編號 = '$del_id'");
        $conn->commit();
        echo "<script>alert('✅ 訂單 $del_id 已成功刪除！'); window.location.href='order_search.php';</script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('❌ 刪除失敗：{$e->getMessage()}');</script>";
    }
}

// 2. 處理搜尋條件
$search_name = isset($_GET['search_name']) ? $_GET['search_name'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// 3. 撈取訂單主資料
$sql = "SELECT o.訂單編號, o.顧客姓名, o.訂單日期, o.顧客電話, 
               COALESCE(SUM(d.數量 * d.成交單價), 0) AS 總金額 
        FROM 訂單 o 
        LEFT JOIN 訂單包含商品 d ON o.訂單編號 = d.訂單編號 
        WHERE (o.顧客姓名 LIKE '%$search_name%' OR o.顧客電話 LIKE '%$search_name%')
        AND o.訂單日期 BETWEEN '$start_date' AND '$end_date'
        GROUP BY o.訂單編號 
        ORDER BY o.訂單日期 DESC, o.訂單編號 DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>歷史訂單查詢 - 同德藥行</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

<div class="admin-container">
    <div class="header-area">
        <h1>歷史訂單查詢與管理</h1>
        <a href="index.html" class="btn-back">返回首頁</a>
    </div>

    <form class="search-box" method="GET" action="order_search.php">
        <label>找顧客：</label>
        <input type="text" name="search_name" placeholder="輸入姓名或電話" value="<?php echo htmlspecialchars($search_name); ?>">
        
        <label>日期區間：</label>
        <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>">
        <span>至</span>
        <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>">
        
        <button type="submit" class="btn-search">搜尋</button>
        
        <div style="width: 100%; margin-top: 10px; border-top: 1px dashed #90CAF9; padding-top: 10px;">
            <span style="font-size: 20px; color: #555; margin-right: 15px;">快速選擇：</span>
            <button type="button" class="btn-shortcut" onclick="setDateRange(0)">今天</button>
            <button type="button" class="btn-shortcut" onclick="setDateRange(1)">昨天</button>
            <button type="button" class="btn-shortcut" onclick="setDateRange(7)">最近 7 天</button>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>訂單日期</th>
                <th>顧客姓名</th>
                <th>電話</th>
                <th>總金額</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['訂單日期']; ?></td>
                        <td style="font-weight:bold; color:#0056B3;"><?php echo htmlspecialchars($row['顧客姓名']); ?></td>
                        <td><?php echo htmlspecialchars($row['顧客電話'] ?: '無'); ?></td>
                        <td class="price">$<?php echo number_format($row['總金額']); ?></td>
                        <td>
                            <button class="btn-view" onclick="viewDetails('<?php echo $row['訂單編號']; ?>', '<?php echo htmlspecialchars($row['顧客姓名']); ?>')">看明細</button>
                            
                            <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ 確定要刪除【<?php echo htmlspecialchars($row['顧客姓名']); ?>】的這筆訂單嗎？\n刪除後將無法復原！');">
                                <input type="hidden" name="delete_order_id" value="<?php echo $row['訂單編號']; ?>">
                                <button type="submit" class="btn-del">刪除</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="padding: 50px; color: #999;">這段時間內沒有找到任何訂單喔！</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="detail-modal">
    <div class="modal-content">
        <div class="modal-header" id="modal-title">訂單明細</div>
        <div class="detail-list" id="detail-list">
            <div style="text-align:center; padding:20px; color:#666;">載入中...</div>
        </div>
        <button class="btn-close-modal" onclick="closeModal()">關閉視窗</button>
    </div>
</div>

<script>
function setDateRange(daysAgo) {
    const endInput = document.getElementById('end_date');
    const startInput = document.getElementById('start_date');
    
    let today = new Date();
    let targetDate = new Date();
    
    if (daysAgo === 0) {
        startInput.value = formatDate(today);
        endInput.value = formatDate(today);
    } else if (daysAgo === 1) {
        targetDate.setDate(today.getDate() - 1);
        startInput.value = formatDate(targetDate);
        endInput.value = formatDate(targetDate);
    } else if (daysAgo === 7) {
        targetDate.setDate(today.getDate() - 6);
        startInput.value = formatDate(targetDate);
        endInput.value = formatDate(today);
    }
    
    document.querySelector('.search-box').submit();
}

function formatDate(date) {
    let d = new Date(date),
        month = '' + (d.getMonth() + 1),
        day = '' + d.getDate(),
        year = d.getFullYear();

    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;

    return [year, month, day].join('-');
}

function viewDetails(orderId, customerName) {
    document.getElementById('modal-title').innerText = `${customerName} 的購買明細`;
    document.getElementById('detail-modal').style.display = 'flex';
    document.getElementById('detail-list').innerHTML = '<div style="text-align:center; padding:20px;">資料讀取中...</div>';

    fetch(`api_get_order_details.php?order_id=${orderId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('detail-list').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('detail-list').innerHTML = '<div style="color:red; text-align:center;">讀取失敗，請稍後再試。</div>';
        });
}

function closeModal() {
    document.getElementById('detail-modal').style.display = 'none';
}
</script>

</body>
</html>