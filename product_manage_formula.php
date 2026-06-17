<?php
include 'db_config.php';

// 處理配方組成保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['formula_action'])) {
    $formula_action = $_POST['formula_action'];
    $formula_id = $_POST['formula_id'] ?? '';
    
    if ($formula_action === 'save_ingredients') {
        $ingredients_json = $_POST['ingredients_json'] ?? '[]';
        $ingredients = json_decode($ingredients_json, true);
        
        if (!is_array($ingredients) || empty($ingredients)) {
            echo json_encode(['status' => 'error', 'msg' => '配方必須至少包含一種藥材']);
            exit;
        }
        
        $conn->begin_transaction();
        try {
            // 先刪除舊的配方藥材組成
            $conn->query("DELETE FROM 藥材組成配方 WHERE 配方商品編號 = '$formula_id'");
            
            // 插入新的藥材組成
            $stmt = $conn->prepare("INSERT INTO 藥材組成配方 (配方商品編號, 藥材商品編號, 數量) VALUES (?, ?, ?)");
            
            foreach ($ingredients as $ing) {
                $herb_id = $ing['herb_id'];
                $qty = floatval($ing['qty']);
                
                $stmt->bind_param("ssd", $formula_id, $herb_id, $qty);
                $stmt->execute();
            }
            
            $conn->commit();
            echo json_encode(['status' => 'success', 'msg' => '配方藥材已保存']);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            exit;
        }
    }
}

// 取得所有配方
$formulas = [];
$res = $conn->query("SELECT 商品編號, 商品名稱 FROM 商品 WHERE 商品類型 = '配方' ORDER BY 商品編號");
while ($row = $res->fetch_assoc()) {
    $formulas[] = [
        'id' => $row['商品編號'],
        'name' => $row['商品名稱']
    ];
}

