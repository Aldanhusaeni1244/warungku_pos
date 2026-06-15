// =====================================================
// WARUNGKU POS - FINAL VERSION (LENGKAP + CRUD KATEGORI & PRODUK + TRANSAKSI + CUSTOMER + USER + REPORT)
// =====================================================

const API_URL = 'api.php';

let currentUser = null;
let currentPage = '';
let cart = [];
let productsData = [];
let currentDiscount = null;
let pendingDelete = null;

// ========== VARIABEL UNTUK TRANSAKSI ==========
let currentTxPage = 1;
let totalTxPages = 1;

// ========== HELPER ==========
async function fetchAPI(action, method = 'GET', data = null) {
    let url = `${API_URL}?action=${action}`;
    const options = { method: method, headers: { 'Content-Type': 'application/json' } };
    if (method === 'POST' && data) options.body = JSON.stringify(data);
    else if (method === 'GET' && data) {
        const params = new URLSearchParams(data);
        url += `&${params.toString()}`;
    }
    const response = await fetch(url, options);
    return await response.json();
}

const rupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
const today = () => new Date().toISOString().split('T')[0];
const timeNow = () => {
    const d = new Date();
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
};

function showToast(msg, type = 'success') {
    const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info', warning: 'fa-triangle-exclamation' };
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<i class="fa-solid ${icons[type] || icons.info}"></i><span>${msg}</span>`;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

function showPage(page) {
    ['landing', 'login', 'app'].forEach(p => {
        const el = document.getElementById(`page-${p}`);
        if (el) el.classList.toggle('hidden', p !== page);
    });
}

function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
function el(id) { return document.getElementById(id); }

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// ========== AUTH ==========
async function handleLogin() {
    const username = el('login-username').value.trim();
    const password = el('login-password').value.trim();
    if (!username || !password) {
        showToast('Isi username dan password', 'error');
        return;
    }
    const result = await fetchAPI('login', 'POST', { username, password });
    if (result.status === 'success') {
        currentUser = result.data;
        localStorage.setItem('session', JSON.stringify(currentUser));
        initApp();
        showPage('app');
        navigateTo('dashboard');
        showToast(`Selamat datang, ${currentUser.name}! 👋`);
    } else {
        showToast(result.message || 'Username atau password salah', 'error');
    }
}

function handleLogout() {
    currentUser = null;
    localStorage.removeItem('session');
    cart = [];
    showPage('login');
    el('login-username').value = '';
    el('login-password').value = '';
}

function restoreSession() {
    const session = localStorage.getItem('session');
    if (session) {
        currentUser = JSON.parse(session);
        return true;
    }
    return false;
}

function initApp() {
    el('sidebar-avatar').textContent = currentUser.name.charAt(0).toUpperCase();
    el('sidebar-name').textContent = currentUser.name;
    el('sidebar-role').textContent = currentUser.role === 'admin' ? 'Administrator' : 'Kasir';
    el('navbar-role-badge').textContent = currentUser.role === 'admin' ? 'Admin' : 'Kasir';
    el('navbar-role-badge').className = 'badge ' + (currentUser.role === 'admin' ? 'badge-yellow' : 'badge-green');
    document.querySelectorAll('.admin-only').forEach(x => {
        x.classList.toggle('hidden', currentUser.role !== 'admin');
    });
}

// ========== RENDER FUNCTIONS ==========
async function renderDashboard() {
    const result = await fetchAPI('get_stats');
    if (result.status === 'success') {
        const stats = result.data;
        el('stats-grid').innerHTML = `
            <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-money-bill-wave"></i></div><div class="stat-info"><div class="stat-label">Pendapatan Hari Ini</div><div class="stat-value">${rupiah(stats.today_revenue)}</div></div></div>
            <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-boxes-stacked"></i></div><div class="stat-info"><div class="stat-label">Total Produk</div><div class="stat-value">${stats.total_products}</div></div></div>
            <div class="stat-card"><div class="stat-icon red"><i class="fa-solid fa-users"></i></div><div class="stat-info"><div class="stat-label">Total Pelanggan</div><div class="stat-value">${stats.total_customers}</div></div></div>
        `;
        el('dash-recent-tbody').innerHTML = stats.recent_transactions.map(t => `
            <tr><td class="font-mono">${t.invoiceNo}</td><td>${t.cashierName}</td><td>${t.customerId ? 'Member' : 'Umum'}</td><td class="font-mono">${rupiah(t.total)}</td><td><span class="badge badge-green">Lunas</span></td>
        `).join('');
        el('dash-stock-tbody').innerHTML = stats.low_stock.map(p => `
            <tr><td><strong>${p.name}</strong></td><td><span class="badge badge-yellow">${p.stock} pcs</span></td>
        `).join('');
    }
}

async function renderProducts() {
    const result = await fetchAPI('get_products');
    if (result.status === 'success') {
        const products = result.data;
        el('products-tbody').innerHTML = products.map(p => `
            <tr>
                <td><strong>${p.name}</strong><br><span class="text-xs">${p.unit || 'pcs'}</span></td>
                <td>${p.categoryName || '-'}</td>
                <td>${p.barcode || '-'}</td>
                <td class="font-mono">${rupiah(p.price)}</td>
                <td class="font-mono">${rupiah(p.cost)}</td>
                <td><span class="badge ${p.stock === 0 ? 'badge-red' : 'badge-green'}">${p.stock}</span></td>
                <td>${p.stock > 0 ? 'Tersedia' : 'Habis'}</td>
                <td>
                    <button class="btn btn-sm btn-secondary" onclick="openProductModal(${p.id})"><i class="fa-solid fa-pen"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="confirmDelete('product', ${p.id}, '${escapeHtml(p.name)}')"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>
        `).join('');
    }
}

async function renderCategories() {
    const result = await fetchAPI('get_categories');
    if (result.status === 'success') {
        const categories = result.data;
        let html = '';
        categories.forEach((cat, index) => {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td><strong>${escapeHtml(cat.name)}</strong></td>
                    <td style="color:var(--text2)">${escapeHtml(cat.desc) || '-'}</td>
                    <td><span class="badge badge-blue">${cat.product_count || 0} produk</span></td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <button class="btn btn-sm btn-secondary" onclick="openCategoryModal(${cat.id})"><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="confirmDelete('category', ${cat.id}, '${escapeHtml(cat.name)}')"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `;
        });
        document.getElementById('categories-tbody').innerHTML = html;
    } else {
        showToast('Gagal memuat kategori', 'error');
    }
}

