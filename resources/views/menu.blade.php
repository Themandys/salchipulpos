<!DOCTYPE html>
<html lang="es-MX">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#F9F7F2">
  <meta name="description" content="B-CRUNCH, sabor que truena. Pide crispy chicken, tenders, salchipulpos, snacks y combos. Personaliza tu pedido y paga en caja.">
  <meta property="og:title" content="B-CRUNCH | Crispy Chicken, Tenders & Salchipulpos">
  <meta property="og:description" content="Sabor que truena. Arma tu pedido con tus salsas y extras favoritos.">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="es_MX">
  <meta property="og:url" content="{{ route('menu') }}">
  <meta property="og:image" content="{{ asset('images/b-crunch-hero.jpg') }}">
  <title>B-CRUNCH | Crispy Chicken, Tenders &amp; Salchipulpos</title>
  <link rel="canonical" href="{{ route('menu') }}">
  <link rel="icon" href="{{ asset('images/b-crunch-icon.svg') }}" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  @vite(['resources/css/menu.css', 'resources/js/menu.js'])
</head>
<body>
<header class="site-header">
  <div class="shell nav">
    <a class="brand" href="{{ route('menu') }}" aria-label="B-CRUNCH, inicio"><img src="{{ asset('images/b-crunch-icon.svg') }}" alt="" width="38" height="38"> B-CRUNCH</a>
    <nav class="nav-links" aria-label="Navegación">
      <a href="#menu">Menú</a>
      <a href="{{ route('pos') }}">Personal</a>
    </nav>
    <button id="cartOpen" class="cart-button" type="button">
      <svg aria-hidden="true" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 8h14l2 13H3L5 8Z"/><path d="M8 10V6a4 4 0 1 1 8 0v4"/></svg>
      <span>Mi pedido</span> <b id="cartCount">0</b>
    </button>
  </div>
</header>

<main class="shell">
  <section class="hero">
    <div class="hero-copy">
      <p class="hero-brand">B-CRUNCH</p>
      <h1>Sabor que<br><em>truena.</em></h1>
      <p class="hero-description">Crispy Chicken, Tenders &amp; Salchipulpos</p>
      <a href="#menu" class="button hero-button">Ver el menú <span aria-hidden="true">↘</span></a>
    </div>
    <img class="hero-image" src="{{ asset('images/b-crunch-hero.jpg') }}" alt="Sándwich de pollo crujiente con tenders, papas y salsa" width="1536" height="1024" fetchpriority="high">
    <div class="checker" aria-hidden="true"></div>
  </section>

  <section id="menu" class="menu-section" aria-labelledby="menuTitle">
    <div class="menu-heading">
      <h2 id="menuTitle">Nuestro menú</h2>
      <p>Elige tu platillo y personalízalo.</p>
    </div>
    @if ($categories->isNotEmpty())
      <div class="filters" role="group" aria-label="Filtrar por categoría">
        <button type="button" data-filter="all" aria-pressed="true">Todo</button>
        @foreach ($categories as $category)
          <button type="button" data-filter="{{ $category->id }}" aria-pressed="false">{{ $category->name }}</button>
        @endforeach
      </div>
    @endif

    @forelse ($categories as $category)
      <section class="category" data-category="{{ $category->id }}">
        <h3>{{ $category->name }}</h3>
        <div class="product-grid">
          @foreach ($category->products as $product)
            <button type="button" class="product-card js-product" data-id="{{ $product->id }}">
              <span class="card-top">
                <span class="food-icon" aria-hidden="true">{{ $product->emoji }}</span>
                @if ($product->calories !== null)<span class="kcal">~{{ number_format($product->calories) }} kcal</span>@endif
              </span>
              <strong>{{ $product->name }}</strong>
              <span class="product-description">{{ $product->description }}</span>
              <span class="card-foot">
                <span class="price">${{ number_format($product->price, $product->price == floor($product->price) ? 0 : 2) }} <small>MXN</small></span>
                @if ($product->stock <= 0)
                  <span class="muted">Agotado</span>
                @else
                  <span class="card-plus" aria-hidden="true">+</span>
                @endif
              </span>
            </button>
          @endforeach
        </div>
      </section>
    @empty
      <p>Estamos preparando el menú. Vuelve en un momento.</p>
    @endforelse
  </section>
