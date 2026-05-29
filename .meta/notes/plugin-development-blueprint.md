# WordPress and WooCommerce Plugin Development Blueprint

This guide distills reusable architecture, implementation patterns, and engineering conventions from a production WooCommerce extension. It is written as a generalized blueprint for building future plugins with similar reliability, native-platform fit, and maintainability.

The core philosophy is simple: keep WordPress and WooCommerce as the platform, not just the host. Use native APIs for storage, settings, admin UI, emails, scheduling, hooks, logging, permissions, localization, and assets. Add custom abstractions only where they organize plugin-specific behavior or centralize rules that would otherwise drift across the codebase.

## 1. Architecture Overview

### Design Philosophy

Build the plugin as a set of small subsystems coordinated by one bootstrap layer:

- The plugin entry file performs only environment checks, constants registration, autoload loading, and activation/deactivation wiring.
- A core bootstrap class owns initialization order, service registration, and hook registration.
- Domain work lives in services, repositories, route classes, settings sections, template classes, and utility classes.
- WordPress and WooCommerce hooks are treated as boundary adapters. Hook callbacks should delegate to focused classes instead of containing large workflows inline.
- Native data models are preferred when they provide lifecycle, permissions, query, UI, and compatibility benefits.

This keeps the plugin understandable as it grows. Developers can find startup behavior in `Core`, storage behavior in `Data`, domain workflows in feature-specific services, admin behavior in `Admin`, HTTP boundaries in `Api`, and shared helpers in `Utils`.

### Recommended Directory Structure

```text
plugin-slug.php
app/
  Admin/
    Settings/
    Wizard/
  Api/
    Routes/
  Core/
    Constants.php
    Container.php
    Installer.php
    Plugin.php
    Settings.php
    Updater.php
  Data/
  DomainFeature/
  Email/
  Integrations/
  License/
  Utils/
assets/
  js/
src/
  feature-entry/
templates/
  emails/
languages/
tests/
docs/
```

Use this layout when a plugin has multiple runtime surfaces: admin UI, frontend assets, REST endpoints, scheduled jobs, WooCommerce integration, and service-layer behavior. For smaller plugins, keep the same boundaries but omit empty directories.

### Separation of Concerns

- `Core`: startup, constants, service container, install/upgrade/deactivate/uninstall, shared settings helpers, platform compatibility declarations, private updater bootstrapping.
- `Admin`: WooCommerce settings tabs, section composition, setup wizard, admin-only actions, admin notices, native admin assets.
- `Api`: REST route classes. Each class registers one route group and delegates real work to services.
- `Data`: repositories around WordPress or WooCommerce persistence APIs. Repositories hide query shape, metadata conventions, and compatibility details.
- `Domain services`: business workflows such as capture, scheduling, matching, restore, notification, reporting, or sync.
- `Email/Templates`: WooCommerce email classes, shared email base classes, placeholders, template files, and preview helpers.
- `Integrations/License`: external API clients, private updaters, and integration-specific adapters.
- `Utils`: cross-cutting helpers such as logging, token creation, rate limiting, formatting, and normalization.
- `src` and `assets`: authored JavaScript lives in `src`; committed build output lives in `assets`.
- `languages`: generated translation catalog and language files.

### Bootstrapping Flow

The preferred initialization flow is:

1. Entry file checks `ABSPATH`.
2. Entry file loads constants and Composer autoload.
3. `plugins_loaded` verifies required plugins are available.
4. Main `Plugin` singleton initializes once.
5. Bootstrap declares WooCommerce feature compatibility.
6. Services are registered in a small container.
7. WordPress, WooCommerce, REST, frontend, admin, email, and scheduler hooks are registered.
8. Private updater initializes after services and constants are available.
9. A plugin-loaded action fires for extension points.

This pattern avoids running feature code before dependencies are loaded and gives every subsystem a predictable startup order.

### Lifecycle Hooks and Execution Flow

Common lifecycle hooks to establish early:

- Activation: create default options, seed default content, register statuses/CPTs needed for rewrites, schedule recurring jobs, flush rewrite rules.
- Deactivation: unschedule recurring plugin jobs, flush rewrite rules, avoid deleting merchant/customer data unless explicitly requested.
- Runtime init: register statuses, CPTs, settings sections, route classes, email classes, frontend assets, and recurring job self-healing.
- Uninstall: respect a stored cleanup preference. Preserve data by default for commercial plugins unless the user opted into deletion.

