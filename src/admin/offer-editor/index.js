/**
 * UpsellBay offer editor entry.
 */
document.addEventListener("click", (event) => {
  const button = event.target.closest("[data-upsellbay-add-rule]");

  if (!button) {
    return;
  }

  event.preventDefault();
  document.dispatchEvent(new CustomEvent("upsellbay:add-rule"));
});

/**
 * UpsellBay product selector.
 */
window.jQuery(function ($) {
  const $selectors = $("[data-upsellbay-product-selector]");
  if (!$selectors.length) {
    return;
  }

  $selectors.each(function () {
    const $selector = $(this);
    const $inputWrapper = $selector.find(
      ".upsellbay-product-selector__input-wrapper",
    );
    const $search = $inputWrapper.find("input");
    const $clear = $selector.find(".upsellbay-product-selector__clear");
    const $input = $selector.children('input[type="hidden"]');
    const $results = $selector.find("[data-upsellbay-results]");
    const $selection = $selector.find("[data-upsellbay-selection]");

    let searchTimeout = null;
    let page = 1;
    let isLoading = false;
    let hasMore = true;
    let currentSearch = "";
    let hasOpened = false;

    function fetchProducts(append = false) {
      if (isLoading || (append && !hasMore)) {
        return;
      }

      isLoading = true;

      if (!append) {
        $results.empty();
        $results.append(
          '<div class="upsellbay-product-selector__message">Loading...</div>',
        );
      } else {
        $results.append(
          '<div class="upsellbay-product-selector__loading-more">Loading more...</div>',
        );
      }

      $.ajax({
        url: window.upsellbay_data.rest_url + "upsellbay/v1/products",
        type: "GET",
        data: {
          search: currentSearch,
          page: page,
        },
        beforeSend: function (xhr) {
          xhr.setRequestHeader("X-WP-Nonce", window.upsellbay_data.nonce);
        },
        success: (response) => {
          isLoading = false;
          $results
            .find(
              ".upsellbay-product-selector__message, .upsellbay-product-selector__loading-more",
            )
            .remove();

          if (Array.isArray(response)) {
            hasMore = response.length >= 10;
            const products = response;

            if (products && products.length) {
              products.forEach((product) => {
                const skuText = product.sku ? `SKU: ${product.sku}` : "";
                const $result = $(`
									<div class="upsellbay-product-selector__result" data-id="${product.id}">
										<div class="upsellbay-product-selector__result-image">
											${product.image ? `<img src="${product.image}" alt="">` : ""}
										</div>
										<div class="upsellbay-product-selector__result-info">
											<span class="upsellbay-product-selector__result-name">${product.name}</span>
											<span class="upsellbay-product-selector__result-meta">${
                        product.id ? `ID: ${product.id}` : ""
                      } ${skuText ? `| ${skuText}` : ""}</span>
										</div>
									</div>
								`);

                $result.on("click", () => {
                  selectProduct(product);
                });

                $results.append($result);
              });
            } else if (!append) {
              $results.append(
                '<div class="upsellbay-product-selector__message">No products found.</div>',
              );
            }
          } else {
            if (!append) {
              $results.append(
                '<div class="upsellbay-product-selector__message upsellbay-product-selector__message--error">Error loading products.</div>',
              );
            }
          }
        },
        error: () => {
          isLoading = false;
          $results
            .find(
              ".upsellbay-product-selector__message, .upsellbay-product-selector__loading-more",
            )
            .remove();
          if (!append) {
            $results.append(
              '<div class="upsellbay-product-selector__message upsellbay-product-selector__message--error">Error loading products.</div>',
            );
          }
        },
      });
    }

    function selectProduct(product) {
      $input.val(product.id);
      $inputWrapper.hide();
      $results.removeClass("is-active");

      $selection
        .html(
          `
				<div class="upsellbay-product-selector__result-image">
					${product.image ? `<img src="${product.image}" alt="">` : ""}
				</div>
				<div class="upsellbay-product-selector__result-info">
					<span class="upsellbay-product-selector__result-name">${product.name}</span>
					<span class="upsellbay-product-selector__result-meta">${
            product.price || ""
          }</span>
				</div>
				<span class="upsellbay-product-selector__selection-remove">&times;</span>
			`,
        )
        .addClass("is-active");

      $selection
        .find(".upsellbay-product-selector__selection-remove")
        .on("click", () => {
          $input.val("");
          $selection.empty().removeClass("is-active");
          $inputWrapper.show();
          $search.focus();
        });
    }

    $search.on("focus", function () {
      $results.addClass("is-active");
      if (!hasOpened) {
        hasOpened = true;
        fetchProducts(false);
      }
    });

    $search.on("input", function () {
      currentSearch = $(this).val();

      if (currentSearch.length > 0) {
        $clear.show();
      } else {
        $clear.hide();
      }

      clearTimeout(searchTimeout);

      searchTimeout = setTimeout(() => {
        page = 1;
        hasMore = true;
        fetchProducts(false);
      }, 400);
    });

    $clear.on("click", function () {
      $search.val("");
      currentSearch = "";
      $clear.hide();
      page = 1;
      hasMore = true;
      fetchProducts(false);
      $search.focus();
    });

    $results.on("scroll", function () {
      const $this = $(this);
      if (
        $this[0].scrollHeight - $this.scrollTop() - $this.innerHeight() <
        50
      ) {
        if (hasMore && !isLoading) {
          page++;
          fetchProducts(true);
        }
      }
    });

    // Initialization for pre-selected product removal handler
    if ($selection.hasClass("is-active")) {
      $selection
        .find(".upsellbay-product-selector__selection-remove")
        .on("click", () => {
          $input.val("");
          $selection.empty().removeClass("is-active");
          $inputWrapper.show();
          $search.focus();
        });
    }
  });

  $(document).on("click", (e) => {
    if (!$(e.target).closest(".upsellbay-product-selector").length) {
      $(".upsellbay-product-selector__results").removeClass("is-active");
    }
  });
});

