# Import and Export Schema

UpsellBay offer exports use this envelope:

```json
{
  "type": "upsellbay_offer_export",
  "version": 1,
  "offers": [
    {
      "title": "Checkout bump",
      "meta": {
        "_ub_offer_type": "checkout_bump",
        "_ub_status": "draft"
      },
      "product_mapping": {
        "sku": "optional-sku",
        "name": "Optional product name"
      }
    }
  ]
}
```

Phase 6 adds narrow import/export extension filters for agencies:

- `upsellbay_export_payload`
- `upsellbay_import_mapping`
- `upsellbay_import_sku_match`
- `upsellbay_import_validation_errors`
- `upsellbay_import_post_status`

These filters can adapt portable templates, but they cannot bypass authorization, nonce checks, JSON shape validation, or server-side product validation.

Exports omit site-specific product and taxonomy IDs. Imports must map products through SKU or deliberate admin mapping before offers are activated.
