let products = [];

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, ch => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    }[ch]));
}

async function loadProducts() {
    const grid = document.getElementById("product-grid");
    try {
        const response = await fetch("api/products.php", { cache: "no-store" });
        if (!response.ok) throw new Error(response.status);
        products = await response.json();
    } catch (error) {
        products = [];
        if (grid) grid.innerHTML = "<p>Menu gagal dimuat. Coba muat ulang halaman.</p>";
        return;
    }
    renderProducts();
}

function formatRupiah(price) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0
    }).format(price);
}

function renderProducts(list = products) {
    const grid = document.getElementById("product-grid");

    if (!list.length) {
        grid.innerHTML = "<p>Produk tidak ditemukan.</p>";
        return;
    }

    grid.innerHTML = list.map(product => `
        <article class="product-card">
            <img src="${escapeHtml(product.image)}"
                 alt="${escapeHtml(product.name)}"
                 loading="lazy"
                 onerror="this.src='https://placehold.co/600x400?text=Chizu+House'">

            <div class="product-info">
                <span class="product-category">${escapeHtml(product.category)}</span>
                <h3>${escapeHtml(product.name)}</h3>
                <p>${escapeHtml(product.description)}</p>

                <div class="product-bottom">
                    <span class="product-price">
                        ${formatRupiah(product.price)}
                    </span>

                    <button class="add-btn"
                        onclick="addToCart(${product.id})">
                        + Tambah
                    </button>
                </div>
            </div>
        </article>
    `).join("");
}

function filterCategory(category, button) {
    document.querySelectorAll(".category-card")
        .forEach(item => item.classList.remove("active"));

    button.classList.add("active");

    const filtered = category === "All"
        ? products
        : products.filter(item => item.category === category);

    renderProducts(filtered);
}

function searchProducts(keyword) {
    const query = keyword.toLowerCase().trim();

    const filtered = products.filter(product =>
        product.name.toLowerCase().includes(query) ||
        product.category.toLowerCase().includes(query)
    );

    renderProducts(filtered);
}