/**
 * UpsellBay Offer Editor Form Validation & Persistence
 */
window.jQuery(function ($) {
  const $form = $('#upsellbay-offer-editor-form');
  if (!$form.length) {
    return;
  }

  const urlParams = new URLSearchParams(window.location.search);
  const offerId = urlParams.get('offer_id') || 'new';
  const cacheKey = `upsellbay_offer_draft_${offerId}`;

  let isDirty = false;
  let isSubmitting = false;

  const requiredFields = ['title', '_ub_headline', '_ub_button_text', '_ub_offer_product_id'];
  const jsonFields = ['_ub_rules', '_ub_placement_config'];

  // Restore cache if exists
  const cachedData = localStorage.getItem(cacheKey);
  if (cachedData) {
    try {
      const parsedCache = JSON.parse(cachedData);
      Object.keys(parsedCache).forEach(key => {
        const $input = $form.find(`[name="${key}"]`);
        if ($input.length) {
          if ($input.is(':checkbox') || $input.is(':radio')) {
            $form.find(`[name="${key}"][value="${parsedCache[key]}"]`).prop('checked', true);
          } else {
            $input.val(parsedCache[key]);
          }
        }
      });
      // Update UI for product selector
      const productId = parsedCache['_ub_offer_product_id'];
      if (productId && productId !== "0") {
         // To make it look selected, at least hide the search wrapper
         $form.find('.upsellbay-product-selector__input-wrapper').hide();
         $form.find('[data-upsellbay-selection]').html(`
            <div class="upsellbay-product-selector__result-info">
              <span class="upsellbay-product-selector__result-name">Product ID: ${productId}</span>
              <span class="upsellbay-product-selector__result-meta">(Restored from draft)</span>
            </div>
            <span class="upsellbay-product-selector__selection-remove">&times;</span>
         `).addClass("is-active");
         
         $form.find('.upsellbay-product-selector__selection-remove').on("click", () => {
            $form.find('input[name="_ub_offer_product_id"]').val("");
            $form.find('[data-upsellbay-selection]').empty().removeClass("is-active");
            $form.find('.upsellbay-product-selector__input-wrapper').show();
         });
      }
    } catch (e) {
      console.error('Failed to parse cached offer data', e);
    }
  }

  // Update cache on input
  function saveCache() {
    isDirty = true;
    const formData = new FormData($form[0]);
    const cacheObj = {};
    for (let [key, value] of formData.entries()) {
      cacheObj[key] = value;
    }
    localStorage.setItem(cacheKey, JSON.stringify(cacheObj));
  }

  $form.on('input change', 'input, select, textarea', function() {
    saveCache();
  });

  // Handle Clear Button
  $('#upsellbay-clear-offer-form').on('click', function(e) {
    e.preventDefault();
    if (confirm(wp.i18n ? wp.i18n.__('Are you sure you want to clear all fields?', 'upsellbay') : 'Are you sure you want to clear all fields?')) {
      $form[0].reset();

      $form.find('[data-upsellbay-selection]').empty().removeClass('is-active');
      $form.find('.upsellbay-product-selector__input-wrapper').show();
      $form.find('input[type="hidden"]').val('');

      localStorage.removeItem(cacheKey);
      isDirty = false;
    }
  });

  // Form Submission Validation
  $form.on('submit', function(e) {
    isSubmitting = true;
    let isValid = true;
    let errorMessages = [];

    // Required fields check
    requiredFields.forEach(fieldName => {
      const $field = $form.find(`[name="${fieldName}"]`);
      if ($field.length && !$field.val().trim()) {
        isValid = false;
        errorMessages.push(`Please fill out the required field: ${fieldName.replace('_ub_', '').replace(/_/g, ' ')}`);
        $field.css('border-color', 'red');
      } else if ($field.length) {
        $field.css('border-color', '');
      }
    });

    // JSON parsing check
    jsonFields.forEach(fieldName => {
      const $field = $form.find(`[name="${fieldName}"]`);
      if ($field.length && $field.val().trim()) {
        try {
          JSON.parse($field.val().trim());
          $field.css('border-color', '');
        } catch (err) {
          isValid = false;
          errorMessages.push(`Invalid JSON format in field: ${fieldName.replace('_ub_', '').replace(/_/g, ' ')}`);
          $field.css('border-color', 'red');
        }
      }
    });

    if (!isValid) {
      e.preventDefault();
      isSubmitting = false;
      alert("Please fix the following errors:\n\n- " + errorMessages.join("\n- "));
      return false;
    }

    localStorage.removeItem(cacheKey);
  });

  // Unsaved changes warning
  window.addEventListener('beforeunload', function(e) {
    if (isDirty && !isSubmitting) {
      e.preventDefault();
      e.returnValue = ''; 
    }
  });
});