</main>

<footer class="footer">
  <div class="shell footer-content">
    <div><strong class="brand">B-CRUNCH</strong><p>© {{ now()->year }} B-CRUNCH</p></div>
    <div class="footer-links">
      <a href="{{ route('pos') }}">Acceso a caja</a>
      @if (preg_match('/^\d{10,15}$/D', $settings['whatsapp']))
        <a href="https://wa.me/{{ $settings['whatsapp'] }}?text={{ rawurlencode('Hola, B-CRUNCH. Quiero consultar sobre un pedido.') }}" target="_blank" rel="noopener">Contactar por WhatsApp</a>
      @endif
      <details><summary>Información del pedido</summary><p>B-CRUNCH usa tu nombre y tus notas para identificar y preparar tu pedido. Los precios están en pesos mexicanos. Las calorías son aproximadas. Paga en caja al recoger.</p></details>
    </div>
  </div>
</footer>

<button id="cartBar" class="button cart-bar" type="button" hidden>
  <span id="cartBarCount">Mi pedido</span>
  <strong id="cartBarTotal"></strong>
</button>

<dialog id="productModal" aria-labelledby="pmName">
  <form id="productForm" class="product-sheet">
    <header class="dialog-header">
      <span id="pmEmoji" class="food-icon" aria-hidden="true"></span>
      <button class="icon-button" type="button" data-close aria-label="Cerrar">×</button>
    </header>
    <h2 id="pmName"></h2>
    <p id="pmDesc" class="muted"></p>
    <div class="product-facts">
      <strong class="price" id="pmPrice"></strong>
      <span class="kcal" id="pmKcal"></span>
    </div>
    <div id="pmOptions" class="options"></div>
    <div class="dialog-actions">
      <div class="stepper">
        <button type="button" id="pmMinus" aria-label="Quitar una porción">−</button>
        <output id="pmQty">1</output>
        <button type="button" id="pmPlus" aria-label="Agregar una porción">+</button>
      </div>
      <button type="submit" id="pmAdd" class="button button-red"></button>
    </div>
    <p id="pmError" class="form-error" role="alert"></p>
  </form>
</dialog>

<dialog id="cartModal" class="drawer" aria-labelledby="cartTitle">
  <header class="dialog-header drawer-head">
    <h2 id="cartTitle">Mi pedido</h2>
    <button class="icon-button" type="button" data-close aria-label="Cerrar">×</button>
  </header>

  <div id="cartStep" class="drawer-body">
    <div id="cartLines"></div>
    <form id="orderForm">
      <label>Tu nombre <input id="customerName" maxlength="60" placeholder="Nombre para recoger" required></label>
      <label>Notas <span class="muted">(opcional)</span> <input id="customerNote" maxlength="200" placeholder="Ej. sin jalapeños"></label>
      <div class="cart-total"><span>Total</span><strong id="cartTotal"></strong></div>
      <p id="cartKcal" class="cart-kcal" aria-live="polite"></p>
      <button id="orderSend" class="button button-red" type="submit">Enviar pedido</button>
      <p id="orderError" class="form-error" role="alert"></p>
      <p class="muted pick-up">Paga en caja al recoger.</p>
    </form>
    <button id="viewPending" type="button" class="text-button" hidden>Ver pedido enviado</button>
  </div>

  <div id="sentStep" class="drawer-body sent" hidden>
    <p class="sent-label">Tu número de pedido en B-CRUNCH</p>
    <div id="sentNumber" class="sent-number"></div>
    <p id="sentStatus" class="sent-status" aria-live="polite"></p>
    <div id="sentLines"></div>
    <button id="newOrder" class="button" type="button">Volver al menú</button>
  </div>
</dialog>

<script type="application/json" id="menuData">{!! Illuminate\Support\Js::encode($menuData) !!}</script>
</body>
</html>
