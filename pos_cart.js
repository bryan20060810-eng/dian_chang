// 注意：allProducts 變數會由 HTML 檔案中的 PHP 傳遞過來
let cart = []; 
let currentSelectedProduct = null; 

const tcmUnits = {
    '斤': 160,
    '兩': 10,
    '錢': 1,
    '分': 0.1,
    '釐': 0.01
};

const normalUnits = {
    '個': 1, '盒': 1, '瓶': 1, '件': 1
};

function renderProducts(category) {
    const grid = document.getElementById('product-grid');
    grid.innerHTML = ''; 
    
    allProducts.forEach(p => {
        if(category === '全部' || p.category === category) {
            let baseUnitTag = (p.category === '藥材' || p.category === '配方') ? ' /錢' : ' /個';
            const card = document.createElement('div');
            card.className = 'product-card';
            card.onclick = () => openModal(p);
            card.innerHTML = `
                <div class="p-name">${p.name}</div>
                <div class="p-price">$${p.price.toLocaleString('zh-TW')} <span class="p-unit-tag">${baseUnitTag}</span></div>
            `;
            grid.appendChild(card);
        }
    });
}

function filterCategory(category) {
    const tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(tab => {
        tab.classList.remove('active');
        if(tab.innerText === category || (category === '全部' && tab.innerText === '全部商品')) {
            tab.classList.add('active');
        }
    });
    renderProducts(category);
}