For long-running workflows, model state transitions explicitly. A typical flow is:

```text
input boundary -> validation -> service -> repository -> state change -> event log -> scheduled follow-up -> analytics/cache invalidation
```

## 2. Native Platform Usage

### Native Admin UI

Prefer existing WordPress and WooCommerce admin surfaces before creating a standalone dashboard. For WooCommerce extensions, a WooCommerce settings tab or submenu that links to a settings tab gives users familiar navigation, save behavior, styling, help tips, and permissions.

Reusable admin choices:

- WooCommerce Settings API fields for configuration.
- `subsubsub` section navigation for settings subsections.
- `woocommerce_admin_fields()` and `woocommerce_update_options()` for render/save.
- `wc_help_tip()` for compact guidance.
- `wp-list-table`, `widefat`, `striped`, and `tablenav` patterns for list-like data.
- WooCommerce Backbone modals for admin confirmations and detail views.

Why: native admin patterns reduce custom CSS, improve accessibility consistency, lower maintenance cost, and make the plugin feel like part of WooCommerce.

### Settings APIs

Use a small number of options grouped by responsibility:

- `plugin_settings` for operational settings.
- `plugin_feature_settings` for workflow configuration.
- `plugin_license_data` for license state.
- `plugin_wizard_complete` for onboarding state.
- version markers such as `plugin_defaults_version` for upgrade-safe migrations.

Centralize normalization for checkbox values, booleans, timings, and nested settings. Settings saved by WordPress and WooCommerce often vary between strings, booleans, integers, and missing values.

### Data Stores

Choose native storage by data shape:

- WooCommerce orders via CRUD for order-like or commerce-lifecycle records.
- WooCommerce coupons via `WC_Coupon` for discount artifacts.
- Private custom post types for small admin-owned records where WordPress status, metadata, and deletion behavior are useful.
- Options for low-volume configuration.
- Transients for short-lived caches, rate limits, and correlation context.
- Files under uploads for sanitized support logs when native logs are not enough.

Avoid custom database tables until query volume, relational shape, or reporting requirements justify them. Native stores bring HPOS compatibility, built-in CRUD, admin behavior, backups, and plugin ecosystem compatibility.

### Hooks, Actions, and Filters

Treat hooks as integration boundaries. Register them centrally, but keep callbacks thin:

- Actions initiate lifecycle work or delegate scheduled jobs.
- Filters register platform extensions or adapt platform output.
- Domain services should be callable independently of the hook that triggered them.

Expose plugin-specific filters for extension points only after the internal model is stable. Useful extension points include plugin settings defaults, mail environment detection, license bypass rules, and data export filters.

### Scheduling

For WooCommerce plugins, use Action Scheduler instead of WP-Cron for business workflows. It provides persistent queues, grouped jobs, retries, visibility, and better operational behavior on stores with irregular traffic.

Patterns:

- Use recurring actions for scanners, analytics refreshes, pruning, and background checks.
- Use single actions for exact per-entity follow-up work.
- Group all jobs under the plugin slug.
- Check for existing scheduled actions before adding duplicates.
- Make callbacks idempotent and state-guarded.

### Security

Use the platform security stack consistently:

- Capabilities for authorization, especially `manage_woocommerce` for WooCommerce admin actions.
- Nonces for admin form intent, never as authorization.
- REST `permission_callback` on every route.
- Public REST routes protected with rate limiting, strict validation, and minimized side effects.
- Immediate sanitization of request input.
- Late escaping by output context.
- Opaque random tokens stored only as hashes.
- PII-safe logs: no raw tokens, full license keys, or unnecessary email addresses.

### Localization

Wrap all user-facing PHP strings with `__()`, `_e()`, `_n()`, or related functions using the plugin text domain. In JavaScript, use `@wordpress/i18n`.

Rules that scale:

- Do not concatenate translatable strings.
- Use translator comments for placeholders.
- Run `wp i18n make-pot` after changing strings.
- Keep generated assets and translation catalogs in the release artifact.

### Asset Loading

Scope assets to the exact screen or frontend context:

