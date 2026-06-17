<?php
include 'db_config.php';

// =========================================================
// 1. 處理表單送出的動作 (新增、修改、刪除)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'delete' && isset($_POST['delete_id'])) {
        $del_id = $_POST['delete_id'];
        try {
            $conn->query("DELETE FROM 商品 WHERE 商品編號 = '$del_id'");
            echo "<script>alert('商品已成功刪除！'); window.location.href='product_manage.php';</script>";
        } catch (Exception $e) {
            echo "<script>alert('❌ 刪除失敗：{$e->getMessage()}');</script>";
        }
    }
    
    elseif ($action === 'add') {
        $name = $_POST['p_name'];
        $type = $_POST['p_type'];
        $status = $_POST['p_status'];
        
        // 新增的進貨與定價資料
        $cost_price = $_POST['p_cost_price'];
        $cost_unit = $_POST['p_cost_unit'];
        $sell_price = $_POST['p_sell_price'];
        $sell_unit = $_POST['p_sell_unit'];
        
        // 這個是給 POS 系統看換算好的基準單價 (隱藏欄位送過來的)
        $base_price = $_POST['p_base_price']; 
        
        $new_id = "P" . date("ymdHis");
        
        $conn->begin_transaction();
        try {
            // 寫入主表 (包含所有新欄位)
            $stmt = $conn->prepare("INSERT INTO 商品 (商品編號, 商品名稱, 進貨金額, 進貨單位, 定價金額, 定價單位, 銷售單價, 供應狀態, 商品類型) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdsdsdss", $new_id, $name, $cost_price, $cost_unit, $sell_price, $sell_unit, $base_price, $status, $type);
            $stmt->execute();
            
            if ($type === '成藥') { $conn->query("INSERT INTO 商品_成藥 (商品編號) VALUES ('$new_id')"); } 
            elseif ($type === '藥材') { $conn->query("INSERT INTO 商品_藥材 (商品編號) VALUES ('$new_id')"); } 
            elseif ($type === '配方') { $conn->query("INSERT INTO 商品_配方 (商品編號) VALUES ('$new_id')"); }
            
            $conn->commit();
            echo "<script>alert('新增成功！'); window.location.href='product_manage.php';</script>";
        } catch (Exception $e) {
            $conn->rollback();
            echo "<script>alert('❌ 新增失敗：{$e->getMessage()}');</script>";
        }
    }
    
    elseif ($action === 'edit') {
        $edit_id = $_POST['edit_id'];
        $name = $_POST['p_name'];
        $type = $_POST['p_type'];
        $status = $_POST['p_status'];
        $cost_price = $_POST['p_cost_price'];
        $cost_unit = $_POST['p_cost_unit'];
        $sell_price = $_POST['p_sell_price'];
        $sell_unit = $_POST['p_sell_unit'];
        $base_price = $_POST['p_base_price'];

        // 先查出目前資料庫裡的舊分類，待會兒如果分類有變更，需要同步搬移舊版分類專屬表的紀錄
        $old_type_res = $conn->query("SELECT 商品類型 FROM 商品 WHERE 商品編號 = '$edit_id'");
        $old_type = $old_type_res ? ($old_type_res->fetch_assoc()['商品類型'] ?? null) : null;

        $stmt = $conn->prepare("UPDATE 商品 SET 商品名稱=?, 進貨金額=?, 進貨單位=?, 定價金額=?, 定價單位=?, 銷售單價=?, 供應狀態=?, 商品類型=? WHERE 商品編號=?");
        $stmt->bind_param("sdsdsdsss", $name, $cost_price, $cost_unit, $sell_price, $sell_unit, $base_price, $status, $type, $edit_id);

        if($stmt->execute()) {
            // 如果分類有變更，把舊分類專屬表（商品_藥材/成藥/配方）裡的紀錄搬到新分類對應的表
            if ($old_type !== null && $old_type !== $type) {
                if ($old_type === '成藥') { $conn->query("DELETE FROM 商品_成藥 WHERE 商品編號 = '$edit_id'"); }
                elseif ($old_type === '藥材') { $conn->query("DELETE FROM 商品_藥材 WHERE 商品編號 = '$edit_id'"); }
                elseif ($old_type === '配方') { $conn->query("DELETE FROM 商品_配方 WHERE 商品編號 = '$edit_id'"); }

                if ($type === '成藥') { $conn->query("INSERT IGNORE INTO 商品_成藥 (商品編號) VALUES ('$edit_id')"); }
                elseif ($type === '藥材') { $conn->query("INSERT IGNORE INTO 商品_藥材 (商品編號) VALUES ('$edit_id')"); }
                elseif ($type === '配方') { $conn->query("INSERT IGNORE INTO 商品_配方 (商品編號) VALUES ('$edit_id')"); }
            }

            echo "<script>alert('商品資料已更新！'); window.location.href='product_manage.php';</script>";
        } else {
            echo "<script>alert('❌ 更新失敗');</script>";
        }
    }
}

