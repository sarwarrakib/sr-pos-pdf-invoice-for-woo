(function($){
  'use strict';

  const state = {
    products: [],
    cart: {},
    customer: { id: 0, name: 'Guest', email: '', phone: '', billing:{}, shipping:{} },
    searchTimer: null,
    customerTimer: null
  };

  const money = (n) => {
    const num = (isNaN(n) || n === null) ? 0 : Number(n);
    return `${WCPOSM.currency}${num.toFixed(2)}`;
  };

  const escapeHtml = (str) => String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));

  const calcSubtotal = () => Object.values(state.cart).reduce((s,i)=> s + (Number(i.price)*Number(i.qty)), 0);
  const calcShipping = () => Number($('#wcposm-shipping').val() || 0);

  const calcDiscount = (subtotal) => {
    const type = $('#wcposm-discount-type').val();
    const val  = Number($('#wcposm-discount-value').val() || 0);
    if(!val || type === 'none') return 0;
    if(type === 'percent') return subtotal * (val/100);
    return val;
  };

  function updateTotals(){
    const subtotal = calcSubtotal();
    const shipping = calcShipping();
    const discount = calcDiscount(subtotal);
    const grand = Math.max(0, subtotal + shipping - discount);
    $('#wcposm-subtotal').text(money(subtotal));
    $('#wcposm-shipping-total').text(money(shipping));
    $('#wcposm-discount-total').text(`-${money(discount)}`);
    $('#wcposm-grand').text(money(grand));
  }

  function renderProducts(list){
    const $list = $('#wcposm-product-list');
    if(!list.length){
      $list.html(`<div class="wcposm-muted">No products found.</div>`);
      return;
    }
    $list.html(list.map(p => `
      <button class="wcposm-product wcposm-product--row" data-id="${p.id}">
        <img src="${p.img}" alt="" />
        <div class="wcposm-product-meta">
          <div class="wcposm-product-name">${escapeHtml(p.name)}</div>
          <div class="wcposm-product-sub">
            <span class="wcposm-price">${money(p.price)}</span>
            <small class="wcposm-sku">${p.sku ? 'SKU: '+escapeHtml(p.sku) : ''}</small>
          </div>
        </div>
        <div class="wcposm-product-add">＋</div>
      </button>
    `).join(''));
  }

  function renderCart(){
    const $wrap = $('#wcposm-cart-items');
    const items = Object.values(state.cart);
    if(!items.length){
      $wrap.html(`<div class="wcposm-muted">Cart is empty.</div>`);
      updateTotals();
      return;
    }
    $wrap.html(items.map(i => `
      <div class="wcposm-cart-item" data-id="${i.id}">
        <img src="${i.img}" alt="" />
        <div class="wcposm-cart-meta">
          <div class="wcposm-cart-name">${escapeHtml(i.name)}</div>
          <div class="wcposm-cart-sub">${money(i.price)}</div>
        </div>
        <div class="wcposm-cart-qty">
          <button class="button wcposm-qty-minus">-</button>
          <input type="number" min="1" class="wcposm-qty" value="${i.qty}" />
          <button class="button wcposm-qty-plus">+</button>
        </div>
        <div class="wcposm-cart-total">${money(Number(i.price)*Number(i.qty))}</div>
        <button class="button wcposm-remove">×</button>
      </div>
    `).join(''));
    updateTotals();
  }

  function addToCart(p){
    if(!state.cart[p.id]) state.cart[p.id] = { ...p, qty: 1 };
    else state.cart[p.id].qty += 1;
    renderCart();
  }

  function loadProducts(term=''){
    $('#wcposm-product-list').html(`<div class="wcposm-muted">Loading…</div>`);
    $.post(WCPOSM.ajaxUrl, { action:'wcposm_product_search', nonce:WCPOSM.nonce, term, page:1, per:40 })
      .done(res => {
        if(res && res.success){
          state.products = res.data.products || [];
          renderProducts(state.products);
        }else{
          $('#wcposm-product-list').html(`<div class="wcposm-muted">${WCPOSM.strings.error}</div>`);
        }
      })
      .fail(() => $('#wcposm-product-list').html(`<div class="wcposm-muted">${WCPOSM.strings.error}</div>`));
  }

  function customerSearch(term){
    if(!term){
      $('#wcposm-customer-results').hide().empty();
      return;
    }
    $.post(WCPOSM.ajaxUrl, { action:'wcposm_customer_search', nonce:WCPOSM.nonce, term })
      .done(res => {
        if(!res || !res.success) return;
        const customers = res.data.customers || [];
        const $dd = $('#wcposm-customer-results');
        if(!customers.length){
          $dd.html(`<div class="wcposm-dd-item wcposm-muted">No customer found.</div>`).show();
          return;
        }
        $dd.html(customers.map(c => `
          <button class="wcposm-dd-item" data-id="${c.id}">
            <strong>${escapeHtml(c.name)}</strong><br/>
            <small>${escapeHtml(c.email)} ${c.phone ? ' • '+escapeHtml(c.phone) : ''}</small>
          </button>
        `).join('')).show();
      });
  }

  function refreshCustomerUI(){
    $('#wcposm-customer-selected').text(state.customer.name || 'Guest');
    const b = state.customer.billing || {};
    const s = state.customer.shipping || {};
    const lines = [];
    if(state.customer.email) lines.push(`<small><strong>Email:</strong> ${escapeHtml(state.customer.email)}</small>`);
    if(state.customer.phone) lines.push(`<small><strong>Phone:</strong> ${escapeHtml(state.customer.phone)}</small>`);
    const addr = [];
    if(b.address_1) addr.push(escapeHtml(b.address_1));
    if(b.city) addr.push(escapeHtml(b.city));
    if(b.postcode) addr.push(escapeHtml(b.postcode));
    if(b.country) addr.push(escapeHtml(b.country));
    if(addr.length) lines.push(`<small><strong>Billing:</strong> ${addr.join(', ')}</small>`);
    const addr2 = [];
    if(s.address_1) addr2.push(escapeHtml(s.address_1));
    if(s.city) addr2.push(escapeHtml(s.city));
    if(s.postcode) addr2.push(escapeHtml(s.postcode));
    if(s.country) addr2.push(escapeHtml(s.country));
    if(addr2.length) lines.push(`<small><strong>Shipping:</strong> ${addr2.join(', ')}</small>`);
    $('#wcposm-customer-summary').html(lines.join('<br/>') || `<small class="wcposm-muted">Guest customer selected.</small>`);
  }

  function loadCustomerDetails(id){
    $.post(WCPOSM.ajaxUrl, { action:'wcposm_customer_get', nonce:WCPOSM.nonce, customer_id:id })
      .done(res => {
        if(res && res.success){
          state.customer = res.data.customer;
          refreshCustomerUI();
        }else{
          alert(res?.data?.message || WCPOSM.strings.error);
        }
      })
      .fail(() => alert(WCPOSM.strings.error));
  }

  function fillAddressFieldsFromState(){
    const b = state.customer.billing || {};
    const s = state.customer.shipping || {};
    $('#wcposm-modal-billing-address').val(b.address_1 || '');
    $('#wcposm-modal-billing-city').val(b.city || '');
    $('#wcposm-modal-billing-postcode').val(b.postcode || '');
    $('#wcposm-modal-billing-country').val(b.country || '');
    $('#wcposm-modal-shipping-address').val(s.address_1 || '');
    $('#wcposm-modal-shipping-city').val(s.city || '');
    $('#wcposm-modal-shipping-postcode').val(s.postcode || '');
    $('#wcposm-modal-shipping-country').val(s.country || '');
  }

  function openModal(mode){
    $('#wcposm-modal').show();
    $('#wcposm-modal-mode').val(mode);

    if(mode === 'new'){
      $('#wcposm-modal-title').text('New Customer');
      $('#wcposm-modal-id').val(0);
      $('#wcposm-modal-name').val('');
      $('#wcposm-modal-phone').val('');
      $('#wcposm-modal-email').val('');
      fillAddressFieldsFromState();
    }else if(mode === 'edit'){
      $('#wcposm-modal-title').text('Edit Customer');
      $('#wcposm-modal-id').val(state.customer.id || 0);
      $('#wcposm-modal-name').val(state.customer.name || '');
      $('#wcposm-modal-phone').val(state.customer.phone || '');
      $('#wcposm-modal-email').val(state.customer.email || '');
      fillAddressFieldsFromState();
    }else{
      $('#wcposm-modal-title').text('Guest Details');
      $('#wcposm-modal-id').val(0);
      $('#wcposm-modal-name').val(state.customer.name || 'Guest');
      $('#wcposm-modal-phone').val(state.customer.phone || '');
      $('#wcposm-modal-email').val(state.customer.email || '');
      fillAddressFieldsFromState();
    }
  }

  function closeModal(){ $('#wcposm-modal').hide(); }

  function collectModalCustomer(){
    return {
      id: Number($('#wcposm-modal-id').val() || 0),
      name: $('#wcposm-modal-name').val() || '',
      phone: $('#wcposm-modal-phone').val() || '',
      email: $('#wcposm-modal-email').val() || '',
      billing: {
        address_1: $('#wcposm-modal-billing-address').val() || '',
        city: $('#wcposm-modal-billing-city').val() || '',
        postcode: $('#wcposm-modal-billing-postcode').val() || '',
        country: $('#wcposm-modal-billing-country').val() || '',
      },
      shipping: {
        address_1: $('#wcposm-modal-shipping-address').val() || '',
        city: $('#wcposm-modal-shipping-city').val() || '',
        postcode: $('#wcposm-modal-shipping-postcode').val() || '',
        country: $('#wcposm-modal-shipping-country').val() || '',
      }
    };
  }

  function saveModal(){
    const mode = $('#wcposm-modal-mode').val();
    const c = collectModalCustomer();

    if(mode === 'guest'){
      state.customer = { ...state.customer, ...c, id:0 };
      refreshCustomerUI();
      closeModal();
      return;
    }

    const customerPayload = {
      id: c.id,
      name: c.name,
      phone: c.phone,
      email: c.email,
      billing_address: c.billing.address_1,
      billing_city: c.billing.city,
      billing_postcode: c.billing.postcode,
      billing_country: c.billing.country,
      shipping_address: c.shipping.address_1,
      shipping_city: c.shipping.city,
      shipping_postcode: c.shipping.postcode,
      shipping_country: c.shipping.country
    };

    $('#wcposm-modal-save').prop('disabled', true).text('Saving…');

    $.post(WCPOSM.ajaxUrl, { action:'wcposm_customer_save', nonce:WCPOSM.nonce, customer:customerPayload })
      .done(res => {
        if(res && res.success){
          state.customer = res.data.customer;
          refreshCustomerUI();
          closeModal();
        }else{
          alert(res?.data?.message || WCPOSM.strings.error);
        }
      })
      .fail(() => alert(WCPOSM.strings.error))
      .always(()=> $('#wcposm-modal-save').prop('disabled', false).text('Save'));
  }

  function createOrder(){
    const items = Object.values(state.cart).map(i => ({ product_id: i.id, qty: i.qty }));
    if(!items.length){ alert('Cart is empty.'); return; }

    const order = {
      items,
      customer_id: state.customer.id || 0,
      status: $('#wcposm-status').val(),
      payment: $('#wcposm-payment').val(),
      shipping: calcShipping(),
      discount_type: $('#wcposm-discount-type').val(),
      discount_value: Number($('#wcposm-discount-value').val() || 0),
      billing: {
        first_name: '',
        last_name: '',
        address_1: state.customer.billing?.address_1 || '',
        city: state.customer.billing?.city || '',
        postcode: state.customer.billing?.postcode || '',
        country: state.customer.billing?.country || '',
        email: state.customer.email || '',
        phone: state.customer.phone || '',
      },
      shipping_addr: {
        first_name: '',
        last_name: '',
        address_1: state.customer.shipping?.address_1 || '',
        city: state.customer.shipping?.city || '',
        postcode: state.customer.shipping?.postcode || '',
        country: state.customer.shipping?.country || '',
        phone: state.customer.phone || '',
      }
    };

    $('#wcposm-create-order').prop('disabled', true).text(WCPOSM.strings.creating);
    $('#wcposm-msg').removeClass('ok bad').text('');

    $.post(WCPOSM.ajaxUrl, { action:'wcposm_create_order', nonce:WCPOSM.nonce, order })
      .done(res => {
        if(res && res.success){
          $('#wcposm-msg').addClass('ok').text(`${WCPOSM.strings.created} #${res.data.order_id}`);
          $('#wcposm-after-create').show();
          $('#wcposm-download-invoice').attr('href', res.data.invoice_url);
          $('#wcposm-download-packing').attr('href', res.data.packing_url);

          state.cart = {};
          renderCart();
        }else{
          $('#wcposm-msg').addClass('bad').text(res?.data?.message || WCPOSM.strings.error);
        }
      })
      .fail(()=> $('#wcposm-msg').addClass('bad').text(WCPOSM.strings.error))
      .always(()=> $('#wcposm-create-order').prop('disabled', false).text('Create Order'));
  }

  function initDefaults(){
    $('#wcposm-status').val(WCPOSM.defaults.status);
    $('#wcposm-payment').val(WCPOSM.defaults.payment);

    if(!WCPOSM.defaults.enableShipping){
      $('#wcposm-shipping').prop('disabled', true).val(0);
    }
    if(!WCPOSM.defaults.enableDiscount){
      $('#wcposm-discount-type, #wcposm-discount-value').prop('disabled', true);
      $('#wcposm-discount-type').val('none');
      $('#wcposm-discount-value').val(0);
    }

    if(Number(WCPOSM.defaults.customer || 0) > 0) loadCustomerDetails(Number(WCPOSM.defaults.customer));
    else { state.customer = { id:0, name:'Guest', email:'', phone:'', billing:{}, shipping:{} }; refreshCustomerUI(); }

    updateTotals();
  }

  // Events
  $(document).on('click', '.wcposm-product', function(){
    const id = Number($(this).data('id'));
    const p = state.products.find(x => Number(x.id) === id);
    if(p) addToCart(p);
  });

  $(document).on('click', '.wcposm-qty-plus', function(){
    const id = Number($(this).closest('.wcposm-cart-item').data('id'));
    state.cart[id].qty += 1; renderCart();
  });

  $(document).on('click', '.wcposm-qty-minus', function(){
    const id = Number($(this).closest('.wcposm-cart-item').data('id'));
    state.cart[id].qty = Math.max(1, state.cart[id].qty - 1); renderCart();
  });

  $(document).on('input', '.wcposm-qty', function(){
    const id = Number($(this).closest('.wcposm-cart-item').data('id'));
    state.cart[id].qty = Math.max(1, Number($(this).val() || 1)); renderCart();
  });

  $(document).on('click', '.wcposm-remove', function(){
    const id = Number($(this).closest('.wcposm-cart-item').data('id'));
    delete state.cart[id]; renderCart();
  });

  $('#wcposm-search').on('input', function(){
    const term = $(this).val();
    clearTimeout(state.searchTimer);
    state.searchTimer = setTimeout(()=> loadProducts(term), 250);
  });

  $('#wcposm-shipping, #wcposm-discount-type, #wcposm-discount-value').on('input change', updateTotals);

  $('#wcposm-customer-search').on('input', function(){
    const term = $(this).val();
    clearTimeout(state.customerTimer);
    state.customerTimer = setTimeout(()=> customerSearch(term), 250);
  });

  $(document).on('click', '.wcposm-dd-item', function(){
    const id = Number($(this).data('id'));
    $('#wcposm-customer-results').hide().empty();
    $('#wcposm-customer-search').val('');
    if(id) loadCustomerDetails(id);
  });

  $('#wcposm-new-customer').on('click', ()=> openModal('new'));
  $('#wcposm-edit-customer').on('click', ()=> state.customer.id > 0 ? openModal('edit') : openModal('guest'));

  $('#wcposm-modal-close').on('click', closeModal);
  $('.wcposm-modal-backdrop').on('click', closeModal);
  $('#wcposm-modal-save').on('click', saveModal);

  $('#wcposm-create-order').on('click', createOrder);

  $(function(){
    initDefaults();
    loadProducts('');
    renderCart();
  });

})(jQuery);