async function renderPosProducts() {
    const result = await fetchAPI('get_products');
    if (result.status === 'success') {
        productsData = result.data;
        el('pos-product-grid').innerHTML = productsData.map(p => `
            <div class="product-card" onclick="addToCart(${p.id})">
                <div class="pc-name">${p.name}</div>
                <div class="pc-price">${rupiah(p.price)}</div>
                <div class="pc-stock">Stok: ${p.stock}</div>
            </div>
        `).join('');
    }
}

// ========== TAMBAHAN: LOAD CUSTOMERS UNTUK DROPDOWN DI POS ==========
async function loadCustomersForPos() {
    try {
        const res = await fetchAPI('get_customers');
        if (res.status === 'success') {
            const select = document.getElementById('pos-customer');
            if (select) {
                let options = '<option value="">Umum (Tanpa Member)</option>';
                res.data.forEach(cust => {
                    options += `<option value="${cust.id}">${escapeHtml(cust.name)}</option>`;
                });
                select.innerHTML = options;
            }
        }
    } catch(e) {
        console.error('Gagal memuat pelanggan untuk POS', e);
    }
}

// ========== CRUD PRODUK ==========
function openProductModal(id = null) {
    const modalTitle = document.getElementById('modal-product-title');
    const formId = document.getElementById('product-id');
    const nameInput = document.getElementById('product-name');
    const categorySelect = document.getElementById('product-category');
    const barcodeInput = document.getElementById('product-barcode');
    const costInput = document.getElementById('product-cost');
    const priceInput = document.getElementById('product-price');
    const stockInput = document.getElementById('product-stock');
    const minStockInput = document.getElementById('product-min-stock');
    const unitInput = document.getElementById('product-unit');

    fetchAPI('get_categories').then(res => {
        if (res.status === 'success') {
            let options = '<option value="">Pilih Kategori</option>';
            res.data.forEach(cat => {
                options += `<option value="${cat.id}">${escapeHtml(cat.name)}</option>`;
            });
            categorySelect.innerHTML = options;
        }
    });

    if (id) {
        fetchAPI('get_products').then(res => {
            if (res.status === 'success') {
                const product = res.data.find(p => p.id == id);
                if (product) {
                    modalTitle.innerText = 'Edit Produk';
                    formId.value = product.id;
                    nameInput.value = product.name;
                    categorySelect.value = product.categoryId;
                    barcodeInput.value = product.barcode || '';
                    costInput.value = product.cost;
                    priceInput.value = product.price;
                    stockInput.value = product.stock;
                    minStockInput.value = product.minStock;
                    unitInput.value = product.unit || 'pcs';
                }
            }
        });
    } else {
        modalTitle.innerText = 'Tambah Produk';
        formId.value = '';
        nameInput.value = '';
        categorySelect.value = '';
        barcodeInput.value = '';
        costInput.value = '';
        priceInput.value = '';
        stockInput.value = '';
        minStockInput.value = 5;
        unitInput.value = 'pcs';
    }
    openModal('modal-product');
}

