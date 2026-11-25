const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[char]));
const formatNumber = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 });
const money = (amount) => `$${formatNumber.format(amount)} MXN`;
const kcal = (amount) => `~${formatNumber.format(amount)} kcal`;

const { products, addons, drinkId, ordersUrl } = JSON.parse($('#menuData').textContent);
const byId = new Map([...products, ...addons].map((product) => [product.id, product]));
const drink = byId.get(drinkId);
const storageKey = 'b_crunch_menu';
const productModal = $('#productModal');
const cartModal = $('#cartModal');
const state = { cart: [], current: null, editing: null, order: null, sending: false };

function save() {
  try {
    localStorage.setItem(storageKey, JSON.stringify({
      cart: state.cart,
      name: $('#customerName').value,
      order: state.order?.uuid,
    }));
  } catch { /* Storage can be disabled. */ }
}

function restore() {
  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
    const legacy = JSON.parse(localStorage.getItem('salchipulpos_menu') || '{}');
    const legacyCart = (legacy.cart ?? []).flatMap(([id, qty]) => {
      const product = byId.get(Number(id));
      if (!products.includes(product) || product.stock <= 0) return [];
      return [{ id: product.id, qty: Math.min(qty, product.stock), choices: defaults(product), combo: null, extras: [] }];
    });
    state.cart = (saved.cart ?? legacyCart).filter((line) => byId.has(line.id))
      .map((line) => ({
        ...line,
        extras: line.extras.filter((extra) => byId.has(extra.id)),
        combo: drink ? line.combo : null,
      }));
    $('#customerName').value = saved.name ?? legacy.name ?? '';
    if (saved.order ?? legacy.order) trackOrder(saved.order ?? legacy.order);
  } catch { /* Use an empty cart if the stored data cannot be read. */ }
}

function open(dialog) {
  if (!dialog.open) dialog.showModal();
}

$$('dialog').forEach((dialog) => {
  dialog.addEventListener('click', (event) => {
    if (event.target.closest('[data-close]')) dialog.close();
    else if (event.target === dialog) {
      const box = dialog.getBoundingClientRect();
      if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
    }
  });
});
productModal.addEventListener('close', () => {
  if (state.editing !== null) {
    state.editing = null;
    showCart();
  }
});

$$('[data-filter]').forEach((button) => button.addEventListener('click', () => {
  const filter = button.dataset.filter;
  $$('[data-filter]').forEach((tab) => tab.setAttribute('aria-pressed', String(tab === button)));
  $$('[data-category]').forEach((section) => { section.hidden = filter !== 'all' && section.dataset.category !== filter; });
}));

function defaults(product) {
  return Object.fromEntries(Object.entries(product.menu_options?.choices ?? {}).map(([label, options]) => [label, options[0]]));
}

function describe(line) {
  const product = byId.get(line.id);
  const parts = Object.entries(line.choices).map(([label, value]) => `${label}: ${value}`);
  const supplements = line.extras.map((extra) => {
    const addon = byId.get(extra.id);
    parts.push([addon.name, ...Object.values(extra.choices)].join(' · '));
    return addon;
  });

  if (line.combo) {
    parts.push(`Hazlo Combo: ${line.combo} (355 ml)`);
    supplements.push(drink);
  }

  const ingredients = [product, ...supplements];
  return {
    product,
    parts,
    price: ingredients.reduce((sum, item) => sum + Math.round(item.price * 100), 0) / 100,
    calories: ingredients.every((item) => item.calories !== null)
      ? ingredients.reduce((sum, item) => sum + item.calories, 0) : null,
  };
}

function stockIssue(lines) {
  const counts = new Map();

  for (const line of lines) {
    const ids = [line.id, ...line.extras.map((extra) => extra.id)];
    if (line.combo || byId.get(line.id).menu_options?.includes_drink) {
      if (!drink) return 'La bebida del combo está agotada.';
      ids.push(drink.id);
    }
    for (const id of ids) counts.set(id, (counts.get(id) ?? 0) + line.qty);
  }

  for (const [id, count] of counts) {
    const product = byId.get(id);
    if (count > product.stock) return `Disponibles de ${product.name}: ${product.stock}.`;
  }
  return '';
}

function selectOptions(values, selected) {
  return values.map((value) => `<option ${value === selected ? 'selected' : ''}>${esc(value)}</option>`).join('');
}

function choicesMarkup(product, selected, dataset = 'choice') {
  return Object.entries(product.menu_options?.choices ?? {}).map(([label, values]) => `<label>${esc(label)}
    <select data-${dataset}="${esc(label)}">${selectOptions(values, selected[label])}</select>
  </label>`).join('');
}

