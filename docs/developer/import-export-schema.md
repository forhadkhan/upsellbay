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

Exports omit each offer's post ID; imports always create new offers (or update an existing one matched by title, depending on the chosen duplicate-handling preference) rather than reusing site-specific IDs. `ImportExporter::import_all()` rejects any payload whose `type`/`version` don't match the envelope above.