async function saveProduct() {
    const id = document.getElementById('product-id').value;
    const name = document.getElementById('product-name').value.trim();
    const categoryId = parseInt(document.getElementById('product-category').value);
    const barcode = document.getElementById('product-barcode').value.trim();
    const cost = parseFloat(document.getElementById('product-cost').value);
    const price = parseFloat(document.getElementById('product-price').value);
    const stock = parseInt(document.getElementById('product-stock').value);
    const minStock = parseInt(document.getElementById('product-min-stock').value);
    const unit = document.getElementById('product-unit').value.trim();

    if (!name || !categoryId || isNaN(cost) || isNaN(price) || isNaN(stock)) {
        showToast('Lengkapi semua field wajib (Nama, Kategori, Harga Beli, Harga Jual, Stok)', 'error');
        return;
    }
    if (price < cost) {
        showToast('Harga jual tidak boleh lebih kecil dari harga beli', 'warning');
        return;
    }

    const payload = { name, categoryId, barcode, cost, price, stock, minStock, unit };
    if (id) payload.id = id;

    const result = await fetchAPI('save_product', 'POST', payload);
    if (result.status === 'success') {
        showToast(result.message || 'Produk berhasil disimpan', 'success');
        closeModal('modal-product');
        renderProducts();
        renderPosProducts();
    } else {
        showToast(result.message || 'Gagal menyimpan produk', 'error');
    }
}

// ========== CART FUNCTIONS ==========
function renderCart() {
    const container = document.getElementById('pos-cart-items');
    if (!cart.length) {
        container.innerHTML = `<div class="cart-empty"><i class="fa-solid fa-cart-shopping"></i><p>Keranjang masih kosong</p></div>`;
    } else {
        container.innerHTML = cart.map(item => `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price">${rupiah(item.subtotal)}</div>
                </div>
                <div class="cart-qty-ctrl">
                    <button class="qty-btn remove" onclick="changeQty(${item.productId}, -1)"><i class="fa-solid fa-minus"></i></button>
                    <span class="qty-val">${item.qty}</span>
                    <button class="qty-btn" onclick="changeQty(${item.productId}, 1)"><i class="fa-solid fa-plus"></i></button>
                    <button class="qty-btn remove" onclick="removeFromCart(${item.productId})"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
        `).join('');
    }
    refreshDiscount();
}

async function refreshDiscount() {
    const subtotal = cart.reduce((s, i) => s + i.subtotal, 0);
    if (subtotal === 0) {
        document.getElementById('cart-subtotal').innerText = rupiah(0);
        const discountSpan = document.getElementById('cart-discount-amount');
        if (discountSpan) discountSpan.innerText = rupiah(0);
        document.getElementById('cart-total').innerText = rupiah(0);
        currentDiscount = null;
        calcChange();
        return;
    }
    const response = await fetch(`${API_URL}?action=calculate_discount&subtotal=${subtotal}`);
    const result = await response.json();
    if (result.status === 'success') {
        const d = result.data;
        currentDiscount = d;
        document.getElementById('cart-subtotal').innerText = rupiah(subtotal);
        let discountDisplay = document.getElementById('cart-discount-amount');
        if (!discountDisplay) {
            const discountRow = document.querySelector('.cart-row.discount');
            if (discountRow) {
                const span = document.createElement('span');
                span.id = 'cart-discount-amount';
                discountRow.appendChild(span);
                discountDisplay = span;
            }
        }
        if (discountDisplay) discountDisplay.innerText = rupiah(d.discount_amount);
        document.getElementById('cart-total').innerText = rupiah(d.total);
        calcChange();
    }
}

function calcChange() {
    const totalText = document.getElementById('cart-total').innerText.replace(/[^0-9]/g, '');
    const total = parseInt(totalText) || 0;
    const paid = parseFloat(document.getElementById('cart-pay')?.value) || 0;
    const change = paid - total;
    const changeEl = document.getElementById('cart-change');
    if (changeEl) {
        changeEl.innerText = rupiah(Math.max(0, change));
        changeEl.style.color = change < 0 ? 'var(--danger)' : 'var(--primary-dark)';
    }
}

function addToCart(productId) {
    const product = productsData.find(p => p.id == productId);
    if (!product) {
        showToast('Produk tidak ditemukan', 'error');
        return;
    }
    if (product.stock <= 0) {
        showToast('Stok habis!', 'error');
        return;
    }
    const existing = cart.find(item => item.productId == productId);
    if (existing) {
        if (existing.qty >= product.stock) {
            showToast('Stok tidak mencukupi', 'warning');
            return;
        }
        existing.qty++;
        existing.subtotal = existing.qty * existing.price;
    } else {
        cart.push({
            productId: product.id,
            name: product.name,
            price: product.price,
            qty: 1,
            subtotal: product.price,
            unit: product.unit || 'pcs'
        });
    }
    renderCart();
    showToast('Produk ditambahkan ke keranjang', 'success');
}

