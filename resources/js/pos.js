/* ==========================================================
   Salchipulpos · Punto de venta
   Todo se guarda en el servidor (Laravel); el navegador solo muestra.
   ========================================================== */

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const moneyFormat = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
const money = (amount) => moneyFormat.format(Number(amount) || 0);
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const pad = (n, size = 2) => String(n).padStart(size, '0');
const folio = (n) => '#' + pad(n, 4);
const hourLabel = (h) => `${pad(h)}:00`;
const timeOf = (iso) => new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
const dateOf = (iso, opts = { day: '2-digit', month: 'short', year: 'numeric' }) => new Date(iso).toLocaleDateString('es-MX', opts);
const todayIso = () => { const d = new Date(); return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; };

const state = {
  settings: { business_name: '', ticket_footer: '' },
  categories: [],
  products: [],
  order: new Map(),
  category: null,
  search: '',
  method: 'efectivo',
  lastSale: null,
  unlocked: false,
  admin: null,
  panel: 'summary',
  salesThisHour: 0,
  orders: [],
  seenOrders: null,
  pendingOrder: null,
  currentHour: new Date().getHours(),
};

const productById = (id) => state.products.find((p) => p.id === Number(id));
const orderTotal = () => [...state.order].reduce((sum, [id, qty]) => sum + (productById(id)?.price ?? 0) * qty, 0);
const orderCount = () => [...state.order.values()].reduce((a, b) => a + b, 0);

/* ---------- API ---------- */
class ApiError extends Error {
  constructor(message, status) { super(message); this.status = status; }
}

async function api(url, { method = 'GET', body } = {}) {
  const response = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').content,
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
    let message = firstError || data.message || 'Ocurrió un error. Intenta de nuevo.';
    if (response.status === 419) message = 'La sesión expiró. Recarga la página.';
    if (response.status === 429) message = 'Demasiados intentos. Espera un minuto.';
    if (response.status === 401) requireLogin();
    throw new ApiError(message, response.status);
  }

  return data;
}

/* ---------- Toasts y confirmaciones ---------- */
function toast(message, { error = false, action, actionLabel, timeout = 4200 } = {}) {
  const el = document.createElement('div');
  el.className = 'toast' + (error ? ' error' : '');
  el.innerHTML = `<span>${esc(message)}</span>${action ? `<button type="button">${esc(actionLabel)}</button>` : ''}`;
  if (action) $('button', el).onclick = () => { action(); el.remove(); };
  $('#toasts').append(el);
  setTimeout(() => el.remove(), action ? timeout * 2 : timeout);
}

function ask(text, yes = 'Sí', no = 'No') {
  return new Promise((resolve) => {
    $('#askText').textContent = text;
    $('#askYes').textContent = yes;
    $('#askNo').textContent = no;
    openModal('#askModal');
    const done = (value) => { closeModal('#askModal'); resolve(value); };
    $('#askYes').onclick = () => done(true);
    $('#askNo').onclick = () => done(false);
    setTimeout(() => $('#askYes').focus(), 30);
  });
}

function openModal(selector) { $(selector).classList.add('is-on'); }
function closeModal(selector) { $(selector).classList.remove('is-on'); }

/* ---------- Reloj y cortes por hora ---------- */
function tickClock() {
  const now = new Date();
  $('#clockTime').textContent = now.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
  const minutesLeft = 60 - now.getMinutes();
  $('#clockNext').textContent = `Corte de hora en ${minutesLeft} min`;

  if (now.getHours() !== state.currentHour) {
    const finishedHour = state.currentHour;
    state.currentHour = now.getHours();
    onHourFinished(finishedHour);
  }
}

async function onHourFinished(hour) {
  const hadSales = state.salesThisHour > 0;
  state.salesThisHour = 0;

  await loadCatalog().catch(() => {});

  if (state.unlocked) {
    await loadAdmin().catch(() => {});
    const cut = state.admin?.hourly_cuts?.find((c) => new Date(c.period_start).getHours() === hour);
    if (cut) {
      toast(`⏰ Corte de ${hourLabel(hour)} a ${hourLabel((hour + 1) % 24)} listo: ${money(cut.total)}`, {
        action: () => printCut(cut), actionLabel: 'Imprimir',
      });
    }
  } else if (hadSales) {
    toast(`⏰ Corte de ${hourLabel(hour)} a ${hourLabel((hour + 1) % 24)} guardado automáticamente`);
  }
}

/* ---------- Navegación ---------- */
$$('.tab').forEach((tab) => tab.addEventListener('click', () => showView(tab.dataset.view)));

function showView(view) {
  $$('.tab').forEach((t) => t.classList.toggle('is-on', t.dataset.view === view));
  $$('.view').forEach((v) => v.classList.toggle('is-on', v.id === view));
  if (view === 'admin') loadAdmin().catch((e) => toast(e.message, { error: true }));
  else renderMenu();
}

$$('.subtab[data-panel]').forEach((tab) => tab.addEventListener('click', () => showPanel(tab.dataset.panel)));

function showPanel(panel) {
  state.panel = panel;
  $$('.subtab[data-panel]').forEach((t) => t.classList.toggle('is-on', t.dataset.panel === panel));
  $$('.panel').forEach((p) => p.classList.toggle('is-on', p.id === `panel-${panel}`));
}

