<?php
include 'db_config.php';

// 判斷這次請求是不是由 fetch (AJAX) 送過來的，如果是就回傳 JSON，
// 結帳頁面會用這個結果直接彈出平面圖亮燈，不需要跳轉頁面。
$is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
}

// 1. 取得表單資料
$customer_name = $_POST['customer_name'] ?? '';
$customer_phone = $_POST['customer_phone'] ?? '';
$order_date = $_POST['order_date'] ?? date('Y-m-d');
$product_ids = $_POST['product_id'] ?? [];
$quantities = $_POST['quantity'] ?? [];

if (trim($customer_name) === '' || empty($product_ids)) {
    if ($is_ajax) {
        echo json_encode(['status' => 'error', 'msg' => '缺少顧客姓名或購物車是空的']);
        exit;
    } else {
        echo "<script>alert('缺少顧客姓名或購物車是空的'); window.location.href='order_add.php';</script>";
        exit;
    }
}

// 2. 生成訂單編號 (簡單範例：時間戳記)
$order_id = "ORD" . date("YmdHis");

// 3. 開始資料庫交易處理
$conn->begin_transaction();

try {
    // 寫入「訂單」主表
    $stmt1 = $conn->prepare("INSERT INTO 訂單 (訂單編號, 顧客姓名, 訂單日期, 顧客電話) VALUES (?, ?, ?, ?)");
    $stmt1->bind_param("ssss", $order_id, $customer_name, $order_date, $customer_phone);
    $stmt1->execute();

    // 寫入「訂單包含商品」明細表
    $stmt2 = $conn->prepare("INSERT INTO 訂單包含商品 (訂單編號, 商品編號, 數量, 成交單價) VALUES (?, ?, ?, ?)");

    $saved_product_ids = [];

    foreach ($product_ids as $key => $p_id) {
        if (!empty($p_id) && !empty($quantities[$key])) {
            $qty = $quantities[$key];

            // 先查詢該商品的銷售單價
            $price_res = $conn->query("SELECT 銷售單價 FROM 商品 WHERE 商品編號 = '$p_id'");
            $price_row = $price_res->fetch_assoc();
            $unit_price = $price_row['銷售單價'];

            $stmt2->bind_param("ssid", $order_id, $p_id, $qty, $unit_price);
            $stmt2->execute();

            $saved_product_ids[] = $p_id;
        }
    }

    // 全部執行成功，提交！
    $conn->commit();

    if ($is_ajax) {
        echo json_encode([
            'status' => 'success',
            'order_id' => $order_id,
            'customer_name' => $customer_name,
            'product_ids' => array_values(array_unique($saved_product_ids)),
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo "<script>
                alert('訂單開立成功！編號：$order_id');
                window.location.href = 'index.html';
              </script>";
    }

} catch (Exception $e) {
    // 萬一失敗，把剛才寫一半的資料都撤回
    $conn->rollback();

    if ($is_ajax) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    } else {
        echo "下單失敗，錯誤訊息: " . $e->getMessage();
    }
}

$conn->close();
