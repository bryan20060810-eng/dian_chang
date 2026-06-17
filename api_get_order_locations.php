<?php
include 'db_config.php';
header('Content-Type: application/json; charset=utf-8');

$order_id = isset($_GET['order_id']) ? $conn->real_escape_string(trim($_GET['order_id'])) : '';

if ($order_id === '') {
    echo json_encode(['status' => 'error', 'msg' => '請輸入訂單編號']);
    exit;
}

// 確認訂單是否存在
$order_result = $conn->query("SELECT 顧客姓名 FROM 訂單 WHERE 訂單編號 = '$order_id'");

if (!$order_result || $order_result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'msg' => '找不到訂單編號：' . $order_id]);
    exit;
}

$customer_name = $order_result->fetch_assoc()['顧客姓名'];

// 撈取此訂單包含的所有商品（含手打的位置描述文字）
$order_items = $conn->query("SELECT d.商品編號, p.商品名稱, p.位置描述, p.商品類型
                              FROM 訂單包含商品 d
                              JOIN 商品 p ON d.商品編號 = p.商品編號
                              WHERE d.訂單編號 = '$order_id'");

$products = []; // 商品編號 => ['name' => ..., 'note' => ..., 'location_ids' => []]
$all_herb_ids = []; // 用來收集所有要查詢的藥材編號（包括配方裡的）

while ($row = $order_items->fetch_assoc()) {
    $pid = $row['商品編號'];
    $product_type = $row['商品類型'];
    
    if ($product_type === '配方') {
        // 這是配方，需要查詢它的藥材組成
        $formula_herbs = $conn->query("
            SELECT 藥材商品編號
            FROM 藥材組成配方
            WHERE 配方商品編號 = '$pid'
        ");
        
        $herb_ids = [];
        while ($herb_row = $formula_herbs->fetch_assoc()) {
            $herb_id = $herb_row['藥材商品編號'];
            $herb_ids[] = $herb_id;
            $all_herb_ids[] = $herb_id;
        }
        
        // 儲存配方資訊（改以藥材名稱列表顯示）
        $herb_names_result = $conn->query("
            SELECT GROUP_CONCAT(p.商品名稱 SEPARATOR '、') as herb_names
            FROM 藥材組成配方 f
            JOIN 商品 p ON f.藥材商品編號 = p.商品編號
            WHERE f.配方商品編號 = '$pid'
        ");
        $herb_names_row = $herb_names_result->fetch_assoc();
        
        $products[$pid] = [
            'id' => $pid,
            'name' => $row['商品名稱'],
            'display_name' => $row['商品名稱'] . ' (' . ($herb_names_row['herb_names'] ?? '無') . ')',
            'note' => $row['位置描述'] ?? '',
            'type' => '配方',
            'location_ids' => [],
            'herb_ids' => $herb_ids,
        ];
    } else {
        // 一般商品（藥材或成藥）
        $products[$pid] = [
            'id' => $pid,
            'name' => $row['商品名稱'],
            'display_name' => $row['商品名稱'],
            'note' => $row['位置描述'] ?? '',
            'type' => $product_type,
            'location_ids' => [],
            'herb_ids' => [],
        ];
        
        // 非配方商品也加到要查詢的列表
        if ($product_type === '藥材') {
            $all_herb_ids[] = $pid;
        }
    }
}

if (empty($products)) {
    echo json_encode([
        'status' => 'success',
        'customer_name' => $customer_name,
        'location_ids' => [],
        'products' => [],
        'unassigned_products' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 撈取所有藥材對應的平面圖存放位置
$all_herb_ids = array_unique($all_herb_ids);
if (!empty($all_herb_ids)) {
    $escaped_ids = array_map(function ($id) use ($conn) {
        return "'" . $conn->real_escape_string($id) . "'";
    }, $all_herb_ids);
    $id_list = implode(',', $escaped_ids);
    
    $loc_result = $conn->query("SELECT 位置編號, 商品編號 FROM 存放位置明細 WHERE 商品編號 IN ($id_list)");
    
    $all_location_ids = [];
    $herb_locations = []; // 藥材編號 => [位置編號...]
    
    while ($row = $loc_result->fetch_assoc()) {
        $herb_id = $row['商品編號'];
        $loc_id = $row['位置編號'];
        
        if (!isset($herb_locations[$herb_id])) {
            $herb_locations[$herb_id] = [];
        }
        $herb_locations[$herb_id][] = $loc_id;
        $all_location_ids[] = $loc_id;
    }
    
    $all_location_ids = array_values(array_unique($all_location_ids));
    
    // 將位置資訊填回商品資訊
    foreach ($products as $pid => &$p) {
        if ($p['type'] === '配方') {
            // 配方：收集所有藥材的位置
            $formula_locations = [];
            foreach ($p['herb_ids'] as $herb_id) {
                if (isset($herb_locations[$herb_id])) {
                    $formula_locations = array_merge($formula_locations, $herb_locations[$herb_id]);
                }
            }
            $p['location_ids'] = array_values(array_unique($formula_locations));
        } else if ($p['type'] === '藥材') {
            // 藥材：直接用該藥材的位置
            $p['location_ids'] = $herb_locations[$pid] ?? [];
        }
    }
}

// 找出完全沒有任何位置資訊的商品名稱
$unassigned_products = [];
foreach ($products as $p) {
    if (empty($p['location_ids']) && trim($p['note']) === '') {
        $unassigned_products[] = $p['name'];
    }
}

echo json_encode([
    'status' => 'success',
    'customer_name' => $customer_name,
    'location_ids' => $all_location_ids,
    'products' => array_values($products),
    'unassigned_products' => $unassigned_products,
], JSON_UNESCAPED_UNICODE);
?>
