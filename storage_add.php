<?php
include 'db_config.php';

// 撈取目前所有存放位置（含座標）
$locations = [];
$res = $conn->query("SELECT 位置編號, 區域名稱, 樓層, pos_x, pos_y, width, height, shape_color FROM 存放位置");
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
$locations_json = json_encode($locations, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>店內平面圖設計 - 同德藥行</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        .layout-toolbar {
            max-width: 1200px; margin: 20px auto 0; background: white; padding: 20px 30px;
            border-radius: 15px 15px 0 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex; gap: 15px; align-items: center; flex-wrap: wrap;
        }
        .btn-new-shelf { background: #E67E22; color: white; border: none; padding: 14px 26px; font-size: 22px; font-weight: bold; border-radius: 10px; cursor: pointer; }
        .btn-new-shelf:active { transform: scale(0.97); }
        .btn-save-layout { background: #28A745; color: white; border: none; padding: 14px 26px; font-size: 22px; font-weight: bold; border-radius: 10px; cursor: pointer; }
        .btn-save-layout:active { transform: scale(0.97); }
        .hint-text { color: #888; font-size: 18px; margin-left: auto; }

        .layout-canvas-wrap {
            max-width: 1200px; margin: 0 auto 40px; background: white; padding: 0;
            border-radius: 0 0 15px 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); overflow: hidden;
        }
        .layout-canvas {
            position: relative; width: 100%; height: 650px; background-image:
                linear-gradient(to right, #F0F0F0 1px, transparent 1px),
                linear-gradient(to bottom, #F0F0F0 1px, transparent 1px);
            background-size: 20px 20px; overflow: auto; touch-action: none;
        }

        .shelf-box {
            position: absolute; border-radius: 10px; border: 3px solid rgba(0,0,0,0.15);
            box-shadow: 0 3px 6px rgba(0,0,0,0.15); cursor: grab; user-select: none;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 6px; box-sizing: border-box; touch-action: none;
        }
        .shelf-box:active { cursor: grabbing; }
        .shelf-box.selected { outline: 4px solid #0056B3; z-index: 5; }
        .shelf-name { font-size: 18px; font-weight: bold; color: #333; text-align: center; word-break: break-all; pointer-events: none; }
        .shelf-floor-tag { font-size: 13px; color: #555; background: rgba(255,255,255,0.7); padding: 1px 6px; border-radius: 6px; margin-top: 4px; pointer-events: none; }

        .resize-handle {
            position: absolute; width: 22px; height: 22px; background: #0056B3; border: 2px solid white;
            border-radius: 50%; right: -11px; bottom: -11px; cursor: nwse-resize; touch-action: none;
        }
        .delete-handle {
            position: absolute; width: 26px; height: 26px; background: #DC3545; color: white; border: 2px solid white;
            border-radius: 50%; left: -13px; top: -13px; cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 16px; font-weight: bold; touch-action: none;
        }

        .edit-panel {
            position: fixed; right: 30px; top: 50%; transform: translateY(-50%);
            background: white; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            padding: 25px; width: 280px; display: none; z-index: 50;
        }
        .edit-panel.show { display: block; }
        .edit-panel label { display: block; font-weight: bold; color: #0056B3; font-size: 18px; margin-bottom: 6px; margin-top: 14px; }
        .edit-panel input { width: 100%; padding: 8px; font-size: 18px; border: 2px solid #CCC; border-radius: 6px; box-sizing: border-box; }
        .color-options { display: flex; gap: 8px; margin-top: 6px; flex-wrap: wrap; }
        .color-swatch { width: 34px; height: 34px; border-radius: 50%; cursor: pointer; border: 3px solid transparent; }
        .color-swatch.active { border-color: #333; }
    </style>
</head>
<body>

<div class="layout-toolbar">
    <a href="index.html" class="btn-back">返回首頁</a>
    <button class="btn-new-shelf" onclick="addShelf()">➕ 新增貨架方塊</button>
    <button class="btn-save-layout" onclick="saveLayout()">💾 儲存平面圖</button>
    <span class="hint-text">拖曳方塊移動位置，拖右下角圓點調整大小，點方塊可編輯名稱</span>
</div>

<div class="layout-canvas-wrap">
    <div class="layout-canvas" id="canvas"></div>
</div>

<div class="edit-panel" id="edit-panel">
    <label>區域名稱（例如：A櫃-第1抽屜）</label>
    <input type="text" id="panel-name" oninput="updateSelectedField('name', this.value)">
    <label>樓層</label>
    <input type="number" id="panel-floor" oninput="updateSelectedField('floor', this.value)">
    <label>顏色</label>
    <div class="color-options" id="color-options"></div>
    <div style="margin-top:20px; text-align:center;">
        <button class="btn-cancel" style="width:100%; padding:10px; font-size:18px; border-radius:8px; border:2px solid #999; background:white; cursor:pointer;" onclick="deselect()">完成編輯</button>
    </div>
</div>

<script>
let locations = <?php echo $locations_json ?: '[]'; ?>;
let nextTempId = 1;
let selectedId = null;
let dragState = null;
const canvas = document.getElementById('canvas');
const colorPalette = ['#90CAF9', '#A5D6A7', '#FFCC80', '#CE93D8', '#FFAB91', '#80CBC4'];

function render() {
    canvas.querySelectorAll('.shelf-box').forEach(el => el.remove());
    locations.forEach(loc => {
        const box = document.createElement('div');
        box.className = 'shelf-box' + (loc.id === selectedId ? ' selected' : '');
        box.style.left = loc.x + 'px';
        box.style.top = loc.y + 'px';
        box.style.width = loc.w + 'px';
        box.style.height = loc.h + 'px';
        box.style.background = loc.color;
        box.dataset.id = loc.id;

        const nameDiv = document.createElement('div');
        nameDiv.className = 'shelf-name';
        nameDiv.innerText = loc.name || '(未命名)';
        box.appendChild(nameDiv);

        const floorTag = document.createElement('div');
        floorTag.className = 'shelf-floor-tag';
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
        box.onclick = (e) => { e.stopPropagation(); selectShelf(loc.id); };

        canvas.appendChild(box);
    });
}

function addShelf() {
    const id = 'NEW_' + (nextTempId++);
    locations.push({ id, name: '新貨架', floor: 1, x: 30, y: 30, w: 110, h: 90, color: colorPalette[0] });
    selectedId = id;
    render();
    openPanel(id);
}

function removeShelf(id) {
    if (!confirm('確定要刪除這個貨架方塊嗎？\n（儲存後，原本指派到此位置的商品也會解除指派）')) return;
    locations = locations.filter(l => l.id !== id);
    if (selectedId === id) deselect();
    render();
}

function selectShelf(id) {
    selectedId = id;
    render();
    openPanel(id);
}

function deselect() {
    selectedId = null;
    document.getElementById('edit-panel').classList.remove('show');
    render();
}

function openPanel(id) {
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
        sw.onclick = () => { updateSelectedField('color', c); openPanel(id); };
        colorWrap.appendChild(sw);
    });
    document.getElementById('edit-panel').classList.add('show');
}

function updateSelectedField(field, value) {
    const loc = locations.find(l => l.id === selectedId);
    if (!loc) return;
    loc[field] = (field === 'floor') ? (parseInt(value) || 1) : value;
    render();
}

function startDrag(e, id) {
    e.preventDefault();
    e.stopPropagation();
    const loc = locations.find(l => l.id === id);
    const canvasRect = canvas.getBoundingClientRect();
    dragState = {
        type: 'move', id,
        startX: e.clientX, startY: e.clientY,
        origX: loc.x, origY: loc.y
    };
    selectedId = id;
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
    selectedId = id;
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
    render();
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
            window.location.href = 'storage_add.php';
        } else {
            alert('❌ 儲存失敗：' + (data.msg || '未知錯誤'));
        }
    })
    .catch(err => alert('❌ 連線錯誤：' + err));
}

canvas.onclick = () => deselect();
render();
</script>

</body>
</html>