function openModal(product) {
    currentSelectedProduct = product;
    document.getElementById('modal-p-name').innerText = product.name;
    document.getElementById('modal-p-price').innerText = `基準單價：$${product.price} ${(product.category === '藥材' || product.category === '配方') ? '每錢' : '每個'}`;
    document.getElementById('modal-qty').innerText = '1';
    
    const unitSelect = document.getElementById('modal-unit');
    unitSelect.innerHTML = '';
    
    let targetUnits = (product.category === '藥材' || product.category === '配方') ? tcmUnits : normalUnits;
    
    for (let unitName in targetUnits) {
        let option = document.createElement('option');
        option.value = unitName;
        option.innerText = `單位：${unitName}`;
        if(unitName === '錢' || unitName === '個') option.selected = true;
        unitSelect.appendChild(option);
    }

    calculateLiveSubtotal(); 
    document.getElementById('qty-modal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('qty-modal').style.display = 'none';
    currentSelectedProduct = null;
}

function changeQty(amount) {
    let qtyDisplay = document.getElementById('modal-qty');
    let currentQty = parseInt(qtyDisplay.innerText);
    let newQty = currentQty + amount;
    if (newQty >= 1) { 
        qtyDisplay.innerText = newQty;
        calculateLiveSubtotal(); 
    }
}

function calculateLiveSubtotal() {
    if(!currentSelectedProduct) return;
    
    let qty = parseInt(document.getElementById('modal-qty').innerText);
    let selectedUnit = document.getElementById('modal-unit').value;
    let isTcm = (currentSelectedProduct.category === '藥材' || currentSelectedProduct.category === '配方');
    let multiplier = isTcm ? tcmUnits[selectedUnit] : normalUnits[selectedUnit];
    let subtotal = Math.round((currentSelectedProduct.price * multiplier) * qty);
    
    document.getElementById('live-subtotal').innerText = `小計金額：$${subtotal.toLocaleString('zh-TW')}`;
}

function confirmAddToCart() {
    let qty = parseInt(document.getElementById('modal-qty').innerText);
    let selectedUnit = document.getElementById('modal-unit').value;
    let isTcm = (currentSelectedProduct.category === '藥材' || currentSelectedProduct.category === '配方');
    let multiplier = isTcm ? tcmUnits[selectedUnit] : normalUnits[selectedUnit];
    
    let baseQty = qty * multiplier;
    let subtotal = Math.round(currentSelectedProduct.price * baseQty);
    let cartItemId = currentSelectedProduct.id + '_' + selectedUnit;

    let existingItem = cart.find(item => item.cartItemId === cartItemId);
    if (existingItem) {
        existingItem.displayQty += qty;
        existingItem.baseQty += baseQty;
        existingItem.subtotal += subtotal;
    } else {
        cart.push({
            cartItemId: cartItemId,
            id: currentSelectedProduct.id,
            name: currentSelectedProduct.name,
            price: currentSelectedProduct.price, 
            unit: selectedUnit,
            displayQty: qty,    
            baseQty: baseQty,   
            subtotal: subtotal  
        });
    }
    
    closeModal();
    updateCartUI();
}

function clearCart() {
    if(cart.length > 0) {
        if(confirm('確定要清空目前購物車的所有商品嗎？')) {
            cart = [];
            updateCartUI();
        }
    }
}

function updateCartUI() {
    const cartList = document.getElementById('cart-list');
    const hiddenInputs = document.getElementById('hidden-inputs');
    cartList.innerHTML = '';
    hiddenInputs.innerHTML = '';
    let total = 0;

    if(cart.length === 0) {
        cartList.innerHTML = '<div style="text-align:center; color:#999; margin-top:50px; font-size:24px;">購物車目前沒有商品<br>請從左側點擊加入</div>';
        document.getElementById('cart-total').innerText = '0';
        return;
    }

    cart.forEach((item, index) => {
        total += item.subtotal;
        cartList.innerHTML += `
            <div class="cart-item">
                <div class="item-info">
                    <span class="item-name">${item.name} <span style="color:#333;">x ${item.displayQty} ${item.unit}</span></span>
                    <span class="item-sub">$${item.subtotal.toLocaleString('zh-TW')}</span>
                </div>
                <button type="button" class="btn-del" onclick="removeFromCart(${index})">刪除</button>
            </div>
        `;
        hiddenInputs.innerHTML += `
            <input type="hidden" name="product_id[]" value="${item.id}">
            <input type="hidden" name="quantity[]" value="${item.baseQty}">
        `;
    });

    document.getElementById('cart-total').innerText = total.toLocaleString('zh-TW');
}

function removeFromCart(index) {
    cart.splice(index, 1); 
    updateCartUI();
}

function submitOrder() {
    if(cart.length === 0) {
        alert("購物車是空的，請先加入商品");
        return;
    }
    const nameInput = document.querySelector('input[name="customer_name"]').value;
    if(nameInput.trim() === '') {
        alert("請輸入顧客姓名");
        return;
    }

    const form = document.getElementById('order-form');
    const formData = new FormData(form);
    const submitBtn = document.querySelector('.btn-submit');
    submitBtn.disabled = true;
    submitBtn.innerText = '處理中...';

    fetch('order_save.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'fetch' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerText = '確認送出訂單';

        if (data.status === 'success') {
            // 先把目前購物車明細記下來（清空前），等下要顯示在彈窗的金額明細裡
            const orderItemsSnapshot = cart.map(item => ({
                name: item.name,
                displayQty: item.displayQty,
                unit: item.unit,
                subtotal: item.subtotal
            }));
            const orderTotalSnapshot = cart.reduce((sum, item) => sum + item.subtotal, 0);

            // 結帳成立！彈出平面圖，把這張訂單買到的商品所在位置亮起來，並附上金額明細
            if (typeof showOrderLocationsModal === 'function') {
                showOrderLocationsModal(data.order_id, data.customer_name, orderItemsSnapshot, orderTotalSnapshot);
            } else {
                alert(`訂單開立成功！編號：${data.order_id}`);
                window.location.href = 'index.html';
            }
            // 清空購物車與顧客資訊，準備接下一筆
            cart = [];
            updateCartUI();
            form.reset();
            document.querySelector('input[name="order_date"]').value = new Date().toISOString().slice(0, 10);
        } else {
            alert('❌ 下單失敗：' + (data.msg || '未知錯誤'));
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerText = '確認送出訂單';
        alert('❌ 連線錯誤，請稍後再試：' + err);
    });
}

window.onload = () => filterCategory('全部');