// IIFE (Immediately Invoked Function Expression) para encapsular el código
(() => {
  // ===============================
  // CONFIGURACIÓN
  // ===============================
  const SHIPPING_THRESHOLD = 200000;
  const FREE_SHIPPING_ITEM_THRESHOLD = 5;
  const SHIPPING_FEE = 8000;
  const WHATSAPP_NUMBER = '573172980319';
  const WHOLESALE_THRESHOLD = 45;

  // ===============================
  // ESTADO DEL CARRITO
  // ===============================
  let cartItems = [];
  
  // ===============================
  // LÓGICA DE NOTIFICACIÓN (TOAST)
  // ===============================
  function showCustomToast({ icon, title, subtitle, actionText, actionCallback, cancelText }) {
      const existingToast = document.querySelector('.custom-toast');
      if (existingToast) existingToast.remove();

      const toast = document.createElement('div');
      toast.className = 'custom-toast';
      
      const actionButtonHTML = actionText ? `<button class="toast-action-btn">${actionText}</button>` : '';
      const cancelButtonHTML = cancelText ? `<button class="toast-cancel-btn">${cancelText}</button>` : '';
      const buttonsContainerHTML = (actionText || cancelText) ? `<div class="toast-buttons">${cancelButtonHTML}${actionButtonHTML}</div>` : '';

      toast.innerHTML = `
          <span class="toast-icon">${icon}</span>
          <div class="toast-body">
              <div class="toast-title">${title}</div>
              <span class="toast-subtitle">${subtitle}</span>
          </div>
          ${buttonsContainerHTML}
          <button class="toast-close-btn">×</button>
      `;

      document.body.appendChild(toast);
      setTimeout(() => toast.classList.add('show'), 10);

      const removeToast = () => {
          toast.classList.remove('show');
          toast.addEventListener('transitionend', () => toast.remove());
      };

      toast.querySelector('.toast-close-btn').onclick = removeToast;
      
      if (actionText && actionCallback) {
          toast.querySelector('.toast-action-btn').onclick = () => {
              actionCallback();
              removeToast();
          };
      }
      if (cancelText) {
          toast.querySelector('.toast-cancel-btn').onclick = removeToast;
      }

      if (!actionText && !cancelText) {
        setTimeout(removeToast, 5000);
      }
  }

  // ===============================
  // LÓGICA DEL MENÚ HAMBURGUESA
  // ===============================
  function toggleNavMenu() {
    const hamburgerBtn = document.getElementById('hamburger-button');
    const navMenu = document.querySelector('.main-nav');
    hamburgerBtn.classList.toggle('open');
    navMenu.classList.toggle('open');
  }

  // ===============================
  // LÓGICA DEL CARRITO
  // ===============================
  function updateCartCounter() {
    const cartCounterEl = document.getElementById('cart-counter');
    if (!cartCounterEl) return;
    const totalItems = cartItems.reduce((sum, item) => sum + item.qty, 0);
    if (totalItems > 0) {
        cartCounterEl.textContent = totalItems;
        cartCounterEl.classList.add('show');
    } else {
        cartCounterEl.classList.remove('show');
    }
  }
  
  const formatCOP = (amount) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(amount);
  const parsePrice = (text) => Number(String(text || '').replace(/[^\d]/g, ''));
  function findItemIndex(name) { return cartItems.findIndex(i => i.name === name); }

  function addItemToCart({ name, price, qty = 1 }) {
    const idx = findItemIndex(name);
    if (idx >= 0) {
      cartItems[idx].qty += qty;
    } else {
      cartItems.push({ name, price, qty });
    }
    renderCart();
  }

  function removeItemFromCart(name) {
    cartItems = cartItems.filter(i => i.name !== name);
    renderCart();
  }

  function changeItemQty(name, newQty) {
    const idx = findItemIndex(name);
    if (idx >= 0) {
      cartItems[idx].qty = Math.max(1, Math.floor(newQty) || 1);
      renderCart();
    }
  }

  function calculateSubtotal() { return cartItems.reduce((sum, item) => sum + item.price * item.qty, 0); }
  function getSelectedShippingMethod() { return document.querySelector('input[name="shippingMethod"]:checked')?.value || 'delivery'; }
  function calculateShipping(subtotal) {
    const method = getSelectedShippingMethod();
    const totalItems = cartItems.reduce((sum, item) => sum + item.qty, 0);
    const qualifiesForFreeShipping = totalItems >= FREE_SHIPPING_ITEM_THRESHOLD || subtotal >= SHIPPING_THRESHOLD;
    return (method === 'delivery' && subtotal > 0 && !qualifiesForFreeShipping) ? SHIPPING_FEE : 0;
  }

  function updateTotalsUI() {
    const subtotalEl = document.getElementById('cart-subtotal');
    const shippingEl = document.getElementById('cart-shipping');
    const totalEl = document.getElementById('cart-total');
    const wholesaleNoticeEl = document.getElementById('wholesale-notice');

    const subtotal = calculateSubtotal();
    const shipping = calculateShipping(subtotal);
    const total = subtotal + shipping;

    if (subtotalEl) subtotalEl.textContent = formatCOP(subtotal);
    if (shippingEl) shippingEl.textContent = formatCOP(shipping);
    if (totalEl) totalEl.textContent = formatCOP(total);
    
    const totalItems = cartItems.reduce((sum, item) => sum + item.qty, 0);
    if (wholesaleNoticeEl) {
        if (totalItems > WHOLESALE_THRESHOLD) {
            wholesaleNoticeEl.classList.add('show');
        } else {
            wholesaleNoticeEl.classList.remove('show');
        }
    }
  }

  function renderCart() {
    const cartItemsContainer = document.querySelector('.cart-items');
    if (!cartItemsContainer) return;
    cartItemsContainer.innerHTML = cartItems.length === 0 ? '<p>Tu carrito está vacío.</p>' :
      cartItems.map(item => `
        <div class="cart-item" data-name="${item.name}">
            <div class="item-info">
                <strong class="item-name">${item.name}</strong>
                <div class="item-meta">
                    <span class="item-price">${formatCOP(item.price)}</span>
                    <span>x</span>
                    <input type="number" min="1" value="${item.qty}" class="item-qty" style="width: 50px;">
                </div>
            </div>
            <button class="item-remove" title="Eliminar">×</button>
        </div>`).join('');
    
    updateTotalsUI();
    updateCartCounter();
  }

  function clearCart() {
      if (cartItems.length === 0) return;
      cartItems = [];
      renderCart();
      showCustomToast({
          icon: '✅',
          title: 'Carrito vaciado',
          subtitle: 'Todos los productos han sido eliminados.'
      });
  }

  // ===============================
  // LÓGICA DE BÚSQUEDA Y FILTRADO
  // ===============================
  function setupProductFiltering() {
      const searchBar = document.getElementById('search-bar');
      const filterBtns = document.querySelectorAll('.filter-btn');
      const allProducts = document.querySelectorAll('.product-grid .producto');

      if (allProducts.length === 0) return;

      let currentFilter = 'all';
      let currentSearchTerm = '';

      function applyFilters() {
          allProducts.forEach(product => {
              const category = product.dataset.category || '';
              const name = product.querySelector('h3').innerText.toLowerCase();

              const categoryMatch = currentFilter === 'all' || category.toLowerCase() === currentFilter;
              const searchMatch = name.includes(currentSearchTerm);

              if (categoryMatch && searchMatch) {
                  product.classList.remove('product-hidden');
              } else {
                  product.classList.add('product-hidden');
              }
          });
      }

      if (searchBar) {
          searchBar.addEventListener('input', () => {
              currentSearchTerm = searchBar.value.toLowerCase().trim();
              applyFilters();
          });
      }

      filterBtns.forEach(btn => {
          btn.addEventListener('click', () => {
              filterBtns.forEach(b => b.classList.remove('active'));
              btn.classList.add('active');
              currentFilter = btn.dataset.filter;
              applyFilters();
          });
      });
  }

  // ===============================
  // NUEVO: LÓGICA PARA EL ACORDEÓN DE FAQ
  // ===============================
  function setupFAQAccordion() {
      const faqItems = document.querySelectorAll('.faq-item');
      if (faqItems.length === 0) return;

      faqItems.forEach(item => {
          const question = item.querySelector('.faq-question');
          question.addEventListener('click', () => {
              faqItems.forEach(otherItem => {
                  if (otherItem !== item) {
                      otherItem.classList.remove('active');
                  }
              });
              item.classList.toggle('active');
          });
      });
  }

  // ===============================
  // GESTIÓN DE EVENTOS
  // ===============================
  function addCartItemListeners() {
    const cartItemsContainer = document.querySelector('.cart-items');
    if (!cartItemsContainer) return;
    
    cartItemsContainer.addEventListener('change', (event) => {
        if (event.target.classList.contains('item-qty')) {
            const name = event.target.closest('.cart-item').dataset.name;
            changeItemQty(name, Number(event.target.value));
        }
    });

    cartItemsContainer.addEventListener('click', (event) => {
        if (event.target.classList.contains('item-remove')) {
            const name = event.target.closest('.cart-item').dataset.name;
            removeItemFromCart(name);
        }
    });
  }

  function toggleCart() { 
    const cartPanel = document.getElementById('cart');
    cartPanel.classList.toggle('open'); 
  }
  function openModal() { 
    const modal = document.getElementById('checkout-modal');
    if (modal) modal.style.display = 'flex'; 
  }
  function closeModal() { 
    const modal = document.getElementById('checkout-modal');
    if (modal) modal.style.display = 'none'; 
  }

  function handleCheckout() {
    if (cartItems.length === 0) {
        showCustomToast({
            icon: '💗',
            title: 'Tu carrito está vacío',
            subtitle: 'Añade tus artículos favoritos y vuelve cuando estés lista.',
            actionText: 'Ver productos',
            actionCallback: () => {
                if (!window.location.pathname.includes('catalogo.html')) {
                    window.location.href = 'catalogo.html';
                }
            }
        });
        return;
    }

    const totalItems = cartItems.reduce((sum, item) => sum + item.qty, 0);

    if (totalItems > WHOLESALE_THRESHOLD) {
        showCustomToast({
            icon: '📦',
            title: 'Cotización por WhatsApp',
            subtitle: 'Al ser una compra por mayor, la cotización se hará directamente por WhatsApp.',
            actionText: 'Continuar',
            cancelText: 'Cancelar',
            actionCallback: () => {
                const message = buildWholesaleWhatsAppMessage();
                window.open(`https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`, '_blank');
            }
        });
        return;
    }

    const method = getSelectedShippingMethod();
    if (method === 'delivery') {
        openModal();
    } else {
        showCustomToast({
            icon: '🏢',
            title: 'Confirmar Recogida en Tienda',
            subtitle: 'Confirma por WhatsApp el lugar y horario disponibles para recoger tu pedido.',
            actionText: 'Aceptar',
            cancelText: 'Cancelar',
            actionCallback: () => {
                const message = buildWhatsAppMessage();
                window.open(`https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`, '_blank');
            }
        });
    }
  }
  
  function handleDeliveryFormSubmit(e) {
    e.preventDefault();
    const address = document.getElementById('delivery-address').value.trim();
    const name = document.getElementById('contact-name').value.trim();
    const phone = document.getElementById('contact-phone').value.trim();
    if (!address || !name || !phone) {
        alert('Por favor completa todos los campos de envío.');
        return;
    }
    const deliveryDetails = { address, name, phone };
    const message = buildWhatsAppMessage(deliveryDetails);
    window.open(`https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`, '_blank');
    closeModal();
  }
  
  function buildWhatsAppMessage(deliveryDetails = null) {
      const subtotal = calculateSubtotal();
      const shipping = calculateShipping(subtotal);
      const total = subtotal + shipping;
      const method = getSelectedShippingMethod();
      const itemsText = cartItems.map(it => `  - ${it.qty} x ${it.name} (${formatCOP(it.price)})`).join('\n');
      let message = [
          '🌙 ¡Hola D&L Pijamas! Quiero realizar este pedido:\n',
          itemsText,
          '\n------------------------------------',
          `Subtotal: ${formatCOP(subtotal)}`,
          `Método: ${method === 'delivery' ? 'Domicilio' : 'Recoger en tienda'}`,
          `Costo Envío: ${formatCOP(shipping)}`,
          `TOTAL: ${formatCOP(total)}`,
          '------------------------------------\n'
      ];
      if (deliveryDetails) {
          message.push('Mis datos de envío son:');
          message.push(`Nombre: ${deliveryDetails.name}`);
          message.push(`Dirección: ${deliveryDetails.address}`);
          message.push(`Celular: ${deliveryDetails.phone}`);
      } else {
          message.push('Pasaré a recoger el pedido. ¡Gracias!');
      }
      return message.join('\n');
  }

  function buildWholesaleWhatsAppMessage() {
      const itemsText = cartItems.map(it => `- ${it.qty} x ${it.name}`).join('\n');
      const message = [
          '¡Hola D&L Pijamas! Quiero consultar un pedido grande para estos productos:\n',
          itemsText
      ].join('\n');
      return message;
  }
  
  // ===============================
  // INICIALIZACIÓN Y EVENTOS PRINCIPALES
  // ===============================
  document.addEventListener('DOMContentLoaded', () => {
    const cartIcon = document.querySelector('.cart-icon');
    const cartCloseBtn = document.getElementById('cart-close');
    const clearBtn = document.getElementById('clear-cart');
    const checkoutBtn = document.getElementById('checkout-whatsapp');
    const hamburgerBtn = document.getElementById('hamburger-button');
    const modalCloseBtn = document.getElementById('modal-close');
    const modalCancelBtn = document.getElementById('modal-cancel');
    const deliveryForm = document.getElementById('delivery-form');
    const cartSummaryEl = document.querySelector('.cart-summary');
    
    renderCart();
    addCartItemListeners();
    setupProductFiltering();
    setupFAQAccordion(); // LLAMADA A LA NUEVA FUNCIÓN

    if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleNavMenu);
    if (cartIcon) cartIcon.addEventListener('click', toggleCart);
    if (cartCloseBtn) cartCloseBtn.addEventListener('click', toggleCart);
    if (clearBtn) clearBtn.addEventListener('click', clearCart);
    if (checkoutBtn) checkoutBtn.addEventListener('click', handleCheckout);
    
    document.body.addEventListener('click', (event) => {
        if (event.target.classList.contains('btn-add-to-cart')) {
            const productCard = event.target.closest('.producto');
            const name = productCard.querySelector('h3').innerText;
            
            let priceElement = productCard.querySelector('.product-info .price') || productCard.querySelector('.price');
            
            if (priceElement) {
                const price = parsePrice(priceElement.innerText);
                addItemToCart({ name, price });
            } else {
                console.error("No se pudo encontrar el elemento del precio para el producto:", name);
            }
        }
    });

    if (cartSummaryEl) {
        cartSummaryEl.addEventListener('change', (event) => {
            if (event.target.name === 'shippingMethod') {
                updateTotalsUI();
            }
        });
    }

    if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);
    if (modalCancelBtn) modalCancelBtn.addEventListener('click', closeModal);
    if (deliveryForm) deliveryForm.addEventListener('submit', handleDeliveryFormSubmit);
    window.addEventListener('click', (e) => { 
        const modal = document.getElementById('checkout-modal');
        if (e.target === modal) closeModal(); 
    });

    // --- LÓGICA PARA BLOQUEAR CLIC DERECHO Y ATAJOS ---
    
    document.addEventListener('contextmenu', (event) => {
        event.preventDefault();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'F12' || (event.ctrlKey && event.shiftKey && ['I', 'J', 'C'].includes(event.key.toUpperCase())) || (event.metaKey && event.altKey && ['I', 'J', 'C'].includes(event.key.toUpperCase())) || (event.ctrlKey && event.key.toUpperCase() === 'U')) {
            event.preventDefault();
        }
    });

  });

})();
