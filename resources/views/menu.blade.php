<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#FBF6EC">
<meta name="description" content="Menú de Salchipulpos: salchipulpos clásicos, BBQ, spicy, deluxe y combos. Arma tu pedido y paga en caja.">
<title>Salchipulpos · Menú</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🐙</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Archivo:wght@500;700;800&display=swap" rel="stylesheet">
@vite(['resources/css/menu.css', 'resources/js/menu.js'])
</head>
<body>

<header class="bar">
  <a href="{{ route('menu') }}" class="bar-logo">🐙 Salchipulpos</a>
  <div class="bar-actions">
    <button id="cartOpen" class="bar-cart" type="button">🛒 Mi pedido <b id="cartCount">0</b></button>
    <a href="{{ route('pos') }}" class="bar-staff" title="Acceso para el personal">👤 Personal</a>
  </div>
</header>

<main class="poster">
  <section class="hero">
    <div class="hero-text">
      <h1>Salchipulpos</h1>
      <p class="tagline"><span></span>Alegría en tu boca<span></span></p>
      <a href="#menu" class="hero-cta">Arma tu pedido ↓</a>
    </div>
    <img class="hero-img" src="{{ asset('images/salchipulpos-hero.jpg') }}" alt="Charola de salchipulpos con papas, queso y jalapeños" width="450" height="480">
  </section>

  <div id="menu"></div>
  @forelse ($categories as $category)
    @php($asCards = preg_match('/extra|topping/i', $category->name) === 1)
    <section class="section">
      <h2 class="brush"><span>{{ $category->name }}</span></h2>

      @if ($asCards)
        <div class="extras">
          @foreach ($category->products as $product)
            <button type="button" class="extra js-product" data-id="{{ $product->id }}">
              @php([$extraName, $extraPortion] = array_pad(explode(' (', $product->name, 2), 2, null))
              <span class="extra-name"><span>{{ $extraName }}@if ($extraPortion)<br>({{ $extraPortion }}@endif</span></span>
              <span class="extra-sep"></span>
              <span class="extra-price">+${{ number_format($product->price, 0) }} MXN</span>
              @if ($product->calories)
                <span class="extra-kcal">[ +{{ $product->calories }} kcal ]</span>
              @endif
              @if ($product->stock <= 0)
                <span class="sold-out">Agotado</span>
              @endif
            </button>
          @endforeach
        </div>
      @else
        <div class="items">
          @foreach ($category->products as $product)
            <button type="button" class="item js-product {{ $product->stock <= 0 ? 'is-out' : '' }}" data-id="{{ $product->id }}">
              <span class="item-head">
                <span class="item-name">{{ $product->name }}</span>
                <span class="item-dots" aria-hidden="true"></span>
                <span class="item-price">${{ number_format($product->price, 0) }} MXN</span>
              </span>
              <span class="item-body">
                <span class="item-desc">{{ $product->description }}</span>
                @if ($product->calories)
                  <span class="item-kcal">~{{ $product->calories }} kcal</span>
                @endif
              </span>
              <span class="item-hint">{{ $product->stock <= 0 ? 'Agotado por hoy' : 'Toca para ver y agregar' }}</span>
            </button>
          @endforeach
        </div>
      @endif
    </section>
  @empty
    <p class="no-menu">Estamos preparando el menú. ¡Vuelve en un momento!</p>
  @endforelse

  <aside class="salsas">
    <p><b>Barra de salsas libre:</b> Cátsup, mostaza, salsa Botanera y Valentina.</p>
    <hr>
    <small>* Los valores calóricos son aproximados por porción promedio.</small>
  </aside>
</main>

<button id="cartBar" class="cart-bar" type="button" hidden>
  <span id="cartBarCount">0 productos</span>
  <strong id="cartBarTotal">$0</strong>
</button>

{{-- Info del producto --}}
<div id="productModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="pmName">
  <div class="sheet product-sheet">
    <button class="sheet-close" type="button" data-close aria-label="Cerrar">✕</button>
    <div class="pm-emoji" id="pmEmoji"></div>
    <h3 id="pmName"></h3>
    <p id="pmDesc" class="pm-desc"></p>
    <div class="pm-facts">
      <span id="pmPrice" class="pm-price"></span>
      <span id="pmKcal" class="pm-kcal"></span>
    </div>
    <div class="pm-buy">
      <div class="stepper">
        <button type="button" id="pmMinus" aria-label="Menos">−</button>
        <span id="pmQty">1</span>
        <button type="button" id="pmPlus" aria-label="Más">+</button>
      </div>
      <button type="button" id="pmAdd" class="btn-red">Agregar</button>
    </div>
  </div>
</div>

{{-- Carrito --}}
<div id="cartModal" class="modal modal-side" role="dialog" aria-modal="true" aria-labelledby="cartTitle">
  <div class="drawer">
    <div class="drawer-head">
      <h3 id="cartTitle">Mi pedido</h3>
      <button class="sheet-close" type="button" data-close aria-label="Cerrar">✕</button>
    </div>

    <div id="cartStep" class="drawer-body">
      <div id="cartLines" class="cart-lines"></div>
      <form id="orderForm" class="order-form" autocomplete="off">
        <label>Tu nombre <input id="customerName" maxlength="60" placeholder="Para llamarte cuando esté listo" required></label>
        <label>Notas (opcional) <input id="customerNote" maxlength="200" placeholder="Ej. sin jalapeños"></label>
        <div class="cart-total"><span>Total</span><strong id="cartTotal">$0</strong></div>
        <button id="orderSend" class="btn-red btn-block" type="submit">Enviar pedido</button>
        <p class="fine">Pagas en caja al recoger tu pedido.</p>
      </form>
    </div>

    <div id="sentStep" class="drawer-body sent" hidden>
      <p class="sent-label">Tu número de pedido</p>
      <div id="sentNumber" class="sent-number">#0</div>
      <p id="sentStatus" class="sent-status"></p>
      <div id="sentLines" class="sent-lines"></div>
      <button id="newOrder" class="btn-ghost btn-block" type="button">Hacer otro pedido</button>
    </div>
  </div>
</div>

<div id="toasts" class="toasts" aria-live="polite"></div>

<script type="application/json" id="menuData">@json($menuProducts)</script>
</body>
</html>