/* ---------- PIN ---------- */
let pinValue = '';

function openPin() {
  pinValue = '';
  $('#pinError').textContent = '';
  renderPin();
  openModal('#pinModal');
}

function renderPin() {
  $('#pinDots').innerHTML = Array.from({ length: Math.max(4, pinValue.length) }, (_, i) => `<i class="${i < pinValue.length ? 'on' : ''}"></i>`).join('');
}

$('#pinPad').innerHTML = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '⌫', '0', '✓']
  .map((k) => `<button type="button" data-k="${k}" aria-label="${k === '⌫' ? 'Borrar' : k === '✓' ? 'Entrar' : k}">${k}</button>`).join('');

$('#pinPad').addEventListener('click', (e) => {
  const key = e.target.closest('button')?.dataset.k;
  if (key) pressPin(key);
});

document.addEventListener('keydown', (e) => {
  if (!$('#pinModal').classList.contains('is-on')) return;
  if (/^\d$/.test(e.key)) pressPin(e.key);
  else if (e.key === 'Backspace') pressPin('⌫');
  else if (e.key === 'Enter') pressPin('✓');
});

async function pressPin(key) {
  $('#pinError').textContent = '';
  if (key === '⌫') pinValue = pinValue.slice(0, -1);
  else if (key === '✓') return submitPin();
  else if (pinValue.length < 8) pinValue += key;
  renderPin();
}

async function submitPin() {
  if (pinValue.length < 4) return;
  try {
    await api('/api/admin/session', { method: 'POST', body: { pin: pinValue } });
    state.unlocked = true;
    closeModal('#pinModal');
    startWork();
  } catch (error) {
    pinValue = '';
    renderPin();
    $('#pinError').textContent = error.message;
    $('#pinDots').classList.add('shake');
    setTimeout(() => $('#pinDots').classList.remove('shake'), 400);
  }
}

function requireLogin() {
  if (!state.unlocked && $('#pinModal').classList.contains('is-on')) return;
  state.unlocked = false;
  state.admin = null;
  $$('.modal.is-on').forEach((m) => m.classList.remove('is-on'));
  openPin();
}

$('#btnLock').onclick = async () => {
  try {
    await api('/api/admin/session', { method: 'DELETE' });
  } finally {
    window.location.href = '/';
  }
};

/* ---------- Pedidos de clientes (desde el menú) ---------- */
async function loadOrders() {
  if (!state.unlocked) return;
  const { orders } = await api('/api/admin/orders');
  const fresh = state.seenOrders ? orders.filter((o) => !state.seenOrders.has(o.id)) : [];
  state.seenOrders = new Set(orders.map((o) => o.id));
  state.orders = orders;

  fresh.forEach((o) => toast(`📥 Nuevo pedido #${o.id} de ${o.customer_name || 'cliente'} · ${money(o.total)}`, {
    action: () => openOrders(), actionLabel: 'Ver',
  }));

  if (state.pendingOrder && !orders.some((o) => o.id === state.pendingOrder.id)) {
    state.pendingOrder = null;
    renderOrder();
  }
  $('#ordersCount').textContent = orders.length;
  $('#ordersBtn').classList.toggle('has-orders', orders.length > 0);
  if ($('#ordersModal').classList.contains('is-on')) renderOrders();
}

function renderOrders() {
  $('#ordersList').innerHTML = state.orders.length ? state.orders.map((o) => `<article class="porder">
      <div class="porder-top">
        <span class="porder-num">#${o.id}</span>
        <span class="porder-who">${esc(o.customer_name || 'Cliente')}<small>hace ${minutesAgo(o.created_at)}</small></span>
        <span class="porder-total">${money(o.total)}</span>
      </div>
      <ul>${o.items.map((i) => `<li>${i.quantity} × ${esc(i.name)}</li>`).join('')}</ul>
      ${o.note ? `<p class="porder-note">📝 ${esc(o.note)}</p>` : ''}
      <div class="porder-actions">
        <button class="btn btn-ghost btn-sm" type="button" data-cancel="${o.id}">Cancelar</button>
        <button class="btn btn-primary btn-sm" type="button" data-charge="${o.id}">Cobrar en caja →</button>
      </div>
    </article>`).join('') : '<p class="empty"><span class="big-emoji">📭</span>No hay pedidos pendientes.<br>Cuando un cliente pida desde el menú aparecerá aquí.</p>';
}

function minutesAgo(iso) {
  const minutes = Math.max(0, Math.round((Date.now() - new Date(iso)) / 60000));
  return minutes < 1 ? 'un momento' : minutes < 60 ? `${minutes} min` : `${Math.floor(minutes / 60)} h ${minutes % 60} min`;
}

function openOrders() {
  renderOrders();
  openModal('#ordersModal');
  loadOrders().catch(() => {});
}

$('#ordersBtn').onclick = openOrders;
$('#ordersClose').onclick = () => closeModal('#ordersModal');

$('#ordersList').addEventListener('click', async (e) => {
  const charge = e.target.closest('[data-charge]');
  const cancel = e.target.closest('[data-cancel]');
  if (charge) loadOrderIntoCart(state.orders.find((o) => o.id === Number(charge.dataset.charge)));
  if (cancel && await ask(`¿Cancelar el pedido #${cancel.dataset.cancel}?`, 'Sí, cancelar', 'No')) {
    try {
      await api(`/api/admin/orders/${cancel.dataset.cancel}/cancel`, { method: 'POST' });
      toast('Pedido cancelado');
      await loadOrders();
      openModal('#ordersModal');
    } catch (error) {
      toast(error.message, { error: true });
    }
  }
});

