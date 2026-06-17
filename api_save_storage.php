<?php
// 開啟錯誤回報，方便除錯
ini_set('display_errors', 1); 
error_reporting(E_ALL);
include 'db_config.php';

// 🌟 關鍵：強制告訴瀏覽器，這支 API 回傳的是 JSON 格式
header('Content-Type: application/json; charset=utf-8');

if(isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // 【動作 1：新增入庫】
    if($action === 'add') {
        $p_id = $conn->real_escape_string($_POST['p_id']);
        $loc_id = $conn->real_escape_string($_POST['loc_id']);
        // 前端傳來的 qty 已經是換算後的「基準數量 (錢 / 個)」
        $qty = floatval($_POST['qty']); 
        
        $conn->begin_transaction();
        try {
            // 1. 扣減「商品表」的庫存總量
            $conn->query("UPDATE 商品 SET 庫存總量 = 庫存總量 - $qty WHERE 商品編號='$p_id'");
            
            // 2. 更新或新增「存放位置明細表」的貨架庫存
            $check = $conn->query("SELECT 紀錄編號, 存放數量 FROM 存放位置明細 WHERE 位置編號='$loc_id' AND 商品編號='$p_id'");
            if($check->num_rows > 0) {
                // 如果這個貨架已經有這個商品，就把數量加上去
                $row = $check->fetch_assoc();
                $new_qty = $row['存放數量'] + $qty;
                $rec_id = $row['紀錄編號'];
                $conn->query("UPDATE 存放位置明細 SET 存放數量 = $new_qty WHERE 紀錄編號 = $rec_id");
            } else {
                // 如果是新放進去的商品，新增一筆紀錄
                $conn->query("INSERT INTO 存放位置明細 (位置編號, 商品編號, 存放數量) VALUES ('$loc_id', '$p_id', $qty)");
                $rec_id = $conn->insert_id; // 取得剛剛新增的紀錄編號
            }
            $conn->commit();
            echo json_encode(["status" => "success", "record_id" => $rec_id]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
        }
    }
    
    // 【動作 2：修改數量】
    elseif($action === 'update') {
        $record_id = (int)$_POST['record_id'];
        $p_id = $conn->real_escape_string($_POST['p_id']);
        $diff_qty = floatval($_POST['diff_qty']); // 差距量 (新數量 - 舊數量)
        
        $conn->begin_transaction();
        try {
            // 同步扣減或補回總庫存
            $conn->query("UPDATE 商品 SET 庫存總量 = 庫存總量 - $diff_qty WHERE 商品編號='$p_id'");
            $conn->query("UPDATE 存放位置明細 SET 存放數量 = 存放數量 + $diff_qty WHERE 紀錄編號 = $record_id");
            
            // 防呆：如果修改後數量小於等於 0，自動將它從貨架上移除
            $conn->query("DELETE FROM 存放位置明細 WHERE 存放數量 <= 0"); 
            $conn->commit();
            echo json_encode(["status" => "success"]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
        }
    }
    
    // 【動作 3：全數移出】
    elseif($action === 'remove') {
        $record_id = (int)$_POST['record_id'];
        $p_id = $conn->real_escape_string($_POST['p_id']);
        $old_qty = floatval($_POST['old_qty']); // 舊數量全數加回總庫存
        
        $conn->begin_transaction();
        try {
            // 把貨架上的數量加回去原本的總庫存
            $conn->query("UPDATE 商品 SET 庫存總量 = 庫存總量 + $old_qty WHERE 商品編號='$p_id'");
            // 刪除這筆貨架存放紀錄
            $conn->query("DELETE FROM 存放位置明細 WHERE 紀錄編號 = $record_id");
            $conn->commit();
            echo json_encode(["status" => "success"]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
        }
    }
} else {
    // 如果前端沒有傳送 action，回報明確的錯誤
    echo json_encode(["status" => "error", "msg" => "前端沒有傳送 action 參數，請確認儲存時是否有夾帶 action。"]);
}
?>