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
      if (productId) {
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
