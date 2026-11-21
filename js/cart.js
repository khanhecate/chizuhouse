// Array penampung keranjang belanja
let cart = [];

// Fungsi untuk menambahkan produk ke keranjang berdasarkan ID
function addToCart(productId) {
    // 1. Cek apakah user sudah login atau belum
    if (typeof isUserLoggedIn !== 'undefined' && !isUserLoggedIn) {
        // Tampilkan modal pop-up peringatan login alih-alih alert()
        const loginModal = document.getElementById('login-alert-modal');
        if (loginModal) {
            loginModal.style.display = 'flex';
            loginModal.style.pointerEvents = 'auto';
        }
        return; // Menghentikan proses agar produk tidak masuk ke keranjang
    }

    // Pastikan variabel 'products' tersedia dari products.js
    const product = products.find(p => p.id === productId);
    if (!product) return;

    // Cek apakah produk sudah ada di dalam keranjang
    const existingItem = cart.find(item => item.id === productId);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            image: product.image,
            quantity: 1
        });
    }

    // Perbarui tampilan badge jumlah dan daftar isi keranjang
    updateCart();
    
    // Tampilkan notifikasi toast
    showToast("Berhasil menambahkan " + product.name + " ke keranjang!");
}

// Fungsi untuk memperbarui tampilan badge dan isi keranjang
function updateCart() {
    const cartCount = document.getElementById("cart-count");
    const cartItemsContainer = document.getElementById("cart-items");
    const cartTotalElement = document.getElementById("cart-total");

    // Hitung total item untuk badge
    const totalCount = cart.reduce((sum, item) => sum + item.quantity, 0);
    if (cartCount) cartCount.innerText = totalCount;

    // Render daftar item di dalam panel keranjang
    if (cartItemsContainer) {
        if (cart.length === 0) {
            cartItemsContainer.innerHTML = '<p style="text-align: center; color: #6b7280; padding: 20px;">Keranjang belanja masih kosong.</p>';
        } else {
            cartItemsContainer.innerHTML = cart.map(item => `
                <div class="cart-item" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid #f3f4f6; padding-bottom: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <img src="${item.image}" alt="${item.name}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                        <div>
                            <h4 style="margin: 0; font-size: 14px; color: #1f2937;">${item.name}</h4>
                            <span style="font-size: 12px; color: #6b7280;">${formatRupiah(item.price)} x ${item.quantity}</span>
                        </div>
                    </div>
                    <button onclick="removeFromCart(${item.id})" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 16px;">🗑️</button>
                </div>
            `).join("");
        }
    }

    // Hitung total harga keseluruhan
    let totalPrice = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    if (cartTotalElement) {
        cartTotalElement.innerText = formatRupiah(totalPrice);
    }
}

// Fungsi untuk menghapus item dari keranjang
function removeFromCart(productId) {
    cart = cart.filter(item => item.id !== productId);
    updateCart();
}

// Fungsi untuk membuka / menutup panel keranjang belanja
function toggleCart() {
    const cartPanel = document.getElementById('cart-panel');
    const cartOverlay = document.getElementById('cart-overlay');
    
    if (cartPanel) {
        // Mendukung kedua nama class template (active dan show)
        cartPanel.classList.toggle('active');
        cartPanel.classList.toggle('show');
    }
    
    if (cartOverlay) {
        const isOpen = cartPanel.classList.contains('active') || cartPanel.classList.contains('show');
        if (isOpen) {
            cartOverlay.style.display = 'block';
            cartOverlay.style.pointerEvents = 'auto';
        } else {
            cartOverlay.style.display = 'none';
            cartOverlay.style.pointerEvents = 'none';
        }
    }
}

// Fungsi Toast Notifikasi
function showToast(message) {
    const toast = document.getElementById('toast');
    if (toast) {
        toast.innerText = message;
        toast.className = "toast show";
        setTimeout(() => {
            toast.className = toast.className.replace("show", "");
        }, 3000);
    }
}

// Fungsi Checkout yang memunculkan Modal QR Pembayaran
function checkout() {
    if (cart.length === 0) {
        alert('Keranjang belanja Anda masih kosong!');
        return;
    }

    let total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

    const totalText = document.getElementById('payment-total-text');
    if (totalText) {
        totalText.innerText = "Total Tagihan: " + formatRupiah(total);
    }

    const modal = document.getElementById('payment-modal');
    if (modal) {
        modal.style.display = 'flex';
        modal.style.pointerEvents = 'auto';
    }
}

// Fungsi untuk menutup modal pembayaran QR
function closePaymentModal() {
    const modal = document.getElementById('payment-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.style.pointerEvents = 'none';
    }
}

// Konfirmasi pembayaran: simpan pesanan ke server, lalu kosongkan keranjang
let isSubmittingOrder = false;
async function confirmPayment() {
    if (isSubmittingOrder || cart.length === 0) return;
    isSubmittingOrder = true;
    const confirmBtn = document.querySelector('#payment-modal button[onclick="confirmPayment()"]');
    if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.style.opacity = '0.6'; }
    let result;
    try {
        const response = await fetch('api/checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ items: cart.map(item => ({ id: item.id, qty: item.quantity })) })
        });
        result = await response.json();
    } catch (error) {
        result = { ok: false, error: 'Tidak bisa terhubung ke server. Coba lagi.' };
    }
    isSubmittingOrder = false;
    if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.style.opacity = ''; }
    if (!result.ok) {
        showToast(result.error || 'Pesanan gagal disimpan.');
        return;
    }
    // Kosongkan keranjang
    cart = [];
    updateCart();
    // Tutup modal pembayaran QR dan panel keranjang
    closePaymentModal();
    const cartPanel = document.getElementById('cart-panel');
    const cartOverlay = document.getElementById('cart-overlay');
    if (cartPanel) cartPanel.classList.remove('active', 'show');
    if (cartOverlay) {
        cartOverlay.style.display = 'none';
        cartOverlay.style.pointerEvents = 'none';
    }
    // Tampilkan modal sukses dengan nomor pesanan
    const orderText = document.getElementById('success-order-text');
    if (orderText) {
        orderText.innerText = 'Nomor pesanan #' + result.order_id + ' (' + formatRupiah(result.total) + '). Pantau statusnya di Pesanan Saya.';
    }
    const successModal = document.getElementById('success-modal');
    if (successModal) {
        successModal.style.display = 'flex';
        successModal.style.pointerEvents = 'auto';
    }
}
// Fungsi untuk menutup modal pop-up sukses dan kembali normal
function closeSuccessModal() {
    const successModal = document.getElementById('success-modal');
    if (successModal) {
        successModal.style.display = 'none';
        successModal.style.pointerEvents = 'none';
    }
}

// Fungsi untuk menutup modal pop-up peringatan login
function closeLoginAlertModal() {
    const loginModal = document.getElementById('login-alert-modal');
    if (loginModal) {
        loginModal.style.display = 'none';
        loginModal.style.pointerEvents = 'none';
    }
}