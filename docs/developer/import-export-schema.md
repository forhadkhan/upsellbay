# Import and Export Schema

UpsellBay's full data export uses this envelope:

```json
{
  "type": "upsellbay_full_export",
  "version": 1,
  "settings": {
    "enabled": true
  },
  "offers": [
    {
      "title": "Checkout bump",
      "status": "draft",
      "meta": {
        "_ub_offer_type": "checkout_bump",
        "_ub_status": "draft"
      }
    }
  ]
}
```

`ImportExporter::export_all()` fires one extension filter:

- `upsellbay_export_all_payload` — adjust the assembled payload before it is JSON-encoded.

Exports omit each offer's post ID; imports always create new offers (or update an existing one matched by title, depending on the chosen duplicate-handling preference) rather than reusing site-specific IDs. Import does not remap anything. Product, category and tag IDs in an offer's `meta` (for example `_ub_offer_product_id` and the targeting rules) are imported exactly as exported, so they only point at the right items when the target store uses the same IDs. After importing onto a different store, open each offer, choose the products and targeting again, and only then enable it. SKU/product remapping on import is planned for 1.1.

`ImportExporter::import_all()` rejects any payload whose `type`/`version` don't match the envelope above.