- Admin settings assets only on the plugin's admin screen.
- Checkout capture assets only on checkout, not order-received.
- Block Checkout assets through WooCommerce block checkout hooks.
- Use localized data only for small runtime configuration and endpoint URLs.
- Keep authored source under `src` and committed build output under `assets`.

This avoids sitewide performance cost and reduces conflicts with themes and other plugins.

### Performance

Useful performance practices:

- Cache expensive reporting in transients.
- Invalidate caches at write points instead of relying only on timed refreshes.
- Prefer exact per-entity scheduled jobs over frequent broad scans, but keep a scanner as a self-healing fallback.
- Keep public REST callbacks cheap before rate-limit and validation checks pass.
- Query through native CRUD APIs and keep limits explicit.
- Avoid global assets and global admin queries.

## 3. Reusable Development Patterns

### Bootstrap Coordinator

Purpose: one central class owns startup order and hook topology.

How it works: the entry file calls `Plugin::instance()->init()`. The bootstrap registers services, then hooks. Hook callbacks delegate to services.

Reuse when: the plugin has more than one subsystem or several hook surfaces.

Tradeoff: the bootstrap can become a hub. Keep actual business logic out of it and periodically extract hook groups when it grows too large.

### Minimal Service Container

Purpose: keep dependency construction explicit without importing a large framework.

How it works: register factories by class name, resolve singleton services through `make()`, and inject dependencies into constructors.

Reuse when: services share repositories, clients, or utilities.

Tradeoff: a custom container is simple but lacks auto-wiring, scopes, and diagnostics. Keep it small.

### Repository Around Native CRUD

Purpose: isolate persistence rules, meta keys, status handling, and query shape.

How it works: domain services call repository methods instead of calling `wc_get_orders()`, `wc_create_order()`, `get_posts()`, or metadata APIs directly.

Reuse when: a data entity has multiple lifecycle states or appears in several workflows.

Tradeoff: repositories can hide expensive queries. Document query limits and expected cardinality.

### Section-Based Settings Architecture

Purpose: split a large settings screen into maintainable sections while keeping WooCommerce-native rendering and saving.

How it works: a `SettingsPage` composes section classes. Each section implements `id()`, `label()`, `fields()`, `render()`, and `save()`.

Reuse when: a plugin has multiple configuration areas, reporting views, or operational tools.

Tradeoff: sections need shared helpers for URLs, field rendering, and environment checks to avoid duplication.

### Settings Normalization Helpers

Purpose: ensure defaults, checkbox values, nested arrays, and legacy values are interpreted consistently.

How it works: use static helpers or value objects to return normalized arrays and derived display values.

Reuse when: settings are stored as nested arrays or evolve across versions.

Tradeoff: normalization code must be used everywhere; partial adoption creates drift.

### Route Class Per REST Surface

Purpose: keep HTTP concerns separate from domain behavior.

How it works: each route class registers path, method, args, permissions, validation, and a handler that delegates to a service.

Reuse when: exposing public capture endpoints, admin analytics, licensing, testing, exports, or agent/API surfaces.

Tradeoff: many small route classes increase file count but improve reviewability.

### Event-Driven Workflow State

Purpose: make asynchronous workflows inspectable and idempotent.

How it works: services append structured events to the entity, update status/meta, cancel or queue jobs, and invalidate analytics.

Reuse when: workflows have delayed jobs, retries, restore links, notifications, or recovery/matching.

Tradeoff: entity-level event logs are easy to adopt but can grow. Add retention or external log storage if volume rises.

### Notification Tracking

Purpose: track queued, attempted, sent, failed, retry, canceled, and delivered states independently from the mail transport.

How it works: store notification records on the related entity, add a transient context key for mail callbacks, and include a custom mail header for correlation.

Reuse when: email, SMS, webhook, or agent notifications need support visibility and retry behavior.

Tradeoff: WordPress mail success means accepted for sending, not provider-confirmed delivery. Reserve a delivered state for provider callbacks.

### Template and Email Base Class

Purpose: keep email classes native to WooCommerce while sharing placeholder, preview, and rendering logic.

How it works: concrete email classes extend a shared `WC_Email` base. Templates live in `templates/emails`. The base class resolves settings, placeholders, preview data, headers, and wrappers.

Reuse when: a plugin sends multiple related transactional or lifecycle emails.

Tradeoff: WooCommerce email settings are powerful but require careful option IDs, preview hooks, and stubs for static analysis.