function loadOrderIntoCart(order) {
  if (!order) return;
  state.order.clear();
  const problems = [];

  order.items.forEach((item) => {
    const product = productById(item.product_id);
    if (!product) return problems.push(`${item.name} ya no está a la venta`);
    const qty = Math.min(item.quantity, product.stock);
    if (qty < item.quantity) problems.push(`solo hay ${product.stock} de ${product.name}`);
    if (qty > 0) state.order.set(product.id, qty);
  });

  state.pendingOrder = order;
  closeModal('#ordersModal');
  showView('sell');
  if (problems.length) toast(`Revisa el pedido: ${problems.join(', ')}.`, { error: true, timeout: 7000 });
  else toast(`Pedido #${order.id} cargado. Revisa y cobra.`);
}

/* ==========================================================
   VENTA
   ========================================================== */
async function loadCatalog() {
  const data = await api('/api/catalog');
  state.settings = data.settings;
  state.categories = data.categories;
  state.products = data.products;
  $('#brandName').textContent = data.settings.business_name;

  for (const [id, qty] of state.order) {
    const product = productById(id);
    if (!product) state.order.delete(id);
    else if (qty > product.stock) product.stock > 0 ? state.order.set(id, product.stock) : state.order.delete(id);
  }

  renderMenu();
}

function renderMenu() {
  const withProducts = state.categories.filter((c) => state.products.some((p) => p.category_id === c.id));
  if (!withProducts.some((c) => c.id === state.category)) state.category = withProducts[0]?.id ?? null;

  $('#cats').innerHTML = withProducts.map((c) => {
    const count = state.products.filter((p) => p.category_id === c.id).length;
    return `<button type="button" role="tab" class="cat ${c.id === state.category && !state.search ? 'is-on' : ''}" data-id="${c.id}">${esc(c.name)} <small>${count}</small></button>`;
  }).join('');

  const term = state.search.trim().toLowerCase();
  const visible = term
    ? state.products.filter((p) => p.name.toLowerCase().includes(term))
    : state.products.filter((p) => p.category_id === state.category);

  $('#grid').innerHTML = visible.length ? visible.map((p) => {
    const qty = state.order.get(p.id) || 0;
    const stockText = p.stock <= 0 ? 'Agotado' : p.stock <= 5 ? `¡Quedan ${p.stock}!` : `${p.stock} disp.`;
    return `<button type="button" class="tile ${qty ? 'is-selected' : ''} ${p.stock <= 0 ? 'is-out' : ''}" data-id="${p.id}">
      ${qty ? `<span class="qty-badge">${qty}</span>` : ''}
      <span class="emoji">${esc(p.emoji || '🍽️')}</span>
      <span class="name">${esc(p.name)}</span>
      <span class="row"><span class="price">${money(p.price)}</span><span class="stock ${p.stock <= 5 ? 'low' : ''}">${stockText}</span></span>
    </button>`;
  }).join('') : `<p class="grid-empty">${term ? 'No hay productos con ese nombre.' : 'Aún no hay productos. Agrégalos en Administrar → Inventario.'}</p>`;

  renderOrder();
}

$('#cats').addEventListener('click', (e) => {
  const button = e.target.closest('.cat');
  if (!button) return;
  state.category = Number(button.dataset.id);
  state.search = '';
  $('#search').value = '';
  renderMenu();
});

$('#search').addEventListener('input', (e) => { state.search = e.target.value; renderMenu(); });

$('#grid').addEventListener('click', (e) => {
  const tile = e.target.closest('.tile');
  if (!tile) return;
  changeQty(Number(tile.dataset.id), 1);
  $(`.tile[data-id="${tile.dataset.id}"]`)?.classList.add('bump');
});

function changeQty(id, delta) {
  const product = productById(id);
  if (!product) return;
  const qty = (state.order.get(id) || 0) + delta;
  if (qty > product.stock) return toast(`Solo hay ${product.stock} de "${product.name}".`, { error: true });
  if (qty <= 0) state.order.delete(id);
  else state.order.set(id, qty);
  renderMenu();
}

function renderOrder() {
  const lines = [...state.order];
  const from = state.pendingOrder;
  $('#orderFrom').hidden = !from;
  if (from) {
    $('#orderFrom').innerHTML = `<div>📥 Pedido #${from.id} · ${esc(from.customer_name || 'Cliente')}${from.note ? `<small>📝 ${esc(from.note)}</small>` : ''}</div>
      <button type="button" class="link-btn" id="orderFromClear">Soltar</button>`;
    $('#orderFromClear').onclick = () => { state.pendingOrder = null; state.order.clear(); renderMenu(); };
  }
  $('#lines').innerHTML = lines.length ? lines.map(([id, qty]) => {
    const p = productById(id);
    return `<div class="line">
      <div><div class="nm">${esc(p.emoji)} ${esc(p.name)}</div><div class="unit">${money(p.price)} c/u</div></div>
      <span class="sub">${money(p.price * qty)}</span>
      <div class="stepper"><button type="button" data-id="${id}" data-d="-1" aria-label="Quitar uno">−</button><span>${qty}</span><button type="button" data-id="${id}" data-d="1" aria-label="Agregar uno">+</button></div>
    </div>`;
  }).join('') : '<div class="empty"><span class="big-emoji">🐙</span>Toca un producto<br>para agregarlo al pedido</div>';

  const total = orderTotal();
  $('#total').textContent = money(total);
  $('#btnPay').disabled = !lines.length;
  $('#btnPay').textContent = lines.length ? `Cobrar ${money(total)}` : 'Cobrar';
  $('#orderCount').textContent = orderCount();
  $('#orderToggleTotal').textContent = money(total);
  $('#orderToggle').classList.toggle('is-empty', !lines.length);
}