function changeQty(productId, delta) {
    const item = cart.find(i => i.productId == productId);
    if (!item) return;
    const newQty = item.qty + delta;
    if (newQty <= 0) {
        cart = cart.filter(i => i.productId != productId);
    } else {
        const product = productsData.find(p => p.id == productId);
        if (product && newQty > product.stock) {
            showToast('Stok tidak mencukupi', 'warning');
            return;
        }
        item.qty = newQty;
        item.subtotal = item.qty * item.price;
    }
    renderCart();
}

function removeFromCart(productId) {
    cart = cart.filter(i => i.productId != productId);
    renderCart();
    showToast('Item dihapus dari keranjang', 'info');
}

function clearCart() {
    cart = [];
    renderCart();
    showToast('Keranjang dikosongkan', 'info');
}

async function processTransaction() {
    if (!cart.length) {
        showToast('Keranjang masih kosong', 'error');
        return;
    }
    await refreshDiscount();
    const subtotal = cart.reduce((s, i) => s + i.subtotal, 0);
    const discountAmount = currentDiscount ? currentDiscount.discount_amount : 0;
    const total = currentDiscount ? currentDiscount.total : subtotal;
    const paid = parseFloat(document.getElementById('cart-pay')?.value) || 0;
    if (paid < total) {
        showToast('Jumlah bayar kurang', 'error');
        return;
    }
    const change = paid - total;
    const invoiceNo = `INV-${Date.now()}`;
    const cashierId = currentUser.id;
    const customerSelect = document.getElementById('pos-customer');
    const customerId = customerSelect && customerSelect.value ? parseInt(customerSelect.value) : null;

    const transactionData = {
        invoiceNo, date: today(), time: timeNow(), cashierId, customerId,
        subtotal, discount_type: currentDiscount ? currentDiscount.discount_type : 'flat',
        discount_value: currentDiscount ? currentDiscount.discount_value : 0,
        discount: discountAmount, total, paid, change,
        items: cart.map(item => ({
            productId: item.productId, name: item.name, qty: item.qty,
            price: item.price, subtotal: item.subtotal
        }))
    };
    const result = await fetchAPI('save_transaction', 'POST', transactionData);
    if (result.status === 'success') {
        showToast(`Transaksi berhasil! ${invoiceNo}`, 'success');
        cart = [];
        renderCart();
        document.getElementById('cart-pay').value = '';
        await renderPosProducts();
        if (currentPage === 'dashboard') renderDashboard();
    } else {
        showToast(result.message || 'Gagal menyimpan transaksi', 'error');
    }
}

// ========== CRUD KATEGORI ==========
function openCategoryModal(id = null) {
    const modal = document.getElementById('modal-category');
    const title = document.getElementById('modal-cat-title');
    const formId = document.getElementById('category-id');
    const nameInput = document.getElementById('category-name');
    const descInput = document.getElementById('category-desc');

    if (id) {
        fetchAPI('get_categories').then(res => {
            if (res.status === 'success') {
                const cat = res.data.find(c => c.id == id);
                if (cat) {
                    title.innerText = 'Edit Kategori';
                    formId.value = cat.id;
                    nameInput.value = cat.name;
                    descInput.value = cat.desc || '';
                }
            }
        });
    } else {
        title.innerText = 'Tambah Kategori';
        formId.value = '';
        nameInput.value = '';
        descInput.value = '';
    }
    openModal('modal-category');
}

async function saveCategory() {
    const id = document.getElementById('category-id').value;
    const name = document.getElementById('category-name').value.trim();
    const desc = document.getElementById('category-desc').value.trim();

    if (!name) {
        showToast('Nama kategori wajib diisi', 'error');
        return;
    }

    const action = id ? 'update_category' : 'create_category';
    const payload = { name, desc };
    if (id) payload.id = id;

    const result = await fetchAPI(action, 'POST', payload);
    if (result.status === 'success') {
        showToast(result.message, 'success');
        closeModal('modal-category');
        renderCategories();
    } else {
        showToast(result.message || 'Gagal menyimpan kategori', 'error');
    }
}

// ========== DELETE CONFIRMATION ==========
function confirmDelete(type, id, name) {
    pendingDelete = { type, id, name };
    document.getElementById('confirm-message').innerHTML = `Yakin ingin menghapus "${name}"? Data yang dihapus tidak dapat dikembalikan.`;
    openModal('modal-confirm');
}