### Admin Page Composition

Purpose: create rich operational screens without building a separate admin application.

How it works: use native settings sections for configuration and native table/modal patterns for reporting, details, test tools, logs, and exports.

Reuse when: the plugin needs dashboards, queues, history, or support tools inside WooCommerce.

Tradeoff: complex inline admin UI can grow. Extract admin JS/CSS into source files when behavior stops being screen-local.

### Error Handling and Logging

Purpose: give operators and support staff useful context while avoiding sensitive data exposure.

How it works: services return `WP_Error` for expected failures; REST routes translate them into responses; a logger writes to WooCommerce logs and optional sanitized plugin logs.

Reuse when: workflows cross HTTP, scheduled jobs, external services, email, or checkout.

Tradeoff: dual logging is useful but requires retention, size caps, sanitization, and admin controls.

### Extensibility

Purpose: allow controlled customization without exposing internals.

How it works: add filters around defaults, environment detection, policy decisions, and output lists. Keep action names namespaced by plugin slug.

Reuse when: future integrations are likely but not yet known.

Tradeoff: public hooks become contracts. Name and document them carefully.

## 4. Reusable Features Inventory

| Feature | What it does | Dependencies | Reusability | Adaptation requirements | Extraction strategy |
| --- | --- | --- | --- | --- | --- |
| Plugin bootstrap | Initializes services, hooks, updater, and compatibility declarations | WordPress, optional WooCommerce | High | Rename namespace, constants, hooks | Extract `Core/Plugin`, `Constants`, and startup template |
| Minimal container | Registers singleton services | PHP only | High | Adjust service graph | Extract unchanged with generic messages |
| Installer/upgrader | Seeds defaults, schedules jobs, registers install-time content | WordPress, Action Scheduler when used | High | Replace options, statuses, seed content | Build a starter `Installer` with migration hooks |
| WooCommerce settings framework | Composes settings sections in Woo settings | WooCommerce Settings API | High for Woo plugins | Replace sections and field types | Extract `SettingsPage`, section interface, URL/environment helpers |
| Field renderer | Adds custom settings row types | WooCommerce admin fields | Medium | Replace row types and copy | Keep generic render methods, add plugin-specific field renderers |
| Setup wizard | Guides first-run configuration | WordPress admin, settings options | Medium | Replace steps and stored fields | Extract controller pattern and view helpers |
| REST route classes | Register public/admin endpoints | WordPress REST API | High | Replace namespace, args, services | Scaffold one route class per endpoint |
| Rate limiter | Limits public REST requests by endpoint and IP | Transients | High | Replace key prefix, limits | Extract utility with configurable prefix |
| Native data repository | Wraps Woo CRUD or WP storage | WooCommerce or WordPress APIs | Medium | Replace entity, statuses, meta keys | Create repository templates per storage type |
| Action Scheduler engine | Schedules recurring and per-entity jobs | Action Scheduler/WooCommerce | High for Woo plugins | Replace hooks and guards | Extract scheduling helper and idempotent callback pattern |
| Workflow sequence settings | Defines ordered steps, defaults, validation | WordPress options | Medium | Replace step count, delays, labels | Extract normalization and delay formatting utilities |
| Notification engine | Tracks lifecycle of notifications | Entity meta, transients, mail hooks | High | Replace entity repository and channels | Extract record schema and channel adapters |
| WooCommerce email base | Shared `WC_Email` behavior, placeholders, preview | WooCommerce mailer | High for Woo email plugins | Replace placeholders and template IDs | Extract abstract base and concrete-class template |
| Template management | Keeps editable templates tied to native email settings | Woo settings, templates | Medium | Replace presets and placeholders | Extract preset sync and preview hooks |
| Logging and support log viewer | Writes Woo logs and sanitized plugin logs | Woo logger, uploads dir | High | Replace source, subsystems, settings keys | Extract logger plus optional admin section |
| Analytics/reporting | Aggregates metrics and caches results | Repository, transients | Medium | Replace metrics and entity statuses | Extract cache/invalidation pattern; rewrite metrics |
| License/updater client | Integrates private license server and update checker | HTTP API, PUC | Medium | Replace server contract and product slug | Extract client interface and private-updater adapter |
| Agent/API access controls | Scopes machine access and audit logs | REST, auth, custom caps | Medium | Replace abilities and policy | Extract only when plugins need external automation |
| Asset build pipeline | Compiles scoped WP scripts | `@wordpress/scripts` | High | Replace entries | Extract webpack/script conventions |
| Release/QA gates | PHPCS, PHPStan, tests, i18n, build, plugin check | Composer, Node/Bun, WP-CLI | High | Replace paths and ignore codes | Extract composer/npm script baseline |

