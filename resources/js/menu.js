/* ==========================================================
   Salchipulpos · Menú público
   El cliente arma su pedido y lo paga en caja.
   ========================================================== */

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const money = (n) => '$' + (Number(n) % 1 === 0 ? Number(n).toFixed(0) : Number(n).toFixed(2));
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const STORAGE_KEY = 'salchipulpos_menu';
const products = JSON.parse($('#menuData').textContent);
const productById = (id) => products.find((p) => p.id === Number(id));

const state = {
  cart: new Map(),
  current: null,
  qty: 1,
  order: null,
  pollTimer: null,
};

/* ---------- Guardado local (solo comodidad del cliente) ---------- */
function persist() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      cart: [...state.cart],
      name: $('#customerName').value,
      order: state.order?.status === 'pending' ? state.order.uuid : null,
    }));
  } catch { /* almacenamiento no disponible */ }
}

function restore() {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    (saved.cart || []).forEach(([id, qty]) => { if (productById(id)) state.cart.set(Number(id), qty); });
    if (saved.name) $('#customerName').value = saved.name;
    if (saved.order) trackOrder(saved.order);
  } catch { /* nada guardado */ }
}

/* ---------- Utilidades ---------- */
function toast(message, error = false) {
  const el = document.createElement('div');
  el.className = 'toast' + (error ? ' error' : '');
  el.textContent = message;
  $('#toasts').append(el);
  setTimeout(() => el.remove(), 3200);
}

function openModal(selector) { $(selector).classList.add('is-on'); document.body.style.overflow = 'hidden'; }
function closeModal(selector) {
  $(selector).classList.remove('is-on');
  if (!$('.modal.is-on')) document.body.style.overflow = '';
}

$$('.modal').forEach((modal) => modal.addEventListener('click', (e) => {
  if (e.target === modal || e.target.closest('[data-close]')) closeModal('#' + modal.id);
}));
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') $$('.modal.is-on').forEach((m) => closeModal('#' + m.id));
});

/* ---------- Info del producto ---------- */
$$('.js-product').forEach((el) => el.addEventListener('click', () => openProduct(Number(el.dataset.id))));

function openProduct(id) {
  const product = productById(id);
  if (!product) return;
  state.current = product;
  state.qty = 1;

  $('#pmEmoji').textContent = product.emoji || '🐙';
  $('#pmName').textContent = product.name;
  $('#pmDesc').textContent = product.description || '';
  $('#pmPrice').textContent = `${money(product.price)} MXN`;
  $('#pmKcal').textContent = product.calories ? `~${product.calories} kcal` : '';
  renderProductQty();
  openModal('#productModal');
  setTimeout(() => $('#pmAdd').focus(), 50);
}

function renderProductQty() {
  const p = state.current;
  const out = p.stock <= 0;
  $('#pmQty').textContent = state.qty;
  $('#pmAdd').disabled = out;
  $('#pmAdd').textContent = out ? 'Agotado por hoy' : `Agregar · ${money(p.price * state.qty)}`;
}

$('#pmMinus').onclick = () => { state.qty = Math.max(1, state.qty - 1); renderProductQty(); };
$('#pmPlus').onclick = () => { state.qty = Math.min(20, state.qty + 1); renderProductQty(); };
$('#pmAdd').onclick = () => {
  const p = state.current;
  state.cart.set(p.id, Math.min(20, (state.cart.get(p.id) || 0) + state.qty));
  closeModal('#productModal');
  renderCart();
  toast(`✅ ${state.qty} × ${p.name} en tu pedido`);
  $('#cartOpen').classList.remove('bump');
  void $('#cartOpen').offsetWidth;
  $('#cartOpen').classList.add('bump');
};

/* ---------- Carrito ---------- */
const cartTotal = () => [...state.cart].reduce((sum, [id, qty]) => sum + productById(id).price * qty, 0);
const cartCount = () => [...state.cart.values()].reduce((a, b) => a + b, 0);