/**
 * UpsellBay offer type guidance
 */
window.jQuery(function ($) {
  const $select = $('#upsellbay-_ub_offer_type');
  const $description = $('[data-upsellbay-offer-type-description]');

  if (!$select.length || !$description.length) {
    return;
  }

  let descriptions = {};

  try {
    descriptions = JSON.parse($description.attr('data-descriptions') || '{}');
  } catch (error) {
    descriptions = {};
  }

  function updateOfferTypeDescription() {
    const selectedType = $select.val();
    const text = descriptions[selectedType] || '';

    if (!text) {
      $description.text('').attr('hidden', true);
      return;
    }

    $description.text(text).attr('hidden', false);
  }

  $select.on('change', updateOfferTypeDescription);
  updateOfferTypeDescription();
});

/**
 * UpsellBay Recommendation Assistant
 */
window.jQuery(function ($) {
  const $triggerInput = $('#upsellbay-_ub_trigger_product_ids');
  const $container = $('#upsellbay-recommendations-container');
  if (!$triggerInput.length || !$container.length) {
    return;
  }

  let fetchTimeout = null;

  function fetchRecommendations() {
    const val = $triggerInput.val().trim();
    if (!val) {
      $container.html('<p class="description">Select a primary target product above to see AI/WooCommerce product recommendations here.</p>');
      return;
    }

    // Use the first ID
    const baseProductId = parseInt(val.split(',')[0].trim(), 10);
    if (!baseProductId || baseProductId <= 0) {
      $container.html('<p class="description">Select a primary target product above to see AI/WooCommerce product recommendations here.</p>');
      return;
    }

    $container.html('<p class="description">Loading recommendations...</p>');

    $.ajax({
      url: window.upsellbay_data.rest_url + "upsellbay/v1/recommendations",
      type: "GET",
      data: {
        base_product_id: baseProductId,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", $container.data('nonce') || window.upsellbay_data.nonce);
      },
      success: (response) => {
        if (!Array.isArray(response) || response.length === 0) {
          $container.html('<p class="description">No recommendations found for this product.</p>');
          return;
        }

        let html = '<ul class="upsellbay-recommendations-list" style="margin: 0; padding: 0; list-style: none;">';
        response.forEach(product => {
          html += `
            <li style="display: flex; align-items: center; background: #fff; border: 1px solid #ccd0d4; padding: 8px; margin-bottom: 8px;">
              <div style="flex: 0 0 40px; margin-right: 12px;">
                ${product.image ? `<img src="${product.image}" style="max-width: 100%; height: auto; border-radius: 4px;" alt="">` : ''}
              </div>
              <div style="flex: 1;">
                <strong style="display: block;">${product.name}</strong>
                <span style="font-size: 12px; color: #646970;">${product.reason}</span>
              </div>
              <div>
                <button type="button" class="button button-secondary upsellbay-use-recommendation" data-id="${product.id}" data-name="${product.name}" data-price="${product.price}">Use this</button>
              </div>
            </li>
          `;
        });
        html += '</ul>';

        $container.html(html);

        $container.find('.upsellbay-use-recommendation').on('click', function(e) {
          e.preventDefault();
          const id = $(this).data('id');
          const name = $(this).data('name');
          const price = $(this).data('price');

          $('#upsellbay-_ub_offer_product_id').val(id);

          // Update the visual product selector
          const $selector = $('#upsellbay-_ub_offer_product_id').closest('.upsellbay-product-selector');
          const $inputWrapper = $selector.find('.upsellbay-product-selector__input-wrapper');
          const $selection = $selector.find('[data-upsellbay-selection]');

          $inputWrapper.hide();
          $selection.html(`
            <div class="upsellbay-product-selector__result-info">
              <span class="upsellbay-product-selector__result-name">${name}</span>
              <span class="upsellbay-product-selector__result-meta">ID: ${id}</span>
            </div>
            <span class="upsellbay-product-selector__selection-remove">&times;</span>
          `).addClass("is-active");

          $selection.find('.upsellbay-product-selector__selection-remove').on("click", () => {
            $('#upsellbay-_ub_offer_product_id').val("");
            $selection.empty().removeClass("is-active");
            $inputWrapper.show();
          });
        });
      },
      error: () => {
        $container.html('<p class="description" style="color: #d63638;">Error loading recommendations.</p>');
      }
    });
  }

  $triggerInput.on('input change', function() {
    clearTimeout(fetchTimeout);
    fetchTimeout = setTimeout(fetchRecommendations, 500);
  });

  // Initial fetch
  if ($triggerInput.val().trim()) {
    fetchRecommendations();
  }
});

