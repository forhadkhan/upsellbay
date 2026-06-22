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
 *
 * Initializes a single product selector element. Reused by the offer product
 * field and by the rules visual builder so both share the exact same search,
 * pagination, and selection UX.
 *
 * @param {JQuery} $selector A single element matching [data-upsellbay-product-selector].
 * @param {object} [options] Optional configuration.
 * @param {boolean} [options.fetchSelected=true] When true, pre-selected product IDs
 *                                                 are hydrated from the REST API.
 */
function initProductSelector($selector, options) {
  if (!$selector || !$selector.length) {
    return;
  }
  if ($selector.data("upsellbay-product-selector-ready")) {
    return;
  }
  $selector.data("upsellbay-product-selector-ready", true);

  const opts = Object.assign({ fetchSelected: true }, options || {});
  const $ = window.jQuery;

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

  /**
   * Hydrate a pre-selected product ID from the REST API so the selection card
   * shows the real product name, image, and price — matching the offer product
   * selector UX exactly.
   *
   * @param {number} productId Product ID to hydrate.
   */
  function hydrateSelectedProduct(productId) {
    if (!productId || productId <= 0) {
      return;
    }

    $.ajax({
      url: window.upsellbay_data.rest_url + "upsellbay/v1/products",
      type: "GET",
      data: {
        include: productId,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", window.upsellbay_data.nonce);
      },
      success: (response) => {
        if (Array.isArray(response) && response.length > 0) {
          selectProduct(response[0], true);
        }
      },
      error: () => {
        // Keep the fallback card on error — do not block editing.
      },
    });
  }

  function selectProduct(product, skipSyncInputs) {
    $input.val(product.id);
    const $priceInput = $selector.find("[data-upsellbay-product-price-input]");
    const $priceMin = $selector.find("[data-upsellbay-product-price-min-input]");
    const $priceMax = $selector.find("[data-upsellbay-product-price-max-input]");

    // Price context only applies to the offer product selector.
    if ($priceInput.length) {
      const baseMin =
        product.base_price_min_raw ||
        product.regular_price_raw ||
        product.price_min_raw ||
        product.price_raw ||
        "";
      const baseMax =
        product.base_price_max_raw ||
        product.regular_price_raw ||
        product.price_max_raw ||
        product.price_raw ||
        "";
      $priceInput.val(product.base_price_raw || baseMin || "");
      $priceMin.val(baseMin);
      $priceMax.val(baseMax);
      $priceInput.trigger("change");
      $(document).trigger("upsellbay:price-context-change");
    }

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
      .attr("data-upsellbay-product-price", product.price_raw || "")
      .addClass("is-active");

    $selection
      .find(".upsellbay-product-selector__selection-remove")
      .on("click", () => {
        $input.val("");
        $input.trigger("change");
        if ($priceInput.length) {
          $priceInput.val("");
          $priceMin.val("");
          $priceMax.val("");
          $priceInput.trigger("change");
          $(document).trigger("upsellbay:price-context-change");
        }
        $selection
          .empty()
          .removeAttr("data-upsellbay-product-price")
          .removeClass("is-active");
        $inputWrapper.show();
        $search.focus();
      });

    if (!skipSyncInputs) {
      $input.trigger("change");
    }
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

  // Hydrate a pre-selected product so the selection card matches the live UX.
  const selectedId = parseInt($input.val(), 10);
  if (opts.fetchSelected && selectedId > 0) {
    if ($selection.hasClass("is-active")) {
      // Offer product selector already renders the card server-side; only wire removal.
      $selection
        .find(".upsellbay-product-selector__selection-remove")
        .on("click", () => {
          $input.val("");
          $input.trigger("change");
          $selector.find("[data-upsellbay-product-price-input]").val("");
          $selector.find("[data-upsellbay-product-price-min-input]").val("");
          $selector.find("[data-upsellbay-product-price-max-input]").val("");
          $selector.find("[data-upsellbay-product-price-input]").trigger("change");
          $(document).trigger("upsellbay:price-context-change");
          $selection
            .empty()
            .removeClass("is-active")
            .removeAttr("data-upsellbay-product-price");
          $inputWrapper.show();
          $search.focus();
        });

      if ($selector.hasClass("upsellbay-product-selector--rule")) {
        hydrateSelectedProduct(selectedId);
      }
    } else {
      // Rules builder renders an empty selection — fetch the real product.
      hydrateSelectedProduct(selectedId);
    }
  }
}

/**
 * Initialize every product selector currently in the DOM.
 */
window.jQuery(function ($) {
  const $selectors = $("[data-upsellbay-product-selector]");
  $selectors.each(function () {
    initProductSelector($(this));
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
      const initialProductId = $form.find('input[name="_ub_offer_product_id"]').val();

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
      if (productId && productId !== "0" && productId !== initialProductId) {
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
          $form.find('[data-upsellbay-product-price-input]').val("");
          $form.find('[data-upsellbay-product-price-min-input]').val("");
          $form.find('[data-upsellbay-product-price-max-input]').val("");
          $form.find('[data-upsellbay-product-price-input]').trigger('change');
          $(document).trigger('upsellbay:price-context-change');
          $form.find('[data-upsellbay-selection]').empty().removeAttr('data-upsellbay-product-price').removeClass("is-active");
          $form.find('.upsellbay-product-selector__input-wrapper').show();
          $form.trigger('change');
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

    const $rulesField = $form.find('[name="_ub_rules"]');
    if ($rulesField.length && $rulesField.val().trim()) {
      try {
        const rules = JSON.parse($rulesField.val().trim());
        const definitions = window.upsellbay_data?.rule_definitions?.rules || {};
        if (Array.isArray(rules)) {
          rules.forEach((rule, index) => {
            const definition = definitions[rule.type];
            const value = rule.value;

            if (!definition) {
              isValid = false;
              errorMessages.push(`Rule ${index + 1} is not supported.`);
              return;
            }

            const hasValue = Array.isArray(value) ? value.length > 0 : String(value || '').trim() !== '';
            if (!hasValue) {
              isValid = false;
              errorMessages.push(`Rule ${index + 1} needs a value.`);
              return;
            }

            if (definition.valueType === 'number' && !Number.isFinite(Number.parseFloat(value))) {
              isValid = false;
              errorMessages.push(`Rule ${index + 1} needs a numeric value.`);
            }
          });
        }
      } catch (err) {
        // The JSON parser above already reports this.
      }
    }

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
 * UpsellBay offer type copy hydration.
 */
window.jQuery(function ($) {
  const $select = $('#upsellbay-_ub_offer_type');
  const $headline = $('#upsellbay-_ub_headline');
  const $buttonText = $('#upsellbay-_ub_button_text');
  const $sectionHeading = $('#upsellbay-_ub_section_heading');

  if (!$select.length) {
    return;
  }

  const copyByType = {
    checkout_bump: {
      _ub_section_heading: __('Recommended for you', 'upsellbay'),
      _ub_headline: __('Complete your order with this add-on', 'upsellbay'),
      _ub_button_text: __('Add to order', 'upsellbay'),
    },
    product_upsell: {
      _ub_section_heading: __('Recommended for you', 'upsellbay'),
      _ub_headline: __('Frequently bought with this product', 'upsellbay'),
      _ub_button_text: __('Add item', 'upsellbay'),
    },
    cart_crosssell: {
      _ub_section_heading: __('Recommended for you', 'upsellbay'),
      _ub_headline: __('Recommended for your cart', 'upsellbay'),
      _ub_button_text: __('Add to cart', 'upsellbay'),
    },
    thankyou_offer: {
      _ub_section_heading: __('Recommended for you', 'upsellbay'),
      _ub_headline: __('Add another useful item', 'upsellbay'),
      _ub_button_text: __('View offer', 'upsellbay'),
    },
  };

  let lastType = $select.val() || 'checkout_bump';

  const currentValue = ($field) => ($field.length ? String($field.val() || '') : '');

  const shouldHydrate = (value, previousType, key) => {
    const defaults = copyByType[previousType] || {};
    return '' === value || value === String(defaults[key] || '');
  };

  const hydrate = (nextType) => {
    const defaults = copyByType[nextType];

    if (!defaults) {
      lastType = nextType;
      return;
    }

    if ($sectionHeading.length && shouldHydrate(currentValue($sectionHeading), lastType, '_ub_section_heading')) {
      $sectionHeading.val(defaults._ub_section_heading).trigger('input');
    }

    if ($headline.length && shouldHydrate(currentValue($headline), lastType, '_ub_headline')) {
      $headline.val(defaults._ub_headline).trigger('input');
    }

    if ($buttonText.length && shouldHydrate(currentValue($buttonText), lastType, '_ub_button_text')) {
      $buttonText.val(defaults._ub_button_text).trigger('input');
    }

    lastType = nextType;
  };

  hydrate(String($select.val() || 'checkout_bump'));

  $select.on('change', function () {
    hydrate(String($(this).val() || ''));
  });
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

    const ruleConfig = window.upsellbay_data?.rule_definitions || {};
    const ruleDefinitions = ruleConfig.rules || {};
    const stockStatuses = ruleConfig.stockStatuses || [];
    const firstRuleType = Object.keys(ruleDefinitions)[0] || 'cart_product';

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

    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function normalizeRule(item) {
      const type = ruleDefinitions[item.type] ? item.type : firstRuleType;
      const definition = ruleDefinitions[type] || {};
      const operator = (definition.operators || []).some((op) => op.value === item.operator)
        ? item.operator
        : definition.defaultOperator;

      return {
        type,
        operator,
        value: normalizeRuleValue(definition, item.value),
      };
    }

    function normalizeRuleValue(definition, value) {
      if (definition.valueType === 'products') {
        if (Array.isArray(value)) {
          return String(value[0] || '');
        }

        return value === undefined || value === null ? '' : String(value);
      }

      if (definition.valueType === 'number' || definition.valueType === 'stock_status') {
        return value === undefined || value === null ? '' : String(value);
      }

      const rawValues = Array.isArray(value)
        ? value
        : (value === undefined || value === null || value === '' ? [] : String(value).split(','));

      if (definition.valueType === 'roles') {
        return rawValues.map((item) => String(item).trim()).filter(Boolean);
      }

      return rawValues
        .map((item) => Number.parseInt(item, 10))
        .filter((item) => Number.isFinite(item) && item > 0);
    }

    function endpointForValueType(valueType) {
      const endpoints = {
        categories: 'categories',
        tags: 'tags',
        roles: 'roles',
      };

      return endpoints[valueType] || '';
    }

    function renderRuleTypeOptions(selectedType) {
      return Object.keys(ruleDefinitions).map((type) => {
        const definition = ruleDefinitions[type];
        return `<option value="${escapeHtml(type)}" ${type === selectedType ? 'selected' : ''}>${escapeHtml(definition.label)}</option>`;
      }).join('');
    }

    function renderOperatorControl(item, definition, index) {
      if (!definition.operatorVisible) {
        return `<input type="hidden" class="upsellbay-vb-op" data-index="${index}" value="${escapeHtml(definition.defaultOperator || '')}"><span class="description">Not required</span>`;
      }

      const operators = definition.operators || [];
      return `
        <select class="upsellbay-vb-op" data-index="${index}" aria-label="Rule operator">
          ${operators.map((operator) => `<option value="${escapeHtml(operator.value)}" ${operator.value === item.operator ? 'selected' : ''}>${escapeHtml(operator.label)}</option>`).join('')}
        </select>
      `;
    }

    function renderValueControl(item, definition, index) {
      const value = item.value;
      const values = Array.isArray(value) ? value : (value ? [value] : []);

      if (definition.valueType === 'products') {
        const selectedId = values.length ? String(values[0] || '') : '';
        const isActive = '' !== selectedId && '0' !== selectedId;

        return `
          <div class="upsellbay-product-selector upsellbay-product-selector--rule" data-upsellbay-product-selector>
            <div class="upsellbay-product-selector__input-wrapper" ${isActive ? 'style="display:none;"' : ''}>
              <input
                id="upsellbay-rule-product-${index}-search"
                type="text"
                class="regular-text"
                placeholder="${escapeHtml('Search for a product...')}"
                autocomplete="off"
              >
              <button type="button" class="upsellbay-product-selector__clear" style="display: none;" title="${escapeHtml('Clear search')}">&times;</button>
            </div>
            <input
              type="hidden"
              id="upsellbay-rule-product-${index}"
              class="upsellbay-vb-val"
              data-index="${index}"
              value="${escapeHtml(selectedId)}"
            >
            <div class="upsellbay-product-selector__results" data-upsellbay-results></div>
            <div class="upsellbay-product-selector__selection${isActive ? ' is-active' : ''}" data-upsellbay-selection>
              ${isActive ? `
                <div class="upsellbay-product-selector__result-info">
                  <span class="upsellbay-product-selector__result-name">Product ID: ${escapeHtml(selectedId)}</span>
                  <span class="upsellbay-product-selector__result-meta">(Selected for this rule)</span>
                </div>
                <span class="upsellbay-product-selector__selection-remove">&times;</span>
              ` : ''}
            </div>
          </div>
        `;
      }

      if (['categories', 'tags', 'roles'].includes(definition.valueType)) {
        const endpoint = endpointForValueType(definition.valueType);
        let preloaded = {};
        try {
          const preloadedStr = $builder.attr('data-preloaded-entities');
          if (preloadedStr) {
            preloaded = JSON.parse(preloadedStr);
          }
        } catch (e) {}
        const options = values.map((selectedValue) => {
          const label = preloaded[selectedValue] || `#${selectedValue}`;
          return `<option value="${escapeHtml(selectedValue)}" selected>${escapeHtml(label)}</option>`;
        }).join('');
        return `
          <select
            class="upsellbay-vb-val upsellbay-rule-entity-search"
            data-index="${index}"
            data-endpoint="${escapeHtml(endpoint)}"
            ${definition.multiple ? 'multiple="multiple"' : ''}
            data-placeholder="${escapeHtml('Search...')}"
            aria-label="Rule value"
          >${options}</select>
        `;
      }

      if (definition.valueType === 'stock_status') {
        return `
          <select class="upsellbay-vb-val" data-index="${index}" aria-label="Rule value">
            <option value="">Select stock status</option>
            ${stockStatuses.map((status) => `<option value="${escapeHtml(status.value)}" ${status.value === value ? 'selected' : ''}>${escapeHtml(status.label)}</option>`).join('')}
          </select>
        `;
      }

      const step = definition.step || 'any';
      const min = definition.min !== null && definition.min !== undefined ? ` min="${escapeHtml(definition.min)}"` : '';
      return `<input type="number" step="${escapeHtml(step)}"${min} class="regular-text upsellbay-vb-val" data-index="${index}" value="${escapeHtml(value || '')}" placeholder="Number" aria-label="Rule value">`;
    }

    function initRuleSelectors() {
      $builder.find('.upsellbay-rule-entity-search').each(function () {
        const $select = $(this);
        const endpoint = $select.data('endpoint');
        const selectInit = $.fn.selectWoo || $.fn.select2;

        if (!endpoint || !selectInit || $select.data('upsellbay-rule-selector-ready')) {
          return;
        }

        $select.data('upsellbay-rule-selector-ready', true);
        selectInit.call($select, {
          width: 'resolve',
          placeholder: $select.data('placeholder') || 'Search...',
          minimumInputLength: 0,
          ajax: {
            url: `${window.upsellbay_data.rest_url}upsellbay/v1/${endpoint}`,
            dataType: 'json',
            delay: 250,
            beforeSend(xhr) {
              xhr.setRequestHeader('X-WP-Nonce', window.upsellbay_data.nonce);
            },
            data(params) {
              return {
                search: params.term || '',
                page: params.page || 1,
              };
            },
            processResults(response) {
              const results = Array.isArray(response) ? response.map((item) => ({
                id: item.id,
                text: item.name || item.slug || item.id,
              })) : [];

              return { results };
            },
          },
        });
      });
    }

    function render() {
      $builder.empty();
      const $table = $('<table class="widefat striped" style="margin-bottom: 10px;"></table>');

      if (items.length > 0) {
        if (isRules) {
          $table.append('<thead><tr><th style="padding: 15px 10px;">Rule type <span class="woocommerce-help-tip" data-tip="The condition to check before the offer appears."></span></th><th style="padding: 15px 10px;">Operator <span class="woocommerce-help-tip" data-tip="Shown only when the selected rule needs a comparison."></span></th><th style="padding: 15px 10px;">Value <span class="woocommerce-help-tip" data-tip="Use the selector or input required by the chosen rule type."></span></th><th style="padding: 15px 10px;"></th></tr></thead>');
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
            const normalized = normalizeRule(item);
            items[index] = normalized;
            const definition = ruleDefinitions[normalized.type] || {};
            const valueHtml = renderValueControl(normalized, definition, index);
            const operatorHtml = renderOperatorControl(normalized, definition, index);

            $tr.append(`
              <td>
                <select class="upsellbay-vb-type" data-index="${index}" aria-label="Rule type">
                  ${renderRuleTypeOptions(normalized.type)}
                </select>
              </td>
              <td>
                ${operatorHtml}
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
          const definition = ruleDefinitions[firstRuleType] || {};
          items.push({
            type: firstRuleType,
            operator: definition.defaultOperator || 'contains',
            value: definition.valueType === 'products' || definition.valueType === 'number' || definition.valueType === 'stock_status' ? '' : [],
          });
        } else {
          items.push({ key: '', value: '' });
        }
        updateTextarea();
        render();
      });

      initRuleSelectors();

      // Initialize product selectors for rule value controls
      $builder.find('[data-upsellbay-product-selector]').each(function () {
        initProductSelector($(this));
      });

      $builder.find('input, select').on('change input', function () {
        const idx = $(this).data('index');
        if (isRules) {
          const previousType = items[idx]?.type;
          const nextType = $builder.find(`.upsellbay-vb-type[data-index="${idx}"]`).val();
          const definition = ruleDefinitions[nextType] || {};

          if (previousType !== nextType && $(this).hasClass('upsellbay-vb-type')) {
            items[idx] = {
              type: nextType,
              operator: definition.defaultOperator || '',
              value: definition.valueType === 'number' || definition.valueType === 'stock_status' ? '' : [],
            };
            updateTextarea();
            render();
            return;
          }

          items[idx] = normalizeRule({
            type: nextType,
            operator: $builder.find(`.upsellbay-vb-op[data-index="${idx}"]`).val(),
            value: $builder.find(`.upsellbay-vb-val[data-index="${idx}"]`).val(),
          });
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

  function getCurrencySettings() {
    const priceDecimals = window.upsellbay_data && Object.prototype.hasOwnProperty.call(window.upsellbay_data, 'price_decimals')
      ? window.upsellbay_data.price_decimals
      : 2;

    return {
      symbol: (window.upsellbay_data && window.upsellbay_data.currency_symbol) || '$',
      position: (window.upsellbay_data && window.upsellbay_data.currency_position) || 'left',
      decimals: Number.isFinite(Number.parseInt(priceDecimals, 10)) ? Number.parseInt(priceDecimals, 10) : 2,
      decimalSeparator: (window.upsellbay_data && window.upsellbay_data.decimal_separator) || '.',
      thousandSeparator: (window.upsellbay_data && window.upsellbay_data.thousand_separator) || ',',
    };
  }

  function formatPrice(value) {
    const settings = getCurrencySettings();
    const amount = Number.parseFloat(value);

    if (!Number.isFinite(amount)) {
      return '';
    }

    const negative = amount < 0 ? '-' : '';
    const fixed = Math.abs(amount).toFixed(Math.max(settings.decimals, 0));
    const parts = fixed.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, settings.thousandSeparator);

    const number = parts.length > 1 ? parts.join(settings.decimalSeparator) : parts[0];
    const formatted = negative + number;

    switch (settings.position) {
      case 'left_space':
        return `${settings.symbol} ${formatted}`;
      case 'right_space':
        return `${formatted} ${settings.symbol}`;
      case 'right':
        return `${formatted}${settings.symbol}`;
      default:
        return `${settings.symbol}${formatted}`;
    }
  }

  function getSelectedPriceContext() {
    const $priceInput = $('[data-upsellbay-product-price-input]');
    const $minInput = $('[data-upsellbay-product-price-min-input]');
    const $maxInput = $('[data-upsellbay-product-price-max-input]');
    const fallbackPrice = $priceInput.length ? Number.parseFloat($priceInput.val()) : NaN;
    const min = $minInput.length ? Number.parseFloat($minInput.val()) : fallbackPrice;
    const max = $maxInput.length ? Number.parseFloat($maxInput.val()) : fallbackPrice;

    return {
      min: Number.isFinite(min) ? min : NaN,
      max: Number.isFinite(max) ? max : NaN,
      isRange: Number.isFinite(min) && Number.isFinite(max) && max > min,
    };
  }

  function getDiscountPreview() {
    const priceContext = getSelectedPriceContext();
    const productPrice = priceContext.min;

    if (!Number.isFinite(productPrice)) {
      return {
        state: 'empty',
        valueHtml: 'Select a product to preview the updated price.',
        note: 'The discount preview uses the selected product base price.',
      };
    }

    const discountType = $('#upsellbay-_ub_discount_type').val() || 'none';
    const discountValue = Number.parseFloat($('#upsellbay-_ub_discount_value').val()) || 0;

    let offerMin = productPrice;
    let offerMax = priceContext.isRange ? priceContext.max : productPrice;

    if (discountType === 'percent') {
      offerMin = productPrice - (productPrice * Math.min(100, Math.max(0, discountValue)) / 100);
      offerMax = priceContext.isRange
        ? priceContext.max - (priceContext.max * Math.min(100, Math.max(0, discountValue)) / 100)
        : offerMin;
    } else if (discountType === 'fixed_amount') {
      offerMin = productPrice - Math.max(0, discountValue);
      offerMax = priceContext.isRange
        ? priceContext.max - Math.max(0, discountValue)
        : offerMin;
    } else if (discountType === 'fixed_price') {
      offerMin = Math.max(0, discountValue);
      offerMax = offerMin;
    }

    offerMin = Math.max(0, offerMin);
    offerMax = Math.max(0, offerMax);

    if (priceContext.isRange && discountType === 'none') {
      return {
        state: 'regular',
        valueHtml: `<strong>${formatPrice(priceContext.min)} - ${formatPrice(priceContext.max)}</strong>`,
        note: 'Variable products show the lowest and highest variation prices until a variation is selected on the storefront.',
      };
    }

    if (!priceContext.isRange && offerMin === productPrice) {
      return {
        state: 'regular',
        valueHtml: `<strong>${formatPrice(productPrice)}</strong>`,
        note: 'No discount is applied, so the current product price is shown here.',
      };
    }

    return {
      state: 'discounted',
      valueHtml: priceContext.isRange
        ? `<del>${formatPrice(priceContext.min)} - ${formatPrice(priceContext.max)}</del> <strong>${formatPrice(offerMin)} - ${formatPrice(offerMax)}</strong>`
        : `<del>${formatPrice(productPrice)}</del> <strong>${formatPrice(offerMin)}</strong>`,
      note: priceContext.isRange
        ? 'Variable products show the lowest and highest variation prices until a variation is selected on the storefront.'
        : 'This is the price shoppers will see after the selected discount is applied.',
    };
  }

  function renderDiscountPreview() {
    const preview = getDiscountPreview();
    const html = [
      '<span class="upsellbay-discount-preview__label">Updated price</span>',
      `<span class="upsellbay-discount-preview__value" data-upsellbay-discount-preview-value>${preview.valueHtml}</span>`,
      `<span class="description upsellbay-discount-preview__note" data-upsellbay-discount-preview-note>${preview.note}</span>`,
    ].join('');

    $('[data-upsellbay-discount-preview]')
      .attr('data-upsellbay-discount-preview', preview.state)
      .attr('class', `upsellbay-discount-preview upsellbay-discount-preview--${preview.state}`)
      .html(html);
  }

  function updateSummary() {
    const status = $('#upsellbay-_ub_status').val() || 'draft';
    const offerType = $('#upsellbay-_ub_offer_type').val();
    const placement = offerType
      ? $('#upsellbay-_ub_offer_type option:selected').text()
      : 'Not selected';
    const preview = getDiscountPreview();
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
      html += `<strong>Discount:</strong> <span style="color: #d63638; font-weight: bold;">⚠️ ${discountText} (High)</span> | `;
    } else {
      html += `<strong>Discount:</strong> ${discountText} | `;
    }

    html += `<strong>Updated price:</strong> ${preview.valueHtml}`;
    html += '</p>';

    $summaryContainer.html(html).show();

    if (isHighRisk) {
      $summaryContainer.css('border-left-color', '#d63638');
    } else if (preview.state === 'discounted') {
      $summaryContainer.css('border-left-color', '#007cba');
    } else {
      $summaryContainer.css('border-left-color', '#8c8f94');
    }
    renderDiscountPreview();
  }

  $form.on('input change', 'input, select, textarea', function() {
    updateSummary();
  });
  $(document).on('upsellbay:price-context-change', function() {
    updateSummary();
  });

  // Initial render
  updateSummary();
});