## 5. Development Standards and Conventions

### Coding Conventions

- Namespace all PHP classes under a stable vendor/plugin namespace.
- Use PSR-4 autoloading from `app/`.
- Use WordPress Coding Standards for PHP formatting.
- Use tabs for PHP indentation when following WPCS.
- Prefer early returns over nested branches.
- Add PHPDoc with `@since`, `@param`, and `@return` to public and non-obvious methods.
- Keep method names and variables in `snake_case` for WordPress-style PHP.
- Use class names in `PascalCase`; file names match class names.

### Naming Patterns

Define and never guess these identifiers:

- Namespace root.
- Text domain.
- Plugin slug.
- Option prefix.
- Meta key prefix.
- Transient prefix.
- Hook prefix.
- Nonce prefix.
- REST namespace.
- Main plugin file.
- Custom status prefixes.

Store these in constants or architecture notes so future contributors do not create parallel naming schemes.

### File Organization

- One class per file.
- Route classes under `app/Api/Routes`.
- Domain services under a feature namespace.
- Repositories under `app/Data`.
- WooCommerce emails under `app/Email`.
- Email templates under `templates/emails`.
- Authored JS under `src`; compiled JS under `assets`.
- Tests under `tests` with namespaces mirroring `app`.

### Documentation Practices

- Keep architecture notes close to the repo.
- Document actual implementation, not aspirational design.
- Update architecture notes when adding subsystems, data entities, routes, storage strategies, or hook topology.
- Add short comments only where they explain non-obvious platform constraints or tradeoffs.
- Keep release, QA, and local development commands in the repository.

### Testing and Verification

Recommended local gates:

```bash
composer phpcs
composer phpstan
composer test
bun run build
bun run i18n:make-pot
composer plugin-check
```

Run the gates that match the change. For example, PHP-only changes need PHPCS/PHPStan; string changes need i18n generation; JS changes need a build; WooCommerce checkout/order changes need runtime or integration verification when available.

### Security Best Practices

- Sanitize inputs immediately.
- Validate types and allowed values.
- Escape output late by context.
- Use capabilities before admin work.
- Use nonces on admin forms.
- Use REST permissions on every route.
- Rate-limit public write endpoints.
- Store opaque token hashes, not raw tokens.
- Avoid logging secrets and PII.
- Use WordPress HTTP API for external calls.
- Use WooCommerce CRUD for orders, products, and coupons.

### Scalability Considerations

- Keep queues idempotent.
- Use exact per-entity jobs where possible.
- Add fallback scanners for missed jobs.
- Keep report queries bounded and cached.
- Put retention controls behind settings.
- Avoid custom tables until native stores become a measurable bottleneck.
- Separate write workflows from reporting workflows.

## 6. Plugin Bootstrap Playbook

### Step 1: Define the Contract

Decide these before coding:

- Plugin slug and main file.
- Namespace root.
- Text domain.
- Option/meta/transient/hook prefixes.
- Minimum PHP, WordPress, and WooCommerce versions.
- Data storage strategy.
- Required external services and failure behavior.
- Whether the plugin is public WordPress.org, private commercial, or internal.

### Step 2: Create Foundational Files

Create:

- Main plugin file with headers, dependency checks, autoload loading, activation/deactivation hooks.
- `composer.json` with PSR-4 autoload and quality scripts.
- `package.json` when JavaScript build assets are needed.
- `phpcs.xml`, `phpstan.neon`, and PHPUnit config.
- `app/Core/Constants.php`.
- `app/Core/Plugin.php`.
- `app/Core/Container.php`.
- `app/Core/Installer.php`.
- `app/Utils/Logger.php`.

### Step 3: Establish Runtime Boundaries

Implement startup in this order:

1. Constants registration.
2. Dependency checks.
3. Autoload.
4. Bootstrap singleton.
5. Service container.
6. Hook registration.
7. Activation/deactivation.
8. Settings defaults and migrations.
9. Logging.