/**
 * UpsellBay Visual JSON Builder
 */
window.jQuery(function ($) {
  function initPlacementConfig() {
    const $textarea = $('#upsellbay-_ub_placement_config');
    const $position = $('[data-upsellbay-placement-position]');
    const $offerType = $('#upsellbay-_ub_offer_type');

    if (!$textarea.length || !$position.length || !$offerType.length) {
      return;
    }

    const offerTypeToPositions = {
      'checkout_bump': ['before_submit'],
      'product_upsell': ['after_add_to_cart'],
      'cart_crosssell': ['after_cart_table'],
      'thankyou_offer': ['order_received_actions']
    };

    function parseConfig() {
      try {
        const parsed = $textarea.val().trim()
          ? JSON.parse($textarea.val().trim())
          : {};
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed)
          ? parsed
          : {};
      } catch (e) {
        return null;
      }
    }

    function ensurePositionOption(position) {
      const hasOption = $position.find('option').filter(function () {
        return $(this).val() === position;
      }).length;

      if (!position || hasOption) {
        return;
      }

      $position.prepend(
        $('<option></option>')
          .val(position)
          .text('Custom saved position'),
      );
    }

    function filterPositions() {
      const selectedType = $offerType.val();
      const allowedPositions = offerTypeToPositions[selectedType] || [];
      const currentPos = $position.val();
      let hasValidSelection = false;

      $position.find('option').each(function() {
        const val = $(this).val();
        const isStandard = Object.values(offerTypeToPositions).flat().includes(val);
        
        if (isStandard && !allowedPositions.includes(val)) {
          $(this).prop('disabled', true).hide();
        } else {
          $(this).prop('disabled', false).show();
          if (val === currentPos) {
             hasValidSelection = true;
          }
        }
      });
      
      if (!hasValidSelection && allowedPositions.length > 0) {
         $position.val(allowedPositions[0]).trigger('change');
      }
    }

    function syncSelectFromTextarea() {
      const config = parseConfig();
      if (!config || !config.position) {
        return;
      }

      ensurePositionOption(config.position);
      $position.val(config.position);
      filterPositions();
    }

    $position.on('change', function () {
      const config = parseConfig() || {};
      config.position = $(this).val();
      $textarea.val(JSON.stringify(config));
      $textarea.trigger('change');
    });

    $textarea.on('input change', function () {
      syncSelectFromTextarea();
    });

    $offerType.on('change', function() {
      filterPositions();
    });

    syncSelectFromTextarea();
    filterPositions();
  }

  function initVisualBuilder(fieldId, isRules) {
    const $textarea = $(`#upsellbay-${fieldId}`);
    const $builder = $(`#upsellbay-builder-${fieldId}`);
    if (!$textarea.length || !$builder.length) {
      return;
    }

    let items = [];
    try {
      const val = $textarea.val();
      items = val ? JSON.parse(val) : (isRules ? [] : {});
    } catch (e) {
      items = isRules ? [] : {};
    }

    // Since placement config is an object, we convert it to an array for the builder temporarily
    if (!isRules && !Array.isArray(items)) {
      items = Object.keys(items).map(key => ({ key: key, value: items[key] }));
    }

    function render() {
      $builder.empty();
      const $table = $('<table class="widefat striped" style="margin-bottom: 10px;"></table>');

      if (items.length > 0) {
        if (isRules) {
          $table.append('<thead><tr><th style="padding: 15px 10px;">Rule Type <span class="woocommerce-help-tip" data-tip="The condition to check. For example, &quot;Cart contains product&quot; shows the offer only when the cart has that product."></span></th><th style="padding: 15px 10px;">Operator <span class="woocommerce-help-tip" data-tip="How to compare the rule value. &quot;Equals&quot; matches exactly, &quot;Greater than&quot; / &quot;Less than&quot; work for numbers like subtotal or order count."></span></th><th style="padding: 15px 10px;">Value <span class="woocommerce-help-tip" data-tip="The value to compare against. For product rules this is a product selection, for numeric rules this is a number, for roles this is a comma-separated list."></span></th><th style="padding: 15px 10px;"></th></tr></thead>');
        } else {
          $table.append('<thead><tr><th style="padding: 15px 10px;">Setting Key</th><th colspan="2" style="padding: 15px 10px;">Value</th><th style="padding: 15px 10px;"></th></tr></thead>');
        }
      }

      const $tbody = $('<tbody></tbody>');

      if (items.length === 0) {
        $tbody.append('<tr><td colspan="4" style="text-align:center;">No items configured. Leave empty for default behavior.</td></tr>');
      } else {
        items.forEach((item, index) => {
          const $tr = $('<tr></tr>');

          if (isRules) {
            let valueHtml = '';
            let valArray = Array.isArray(item.value) ? item.value : (item.value ? String(item.value).split(',') : []);

            if (['cart_product', 'exclude_if_product_in_cart', 'viewed_product'].includes(item.type)) {
              let options = valArray.map(id => `<option value="${id}" selected>#${id}</option>`).join('');
              valueHtml = `<select class="wc-product-search upsellbay-vb-val" data-index="${index}" multiple="multiple" data-placeholder="Search for a product..." aria-label="Rule value">${options}</select>`;
            } else if (item.type === 'cart_category') {
              let options = valArray.map(id => `<option value="${id}" selected>#${id}</option>`).join('');
              valueHtml = `<select class="wc-category-search upsellbay-vb-val" data-index="${index}" multiple="multiple" data-placeholder="Search for a category..." aria-label="Rule value">${options}</select>`;
            } else if (item.type === 'stock_status') {
              valueHtml = `
                <select class="upsellbay-vb-val" data-index="${index}" aria-label="Rule value">
                  <option value="" ${!item.value ? 'selected' : ''}>Any</option>
                  <option value="instock" ${item.value === 'instock' ? 'selected' : ''}>In stock</option>
                  <option value="outofstock" ${item.value === 'outofstock' ? 'selected' : ''}>Out of stock</option>
                  <option value="onbackorder" ${item.value === 'onbackorder' ? 'selected' : ''}>On backorder</option>
                </select>
              `;
            } else if (item.type === 'user_role') {
              valueHtml = `<input type="text" class="regular-text upsellbay-vb-val" data-index="${index}" value="${valArray.join(',')}" placeholder="User roles (comma separated, e.g. customer, subscriber)" aria-label="Rule value">`;
            } else if (['cart_subtotal', 'customer_order_count', 'customer_lifetime_spend'].includes(item.type)) {
              valueHtml = `<input type="number" step="any" class="regular-text upsellbay-vb-val" data-index="${index}" value="${item.value || ''}" placeholder="Number" aria-label="Rule value">`;
            } else {
              valueHtml = `<input type="text" class="regular-text upsellbay-vb-val" data-index="${index}" value="${valArray.join(',')}" placeholder="Value (comma separated for IDs)" aria-label="Rule value">`;
            }

            $tr.append(`
              <td>
                <select class="upsellbay-vb-type" data-index="${index}" aria-label="Rule type">
                  <option value="cart_product" ${item.type === 'cart_product' ? 'selected' : ''}>Cart contains product</option>
                  <option value="cart_category" ${item.type === 'cart_category' ? 'selected' : ''}>Cart contains category</option>
                  <option value="cart_tag" ${item.type === 'cart_tag' ? 'selected' : ''}>Cart contains tag</option>
                  <option value="cart_subtotal" ${item.type === 'cart_subtotal' ? 'selected' : ''}>Cart subtotal is</option>
                  <option value="viewed_product" ${item.type === 'viewed_product' ? 'selected' : ''}>Currently viewing product</option>
                  <option value="user_role" ${item.type === 'user_role' ? 'selected' : ''}>User role is</option>
                  <option value="customer_order_count" ${item.type === 'customer_order_count' ? 'selected' : ''}>Customer order count is</option>
                  <option value="customer_lifetime_spend" ${item.type === 'customer_lifetime_spend' ? 'selected' : ''}>Customer lifetime spend is</option>
                  <option value="stock_status" ${item.type === 'stock_status' ? 'selected' : ''}>Stock status is</option>
                  <option value="exclude_if_product_in_cart" ${item.type === 'exclude_if_product_in_cart' ? 'selected' : ''}>Exclude if cart contains product</option>
                </select>
              </td>
              <td>
                <select class="upsellbay-vb-op" data-index="${index}" aria-label="Rule operator">
                  <option value="eq" ${item.operator === 'eq' ? 'selected' : ''}>Equals</option>
                  <option value="neq" ${item.operator === 'neq' ? 'selected' : ''}>Does not equal</option>
                  <option value="gt" ${item.operator === 'gt' ? 'selected' : ''}>Greater than</option>
                  <option value="lt" ${item.operator === 'lt' ? 'selected' : ''}>Less than</option>
                </select>
              </td>
              <td>
                ${valueHtml}
              </td>
            `);
          } else {
            $tr.append(`
              <td>
                <input type="text" class="regular-text upsellbay-vb-key" data-index="${index}" value="${item.key || ''}" placeholder="Setting Key (e.g. location)" aria-label="Setting key">
              </td>
              <td colspan="2">
                <input type="text" class="regular-text upsellbay-vb-val" data-index="${index}" value="${item.value || ''}" placeholder="Value" aria-label="Setting value">
              </td>
            `);
          }

          $tr.append(`<td><button type="button" class="button button-link-delete upsellbay-vb-remove" data-index="${index}">Remove</button></td>`);
          $tbody.append($tr);
        });
      }

      $table.append($tbody);
      $builder.append($table);
      $builder.append(`<button type="button" class="button upsellbay-vb-add">Add ${isRules ? 'Rule' : 'Setting'}</button>`);

      $builder.find('.woocommerce-help-tip').each(function () {
        if ($.fn.tipTip) {
          $(this).tipTip({ attribute: 'data-tip', fadeIn: 50, fadeOut: 50, delay: 200 });
        }
      });

      $builder.find('.upsellbay-vb-remove').on('click', function () {
        const idx = $(this).data('index');
        items.splice(idx, 1);
        updateTextarea();
        render();
      });

      $builder.find('.upsellbay-vb-add').on('click', function () {
        if (isRules) {
          items.push({ type: 'cart_product', operator: 'eq', value: '' });
        } else {
          items.push({ key: '', value: '' });
        }
        updateTextarea();
        render();
      });

      if (isRules) {
        $(document.body).trigger('wc-enhanced-select-init');
      }

      $builder.find('input, select').on('change input', function () {
        const idx = $(this).data('index');
        if (isRules) {
          items[idx].type = $builder.find(`.upsellbay-vb-type[data-index="${idx}"]`).val();
          items[idx].operator = $builder.find(`.upsellbay-vb-op[data-index="${idx}"]`).val();
          let rawVal = $builder.find(`.upsellbay-vb-val[data-index="${idx}"]`).val();
          if (rawVal === null) rawVal = [];
          if (Array.isArray(rawVal)) {
            items[idx].value = rawVal.map(s => parseInt(s, 10)).filter(n => !isNaN(n));
          } else if (typeof rawVal === 'string' && rawVal.includes(',')) {
            items[idx].value = rawVal.split(',').map(s => parseInt(s.trim(), 10)).filter(n => !isNaN(n));
          } else {
            items[idx].value = rawVal;
          }
        } else {
          items[idx].key = $builder.find(`.upsellbay-vb-key[data-index="${idx}"]`).val();
          items[idx].value = $builder.find(`.upsellbay-vb-val[data-index="${idx}"]`).val();
        }
        updateTextarea();
      });
    }

    function updateTextarea() {
      let finalData;
      if (isRules) {
        finalData = items;
      } else {
        finalData = {};
        items.forEach(item => {
          if (item.key) finalData[item.key] = item.value;
        });
      }
      $textarea.val(JSON.stringify(finalData));
      $textarea.trigger('change');
    }

    render();
  }

  initPlacementConfig();
  initVisualBuilder('_ub_rules', true);
});

