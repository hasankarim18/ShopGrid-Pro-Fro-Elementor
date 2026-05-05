# WooCommerce Elementor Addon — Complete Documentation

---

## Table of Contents

1. [Big Picture — What Was Built & Why](#1-big-picture)
2. [Step-by-Step Build Workflow](#2-step-by-step-build-workflow)
3. [Full File & Folder Structure Explained](#3-file--folder-structure)
4. [PHP Backend — Every File & Function Explained](#4-php-backend)
   - 4.1 Main Entry Point (`woo-elementor-addon.php`)
   - 4.2 Bootstrap Class (`Main.php`)
   - 4.3 REST API Router (`RestAPI.php`)
   - 4.4 Products Controller (`ProductsController.php`)
   - 4.5 Cart Controller (`CartController.php`)
   - 4.6 Wishlist Controller (`WishlistController.php`)
   - 4.7 Product Query Engine (`ProductQuery.php`)
   - 4.8 Wishlist Table (`WishlistTable.php`)
   - 4.9 Wishlist Repository (`WishlistRepository.php`)
   - 4.10 Wishlist Sync (`WishlistSync.php`)
   - 4.11 Assets Manager (`AssetsManager.php`)
   - 4.12 Grid Widget (`ProductGridWidget.php`)
   - 4.13 List Widget (`ProductListWidget.php`)
   - 4.14 Uninstall Script (`uninstall.php`)
5. [JavaScript Frontend — Every Function Explained](#5-javascript-frontend)
   - 5.1 Configuration
   - 5.2 `apiFetch()` utility
   - 5.3 `debounce()` utility
   - 5.4 `PWToast` — Toast Notification System
   - 5.5 `PWWishlist` — Wishlist Store
   - 5.6 `PWQuickView` — Quick View Modal
   - 5.7 Helper functions
   - 5.8 Cart Counter Sync
   - 5.9 `ProductWidget` Class — Full Breakdown
6. [CSS Architecture Explained](#6-css-architecture)
7. [How Everything Connects — Data Flow Diagrams](#7-data-flow)
8. [Security Measures Explained](#8-security)
9. [WordPress Concepts Used — Beginner Reference](#9-wordpress-concepts)

---

## 1. Big Picture

### What this plugin is

This is a **WooCommerce Elementor Addon** — a plugin that adds new drag-and-drop widgets to the Elementor page builder. Those widgets display WooCommerce products in real time **without ever reloading the page**.

Think of it as a mini React-style frontend application embedded inside a WordPress page, but built entirely with vanilla JavaScript and PHP.

### The core problem it solves

By default, WooCommerce product pages reload the entire page when you filter, search, or paginate. This plugin replaces that old behaviour with an AJAX-first experience — the page stays the same, only the product area changes.

### The three main layers

```
┌─────────────────────────────────────┐
│   ELEMENTOR EDITOR (Drag & Drop)    │  ← PHP: Widget controls
├─────────────────────────────────────┤
│   FRONTEND PAGE (Browser)           │  ← JavaScript: ProductWidget class
├─────────────────────────────────────┤
│   WORDPRESS REST API                │  ← PHP: Controllers, Query, DB
└─────────────────────────────────────┘
```

Every user action (search, filter, paginate, wishlist, cart) flows:

```
User clicks/types → JavaScript updates state → Calls REST API → PHP queries DB → Returns JSON → JavaScript redraws UI
```

---

## 2. Step-by-Step Build Workflow

Here is the exact order of thinking and building, from start to finish.

---

### Step 1 — Define the Plugin Entry Point

**File:** `woo-elementor-addon.php`

Every WordPress plugin needs one main PHP file at its root. WordPress reads the comment block at the top (Plugin Name, Version, etc.) to recognise and list the plugin in WP Admin.

The first decisions made here were:
- Define global constants (`PW_VERSION`, `PW_PATH`, `PW_URL`) so every file knows where the plugin lives
- Write a PSR-4 autoloader so we never need to manually `require` PHP files
- Hook into `plugins_loaded` to boot the plugin only after WordPress + all other plugins are ready
- Check that Elementor and WooCommerce are both active before doing anything else
- Register an activation hook that creates the wishlist database table the moment the plugin is activated

---

### Step 2 — Write the Autoloader

Instead of writing `require 'src/App/API/ProductsController.php'` hundreds of times, PHP's `spl_autoload_register` was used. It intercepts every `new ClassName()` call and automatically finds the right file based on the namespace.

The rule is simple: `PW\App\API\ProductsController` → look in `src/App/API/ProductsController.php`.

---

### Step 3 — Create the Bootstrap Class (Main.php)

This is the **single entry point** for all plugin systems. When WordPress calls `Main::instance()`:
- It registers the Elementor widgets
- It boots the REST API
- It boots the assets (CSS/JS) manager
- It boots the wishlist sync system

The Singleton pattern (`private static $instance`) ensures Main only runs once per page load, never twice.

---

### Step 4 — Design the REST API

Before writing any frontend, the API contract was designed first. This is the correct approach — frontend and backend communicate via a defined JSON contract. Four endpoints were defined:

| Endpoint | Method | Purpose |
|---|---|---|
| `/wp-json/pw/v1/products` | GET | Fetch products with filters |
| `/wp-json/pw/v1/cart/add` | POST | Add a simple product to cart |
| `/wp-json/pw/v1/wishlist/toggle` | POST | Add/remove wishlist (logged-in) |
| `/wp-json/pw/v1/wishlist` | GET | Get all wishlisted product IDs |
| `/wp-json/pw/v1/wishlist/sync` | POST | Sync guest wishlist after login |

---

### Step 5 — Write the Product Query Engine

`ProductQuery::run()` is the heart of the backend. It takes parameters (page, search, category, sort), builds a `WP_Query`, and returns products + pagination. A 60-second transient cache was added so repeated identical queries don't hit the database each time.

---

### Step 6 — Write the Controllers

Three controllers were written, each handling one domain:
- `ProductsController` — formats raw WP_Query results into clean JSON the frontend can use
- `CartController` — adds products to WooCommerce's cart via `WC()->cart->add_to_cart()`
- `WishlistController` — toggles wishlist state and fetches the wishlist

---

### Step 7 — Build the Wishlist Database System

Three files for three responsibilities:
- `WishlistTable.php` — creates the `wp_pw_wishlist` database table
- `WishlistRepository.php` — all SQL operations (add, remove, check, get)
- `WishlistSync.php` — special endpoint that fires after a guest logs in, syncing localStorage data to the database

---

### Step 8 — Build the Elementor Widgets

Two Elementor widgets were built by extending `\Elementor\Widget_Base`:
- `ProductGridWidget` — shows products in a CSS grid (2, 3, 4, or 5 columns)
- `ProductListWidget` — shows products in a vertical list (image + text side by side)

Both widgets do the same thing in their `render()` method: output a single `<div>` with a `data-settings` attribute containing all the Elementor control values as JSON. The JavaScript picks this up and runs everything from there.

---

### Step 9 — Build the Frontend Engine (widget.js)

This is the largest file and the most complex part. It is structured as a self-executing function (IIFE) containing:

- `apiFetch()` — all HTTP calls to the REST API
- `debounce()` — prevents search from firing on every keypress
- `PWToast` — global toast notification system
- `PWWishlist` — wishlist state management (guest localStorage + logged-in DB)
- `PWQuickView` — modal popup for simple products
- `ProductWidget` class — one instance per widget on the page, fully isolated state

---

### Step 10 — Write the CSS

The CSS was written using CSS custom properties (variables) for theming, with styles for:
- Grid and list card layouts
- Skeleton loading placeholders
- Toast notifications
- Quick view modal
- Pagination
- Responsive breakpoints for mobile/tablet/desktop

---

### Step 11 — Write the Uninstall Script

`uninstall.php` runs automatically when a user **deletes** the plugin from WP Admin (not just deactivates it). It drops the wishlist table and cleans up all transients created by the plugin.

---

## 3. File & Folder Structure

```
woo-elementor-addon/
│
├── woo-elementor-addon.php          ← Main plugin file (WordPress reads this)
├── composer.json                    ← Package metadata + autoload map
├── uninstall.php                    ← Cleanup when plugin is deleted
│
├── assets/
│   ├── css/
│   │   └── widget.css               ← All frontend styles
│   └── js/
│       └── widget.js                ← All frontend JavaScript
│
├── src/                             ← All PHP classes live here
│   ├── Main.php                     ← Bootstrap / singleton entry
│   └── App/
│       ├── API/
│       │   ├── RestAPI.php          ← Registers all REST routes
│       │   ├── ProductsController.php ← Handles /products endpoint
│       │   ├── CartController.php   ← Handles /cart/add endpoint
│       │   └── WishlistController.php ← Handles /wishlist endpoints
│       ├── Query/
│       │   └── ProductQuery.php     ← Builds and runs WP_Query
│       ├── Wishlist/
│       │   ├── WishlistTable.php    ← Creates DB table on activation
│       │   ├── WishlistRepository.php ← SQL operations (CRUD)
│       │   └── WishlistSync.php     ← Guest-to-DB sync on login
│       ├── Assets/
│       │   └── AssetsManager.php    ← Enqueues CSS and JS
│       ├── ProductGridType/
│       │   └── ProductGridWidget.php ← Elementor Grid widget
│       └── ProductListType/
│           └── ProductListWidget.php ← Elementor List widget
│
└── vendor/                          ← Reserved for Composer packages (empty now)
```

**Why this structure?**
Each folder represents one domain of responsibility. This is called the **Single Responsibility Principle** — each file does one job. If you need to change how products are queried, you only touch `ProductQuery.php`. If you need to change how cart works, you only touch `CartController.php`. Nothing is tangled together.

---

## 4. PHP Backend

---

### 4.1 Main Entry Point — `woo-elementor-addon.php`

This file has four jobs:

#### Constants
```php
define( 'PW_VERSION', '1.0.0' );
define( 'PW_FILE', __FILE__ );
define( 'PW_PATH', plugin_dir_path( __FILE__ ) );
define( 'PW_URL',  plugin_dir_url( __FILE__ ) );
```
- `PW_PATH` — absolute filesystem path to the plugin folder (used to find PHP files)
- `PW_URL` — web URL to the plugin folder (used to load CSS/JS in browsers)
- These are defined once and used everywhere, so if the plugin moves, only this file needs updating

#### The Autoloader
```php
spl_autoload_register( function ( $class ) {
    $prefix = 'PW\\';
    $base   = PW_PATH . 'src/';

    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return; // Not our class, ignore it
    }

    $relative = substr( $class, strlen( $prefix ) );
    $file     = $base . str_replace( '\\', '/', $relative ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );
```
- `spl_autoload_register` tells PHP: "When you can't find a class, call this function before giving up"
- `strncmp` checks if the class name starts with `PW\` — if not, it's not our class so we skip it
- `substr` strips the `PW\` prefix off the class name
- `str_replace( '\\', '/', $relative )` converts namespace separators to folder separators
- Result: `PW\App\API\ProductsController` → `src/App/API/ProductsController.php`

#### Dependency Checks
```php
add_action( 'plugins_loaded', function () {
    if ( ! did_action( 'elementor/loaded' ) ) { ... return; }
    if ( ! class_exists( 'WooCommerce' ) ) { ... return; }
    \PW\Main::instance();
} );
```
- `plugins_loaded` fires after ALL plugins have loaded — safe to check if Elementor and WooCommerce exist
- `did_action( 'elementor/loaded' )` — Elementor fires this action when it's ready
- `class_exists( 'WooCommerce' )` — WooCommerce registers this class when active
- If either is missing, an admin error notice is shown and the plugin stops loading

#### Activation Hook
```php
register_activation_hook( __FILE__, function () {
    \PW\App\Wishlist\WishlistTable::create();
} );
```
- Runs **once** when the admin clicks "Activate Plugin"
- Creates the `wp_pw_wishlist` database table
- `register_activation_hook` must be called from the main plugin file directly — it won't work from inside a class or nested file

---

### 4.2 Bootstrap Class — `src/Main.php`

#### Singleton Pattern
```php
private static ?Main $instance = null;

public static function instance(): self {
    if ( null === self::$instance ) {
        self::$instance = new self();
    }
    return self::$instance;
}
```
- `$instance` is a static property — it belongs to the class, not to any individual object
- First time `instance()` is called, `$instance` is null, so it creates a new `Main` object
- Every subsequent call returns the same object that was already created
- This guarantees the plugin only boots once, even if `instance()` is called multiple times

#### `private function __construct()`
- The constructor is private so nobody can do `new Main()` from outside — they must go through `instance()`
- Immediately calls `init()`

#### `private function init()`
```php
add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
App\API\RestAPI::boot();
App\Assets\AssetsManager::boot();
App\Wishlist\WishlistSync::boot();
```
- Hooks `register_widgets` into Elementor's widget registration system
- Calls `::boot()` on each subsystem — these are static methods that hook themselves into WordPress

#### `public function register_widgets()`
```php
public function register_widgets( \Elementor\Widgets_Manager $manager ): void {
    $manager->register( new App\ProductGridType\ProductGridWidget() );
    $manager->register( new App\ProductListType\ProductListWidget() );
}
```
- Elementor passes its `Widgets_Manager` instance to this method
- We register both our custom widget classes with it
- After this, both widgets appear in the Elementor panel under "WooCommerce Elements"

---

### 4.3 REST API Router — `src/App/API/RestAPI.php`

#### `public static function boot()`
```php
add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
```
- `rest_api_init` is a WordPress action that fires when the REST API is being initialised
- We attach our route registration to it

#### `public static function register_routes()`

This registers all five API endpoints. Each route definition has four parts:

1. **Namespace + path** — `'pw/v1'` + `'/products'` → `/wp-json/pw/v1/products`
2. **Methods** — `READABLE` means GET only; `CREATABLE` means POST only
3. **Callback** — which PHP function handles requests to this route
4. **Permission callback** — who is allowed to call this route
5. **Args** — what parameters are accepted, their types, defaults, and constraints

**Products route:**
```php
'permission_callback' => '__return_true',
```
Public — anyone can fetch products, including guests.

**Wishlist routes:**
```php
'permission_callback' => function () {
    return is_user_logged_in();
},
```
Protected — only logged-in users can toggle or fetch their wishlist. If a guest calls this, WordPress automatically returns a 401 Unauthorized response.

**Args validation:** The `args` array tells WordPress to automatically validate and sanitise parameters before the callback runs. For example, `'type' => 'integer'` means if someone passes `page=abc`, WordPress rejects it before our code even sees it.

---

### 4.4 Products Controller — `src/App/API/ProductsController.php`

#### `public static function get_products( WP_REST_Request $request )`

This is called by WordPress when `GET /wp-json/pw/v1/products` is requested.

```php
$params = [
    'page'     => (int) $request->get_param( 'page' ),
    'search'   => sanitize_text_field( $request->get_param( 'search' ) ),
    ...
];
```
- `$request->get_param()` retrieves URL query parameters safely
- Each value is cast or sanitised: `(int)` forces integer, `sanitize_text_field()` strips HTML tags and extra whitespace

```php
$query_result = ProductQuery::run( $params );
```
Delegates the actual database query to the Query Engine (separation of concerns).

```php
$user_id      = get_current_user_id();
$wishlist_ids = $user_id ? WishlistRepository::get_product_ids( $user_id ) : [];
```
- `get_current_user_id()` returns 0 for guests, so the ternary returns an empty array for guests
- For logged-in users, fetches all their wishlisted product IDs in a single query (not one per product)

```php
$products = array_map( function ( $post ) use ( $wishlist_ids ) {
    return self::format_product( $post->ID, $wishlist_ids );
}, $query_result['posts'] );
```
- `array_map` transforms every WP_Post object into a clean array using `format_product()`
- `use ( $wishlist_ids )` passes the wishlist array into the closure (PHP closures don't inherit outer variables automatically)

#### `private static function format_product( int $product_id, array $wishlist_ids )`

Converts a raw WooCommerce product into a flat JSON-ready array.

```php
$product = wc_get_product( $product_id );
```
`wc_get_product()` is WooCommerce's function to get a product object from an ID.

```php
$type = $product->is_type( 'variable' ) ? 'variable' : 'simple';
```
Variable products have multiple variants (size, colour etc.) and cannot be added to cart directly — they need the product page. Simple products have one variant and can be added directly.

```php
$image_url = $image_id
    ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' )
    : wc_placeholder_img_src( 'woocommerce_thumbnail' );
```
- If the product has an image, get the URL at WooCommerce's standard thumbnail size
- If not, use WooCommerce's built-in placeholder image

```php
'wishlist' => in_array( $product_id, $wishlist_ids, true ),
```
- The `true` third argument makes this a strict comparison (checks both value AND type)
- Returns `true` or `false` — the frontend uses this to show the heart as filled or empty

---

### 4.5 Cart Controller — `src/App/API/CartController.php`

#### `public static function add_to_cart( WP_REST_Request $request )`

Handles `POST /wp-json/pw/v1/cart/add`.

```php
if ( $product->is_type( 'variable' ) ) {
    return rest_ensure_response( [
        'success' => false,
        'message' => 'Variable products must be configured on the product page.',
    ] );
}
```
Variable products are blocked here as a safety measure. Even if the JavaScript already prevents the button from showing, the backend also enforces this rule — defence in depth.

```php
if ( ! WC()->cart ) {
    wc_load_cart();
}
```
The WooCommerce cart is not always loaded during REST API requests (it's normally only loaded for regular page views). `wc_load_cart()` forces it to initialise.

```php
$added = WC()->cart->add_to_cart( $product_id, $quantity );
```
`add_to_cart()` returns the cart item key (a string) on success, or `false` on failure.

```php
WC()->cart->calculate_totals();

return rest_ensure_response( [
    'cart_count' => WC()->cart->get_cart_contents_count(),
] );
```
- `calculate_totals()` recalculates prices and taxes after adding the item
- `get_cart_contents_count()` returns the total number of items in the cart
- This count is sent back so JavaScript can update the cart counter in the header

---

### 4.6 Wishlist Controller — `src/App/API/WishlistController.php`

#### `public static function toggle( WP_REST_Request $request )`

```php
$already = WishlistRepository::is_wishlisted( $user_id, $product_id );

if ( $already ) {
    WishlistRepository::remove( $user_id, $product_id );
    $wishlisted = false;
} else {
    WishlistRepository::add( $user_id, $product_id );
    $wishlisted = true;
}
```
Toggle logic: check the current state, then do the opposite. Returns the new state to the frontend.

#### `public static function get_wishlist( WP_REST_Request $request )`

Returns all product IDs the user has wishlisted. The frontend uses this on page load to correctly show hearts on products that were wishlisted in a previous session.

---

### 4.7 Product Query Engine — `src/App/Query/ProductQuery.php`

#### `public static function run( array $params ): array`

This is the database query layer. It builds a `WP_Query` argument array step by step.

**Base query:**
```php
$args = [
    'post_type'   => 'product',    // WooCommerce products
    'post_status' => 'publish',    // Only live products, not drafts
    'posts_per_page' => $per_page,
    'paged'       => $page,        // Which page of results
];
```

**Search:**
```php
if ( ! empty( $search ) ) {
    $args['s'] = $search;
}
```
WordPress's built-in `s` parameter searches product titles and descriptions.

**Category filter:**
```php
$args['tax_query'] = [ [
    'taxonomy'         => 'product_cat',
    'field'            => is_numeric( $category ) ? 'term_id' : 'slug',
    'terms'            => $category,
    'include_children' => true,
] ];
```
- `tax_query` filters by taxonomy (category)
- `is_numeric` decides whether the category value is an ID or a slug
- `include_children => true` means if you filter by "Clothing", products in "T-Shirts" (a sub-category of Clothing) also appear

**Sorting:**
```php
case 'price_low':
    $args['meta_key'] = '_price';
    $args['orderby']  = 'meta_value_num';
    $args['order']    = 'ASC';
    break;
```
- WooCommerce stores prices in the `wp_postmeta` table under the `_price` key
- `meta_value_num` tells WordPress to sort numerically (not alphabetically — "10" comes before "9" alphabetically but not numerically)

**Transient cache:**
```php
$cache_key = 'pw_query_' . md5( serialize( $args ) );
$cached    = get_transient( $cache_key );

if ( $cached !== false ) {
    return $cached; // Return cached result, skip database
}
```
- `serialize()` converts the args array to a string
- `md5()` hashes it to a short fixed-length key
- `get_transient()` retrieves from WordPress's object cache or database
- If the exact same query was run in the last 60 seconds, the cached result is returned — no database hit

```php
set_transient( $cache_key, $result, 60 );
```
Stores the result for 60 seconds. After that, the next request runs a fresh query.

---

### 4.8 Wishlist Table — `src/App/Wishlist/WishlistTable.php`

#### `public static function create()`

```php
$sql = "CREATE TABLE IF NOT EXISTS {$table} (
    id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT(20) UNSIGNED NOT NULL,
    product_id  BIGINT(20) UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_product (user_id, product_id),
    KEY user_id (user_id)
) {$charset};";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $sql );
```
- `CREATE TABLE IF NOT EXISTS` — safe to run multiple times (won't error if table exists)
- `UNIQUE KEY user_product (user_id, product_id)` — prevents a user from adding the same product to their wishlist twice at the database level
- `KEY user_id (user_id)` — an index on `user_id` makes "get all wishlisted products for user X" queries very fast
- `dbDelta()` is WordPress's smart table creation function — it creates the table if missing, or updates it if the schema changed. Never use raw `CREATE TABLE` in WordPress plugins.

#### `public static function drop()`
Used by `uninstall.php` to delete the table when the plugin is removed.

---

### 4.9 Wishlist Repository — `src/App/Wishlist/WishlistRepository.php`

This class is the only place that touches the wishlist database table. All other code asks this class to do it.

#### `private static function table(): string`
```php
return $wpdb->prefix . 'pw_wishlist';
```
Returns the full table name including the WordPress prefix (usually `wp_`). If WordPress is installed with a custom prefix like `mysite_`, the table name is `mysite_pw_wishlist`. This method ensures we always use the correct prefix.

#### `public static function add( int $user_id, int $product_id ): bool`
```php
$result = $wpdb->insert(
    self::table(),
    [ 'user_id' => $user_id, 'product_id' => $product_id, 'created_at' => current_time('mysql') ],
    [ '%d', '%d', '%s' ]
);
```
- `$wpdb->insert()` is WordPress's safe way to insert data — it uses prepared statements internally
- The third argument `['%d', '%d', '%s']` tells wpdb the data types: `%d` = integer, `%s` = string
- This prevents SQL injection

#### `public static function remove( int $user_id, int $product_id ): bool`
```php
$result = $wpdb->delete(
    self::table(),
    [ 'user_id' => $user_id, 'product_id' => $product_id ],
    [ '%d', '%d' ]
);
```
Deletes the row where both `user_id` AND `product_id` match. The `UNIQUE KEY` on the table ensures there's never more than one such row.

#### `public static function is_wishlisted( int $user_id, int $product_id ): bool`
```php
$count = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM %i WHERE user_id = %d AND product_id = %d",
    self::table(), $user_id, $product_id
) );
```
- `get_var()` returns a single value from the database (just the count number)
- `$wpdb->prepare()` safely interpolates variables into SQL using placeholders
- `%i` is the table name placeholder (new in WP 6.2), `%d` is integer

#### `public static function get_product_ids( int $user_id ): array`
```php
$results = $wpdb->get_col( $wpdb->prepare(
    "SELECT product_id FROM %i WHERE user_id = %d",
    self::table(), $user_id
) );
return array_map( 'intval', $results );
```
- `get_col()` returns a flat array of values from one column
- `array_map( 'intval', $results )` converts every value to an integer (database returns strings by default)

---

### 4.10 Wishlist Sync — `src/App/Wishlist/WishlistSync.php`

#### Why this exists

Guests can't use the database wishlist (they have no user ID). So guest wishlists are stored in the browser's `localStorage`. When a guest logs in, their localStorage wishlist needs to be moved into the database. This file handles that.

#### `public static function register_sync_route()`

Registers `POST /wp-json/pw/v1/wishlist/sync`. It accepts an array of product IDs and inserts each one into the database wishlist for the now-logged-in user.

#### `public static function sync( WP_REST_Request $request )`
```php
foreach ( $product_ids as $pid ) {
    if ( $pid > 0 && wc_get_product( $pid ) ) {
        if ( ! WishlistRepository::is_wishlisted( $user_id, $pid ) ) {
            WishlistRepository::add( $user_id, $pid );
        }
    }
}
```
- Validates each product ID is positive and actually exists in WooCommerce
- Checks if already wishlisted to avoid duplicate attempts (the DB `UNIQUE KEY` also blocks this, but checking first avoids an error)
- Inserts each new wishlist item

---

### 4.11 Assets Manager — `src/App/Assets/AssetsManager.php`

#### `public static function enqueue()`
```php
wp_enqueue_style( 'pw-widget-style', PW_URL . 'assets/css/widget.css', [], PW_VERSION );
wp_enqueue_script( 'pw-widget-script', PW_URL . 'assets/js/widget.js', ['jquery'], PW_VERSION, true );
```
- `wp_enqueue_style` / `wp_enqueue_script` are the WordPress-approved way to load assets
- The handle `'pw-widget-style'` uniquely identifies this asset so WordPress won't load it twice
- `PW_VERSION` is passed as the version number — this busts the browser cache when you release a new version
- The last `true` in `enqueue_script` means load the JS at the bottom of the page (before `</body>`) for performance

```php
wp_localize_script( 'pw-widget-script', 'PW_CONFIG', [
    'api_base'  => esc_url_raw( rest_url( 'pw/v1' ) ),
    'nonce'     => wp_create_nonce( 'wp_rest' ),
    'is_logged' => is_user_logged_in() ? 1 : 0,
    'cart_url'  => wc_get_cart_url(),
] );
```
- `wp_localize_script` injects PHP variables into the page as a JavaScript object
- The JavaScript accesses these as `window.PW_CONFIG.api_base` etc.
- `wp_create_nonce( 'wp_rest' )` creates a security token — REST API calls include this in their headers so WordPress knows the request came from a legitimate page, not an external attacker
- `esc_url_raw()` sanitises the API URL

---

### 4.12 Grid Widget — `src/App/ProductGridType/ProductGridWidget.php`

Extends `\Elementor\Widget_Base` — this is the Elementor contract for all widgets.

#### Required methods:
- `get_name()` — unique machine name (used internally by Elementor)
- `get_title()` — display name in the Elementor panel
- `get_icon()` — icon class from Elementor's icon library
- `get_categories()` — which panel section to appear in

#### `protected function register_controls()`

This defines all the settings panels in the Elementor editor sidebar. Each `add_control()` call adds one setting:

```php
$this->add_control( 'posts_per_page', [
    'label'   => 'Products Per Page',
    'type'    => \Elementor\Controls_Manager::NUMBER,
    'default' => 12,
    'min'     => 1,
    'max'     => 100,
] );
```
- `NUMBER` creates a number input
- `SWITCHER` creates a toggle (yes/no)
- `SELECT` creates a dropdown
- `COLOR` creates a colour picker
- `SLIDER` creates a range slider

The style controls use `selectors` to apply CSS directly:
```php
$this->add_control( 'card_bg', [
    'type'      => \Elementor\Controls_Manager::COLOR,
    'selectors' => [ '{{WRAPPER}} .pw-product-card' => 'background-color: {{VALUE}};' ],
] );
```
`{{WRAPPER}}` is replaced by Elementor with the widget's unique CSS selector. This ensures the colour change only affects *this* widget, not every widget on the page.

#### `protected function render()`
```php
$config = [
    'id'         => $widget_id,
    'layout'     => 'grid',
    'per_page'   => (int) $settings['posts_per_page'],
    'pagination' => $settings['show_pagination'] === 'yes',
    ...
];

echo '<div class="pw-widget pw-widget--grid"
        data-id="' . esc_attr( $widget_id ) . '"
        data-settings="' . esc_attr( wp_json_encode( $config ) ) . '">
      </div>';
```
- The widget outputs only **one div**. All the product HTML is generated by JavaScript after the page loads.
- `wp_json_encode()` converts the PHP array to a JSON string
- `esc_attr()` makes it safe to put inside an HTML attribute
- The JavaScript reads `data-settings` and uses those values to call the API and render products

---

### 4.13 List Widget — `src/App/ProductListType/ProductListWidget.php`

Identical structure to the Grid widget, but with `'layout' => 'list'` and no column setting. The CSS handles the different visual presentation.

---

### 4.14 Uninstall Script — `uninstall.php`

```php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
```
This constant is set by WordPress only when a genuine uninstall is happening. This line prevents the script from running if someone navigates to it directly in a browser.

```php
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pw_wishlist" );
delete_option( 'pw_version' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pw_query_%'" );
```
- Drops the wishlist table permanently
- Deletes any stored options
- Removes all transient cache entries created by the plugin

---

## 5. JavaScript Frontend

The entire JavaScript is wrapped in an **IIFE** (Immediately Invoked Function Expression):

```js
(function () {
  "use strict";
  // all code here
})();
```

This creates a private scope — nothing inside can accidentally conflict with jQuery, WooCommerce scripts, or theme scripts. `"use strict"` enables strict mode which catches common mistakes at runtime.

---

### 5.1 Configuration

```js
const API     = window.PW_CONFIG?.api_base || "/wp-json/pw/v1";
const NONCE   = window.PW_CONFIG?.nonce    || "";
const IS_LOGGED = parseInt(window.PW_CONFIG?.is_logged || "0", 10) === 1;
```
- `?.` is optional chaining — if `PW_CONFIG` doesn't exist, it returns `undefined` instead of throwing an error
- `|| fallback` provides a default value if the left side is falsy
- These constants are read once and used throughout the file

---

### 5.2 `apiFetch( endpoint, options )`

```js
function apiFetch(endpoint, options = {}) {
  const headers = {
    "Content-Type": "application/json",
    "X-WP-Nonce": NONCE,
    ...(options.headers || {}),
  };
  return fetch(`${API}${endpoint}`, { ...options, headers }).then(r => r.json());
}
```
- A wrapper around the browser's `fetch()` API
- Automatically prepends the API base URL to every endpoint
- Automatically adds the `X-WP-Nonce` header (WordPress checks this for authentication)
- Returns a Promise that resolves to parsed JSON
- Using `...` spread operator, it merges any extra options passed in (like `signal` for abort, or `body` for POST data)

---

### 5.3 `debounce( fn, delay )`

```js
function debounce(fn, delay) {
  let timer;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}
```
- Returns a new function that wraps `fn`
- Each time the returned function is called, it cancels the previous timer and starts a new one
- `fn` only actually runs after `delay` milliseconds of silence
- Without debounce, typing "shoes" would fire 5 API requests (s, sh, sho, shoe, shoes). With debounce 300ms, it only fires once after you stop typing.
- `fn.apply(this, args)` preserves the original `this` context and arguments

---

### 5.4 `PWToast` — Toast Notification System

A singleton object (created once via an IIFE) that manages all notification pop-ups.

#### `init()`
```js
function init() {
  if (container) return; // Only create once
  container = document.createElement("div");
  container.id = "pw-toast-container";
  container.setAttribute("aria-live", "polite"); // Screen reader support
  document.body.appendChild(container);
}
```
Creates the container div and appends it to the body. `aria-live="polite"` tells screen readers to announce new toasts after they finish reading the current element.

#### `show( message, type, duration )`
```js
const toast = document.createElement("div");
toast.className = `pw-toast pw-toast--${type}`;
toast.innerHTML = `<span class="pw-toast__icon">${icon}</span><span>${message}</span>`;
container.appendChild(toast);

requestAnimationFrame(() => toast.classList.add("pw-toast--visible"));

setTimeout(() => {
  toast.classList.remove("pw-toast--visible");
  toast.addEventListener("transitionend", () => toast.remove(), { once: true });
}, duration);
```
- Creates a toast div and appends it to the container
- `requestAnimationFrame` delays the class addition by one frame — this ensures CSS transitions work (the element needs to exist in the DOM before the transition starts)
- `pw-toast--visible` triggers the CSS fade-in + slide-up transition
- After `duration` ms, removes the visible class (triggering fade-out)
- `transitionend` fires when the CSS transition completes — then removes the element from the DOM entirely
- `{ once: true }` means the event listener removes itself after firing once

---

### 5.5 `PWWishlist` — Wishlist Store

Manages wishlist state for both guests (localStorage) and logged-in users (API).

#### `getGuest()` / `saveGuest()`
```js
function getGuest() {
  try {
    return JSON.parse(localStorage.getItem(LS_KEY) || "[]").map(Number);
  } catch {
    return [];
  }
}
```
- Reads the `pw_guest_wishlist` key from localStorage
- `JSON.parse` converts the stored string back to an array
- `.map(Number)` converts string IDs to numbers for consistent comparison
- `try/catch` handles cases where localStorage is blocked or the stored data is corrupted

#### `toggle( productId )`
```js
async function toggle(productId) {
  if (!IS_LOGGED) {
    // Guest: update localStorage
    const ids = getGuest();
    const idx = ids.indexOf(productId);
    const adding = idx === -1;
    if (adding) ids.push(productId);
    else ids.splice(idx, 1);
    saveGuest(ids);
    return { wishlisted: adding, product_id: productId };
  }

  // Logged-in: API call
  const res = await apiFetch("/wishlist/toggle", {
    method: "POST",
    body: JSON.stringify({ product_id: productId }),
  });
  return res;
}
```
- Two completely different code paths depending on login state
- For guests: synchronous localStorage manipulation, returns immediately
- For logged-in: async API call, awaits the server response
- Both return the same shaped object `{ wishlisted: bool, product_id: int }` so the calling code doesn't need to care which path was taken

#### `syncGuestOnLogin()`
```js
async function syncGuestOnLogin() {
  if (!IS_LOGGED) return;
  const guestIds = getGuest();
  if (!guestIds.length) return;
  try {
    await apiFetch("/wishlist/sync", {
      method: "POST",
      body: JSON.stringify({ product_ids: guestIds }),
    });
    localStorage.removeItem(LS_KEY); // Clean up guest data
  } catch (e) {
    console.warn("PW: wishlist sync failed", e);
  }
}
```
- Only runs if user is logged in AND there are guest wishlist items
- Sends all guest IDs to the sync endpoint
- Clears localStorage after successful sync — the user's wishlist now lives in the DB
- Errors are caught silently (non-critical feature)

---

### 5.6 `PWQuickView` — Quick View Modal

A singleton modal that displays product details without leaving the page.

#### `init()`
Creates the modal HTML structure and appends it to `document.body`. Event listeners for the close button and overlay click are attached once here.

#### `open( product )`
```js
function open(product) {
  init();
  inner.innerHTML = `
    <div class="pw-qv-product">
      <div class="pw-qv-image"><img src="${product.image}" /></div>
      <div class="pw-qv-details">
        <h2>${escHtml(product.title)}</h2>
        <div>${product.price}</div>
        <button class="pw-qv-cart" data-product-id="${product.id}">Add to Cart</button>
      </div>
    </div>`;

  modal.classList.add("pw-qv--open");
  document.body.style.overflow = "hidden"; // Prevent background scroll
}
```
- Populates the modal with product data
- `modal.classList.add("pw-qv--open")` triggers the CSS transition that fades in the overlay and scales up the box
- `document.body.style.overflow = "hidden"` prevents the page behind the modal from scrolling

The Add to Cart button inside the modal wires up its own click handler inline, including loading state and toast feedback.

#### `close()`
Removes the `pw-qv--open` class and restores `body.overflow`. CSS transitions handle the visual fade-out.

---

### 5.7 Helper Functions

#### `escHtml( str )`
```js
function escHtml(str) {
  const d = document.createElement("div");
  d.textContent = str;
  return d.innerHTML;
}
```
A simple XSS (cross-site scripting) protection function. Setting `textContent` on a DOM element automatically escapes any HTML characters. Then reading `innerHTML` gives us the escaped string. For example, a product named `<script>alert(1)</script>` becomes `&lt;script&gt;alert(1)&lt;/script&gt;` — safe to inject into the DOM.

#### `renderStars( rating )`
```js
function renderStars(rating) {
  const r = parseFloat(rating) || 0;
  let html = "";
  for (let i = 1; i <= 5; i++) {
    html += `<span class="${i <= Math.round(r) ? 'pw-star pw-star--filled' : 'pw-star'}">★</span>`;
  }
  return html;
}
```
Generates 5 star `<span>` elements. Stars up to the rounded rating value get the `pw-star--filled` class (yellow), the rest stay grey.

---

### 5.8 Cart Counter Sync

```js
document.addEventListener("pw_cart_updated", (e) => {
  const count = e.detail?.cart_count;
  document.querySelectorAll(".cart-count, .cart-contents-count, [data-pw-cart-count]")
    .forEach(el => { el.textContent = count; });
});
```
- Listens for the custom `pw_cart_updated` event (fired by any widget after a successful cart add)
- Finds any element on the page that displays the cart count — most themes use `.cart-count` or `.cart-contents-count`
- Updates the count instantly without a page reload
- This works across widgets — if Widget A adds to cart, Widget B's context also benefits because this listener is global

---

### 5.9 `ProductWidget` Class

One instance is created for every `.pw-widget` element found on the page. Each instance is completely isolated — its state, timers, and event listeners don't affect any other instance.

#### `constructor( el )`
```js
constructor(el) {
  this.el = el;
  this.settings = JSON.parse(el.dataset.settings || "{}");
  this.state = {
    page: 1,
    search: "",
    category: this.settings.category || "",
    sort: this.settings.sort || "default",
    loading: false,
  };
  this.abortController = null;
  this._render();
  this._load();
  this._bindGlobalEvents();
}
```
- `this.el` — reference to the DOM element this widget owns
- `this.settings` — parsed from `data-settings` attribute, contains all Elementor control values
- `this.state` — the widget's current internal state. Every AJAX call uses these values.
- `this.abortController` — used to cancel in-flight requests when a new one starts
- Calls `_render()` (build the shell HTML), then `_load()` (fetch first page of products)

#### `_render()`
Generates the toolbar HTML (search input + filter dropdowns) and the products container. Shows skeleton loader cards immediately so the user sees something while the API call is in progress. Then calls `_bindUI()` and `_loadCategories()`.

#### `_skeleton( count )`
Generates `count` skeleton card placeholders using a shimmer animation. This is shown instantly before products load, giving the perception of fast loading.

#### `_bindUI()`
Attaches event listeners to the search input and dropdowns.

```js
const debouncedSearch = debounce((val) => {
  this.state.search = val;
  this.state.page = 1; // Reset to page 1 on new search
  this._load();
}, 300);

searchInput.addEventListener("input", (e) => {
  debouncedSearch(e.target.value.trim());
});
```
- Uses `debounce` so the API is only called after 300ms of typing inactivity
- Resets `page` to 1 — a new search always starts from the first page

For sort/category dropdowns:
```js
sortSelect.addEventListener("change", (e) => {
  this.state.sort = e.target.value;
  this.state.page = 1;
  this._load();
});
```
Any change triggers an immediate API call.

#### `_bindGlobalEvents()`
```js
document.addEventListener("pw_wishlist_updated", (e) => {
  const { product_id, wishlisted } = e.detail || {};
  this.el.querySelectorAll(`.pw-wishlist-btn[data-product-id="${product_id}"]`)
    .forEach(btn => {
      btn.classList.toggle("pw-wishlist-btn--active", wishlisted);
    });
});
```
When any widget on the page fires a `pw_wishlist_updated` event, ALL widgets update their heart buttons for that product. This is the cross-widget wishlist sync. The query uses `this.el.querySelectorAll` so it only searches within THIS widget's DOM, but because all widgets listen to the same global event, all of them react.

#### `_loadCategories()`
```js
const res = await fetch(`${origin}/wp-json/wc/v3/products/categories?per_page=50`, ...);
const cats = await res.json();
cats.forEach(cat => {
  const opt = document.createElement("option");
  opt.value = cat.slug;
  opt.textContent = cat.name;
  catSelect.appendChild(opt);
});
```
Uses WooCommerce's built-in REST API (not our custom one) to fetch all product categories and populates the category dropdown. Fails silently if the API is unavailable.

#### `async _load()`
The core AJAX method. Called whenever state changes.

```js
if (this.state.loading) {
  if (this.abortController) this.abortController.abort();
}
this.abortController = new AbortController();
```
If a request is already in flight when a new one starts (user typed quickly), the old request is aborted using the `AbortController` API. This prevents old results from overwriting newer ones.

```js
const params = new URLSearchParams({
  page: this.state.page,
  per_page: this.settings.per_page || 12,
  search: this.state.search,
  category: this.state.category,
  sort: this.state.sort,
});

const res = await apiFetch(`/products?${params}`, {
  signal: this.abortController.signal,
});
```
Builds URL query parameters from the current state and calls the API. The `signal` connects to the `AbortController` so the fetch can be cancelled.

#### `_renderProducts( products )`
Clears the product grid and replaces it with new product cards. Handles the empty state:
```js
if (!products || products.length === 0) {
  grid.innerHTML = `<div class="pw-no-products">...</div>`;
  return;
}
```

For each product, decides whether to use `_gridCard()` or `_listCard()` based on `this.settings.layout`.

#### `_gridCard( p, isSimple, wishlisted )`
Generates the HTML for one grid product card.

Key decision logic:
```js
${isSimple
  ? `<button class="pw-add-to-cart" data-product-id="${p.id}">Add to Cart</button>`
  : `<a class="pw-view-details" href="${p.permalink}">View Details</a>`
}
```
Simple products get an "Add to Cart" button (AJAX). Variable products get a "View Details" link (redirect to product page). This is one of the most important UX rules in the spec.

```js
${isSimple ? `<button class="pw-quickview-btn" data-product='${JSON.stringify(p)}'>Quick View</button>` : ""}
```
Quick View is also only shown for simple products. The entire product data is stored in the `data-product` attribute so the quick view modal can display it without another API call.

#### `_bindProductEvents( container )`
Attaches click handlers to the newly rendered product cards. Called after every render.

**Add to Cart button:**
```js
btn.disabled = true;
btn.innerHTML = `<span class="pw-spinner"></span> Adding…`;

const res = await apiFetch("/cart/add", {
  method: "POST",
  body: JSON.stringify({ product_id: productId, quantity: 1 }),
});
```
- Immediately disables button and shows spinner to prevent double-clicks
- After success: shows "Added ✓" then reverts after 2 seconds
- Fires `pw_cart_updated` event with the new cart count

**Wishlist button:**
```js
const res = await PWWishlist.toggle(productId);

document.dispatchEvent(new CustomEvent("pw_wishlist_updated", {
  detail: { product_id: productId, wishlisted: res.wishlisted },
}));
```
After toggling, fires a global event. Every widget on the page (including this one via `_bindGlobalEvents`) reacts and updates its heart icons.

**Quick View button:**
```js
const product = JSON.parse(e.currentTarget.dataset.product);
PWQuickView.open(product);
```
Reads the product data embedded in the HTML attribute and opens the modal.

#### `_renderPagination( pagination )`
Generates page number buttons. Includes smart ellipsis logic so that for 20 pages, it shows: `1 2 … 4 5 6 … 19 20` instead of all 20 buttons.

Each button click updates `this.state.page` and calls `this._load()`, then scrolls the widget into view.

---

## 6. CSS Architecture

The CSS uses **CSS Custom Properties** (variables) defined on `:root`:

```css
:root {
  --pw-primary:     #2563eb;  /* Blue — buttons, links */
  --pw-heart:       #ef4444;  /* Red — wishlist hearts */
  --pw-card-radius: 12px;     /* Consistent border radius */
  --pw-transition:  200ms ease; /* All animations this speed */
  --pw-cols:        3;          /* Grid columns (overridden per widget) */
}
```

This makes theming possible — a child theme could override `--pw-primary` to change all button colours in one line.

**The `--pw-cols` trick:**
```css
.pw-products--grid {
  display: grid;
  grid-template-columns: repeat(var(--pw-cols), 1fr);
}
```
The widget sets `style="--pw-cols:4"` inline, and this CSS reads it. Each widget can have a different column count without JavaScript needing to write media queries.

**Skeleton animation:**
```css
@keyframes pw-shimmer {
  0%   { background-position: -200% 0; }
  100% { background-position:  200% 0; }
}
.pw-skeleton-thumb {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: pw-shimmer 1.5s infinite linear;
}
```
A gradient that sweeps left to right infinitely, creating the shimmer effect on skeleton cards.

**Toast slide-up:**
```css
.pw-toast {
  opacity: 0;
  transform: translateY(12px) scale(.96);
  transition: opacity 280ms ease, transform 280ms ease;
}
.pw-toast--visible {
  opacity: 1;
  transform: translateY(0) scale(1);
}
```
Two CSS properties transition simultaneously — opacity fades in, transform slides up and scales from 96% to 100%.

---

## 7. Data Flow

### User searches for "shoes":

```
User types "shoes" in search box
     ↓
debounce waits 300ms
     ↓
state.search = "shoes", state.page = 1
     ↓
_load() called
     ↓
Previous request aborted (if any)
     ↓
GET /wp-json/pw/v1/products?search=shoes&page=1&sort=default
     ↓  (PHP)
RestAPI routes to ProductsController::get_products()
     ↓
ProductQuery::run() builds WP_Query with 's' => 'shoes'
     ↓
Check transient cache — miss (new search)
     ↓
WP_Query hits database
     ↓
Results cached for 60s
     ↓
ProductsController formats each product into JSON
     ↓
JSON response sent back
     ↓  (JavaScript)
_renderProducts() generates card HTML
     ↓
DOM updated with new cards
     ↓
_bindProductEvents() attaches click handlers to new cards
```

### User clicks "Add to Cart":

```
Click on Add to Cart button
     ↓
Button disabled, spinner shown
     ↓
POST /wp-json/pw/v1/cart/add  {product_id: 42, quantity: 1}
     ↓  (PHP)
CartController::add_to_cart()
     ↓
Product fetched, type checked (must be simple)
     ↓
WC()->cart->add_to_cart(42, 1)
     ↓
WC()->cart->calculate_totals()
     ↓
{success: true, cart_count: 3} returned
     ↓  (JavaScript)
PWToast.show("Product added to cart!", "success")
     ↓
document.dispatchEvent(new CustomEvent("pw_cart_updated", {detail: {cart_count: 3}}))
     ↓
Cart counter elements on page updated to show "3"
     ↓
Button shows "Added ✓" for 2 seconds, then reverts
```

### User toggles wishlist (logged-in):

```
Click on heart button
     ↓
Button disabled
     ↓
PWWishlist.toggle(productId) called
     ↓  (IS_LOGGED = true, so API path)
POST /wp-json/pw/v1/wishlist/toggle  {product_id: 42}
     ↓  (PHP)
WishlistController::toggle()
     ↓
WishlistRepository::is_wishlisted(userId, 42) → false
     ↓
WishlistRepository::add(userId, 42) → INSERT INTO wp_pw_wishlist
     ↓
{success: true, wishlisted: true, product_id: 42} returned
     ↓  (JavaScript)
document.dispatchEvent(new CustomEvent("pw_wishlist_updated", {detail: {product_id: 42, wishlisted: true}}))
     ↓
ALL widgets on page react — their heart buttons for product 42 turn red
     ↓
PWToast.show("Added to wishlist ♥", "heart")
     ↓
Button re-enabled
```

---

## 8. Security

| Threat | Protection |
|---|---|
| SQL Injection | `$wpdb->prepare()` with typed placeholders on all queries |
| XSS in JavaScript | `escHtml()` function escapes all product data before DOM insertion |
| XSS in PHP | `esc_html()`, `esc_attr()`, `esc_url()` on all output |
| CSRF on REST API | `X-WP-Nonce` header verified by WordPress on every request |
| Unauthorised wishlist access | `permission_callback => is_user_logged_in()` blocks guests at route level |
| Adding variable products to cart directly | Double-blocked: JS hides the button, PHP rejects the request |
| Direct file access | `defined('ABSPATH') || exit` at top of every PHP file |
| Parameter tampering | REST API `args` with `type`, `minimum`, `maximum`, `enum` constraints |

---

## 9. WordPress Concepts Used — Beginner Reference

| Concept | What it is |
|---|---|
| `add_action()` | Tells WordPress to call your function when a specific event happens |
| `register_activation_hook()` | Runs once when the plugin is activated |
| `WP_Query` | WordPress's class for querying posts/products from the database |
| `WP_REST_Request` | Object containing all data from an incoming REST API request |
| `rest_ensure_response()` | Wraps a PHP array into a proper REST API JSON response |
| `wp_enqueue_script/style` | The approved way to load CSS/JS files in WordPress |
| `wp_localize_script` | Passes PHP variables into JavaScript as a global object |
| `$wpdb` | WordPress's global database object for safe SQL queries |
| `get_transient / set_transient` | WordPress's cache system (stored in DB or memory) |
| `wp_create_nonce` | Creates a one-time security token for AJAX/API calls |
| `wc_get_product()` | WooCommerce function to get a product object from an ID |
| `WC()->cart` | WooCommerce's global cart object |
| `dbDelta()` | WordPress function that safely creates or updates DB tables |
| `sanitize_text_field()` | Strips HTML and extra whitespace from text input |
| `esc_attr()` | Escapes a string for safe use inside HTML attributes |