// =========================================================
// 2. 撈取目前所有商品清單
// =========================================================
$search_keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$sql = "SELECT * FROM 商品 WHERE 商品名稱 LIKE '%$search_keyword%' OR 商品編號 LIKE '%$search_keyword%' ORDER BY 商品編號 DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>品項管理系統 - 同德藥行</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 8px; color: #0056B3; font-size: 20px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; font-size: 20px; border: 2px solid #CCC; border-radius: 8px; box-sizing: border-box; }
        
        /* 區塊視覺分隔 */
        .cost-section { background: #FFF3E0; padding: 15px; border-radius: 8px; border: 2px solid #FFB74D; margin-bottom: 20px; }
        .sell-section { background: #E3F2FD; padding: 15px; border-radius: 8px; border: 2px solid #64B5F6; margin-bottom: 20px; }
        .flex-row { display: flex; gap: 10px; align-items: center; }
        
        .status-badge { padding: 5px 12px; border-radius: 20px; font-weight: bold; font-size: 20px; }
        .status-ok { background: #E8F5E9; color: #2E7D32; }
        .status-empty { background: #FFEBEE; color: #D32F2F; }
        .btn-add-new { background: #0056B3; color: white; border: none; padding: 15px 30px; font-size: 24px; border-radius: 10px; cursor: pointer; font-weight: bold; width: 100%; margin-bottom: 20px; }
        .btn-edit { background: #FF9800; color: white; border: none; padding: 10px 20px; font-size: 20px; border-radius: 8px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

<div class="admin-container">
    <div class="header-area">
        <h1>品項管理系統 (進貨與定價設定)</h1>
        <a href="index.html" class="btn-back">返回首頁</a>
    </div>

    <button class="btn-add-new" onclick="openModal('add')">➕ 建立新商品</button>

    <form class="search-box" method="GET" action="product_manage.php">
        <label style="font-weight:bold; color:#0056B3;">找商品：</label>
        <input type="text" name="keyword" placeholder="輸入名稱或編號" value="<?php echo htmlspecialchars($search_keyword); ?>">
        <button type="submit" class="btn-search">🔍 搜尋</button>
        <a href="product_manage.php" class="btn-shortcut" style="text-decoration:none;">顯示全部</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>商品名稱</th>
                <th>分類</th>
                <th>原始進價</th>
                <th>原始定價</th>
                <th>POS基準價</th>
                <th>狀態</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight:bold; font-size:24px; color:#0056B3;"><?php echo htmlspecialchars($row['商品名稱']); ?></td>
                        <td><?php echo $row['商品類型']; ?></td>
                        <td style="color:#666;">$<?php echo floatval($row['進貨金額']); ?> / <?php echo htmlspecialchars($row['進貨單位']); ?></td>
                        <td style="color:#E65100; font-weight:bold;">$<?php echo floatval($row['定價金額']); ?> / <?php echo htmlspecialchars($row['定價單位']); ?></td>
                        <td class="price">$<?php echo floatval($row['銷售單價']); ?> <span style="font-size:16px;color:#999;">(<?php echo ($row['商品類型']=='藥材'||$row['商品類型']=='配方')?'每錢':'每個'; ?>)</span></td>
                        <td>
                            <?php if($row['供應狀態'] === '充足'): ?>
                                <span class="status-badge status-ok">✔️ 充足</span>
                            <?php else: ?>
                                <span class="status-badge status-empty">❌ <?php echo $row['供應狀態']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-edit" onclick="openModal('edit', '<?php echo $row['商品編號']; ?>', '<?php echo htmlspecialchars($row['商品名稱']); ?>', '<?php echo $row['進貨金額']; ?>', '<?php echo $row['進貨單位']; ?>', '<?php echo $row['定價金額']; ?>', '<?php echo $row['定價單位']; ?>', '<?php echo $row['供應狀態']; ?>', '<?php echo $row['商品類型']; ?>')">修改</button>
                            <button class="btn-del" onclick="deleteProduct('<?php echo $row['商品編號']; ?>', '<?php echo htmlspecialchars($row['商品名稱'], ENT_QUOTES); ?>')">刪除</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="padding: 50px; color: #999;">目前沒有找到任何商品資料。</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<form method="POST" action="product_manage.php" id="delete-form" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="delete_id" id="delete_id" value="">
</form>

<div class="modal-overlay" id="product-modal">
    <div class="modal-content" style="width: 650px;">
        <div class="modal-title" id="modal-title" style="border-bottom: 2px solid #EEE; padding-bottom:15px; margin-bottom:20px;">建立新商品</div>
        
        <form method="POST" action="product_manage.php" id="product-form">
            <input type="hidden" name="action" id="form-action" value="add">
            <input type="hidden" name="edit_id" id="edit_id" value="">

            <div class="flex-row" style="margin-bottom: 20px;">
                <div class="form-group" style="flex: 2; margin-bottom:0;">
                    <label>商品名稱：</label>
                    <input type="text" name="p_name" id="p_name" placeholder="例如：頂級燕窩" required>
                </div>
                <div class="form-group" style="flex: 1; margin-bottom:0;">
                    <label>商品分類：</label>
                    <select name="p_type" id="p_type" onchange="onTypeChange()" required>
                        <option value="藥材">藥材</option>
                        <option value="成藥">成藥</option>
                        <option value="配方">配方</option>
                    </select>
                </div>
            </div>

            <div class="cost-section">
                <label style="font-weight:bold; color:#E65100; font-size:22px; display:block; margin-bottom:10px;">買入成本</label>
                <div class="flex-row">
                    <input type="number" name="p_cost_price" id="p_cost_price" step="any" placeholder="買入總價" required style="flex:2;">
                    <span style="font-size:20px; font-weight:bold; color:#666;">/</span>
                    <select name="p_cost_unit" id="p_cost_unit" class="unit-dropdown" style="flex:1;"></select>
                </div>
            </div>

            <div class="sell-section">
                <label style="font-weight:bold; color:#0056B3; font-size:22px; display:block; margin-bottom:10px;">預期定價</label>
                <div class="flex-row">
                    <input type="number" name="p_sell_price" id="p_sell_price" step="any" placeholder="預期賣價" oninput="calculateBasePrice()" required style="flex:2;">
                    <span style="font-size:20px; font-weight:bold; color:#666;">/</span>
                    <select name="p_sell_unit" id="p_sell_unit" class="unit-dropdown" onchange="calculateBasePrice()" style="flex:1;"></select>
                </div>
                
                <div style="margin-top: 15px; background: #FFF; padding: 10px; border-radius: 8px; text-align:right;">
                    <span style="color:#666; font-size:18px;">POS 收銀機換算基準價 <span id="base_unit_label"></span>：</span>
                    <span style="font-size:24px; font-weight:bold; color:#28A745;">$ <span id="display_base_price">0</span></span>
                    <input type="hidden" name="p_base_price" id="p_base_price" value="0">
                </div>
            </div>

            <div class="form-group">
                <label>目前狀態：</label>
                <select name="p_status" id="p_status" required>
                    <option value="充足">✔️ 充足</option>
                    <option value="缺貨">❌ 缺貨</option>
                    <option value="停售">⚠️ 停售</option>
                </select>
            </div>

            <div class="modal-actions" style="margin-top: 20px;">
                <button type="button" class="btn-cancel" onclick="closeModal()">取消</button>
                <button type="submit" class="btn-confirm" id="btn-submit-text">確認儲存</button>
            </div>
        </form>
    </div>
</div>

<script>
const tcmUnits = { '斤': 160, '兩': 10, '錢': 1, '分': 0.1, '釐': 0.01 ,'錠劑':1, '包':1 };
const normalUnits = { '個': 1, '盒': 1, '瓶': 1, '件': 1 };

function updateUnitOptions() {
    const type = document.getElementById('p_type').value;
    const costUnitSelect = document.getElementById('p_cost_unit');
    const sellUnitSelect = document.getElementById('p_sell_unit');
    const baseLabel = document.getElementById('base_unit_label');
    
    costUnitSelect.innerHTML = ''; 
    sellUnitSelect.innerHTML = '';
    
    let targetUnits = (type === '藥材' || type === '配方') ? Object.keys(tcmUnits) : Object.keys(normalUnits);
    baseLabel.innerText = (type === '藥材' || type === '配方') ? '(每錢)' : '(每個)';

    targetUnits.forEach(unit => {
        let opt1 = new Option(unit, unit);
        let opt2 = new Option(unit, unit);
        
        if(unit === '斤' || unit === '個') {
            opt1.selected = true;
            opt2.selected = true;
        }
        
        costUnitSelect.add(opt1);
        sellUnitSelect.add(opt2);
    });
    
    calculateBasePrice();
}

function onTypeChange() {
    const isEditing = document.getElementById('form-action').value === 'edit';
    if (isEditing) {
        alert('⚠️ 已切換商品分類，買入成本與預期定價的單位選項會重新設定，請確認金額與單位是否需要重新輸入。');
    }
    updateUnitOptions();
}

function calculateBasePrice() {
    let sellPrice = parseFloat(document.getElementById('p_sell_price').value) || 0;
    let sellUnit = document.getElementById('p_sell_unit').value;
    const type = document.getElementById('p_type').value;

    let multiplier = (type === '藥材' || type === '配方') ? tcmUnits[sellUnit] : normalUnits[sellUnit];
    let basePrice = sellPrice / multiplier;
    
    let roundedPrice = Math.round(basePrice * 100) / 100;

    document.getElementById('display_base_price').innerText = roundedPrice;
    document.getElementById('p_base_price').value = roundedPrice;
}

function openModal(mode, id='', name='', costPrice='', costUnit='斤', sellPrice='', sellUnit='斤', status='充足', type='藥材') {
    const modal = document.getElementById('product-modal');
    const formAction = document.getElementById('form-action');
    const modalTitle = document.getElementById('modal-title');
    const submitBtn = document.getElementById('btn-submit-text');
    const typeSelect = document.getElementById('p_type');

    if (mode === 'add') {
        modalTitle.innerText = '➕ 建立新商品';
        formAction.value = 'add';
        submitBtn.innerText = '確認新增';
        document.getElementById('edit_id').value = '';
        document.getElementById('p_name').value = '';
        document.getElementById('p_cost_price').value = '';
        document.getElementById('p_sell_price').value = '';
        document.getElementById('p_status').value = '充足';
        typeSelect.value = '藥材';
        typeSelect.disabled = false; 
        updateUnitOptions(); 
        
    } else if (mode === 'edit') {
        modalTitle.innerText = '修改商品設定';
        formAction.value = 'edit';
        submitBtn.innerText = '儲存修改';
        document.getElementById('edit_id').value = id;
        document.getElementById('p_name').value = name;
        document.getElementById('p_cost_price').value = costPrice;
        document.getElementById('p_sell_price').value = sellPrice;
        document.getElementById('p_status').value = status;
        
        typeSelect.value = type;
        typeSelect.disabled = false;
        updateUnitOptions(); 

        document.getElementById('p_cost_unit').value = costUnit || (type === '藥材' ? '斤' : '個');
        document.getElementById('p_sell_unit').value = sellUnit || (type === '藥材' ? '斤' : '個');
        
        calculateBasePrice();
    }
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('product-modal').style.display = 'none';
}

function deleteProduct(id, name) {
    if (!confirm(`確定要刪除商品「${name}」嗎？\n⚠️ 此動作無法復原，且如果這個商品已經出現在歷史訂單或存放區指派中，建議改成「停售」狀態而不是直接刪除。`)) {
        return;
    }
    document.getElementById('delete_id').value = id;
    document.getElementById('delete-form').submit();
}
</script>

</body>
</html>