### Step 4: Add Storage and Settings

Create repositories before domain workflows. Define:

- Entity identity.
- Statuses or state values.
- Meta keys.
- Query methods.
- Retention behavior.
- Event log shape if needed.

Then add settings sections and normalization helpers so workflows read stable configuration.

### Step 5: Add Domain Services

Implement services around one responsibility each:

- Capture/input handling.
- Scheduling.
- Notification delivery.
- Matching or reconciliation.
- Analytics/reporting.
- External API sync.

Each service should accept sanitized values, use repositories, return predictable results, and log only safe context.

### Step 6: Add HTTP and Admin Boundaries

Add route classes and admin actions after services exist. Routes should validate and delegate. Admin actions should check capabilities, verify nonces, sanitize input, call services, and redirect with status.

### Step 7: Add Assets and Templates

Scope assets to exact screens and contexts. Keep templates override-friendly where platform conventions support it. For WooCommerce emails, use `WC_Email` and native email templates rather than direct `wp_mail()` HTML assembly.

### Step 8: Add QA and Release Tooling

Add scripts for:

- PHPCS.
- PHPStan.
- PHPUnit.
- JS build.
- i18n catalog generation.
- Plugin Check, with documented ignores for intentional private-plugin findings.
- Release packaging.

### Common Pitfalls

- Putting too much logic in the main plugin file.
- Treating nonces as authorization.
- Creating custom database tables before native stores are exhausted.
- Enqueueing admin or frontend assets globally.
- Duplicating settings normalization across services.
- Scheduling duplicate Action Scheduler jobs.
- Assuming `wp_mail_succeeded` means delivered to inbox.
- Logging raw tokens, license keys, or unnecessary PII.
- Using direct order tables instead of WooCommerce CRUD.
- Changing license metadata or updater behavior to satisfy checks that do not apply to private commercial distribution.

## 7. Code Examples

These examples are intentionally generic. Replace `Vendor\Product`, `product`, and constants with the target plugin identifiers.

### Main Plugin Entrypoint

```php
<?php
/**
 * Plugin Name: Product Plugin
 * Text Domain: product
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/app/Core/Constants.php';
\Vendor\Product\Core\Constants::register( __FILE__ );

if ( file_exists( PRODUCT_DIR . 'vendor/autoload.php' ) ) {
	require_once PRODUCT_DIR . 'vendor/autoload.php';
}

function product_init(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'product_missing_woocommerce_notice' );
		return;
	}

	\Vendor\Product\Core\Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'product_init' );

register_activation_hook( __FILE__, array( \Vendor\Product\Core\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Vendor\Product\Core\Installer::class, 'deactivate' ) );
```

### Service Registration

```php
private function register_services(): void {
	$this->container->singleton(
		OrderRepository::class,
		static fn (): OrderRepository => new OrderRepository()
	);

	$this->container->singleton(
		WorkflowService::class,
		static fn (): WorkflowService => new WorkflowService(
			self::instance()->container()->make( OrderRepository::class )
		)
	);
}
```

### Hook Registration

```php
private function register_hooks(): void {
	add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );
	add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	add_action( 'product_process_queue', array( $this->workflow_service(), 'process_queue' ) );
	add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

	$this->settings_page()->register_hooks();
}
```

### Settings Section Contract

```php
interface SettingsSectionInterface {
	public function id(): string;
	public function label(): string;

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function fields(): array;

	public function render(): void;
	public function save(): void;
}
```

### WooCommerce Settings Section

```php
final class CaptureSection extends AbstractSettingsSection {
	public function id(): string {
		return 'capture';
	}

	public function label(): string {
		return __( 'Capture', 'product' );
	}

	public function fields(): array {
		return array(
			array(
				'title' => __( 'Capture', 'product' ),
				'type'  => 'title',
				'id'    => 'product_capture_options',
			),
			array(
				'title'   => __( 'Enable capture', 'product' ),
				'id'      => 'product_settings[capture_enabled]',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'product_capture_options',
			),
		);
	}
}
```

### REST Route Class

