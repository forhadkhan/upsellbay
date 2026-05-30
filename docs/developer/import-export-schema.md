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

Exports omit site-specific product and taxonomy IDs. Imports must map products through SKU or deliberate admin mapping before offers are activated.
