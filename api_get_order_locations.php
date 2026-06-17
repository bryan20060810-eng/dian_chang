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
$order_items = $conn->query("SELECT d.商品編號, p.商品名稱, p.位置描述
                              FROM 訂單包含商品 d
                              JOIN 商品 p ON d.商品編號 = p.商品編號
                              WHERE d.訂單編號 = '$order_id'");

$products = []; // 商品編號 => ['name' => ..., 'note' => ..., 'location_ids' => []]
$product_ids = [];
while ($row = $order_items->fetch_assoc()) {
    $pid = $row['商品編號'];
    $product_ids[] = $pid;
    $products[$pid] = [
        'id' => $pid,
        'name' => $row['商品名稱'],
        'note' => $row['位置描述'] ?? '',
        'location_ids' => [],
    ];
}

if (empty($product_ids)) {
    echo json_encode([
        'status' => 'success',
        'customer_name' => $customer_name,
        'location_ids' => [],
        'products' => [],
        'unassigned_products' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 撈取這些商品對應的平面圖存放位置
$escaped_ids = array_map(function ($id) use ($conn) {
    return "'" . $conn->real_escape_string($id) . "'";
}, $product_ids);
$id_list = implode(',', $escaped_ids);

$loc_result = $conn->query("SELECT 位置編號, 商品編號 FROM 存放位置明細 WHERE 商品編號 IN ($id_list)");

$all_location_ids = [];
while ($row = $loc_result->fetch_assoc()) {
    $pid = $row['商品編號'];
    if (isset($products[$pid])) {
        $products[$pid]['location_ids'][] = $row['位置編號'];
    }
    $all_location_ids[] = $row['位置編號'];
}
$all_location_ids = array_values(array_unique($all_location_ids));

// 找出完全沒有任何位置資訊（沒平面圖位置也沒文字描述）的商品名稱，用來提醒使用者
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