function renderOptions(product, line) {
  const comboOptions = product.menu_options?.combo && drink ? `
    <label class="combo-choice">Hazlo Combo (+${money(drink.price)})
      <span>Agrega una bebida de 355 ml.</span>
      <select id="pmCombo" ${drink.stock <= 0 ? 'disabled' : ''}>
        <option value="">${drink.stock > 0 ? 'Sin bebida' : 'Agotado'}</option>
        ${selectOptions(drink.menu_options.choices.Bebida, line.combo)}
      </select>
    </label>` : '';

  const extraOptions = addons.length ? `
    <details class="extras" ${line.extras.length ? 'open' : ''}>
      <summary>Extras &amp; Toppings <span id="extrasCount"></span></summary>
      ${addons.map((addon) => {
        const selected = line.extras.find((extra) => extra.id === addon.id);
        return `<div class="extra-option" data-extra="${addon.id}">
          <label class="extra-check">
            <input type="checkbox" ${selected ? 'checked' : ''} ${addon.stock <= 0 && !selected ? 'disabled' : ''}>
            <span>${esc(addon.name)}<small>${addon.stock <= 0 ? 'Agotado' : `+${money(addon.price)} · +${addon.calories} kcal`}</small></span>
          </label>
          <div class="extra-select" ${selected ? '' : 'hidden'}>${choicesMarkup(addon, selected?.choices ?? {}, 'extra-choice')}</div>
        </div>`;
      }).join('')}
    </details>` : '';

  $('#pmOptions').innerHTML = choicesMarkup(product, line.choices) + comboOptions + extraOptions;
}

function openProduct(id, index = null) {
  if (state.sending) return;
  const product = byId.get(id);
  state.editing = index;
  state.current = index === null
    ? { id, qty: 1, choices: defaults(product), combo: null, extras: [] }
    : structuredClone(state.cart[index]);

  if (cartModal.open) cartModal.close();
  $('#pmEmoji').textContent = product.emoji;
  $('#pmName').textContent = product.name;
  $('#pmDesc').textContent = product.description;
  renderOptions(product, state.current);
  previewProduct();
  open(productModal);
}

function previewProduct() {
  const line = state.current;
  const { product, price, calories } = describe(line);
  const others = state.cart.filter((_, index) => index !== state.editing);
  const issue = product.stock <= 0 ? 'Agotado' : stockIssue([...others, line]);

  $('#pmPrice').textContent = money(price * line.qty);
  $('#pmKcal').hidden = calories === null;
  $('#pmKcal').textContent = calories === null ? '' : kcal(calories * line.qty);
  $('#pmQty').textContent = line.qty;
  $('#pmMinus').disabled = line.qty <= 1;
  $('#pmPlus').disabled = line.qty >= Math.min(20, product.stock);
  $('#pmAdd').disabled = Boolean(issue);
  $('#pmAdd').textContent = state.editing === null ? 'Agregar a mi pedido' : 'Guardar cambios';
  $('#pmError').textContent = issue;

  const extraCount = $('#extrasCount');
  if (extraCount) extraCount.textContent = line.extras.length ? `(${line.extras.length})` : '';
}

function readChoices(elements, key) {
  return Object.fromEntries(elements.map((select) => [select.dataset[key], select.value]));
}

$$('.js-product').forEach((card) => card.addEventListener('click', () => openProduct(Number(card.dataset.id))));
$('#pmMinus').onclick = () => { state.current.qty--; previewProduct(); };
$('#pmPlus').onclick = () => { state.current.qty++; previewProduct(); };
$('#pmOptions').addEventListener('change', () => {
  state.current.choices = readChoices($$('[data-choice]'), 'choice');
  state.current.combo = $('#pmCombo')?.value || null;
  state.current.extras = $$('[data-extra]').flatMap((row) => {
    const selected = $('input', row).checked;
    $('.extra-select', row).hidden = !selected;
    return selected ? [{ id: Number(row.dataset.extra), choices: readChoices($$('[data-extra-choice]', row), 'extraChoice') }] : [];
  });
  previewProduct();
});

$('#productForm').addEventListener('submit', (event) => {
  event.preventDefault();
  const line = state.current;
  if (state.editing !== null) {
    state.cart[state.editing] = line;
  } else {
    if (state.cart.length >= 30) {
      $('#pmError').textContent = 'Tu pedido admite hasta 30 partidas.';
      return;
    }
    state.cart.push(line);
  }

  const firstItem = state.cart.length === 1 && state.editing === null;
  state.editing = null;
  productModal.close();
  showCart();
  if (firstItem && !$('#customerName').value) $('#customerName').focus();
});