function renderCart() {
  const lines = [...state.cart];
  $('#cartLines').innerHTML = lines.length ? lines.map(([id, qty]) => {
    const p = productById(id);
    return `<div class="cart-line">
      <div><div class="nm">${esc(p.name)}</div><div class="unit">${money(p.price)} c/u</div></div>
      <span class="sub">${money(p.price * qty)}</span>
      <div class="stepper"><button type="button" data-id="${id}" data-d="-1" aria-label="Quitar uno">−</button><span>${qty}</span><button type="button" data-id="${id}" data-d="1" aria-label="Agregar uno">+</button></div>
    </div>`;
  }).join('') : '<div class="cart-empty"><span>🐙</span>Tu pedido está vacío.<br>Toca un platillo del menú para agregarlo.</div>';

  const total = cartTotal();
  const count = cartCount();
  $('#cartTotal').textContent = money(total);
  $('#cartCount').textContent = count;
  const pending = state.order?.status === 'pending' && !$('#sentStep').hidden;
  $('#cartBarCount').textContent = pending ? `Tu pedido #${state.order.number} · paga en caja` : `${count} ${count === 1 ? 'producto' : 'productos'}`;
  $('#cartBarTotal').textContent = money(pending ? state.order.total : total);
  $('#cartBar').hidden = !count && !pending;
  $('#orderSend').disabled = !count;
  persist();
}

$('#cartLines').addEventListener('click', (e) => {
  const button = e.target.closest('.stepper button');
  if (!button) return;
  const id = Number(button.dataset.id);
  const qty = (state.cart.get(id) || 0) + Number(button.dataset.d);
  if (qty <= 0) state.cart.delete(id);
  else state.cart.set(id, Math.min(20, qty));
  renderCart();
});

const openCart = () => openModal('#cartModal');
$('#cartOpen').onclick = openCart;
$('#cartBar').onclick = openCart;

/* ---------- Enviar pedido ---------- */
$('#orderForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  if (!state.cart.size) return;
  const button = $('#orderSend');
  button.disabled = true;
  button.textContent = 'Enviando…';

  try {
    const response = await fetch('/api/orders', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').content },
      body: JSON.stringify({
        customer_name: $('#customerName').value.trim(),
        note: $('#customerNote').value.trim() || null,
        items: [...state.cart].map(([id, qty]) => ({ id, qty })),
      }),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const first = data.errors ? Object.values(data.errors).flat()[0] : null;
      throw new Error(response.status === 429 ? 'Demasiados pedidos seguidos. Espera un minuto.' : first || data.message || 'No se pudo enviar el pedido.');
    }

    state.cart.clear();
    $('#customerNote').value = '';
    showOrder(data.order);
    startPolling();
  } catch (error) {
    toast(error.message, true);
  } finally {
    button.textContent = 'Enviar pedido';
    renderCart();
  }
});

const statusText = {
  pending: '⏳ Pasa a caja, di tu número y paga ahí.',
  paid: '✅ ¡Pagado! Ya estamos preparando tu pedido.',
  cancelled: '❌ Este pedido fue cancelado. Pregunta en caja.',
};

function showOrder(order) {
  state.order = order;
  $('#cartStep').hidden = true;
  $('#sentStep').hidden = false;
  $('#cartTitle').textContent = order.customer_name ? `¡Gracias, ${order.customer_name}!` : '¡Gracias!';
  $('#sentNumber').textContent = '#' + order.number;
  $('#sentStatus').className = 'sent-status ' + order.status;
  $('#sentStatus').textContent = statusText[order.status] || '';
  $('#sentLines').innerHTML = order.items.map((i) => `<div><span>${i.quantity} × ${esc(i.name)}</span><b>${money(i.subtotal)}</b></div>`).join('')
    + `<div><span><b>Total a pagar</b></span><b>${money(order.total)}</b></div>`;
  renderCart();
  persist();
}

async function trackOrder(uuid) {
  try {
    const response = await fetch(`/api/orders/${encodeURIComponent(uuid)}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error();
    const { order } = await response.json();
    const changed = state.order && state.order.status !== order.status;
    showOrder(order);
    if (changed && order.status === 'paid') toast('✅ ¡Tu pedido fue pagado!');
    if (order.status === 'pending') startPolling();
    else stopPolling();
  } catch {
    stopPolling();
  }
}

function startPolling() {
  stopPolling();
  state.pollTimer = setInterval(() => { if (!document.hidden && state.order) trackOrder(state.order.uuid); }, 8000);
}
function stopPolling() { clearInterval(state.pollTimer); state.pollTimer = null; }

$('#newOrder').onclick = () => {
  stopPolling();
  state.order = null;
  $('#cartTitle').textContent = 'Mi pedido';
  $('#cartStep').hidden = false;
  $('#sentStep').hidden = true;
  renderCart();
  closeModal('#cartModal');
};

/* ---------- Arranque ---------- */
restore();
renderCart();