async function executeDelete() {
    if (!pendingDelete) return;
    const { type, id, name } = pendingDelete;
    let action = '';
    let message = '';

    if (type === 'category') {
        action = 'delete_category';
        message = 'Kategori berhasil dihapus';
    } else if (type === 'product') {
        action = 'delete_product';
        message = 'Produk berhasil dihapus';
    } else if (type === 'customer') {
        action = 'delete_customer';
        message = 'Pelanggan berhasil dihapus';
    } else if (type === 'user') {
        action = 'delete_user';
        message = 'Pengguna berhasil dihapus';
    } else {
        closeModal('modal-confirm');
        pendingDelete = null;
        return;
    }

    const result = await fetchAPI(action, 'GET', { id });
    if (result.status === 'success') {
        showToast(message, 'success');
        if (type === 'category') renderCategories();
        if (type === 'product') renderProducts();
        if (type === 'customer') renderCustomers();
        if (type === 'user') renderUsers();
    } else {
        showToast(result.message || 'Gagal menghapus', 'error');
    }
    closeModal('modal-confirm');
    pendingDelete = null;
}

// ========== FUNGSI UNTUK TRANSAKSI ==========
async function renderTransactions() {
    const search = document.getElementById('tx-search')?.value || '';
    const dateFrom = document.getElementById('tx-date-from')?.value || '';
    const dateTo = document.getElementById('tx-date-to')?.value || '';
    
    try {
        const res = await fetch(`${API_URL}?action=get_transactions&search=${encodeURIComponent(search)}&start_date=${dateFrom}&end_date=${dateTo}&page=${currentTxPage}`);
        const result = await res.json();
        if (result.status !== 'success') throw new Error(result.message);
        
        const tbody = document.getElementById('transactions-tbody');
        tbody.innerHTML = '';
        result.data.forEach(tx => {
            const row = `
                <tr>
                    <td class="p-2">${escapeHtml(tx.invoiceNo)}</td>
                    <td class="p-2">${escapeHtml(tx.date)} ${tx.time}</td>
                    <td class="p-2">${escapeHtml(tx.kasir)}</td>
                    <td class="p-2">${escapeHtml(tx.pelanggan)}</td>
                    <td class="p-2 text-center">${tx.items}</td>
                    <td class="p-2 text-right">${rupiah(tx.total)}</td>
                    <td class="p-2 text-right">${rupiah(tx.paid)}</td>
                    <td class="p-2 text-right">${rupiah(tx.change)}</td>
                    <td class="p-2 text-center"><button class="btn btn-sm btn-secondary" onclick="showTxDetail(${tx.id})"><i class="fa-solid fa-eye"></i> Detail</button></td>
                </tr>
            `;
            tbody.innerHTML += row;
        });
        
        totalTxPages = result.totalPages;
        currentTxPage = result.currentPage;
        renderTxPagination();
    } catch (err) {
        console.error(err);
        showToast('Gagal memuat transaksi', 'error');
    }
}

function renderTxPagination() {
    const container = document.querySelector('#view-transactions .card-body');
    if (!container) return;
    let existingPagination = document.getElementById('tx-pagination');
    if (existingPagination) existingPagination.remove();
    
    if (totalTxPages <= 1) return;
    const paginationDiv = document.createElement('div');
    paginationDiv.id = 'tx-pagination';
    paginationDiv.className = 'flex justify-between items-center mt-4 px-4 py-2';
    paginationDiv.innerHTML = `
        <span class="text-sm">Halaman ${currentTxPage} dari ${totalTxPages}</span>
        <div class="flex gap-2">
            ${currentTxPage > 1 ? `<button class="btn btn-sm btn-secondary" onclick="changeTxPage(${currentTxPage - 1})">Prev</button>` : ''}
            ${currentTxPage < totalTxPages ? `<button class="btn btn-sm btn-secondary" onclick="changeTxPage(${currentTxPage + 1})">Next</button>` : ''}
        </div>
    `;
    container.appendChild(paginationDiv);
}

function changeTxPage(page) {
    currentTxPage = page;
    renderTransactions();
}

async function showTxDetail(id) {
    try {
        const res = await fetch(`${API_URL}?action=get_transaction_detail&id=${id}`);
        const result = await res.json();
        if (result.status !== 'success') throw new Error();
        
        let itemsHtml = '';
        result.items.forEach(item => {
            itemsHtml += `
                <tr>
                    <td class="p-2">${escapeHtml(item.product_name || 'Produk #'+item.product_id)}</td>
                    <td class="p-2 text-center">${item.quantity}</td>
                    <td class="p-2 text-right">${rupiah(item.price)}</td>
                    <td class="p-2 text-right">${rupiah(item.quantity * item.price)}</td>
                </tr>
            `;
        });
        
        const detailBody = document.getElementById('tx-detail-body');
        detailBody.innerHTML = `
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr><th class="p-2 text-left">Produk</th><th class="p-2 text-center">Qty</th><th class="p-2 text-right">Harga</th><th class="p-2 text-right">Subtotal</th></tr>
                    </thead>
                    <tbody>${itemsHtml || '<tr><td colspan="4" class="text-center p-4">Tidak ada item</tr>'}</tbody>
                </table>
            </div>
        `;
        openModal('modal-tx-detail');
    } catch (err) {
        showToast('Gagal memuat detail transaksi', 'error');
    }
}