function renderCart() {
  const lines = state.cart.map((line, index) => {
    const { product, parts, price, calories } = describe(line);
    return {
      qty: line.qty,
      total: Math.round(price * 100) * line.qty,
      calories: calories === null ? null : calories * line.qty,
      html: `<article class="cart-line">
        <div><b>${line.qty} × ${esc(product.name)}</b>${parts.length ? `<p>${parts.map(esc).join(' · ')}</p>` : ''}</div>
        <strong class="price">${money(price * line.qty)}</strong>
        <div class="line-actions">
          <button type="button" class="text-button" data-edit="${index}">Personalizar</button>
          <button type="button" class="text-button muted" data-remove="${index}">Quitar</button>
        </div>
      </article>`,
    };
  });

  const amount = lines.reduce((sum, line) => sum + line.total, 0) / 100;
  const count = lines.reduce((sum, line) => sum + line.qty, 0);
  const calories = lines.every((line) => line.calories !== null) ? lines.reduce((sum, line) => sum + line.calories, 0) : null;

  $('#cartLines').innerHTML = lines.map((line) => line.html).join('') || '<p class="muted empty-cart">Agrega un platillo del menú para empezar.</p>';
  $('#cartTotal').textContent = money(amount);
  $('#cartKcal').textContent = calories === null ? 'Calorías aproximadas no disponibles para todo el pedido' : kcal(calories);
  $('#cartCount').textContent = count;
  $('#cartBar').hidden = !count && state.order?.status !== 'pending';
  $('#cartBarCount').textContent = count ? `Mi pedido · ${count}` : `Ver pedido #${state.order?.number}`;
  $('#cartBarTotal').textContent = money(count ? amount : state.order?.total ?? 0);
  $('#orderForm').hidden = !count;
  $('#orderSend').disabled = !count || state.sending;
  $('#viewPending').hidden = !state.order;
  save();
}

$('#cartLines').addEventListener('click', (event) => {
  if (state.sending) return;
  const remove = event.target.closest('[data-remove]');
  const edit = event.target.closest('[data-edit]');
  if (remove) {
    state.cart.splice(Number(remove.dataset.remove), 1);
    renderCart();
  }
  if (edit) {
    const index = Number(edit.dataset.edit);
    openProduct(state.cart[index].id, index);
  }
});

function showCart() {
  renderCart();
  const showSummary = !state.cart.length && state.order;
  setCartView(Boolean(showSummary));
  open(cartModal);
}

function setCartView(summary) {
  $('#cartTitle').textContent = summary ? 'Pedido enviado' : 'Mi pedido';
  $('#cartStep').hidden = summary;
  $('#sentStep').hidden = !summary;
}

$('#cartOpen').onclick = showCart;
$('#cartBar').onclick = showCart;
$('#viewPending').onclick = () => setCartView(true);
$('#newOrder').onclick = () => cartModal.close();
$('#customerName').addEventListener('input', save);

$('#orderForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!state.cart.length || state.sending) return;

  state.sending = true;
  $('#orderSend').disabled = true;
  $('#orderSend').textContent = 'Enviando…';
  $('#orderError').textContent = '';

  try {
    const response = await fetch(ordersUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').content },
      body: JSON.stringify({
        customer_name: $('#customerName').value.trim(),
        note: $('#customerNote').value.trim() || null,
        items: state.cart,
      }),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const message = data.errors ? Object.values(data.errors).flat()[0] : 'No se pudo enviar el pedido. Vuelve a intentarlo.';
      throw new Error(response.status === 429 ? 'Demasiados pedidos seguidos. Espera un minuto.' : message);
    }

    state.cart = [];
    $('#customerNote').value = '';
    updateOrder(data.order);
    setCartView(true);
  } catch (error) {
    $('#orderError').textContent = error.message;
  } finally {
    state.sending = false;
    $('#orderSend').textContent = 'Enviar pedido';
    renderCart();
  }
});

const statusText = {
  pending: 'Pasa a caja, di tu número y paga ahí.',
  paid: '¡Pagado! Estamos preparando tu pedido.',
  cancelled: 'Este pedido fue cancelado. Pregunta en caja.',
};

function updateOrder(order) {
  state.order = order;
  $('#sentNumber').textContent = '#' + order.number;
  $('#sentStatus').textContent = statusText[order.status] || '';
  $('#sentLines').innerHTML = order.items.map((item) => `<div class="sent-line">
    <div><b>${item.quantity} × ${esc(item.name)}</b>${item.details?.labels?.length ? `<small>${item.details.labels.map(esc).join(' · ')}</small>` : ''}</div>
    <strong>${money(item.subtotal)}</strong></div>`).join('')
    + `<div class="cart-total"><span>${order.status === 'pending' ? 'Total a pagar' : 'Total'}</span><strong>${money(order.total)}</strong></div>`
    + (order.calories === null ? '' : `<p class="cart-kcal">${kcal(order.calories)}</p>`);
  renderCart();
}

async function trackOrder(uuid) {
  try {
    const response = await fetch(`${ordersUrl}/${encodeURIComponent(uuid)}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) return;
    const { order } = await response.json();
    if (!state.order || state.order.uuid === uuid) updateOrder(order);
  } catch { /* Try again on the next status refresh. */ }
}

restore();
renderCart();
setInterval(() => {
  if (!document.hidden && state.order?.status === 'pending') trackOrder(state.order.uuid);
}, 8000);