```php
final class CaptureRoute {
	public function __construct( private CaptureService $service ) {}

	public function register(): void {
		register_rest_route(
			'product/v1',
			'/capture',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'email' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
				),
			)
		);
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! RateLimiter::check( 'capture' ) ) {
			$response = new WP_REST_Response( array( 'code' => 'rate_limited' ), 429 );
			$response->header( 'Retry-After', '600' );
			return $response;
		}

		$email = sanitize_email( $request->get_param( 'email' ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'product' ), array( 'status' => 422 ) );
		}

		return new WP_REST_Response( $this->service->capture( $email ), 201 );
	}
}
```

### Action Scheduler Registration

```php
public static function schedule_recurring_jobs(): void {
	if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
		return;
	}

	if ( ! as_has_scheduled_action( 'product_refresh_analytics', array(), 'product' ) ) {
		as_schedule_recurring_action( time(), HOUR_IN_SECONDS, 'product_refresh_analytics', array(), 'product' );
	}
}
```

### Repository Wrapper

```php
final class SessionRepository {
	public function get( int $id ): ?WC_Order {
		$order = wc_get_order( absint( $id ) );
		return $order instanceof WC_Order ? $order : null;
	}

	public function update_meta( int $id, array $meta ): bool {
		$order = $this->get( $id );
		if ( ! $order ) {
			return false;
		}

		foreach ( $meta as $key => $value ) {
			$order->update_meta_data( '_product_' . sanitize_key( $key ), $value );
		}

		$order->save();
		return true;
	}
}
```

### Token Helper

```php
final class TokenHelper {
	public static function create(): string {
		return wp_generate_password( 64, false );
	}

	public static function hash( string $token ): string {
		return hash( 'sha256', $token );
	}
}
```

### WooCommerce Email Base

```php
abstract class AbstractProductEmail extends WC_Email {
	public function trigger( int $entity_id, string $action_url ): bool {
		$this->setup_locale();

		$this->object    = wc_get_order( $entity_id );
		$this->recipient = $this->object ? $this->object->get_billing_email() : '';

		if ( '' === $this->recipient ) {
			$this->restore_locale();
			return false;
		}

		$sent = $this->send(
			$this->get_recipient(),
			$this->get_subject(),
			$this->get_content_html(),
			$this->get_headers(),
			$this->get_attachments()
		);

		$this->restore_locale();
		return (bool) $sent;
	}
}
```

## 8. Lessons and Optimization Opportunities

### What Worked Well

- Native WooCommerce settings and email surfaces reduced the amount of custom UI and made advanced features feel familiar.
- A small container gave enough dependency management without framework weight.
- Section-based settings kept a large admin surface maintainable.
- Action Scheduler handled delayed workflows better than ad hoc cron.
- Repository wrapping made HPOS-safe order storage easier to enforce.
- Central settings normalization prevented stale installs and UI saves from creating malformed workflow settings.
- Entity-level event logs and notification records made asynchronous behavior debuggable.
- Cache invalidation at write points kept analytics responsive.
- Scoped assets avoided unnecessary frontend and admin cost.
- Keeping private updater and license behavior explicit avoided accidental WordPress.org-oriented changes.

### What To Improve Next Time

- Split very large bootstrap hook registration into smaller registrar classes once the hook list grows.
- Move substantial inline admin JavaScript and CSS into authored assets earlier.
- Define route base classes or traits for repeated permission, logging, and response handling.
- Add dedicated integration tests around scheduled workflows, REST routes, and WooCommerce email preview behavior.
- Introduce typed value objects for complex settings once normalization grows beyond one helper class.
- Add a generic export/table abstraction for repeated admin tables.
- Make notification channels pluggable earlier if email, SMS, webhook, or provider callbacks are likely.
- Add operational health checks for Action Scheduler, mail transport, and license connectivity.

### Starter Kit Candidates

The most reusable internal framework pieces are:

- Core bootstrap and constants template.
- Minimal container.
- Installer/upgrader/scheduler template.
- WooCommerce settings page framework.
- REST route scaffold.
- Rate limiter.
- Logger and support log section.
- Action Scheduler helper.
- WooCommerce email base class.
- Notification lifecycle service.
- Settings normalization utilities.
- Composer, PHPCS, PHPStan, PHPUnit, i18n, and build script baseline.

Package these as an internal starter kit with placeholders for namespace, slug, text domain, option prefix, REST namespace, and product-specific services. Keep business logic out of the starter kit; include only platform wiring, reusable contracts, and proven workflow skeletons.
