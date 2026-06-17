<?php 
include 'db_config.php'; 

// 從資料庫撈取商品
$products = [];
$categories = [];

$res = $conn->query("SELECT 商品編號, 商品名稱, 銷售單價, 商品類型 FROM 商品");
while($row = $res->fetch_assoc()) {
    $type = $row['商品類型'] ? $row['商品類型'] : '未分類';
    
    if(!in_array($type, $categories)) {
        $categories[] = $type;
    }
    
    $products[] = [
        'id' => $row['商品編號'],
        'name' => $row['商品名稱'],
        'price' => (int)$row['銷售單價'],
        'category' => $type
    ];
}
$products_json = json_encode($products, JSON_UNESCAPED_UNICODE);
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>收銀點餐系統 - 同德藥行</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="pos-mode">

    <div class="menu-section">
        <a href="index.html" class="btn-back">返回首頁</a>
        <div class="category-tabs" id="category-tabs">
            <button class="tab-btn active" onclick="filterCategory('全部')">全部商品</button>
            <?php foreach($categories as $cat): ?>
                <button class="tab-btn" onclick="filterCategory('<?php echo $cat; ?>')"><?php echo $cat; ?></button>
            <?php endforeach; ?>
        </div>
        <div class="product-grid" id="product-grid"></div>
    </div>

    <div class="cart-section">
        <div class="cart-header">
            購物車明細
            <button class="btn-clear-cart" onclick="clearCart()">全部清空</button>
        </div>
        <form action="order_save.php" method="POST" id="order-form" class="order-form">
            <div class="customer-info">
                <input type="text" name="customer_name" placeholder="顧客姓名 (必填)" required>
                <input type="text" name="customer_phone" placeholder="顧客電話">
                <input type="date" name="order_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="cart-list" id="cart-list"></div>
            <div class="checkout-area">
                <div class="total-price">總計：NT$ <span id="cart-total">0</span></div>
                <div id="hidden-inputs"></div>
                <button type="button" class="btn-submit" onclick="submitOrder()">確認送出訂單</button>
            </div>
        </form>
    </div>

    <div class="modal-overlay" id="qty-modal">
        <div class="modal-content">
            <div class="modal-title" id="modal-p-name">商品名稱</div>
            <div class="modal-price" id="modal-p-price">基準單價：$0</div>
            
            <div class="unit-selector">
                <select id="modal-unit" class="unit-select" onchange="calculateLiveSubtotal()"></select>
            </div>

            <div class="qty-control">
                <button class="btn-qty" onclick="changeQty(-1)">-</button>
                <div class="qty-display" id="modal-qty">1</div>
                <button class="btn-qty" onclick="changeQty(1)">+</button>
            </div>
            
            <div class="live-subtotal" id="live-subtotal">小計：$0</div>
            
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal()">取消</button>
                <button class="btn-confirm" onclick="confirmAddToCart()">加入購物車</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="storage-map-modal">
        <div class="modal-content" style="width: 90%; max-width: 1100px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-title" id="storage-map-title">訂單成立！抓藥位置如下</div>
            <div id="storage-map-order-id" style="font-size: 16px; color: #999; margin-bottom: 8px;"></div>
            <div id="storage-map-status" style="font-size: 18px; color: #555; margin-bottom: 10px;"></div>
            <div id="storage-map-canvas" style="position: relative; width: 100%; height: 560px; background-image: linear-gradient(to right, #F0F0F0 1px, transparent 1px), linear-gradient(to bottom, #F0F0F0 1px, transparent 1px); background-size: 20px 20px; overflow: auto; border-radius: 10px; border: 2px solid #EEE;"></div>
            <div id="storage-map-note-list" style="margin-top: 20px; text-align: left;"></div>
            <div id="storage-map-billing" style="margin-top: 24px; text-align: left;"></div>
            <button class="btn-close-modal" style="margin-top: 20px;" onclick="closeStorageMapModal()">關閉，繼續下一筆</button>
        </div>
    </div>

    <style>
        #storage-map-canvas .shelf-box {
            position: absolute; border-radius: 10px; border: 3px solid rgba(0,0,0,0.15);
            box-shadow: 0 3px 6px rgba(0,0,0,0.15); display: flex; flex-direction: column;
            align-items: center; justify-content: center; padding: 6px; box-sizing: border-box;
        }
        #storage-map-canvas .shelf-box .shelf-name { font-size: 17px; font-weight: bold; color: #333; text-align: center; word-break: break-all; }
        #storage-map-canvas .shelf-box.dimmed { opacity: 0.3; }
        #storage-map-canvas .shelf-box.highlight {
            border: 4px solid #FFC107;
            box-shadow: 0 0 0 6px rgba(255,193,7,0.45), 0 0 25px 8px rgba(255,193,7,0.7);
            animation: pulse-glow-order 1.1s infinite alternate;
            z-index: 10;
        }
        @keyframes pulse-glow-order {
            from { box-shadow: 0 0 0 6px rgba(255,193,7,0.45), 0 0 25px 8px rgba(255,193,7,0.7); }
            to   { box-shadow: 0 0 0 10px rgba(255,193,7,0.25), 0 0 40px 14px rgba(255,193,7,0.9); }
        }
        .note-list-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 18px; border-bottom: 2px solid #EEE; font-size: 20px;
        }
        .note-list-item:last-child { border-bottom: none; }
        .note-list-name { font-weight: bold; color: #333; }
        .note-list-note { color: #00695C; font-weight: bold; }

        .billing-title { font-size: 22px; font-weight: bold; color: #0056B3; margin-bottom: 10px; border-top: 2px solid #EEE; padding-top: 16px; }
        .billing-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 4px; font-size: 19px; border-bottom: 1px solid #F0F0F0; }
        .billing-row-name { color: #333; }
        .billing-row-name .billing-qty { color: #888; font-size: 16px; margin-left: 6px; }
        .billing-row-amount { color: #333; font-weight: bold; }
        .billing-total-row { display: flex; justify-content: space-between; align-items: center; padding: 16px 4px 4px; font-size: 26px; font-weight: bold; color: #28A745; }
    </style>

    <script>
        const allProducts = <?php echo $products_json; ?>;
    </script>
    
    <script src="pos_cart.js?v=<?php echo time(); ?>"></script>

    <script>
        function showOrderLocationsModal(orderId, customerName, orderItems, orderTotal) {
            const modal = document.getElementById('storage-map-modal');
            const titleEl = document.getElementById('storage-map-title');
            const orderIdEl = document.getElementById('storage-map-order-id');
            const statusEl = document.getElementById('storage-map-status');
            const canvasEl = document.getElementById('storage-map-canvas');
            const noteListEl = document.getElementById('storage-map-note-list');

            titleEl.innerText = `✅ ${customerName} 的訂單成立！抓藥位置如下`;
            orderIdEl.innerText = `訂單編號：${orderId}`;
            statusEl.innerText = '查詢存放位置中...';
            canvasEl.innerHTML = '';
            noteListEl.innerHTML = '';
            modal.style.display = 'flex';

            renderBillingDetails(orderItems, orderTotal);

            fetch('api_get_order_locations.php?order_id=' + encodeURIComponent(orderId))
                .then(r => r.json())
                .then(data => {
                    if (data.status !== 'success') {
                        statusEl.innerHTML = `<span style="color:#D32F2F;">⚠️ ${escapeMapHtml(data.msg || '查詢失敗')}</span>`;
                        return;
                    }

                    if (data.unassigned_products && data.unassigned_products.length > 0) {
                        statusEl.innerHTML = `<span style="color:#D32F2F;">⚠️ 以下商品還沒有設定任何位置資訊：${data.unassigned_products.map(escapeMapHtml).join('、')}</span>`;
                    } else {
                        statusEl.innerHTML = `依下圖與下方清單前往抓藥即可。`;
                    }

                    renderOrderLocationsMap(data.location_ids);
                    renderProductNoteList(data.products);
                })
                .catch(() => {
                    statusEl.innerHTML = '<span style="color:#D32F2F;">❌ 連線錯誤，請稍後到「存放區管理」頁面手動查詢。</span>';
                });
        }

        function renderBillingDetails(orderItems, orderTotal) {
            const billingEl = document.getElementById('storage-map-billing');
            billingEl.innerHTML = '';

            if (!orderItems || orderItems.length === 0) return;

            const titleDiv = document.createElement('div');
            titleDiv.className = 'billing-title';
            titleDiv.innerText = '💰 本次消費明細';
            billingEl.appendChild(titleDiv);

            orderItems.forEach(item => {
                const row = document.createElement('div');
                row.className = 'billing-row';
                row.innerHTML = `
                    <span class="billing-row-name">${escapeMapHtml(item.name)}<span class="billing-qty">x ${item.displayQty} ${escapeMapHtml(item.unit)}</span></span>
                    <span class="billing-row-amount">$${item.subtotal.toLocaleString('zh-TW')}</span>
                `;
                billingEl.appendChild(row);
            });

            const totalRow = document.createElement('div');
            totalRow.className = 'billing-total-row';
            totalRow.innerHTML = `<span>總計</span><span>NT$ ${orderTotal.toLocaleString('zh-TW')}</span>`;
            billingEl.appendChild(totalRow);
        }

        function renderProductNoteList(products) {
            const noteListEl = document.getElementById('storage-map-note-list');
            noteListEl.innerHTML = '';

            if (!products || products.length === 0) return;

            products.forEach(p => {
                const item = document.createElement('div');
                item.className = 'note-list-item';
                const noteText = p.note && p.note.trim() !== ''
                    ? `📍 ${escapeMapHtml(p.note)}`
                    : '<span style="color:#BBB;">尚未設定精確位置描述</span>';
                item.innerHTML = `<span class="note-list-name">${escapeMapHtml(p.name)}</span><span class="note-list-note">${noteText}</span>`;
                noteListEl.appendChild(item);
            });
        }

        function renderOrderLocationsMap(highlightIds) {
            const canvasEl = document.getElementById('storage-map-canvas');
            canvasEl.innerHTML = '';
            const highlightSet = new Set(highlightIds);

            fetch('api_storage_layout.php?action=get_locations')
                .then(r => r.json())
                .then(data => {
                    if (data.status !== 'success') return;
                    data.locations.forEach(loc => {
                        const box = document.createElement('div');
                        box.className = 'shelf-box' + (highlightSet.has(loc.id) ? ' highlight' : ' dimmed');
                        box.style.left = loc.x + 'px';
                        box.style.top = loc.y + 'px';
                        box.style.width = loc.w + 'px';
                        box.style.height = loc.h + 'px';
                        box.style.background = loc.color;

                        const nameDiv = document.createElement('div');
                        nameDiv.className = 'shelf-name';
                        nameDiv.innerText = loc.name || '(未命名)';
                        box.appendChild(nameDiv);

                        canvasEl.appendChild(box);
                    });
                });
        }

        function closeStorageMapModal() {
            document.getElementById('storage-map-modal').style.display = 'none';
        }

        function escapeMapHtml(str) {
            const div = document.createElement('div');
            div.innerText = str;
            return div.innerHTML;
        }
    </script>
</body>
</html>