$('#lines').addEventListener('click', (e) => {
  const button = e.target.closest('.stepper button');
  if (button) changeQty(Number(button.dataset.id), Number(button.dataset.d));
});

$('#btnClear').onclick = async () => {
  if (!state.order.size) return;
  if (await ask('¿Vaciar el pedido actual?', 'Sí, vaciar', 'No')) { state.order.clear(); state.pendingOrder = null; renderMenu(); }
};

$('#orderToggle').onclick = () => $('#order').classList.toggle('is-open');
document.addEventListener('click', (e) => {
  const order = $('#order');
  if (order.classList.contains('is-open') && !order.contains(e.target) && !$('#orderToggle').contains(e.target) && !e.target.closest('.modal')) {
    order.classList.remove('is-open');
  }
});

/* ---------- Cobro ---------- */
$('#btnPay').onclick = () => {
  const total = orderTotal();
  $('#payStep').hidden = false;
  $('#doneStep').hidden = true;
  $('#payTotal').textContent = money(total);
  $('#paid').value = '';

  const bills = [20, 50, 100, 200, 500, 1000].filter((b) => b > total).slice(0, 4);
  $('#quick').innerHTML = `<button type="button" data-v="${total}">Exacto</button>` + bills.map((b) => `<button type="button" data-v="${b}">$${b}</button>`).join('');

  setMethod('efectivo');
  openModal('#payModal');
  setTimeout(() => $('#paid').focus(), 60);
};

$('#quick').addEventListener('click', (e) => {
  const button = e.target.closest('button');
  if (!button) return;
  $('#paid').value = button.dataset.v;
  updateChange();
});

$$('.seg').forEach((seg) => seg.addEventListener('click', () => setMethod(seg.dataset.method)));

function setMethod(method) {
  state.method = method;
  $$('.seg').forEach((s) => s.classList.toggle('is-on', s.dataset.method === method));
  $('#cashFields').hidden = method !== 'efectivo';
  updateChange();
}

function updateChange() {
  const total = orderTotal();
  const box = $('#changeBox');
  if (state.method === 'tarjeta') {
    box.className = 'change card';
    box.innerHTML = `<span>Cobra con la terminal</span><strong>${money(total)}</strong>`;
    $('#btnConfirm').disabled = false;
    return;
  }
  const paid = parseFloat($('#paid').value) || 0;
  const diff = paid - total;
  if (!paid) { box.className = 'change'; box.innerHTML = '<span>Cambio</span><strong>$0.00</strong>'; }
  else if (diff < -0.001) { box.className = 'change bad'; box.innerHTML = `<span>Faltan</span><strong>${money(-diff)}</strong>`; }
  else { box.className = 'change ok'; box.innerHTML = `<span>Cambio</span><strong>${money(diff)}</strong>`; }
  $('#btnConfirm').disabled = !(paid >= total - 0.001 && total > 0);
}

$('#paid').addEventListener('input', updateChange);
$('#paid').addEventListener('keydown', (e) => { if (e.key === 'Enter' && !$('#btnConfirm').disabled) $('#btnConfirm').click(); });
$('#btnBack').onclick = () => closeModal('#payModal');

$('#btnConfirm').onclick = async () => {
  const button = $('#btnConfirm');
  button.disabled = true;
  button.textContent = 'Guardando…';

  try {
    const { sale } = await api('/api/sales', {
      method: 'POST',
      body: {
        payment_method: state.method,
        paid: state.method === 'efectivo' ? parseFloat($('#paid').value) : null,
        items: [...state.order].map(([id, qty]) => ({ id, qty })),
        order_id: state.pendingOrder?.id ?? null,
      },
    });

    state.lastSale = sale;
    state.pendingOrder = null;
    loadOrders().catch(() => {});
    state.salesThisHour++;
    state.order.clear();
    $('#order').classList.remove('is-open');

    $('#doneTitle').textContent = `¡Venta ${folio(sale.folio)} registrada!`;
    $('#doneChange').innerHTML = sale.payment_method === 'efectivo'
      ? `Entrega de cambio<strong>${money(sale.change)}</strong>`
      : `Pagado con tarjeta<strong>${money(sale.total)}</strong>`;
    $('#payStep').hidden = true;
    $('#doneStep').hidden = false;
    setTimeout(() => $('#btnNewSale').focus(), 60);

    loadCatalog().catch(() => {});
  } catch (error) {
    toast(error.message, { error: true });
    loadCatalog().catch(() => {});
    loadOrders().catch(() => {});
    updateChange();
  } finally {
    button.textContent = 'Confirmar cobro';
  }
};

