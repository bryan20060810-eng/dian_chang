<?php
include 'db_config.php';
header('Content-Type: application/json; charset=utf-8');

// 取得配方的所有藥材組成及其位置資訊
$formula_id = isset($_GET['formula_id']) ? $conn->real_escape_string(trim($_GET['formula_id'])) : '';

if ($formula_id === '') {
    echo json_encode(['status' => 'error', 'msg' => '缺少配方編號']);
    exit;
}

// 1. 驗證這是否為配方商品
$formula_check = $conn->query("SELECT 商品名稱 FROM 商品 WHERE 商品編號 = '$formula_id' AND 商品類型 = '配方'");
if (!$formula_check || $formula_check->num_rows === 0) {
    echo json_encode(['status' => 'error', 'msg' => '找不到該配方']);
    exit;
}
$formula_name = $formula_check->fetch_assoc()['商品名稱'];

// 2. 取得配方的所有藥材組成
$ingredients_result = $conn->query("
    SELECT f.藥材商品編號, p.商品名稱, f.數量
    FROM 藥材組成配方 f
    JOIN 商品 p ON f.藥材商品編號 = p.商品編號
    WHERE f.配方商品編號 = '$formula_id'
    ORDER BY f.藥材商品編號
");

if (!$ingredients_result) {
    echo json_encode(['status' => 'error', 'msg' => '資料庫查詢失敗']);
    exit;
}

$ingredients = [];
$all_location_ids = [];

while ($row = $ingredients_result->fetch_assoc()) {
    $ingredient_id = $row['藥材商品編號'];
    $ingredient_name = $row['商品名稱'];
    $qty = floatval($row['數量']);
    
    // 3. 取得該藥材的所有存放位置
    $locations_result = $conn->query("
        SELECT DISTINCT 位置編號
        FROM 存放位置明細
        WHERE 商品編號 = '$ingredient_id'
    ");
    
    $location_ids = [];
    while ($loc_row = $locations_result->fetch_assoc()) {
        $location_ids[] = $loc_row['位置編號'];
        if (!in_array($loc_row['位置編號'], $all_location_ids)) {
            $all_location_ids[] = $loc_row['位置編號'];
        }
    }
    
    $ingredients[] = [
        'id' => $ingredient_id,
        'name' => $ingredient_name,
        'qty' => $qty,
        'location_ids' => $location_ids,
    ];
}

echo json_encode([
    'status' => 'success',
    'formula_id' => $formula_id,
    'formula_name' => $formula_name,
    'ingredients' => $ingredients,
    'location_ids' => $all_location_ids,
], JSON_UNESCAPED_UNICODE);
?>
