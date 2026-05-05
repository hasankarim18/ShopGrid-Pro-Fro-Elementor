# WooCommerce Elementor Addon — Complete Plugin Documentation

> **How to read this document:** Start at Section 1 and read straight through. Every section builds on the one before it. By the end you will understand every file, every class, every function, every hook, and exactly why each decision was made.

---

## Table of Contents

1. [What This Plugin Does — Plain English](#1-what-this-plugin-does)
2. [The Three-Layer Architecture](#2-the-three-layer-architecture)
3. [File and Folder Structure](#3-file-and-folder-structure)
4. [Step 1 — Plugin Entry Point](#4-step-1--plugin-entry-point-woo-elementor-addonphp)
5. [Step 2 — The Autoloader](#5-step-2--the-autoloader)
6. [Step 3 — Dependency Checks and Booting](#6-step-3--dependency-checks-and-booting)
7. [Step 4 — The Main Bootstrap Class](#7-step-4--the-main-bootstrap-class-mainphp)
8. [Step 5 — The Assets Manager](#8-step-5--the-assets-manager-assetsmanagerphp)
9. [Step 6 — The REST API Router](#9-step-6--the-rest-api-router-restapiph)
10. [Step 7 — The Product Query Engine](#10-step-7--the-product-query-engine-productqueryphp)
11. [Step 8 — The Products Controller](#11-step-8--the-products-controller-productscontrollerphp)
12. [Step 9 — The Cart Controller](#12-step-9--the-cart-controller-cartcontrollerphp)
13. [Step 10 — The Wishlist System](#13-step-10--the-wishlist-system)
14. [Step 11 — The Elementor Widgets](#14-step-11--the-elementor-widgets)
15. [Step 12 — The Frontend JavaScript Engine](#15-step-12--the-frontend-javascript-engine-widgetjs)
16. [Step 13 — The Uninstall Script](#16-step-13--the-uninstall-script)
17. [All WordPress Hooks Used — Reference Table](#17-all-wordpress-hooks-used)
18. [Complete Request-to-Response Flows](#18-complete-request-to-response-flows)
19. [Bug Fixes Applied and Why](#19-bug-fixes-applied-and-why)

---

## 1. What This Plugin Does

In plain English, this plugin gives you two drag-and-drop blocks inside the Elementor page builder — a **Product Grid** and a **Product List**. When a visitor lands on a page that has one of these blocks, they can:

- Browse products loaded dynamically from WooCommerce
- Search for products by name
- Filter by category
- Sort by price or alphabetically
- Add simple products to the cart without the page reloading
- Click "View Details" on variable or grouped products to go to the product page
- Click "Buy Now" on external products to be sent to an outside website
- Save products to a wishlist (stored in the database for logged-in users, stored in the browser for guests)
- Preview a simple product in a popup modal without leaving the page

**None of these actions cause a full page reload.** Everything happens through AJAX — the browser sends a small request to the server, gets data back, and only the product area of the page updates. This is the core design goal.

---

## 2. The Three-Layer Architecture

Before looking at any code, understand the three layers and how they talk to each other:

```
┌─────────────────────────────────────────────────────────────────┐
│  LAYER 1 — ELEMENTOR WIDGET (PHP)                               │
│  The widget renders one empty <div> with settings baked into    │
│  a data attribute. That's all it does.                          │
└────────────────────────────┬────────────────────────────────────┘
                             │ Page loads, JS finds the <div>
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  LAYER 2 — FRONTEND ENGINE (JavaScript)                         │
│  Reads the settings from the data attribute, calls the API,     │
│  draws product cards, handles all user interactions.            │
└────────────────────────────┬────────────────────────────────────┘
                             │ AJAX requests over HTTP
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│  LAYER 3 — REST API + DATABASE (PHP)                            │
│  Receives requests, queries the database, returns JSON.         │
└─────────────────────────────────────────────────────────────────┘
```

The widget (Layer 1) never directly shows products. The JavaScript (Layer 2) never stores data. The PHP backend (Layer 3) never draws HTML for products. Each layer does exactly one job.

---

## 3. File and Folder Structure

```
woo-elementor-addon/
│
├── shop-grid-pro-for-elementor.php  ← WordPress reads this. Plugin starts here.
├── composer.json                    ← Package info (not required to run)
├── uninstall.php                    ← Runs when you DELETE the plugin
│
├── assets/
│   ├── css/widget.css               ← All visual styles
│   └── js/widget.js                 ← All frontend JavaScript
│
└── src/                             ← All PHP logic lives here
    ├── Main.php                     ← Central bootstrap, ties everything together
    └── App/
        ├── API/
        │   ├── RestAPI.php          ← Registers the URL routes
        │   ├── ProductsController.php ← Handles product fetch requests
        │   ├── CartController.php   ← Handles add-to-cart requests
        │   └── WishlistController.php ← Handles wishlist requests
        ├── Query/
        │   └── ProductQuery.php     ← Builds the database query
        ├── Wishlist/
        │   ├── WishlistTable.php    ← Creates the database table
        │   ├── WishlistRepository.php ← All wishlist SQL operations
        │   └── WishlistSync.php     ← Syncs guest wishlist on login
        ├── Assets/
        │   └── AssetsManager.php    ← Loads CSS and JS files
        ├── ProductGridType/
        │   └── ProductGridWidget.php ← The Elementor grid widget
        └── ProductListType/
            └── ProductListWidget.php ← The Elementor list widget
```

**Why this structure?** Each folder represents one subject. If the cart breaks, you open `CartController.php`. If the database query is wrong, you open `ProductQuery.php`. Nothing is tangled together. This is called Separation of Concerns.

---

## 4. Step 1 — Plugin Entry Point (`shopgrid-pro-for-elementor.php`)

This is the very first file WordPress reads. Every plugin must have one file at its root with a special comment block at the top.

### The Comment Header

```php
/**
 * Plugin Name: ShopGrid Pro Fro Elementor
 * Version:     1.0.0
 * Text Domain: shop-grid-pro-for-elementor
 * ...
 */
```

WordPress reads this comment to know the plugin exists, what its name is, and what version it is. Without this comment, WordPress cannot see the plugin at all. It is not PHP code — it is metadata that WordPress parses as text.

### Constants

```php
define( 'PW_VERSION', '1.0.0' );
define( 'PW_FILE',    __FILE__ );
define( 'PW_PATH',    plugin_dir_path( __FILE__ ) );
define( 'PW_URL',     plugin_dir_url( __FILE__ ) );
```

These four lines create global constants that any file in the plugin can use:

- **`PW_VERSION`** — the version number. Used when loading CSS/JS so browsers know to fetch a new copy when the plugin updates instead of serving a cached old version.
- **`PW_FILE`** — the absolute file path to the main plugin file. Used by WordPress for the activation hook.
- **`PW_PATH`** — the absolute folder path on the server (e.g. `/var/www/wp-content/plugins/woo-elementor-addon/`). Used to find and `require` other PHP files.
- **`PW_URL`** — the web URL to the plugin folder (e.g. `https://yoursite.com/wp-content/plugins/woo-elementor-addon/`). Used to load CSS and JS in the browser.

These are defined here and never again. Every other file uses `PW_PATH` instead of writing the full path manually.

### Security Guard

```php
defined( 'ABSPATH' ) || exit;
```

This line appears at the top of every PHP file in the plugin. `ABSPATH` is a constant that WordPress defines when it boots. If someone tries to load one of your PHP files directly in a browser (e.g. by visiting `yoursite.com/wp-content/plugins/woo-elementor-addon/src/Main.php`), `ABSPATH` won't be defined, `exit` runs, and the file shows nothing. This prevents attackers from poking at your files directly.

### The Activation Hook

```php
register_activation_hook( __FILE__, function () {
    \PW\App\Wishlist\WishlistTable::create();
} );
```

**Hook:** `register_activation_hook`
**When it runs:** Exactly once — the moment the admin clicks "Activate" in WP Admin → Plugins.
**What it does:** Creates the `wp_pw_wishlist` database table.
**Why it must be here:** WordPress requires the activation hook to be registered from the main plugin file directly. It cannot be inside a class or included file. This is a WordPress rule.

---

## 5. Step 2 — The Autoloader

```php
spl_autoload_register( function ( $class ) {
    $prefix = 'PW\\';
    $base   = PW_PATH . 'src/';

    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return;
    }

    $relative = substr( $class, strlen( $prefix ) );
    $file     = $base . str_replace( '\\', '/', $relative ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
} );
```

**What problem this solves:** Without an autoloader, every file that uses another class needs a `require_once` line pointing to that class file. With 12+ PHP files, that becomes dozens of `require` statements scattered everywhere. The autoloader eliminates all of them.

**How it works step by step:**

1. `spl_autoload_register` tells PHP: "Before giving up on a missing class, try calling this function."
2. When PHP sees `new \PW\App\API\CartController()` and can't find the class, it calls this function with the string `"PW\App\API\CartController"`.
3. `strncmp` checks if the class name starts with `"PW\"`. If it doesn't, this is not our class, so we return immediately and let PHP handle it.
4. `substr` removes the `"PW\"` prefix, leaving `"App\API\CartController"`.
5. `str_replace` converts backslashes to forward slashes: `"App/API/CartController"`.
6. The file path becomes: `src/App/API/CartController.php`.
7. `file_exists` checks the file is there, then `require` loads it.

**The rule it enforces:** The namespace must match the folder structure. `PW\App\API\CartController` lives at `src/App/API/CartController.php`. This is the PSR-4 standard and makes the codebase predictable — you always know exactly where a class lives.

---

## 6. Step 3 — Dependency Checks and Booting

```php
add_action( 'plugins_loaded', function () {
    if ( ! did_action( 'elementor/loaded' ) ) { /* show error, return */ }
    if ( ! class_exists( 'WooCommerce' ) )    { /* show error, return */ }
    \PW\Main::instance();
} );
```

**Hook:** `plugins_loaded`
**When it runs:** After every active plugin has been loaded. This is the first moment where it's safe to check if other plugins (Elementor, WooCommerce) are available.
**Why this hook and not an earlier one:** If we checked at `init` or `wp_loaded`, some plugins might not have loaded yet, giving a false negative. `plugins_loaded` is the correct hook for plugin-to-plugin dependency checks.

**The two checks:**

- `did_action( 'elementor/loaded' )` — Elementor fires a custom action called `elementor/loaded` when it's ready. Checking this action has happened is the official way to detect Elementor, not just checking if the class exists.
- `class_exists( 'WooCommerce' )` — WooCommerce registers this class when active. Checking for it is the standard way to detect WooCommerce.

If either check fails, `add_action( 'admin_notices', ... )` shows a red error banner in the WordPress admin and the function returns early. The plugin does nothing else.

If both pass, `\PW\Main::instance()` starts the plugin.

---

## 7. Step 4 — The Main Bootstrap Class (`Main.php`)

```php
class Main {
    private static ?Main $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init(): void {
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
        App\API\RestAPI::boot();
        App\Assets\AssetsManager::boot();
        App\Wishlist\WishlistSync::boot();
    }

    public function register_widgets( \Elementor\Widgets_Manager $manager ): void {
        $manager->register( new App\ProductGridType\ProductGridWidget() );
        $manager->register( new App\ProductListType\ProductListWidget() );
    }
}
```

### The Singleton Pattern

`$instance` is a static property — it belongs to the class itself, not to any object created from the class. The first time `instance()` is called, `$instance` is `null`, so `new self()` creates one `Main` object and stores it. Every subsequent call to `instance()` returns that same stored object.

The constructor is `private` — this prevents anyone from writing `new Main()` from outside the class, forcing them to always go through `instance()`.

**Why Singleton here?** The plugin should only boot once per page request. The Singleton guarantees that even if `Main::instance()` is somehow called multiple times, only one boot happens.

### `init()` — The Wiring Method

This is where all subsystems are connected to WordPress:

- **`add_action( 'elementor/widgets/register', ... )`** — Registers `register_widgets` to be called when Elementor is ready to accept new widgets. This is the official Elementor hook for adding custom widgets.
- **`RestAPI::boot()`** — Tells the REST API subsystem to start listening for its hook.
- **`AssetsManager::boot()`** — Tells the assets subsystem to start listening for its hook.
- **`WishlistSync::boot()`** — Tells the wishlist sync subsystem to start listening for its hook.

### `register_widgets()` — Adding Widgets to Elementor

```php
public function register_widgets( \Elementor\Widgets_Manager $manager ): void {
    $manager->register( new App\ProductGridType\ProductGridWidget() );
    $manager->register( new App\ProductListType\ProductListWidget() );
}
```

Elementor passes its `Widgets_Manager` object to this function. We call `register()` on it with an instance of each widget class. After this, both widgets appear in the Elementor editor panel under the "WooCommerce Elements" category.

---

## 8. Step 5 — The Assets Manager (`AssetsManager.php`)

```php
class AssetsManager {

    public static function boot(): void {
        add_action( 'wp_enqueue_scripts',                  [ __CLASS__, 'enqueue' ] );
        add_action( 'elementor/editor/before_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_action( 'elementor/preview/enqueue_scripts',   [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue(): void {
        wp_enqueue_style( 'pw-widget-style', PW_URL . 'assets/css/widget.css', [], PW_VERSION );

        wp_enqueue_script( 'pw-widget-script', PW_URL . 'assets/js/widget.js', ['jquery'], PW_VERSION, true );

        wp_localize_script( 'pw-widget-script', 'PW_CONFIG', [
            'api_base'  => esc_url_raw( rest_url( 'pw/v1' ) ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'is_logged' => is_user_logged_in() ? 1 : 0,
            'cart_url'  => wc_get_cart_url(),
        ] );
    }
}
```

### Three Hooks, One Function

**Why three hooks?**

- `wp_enqueue_scripts` — loads assets on the normal frontend (when a visitor views a page)
- `elementor/editor/before_enqueue_scripts` — loads assets inside the Elementor editor itself (so the widget works when you're editing)
- `elementor/preview/enqueue_scripts` — loads assets in Elementor's live preview panel (the right side of the editor)

Without all three, the widget would work on the live site but break inside the Elementor editor.

### `wp_enqueue_style`

```php
wp_enqueue_style( 'pw-widget-style', PW_URL . 'assets/css/widget.css', [], PW_VERSION );
```

- First argument: a unique handle name. WordPress uses this to avoid loading the same stylesheet twice.
- Second argument: the URL to the CSS file.
- Third argument: dependencies (empty — no other CSS needs to load first).
- Fourth argument: the version number. When `PW_VERSION` changes, browsers fetch the new file instead of using their cached version.

### `wp_enqueue_script`

```php
wp_enqueue_script( 'pw-widget-script', PW_URL . 'assets/js/widget.js', ['jquery'], PW_VERSION, true );
```

- The `['jquery']` dependency tells WordPress to always load jQuery before our script.
- The final `true` argument tells WordPress to place the script at the bottom of the page, just before `</body>`. This means the page renders first and the script loads after, which is faster for the user.

### `wp_localize_script`

```php
wp_localize_script( 'pw-widget-script', 'PW_CONFIG', [
    'api_base'  => esc_url_raw( rest_url( 'pw/v1' ) ),
    'nonce'     => wp_create_nonce( 'wp_rest' ),
    'is_logged' => is_user_logged_in() ? 1 : 0,
    'cart_url'  => wc_get_cart_url(),
] );
```

This is the bridge between PHP and JavaScript. It injects a JavaScript object called `PW_CONFIG` into the page before our script runs. The JavaScript can then read `window.PW_CONFIG.api_base`, `window.PW_CONFIG.nonce`, etc.

- **`api_base`** — the full URL to our REST API (e.g. `https://yoursite.com/wp-json/pw/v1`). The JavaScript needs this to know where to send requests.
- **`nonce`** — a one-time security token. The JavaScript sends this in every API request. WordPress checks it to confirm the request came from your site and not from an attacker.
- **`is_logged`** — `1` if the user is logged in, `0` if not. The JavaScript uses this to decide whether to use localStorage or the API for the wishlist.
- **`cart_url`** — the URL to the WooCommerce cart page. Passed to JavaScript so it can show the user a link after adding to cart.

---

## 9. Step 6 — The REST API Router (`RestAPI.php`)

```php
class RestAPI {

    public static function boot(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }
```

**Hook:** `rest_api_init`
**When it runs:** When WordPress initialises its REST API system, which happens on every request to a `/wp-json/` URL.
**Why this hook:** WordPress requires all REST routes to be registered inside `rest_api_init`. Registering them anywhere else won't work.

### The Five Endpoints

```
GET  /wp-json/pw/v1/products         — fetch products with filters
POST /wp-json/pw/v1/cart/add         — add a product to cart
POST /wp-json/pw/v1/wishlist/toggle  — add/remove wishlist item
GET  /wp-json/pw/v1/wishlist         — get all wishlisted product IDs
POST /wp-json/pw/v1/wishlist/sync    — sync guest wishlist to DB on login
```

Each route registration has four parts:

**1. Namespace and path** — `'pw/v1'` is the namespace (your plugin identifier and version), `/products` is the specific path. Together they form `/wp-json/pw/v1/products`.

**2. Methods** — `WP_REST_Server::READABLE` means GET only. `WP_REST_Server::CREATABLE` means POST only. Using these constants instead of the strings `"GET"` and `"POST"` is the WordPress convention.

**3. Callback** — which function handles requests to this URL.

**4. Permission callback** — controls who can call this endpoint:

```php
// Public — anyone including guests can fetch products
'permission_callback' => '__return_true',

// Protected — only logged-in users can use the wishlist
'permission_callback' => function () {
    return is_user_logged_in();
},
```

If the permission callback returns `false`, WordPress automatically returns a 401 Unauthorized response before your callback even runs.

**5. Args** — define what parameters are accepted and their rules:

```php
'args' => [
    'page' => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
]
```

WordPress validates these automatically. If someone sends `page=banana`, WordPress rejects it before your callback sees it, because `banana` is not an integer.

---

## 10. Step 7 — The Product Query Engine (`ProductQuery.php`)

```php
class ProductQuery {

    public static function run( array $params ): array {
        // ... sanitise params ...
        // ... build $args array ...
        // ... check cache ...
        $query = new \WP_Query( $args );
        // ... cache result ...
        return $result;
    }
}
```

This class has one job: take filter parameters and return matching products from the database. It uses WordPress's `WP_Query` class, which is the standard way to query posts (and WooCommerce products are stored as posts).

### Building the Query Arguments

**Base query:**

```php
$args = [
    'post_type'      => 'product',   // Only get WooCommerce products
    'post_status'    => 'publish',   // Only live, published products
    'posts_per_page' => $per_page,   // How many per page
    'paged'          => $page,       // Which page number
];
```

**Search:**

```php
if ( ! empty( $search ) ) {
    $args['s'] = $search;
}
```

WordPress's built-in `s` parameter searches post titles and content. Only added when the user has typed something — leaving it out returns all products.

**Category filter:**

```php
$args['tax_query'] = [ [
    'taxonomy'         => 'product_cat',
    'field'            => is_numeric( $category ) ? 'term_id' : 'slug',
    'terms'            => $category,
    'include_children' => true,
] ];
```

`tax_query` filters by taxonomy (a WordPress word for categorisation systems). `product_cat` is WooCommerce's product category taxonomy. `include_children => true` means if you filter by "Clothing", products in "T-Shirts" (a sub-category) also appear.

**Sorting:**

```php
case 'price_low':
    $args['meta_key'] = '_price';
    $args['orderby']  = 'meta_value_num';
    $args['order']    = 'ASC';
```

WooCommerce stores product prices in the WordPress post metadata table under the key `_price`. `meta_value_num` tells WordPress to sort numerically (so 10 comes after 9, not before 2 as it would alphabetically).

### Transient Caching

```php
$cache_key = 'pw_query_' . md5( serialize( $args ) );
$cached    = get_transient( $cache_key );

if ( $cached !== false ) {
    return $cached;
}

// ... run query ...

set_transient( $cache_key, $result, 60 );
```

**What is a transient?** A transient is WordPress's built-in short-term cache. It stores data in the database (or in a memory cache like Redis if configured) with an expiry time.

**How the cache key works:** `serialize` converts the entire `$args` array to a string. `md5` hashes that string to a short, fixed-length key. If the same set of filters and sort options is used again within 60 seconds, the cached result is returned instantly — no database query needed.

**Why 60 seconds?** Products don't change every second. Caching for 60 seconds means a busy store with many visitors all searching for the same thing only hits the database once per minute instead of thousands of times.

---

## 11. Step 8 — The Products Controller (`ProductsController.php`)

```php
class ProductsController {

    public static function get_products( \WP_REST_Request $request ): \WP_REST_Response { ... }

    private static function format_product( int $product_id, array $wishlist_ids ): array { ... }
}
```

This class is called by WordPress when a `GET /wp-json/pw/v1/products` request arrives. It has two functions.

### `get_products()` — The Entry Point

```php
$params = [
    'page'     => (int) $request->get_param( 'page' ),
    'search'   => sanitize_text_field( $request->get_param( 'search' ) ),
    'category' => sanitize_text_field( $request->get_param( 'category' ) ),
    'sort'     => sanitize_key( $request->get_param( 'sort' ) ),
];
```

`$request->get_param()` safely reads URL parameters. Each value is then sanitised:

- `(int)` casts to integer — `"abc"` becomes `0`
- `sanitize_text_field()` strips HTML tags and extra whitespace
- `sanitize_key()` strips anything that isn't a lowercase letter, number, underscore, or dash

Then it calls `ProductQuery::run($params)` to get the raw results.

```php
$user_id      = get_current_user_id();
$wishlist_ids = $user_id ? WishlistRepository::get_product_ids( $user_id ) : [];
```

`get_current_user_id()` returns `0` for guests. For logged-in users, it fetches all their wishlisted product IDs in one single database query — not one query per product. This is efficient even if there are 100 products on the page.

```php
$products = array_map( function ( $post ) use ( $wishlist_ids ) {
    return self::format_product( $post->ID, $wishlist_ids );
}, $query_result['posts'] );

$products = array_values( array_filter( $products ) );
```

`array_map` runs `format_product` on every post returned by the query. `array_filter` removes any empty arrays (returned when `wc_get_product()` fails for a deleted product). `array_values` resets the array keys so the JSON output is a clean numbered list.

### `format_product()` — Shaping One Product for the Frontend

This private function takes a raw product ID and turns it into a clean array the JavaScript can use.

**Product type detection:**

```php
if      ( $product->is_type( 'variable' ) ) $type = 'variable';
elseif  ( $product->is_type( 'grouped' ) )  $type = 'grouped';
elseif  ( $product->is_type( 'external' ) ) $type = 'external';
else                                         $type = 'simple';
```

WooCommerce has four product types:

- **simple** — one product, one price, add to cart directly
- **variable** — has options (size, colour etc.), must go to product page to pick a variant
- **grouped** — a collection of related products shown together (like "Logo Collection")
- **external** — sold on another website, clicking should open that external URL

Previously only simple vs variable was checked, which caused grouped and external products to be treated as simple — showing an Add to Cart button that would fail.

**External product extra data:**

```php
if ( $type === 'external' ) {
    $buy_url     = esc_url( $product->get_product_url() );
    $button_text = $product->get_button_text() ?: 'Buy Now';
}
```

`get_product_url()` returns the external URL the store admin entered. `get_button_text()` returns the custom button label they set (e.g. "Buy at WordPress.org"). If they didn't set one, it defaults to "Buy Now".

**Wishlist status per product:**

```php
'wishlist' => in_array( $product_id, $wishlist_ids, true ),
```

Because we fetched all wishlist IDs upfront in one query, this is just an array lookup — `O(1)` time — not another database query.

---

## 12. Step 9 — The Cart Controller (`CartController.php`)

```php
class CartController {

    public static function add_to_cart( \WP_REST_Request $request ): \WP_REST_Response { ... }
}
```

Called when `POST /wp-json/pw/v1/cart/add` is received.

### Validation Checks

Before touching the cart, three checks run:

1. **Product exists:** `wc_get_product( $product_id )` returns `false` for invalid IDs.
2. **Product is not variable:** Variable products cannot be added without choosing a variant first. Blocked both here in PHP and in the JavaScript (defence in depth).
3. **Product is in stock:** `$product->is_in_stock()` checks WooCommerce's stock management.

### Session Initialisation (The Bug Fix)

```php
if ( ! WC()->session ) {
    WC()->session = new \WC_Session_Handler();
    WC()->session->init();
}

if ( ! WC()->customer ) {
    WC()->customer = new \WC_Customer( get_current_user_id(), true );
}

if ( ! WC()->cart ) {
    WC()->cart = new \WC_Cart();
    WC()->cart->get_cart_from_session();
}
```

**The problem this fixes:** WooCommerce normally initialises its session, customer, and cart during a standard WordPress page load. During a REST API request, none of that automatic setup happens. Without it, every single AJAX cart request starts with a brand new empty cart — so only the most recently added product would ever be in the cart.

**The fix:** Manually initialise the session handler, customer, and cart in the correct order, then call `get_cart_from_session()` to load whatever is already saved. This mirrors exactly what WooCommerce does on a normal page load.

### Session Save (The Second Bug Fix)

```php
WC()->session->save_data();
```

**The problem this fixes:** WooCommerce normally saves session data via the `shutdown` hook, which fires at the very end of a PHP request. REST API requests do not reliably fire this hook. So even after successfully adding to cart, the cart data was thrown away when the PHP process ended.

**The fix:** Manually call `save_data()` immediately after mutating the cart. This writes the cart state to the database right now, so the next request reads it correctly.

---

## 13. Step 10 — The Wishlist System

The wishlist is split across four files, each with one responsibility.

### `WishlistTable.php` — Database Setup

```php
public static function create(): void {
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
}
```

`dbDelta()` is WordPress's special table creation function. Unlike raw `CREATE TABLE`, it is safe to run multiple times — it creates the table if it doesn't exist, updates it if the structure changed, and does nothing if everything is already correct.

**The table design:**

- `UNIQUE KEY user_product` — prevents the same user from adding the same product to their wishlist twice at the database level. Even if a bug in the code tried to insert twice, the database would reject the duplicate.
- `KEY user_id` — a database index on `user_id`. When you search "get all products for user 5", the database can find them instantly instead of scanning every row.

`$wpdb->prefix` uses WordPress's configured table prefix (usually `wp_`). This means the table is named `wp_pw_wishlist`, or `myprefix_pw_wishlist` if the site uses a custom prefix. Using the prefix is required — hardcoding `wp_` would break sites with custom prefixes.

### `WishlistRepository.php` — All SQL Operations

This class is the only place that touches the `wp_pw_wishlist` table. All other code asks this class instead of writing SQL themselves.

**`table()`** — returns the correct table name including WordPress prefix:

```php
private static function table(): string {
    global $wpdb;
    return $wpdb->prefix . 'pw_wishlist';
}
```

**`add()`** — inserts a wishlist row:

```php
$wpdb->insert(
    self::table(),
    [ 'user_id' => $user_id, 'product_id' => $product_id, 'created_at' => current_time('mysql') ],
    [ '%d', '%d', '%s' ]
);
```

The third argument `['%d', '%d', '%s']` tells WordPress the data types: `%d` = integer, `%s` = string. WordPress uses these to create a properly escaped SQL statement internally, preventing SQL injection.

**`remove()`** — deletes a specific wishlist row by matching both `user_id` and `product_id`.

**`is_wishlisted()`** — checks if a row exists:

```php
$count = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM %i WHERE user_id = %d AND product_id = %d",
    self::table(), $user_id, $product_id
) );
```

`$wpdb->prepare()` creates a safely parameterised query. `%i` is a table name placeholder (safe against injection). `get_var()` returns just the single count value.

**`get_product_ids()`** — returns all wishlisted product IDs for a user:

```php
$results = $wpdb->get_col( ... );
return array_map( 'intval', $results );
```

`get_col()` returns a flat array of values from one column. `array_map('intval', ...)` converts every value from a string to an integer, because databases always return strings even for number columns.

### `WishlistController.php` — Handling API Requests

**`toggle()`** — checks the current state, then does the opposite:

```php
$already = WishlistRepository::is_wishlisted( $user_id, $product_id );

if ( $already ) {
    WishlistRepository::remove( ... );
    $wishlisted = false;
} else {
    WishlistRepository::add( ... );
    $wishlisted = true;
}
```

Returns the new state so the JavaScript knows whether to show the heart as filled or empty.

**`get_wishlist()`** — returns all the user's wishlisted product IDs. The JavaScript calls this on page load to correctly show which hearts should be filled based on wishlist items from a previous session.

### `WishlistSync.php` — Syncing Guest Wishlists

Guests cannot use the database wishlist (they have no user ID). So guest wishlists are stored in the browser's localStorage by the JavaScript. When a guest logs in, this endpoint moves their localStorage wishlist into the database.

**Hook:** `rest_api_init`
**Endpoint:** `POST /wp-json/pw/v1/wishlist/sync`
**Permission:** Only logged-in users (the sync only makes sense after login)

```php
foreach ( $product_ids as $pid ) {
    if ( $pid > 0 && wc_get_product( $pid ) ) {
        if ( ! WishlistRepository::is_wishlisted( $user_id, $pid ) ) {
            WishlistRepository::add( $user_id, $pid );
        }
    }
}
```

Each ID is validated (positive integer, product actually exists) before inserting. The `is_wishlisted` check prevents duplicate insert attempts (the `UNIQUE KEY` database constraint would also catch this, but checking first avoids a database error).

---

## 14. Step 11 — The Elementor Widgets

Both widgets (`ProductGridWidget` and `ProductListWidget`) work identically — they just output different layout settings. The grid widget is described here in detail.

### Extending `Widget_Base`

```php
class ProductGridWidget extends \Elementor\Widget_Base {
```

To create an Elementor widget you must extend their `Widget_Base` class and implement specific methods. This is the Elementor contract.

### Required Identity Methods

```php
public function get_name(): string    { return 'pw_product_grid'; }
public function get_title(): string   { return 'Product Grid'; }
public function get_icon(): string    { return 'eicon-products'; }
public function get_categories(): array { return [ 'woocommerce-elements' ]; }
public function get_keywords(): array { return [ 'woocommerce', 'products', 'grid' ]; }
```

- `get_name()` — a unique machine name. Elementor uses it internally to identify the widget. Never change this after the widget is in use — it would break existing pages.
- `get_title()` — the label shown in the Elementor panel.
- `get_icon()` — the icon shown in the panel. `eicon-products` is one of Elementor's built-in icons.
- `get_categories()` — which section of the panel to appear in. `woocommerce-elements` puts it in the WooCommerce section.
- `get_keywords()` — when someone types "products" in the Elementor search box, these keywords help the widget appear.

### `register_controls()` — The Settings Panel

This method defines everything that appears in the Elementor sidebar when you click the widget. Each `add_control()` creates one setting field:

```php
$this->add_control( 'posts_per_page', [
    'label'   => 'Products Per Page',
    'type'    => \Elementor\Controls_Manager::NUMBER,
    'default' => 12,
    'min'     => 1,
    'max'     => 100,
] );
```

Control types used:

- `NUMBER` — a number input field
- `SELECT` — a dropdown menu
- `SWITCHER` — a toggle switch (yes/no)
- `COLOR` — a colour picker
- `SLIDER` — a range slider with min and max

**Style controls with CSS selectors:**

```php
$this->add_control( 'card_bg', [
    'type'      => Controls_Manager::COLOR,
    'selectors' => [ '{{WRAPPER}} .pw-product-card' => 'background-color: {{VALUE}};' ],
] );
```

`{{WRAPPER}}` is replaced by Elementor with the widget's unique CSS class (e.g. `.elementor-widget-pw_product_grid`). `{{VALUE}}` is replaced with the chosen colour. This means the CSS change only affects this specific widget on the page, not all product cards.

### `render()` — What the Widget Outputs

```php
protected function render(): void {
    $settings  = $this->get_settings_for_display();
    $widget_id = $this->get_id();

    $config = [
        'id'         => $widget_id,
        'layout'     => 'grid',
        'per_page'   => (int) $settings['posts_per_page'],
        'columns'    => (int) $settings['columns'],
        'category'   => sanitize_text_field( $settings['category'] ),
        'sort'       => sanitize_key( $settings['orderby'] ),
        'pagination' => $settings['show_pagination'] === 'yes',
        'filters'    => $settings['show_filters'] === 'yes',
        'search'     => $settings['show_search'] === 'yes',
        'wishlist'   => $settings['show_wishlist'] === 'yes',
    ];

    echo '<div class="pw-widget pw-widget--grid"
               data-id="' . esc_attr( $widget_id ) . '"
               data-settings="' . esc_attr( wp_json_encode( $config ) ) . '">
          </div>';
}
```

**The key design decision:** The widget outputs only one empty `<div>`. All the product HTML is generated by JavaScript after the page loads. The widget's only job is to pass the Elementor settings to JavaScript via the `data-settings` attribute.

`wp_json_encode()` converts the PHP array to a JSON string. `esc_attr()` makes it safe to use inside an HTML attribute by escaping quotes and special characters.

Each widget on the page has a unique `$this->get_id()` value, which is passed in the config. The JavaScript uses this ID to keep widget instances separate so their states (search term, current page, active filters) don't interfere with each other.

---

## 15. Step 12 — The Frontend JavaScript Engine (`widget.js`)

The entire JavaScript is wrapped in an **Immediately Invoked Function Expression (IIFE)**:

```javascript
(function () {
  "use strict";
  // everything is here
})();
```

The IIFE creates a private scope. Variables defined inside cannot be accessed from outside. This prevents conflicts with jQuery, WooCommerce scripts, or theme scripts. `"use strict"` enables strict mode, which catches common mistakes like using undeclared variables.

### Global Configuration

```javascript
const API = window.PW_CONFIG?.api_base || "/wp-json/pw/v1";
const NONCE = window.PW_CONFIG?.nonce || "";
const IS_LOGGED = parseInt(window.PW_CONFIG?.is_logged || "0", 10) === 1;
```

`?.` is optional chaining — if `PW_CONFIG` doesn't exist, the expression returns `undefined` instead of throwing an error. The `||` fallback provides a safe default.

### `apiFetch( endpoint, options )` — The HTTP Helper

```javascript
function apiFetch(endpoint, options = {}) {
  const headers = {
    "Content-Type": "application/json",
    "X-WP-Nonce": NONCE,
    ...(options.headers || {}),
  };
  return fetch(`${API}${endpoint}`, { ...options, headers }).then((r) =>
    r.json(),
  );
}
```

Every API call in the plugin goes through this function. It automatically:

- Prepends the full API base URL to the endpoint
- Adds the `X-WP-Nonce` header that WordPress checks for security
- Sets `Content-Type` to `application/json`
- Parses the response as JSON and returns the result

### `debounce( fn, delay )` — Preventing Too Many Requests

```javascript
function debounce(fn, delay) {
  let timer;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}
```

When used with the search input, this prevents an API call from firing on every single keystroke. Instead, it waits 300ms after the user stops typing before calling the function. If the user types again before 300ms passes, the timer resets. The actual API call only fires once, 300ms after the last keystroke.

`fn.apply(this, args)` is used instead of just `fn(args)` to preserve the original `this` context and pass all arguments correctly.

### `PWToast` — The Notification System

```javascript
const PWToast = (() => {
  let container;

  function init() { ... } // creates the container <div> once
  function show(message, type, duration) { ... }

  return { show };
})();
```

`PWToast` is a singleton — one object, created once, accessible throughout the script. The `let container` variable is captured in the closure and persists between calls.

**`show()` — displaying a notification:**

```javascript
const toast = document.createElement("div");
toast.className = `pw-toast pw-toast--${type}`;
container.appendChild(toast);

requestAnimationFrame(() => toast.classList.add("pw-toast--visible"));

setTimeout(() => {
  toast.classList.remove("pw-toast--visible");
  toast.addEventListener("transitionend", () => toast.remove(), { once: true });
}, duration);
```

- Creates a `<div>` and appends it to the body
- `requestAnimationFrame` delays the class addition by one frame — this is required because the element needs to exist in the DOM before a CSS transition can start
- After `duration` milliseconds, removes the visible class, which triggers a CSS fade-out transition
- `transitionend` fires when the CSS transition finishes — only then is the element removed from the DOM
- `{ once: true }` means the event listener automatically removes itself after firing once

### `PWWishlist` — The Wishlist State Manager

```javascript
const PWWishlist = (() => {
  const LS_KEY = "pw_guest_wishlist";
  // ...
  return { isWishlisted, toggle, syncGuestOnLogin, getGuest };
})();
```

**`toggle( productId )`** — has two completely different code paths:

```javascript
async function toggle(productId) {
  if (!IS_LOGGED) {
    // Guest: update localStorage immediately
    const ids = getGuest();
    const idx = ids.indexOf(productId);
    const adding = idx === -1;
    if (adding) ids.push(productId);
    else ids.splice(idx, 1);
    saveGuest(ids);
    return { wishlisted: adding, product_id: productId };
  }

  // Logged-in: send to API
  const res = await apiFetch("/wishlist/toggle", {
    method: "POST",
    body: JSON.stringify({ product_id: productId }),
  });
  return res;
}
```

For guests: instant localStorage update, no network request, returns immediately.
For logged-in users: async API call, waits for server response.

Both return the same shape of object so the calling code doesn't need to know which path was taken.

**`syncGuestOnLogin()`** — only runs when a user is logged in and there are guest wishlist items in localStorage:

```javascript
async function syncGuestOnLogin() {
  if (!IS_LOGGED) return;
  const guestIds = getGuest();
  if (!guestIds.length) return;
  // send to sync API
  localStorage.removeItem(LS_KEY); // clean up after syncing
}
```

### `PWQuickView` — The Product Preview Modal

```javascript
const PWQuickView = (() => {
  let modal, overlay, inner;

  function init() { ... }  // creates modal HTML once
  function open(product) { ... }  // fills content and shows modal
  function close() { ... }  // hides modal

  return { open, close };
})();
```

**`open( product )`** — receives the full product data object (embedded in the card's `data-product` attribute by the PHP render):

- Fills the modal with the product's image, title, price, and rating
- Shows the modal by adding `pw-qv--open` class (CSS handles the animation)
- Sets `document.body.style.overflow = "hidden"` to prevent the page behind from scrolling
- Attaches a new Add to Cart click handler inside the modal

**`close()`** — removes the class and restores body scroll.

### `ProductWidget` Class — The Core Engine

```javascript
class ProductWidget {
  constructor(el) { ... }
  _render() { ... }
  _skeleton(count) { ... }
  _bindUI() { ... }
  _bindGlobalEvents() { ... }
  _loadCategories() { ... }
  async _load() { ... }
  _renderProducts(products) { ... }
  _gridCard(p, wishlisted) { ... }
  _listCard(p, wishlisted) { ... }
  _bindProductEvents(container) { ... }
  _renderPagination(pagination) { ... }
  _renderError(msg) { ... }
}
```

One instance is created for every `.pw-widget` element on the page. Each instance has completely private state — its own page number, search term, active filters, loading flag, and abort controller. No two widgets share state.

**`constructor( el )`:**

```javascript
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

`this.settings` — parsed from the `data-settings` HTML attribute. Contains everything the Elementor widget was configured with.
`this.state` — the widget's live current state. Every API call reads from this object.

**`_render()`** — builds the shell HTML:
Generates the toolbar (search input + dropdowns), an empty products container, and pagination container. Immediately fills the products container with skeleton loading cards so the user sees something instantly.

**`_skeleton( count )`** — generates shimmer placeholder cards:
Returns a string of placeholder card HTML. These have CSS animations that create a sweeping shimmer effect, making the page feel fast while products load.

**`_bindUI()`** — attaches event listeners to search and dropdowns:

```javascript
const debouncedSearch = debounce((val) => {
  this.state.search = val;
  this.state.page = 1; // always reset to page 1 on new search
  this._load();
}, 300);

searchInput.addEventListener("input", (e) => {
  debouncedSearch(e.target.value.trim());
});
```

Every state change resets `page` to `1` because a new filter means starting from the beginning.

**`_bindGlobalEvents()`** — listens for events from other widgets:

```javascript
document.addEventListener("pw_wishlist_updated", (e) => {
  const { product_id, wishlisted } = e.detail;
  this.el
    .querySelectorAll(`.pw-wishlist-btn[data-product-id="${product_id}"]`)
    .forEach((btn) => {
      btn.classList.toggle("pw-wishlist-btn--active", wishlisted);
    });
});
```

When any widget on the page fires a `pw_wishlist_updated` event, every widget reacts and updates its heart buttons for that product. This is how cross-widget wishlist sync works. Note: `this.el.querySelectorAll` only searches within this widget's own DOM, but all widgets listen to the same global event.

**`async _load()`** — the AJAX fetch:

```javascript
if (this.state.loading) {
  if (this.abortController) this.abortController.abort();
}
this.abortController = new AbortController();
```

If a previous request is still running when a new one starts (user changed filters quickly), the old request is cancelled via the `AbortController` API. This prevents old results from overwriting newer ones.

The `signal: this.abortController.signal` is passed to `fetch()` via `apiFetch`. When `abort()` is called, the browser cancels the in-flight HTTP request.

**`_renderProducts( products )`** — draws the product cards:
Loops through the product data, checks if the layout is grid or list, and calls either `_gridCard()` or `_listCard()`. After rendering, calls `_bindProductEvents()` on the new HTML.

**`_gridCard( p, wishlisted )` and `_listCard( p, wishlisted )`** — the button logic:

```javascript
const isSimple   = p.type === "simple";
const isExternal = p.type === "external";

${isSimple
  ? `<button class="pw-add-to-cart">Add to Cart</button>`
  : isExternal
  ? `<a class="pw-buy-now" href="${p.buy_url}" target="_blank">Buy Now</a>`
  : `<a class="pw-view-details" href="${p.permalink}">View Details</a>`
}
```

Three possible buttons:

- **simple** → AJAX Add to Cart button
- **external** → opens the external URL in a new tab
- **variable or grouped** → link to the product page

Quick View is only shown for simple products — it makes no sense for grouped or external products.

**`_bindProductEvents( container )`** — attaches click handlers to the just-rendered cards:

_Add to Cart button:_

```javascript
btn.disabled = true;
btn.innerHTML = `<span class="pw-spinner"></span> Adding…`;
// ... API call ...
PWToast.show("Product added to cart!", "success");
document.dispatchEvent(
  new CustomEvent("pw_cart_updated", {
    detail: { cart_count: res.cart_count },
  }),
);
```

Disables immediately on click to prevent double-clicking. Shows spinner. On success, shows toast and fires `pw_cart_updated` event so the cart counter in the header updates.

_Wishlist button:_

```javascript
const res = await PWWishlist.toggle(productId);
document.dispatchEvent(
  new CustomEvent("pw_wishlist_updated", {
    detail: { product_id: productId, wishlisted: res.wishlisted },
  }),
);
```

After toggling, fires a global event. Every widget on the page (via `_bindGlobalEvents`) reacts and updates all heart buttons for that product across the whole page.

_Quick View button:_

```javascript
const product = JSON.parse(e.currentTarget.dataset.product);
PWQuickView.open(product);
```

The full product data was embedded in the HTML when the card was rendered. No second API call needed — just parse and pass to the modal.

**`_renderPagination( pagination )`** — draws page number buttons:
Includes smart ellipsis logic: for a result with 20 pages, it shows `1 2 … 5 6 7 … 19 20` instead of all 20 buttons. Each button updates `this.state.page` and calls `this._load()`, then scrolls the widget into view smoothly.

### Widget Initialisation

```javascript
function initWidgets() {
  document.querySelectorAll(".pw-widget[data-settings]").forEach((el) => {
    if (!el.dataset.pwInit) {
      el.dataset.pwInit = "1";
      new ProductWidget(el);
    }
  });
}
```

Finds every `.pw-widget` element with a `data-settings` attribute and creates a `ProductWidget` instance for it. `el.dataset.pwInit = "1"` marks the element as initialised so `initWidgets()` is safe to call multiple times without creating duplicate instances.

```javascript
// Elementor editor re-init
window.elementorFrontend.hooks.addAction(
  "frontend/element_ready/pw_product_grid.default",
  ($scope) => {
    const el = $scope[0]?.querySelector(".pw-widget");
    if (el && !el.dataset.pwInit) {
      el.dataset.pwInit = "1";
      new ProductWidget(el);
    }
  },
);
```

Elementor fires `frontend/element_ready/{widget_name}.default` in the editor when a widget is rendered or updated. Without this hook, the widget would work on the live site but show as an empty div inside the Elementor editor.

---

## 16. Step 13 — The Uninstall Script (`uninstall.php`)

```php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
```

`WP_UNINSTALL_PLUGIN` is a constant WordPress sets only during a genuine plugin deletion. This line ensures the script cannot be run by visiting the URL directly in a browser.

```php
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pw_wishlist" );
delete_option( 'pw_version' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pw_query_%'" );
```

**When it runs:** Only when an admin clicks "Delete" on the plugin in WP Admin → Plugins. Not on deactivation — only on deletion.

**What it cleans up:**

- Drops the wishlist table permanently
- Removes any stored plugin options
- Deletes all cached query transients from the options table

---

## 17. All WordPress Hooks Used

| Hook                                      | Type           | File                          | Why It's Used                                                           |
| ----------------------------------------- | -------------- | ----------------------------- | ----------------------------------------------------------------------- |
| `plugins_loaded`                          | action         | main file                     | Safe point to check if dependencies (WooCommerce, Elementor) are active |
| `admin_notices`                           | action         | main file                     | Shows error banners in WP Admin when dependencies are missing           |
| `register_activation_hook`                | function       | main file                     | Creates database table once when plugin is activated                    |
| `elementor/widgets/register`              | action         | Main.php                      | Registers our widgets with Elementor at the correct time                |
| `wp_enqueue_scripts`                      | action         | AssetsManager.php             | Loads CSS and JS on frontend pages                                      |
| `elementor/editor/before_enqueue_scripts` | action         | AssetsManager.php             | Loads CSS and JS inside Elementor editor                                |
| `elementor/preview/enqueue_scripts`       | action         | AssetsManager.php             | Loads CSS and JS in Elementor live preview                              |
| `rest_api_init`                           | action         | RestAPI.php, WishlistSync.php | Registers REST API routes when the REST system initialises              |
| `after_setup_theme`                       | action         | (STF Header plugin)           | Registers nav menu locations after theme is ready                       |
| `frontend/element_ready/{name}.default`   | Elementor hook | widget.js                     | Re-initialises widget JavaScript inside the Elementor editor            |

---

## 18. Complete Request-to-Response Flows

### User types "shoes" in the search box

```
User types "s", "sh", "sho", "shoe", "shoes"
   ↓
debounce waits 300ms after last keystroke
   ↓
this.state.search = "shoes", this.state.page = 1
   ↓
_load() is called
   ↓
Previous request aborted via AbortController
   ↓
GET /wp-json/pw/v1/products?search=shoes&page=1&per_page=12&sort=default
   ↓ (PHP)
RestAPI routes request to ProductsController::get_products()
   ↓
ProductQuery::run() builds WP_Query with 's' => 'shoes'
   ↓
Cache miss (new search) → queries database
   ↓
Results cached for 60 seconds
   ↓
format_product() shapes each result into JSON
   ↓
JSON response sent back to browser
   ↓ (JavaScript)
_renderProducts() generates card HTML
   ↓
Grid innerHTML replaced with new cards
   ↓
_bindProductEvents() attaches click handlers to new cards
```

### User clicks "Add to Cart" on a simple product

```
Click on Add to Cart button
   ↓
Button disabled, spinner shown (prevents double click)
   ↓
POST /wp-json/pw/v1/cart/add  { product_id: 42, quantity: 1 }
   ↓ (PHP)
CartController::add_to_cart()
   ↓
Product fetched, validated (exists, not variable, in stock)
   ↓
WC session initialised from stored session cookie
   ↓
WC cart loaded from that session
   ↓
WC()->cart->add_to_cart(42, 1)
   ↓
WC()->cart->calculate_totals()
   ↓
WC()->session->save_data()  ← writes to database immediately
   ↓
{ success: true, cart_count: 3 } sent back
   ↓ (JavaScript)
Toast: "Product added to cart!"
   ↓
CustomEvent "pw_cart_updated" dispatched with cart_count: 3
   ↓
Header cart badge updates to show "3"
   ↓
Button shows "Added ✓" for 2 seconds, then resets
```

### User clicks wishlist heart (logged in)

```
Click on heart button
   ↓
Button disabled immediately
   ↓
PWWishlist.toggle(42) called
   ↓
IS_LOGGED is true → takes API path
   ↓
POST /wp-json/pw/v1/wishlist/toggle  { product_id: 42 }
   ↓ (PHP)
WishlistController::toggle()
   ↓
WishlistRepository::is_wishlisted(userId, 42) → false
   ↓
WishlistRepository::add(userId, 42) → INSERT INTO wp_pw_wishlist
   ↓
{ success: true, wishlisted: true, product_id: 42 }
   ↓ (JavaScript)
CustomEvent "pw_wishlist_updated" dispatched globally
   ↓
Every widget on the page reacts via _bindGlobalEvents()
   ↓
ALL heart buttons for product 42 across ALL widgets turn filled
   ↓
Toast: "Added to wishlist ♥"
   ↓
Button re-enabled
```

### Guest wishlists product, then logs in

```
Guest clicks heart on product 42
   ↓
PWWishlist.toggle(42)
   ↓
IS_LOGGED is false → takes localStorage path
   ↓
localStorage: pw_guest_wishlist = [42]
   ↓
Returns immediately (no network request)
   ↓
... later, user logs in via WordPress login page ...
   ↓
Page reloads, PHP sets IS_LOGGED = 1 in PW_CONFIG
   ↓
widget.js runs, PWWishlist.syncGuestOnLogin() is called
   ↓
guestIds = [42] found in localStorage
   ↓
POST /wp-json/pw/v1/wishlist/sync  { product_ids: [42] }
   ↓ (PHP)
WishlistSync::sync() validates product 42 exists
   ↓
WishlistRepository::add(userId, 42) → INSERT INTO wp_pw_wishlist
   ↓
{ success: true, synced: 1 }
   ↓ (JavaScript)
localStorage.removeItem("pw_guest_wishlist")
   ↓
Wishlist now lives in database. Guest data cleaned up.
```

---

## 19. Bug Fixes Applied and Why

### Bug 1: Only One Product Ever Stayed in Cart

**Root cause:** WooCommerce's session is not automatically initialised during REST API requests. Every AJAX cart request started with a blank session and a blank cart — so the second "Add to Cart" always saw an empty cart and overwrote the first product.

**What the old code did:**

```php
if ( ! WC()->cart ) {
    wc_load_cart(); // Only loaded the cart object, did not restore the session
}
```

**What the fix does:**

```php
if ( ! WC()->session ) {
    WC()->session = new \WC_Session_Handler();
    WC()->session->init(); // Reads the session cookie, loads stored data
}
if ( ! WC()->customer ) {
    WC()->customer = new \WC_Customer( get_current_user_id(), true );
}
if ( ! WC()->cart ) {
    WC()->cart = new \WC_Cart();
    WC()->cart->get_cart_from_session(); // Loads whatever was already in the cart
}
```

### Bug 2: Cart Was Forgotten After Each Request

**Root cause:** WooCommerce normally saves session data via the `shutdown` PHP hook at the end of a request. REST API requests do not reliably fire this hook.

**What the fix does:**

```php
WC()->session->save_data(); // Force-save immediately after adding to cart
```

### Bug 3: Grouped and External Products Showed Wrong Buttons

**Root cause:** The product type check was binary — only checking for `variable`, everything else was treated as `simple`. Grouped products (like "Logo Collection") and external products (like "WordPress Pennant") both fell through as `simple` and got an Add to Cart button that would fail.

**What the old code did:**

```php
$type = $product->is_type('variable') ? 'variable' : 'simple';
```

**What the fix does:**

```php
if      ($product->is_type('variable')) $type = 'variable';
elseif  ($product->is_type('grouped'))  $type = 'grouped';
elseif  ($product->is_type('external')) $type = 'external';
else                                    $type = 'simple';
```

And in JavaScript, three-way button logic:

- `simple` → Add to Cart (AJAX)
- `external` → Buy Now link (opens external URL in new tab)
- `variable` or `grouped` → View Details (links to product page)