$('#btnPrintTicket').onclick = () => state.lastSale && printTicket(state.lastSale);
$('#btnNewSale').onclick = () => closeModal('#payModal');

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    if ($('#payModal').classList.contains('is-on')) closeModal('#payModal');
    if ($('#askModal').classList.contains('is-on')) $('#askNo').click();
  }
});

/* ==========================================================
   ADMINISTRACIÓN
   ========================================================== */
async function loadAdmin() {
  state.admin = await api('/api/admin/dashboard');
  renderAdmin();
}

function renderAdmin() {
  const a = state.admin;
  if (!a) return;
  renderSummary(a);
  renderCuts(a);
  renderInventory(a);
  renderSales(a);
  renderSettings(a);
}

/* ---------- Resumen ---------- */
function renderSummary(a) {
  const t = a.today;
  const lowStock = a.products.filter((p) => p.is_active && p.stock <= 5).length;

  $('#kpis').innerHTML = `
    <div class="kpi accent"><span>Vendido hoy</span><b>${money(t.total)}</b></div>
    <div class="kpi"><span>Tickets</span><b>${t.sales_count}</b></div>
    <div class="kpi"><span>Ticket promedio</span><b>${money(t.summary.average_ticket)}</b></div>
    <div class="kpi"><span>💵 Efectivo</span><b>${money(t.cash_total)}</b></div>
    <div class="kpi"><span>💳 Tarjeta</span><b>${money(t.card_total)}</b></div>
    <div class="kpi"><span>Stock bajo</span><b style="color:${lowStock ? 'var(--chile)' : 'inherit'}">${lowStock}</b></div>`;

  const nowHour = new Date().getHours();
  const hours = t.summary.hours;
  const byHour = Object.fromEntries(hours.map((h) => [h.hour, h]));
  const minHour = Math.min(nowHour, ...hours.map((h) => h.hour));
  let maxHour = Math.max(nowHour, ...hours.map((h) => h.hour));
  const start = Math.max(0, Math.min(minHour, maxHour - 5));
  const peak = Math.max(1, ...hours.map((h) => h.total));

  if (!hours.length) {
    $('#hourChart').innerHTML = '<p class="chart-empty">Las ventas de hoy aparecerán aquí por hora.</p>';
  } else {
    const bars = [];
    for (let h = start; h <= maxHour; h++) {
      const item = byHour[h];
      const height = item ? Math.max(3, (item.total / peak) * 100) : 0;
      bars.push(`<div class="bar ${h === nowHour ? 'is-now' : ''}" title="${hourLabel(h)} · ${item?.sales_count ?? 0} tickets">
        <em>${item ? money(item.total).replace('.00', '') : ''}</em><i style="height:${height}%"></i><small>${pad(h)}h</small></div>`);
    }
    $('#hourChart').innerHTML = bars.join('');
  }
  $('#chartNote').textContent = hours.length ? `Mejor hora: ${hourLabel([...hours].sort((x, y) => y.total - x.total)[0].hour)}` : '';

  const top = t.summary.products.slice(0, 6);
  const topQty = Math.max(1, ...top.map((p) => p.quantity));
  $('#topProducts').innerHTML = top.length ? top.map((p, i) => `<li>
      <span class="pos">${i + 1}</span>
      <div>${esc(p.name)}<div class="meter"><i style="width:${(p.quantity / topQty) * 100}%"></i></div></div>
      <b>${p.quantity}</b></li>`).join('') : '<li class="muted">Aún no hay ventas hoy.</li>';

  const h = a.current_hour;
  $('#liveHour').innerHTML = `
    <span class="dot"></span>
    <div class="grow"><h3>Hora en curso · ${hourLabel(nowHour)} a ${hourLabel((nowHour + 1) % 24)}</h3>
    <p>El corte de esta hora se guarda solo a las ${hourLabel((nowHour + 1) % 24)}.</p></div>
    <div class="nums">
      <div><b>${money(h.total)}</b><span>vendido</span></div>
      <div><b>${h.sales_count}</b><span>tickets</span></div>
    </div>
    <button class="btn btn-ghost btn-sm" type="button" id="btnPrintLive">🖨️ Corte parcial</button>`;
  $('#btnPrintLive').onclick = () => printCut({ ...h, type: 'hourly', partial: true });
}

