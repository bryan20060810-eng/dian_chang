<?php
include 'db_config.php';
header('Content-Type: application/json; charset=utf-8');

// GET 請求：取得目前所有存放位置（給結帳後彈出的平面圖使用）
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_locations') {
    $locations = [];
    $res = $conn->query("SELECT 位置編號, 區域名稱, 樓層, pos_x, pos_y, width, height, shape_color FROM 存放位置 ORDER BY 樓層, 位置編號");
    while ($row = $res->fetch_assoc()) {
        $locations[] = [
            'id' => $row['位置編號'],
            'name' => $row['區域名稱'],
            'floor' => (int)$row['樓層'],
            'x' => (int)$row['pos_x'],
            'y' => (int)$row['pos_y'],
            'w' => (int)$row['width'],
            'h' => (int)$row['height'],
            'color' => $row['shape_color'],
        ];
    }
    echo json_encode(['status' => 'success', 'locations' => $locations], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'save_layout') {
    $locations = $input['locations'] ?? [];

    $conn->begin_transaction();
    try {
        // 取得目前資料庫裡所有的位置編號，找出哪些被刪除了
        $existing_ids = [];
        $res = $conn->query("SELECT 位置編號 FROM 存放位置");
        while ($row = $res->fetch_assoc()) {
            $existing_ids[] = $row['位置編號'];
        }

        $kept_ids = [];
        $next_loc_num = get_next_location_number($conn);

        foreach ($locations as $loc) {
            $id = $loc['id'];
            $name = $conn->real_escape_string($loc['name'] ?? '');
            $floor = (int)($loc['floor'] ?? 1);
            $x = (int)($loc['x'] ?? 0);
            $y = (int)($loc['y'] ?? 0);
            $w = (int)($loc['w'] ?? 100);
            $h = (int)($loc['h'] ?? 80);
            $color = $conn->real_escape_string($loc['color'] ?? '#90CAF9');

            if (strpos($id, 'NEW_') === 0) {
                // 新增的方塊，產生新的位置編號（用遞增計數器，避免同一批新增多個方塊時編號重複）
                $new_id = 'LOC' . str_pad($next_loc_num, 3, '0', STR_PAD_LEFT);
                $next_loc_num++;
                $stmt = $conn->prepare("INSERT INTO 存放位置 (位置編號, 區域名稱, 樓層, pos_x, pos_y, width, height, shape_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssiiiiis", $new_id, $name, $floor, $x, $y, $w, $h, $color);
                $stmt->execute();
                $kept_ids[] = $new_id;
            } else {
                $stmt = $conn->prepare("UPDATE 存放位置 SET 區域名稱=?, 樓層=?, pos_x=?, pos_y=?, width=?, height=?, shape_color=? WHERE 位置編號=?");
                $stmt->bind_param("siiiiiss", $name, $floor, $x, $y, $w, $h, $color, $id);
                $stmt->execute();
                $kept_ids[] = $id;
            }
        }

        // 刪除已經被使用者移除的方塊（連帶會刪除存放位置明細裡的指派，因為有 ON DELETE CASCADE）
        $to_delete = array_diff($existing_ids, $kept_ids);
        foreach ($to_delete as $del_id) {
            $stmt = $conn->prepare("DELETE FROM 存放位置 WHERE 位置編號=?");
            $stmt->bind_param("s", $del_id);
            $stmt->execute();
        }

        $conn->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'assign_product') {
    $product_id = $input['product_id'] ?? '';
    $location_id = $input['location_id'] ?? '';

    if ($product_id === '' || $location_id === '') {
        echo json_encode(['status' => 'error', 'msg' => '缺少商品或位置編號']);
        exit;
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO 存放位置明細 (位置編號, 商品編號) VALUES (?, ?)");
    $stmt->bind_param("ss", $location_id, $product_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => $conn->error]);
    }
    exit;
}

if ($action === 'unassign_product') {
    $product_id = $input['product_id'] ?? '';
    $location_id = $input['location_id'] ?? '';

    $stmt = $conn->prepare("DELETE FROM 存放位置明細 WHERE 位置編號 = ? AND 商品編號 = ?");
    $stmt->bind_param("ss", $location_id, $product_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => $conn->error]);
    }
    exit;
}

if ($action === 'update_product_note') {
    $product_id = $input['product_id'] ?? '';
    $note = trim($input['note'] ?? '');

    if ($product_id === '') {
        echo json_encode(['status' => 'error', 'msg' => '缺少商品編號']);
        exit;
    }

    // 允許清空（note 為空字串時存成 NULL）
    $note_value = $note === '' ? null : $note;

    $stmt = $conn->prepare("UPDATE 商品 SET 位置描述 = ? WHERE 商品編號 = ?");
    $stmt->bind_param("ss", $note_value, $product_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => $conn->error]);
    }
    exit;
}

if ($action === 'rename_location') {
    $location_id = $input['location_id'] ?? '';
    $new_name = trim($input['name'] ?? '');

    if ($location_id === '' || $new_name === '') {
        echo json_encode(['status' => 'error', 'msg' => '缺少位置編號或名稱不可為空']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE 存放位置 SET 區域名稱 = ? WHERE 位置編號 = ?");
    $stmt->bind_param("ss", $new_name, $location_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => $conn->error]);
    }
    exit;
}

if ($action === 'delete_location') {
    $location_id = $input['location_id'] ?? '';

    if ($location_id === '') {
        echo json_encode(['status' => 'error', 'msg' => '缺少位置編號']);
        exit;
    }

    // 刪除存放位置時，存放位置明細裡的指派會因為 ON DELETE CASCADE 一起被刪除
    $stmt = $conn->prepare("DELETE FROM 存放位置 WHERE 位置編號 = ?");
    $stmt->bind_param("s", $location_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => $conn->error]);
    }
    exit;
}

// 取得下一個可用的位置編號數字（LOC001 => 1, LOC002 => 2 ...）
function get_next_location_number($conn) {
    $res = $conn->query("SELECT 位置編號 FROM 存放位置 WHERE 位置編號 LIKE 'LOC%' ORDER BY CAST(SUBSTRING(位置編號, 4) AS UNSIGNED) DESC LIMIT 1");
    $max_num = 0;
    if ($row = $res->fetch_assoc()) {
        $max_num = (int)substr($row['位置編號'], 3);
    }
    return $max_num + 1;
}

echo json_encode(['status' => 'error', 'msg' => '未知的操作']);
