# API Documentation

> Complete guide for integrating your software with the WordPress License Server.

---

## Base URL

```
https://wpanchorbay.com/wp-json/license-server/v1
```

## CartBay Resources
- slug: `cartbay`
- license_key: `WPAB-6A0073F3D5F11-FF15AAA71B14`

---

## Endpoints

### 🔐 Activate License

```http
POST /activate
```

**Full URL:** `https://wpanchorbay.com/wp-json/license-server/v1/activate`

Registers a new activation for a license key on a specific domain.

#### Request Body (JSON)

```json
{
  "license_key": "WPAB-XXXXXXXXXXXX",
  "slug": "your-plugin-slug",
  "domain": "https://client-site.com",
  "ip_address": "1.2.3.4"
}
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `license_key` | string | ✅ | The license key to activate |
| `slug` | string | ✅ | Plugin or theme slug identifier |
| `domain` | string | ✅ | The domain where the license is being activated |
| `ip_address` | string | ❌ | Optional IP address for additional validation |

#### Success Response `200 OK`

```json
{
  "success": true,
  "message": "License activated successfully.",
  "license": "valid",
  "expires_at": "2025-12-31 23:59:59"
}
```

---

### ✅ Check License Status

```http
GET /check
```

**Full URL:** `https://wpanchorbay.com/wp-json/license-server/v1/check`

Validates if a license key is still active and not expired.

#### Query Parameters

| Parameter | Required | Description |
|-----------|----------|-------------|
| `license_key` | ✅ | The license key to validate |
| `slug` | ✅ | Plugin or theme slug identifier |
| `domain` | ❌ | Validate specifically for this domain |

#### Success Response `200 OK`

```json
{
  "success": true,
  "license": "valid",
  "expires_at": "2025-12-31 23:59:59",
  "activation_limit": 5
}
```

---

### 🔄 Update Checker Integration

#### Get Update Information

```http
GET /update-check/{slug}/{license_key}
```

Returns plugin update information, including changelog and temporary download link.

##### Example Response

```json
{
  "name": "CartBay",
  "slug": "cartbay",
  "version": "1.0.1",
  "download_url": "https://wpanchorbay.com/wp-json/license-server/v1/download/cartbay/WPAB-XXXXX",
  "package": "https://wpanchorbay.com/wp-json/license-server/v1/download/cartbay/WPAB-XXXXX",
  "homepage": "https://example.com",
  "requires": "5.0",
  "tested": "6.9",
  "sections": {
    "description": "...",
    "changelog": "...",
    "installation": "..."
  }
}
```

#### Download Plugin Package

```http
GET /download/{slug}/{license_key}
```

Securely serves the plugin ZIP file. Access is only granted if the license is active and valid for the requested slug.

> ⚠️ **Note:** This endpoint returns a binary file download, not JSON.

---

## Error Handling

| HTTP Status | Error Code | Meaning |
|-------------|------------|---------|
| `400` | `missing_params` | Required fields like `slug` or `license_key` are missing. |
| `403` | `license_expired` | The license term has ended. |
| `403` | `activation_limit_reached` | Maximum activations reached for this license. |
| `404` | `invalid_license` | The provided license key does not exist. |

### Error Response Format

```json
{
  "success": false,
  "error": {
    "code": "license_expired",
    "message": "Your license has expired. Please renew to continue receiving updates."
  }
}
```

---

## Implementation Tips

### 📦 WordPress Plugin Integration Example

```php
function check_license_status( $license_key, $slug, $domain ) {
    $api_url = 'https://wpanchorbay.com/wp-json/license-server/v1/check';
    
    $response = wp_remote_get( add_query_arg( [
        'license_key' => $license_key,
        'slug'        => $slug,
        'domain'      => $domain,
    ], $api_url ) );
    
    if ( is_wp_error( $response ) ) {
        return false;
    }
    
    return json_decode( wp_remote_retrieve_body( $response ), true );
}
```

### 🔐 Security Best Practices

1. **Always use HTTPS** for all API communications
2. **Validate responses** on the client side before granting access
3. **Cache license checks** locally to reduce API calls (respect `expires_at`)
4. **Sanitize all inputs** before sending to the API
5. **Handle errors gracefully** with user-friendly messages

### 🔄 Auto-Update Integration

To integrate with WordPress's native update system:

1. Use the `/update-check/{slug}/{license_key}` endpoint in your plugin's `pre_set_site_transient_update_plugins` filter
2. Parse the response and inject it into the update transient
3. WordPress will automatically display and handle the update

---

> 💡 **Need help?** Replace `wpanchorbay.com` with your actual WordPress domain when implementing these endpoints.