/* ---------- Cortes ---------- */
function renderCuts(a) {
  const t = a.today;
  const closed = a.today_close;
  $('#dayLabel').textContent = dateOf(new Date(), { weekday: 'long', day: 'numeric', month: 'long' });
  $('#dayStatus').className = 'pill' + (closed ? ' ok' : '');
  $('#dayStatus').textContent = closed ? `Cerrado a las ${timeOf(closed.closed_at)}` : 'Caja abierta';

  $('#dayTotals').innerHTML = `
    <div class="wide"><span>Total del día</span><b>${money(t.total)}</b></div>
    <div><span>💵 Ventas en efectivo</span><b>${money(t.cash_total)}</b></div>
    <div><span>💳 Ventas con tarjeta</span><b>${money(t.card_total)}</b></div>
    <div><span>Tickets</span><b>${t.sales_count}</b></div>
    <div><span>Folios</span><b>${t.summary.first_folio ? `${folio(t.summary.first_folio)}–${folio(t.summary.last_folio)}` : '—'}</b></div>`;

  if (document.activeElement !== $('#openingCash')) {
    $('#openingCash').value = closed?.opening_cash ?? a.settings.opening_cash ?? 0;
  }
  if (closed && document.activeElement !== $('#countedCash') && !$('#countedCash').value) {
    $('#countedCash').value = closed.counted_cash ?? '';
  }
  updateCashCheck();

  const nowHour = new Date().getHours();
  const live = `<div class="cut is-live">
      <div class="when">${hourLabel(nowHour)}<small>en curso</small></div>
      <div class="meta">${a.current_hour.sales_count} tickets</div>
      <div class="amt">${money(a.current_hour.total)}</div>
      <button class="btn btn-ghost btn-sm" type="button" data-live>🖨️</button></div>`;

  $('#hourlyCuts').innerHTML = live + (a.hourly_cuts.length ? a.hourly_cuts.map((c) => {
    const isToday = new Date(c.period_start).toDateString() === new Date().toDateString();
    return `<div class="cut">
      <div class="when">${timeOf(c.period_start)}<small>${isToday ? 'hoy' : 'ayer'} · hasta ${hourLabel((new Date(c.period_start).getHours() + 1) % 24)}</small></div>
      <div class="meta">${c.sales_count} tickets · 💵 ${money(c.cash_total)} · 💳 ${money(c.card_total)}</div>
      <div class="amt">${money(c.total)}</div>
      <button class="btn btn-ghost btn-sm" type="button" data-cut="${c.id}" aria-label="Imprimir corte">🖨️</button></div>`;
  }).join('') : '<p class="muted" style="padding:10px">Los cortes aparecerán aquí al terminar cada hora con ventas.</p>');

  $('#dailyCuts').innerHTML = a.daily_cuts.length ? a.daily_cuts.map((c) => {
    const diff = c.difference;
    const diffHtml = diff === null ? '<span class="muted">sin conteo</span>'
      : `<span class="${Math.abs(diff) < 0.01 ? 'diff-ok' : 'diff-bad'}">${diff > 0 ? '+' : ''}${money(diff)}</span>`;
    return `<tr>
      <td>${dateOf(c.period_start, { weekday: 'short', day: '2-digit', month: 'short' })}</td>
      <td>${c.sales_count}</td>
      <td class="num">${money(c.cash_total)}</td>
      <td class="num">${money(c.card_total)}</td>
      <td class="num">${money(c.total)}</td>
      <td class="num">${diffHtml}</td>
      <td><button class="btn btn-ghost btn-sm" type="button" data-cut="${c.id}">🖨️ Imprimir</button></td></tr>`;
  }).join('') : '<tr><td colspan="7" class="empty-row">Todavía no hay cortes del día.</td></tr>';
}

function updateCashCheck() {
  const a = state.admin;
  if (!a) return;
  const opening = parseFloat($('#openingCash').value) || 0;
  const expected = opening + a.today.cash_total;
  const countedRaw = $('#countedCash').value;
  const counted = parseFloat(countedRaw);
  let diffHtml = '<div><span>Diferencia</span><b>—</b></div>';
  if (countedRaw !== '' && !Number.isNaN(counted)) {
    const diff = counted - expected;
    const ok = Math.abs(diff) < 0.01;
    diffHtml = `<div class="${ok ? 'ok' : 'bad'}"><span>${ok ? 'Cuadra ✔' : diff > 0 ? 'Sobrante' : 'Faltante'}</span><b>${money(Math.abs(diff))}</b></div>`;
  }
  $('#cashCheck').innerHTML = `<div><span>Debe haber en caja</span><b>${money(expected)}</b></div>${diffHtml}`;
}

$('#openingCash').addEventListener('input', updateCashCheck);
$('#countedCash').addEventListener('input', updateCashCheck);

$('#closeForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const wasClosed = Boolean(state.admin?.today_close);
  if (!await ask(wasClosed ? 'Ya cerraste hoy. ¿Rehacer el corte del día con estos datos?' : '¿Cerrar la caja del día e imprimir el corte?', 'Sí, cerrar caja', 'Todavía no')) return;
  try {
    const { cut } = await api('/api/admin/cuts/close-day', {
      method: 'POST',
      body: { opening_cash: parseFloat($('#openingCash').value) || 0, counted_cash: parseFloat($('#countedCash').value) || 0 },
    });
    toast('✅ Corte del día guardado');
    await loadAdmin();
    printCut(cut);
  } catch (error) {
    toast(error.message, { error: true });
  }
});

$('#panel-cuts').addEventListener('click', (e) => {
  const button = e.target.closest('[data-cut], [data-live]');
  if (!button) return;
  if (button.hasAttribute('data-live')) return printCut({ ...state.admin.current_hour, type: 'hourly', partial: true });
  const id = Number(button.dataset.cut);
  const cut = [...state.admin.hourly_cuts, ...state.admin.daily_cuts].find((c) => c.id === id);
  if (cut) printCut(cut);
});

