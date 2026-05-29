# **UpsellBay: Product Requirements Document (v1.0)**

## *Document Status: Draft for Engineering Review*

## *Target Sprint: May 02, 2026 – July 02, 2026*

## **1\. Plugin Overview**

UpsellBay is an average order value (AOV) multiplication engine that allows store owners to display highly relevant product offers before and after checkout. Instead of relying on complex funnel builders, this plugin solves the problem of leaving money on the table by injecting simple, conversion-optimized order bumps, cross-sells, and upsells natively into the WooCommerce buying journey. This matters because increasing the value of existing traffic is significantly more profitable for store owners than acquiring new traffic.

## **2\. User Personas**

### **Persona 1: Mike the Marketer (The Funnel Optimizer)**

* **Background:** An aggressive e-commerce marketer or agency owner managing high-traffic stores. He optimizes every penny of ad spend and relies on data to drive decisions.  
* **Goals:** Increase Average Order Value (AOV) by 20%+ to offset rising customer acquisition costs (CAC). Wants to implement strict conditional logic (e.g., "If customer buys Product A, offer Product B at 15% off").  
* **Pain Points:** Existing funnel plugins are bloated, slow down checkout speeds, or force him to build custom checkout pages from scratch. He needs a native WooCommerce integration that leverages existing payment gateways smoothly without breaking the theme.

### **Persona 2: Sarah the Store Operator (The Scaling Entrepreneur)**

* **Background:** Owner of a growing $50k+/mo WooCommerce boutique. She handles inventory, support, and marketing but has zero coding skills.  
* **Goals:** Generate "free money" by offering simple, relevant add-ons (like a warranty or a matching accessory) at checkout. Needs "set it and forget it" functionality.  
* **Pain Points:** Intimidated by complex rules engines, shortcodes, and webhook setups. She needs simple, pre-designed order bump templates that look native to her theme and work flawlessly on mobile devices out-of-the-box.

## **3\. Core Features (MoSCoW)**

#### **Must Have (MVP)**

* **Checkout order bump:** Done when a shopper sees a friction-free checkbox offer on the checkout page that dynamically adds a product to the cart and recalculates the total via AJAX.  
* **Product page upsells:** Done when a store admin can display "frequently bought together" or upgrade products directly on a single product page.  
* **Cart cross-sells:** Done when complementary items are injected into the cart view based on matching current cart contents.  
* **Thank-you page offer:** Done when a post-purchase offer is presented immediately after payment on the order-received page, without blocking the initial checkout transaction.  
* **Simple rules engine:** Done when an admin can restrict offers based on specific products, specific categories, or minimum cart values.  
* **AOV and offer revenue dashboard:** Done when the plugin successfully tracks and displays offer views, offer accepts, total generated revenue, and net AOV lift.

#### **Should Have**

* Native support for the new WooCommerce Block-based checkout.  
* Basic styling customizer (colors, borders) to match the active theme.

#### **Could Have**

* Countdown timers for thank-you page offers.

#### **Won't Have (in v1)**

* Multi-step funnels.  
* AI-driven product recommendations.  
* A/B testing engine.  
* Subscription upgrade flows.  
* Advanced personalization.  
* External CRM sync.

## **4\. Key User Flows**

### **Flow 1: Configuring an Order Bump**

* Admin navigates to WP Admin → UpsellBay → New Offer.  
* Admin selects offer type "Checkout Bump".  
* Admin selects the product to offer (e.g., "Leather Care Kit").  
* Admin sets a targeting rule (e.g., "Show only if cart contains category: Shoes").  
* Admin inputs short copy, selects an image, and saves.

### **Flow 2: Shopper Accepts Checkout Bump**

* Shopper adds "Running Shoes" to the cart and proceeds to checkout.  
* UpsellBay evaluates rules and injects the "Leather Care Kit" bump above the payment gateway.  
* Shopper clicks the bump checkbox.  
* WooCommerce triggers an AJAX cart update, adding the item and adjusting the total price.  
* Shopper completes the purchase. The order meta flags the "Leather Care Kit" line item as upsellbay\_bump\_accepted.

### **Flow 3: Shopper Accepts Post-Purchase Offer**

* Shopper completes a standard checkout.  
* UpsellBay intercepts the woocommerce\_thankyou redirect and displays a post-purchase offer ("Add these matching socks for $10").  
* Shopper clicks "Add to Order".  
* Plugin leverages the payment gateway's tokenization to process the $10 charge instantly (if supported) OR appends the item to the existing order status.

## **5\. WooCommerce Integration Points**

* Hooks Used: \* woocommerce\_before\_add\_to\_cart\_form (Product Page)  
* woocommerce\_cart\_collaterals (Cart)  
* woocommerce\_review\_order\_before\_submit / woocommerce\_checkout\_order\_review (Checkout)  
* template\_redirect / woocommerce\_thankyou (Post-purchase)  
* Data Structures: \* Custom DB table for tracking offer impressions/conversions.  
* Order Line Item Meta (to flag which specific items in an order were added via UpsellBay for proper attribution).  
* Conflict Risks: \* Major conflict risk with WooCommerce Checkout Blocks (requires React-based integration rather than standard PHP hooks).  
* High risk of conflict with third-party payment gateways for the post-purchase 1-click upsell feature.

## **6\. Technical Constraints & Assumptions**

* Minimum Specs: WordPress 6.2+, WooCommerce 8.0+, PHP 7.4+.  
* Assumptions: Post-purchase "one-click" functionality will be limited strictly to gateways that support tokenization natively (e.g., Stripe, PayPal). Users on unsupported gateways will be forced through a standard secondary checkout flow.

## **7\. Success Metrics**

* Performance: The checkout order bump injection adds less than 150ms to the checkout page load time.  
* Functional Correctness: 100% accurate attribution mapping in the DB (every accepted offer correctly ties back to the exact UpsellBay rule ID and WooCommerce Order ID without double-counting).  
* Compatibility: Zero fatal errors when running alongside standard WooCommerce Subscriptions and the top 3 payment gateways (Stripe, PayPal, WooPayments).

## **8\. Open Questions & Risks**

* Placement System: Exactly which WooCommerce hooks and blocks must we support for the V1 launch to cover 80% of themes? Do we delay Block Checkout support, or is it mandatory for MVP?  
* Attribution Logic: How exactly do we connect accepted offers to order revenue in edge cases? For example, if a user accepts a post-purchase offer but the secondary payment fails, do we hold the original order, or split them into two orders?  
* Tokenization Limits: We need immediate engineering discovery on Stripe/PayPal token APIs to confirm if true "one-click" post-purchase is viable within our sprint timeframe.  
* 