function exportTransactions() {
    const search = document.getElementById('tx-search')?.value || '';
    const dateFrom = document.getElementById('tx-date-from')?.value || '';
    const dateTo = document.getElementById('tx-date-to')?.value || '';
    window.location.href = `${API_URL}?action=export_transactions&search=${encodeURIComponent(search)}&start_date=${dateFrom}&end_date=${dateTo}`;
}

// ========== CUSTOMERS (CRM) ==========
async function renderCustomers() {
    const search = document.getElementById('cust-search')?.value || '';
    try {
        const res = await fetchAPI('get_customers', 'GET', search ? { search } : null);
        if (res.status !== 'success') throw new Error();
        const tbody = document.getElementById('customers-tbody');
        tbody.innerHTML = '';
        res.data.forEach(cust => {
            const row = `
                <tr>
                    <td class="p-2">${escapeHtml(cust.name)}</td>
                    <td class="p-2">${escapeHtml(cust.phone) || '-'}</td>
                    <td class="p-2">${escapeHtml(cust.address) || '-'}</td>
                    <td class="p-2 text-center">${cust.total_transactions}</td>
                    <td class="p-2 text-right">${rupiah(cust.total_spent)}</td>
                    <td class="p-2">${cust.createdAt || '-'}</td>
                    <td class="p-2">
                        <button class="btn btn-sm btn-secondary" onclick="openCustomerModal(${cust.id})"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete('customer', ${cust.id}, '${escapeHtml(cust.name)}')"><i class="fa-solid fa-trash"></i></button>
                        <button class="btn btn-sm btn-info" onclick="showCustDetail(${cust.id})"><i class="fa-solid fa-eye"></i></button>
                    </td>
                </tr>
            `;
            tbody.innerHTML += row;
        });
    } catch(e) {
        showToast('Gagal memuat pelanggan', 'error');
    }
}

function openCustomerModal(id = null) {
    const title = document.getElementById('modal-cust-title');
    const formId = document.getElementById('customer-id');
    const nameInput = document.getElementById('customer-name');
    const phoneInput = document.getElementById('customer-phone');
    const emailInput = document.getElementById('customer-email');
    const addressInput = document.getElementById('customer-address');

    if (id) {
        fetchAPI('get_customers').then(res => {
            if (res.status === 'success') {
                const cust = res.data.find(c => c.id == id);
                if (cust) {
                    title.innerText = 'Edit Pelanggan';
                    formId.value = cust.id;
                    nameInput.value = cust.name;
                    phoneInput.value = cust.phone || '';
                    emailInput.value = cust.email || '';
                    addressInput.value = cust.address || '';
                }
            }
        });
    } else {
        title.innerText = 'Tambah Pelanggan';
        formId.value = '';
        nameInput.value = '';
        phoneInput.value = '';
        emailInput.value = '';
        addressInput.value = '';
    }
    openModal('modal-customer');
}

async function saveCustomer() {
    const id = document.getElementById('customer-id').value;
    const name = document.getElementById('customer-name').value.trim();
    const phone = document.getElementById('customer-phone').value.trim();
    const email = document.getElementById('customer-email').value.trim();
    const address = document.getElementById('customer-address').value.trim();

    if (!name) {
        showToast('Nama pelanggan wajib diisi', 'error');
        return;
    }

    const payload = { name, phone, email, address };
    if (id) payload.id = id;

    const result = await fetchAPI('save_customer', 'POST', payload);
    if (result.status === 'success') {
        showToast(result.message, 'success');
        closeModal('modal-customer');
        renderCustomers();
    } else {
        showToast(result.message || 'Gagal menyimpan pelanggan', 'error');
    }
}

