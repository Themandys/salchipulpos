<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#1D1310">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<title>{{ $settings['business_name'] }} · Punto de venta</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🐙</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Nunito:wght@500;600;700;800&display=swap" rel="stylesheet">
@vite(['resources/css/pos.css', 'resources/js/pos.js'])
</head>
<body>

<header class="topbar">
  <div class="brand">
    <span class="brand-mark" aria-hidden="true">🐙</span>
    <div>
      <strong id="brandName">{{ $settings['business_name'] }}</strong>
      <small>Punto de venta</small>
    </div>
  </div>

  <div class="clock" aria-live="polite">
    <span id="clockTime">--:--</span>
    <small id="clockNext">Próximo corte en -- min</small>
  </div>

  <button id="ordersBtn" class="orders-btn" type="button">📥 <span>Pedidos</span> <b id="ordersCount">0</b></button>

  <nav class="tabs" aria-label="Secciones">
    <button class="tab is-on" data-view="sell" type="button">🛒 <span>Vender</span></button>
    <button class="tab" data-view="admin" type="button">⚙️ <span>Administrar</span></button>
  </nav>
</header>

{{-- ====== VENDER ====== --}}
<main id="sell" class="view is-on">
  <section class="menu">
    <div class="menu-tools">
      <label class="search">
        <span aria-hidden="true">🔎</span>
        <input id="search" type="search" placeholder="Buscar producto…" autocomplete="off">
      </label>
      <div id="cats" class="cats" role="tablist"></div>
    </div>
    <div id="grid" class="grid"></div>
  </section>

  <aside id="order" class="order" aria-label="Pedido">
    <div class="order-head">
      <h2>Pedido</h2>
      <button id="btnClear" class="link-btn" type="button">Vaciar</button>
    </div>
    <div id="orderFrom" class="order-from" hidden></div>
    <div id="lines" class="lines"></div>
    <div class="order-foot">
      <div class="total-row"><span>Total</span><strong id="total">$0.00</strong></div>
      <button id="btnPay" class="btn btn-primary btn-xl" type="button" disabled>Cobrar</button>
    </div>
  </aside>

  <button id="orderToggle" class="order-toggle" type="button">
    <span><b id="orderCount">0</b> productos</span>
    <strong id="orderToggleTotal">$0.00</strong>
  </button>
</main>