// 如果指定了配方ID，取得其藥材組成
$selected_formula = null;
$formula_ingredients = [];
if (isset($_GET['formula_id'])) {
    $formula_id = $_GET['formula_id'];
    $res = $conn->query("SELECT 商品編號, 商品名稱 FROM 商品 WHERE 商品編號 = '$formula_id' AND 商品類型 = '配方'");
    if ($res && $res->num_rows > 0) {
        $selected_formula = $res->fetch_assoc();
        
        // 取得配方的藥材組成
        $res = $conn->query("
            SELECT f.藥材商品編號, p.商品名稱, f.數量
            FROM 藥材組成配方 f
            JOIN 商品 p ON f.藥材商品編號 = p.商品編號
            WHERE f.配方商品編號 = '$formula_id'
            ORDER BY p.商品編號
        ");
        while ($row = $res->fetch_assoc()) {
            $formula_ingredients[] = $row;
        }
    }
}

// 取得所有藥材供選擇
$all_herbs = [];
$res = $conn->query("SELECT 商品編號, 商品名稱 FROM 商品 WHERE 商品類型 = '藥材' ORDER BY 商品編號");
while ($row = $res->fetch_assoc()) {
    $all_herbs[] = $row;
}

$formulas_json = json_encode($formulas, JSON_UNESCAPED_UNICODE);
$herbs_json = json_encode($all_herbs, JSON_UNESCAPED_UNICODE);
$ingredients_json = json_encode($formula_ingredients, JSON_UNESCAPED_UNICODE);
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>配方組成管理 - 同德藥行</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .formula-container { max-width: 1200px; margin: 20px auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .header-area { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #E67E22; padding-bottom: 15px; margin-bottom: 25px; }
        .header-area h1 { color: #E67E22; margin: 0; font-size: 36px; }

        .formula-selector { display: flex; gap: 15px; margin-bottom: 25px; align-items: center; flex-wrap: wrap; }
        .formula-selector select { padding: 12px; font-size: 20px; border: 2px solid #CCC; border-radius: 8px; flex: 1; min-width: 250px; }
        .formula-selector button { background: #0056B3; color: white; border: none; padding: 12px 25px; font-size: 20px; border-radius: 8px; cursor: pointer; font-weight: bold; }

        .formula-details { background: #FFF3E0; padding: 20px; border-radius: 10px; border: 2px solid #FFB74D; margin-bottom: 25px; }
        .formula-details h2 { color: #E67E22; margin-top: 0; }

        .ingredient-row { display: flex; gap: 15px; align-items: center; margin-bottom: 15px; padding: 15px; background: #F9F9F9; border-radius: 8px; border: 2px solid #EEE; }
        .ingredient-select { flex: 1; padding: 10px; font-size: 18px; border: 2px solid #CCC; border-radius: 6px; }
        .ingredient-input { flex: 0.5; padding: 10px; font-size: 18px; border: 2px solid #CCC; border-radius: 6px; }
        .ingredient-remove { background: #DC3545; color: white; border: none; padding: 10px 15px; font-size: 16px; border-radius: 6px; cursor: pointer; font-weight: bold; }

        .button-group { display: flex; gap: 15px; margin-top: 25px; }
        .btn-save { background: #28A745; color: white; border: none; padding: 15px 30px; font-size: 22px; border-radius: 8px; cursor: pointer; font-weight: bold; flex: 1; }
        .btn-add-ingredient { background: #0056B3; color: white; border: none; padding: 12px 25px; font-size: 20px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-back { text-decoration: none; color: #555; font-size: 22px; font-weight: bold; border: 2px solid #CCC; display: inline-block; width: 120px; text-align: center; padding: 8px; border-radius: 8px; background: #FFF; }
    </style>
</head>
<body>

<div class="formula-container">
    <div class="header-area">
        <h1>配方組成管理</h1>
        <a href="product_manage.php" class="btn-back">返回</a>
    </div>

    <div class="formula-selector">
        <select id="formula-select" onchange="selectFormula()">
            <option value="">-- 選擇要編輯的配方 --</option>
            <?php foreach ($formulas as $formula): ?>
                <option value="<?php echo $formula['id']; ?>" <?php echo (isset($_GET['formula_id']) && $_GET['formula_id'] === $formula['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($formula['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($selected_formula): ?>
        <div class="formula-details">
            <h2>配方：<?php echo htmlspecialchars($selected_formula['商品名稱']); ?></h2>
            <p>編號：<?php echo htmlspecialchars($selected_formula['商品編號']); ?></p>

            <form id="ingredients-form" method="POST" onsubmit="submitIngredients(event)">
                <input type="hidden" name="formula_action" value="save_ingredients">
                <input type="hidden" name="formula_id" value="<?php echo $selected_formula['商品編號']; ?>">
                <input type="hidden" name="ingredients_json" id="ingredients-json" value="">

                <h3 style="margin-top: 25px; color: #0056B3;">藥材組成</h3>
                <div id="ingredients-list"></div>

                <button type="button" class="btn-add-ingredient" onclick="addIngredientRow()">➕ 新增藥材</button>

                <div class="button-group">
                    <button type="submit" class="btn-save">💾 儲存配方組成</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div style="text-align: center; color: #999; font-size: 20px; padding: 50px;">
            請選擇一個配方進行編輯
        </div>
    <?php endif; ?>
</div>

<script>
const allHerbs = <?php echo $herbs_json; ?>;
const currentIngredients = <?php echo $ingredients_json; ?>;

function selectFormula() {
    const select = document.getElementById('formula-select');
    if (select.value) {
        window.location.href = '?formula_id=' + select.value;
    }
}

function addIngredientRow() {
    const list = document.getElementById('ingredients-list');
    const row = document.createElement('div');
    row.className = 'ingredient-row';
    row.innerHTML = `
        <select class="ingredient-select">
            <option value="">-- 選擇藥材 --</option>
            ${allHerbs.map(h => `<option value="${h['商品編號']}">${escapeHtml(h['商品名稱'])}</option>`).join('')}
        </select>
        <input type="number" class="ingredient-input" placeholder="數量（錢）" step="0.01" min="0.01" value="1">
        <button type="button" class="ingredient-remove" onclick="this.parentElement.remove()">刪除</button>
    `;
    list.appendChild(row);
}

function submitIngredients(e) {
    e.preventDefault();
    
    const rows = document.querySelectorAll('.ingredient-row');
    const ingredients = [];
    const usedHerbs = new Set();

    rows.forEach(row => {
        const select = row.querySelector('select');
        const input = row.querySelector('input');
        
        const herbId = select.value;
        const qty = parseFloat(input.value) || 0;

        if (!herbId) {
            alert('請選擇所有藥材');
            return;
        }
        
        if (usedHerbs.has(herbId)) {
            alert('同一個藥材不能重複新增');
            return;
        }

        if (qty <= 0) {
            alert('數量必須大於 0');
            return;
        }

        usedHerbs.add(herbId);
        ingredients.push({
            herb_id: herbId,
            qty: qty
        });
    });

    if (ingredients.length === 0) {
        alert('請至少新增一種藥材');
        return;
    }

    document.getElementById('ingredients-json').value = JSON.stringify(ingredients);
    document.getElementById('ingredients-form').submit();
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.innerText = str;
    return div.innerHTML;
}

// 初始化顯示現有藥材
window.addEventListener('load', () => {
    const list = document.getElementById('ingredients-list');
    if (list && currentIngredients.length > 0) {
        currentIngredients.forEach(ing => {
            const row = document.createElement('div');
            row.className = 'ingredient-row';
            row.innerHTML = `
                <select class="ingredient-select">
                    <option value="">-- 選擇藥材 --</option>
                    ${allHerbs.map(h => `<option value="${h['商品編號']}" ${h['商品編號'] === ing['藥材商品編號'] ? 'selected' : ''}>${escapeHtml(h['商品名稱'])}</option>`).join('')}
                </select>
                <input type="number" class="ingredient-input" value="${parseFloat(ing['數量'])}" step="0.01" min="0.01">
                <button type="button" class="ingredient-remove" onclick="this.parentElement.remove()">刪除</button>
            `;
            list.appendChild(row);
        });
    } else if (list) {
        // 如果沒有現有藥材，添加一個空白列
        addIngredientRow();
    }
});
</script>

</body>
</html>