/* ---------- Inventario ---------- */
function renderInventory(a) {
  if ($('#inventory').contains(document.activeElement)) return;
  $('#catList').innerHTML = a.categories.map((c) => `<option value="${esc(c)}">`).join('');
  $('#inventory').innerHTML = a.products.length ? a.products.map((p) => `<tr class="${p.is_active ? '' : 'is-off'}">
    <td><input class="emoji-cell" data-id="${p.id}" data-f="emoji" value="${esc(p.emoji)}" maxlength="4" aria-label="Emoji"></td>
    <td><input data-id="${p.id}" data-f="name" value="${esc(p.name)}" aria-label="Nombre"></td>
    <td><input class="desc-cell" data-id="${p.id}" data-f="description" value="${esc(p.description)}" aria-label="Descripción"></td>
    <td><input data-id="${p.id}" data-f="category" value="${esc(p.category?.name)}" list="catList" aria-label="Categoría"></td>
    <td><input data-id="${p.id}" data-f="price" type="number" min="0" step="0.5" value="${p.price}" aria-label="Precio"></td>
    <td><input data-id="${p.id}" data-f="calories" type="number" min="0" value="${p.calories ?? ''}" aria-label="Calorías"></td>
    <td><input data-id="${p.id}" data-f="stock" type="number" min="0" value="${p.stock}" class="${p.stock <= 5 ? 'is-low' : ''}" aria-label="Stock"></td>
    <td><button type="button" class="btn btn-ghost btn-sm" data-toggle="${p.id}">${p.is_active ? '✅ A la venta' : '🚫 Oculto'}</button></td>
  </tr>`).join('') : '<tr><td colspan="8" class="empty-row">No hay productos.</td></tr>';
}

$('#inventory').addEventListener('change', async (e) => {
  const input = e.target.closest('input[data-f]');
  if (!input) return;
  const field = input.dataset.f;
  let value = input.value.trim();
  if (field === 'price') value = parseFloat(value) || 0;
  if (field === 'stock') value = parseInt(value, 10) || 0;
  if (field === 'calories') value = value === '' ? null : parseInt(value, 10) || 0;
  try {
    await api(`/api/admin/products/${input.dataset.id}`, { method: 'PATCH', body: { [field]: value } });
    toast('Guardado ✔', { timeout: 1500 });
    await Promise.all([loadAdmin(), loadCatalog()]);
  } catch (error) {
    toast(error.message, { error: true });
    renderInventory(state.admin);
  }
});

$('#inventory').addEventListener('click', async (e) => {
  const button = e.target.closest('[data-toggle]');
  if (!button) return;
  const product = state.admin.products.find((p) => p.id === Number(button.dataset.toggle));
  try {
    await api(`/api/admin/products/${product.id}`, { method: 'PATCH', body: { is_active: !product.is_active } });
    await Promise.all([loadAdmin(), loadCatalog()]);
  } catch (error) {
    toast(error.message, { error: true });
  }
});

$('#addForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  try {
    await api('/api/admin/products', {
      method: 'POST',
      body: {
        emoji: $('#nEmoji').value.trim(),
        name: $('#nName').value.trim(),
        description: $('#nDesc').value.trim() || null,
        calories: $('#nKcal').value === '' ? null : parseInt($('#nKcal').value, 10),
        category: $('#nCat').value.trim(),
        price: parseFloat($('#nPrice').value),
        stock: parseInt($('#nStock').value, 10) || 0,
      },
    });
    e.target.reset();
    toast('Producto agregado ✔');
    await Promise.all([loadAdmin(), loadCatalog()]);
  } catch (error) {
    toast(error.message, { error: true });
  }
});

/* ---------- Ventas ---------- */
function renderSales(a) {
  if (!$('#exFrom').value) $('#exFrom').value = todayIso();
  if (!$('#exTo').value) $('#exTo').value = todayIso();

  $('#salesTable').innerHTML = a.sales.length ? a.sales.map((s) => `<tr>
    <td class="num">${folio(s.folio)}</td>
    <td>${timeOf(s.created_at)}</td>
    <td><span class="tag ${s.payment_method === 'tarjeta' ? 'card' : 'cash'}">${s.payment_method === 'tarjeta' ? '💳 Tarjeta' : '💵 Efectivo'}</span></td>
    <td>${s.items.map((i) => `${i.quantity}× ${esc(i.product_name)}`).join(', ')}</td>
    <td class="num">${money(s.total)}</td>
    <td><button type="button" class="btn btn-ghost btn-sm" data-sale="${s.id}" aria-label="Reimprimir ticket">🖨️</button></td></tr>`).join('')
    : '<tr><td colspan="6" class="empty-row">Aún no hay ventas hoy.</td></tr>';
}

$('#salesTable').addEventListener('click', (e) => {
  const button = e.target.closest('[data-sale]');
  const sale = button && state.admin.sales.find((s) => s.id === Number(button.dataset.sale));
  if (sale) printTicket(sale);
});

$('#exportForm').addEventListener('submit', (e) => {
  e.preventDefault();
  const params = new URLSearchParams({ from: $('#exFrom').value || todayIso(), to: $('#exTo').value || todayIso() });
  window.location.href = `/api/admin/sales/export?${params}`;
});

/* ---------- Ajustes ---------- */
function renderSettings(a) {
  if ($('#settingsForm').contains(document.activeElement)) return;
  $('#sName').value = a.settings.business_name;
  $('#sFooter').value = a.settings.ticket_footer;
  $('#sOpening').value = a.settings.opening_cash;
  $('#sPin').value = '';
}