{{-- ====== ADMINISTRAR ====== --}}
<main id="admin" class="view">
  <div class="admin">
    <nav class="subtabs" aria-label="Administración">
      <button class="subtab is-on" data-panel="summary" type="button">📊 Resumen</button>
      <button class="subtab" data-panel="cuts" type="button">🧾 Cortes de caja</button>
      <button class="subtab" data-panel="inventory" type="button">📦 Inventario</button>
      <button class="subtab" data-panel="sales" type="button">💳 Ventas</button>
      <button class="subtab" data-panel="settings" type="button">⚙️ Ajustes</button>
      <a href="{{ route('menu') }}" class="subtab subtab-lock" target="_blank" rel="noopener">👀 Ver menú</a>
      <button id="btnLock" class="subtab" type="button">🔒 Cerrar sesión</button>
    </nav>

    {{-- Resumen --}}
    <section id="panel-summary" class="panel is-on">
      <div id="kpis" class="kpis"></div>
      <div class="cards-2">
        <article class="card">
          <header class="card-head"><h3>Ventas por hora</h3><span class="muted" id="chartNote"></span></header>
          <div id="hourChart" class="hour-chart"></div>
        </article>
        <article class="card">
          <header class="card-head"><h3>Lo más vendido hoy</h3></header>
          <ol id="topProducts" class="rank"></ol>
        </article>
      </div>
      <article class="card live-hour" id="liveHour"></article>
    </section>

    {{-- Cortes --}}
    <section id="panel-cuts" class="panel">
      <div class="cards-2 cards-cuts">
        <article class="card day-close">
          <header class="card-head">
            <div><h3>Corte del día</h3><span class="muted" id="dayLabel"></span></div>
            <span id="dayStatus" class="pill"></span>
          </header>
          <div id="dayTotals" class="day-totals"></div>
          <form id="closeForm" class="close-form" autocomplete="off">
            <label>Fondo inicial de caja
              <input id="openingCash" type="number" inputmode="decimal" min="0" step="0.5" required>
            </label>
            <label>Efectivo contado en caja
              <input id="countedCash" type="number" inputmode="decimal" min="0" step="0.5" placeholder="Cuenta billetes y monedas" required>
            </label>
            <div id="cashCheck" class="cash-check"></div>
            <button class="btn btn-primary btn-lg" type="submit">Cerrar caja e imprimir corte</button>
            <p class="hint">Si no cierras la caja, el sistema guarda el corte del día automáticamente después de medianoche.</p>
          </form>
        </article>

        <article class="card">
          <header class="card-head">
            <div><h3>Cortes por hora</h3><span class="muted">Se generan solos al terminar cada hora</span></div>
          </header>
          <div id="hourlyCuts" class="cut-list"></div>
        </article>
      </div>

      <article class="card">
        <header class="card-head"><h3>Historial de cortes del día</h3></header>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Fecha</th><th>Tickets</th><th>Efectivo</th><th>Tarjeta</th><th>Total</th><th>Diferencia</th><th></th></tr></thead>
            <tbody id="dailyCuts"></tbody>
          </table>
        </div>
      </article>
    </section>

    {{-- Inventario --}}
    <section id="panel-inventory" class="panel">
      <article class="card">
        <header class="card-head"><h3>Agregar producto</h3></header>
        <form id="addForm" class="add-form" autocomplete="off">
          <input id="nEmoji" placeholder="🌭" maxlength="4" aria-label="Emoji" class="emoji-input">
          <input id="nName" placeholder="Nombre del producto" required>
          <input id="nDesc" placeholder="Descripción breve" maxlength="255" class="wide-input">
          <input id="nKcal" type="number" min="0" placeholder="kcal">
          <input id="nCat" placeholder="Categoría" list="catList" required>
          <datalist id="catList"></datalist>
          <input id="nPrice" type="number" min="0" step="0.5" placeholder="Precio" required>
          <input id="nStock" type="number" min="0" placeholder="Stock" required>
          <button class="btn btn-primary" type="submit">Agregar</button>
        </form>
      </article>
      <article class="card">
        <header class="card-head"><h3>Inventario y precios</h3><span class="muted">Los cambios se guardan al salir de cada campo</span></header>
        <div class="table-wrap">
          <table class="table table-inv">
            <thead><tr><th></th><th>Producto</th><th>Descripción</th><th>Categoría</th><th>Precio</th><th>kcal</th><th>Stock</th><th>Estado</th></tr></thead>
            <tbody id="inventory"></tbody>
          </table>
        </div>
      </article>
    </section>

    {{-- Ventas --}}
    <section id="panel-sales" class="panel">
      <article class="card">
        <header class="card-head">
          <h3>Ventas de hoy</h3>
          <form id="exportForm" class="export-form">
            <input id="exFrom" type="date" aria-label="Desde">
            <input id="exTo" type="date" aria-label="Hasta">
            <button class="btn btn-ghost" type="submit">⬇️ Descargar CSV</button>
          </form>
        </header>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Folio</th><th>Hora</th><th>Método</th><th>Productos</th><th>Total</th><th></th></tr></thead>
            <tbody id="salesTable"></tbody>
          </table>
        </div>
      </article>
    </section>

    {{-- Ajustes --}}
    <section id="panel-settings" class="panel">
      <article class="card narrow">
        <header class="card-head"><h3>Ajustes del negocio</h3></header>
        <form id="settingsForm" class="settings-form" autocomplete="off">
          <label>Nombre en el ticket <input id="sName" maxlength="80" required></label>
          <label>Mensaje al final del ticket <input id="sFooter" maxlength="160"></label>
          <label>Fondo de caja habitual <input id="sOpening" type="number" min="0" step="0.5"></label>
          <label>Nuevo PIN (déjalo vacío para no cambiarlo) <input id="sPin" inputmode="numeric" pattern="[0-9]{4,8}" maxlength="8"></label>
          <button class="btn btn-primary" type="submit">Guardar ajustes</button>
        </form>
      </article>
    </section>
  </div>
