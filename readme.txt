=== UpsellBay Pro ===
Contributors: wpanchorbay
Tags: order bump, woocommerce upsell, upsells, aov, checkout upsell
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
WC requires at least: 10.8
WC tested up to: 11.2
Requires Plugins: woocommerce, upsellbay-order-bump-for-woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add-on for UpsellBay Order Bump for WooCommerce. Adds product page, cart and thank-you page offers, extra analytics and import/export.

== Description ==

UpsellBay Pro is an add-on. It requires the free plugin "UpsellBay Order Bump for WooCommerce" (version 1.0.1 or newer) to be installed and active, along with WooCommerce. The free plugin supplies the checkout order bump, the offer editor and the shared settings. Pro adds the other placements on top of it.

UpsellBay does not replace your checkout. Offers are added to the WooCommerce pages your store already uses.

= What UpsellBay Pro adds =

* Product page offers - Show an add-on offer on the product page, after the add to cart button. You choose the offered products yourself.
* Cart offers - Show cross-sell offers below the cart table. An offer can be given the "threshold helper" goal when its job is to help a shopper reach a spending target.
* Thank-you page offers - Show a follow-on offer on the order received page. Accepting it adds the product to a new cart and starts a separate checkout. The completed order is not changed or charged again.
* Extra offer analytics - Views, accepts and dismissals are counted per offer and per placement, as aggregate figures.
* Import and export - Export your offers and settings to a JSON file and import them on another store, with a preview before the import is applied.
* Licence handling - Enter and manage your licence key under WooCommerce > UpsellBay > Settings > License.

Pro offers use the same editor, targeting rules and server-side pricing as the free plugin. The price a shopper pays is calculated on the server from the saved offer, not from anything the browser sends.

= What Pro does not do =

* It does not charge a card in one click after checkout. Thank-you page offers always go through a normal, separate checkout.
* It does not pick products for you. Every offered product is chosen by the merchant.
* It does not remap product, category or tag IDs on import. After importing onto a different store, open each offer and choose its products and rules again before you turn it on.
* It has no abandoned cart recovery, emails or SMS.

== Installation ==

Requirements: WordPress 6.8, PHP 8.1, WooCommerce 10.8 or newer, and the free UpsellBay Order Bump for WooCommerce plugin, version 1.0.1 or newer.

1. Install and activate WooCommerce.
2. Install and activate "UpsellBay Order Bump for WooCommerce" from Plugins > Add New.
3. Upload the UpsellBay Pro ZIP at Plugins > Add New > Upload Plugin, then click "Activate Plugin".
4. Open WooCommerce > UpsellBay. Product page, cart and thank-you page offer types are now available when you add an offer.
5. To enter your licence key, go to Settings > License on the same screen.

Test mode, from the free plugin's settings, lets store managers preview offers before shoppers see them.

== Frequently Asked Questions ==

= Do I need the free plugin? =

Yes. UpsellBay Pro does not run on its own. Install and activate UpsellBay Order Bump for WooCommerce, version 1.0.1 or newer, first.

= Does it work without CartBay? =

Yes. UpsellBay Pro is a separate product and does not use CartBay data, settings or sessions.

= Is a thank-you offer a one-click purchase? =

No. Accepting a thank-you offer adds the product to a new cart and sends the customer to a separate checkout. The original order is never changed.

= What data does it store? =

Offer statistics are daily aggregate counts per offer, with no customer names, emails or addresses. Pro also keeps a diagnostic log of refused or failed requests. That log stores the visitor's IP address only in truncated form (IPv4 to /24, IPv6 to /48), and it does not store the user agent.

= Does it contact an external service? =

Only to activate and check your licence, and to look for Pro plugin updates once a licence key is saved. These requests go to the WPAnchorBay licence server and send the licence key, the product slug and your site's domain. Update checks also send the installed Pro version, your PHP version and your site's language, and WordPress adds its usual User-Agent with your site address. Without a saved licence key, Pro makes no update checks. Offers are still shown if the licence server is unreachable. Pro has no other telemetry.

= What happens when I delete the plugin? =

Your data is kept by default. Pro data is removed only if you turned on the data cleanup setting before deleting. Even then, offers and the shared settings option belong to the free plugin and are not deleted.

= My site is behind a CDN or reverse proxy and visitors share one IP. What should I do? =

Pro rate-limits its public requests by visitor IP. By default it reads REMOTE_ADDR, which behind a proxy is the proxy's address. Use the `upsellbay_pro_rate_limit_client_ip` filter to return the real visitor IP, and only from a header your own proxy sets. Do not use a header a browser can send. The same address, truncated, is what the log records.

= My pages are served from a full-page cache. Why do offers fail to be accepted? =

Accepting an offer needs a session token. On the storefront the token comes from a cart fragment. If your cache serves a stale page and the cart fragment is not allowed to run, no fresh token is issued. Make sure WooCommerce cart fragments are not blocked on pages that show offers.

= Where can I get help? =

See the [UpsellBay documentation](https://docs.wpanchorbay.com/upsellbay/) or the [WPAnchorBay support portal](https://wpanchorbay.com/support/).

== Changelog ==

= 1.0.0 =

* Initial release of UpsellBay Pro as an add-on to UpsellBay Order Bump for WooCommerce 1.0.1 or newer.
* Added product page offers, cart offers and thank-you page follow-on offers.
* Thank-you page offers start a separate follow-on checkout. A one-click charge is not part of this release.
* Added per-placement offer analytics, including views, accepts and dismissals.
* Added settings and offer import and export with a preview step.
* Added licence activation, status and deactivation under Settings > License.
* Public offer requests are rate-limited, and log entries keep only a truncated IP address.
* Added the `upsellbay_pro_rate_limit_client_ip` filter for sites behind a proxy.

== Upgrade Notice ==

= 1.0.0 =

First release. Requires the free UpsellBay Order Bump for WooCommerce plugin, version 1.0.1 or newer, to be active.