$('#settingsForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const body = {
    business_name: $('#sName').value.trim(),
    ticket_footer: $('#sFooter').value.trim(),
    opening_cash: parseFloat($('#sOpening').value) || 0,
  };
  if ($('#sPin').value) body.business_pin = $('#sPin').value;
  try {
    await api('/api/admin/settings', { method: 'PATCH', body });
    toast(body.business_pin ? 'Ajustes y PIN guardados ✔' : 'Ajustes guardados ✔');
    $('#sPin').value = '';
    await Promise.all([loadAdmin(), loadCatalog()]);
  } catch (error) {
    toast(error.message, { error: true });
  }
});

/* ==========================================================
   IMPRESIÓN (impresora térmica de 58/80 mm o PDF)
   ========================================================== */
const row = (left, right, cls = '') => `<div class="r ${cls}"><span>${left}</span><span>${right}</span></div>`;

function printHtml(html) {
  $('#print').innerHTML = html;
  window.print();
}

function printTicket(sale) {
  const d = new Date(sale.created_at);
  printHtml(`
    <h1>${esc(state.settings.business_name)}</h1>
    <div class="c">Ticket ${folio(sale.folio)}<br>${d.toLocaleDateString('es-MX')} ${timeOf(sale.created_at)}</div>
    <hr>
    ${sale.items.map((i) => `<div>${i.quantity} x ${esc(i.product_name)}</div>${row(`&nbsp;&nbsp;@ ${money(i.price)}`, money(i.subtotal))}`).join('')}
    <hr>
    ${row('TOTAL', money(sale.total), 'big')}
    ${row(sale.payment_method === 'tarjeta' ? 'Tarjeta' : 'Efectivo', money(sale.paid))}
    ${sale.payment_method === 'efectivo' ? row('Cambio', money(sale.change)) : ''}
    <hr>
    <div class="c">${esc(state.settings.ticket_footer)}</div>`);
}

function printCut(cut) {
  const isDaily = cut.type === 'daily';
  const start = new Date(String(cut.period_start).replace(' ', 'T'));
  const startHour = start.getHours();
  const s = cut.summary || {};
  const title = isDaily ? 'CORTE DE CAJA DEL DÍA' : cut.partial ? 'CORTE PARCIAL (HORA EN CURSO)' : 'CORTE DE CAJA POR HORA';
  const period = isDaily
    ? start.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    : `${start.toLocaleDateString('es-MX')} · ${hourLabel(startHour)} a ${hourLabel((startHour + 1) % 24)}`;

  let cash = '';
  if (isDaily && cut.opening_cash !== null && cut.opening_cash !== undefined) {
    cash = `<hr><h2>ARQUEO DE EFECTIVO</h2>
      ${row('Fondo inicial', money(cut.opening_cash))}
      ${row('+ Ventas efectivo', money(cut.cash_total))}
      ${row('= Debe haber', money(cut.expected_cash), 'b')}
      ${cut.counted_cash !== null ? row('Efectivo contado', money(cut.counted_cash)) : ''}
      ${cut.difference !== null ? row(Math.abs(cut.difference) < 0.01 ? 'Diferencia (cuadra)' : cut.difference > 0 ? 'SOBRANTE' : 'FALTANTE', money(Math.abs(cut.difference)), 'b') : ''}`;
  }

  const hours = isDaily && s.hours?.length
    ? `<hr><h2>VENTAS POR HORA</h2>${s.hours.map((h) => row(`${hourLabel(h.hour)} (${h.sales_count})`, money(h.total))).join('')}`
    : '';

  const products = s.products?.length
    ? `<hr><h2>PRODUCTOS</h2>${s.products.map((p) => row(`${p.quantity} x ${esc(p.name)}`, money(p.total))).join('')}`
    : '';

  printHtml(`
    <h1>${esc(state.settings.business_name)}</h1>
    <h2>${title}</h2>
    <div class="c">${period}</div>
    <hr>
    ${row('Tickets', cut.sales_count)}
    ${s.first_folio ? row('Folios', `${folio(s.first_folio)} a ${folio(s.last_folio)}`) : ''}
    ${row('Ticket promedio', money(s.average_ticket))}
    <hr>
    ${row('Efectivo', money(cut.cash_total))}
    ${row('Tarjeta', money(cut.card_total))}
    ${row('TOTAL VENDIDO', money(cut.total), 'big')}
    ${cash}${hours}${products}
    <hr>
    <div class="c">Impreso ${new Date().toLocaleString('es-MX')}</div>
    ${isDaily ? '<br><br><div class="c">______________________<br>Firma de quien entrega</div>' : ''}`);
}

/* ---------- Arranque ---------- */
tickClock();
setInterval(tickClock, 15000);
setInterval(() => { if (!document.hidden && state.unlocked && $('#admin').classList.contains('is-on')) loadAdmin().catch(() => {}); }, 60000);
setInterval(() => { if (!document.hidden) loadOrders().catch(() => {}); }, 10000);

async function startWork() {
  try {
    await Promise.all([loadCatalog(), loadOrders()]);
  } catch (error) {
    toast(error.message, { error: true });
  }
}

api('/api/admin/session')
  .then(({ unlocked }) => {
    state.unlocked = unlocked;
    if (unlocked) startWork();
    else openPin();
  })
  .catch(() => openPin());