async function showCustDetail(id) {
    try {
        const res = await fetchAPI('get_customer_detail', 'GET', { id });
        if (res.status !== 'success') throw new Error();
        const cust = res.customer;
        const transactions = res.transactions;
        let txHtml = '<table class="min-w-full text-sm"><thead class="bg-gray-100"><tr><th class="p-2">No. Transaksi</th><th class="p-2">Tanggal</th><th class="p-2">Kasir</th><th class="p-2 text-right">Total</th></tr></thead><tbody>';
        transactions.forEach(tx => {
            txHtml += `<tr><td class="p-2">${tx.invoiceNo}</td><td class="p-2">${tx.date} ${tx.time}</td><td class="p-2">${tx.kasir}</td><td class="p-2 text-right">${rupiah(tx.total)}</td></tr>`;
        });
        txHtml += '</tbody></table>';
        document.getElementById('cust-detail-body').innerHTML = `
            <div class="mb-4"><strong>${escapeHtml(cust.name)}</strong><br>${escapeHtml(cust.phone) || '-'}<br>${escapeHtml(cust.address) || '-'}</div>
            <h4>Riwayat Transaksi</h4>
            ${txHtml}
        `;
        document.getElementById('cust-detail-title').innerText = `Riwayat ${cust.name}`;
        openModal('modal-cust-detail');
    } catch(e) {
        showToast('Gagal memuat detail pelanggan', 'error');
    }
}

// ========== USERS (PENGGUNA) ==========
async function renderUsers() {
    try {
        const res = await fetchAPI('get_users');
        if (res.status !== 'success') throw new Error();
        const tbody = document.getElementById('users-tbody');
        tbody.innerHTML = '';
        res.data.forEach(user => {
            const row = `
                <tr>
                    <td class="p-2">${escapeHtml(user.name)}</td>
                    <td class="p-2">${escapeHtml(user.username)}</td>
                    <td class="p-2 capitalize">${user.role}</td>
                    <td class="p-2"><span class="badge ${user.status === 'active' ? 'badge-green' : 'badge-red'}">${user.status === 'active' ? 'Aktif' : 'Non-aktif'}</span></td>
                    <td class="p-2">${user.createdAt || '-'}</td>
                    <td class="p-2">
                        <button class="btn btn-sm btn-secondary" onclick="openUserModal(${user.id})"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete('user', ${user.id}, '${escapeHtml(user.name)}')"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>
            `;
            tbody.innerHTML += row;
        });
    } catch(e) {
        showToast('Gagal memuat pengguna', 'error');
    }
}

function openUserModal(id = null) {
    const title = document.getElementById('modal-user-title');
    const formId = document.getElementById('user-id');
    const nameInput = document.getElementById('user-name');
    const usernameInput = document.getElementById('user-username');
    const roleSelect = document.getElementById('user-role');
    const statusSelect = document.getElementById('user-status');
    const passwordInput = document.getElementById('user-password');

    if (id) {
        fetchAPI('get_users').then(res => {
            if (res.status === 'success') {
                const user = res.data.find(u => u.id == id);
                if (user) {
                    title.innerText = 'Edit Pengguna';
                    formId.value = user.id;
                    nameInput.value = user.name;
                    usernameInput.value = user.username;
                    roleSelect.value = user.role;
                    statusSelect.value = user.status;
                    passwordInput.value = '';
                    passwordInput.placeholder = 'Kosongkan jika tidak diubah';
                }
            }
        });
    } else {
        title.innerText = 'Tambah Pengguna';
        formId.value = '';
        nameInput.value = '';
        usernameInput.value = '';
        roleSelect.value = 'kasir';
        statusSelect.value = 'active';
        passwordInput.value = '';
        passwordInput.placeholder = 'Password';
    }
    openModal('modal-user');
}

async function saveUser() {
    const id = document.getElementById('user-id').value;
    const name = document.getElementById('user-name').value.trim();
    const username = document.getElementById('user-username').value.trim();
    const role = document.getElementById('user-role').value;
    const status = document.getElementById('user-status').value;
    const password = document.getElementById('user-password').value;

    if (!name || !username) {
        showToast('Nama dan username wajib diisi', 'error');
        return;
    }
    if (!id && !password) {
        showToast('Password wajib diisi untuk pengguna baru', 'error');
        return;
    }

    const payload = { name, username, role, status, password };
    if (id) payload.id = id;

    const result = await fetchAPI('save_user', 'POST', payload);
    if (result.status === 'success') {
        showToast(result.message, 'success');
        closeModal('modal-user');
        renderUsers();
    } else {
        showToast(result.message || 'Gagal menyimpan pengguna', 'error');
    }
}

