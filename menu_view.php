<?php
include 'db_config.php';

// 只撈取「供應狀態」不是停售的商品，停售品不給顧客看到
$products = [];
$categories = [];

$res = $conn->query("SELECT 商品編號, 商品名稱, 銷售單價, 商品類型, 供應狀態 FROM 商品 WHERE 供應狀態 != '停售' ORDER BY 商品類型, 商品編號");
while ($row = $res->fetch_assoc()) {
    $type = $row['商品類型'] ? $row['商品類型'] : '未分類';

    if (!in_array($type, $categories)) {
        $categories[] = $type;
    }

    $products[] = [
        'id' => $row['商品編號'],
        'name' => $row['商品名稱'],
        'price' => (float)$row['銷售單價'],
        'category' => $type,
        'status' => $row['供應狀態'],
    ];
}
$products_json = json_encode($products, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品總覽 - 同德藥行</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .menu-view-page { max-width: 1100px; margin: 0 auto; padding: 20px; box-sizing: border-box; }
        .menu-view-header { text-align: center; padding: 30px 0 10px; }
        .menu-view-header h1 { color: #0056B3; font-size: 38px; margin: 0 0 8px; }
        .menu-view-header p { color: #888; font-size: 18px; margin: 0; }

        .menu-view-search { padding: 14px 18px; font-size: 22px; width: 100%; border: 2px solid #CCC; border-radius: 10px; box-sizing: border-box; margin: 20px 0; }

        .menu-view-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; padding-bottom: 40px; }
        .menu-view-card { background: white; border: 2px solid #E0E0E0; border-radius: 15px; padding: 25px 18px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .menu-view-card .p-name { font-size: 26px; font-weight: bold; margin-bottom: 12px; color: #222; }
        .menu-view-card .p-price { font-size: 22px; color: #E65100; font-weight: bold; }
        .menu-view-card .p-unit-tag { font-size: 16px; color: #666; background: #EEE; padding: 2px 8px; border-radius: 5px; margin-left: 5px; }
        .menu-view-card .p-status { display: inline-block; margin-top: 10px; font-size: 15px; padding: 3px 12px; border-radius: 20px; font-weight: bold; }
        .p-status.status-ok { background: #E8F5E9; color: #2E7D32; }
        .p-status.status-empty { background: #FFEBEE; color: #D32F2F; }

        .menu-view-empty { text-align: center; color: #999; font-size: 22px; padding: 60px 0; }
    </style>
</head>
<body>

<div class="menu-view-page">
    <div class="menu-view-header">
        <h1>同德藥行 商品總覽</h1>
        <p>價格僅供參考，實際金額依現場結帳為準</p>
    </div>

    <div class="category-tabs" id="category-tabs" style="justify-content:center;">
        <button class="tab-btn active" onclick="filterCategory('全部')">全部商品</button>
        <?php foreach ($categories as $cat): ?>
            <button class="tab-btn" onclick="filterCategory('<?php echo $cat; ?>')"><?php echo $cat; ?></button>
        <?php endforeach; ?>
    </div>

    <input type="text" class="menu-view-search" id="menu-search" placeholder="搜尋商品名稱..." oninput="renderMenu()">

    <div class="menu-view-grid" id="menu-grid"></div>

    <div style="text-align:center; padding: 10px 0 30px;">
        <a href="index.html" style="color:#AAA; font-size:14px; text-decoration:none;">管理系統入口</a>
    </div>
</div>

<script>
    const allProducts = <?php echo $products_json; ?>;
    let currentCategory = '全部';

    function filterCategory(category) {
        currentCategory = category;
        const tabs = document.querySelectorAll('.tab-btn');
        tabs.forEach(tab => {
            tab.classList.remove('active');
            if (tab.innerText === category || (category === '全部' && tab.innerText === '全部商品')) {
                tab.classList.add('active');
            }
        });
        renderMenu();
    }

    function renderMenu() {
        const keyword = document.getElementById('menu-search').value.trim().toLowerCase();
        const grid = document.getElementById('menu-grid');
        grid.innerHTML = '';

        const filtered = allProducts.filter(p => {
            const matchCategory = (currentCategory === '全部' || p.category === currentCategory);
            const matchKeyword = !keyword || p.name.toLowerCase().includes(keyword);
            return matchCategory && matchKeyword;
        });

        if (filtered.length === 0) {
            grid.innerHTML = '<div class="menu-view-empty">沒有找到符合的商品</div>';
            return;
        }

        filtered.forEach(p => {
            const isTcm = (p.category === '藥材' || p.category === '配方');
            const unitTag = isTcm ? '/錢' : '/個';
            const statusOk = (p.status === '充足');

            const card = document.createElement('div');
            card.className = 'menu-view-card';
            card.innerHTML = `
                <div class="p-name">${escapeHtml(p.name)}</div>
                <div class="p-price">$${p.price.toLocaleString('zh-TW')} <span class="p-unit-tag">${unitTag}</span></div>
                <div class="p-status ${statusOk ? 'status-ok' : 'status-empty'}">${statusOk ? '✔️ 供應中' : '❌ ' + escapeHtml(p.status)}</div>
            `;
            grid.appendChild(card);
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.innerText = str;
        return div.innerHTML;
    }

    renderMenu();
</script>

</body>
</html>