</main>

{{-- ====== MODAL: COBRO ====== --}}
<div id="payModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="payTitle">
  <div class="sheet">
    <div id="payStep">
      <p class="eyebrow" id="payTitle">Total a cobrar</p>
      <div id="payTotal" class="huge">$0.00</div>

      <div class="segmented" role="radiogroup" aria-label="Método de pago">
        <button type="button" class="seg is-on" data-method="efectivo">💵 Efectivo</button>
        <button type="button" class="seg" data-method="tarjeta">💳 Tarjeta</button>
      </div>

      <div id="cashFields">
        <label class="eyebrow" for="paid">¿Con cuánto pagó?</label>
        <input id="paid" class="money-input" type="number" inputmode="decimal" min="0" step="0.5" placeholder="0.00">
        <div id="quick" class="quick"></div>
      </div>

      <div id="changeBox" class="change"></div>

      <div class="sheet-actions">
        <button id="btnBack" class="btn btn-ghost" type="button">Volver</button>
        <button id="btnConfirm" class="btn btn-primary btn-lg" type="button" disabled>Confirmar cobro</button>
      </div>
    </div>

    <div id="doneStep" class="done" hidden>
      <div class="done-icon" aria-hidden="true">✅</div>
      <h3 id="doneTitle">¡Venta registrada!</h3>
      <div id="doneChange" class="done-change"></div>
      <div class="sheet-actions">
        <button id="btnPrintTicket" class="btn btn-ghost" type="button">🖨️ Imprimir ticket</button>
        <button id="btnNewSale" class="btn btn-primary btn-lg" type="button">Nueva venta</button>
      </div>
    </div>
  </div>
</div>

{{-- ====== MODAL: PIN ====== --}}
<div id="pinModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="pinTitle">
  <div class="sheet sheet-pin">
    <p class="eyebrow" id="pinTitle">Acceso del personal</p>
    <h3>Ingresa tu PIN</h3>
    <div id="pinDots" class="pin-dots"></div>
    <p id="pinError" class="pin-error" role="alert"></p>
    <div id="pinPad" class="pin-pad"></div>
    <a href="{{ route('menu') }}" class="btn btn-ghost">← Volver al menú</a>
  </div>
</div>

{{-- ====== MODAL: PEDIDOS DE CLIENTES ====== --}}
<div id="ordersModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="ordersTitle">
  <div class="sheet sheet-orders">
    <div class="orders-head">
      <h3 id="ordersTitle">Pedidos de clientes</h3>
      <button id="ordersClose" class="link-btn" type="button">Cerrar</button>
    </div>
    <div id="ordersList" class="orders-list"></div>
  </div>
</div>

{{-- ====== MODAL: CONFIRMAR ====== --}}
<div id="askModal" class="modal" role="alertdialog" aria-modal="true">
  <div class="sheet sheet-ask">
    <p id="askText"></p>
    <div class="sheet-actions">
      <button id="askNo" class="btn btn-ghost" type="button">No</button>
      <button id="askYes" class="btn btn-primary" type="button">Sí</button>
    </div>
  </div>
</div>

<div id="toasts" class="toasts" aria-live="polite"></div>

{{-- Solo visible al imprimir (ticket o corte) --}}
<div id="print" class="print-area"></div>

</body>
</html>