/**
 * UpsellBay Dynamic Offer Summary
 */
window.jQuery(function ($) {
  const $form = $('#upsellbay-offer-editor-form');
  const $summaryContainer = $('#upsellbay-offer-summary');

  if (!$form.length || !$summaryContainer.length) {
    return;
  }

  function updateSummary() {
    const status = $('#upsellbay-_ub_status').val() || 'draft';
    const offerType = $('#upsellbay-_ub_offer_type').val();
    const placement = offerType
      ? $('#upsellbay-_ub_offer_type option:selected').text()
      : 'Not selected';
    const discountType = $('#upsellbay-_ub_discount_type').val();
    const discountVal = parseFloat($('#upsellbay-_ub_discount_value').val()) || 0;
    
    let discountText = 'None';
    let isHighRisk = false;

    if (discountType === 'percent') {
      discountText = discountVal + '% OFF';
      if (discountVal > 50) isHighRisk = true;
    } else if (discountType === 'fixed_amount') {
      discountText = window.upsellbay_data && window.upsellbay_data.currency_symbol 
        ? window.upsellbay_data.currency_symbol + discountVal + ' OFF'
        : discountVal + ' OFF (Fixed)';
    } else if (discountType === 'fixed_price') {
      discountText = window.upsellbay_data && window.upsellbay_data.currency_symbol 
        ? 'Fixed Price: ' + window.upsellbay_data.currency_symbol + discountVal
        : 'Fixed Price: ' + discountVal;
    }

    let html = '<p style="margin: 0.5em 0;">';
    html += `<strong>Status:</strong> ${status.charAt(0).toUpperCase() + status.slice(1)} | `;
    html += `<strong>Placement:</strong> ${placement} | `;
    
    if (isHighRisk) {
      html += `<strong>Discount:</strong> <span style="color: #d63638; font-weight: bold;">⚠️ ${discountText} (High)</span>`;
    } else {
      html += `<strong>Discount:</strong> ${discountText}`;
    }
    
    html += '</p>';

    $summaryContainer.html(html).show();
    
    if (isHighRisk) {
      $summaryContainer.css('border-left-color', '#d63638');
    } else {
      $summaryContainer.css('border-left-color', '#007cba');
    }
  }

  $form.on('input change', 'input, select, textarea', function() {
    updateSummary();
  });

  // Initial render
  updateSummary();
});
