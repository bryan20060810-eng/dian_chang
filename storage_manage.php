<?php
include 'db_config.php';

// 撈取所有存放位置（含座標）
$locations = [];
$res = $conn->query("SELECT 位置編號, 區域名稱, 樓層, pos_x, pos_y, width, height, shape_color FROM 存放位置 ORDER BY 樓層, 位置編號");
while ($row = $res->fetch_assoc()) {
    $locations[$row['位置編號']] = [
        'id' => $row['位置編號'],
        'name' => $row['區域名稱'],
        'floor' => (int)$row['樓層'],
        'x' => (int)$row['pos_x'],
        'y' => (int)$row['pos_y'],
        'w' => (int)$row['width'],
        'h' => (int)$row['height'],
        'color' => $row['shape_color'],
        'products' => [],
    ];
}

// 撈取每個位置目前指派的商品
$res = $conn->query("SELECT m.位置編號, p.商品編號, p.商品名稱, p.商品類型
                      FROM 存放位置明細 m
                      JOIN 商品 p ON m.商品編號 = p.商品編號
                      ORDER BY p.商品編號");
$product_location_map = []; // 商品編號 => [位置編號, ...]
while ($row = $res->fetch_assoc()) {
    if (isset($locations[$row['位置編號']])) {
        $locations[$row['位置編號']]['products'][] = [
            'id' => $row['商品編號'],
            'name' => $row['商品名稱'],
        ];
    }
    $product_location_map[$row['商品編號']][] = $row['位置編號'];
}

// 撈取所有商品（用於指派清單）
$products = [];
$res = $conn->query("SELECT 商品編號, 商品名稱, 商品類型, 位置描述 FROM 商品 ORDER BY 商品類型, 商品編號");
while ($row = $res->fetch_assoc()) {
    $products[] = [
        'id' => $row['商品編號'],
        'name' => $row['商品名稱'],
        'type' => $row['商品類型'],
        'note' => $row['位置描述'] ?? '',
        'locations' => $product_location_map[$row['商品編號']] ?? [],
    ];
}

$locations_json = json_encode(array_values($locations), JSON_UNESCAPED_UNICODE);
$products_json = json_encode($products, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>存放區管理 - 同德藥行</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .storage-layout-grid { display: flex; gap: 20px; max-width: 1400px; margin: 20px auto; align-items: flex-start; flex-wrap: wrap; }
        .map-panel { flex: 2; min-width: 600px; background: white; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); padding: 20px; }
        .side-panel { flex: 1; min-width: 320px; background: white; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); padding: 20px; max-height: 750px; overflow-y: auto; }

        .mode-toolbar { display: flex; gap: 12px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; }
        .btn-toggle-edit { background: #E67E22; color: white; border: none; padding: 12px 22px; font-size: 19px; font-weight: bold; border-radius: 10px; cursor: pointer; }
        .btn-toggle-edit.editing { background: #6C757D; }
        .btn-new-shelf { background: #0056B3; color: white; border: none; padding: 12px 22px; font-size: 19px; font-weight: bold; border-radius: 10px; cursor: pointer; display: none; }
        .btn-save-layout { background: #28A745; color: white; border: none; padding: 12px 22px; font-size: 19px; font-weight: bold; border-radius: 10px; cursor: pointer; display: none; }
        .edit-mode-hint { color: #E67E22; font-weight: bold; font-size: 16px; display: none; }

        .map-canvas { position: relative; width: 100%; height: 640px; background-image:
                linear-gradient(to right, #F0F0F0 1px, transparent 1px),
                linear-gradient(to bottom, #F0F0F0 1px, transparent 1px);
            background-size: 20px 20px; overflow: auto; border-radius: 10px; border: 2px solid #EEE; touch-action: none; }

        .shelf-box { position: absolute; border-radius: 10px; border: 3px solid rgba(0,0,0,0.15);
            box-shadow: 0 3px 6px rgba(0,0,0,0.15); display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 6px; box-sizing: border-box; cursor: pointer; transition: box-shadow .2s, transform .2s; touch-action: none; user-select: none; }
        .shelf-box .shelf-name { font-size: 17px; font-weight: bold; color: #333; text-align: center; word-break: break-all; pointer-events: none; }
        .shelf-box .shelf-count { font-size: 14px; color: #555; background: rgba(255,255,255,0.7); padding: 1px 6px; border-radius: 6px; margin-top: 4px; pointer-events: none; }
        .shelf-box.dragover { outline: 4px dashed #0056B3; }
        .shelf-box.selected { outline: 4px solid #0056B3; z-index: 5; }
        .shelf-box.edit-mode { cursor: grab; }
        .shelf-box.edit-mode:active { cursor: grabbing; }

        .shelf-box.highlight {
            border: 4px solid #FFC107;
            box-shadow: 0 0 0 6px rgba(255,193,7,0.45), 0 0 25px 8px rgba(255,193,7,0.7);
            animation: pulse-glow 1.1s infinite alternate;
            z-index: 10;
        }
        @keyframes pulse-glow {
            from { box-shadow: 0 0 0 6px rgba(255,193,7,0.45), 0 0 25px 8px rgba(255,193,7,0.7); }
            to   { box-shadow: 0 0 0 10px rgba(255,193,7,0.25), 0 0 40px 14px rgba(255,193,7,0.9); }
        }
        .shelf-box.dimmed { opacity: 0.35; }

        .resize-handle {
            position: absolute; width: 22px; height: 22px; background: #0056B3; border: 2px solid white;
            border-radius: 50%; right: -11px; bottom: -11px; cursor: nwse-resize; touch-action: none;
        }
        .delete-handle {
            position: absolute; width: 26px; height: 26px; background: #DC3545; color: white; border: 2px solid white;
            border-radius: 50%; left: -13px; top: -13px; cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 16px; font-weight: bold; touch-action: none;
        }

        .edit-shape-panel {
            position: fixed; right: 30px; top: 50%; transform: translateY(-50%);
            background: white; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            padding: 25px; width: 280px; display: none; z-index: 50;
        }
        .edit-shape-panel.show { display: block; }
        .edit-shape-panel label { display: block; font-weight: bold; color: #0056B3; font-size: 18px; margin-bottom: 6px; margin-top: 14px; }
        .edit-shape-panel input { width: 100%; padding: 8px; font-size: 18px; border: 2px solid #CCC; border-radius: 6px; box-sizing: border-box; }
        .color-options { display: flex; gap: 8px; margin-top: 6px; flex-wrap: wrap; }
        .color-swatch { width: 34px; height: 34px; border-radius: 50%; cursor: pointer; border: 3px solid transparent; }
        .color-swatch.active { border-color: #333; }

        .product-search { padding: 12px; font-size: 20px; width: 100%; border: 2px solid #CCC; border-radius: 8px; box-sizing: border-box; margin-bottom: 12px; }
        .product-row { display: flex; align-items: flex-start; padding: 12px; border-bottom: 2px solid #EEE; cursor: grab; border-radius: 8px; }
        .product-row:hover { background: #F5F9FF; }
        .product-row.assigned { background: #F1F8F4; }
        .product-row-name { font-weight: bold; color: #333; font-size: 19px; }
        .product-row-type { font-size: 14px; color: #888; }
        .product-row-locs { font-size: 14px; color: #0056B3; margin-top: 3px; }
        .product-row-locs .none { color: #BBB; }
        .btn-unassign { background: #FFEBEE; color: #D32F2F; border: 1px solid #FFCDD2; border-radius: 6px; font-size: 13px; padding: 2px 8px; cursor: pointer; margin-left: 4px; }

        .product-row-note { margin-top: 8px; padding-top: 8px; border-top: 1px dashed #DDD; font-size: 15px; }
        .product-row-note .note-display { color: #00695C; font-weight: bold; }
        .product-row-note .note-display .none { color: #BBB; font-weight: normal; }
        .note-input { padding: 6px 10px; font-size: 16px; border: 2px solid #00897B; border-radius: 6px; width: 200px; margin-right: 6px; }
        .btn-note-edit { background: #E0F2F1; color: #00695C; border: 1px solid #80CBC4; border-radius: 6px; font-size: 13px; padding: 4px 10px; cursor: pointer; margin-left: 8px; }
        .btn-note-save { background: #00897B; color: white; border: none; border-radius: 6px; font-size: 13px; padding: 4px 10px; cursor: pointer; }

        .legend-hint { font-size: 16px; color: #888; margin-top: 10px; }
        .panel-title { font-size: 24px; font-weight: bold; color: #0056B3; margin-bottom: 12px; }
        .btn-edit { background: #FFF3E0; color: #E67E22; border: 1px solid #FFCC80; padding: 10px 18px; font-size: 18px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .loc-name-input { margin-right: 6px; }
    </style>
</head>
<body>

<div class="admin-container" style="max-width:1400px;">
    <div class="header-area">
        <h1>存放區管理與商品位置</h1>
        <div>
            <a href="index.html" class="btn-back">返回首頁</a>
        </div>
    </div>
</div>

<div class="storage-layout-grid">
    <div class="map-panel">
        <div class="mode-toolbar">
            <button class="btn-toggle-edit" id="btn-toggle-edit" onclick="toggleEditMode()">🛠️ 編輯平面圖</button>
            <button class="btn-new-shelf" id="btn-new-shelf" onclick="addShelf()">➕ 新增貨架方塊</button>
            <button class="btn-save-layout" id="btn-save-layout" onclick="saveLayout()">💾 儲存並返回</button>
            <span class="edit-mode-hint" id="edit-mode-hint">編輯模式中：拖曳方塊移動位置，拖右下角圓點調整大小，點方塊編輯名稱/樓層/顏色</span>
        </div>
        <div class="map-canvas" id="map-canvas"></div>
        <div class="legend-hint" id="view-mode-hint">💡 提示：把右側商品拖曳到方塊上即可指派位置。結帳成立時，系統會自動彈出平面圖並亮起該訂單的商品位置，這裡不需要手動查詢。</div>
    </div>

    <div class="side-panel" id="side-panel-products">
        <div class="panel-title">商品清單</div>
        <input type="text" class="product-search" id="product-search" placeholder="搜尋商品名稱或編號..." oninput="renderProducts()">
        <div id="product-list"></div>
    </div>
</div>

<div class="admin-container" style="max-width:1400px; margin-top:0;">
    <div class="panel-title">存放位置清單</div>
    <table>
        <thead>
            <tr>
                <th>位置編號</th>
                <th>區域名稱</th>
                <th>樓層</th>
                <th>目前商品數</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody id="location-list-body"></tbody>
    </table>
</div>

<div class="edit-shape-panel" id="edit-shape-panel">
    <label>區域名稱（例如：A櫃-第1抽屜）</label>
    <input type="text" id="panel-name" oninput="updateSelectedField('name', this.value)">
    <label>樓層</label>
    <input type="number" id="panel-floor" oninput="updateSelectedField('floor', this.value)">
    <label>顏色</label>
    <div class="color-options" id="color-options"></div>
    <div style="margin-top:20px; text-align:center;">
        <button class="btn-cancel" style="width:100%; padding:10px; font-size:18px; border-radius:8px; border:2px solid #999; background:white; cursor:pointer;" onclick="deselectShape()">完成編輯</button>
    </div>
</div>

<script>
let locations = <?php echo $locations_json ?: '[]'; ?>;
let products = <?php echo $products_json ?: '[]'; ?>;
let isEditMode = false;
let nextTempId = 1;
let selectedShapeId = null;
let dragState = null;
const colorPalette = ['#90CAF9', '#A5D6A7', '#FFCC80', '#CE93D8', '#FFAB91', '#80CBC4'];

const mapCanvas = document.getElementById('map-canvas');

function toggleEditMode() {
    if (isEditMode) {
        if (!confirm('要放棄目前平面圖的變更並返回檢視模式嗎？\n（如果想保留變更，請按「儲存並返回」）')) return;
        exitEditMode(true);
    } else {
        enterEditMode();
    }
}

function enterEditMode() {
    isEditMode = true;
    document.getElementById('btn-toggle-edit').innerText = '✖ 取消編輯';
    document.getElementById('btn-toggle-edit').classList.add('editing');
    document.getElementById('btn-new-shelf').style.display = 'inline-block';
    document.getElementById('btn-save-layout').style.display = 'inline-block';
    document.getElementById('edit-mode-hint').style.display = 'inline';
    document.getElementById('view-mode-hint').style.display = 'none';
    document.getElementById('side-panel-products').style.display = 'none';
    mapCanvas.onclick = () => deselectShape();
    renderMap();
}

function exitEditMode(reload) {
    isEditMode = false;
    selectedShapeId = null;
    document.getElementById('btn-toggle-edit').innerText = '🛠️ 編輯平面圖';
    document.getElementById('btn-toggle-edit').classList.remove('editing');
    document.getElementById('btn-new-shelf').style.display = 'none';
    document.getElementById('btn-save-layout').style.display = 'none';
    document.getElementById('edit-mode-hint').style.display = 'none';
    document.getElementById('view-mode-hint').style.display = 'block';
    document.getElementById('side-panel-products').style.display = 'block';
    document.getElementById('edit-shape-panel').classList.remove('show');
    mapCanvas.onclick = null;

    if (reload) {
        window.location.reload();
    } else {
        renderMap();
        renderProducts();
        renderLocationList();
    }
}

function renderMap() {
    mapCanvas.innerHTML = '';
    locations.forEach(loc => {
        const box = document.createElement('div');
        let cls = 'shelf-box';
        if (isEditMode) {
            cls += ' edit-mode';
            if (loc.id === selectedShapeId) cls += ' selected';
        }
        box.className = cls;
        box.style.left = loc.x + 'px';
        box.style.top = loc.y + 'px';
        box.style.width = loc.w + 'px';
        box.style.height = loc.h + 'px';
        box.style.background = loc.color;
        box.dataset.locId = loc.id;
        box.title = (loc.products || []).map(p => p.name).join('、') || '（尚無商品）';

        const nameDiv = document.createElement('div');
        nameDiv.className = 'shelf-name';
        nameDiv.innerText = loc.name || '(未命名)';
        box.appendChild(nameDiv);

        if (!isEditMode) {
            const countDiv = document.createElement('div');
            countDiv.className = 'shelf-count';
            countDiv.innerText = (loc.products ? loc.products.length : 0) + ' 項商品';
            box.appendChild(countDiv);
        } else {
            const floorTag = document.createElement('div');
            floorTag.className = 'shelf-count';
            floorTag.innerText = (loc.floor || 1) + ' 樓';
            box.appendChild(floorTag);

            const delHandle = document.createElement('div');
            delHandle.className = 'delete-handle';
            delHandle.innerText = '✕';
            delHandle.onpointerdown = (e) => { e.stopPropagation(); };
            delHandle.onclick = (e) => { e.stopPropagation(); removeShelf(loc.id); };
            box.appendChild(delHandle);

            const resizeHandle = document.createElement('div');
            resizeHandle.className = 'resize-handle';
            resizeHandle.onpointerdown = (e) => startResize(e, loc.id);
            box.appendChild(resizeHandle);

            box.onpointerdown = (e) => startDrag(e, loc.id);
            box.onclick = (e) => { e.stopPropagation(); selectShape(loc.id); };
        }

        if (!isEditMode) {
            box.ondragover = (e) => { e.preventDefault(); box.classList.add('dragover'); };
            box.ondragleave = () => box.classList.remove('dragover');
            box.ondrop = (e) => {
                e.preventDefault();
                box.classList.remove('dragover');
                const productId = e.dataTransfer.getData('text/product-id');
                if (productId) assignProduct(productId, loc.id);
            };
        }

        mapCanvas.appendChild(box);
    });
}

function addShelf() {
    const id = 'NEW_' + (nextTempId++);
    locations.push({ id, name: '新貨架', floor: 1, x: 30, y: 30, w: 110, h: 90, color: colorPalette[0], products: [] });
    selectedShapeId = id;
    renderMap();
    openShapePanel(id);
}

function removeShelf(id) {
    if (!confirm('確定要刪除這個貨架方塊嗎？\n（儲存後，原本指派到此位置的商品也會解除指派）')) return;
    locations = locations.filter(l => l.id !== id);
    if (selectedShapeId === id) deselectShape();
    renderMap();
}

function selectShape(id) {
    selectedShapeId = id;
    renderMap();
    openShapePanel(id);
}

function deselectShape() {
    selectedShapeId = null;
    document.getElementById('edit-shape-panel').classList.remove('show');
    renderMap();
}

function openShapePanel(id) {
    const loc = locations.find(l => l.id === id);
    if (!loc) return;
    document.getElementById('panel-name').value = loc.name || '';
    document.getElementById('panel-floor').value = loc.floor || 1;
    const colorWrap = document.getElementById('color-options');
    colorWrap.innerHTML = '';
    colorPalette.forEach(c => {
        const sw = document.createElement('div');
        sw.className = 'color-swatch' + (loc.color === c ? ' active' : '');
        sw.style.background = c;
        sw.onclick = () => { updateSelectedField('color', c); openShapePanel(id); };
        colorWrap.appendChild(sw);
    });
    document.getElementById('edit-shape-panel').classList.add('show');
}

function updateSelectedField(field, value) {
    const loc = locations.find(l => l.id === selectedShapeId);
    if (!loc) return;
    loc[field] = (field === 'floor') ? (parseInt(value) || 1) : value;
    renderMap();
}

function startDrag(e, id) {
    e.preventDefault();
    e.stopPropagation();
    const loc = locations.find(l => l.id === id);
    dragState = {
        type: 'move', id,
        startX: e.clientX, startY: e.clientY,
        origX: loc.x, origY: loc.y
    };
    selectedShapeId = id;
    document.addEventListener('pointermove', onDragMove);
    document.addEventListener('pointerup', onDragEnd);
}

function startResize(e, id) {
    e.preventDefault();
    e.stopPropagation();
    const loc = locations.find(l => l.id === id);
    dragState = {
        type: 'resize', id,
        startX: e.clientX, startY: e.clientY,
        origW: loc.w, origH: loc.h
    };
    selectedShapeId = id;
    document.addEventListener('pointermove', onDragMove);
    document.addEventListener('pointerup', onDragEnd);
}

function onDragMove(e) {
    if (!dragState) return;
    const loc = locations.find(l => l.id === dragState.id);
    if (!loc) return;
    const dx = e.clientX - dragState.startX;
    const dy = e.clientY - dragState.startY;
    if (dragState.type === 'move') {
        loc.x = Math.max(0, dragState.origX + dx);
        loc.y = Math.max(0, dragState.origY + dy);
    } else if (dragState.type === 'resize') {
        loc.w = Math.max(60, dragState.origW + dx);
        loc.h = Math.max(50, dragState.origH + dy);
    }
    renderMap();
}

function onDragEnd() {
    dragState = null;
    document.removeEventListener('pointermove', onDragMove);
    document.removeEventListener('pointerup', onDragEnd);
}

function saveLayout() {
    fetch('api_storage_layout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_layout', locations })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            alert('✅ 平面圖已儲存！');
            window.location.reload();
        } else {
            alert('❌ 儲存失敗：' + (data.msg || '未知錯誤'));
        }
    })
    .catch(err => alert('❌ 連線錯誤：' + err));
}

function renderProducts() {
    const keyword = document.getElementById('product-search').value.trim().toLowerCase();
    const listEl = document.getElementById('product-list');
    listEl.innerHTML = '';

    const filtered = products.filter(p =>
        !keyword || p.name.toLowerCase().includes(keyword) || p.id.toLowerCase().includes(keyword)
    );

    if (filtered.length === 0) {
        listEl.innerHTML = '<div style="text-align:center; color:#999; padding:20px;">沒有符合的商品</div>';
        return;
    }

    filtered.forEach(p => {
        const row = document.createElement('div');
        row.className = 'product-row' + (p.locations.length > 0 ? ' assigned' : '');
        row.draggable = true;
        row.ondragstart = (e) => e.dataTransfer.setData('text/product-id', p.id);

        const left = document.createElement('div');
        left.style.width = '100%';
        left.innerHTML = `<div class="product-row-name">${escapeHtml(p.name)}</div>
                           <div class="product-row-type">${escapeHtml(p.type || '')} · ${escapeHtml(p.id)}</div>`;

        const locsDiv = document.createElement('div');
        locsDiv.className = 'product-row-locs';
        if (p.locations.length === 0) {
            locsDiv.innerHTML = '<span class="none">尚未指派平面圖位置</span>';
        } else {
            locsDiv.innerHTML = p.locations.map(locId => {
                const loc = locations.find(l => l.id === locId);
                const name = loc ? loc.name : locId;
                return `${escapeHtml(name)} <button class="btn-unassign" onclick="unassignProduct('${p.id}','${locId}')">移除</button>`;
            }).join(' ');
        }
        left.appendChild(locsDiv);

        const noteDiv = document.createElement('div');
        noteDiv.className = 'product-row-note';
        noteDiv.innerHTML = `
            <span class="note-display" id="note-display-${p.id}">${p.note ? '📍 ' + escapeHtml(p.note) : '<span class="none">尚未填寫精確位置描述</span>'}</span>
            <input type="text" class="note-input" id="note-input-${p.id}" value="${escapeHtml(p.note || '')}" placeholder="例如：A櫃-第1排第1格" style="display:none;">
            <button class="btn-note-edit" id="note-editbtn-${p.id}" onclick="startEditNote('${p.id}')">設定位置描述</button>
            <button class="btn-note-save" id="note-savebtn-${p.id}" style="display:none;" onclick="saveNote('${p.id}')">儲存</button>
        `;
        left.appendChild(noteDiv);

        row.appendChild(left);
        listEl.appendChild(row);
    });
}

function startEditNote(productId) {
    document.getElementById(`note-display-${productId}`).style.display = 'none';
    document.getElementById(`note-input-${productId}`).style.display = 'inline-block';
    document.getElementById(`note-editbtn-${productId}`).style.display = 'none';
    document.getElementById(`note-savebtn-${productId}`).style.display = 'inline-block';
    document.getElementById(`note-input-${productId}`).focus();
}

function saveNote(productId) {
    const newNote = document.getElementById(`note-input-${productId}`).value.trim();

    fetch('api_storage_layout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_product_note', product_id: productId, note: newNote })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            const product = products.find(p => p.id === productId);
            if (product) product.note = newNote;
            renderProducts();
        } else {
            alert('❌ 儲存失敗：' + (data.msg || '未知錯誤'));
        }
    })
    .catch(err => alert('❌ 連線錯誤：' + err));
}

function assignProduct(productId, locId) {
    fetch('api_storage_layout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'assign_product', product_id: productId, location_id: locId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            const product = products.find(p => p.id === productId);
            const loc = locations.find(l => l.id === locId);
            if (product && !product.locations.includes(locId)) product.locations.push(locId);
            if (loc && !loc.products.find(p => p.id === productId)) loc.products.push({ id: productId, name: product.name });
            renderMap();
            renderProducts();
            renderLocationList();
        } else {
            alert('❌ 指派失敗：' + (data.msg || '未知錯誤'));
        }
    })
    .catch(err => alert('❌ 連線錯誤：' + err));
}

function unassignProduct(productId, locId) {
    fetch('api_storage_layout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'unassign_product', product_id: productId, location_id: locId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            const product = products.find(p => p.id === productId);
            const loc = locations.find(l => l.id === locId);
            if (product) product.locations = product.locations.filter(l => l !== locId);
            if (loc) loc.products = loc.products.filter(p => p.id !== productId);
            renderMap();
            renderProducts();
            renderLocationList();
        } else {
            alert('❌ 移除失敗：' + (data.msg || '未知錯誤'));
        }
    })
    .catch(err => alert('❌ 連線錯誤：' + err));
}

function renderLocationList() {
    const tbody = document.getElementById('location-list-body');
    tbody.innerHTML = '';

    if (locations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="padding:40px; color:#999;">目前還沒有任何存放位置，請先按上方「編輯平面圖」新增貨架方塊。</td></tr>';
        return;
    }

    locations.forEach(loc => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="color:#888;">${escapeHtml(loc.id)}</td>
            <td>
                <span class="loc-name-display" id="loc-name-${loc.id}" style="font-weight:bold; font-size:22px; color:#0056B3;">${escapeHtml(loc.name)}</span>
                <input type="text" class="loc-name-input" id="loc-input-${loc.id}" value="${escapeHtml(loc.name)}" style="display:none; padding:8px; font-size:20px; border:2px solid #0056B3; border-radius:6px; width:160px;">
            </td>
            <td>${loc.floor} 樓</td>
            <td>${loc.products.length} 項</td>
            <td>
                <button class="btn-edit" id="loc-editbtn-${loc.id}" onclick="startRenameLocation('${loc.id}')">改名</button>
                <button class="btn-edit" id="loc-savebtn-${loc.id}" style="display:none; background:#28A745;" onclick="saveRenameLocation('${loc.id}')">儲存</button>
                <button class="btn-del" onclick="deleteLocation('${loc.id}')">刪除</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function startRenameLocation(locId) {
    document.getElementById(`loc-name-${locId}`).style.display = 'none';
    document.getElementById(`loc-input-${locId}`).style.display = 'inline-block';
    document.getElementById(`loc-editbtn-${locId}`).style.display = 'none';
    document.getElementById(`loc-savebtn-${locId}`).style.display = 'inline-block';
    document.getElementById(`loc-input-${locId}`).focus();
}

function saveRenameLocation(locId) {
    const newName = document.getElementById(`loc-input-${locId}`).value.trim();
    if (newName === '') { alert('名稱不可為空'); return; }

    fetch('api_storage_layout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'rename_location', location_id: locId, name: newName })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            const loc = locations.find(l => l.id === locId);
            if (loc) loc.name = newName;
            renderLocationList();
            renderMap();
            renderProducts();
        } else {
            alert('❌ 改名失敗：' + (data.msg || '未知錯誤'));
        }
    })
    .catch(err => alert('❌ 連線錯誤：' + err));
}

function deleteLocation(locId) {
    const loc = locations.find(l => l.id === locId);
    const productCount = loc ? loc.products.length : 0;
    let confirmMsg = `確定要刪除「${loc ? loc.name : locId}」這個存放位置嗎？`;
    if (productCount > 0) {
        confirmMsg += `\n⚠️ 目前有 ${productCount} 項商品指派在這裡，刪除後這些商品會變成「尚未指派位置」。`;
    }
    if (!confirm(confirmMsg)) return;

    fetch('api_storage_layout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_location', location_id: locId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            locations = locations.filter(l => l.id !== locId);
            products.forEach(p => { p.locations = p.locations.filter(l => l !== locId); });
            renderLocationList();
            renderMap();
            renderProducts();
        } else {
            alert('❌ 刪除失敗：' + (data.msg || '未知錯誤'));
        }
    })
    .catch(err => alert('❌ 連線錯誤：' + err));
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.innerText = str;
    return div.innerHTML;
}

renderMap();
renderProducts();
renderLocationList();
</script>

</body>
</html>
