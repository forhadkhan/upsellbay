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
    const $search = $selector.find('input[type="text"]');
    const $clear = $selector.find(".upsellbay-product-selector__clear");
    const $input = $selector.find('input[type="hidden"]');
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
        url: window.upsellbay_data.ajax_url,
        type: "POST",
        data: {
          action: "upsellbay_search_products",
          nonce: window.upsellbay_data.ajax_nonce,
          search: currentSearch,
          page: page,
        },
        success: (response) => {
          isLoading = false;
          $results
            .find(
              ".upsellbay-product-selector__message, .upsellbay-product-selector__loading-more",
            )
            .remove();

          if (response && response.success) {
            hasMore = response.data.has_more;
            const products = response.data.products;

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