// ========== LAPORAN PENJUALAN ==========
async function renderReports() {
    const period = document.getElementById('report-period')?.value || 'month';
    try {
        const res = await fetchAPI('get_reports', 'GET', { period });
        if (res.status !== 'success') throw new Error();
        const data = res.data;
        // Stats summary
        const statsHtml = `
            <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-money-bill-wave"></i></div><div class="stat-info"><div class="stat-label">Total Pendapatan</div><div class="stat-value">${rupiah(data.total_revenue)}</div></div></div>
            <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-receipt"></i></div><div class="stat-info"><div class="stat-label">Total Transaksi</div><div class="stat-value">${data.total_transactions}</div></div></div>
            <div class="stat-card"><div class="stat-icon yellow"><i class="fa-solid fa-chart-line"></i></div><div class="stat-info"><div class="stat-label">Rata-rata</div><div class="stat-value">${rupiah(data.average)}</div></div></div>
        `;
        document.getElementById('report-stats').innerHTML = statsHtml;
        
        // Top products table
        const topTbody = document.getElementById('report-top-products');
        topTbody.innerHTML = '';
        data.top_products.forEach((p, idx) => {
            topTbody.innerHTML += `<tr><td class="p-2">${idx+1}</td><td class="p-2">${escapeHtml(p.name)}</td><td class="p-2 text-center">${p.terjual}</td><td class="p-2 text-right">${rupiah(p.pendapatan)}</td></tr>`;
        });
        
        // Chart
        if (data.chart_data.length > 0) {
            const maxTotal = Math.max(...data.chart_data.map(d => d.total));
            let chartHtml = '<div class="chart-bars">';
            data.chart_data.forEach(day => {
                const height = maxTotal > 0 ? (day.total / maxTotal) * 150 : 0;
                chartHtml += `
                    <div class="chart-bar-group">
                        <div class="chart-bar-wrap"><div class="chart-bar" style="height: ${height}px;"></div></div>
                        <div class="chart-bar-val">${rupiah(day.total)}</div>
                        <div class="chart-bar-label">${day.date.slice(5)}</div>
                    </div>
                `;
            });
            chartHtml += '</div>';
            document.getElementById('chart-daily').innerHTML = chartHtml;
        } else {
            document.getElementById('chart-daily').innerHTML = '<p class="text-center text-gray-500 py-4">Tidak ada data</p>';
        }
    } catch(e) {
        showToast('Gagal memuat laporan', 'error');
    }
}

function exportReportCSV() {
    const period = document.getElementById('report-period')?.value || 'month';
    window.location.href = `${API_URL}?action=export_report&period=${period}`;
}

// ========== NAVIGATION ==========
const pageLabels = {
    dashboard: 'Dashboard', pos: 'Kasir (POS)', products: 'Manajemen Produk',
    categories: 'Kategori', transactions: 'Riwayat Transaksi',
    customers: 'Pelanggan (CRM)', reports: 'Laporan Penjualan', users: 'Pengguna'
};
const adminPages = ['products', 'categories', 'transactions', 'customers', 'reports', 'users'];

async function navigateTo(page) {
    if (adminPages.includes(page) && currentUser.role !== 'admin') {
        showToast('Akses ditolak — hanya untuk Admin', 'error');
        return;
    }
    currentPage = page;
    el('navbar-title').textContent = pageLabels[page] || page;
    document.querySelectorAll('[id^="view-"]').forEach(v => v.classList.add('hidden'));
    el(`view-${page}`).classList.remove('hidden');
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    const nav = el(`nav-${page}`);
    if (nav) nav.classList.add('active');
    closeSidebar();
    if (page === 'dashboard') await renderDashboard();
    else if (page === 'products') await renderProducts();
    else if (page === 'categories') await renderCategories();
    else if (page === 'pos') {
        renderPosProducts();
        loadCustomersForPos();   // <-- TAMBAHAN: muat pelanggan untuk dropdown
    }
    else if (page === 'transactions') {
        const searchInput = document.getElementById('tx-search');
        const dateFrom = document.getElementById('tx-date-from');
        const dateTo = document.getElementById('tx-date-to');
        const refresh = () => { currentTxPage = 1; renderTransactions(); };
        if (searchInput) searchInput.oninput = refresh;
        if (dateFrom) dateFrom.onchange = refresh;
        if (dateTo) dateTo.onchange = refresh;
        renderTransactions();
    }
    else if (page === 'customers') {
    const custSearch = document.getElementById('cust-search');
    if (custSearch) custSearch.oninput = () => renderCustomers();
    renderCustomers();
}
else if (page === 'users') {
    renderUsers();
}
else if (page === 'reports') {
    const reportPeriod = document.getElementById('report-period');
    if (reportPeriod) reportPeriod.onchange = () => renderReports();
    renderReports();
}
}

function toggleSidebar() {
    el('sidebar').classList.toggle('open');
    el('overlay').classList.toggle('hidden', !el('sidebar').classList.contains('open'));
}
function closeSidebar() {
    el('sidebar').classList.remove('open');
    el('overlay').classList.add('hidden');
}

function fillDemo(u, p) { el('login-username').value = u; el('login-password').value = p; }

// Bootstrap
(function boot() {
    if (restoreSession()) {
        initApp();
        showPage('app');
        navigateTo('dashboard');
    } else {
        showPage('landing');
    }
})();