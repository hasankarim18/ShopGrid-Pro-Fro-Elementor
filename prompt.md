Alright—this is now rewritten as a **complete, refined, execution-ready product specification** starting from **expected behavior (what the plugin does)** → then **how to build it (technical implementation)**.

This is something you can hand to:

- a developer
- an agency
- or an AI

…and they **should not need to ask questions**.

---

# 📘 WooCommerce Elementor Addon

## **Functional + Technical Specification (Final Version)**

---

# 1. 🎯 PRODUCT OVERVIEW (READ THIS FIRST)

## 1.1 What This Plugin Is

This plugin is a **WooCommerce-focused Elementor addon** that allows users to display and interact with products using **dynamic, AJAX-powered widgets**.

It is NOT a static grid.

It behaves like a **mini frontend application inside Elementor**.

---

## 1.2 Core Capabilities

The plugin provides a **Product Display Widget** with:

- Grid layout
- List layout
- AJAX pagination
- AJAX filtering
- Live search (debounced)
- Wishlist system (database-based)
- Multi-instance independence (critical)

---

# 2. 🧠 EXPECTED BEHAVIOR (NON-NEGOTIABLE)

This section defines exactly how the plugin MUST behave.

---

## 2.1 Layout Behavior

The widget must support:

### Grid Layout

- Products displayed in columns
- Responsive (desktop/tablet/mobile)

### List Layout

- Horizontal layout
- Image + content side-by-side

---

## 2.2 Pagination Behavior

- Pagination can be:
  - Enabled
  - Disabled

### If ENABLED:

- Works via AJAX (NO page reload)
- Updates product list dynamically

### If DISABLED:

- Shows limited products (defined in settings)
- Used for homepage sections

---

## 2.3 Filtering Behavior

Filters are part of the widget and can be:

- Enabled or Disabled

### Supported Filters

1. Price Sorting:
   - Low → High
   - High → Low

2. Alphabetical Sorting:
   - A → Z
   - Z → A

3. Default Sorting:
   - WooCommerce default

4. Category Filter:
   - Dropdown or selectable UI
   - Filters products by category

---

### Filter Rules

- Changing any filter:
  - MUST NOT reload page
  - MUST trigger AJAX request
  - MUST reset pagination to page 1

---

## 2.4 Search Behavior

- Search input is part of widget
- Can be enabled/disabled

### Behavior:

- Typing triggers search (debounced 300ms)
- Results update instantly (AJAX)
- Clearing input:
  - Resets to default product list

---

## 2.5 Wishlist Behavior (CRITICAL FEATURE)

### When user clicks wishlist:

- Product is stored in database
- Wishlist icon changes state (active)

### Global Sync Requirement:

If the same product appears:

- In another widget
- On another page

👉 It MUST show as **already wishlisted**

---

### Wishlist Rules

- Logged-in users → stored in DB
- Guests → stored in localStorage
- On login → sync guest wishlist to DB

---

## 2.6 Multi-Widget Behavior (CRITICAL)

Multiple widgets can exist:

- Same page
- Different pages

### Rules:

- Each widget works independently
- Filters/search in one widget MUST NOT affect another
- No shared state

---

# 3. 🧱 TECHNICAL ARCHITECTURE

---

## 3.1 Technology Stack

- PHP (WordPress Plugin)
- WooCommerce
- Elementor
- JavaScript (Vanilla or minimal framework)
- REST API (MANDATORY)

---

## 3.2 Core Principles

1. Composer-based (PSR-4)
2. Modular structure (App-based)
3. REST-driven frontend
4. Stateless backend
5. Instance-based frontend

---

# 4. 📁 PROJECT STRUCTURE

```id="v4r3c9"
plugin-name/
│
├── plugin-main-file.php
├── composer.json
├── uninstall.php
├── vendor/
│
├── src/
│   ├── Main.php
│   ├── App/
│   │   ├── ProductGridType/
│   │   ├── ProductListType/
│   │   ├── Wishlist/
│   │   ├── API/
│   │   ├── Query/
│   │   ├── Assets/
```

---

# 5. ⚙️ BOOTSTRAP FLOW

```id="p4p67q"
plugin-main-file.php
    ↓
Main.php
    ↓
Registers:
    - Elementor Widgets
    - REST API
    - Assets
```

---

# 6. 🧩 ELEMENTOR WIDGET RULES

---

## 6.1 Widget Responsibilities

The widget MUST:

- Output container HTML only
- Pass settings via JSON
- NOT query products

---

## 6.2 Output Format (STRICT)

```html id="3kql8z"
<div class="pw-widget" data-id="123" data-settings="{}"></div>
```

---

# 7. 🧠 FRONTEND ENGINE

---

## 7.1 Widget Instance System

Each widget becomes:

```js id="6mfg89"
new ProductWidget(element);
```

---

## 7.2 Internal State

```js id="r1vvtu"
{
  page: 1,
  search: '',
  category: null,
  sort: 'default'
}
```

---

## 7.3 Behavior Flow

```id="l8r7hj"
User action
 → Update state
 → API call
 → Response
 → UI update
```

---

## 7.4 Debounce Rule

- 300ms delay
- Cancel previous request

---

# 8. 🌐 REST API SPEC

---

## Endpoint

```id="x5bzws"
/wp-json/pw/v1/products
```

---

## Request

```json id="v0q2n8"
{
  "page": 1,
  "per_page": 10,
  "search": "",
  "category": "",
  "sort": "default"
}
```

---

## Response

```json id="s4hj6v"
{
  "success": true,
  "data": {
    "products": [],
    "pagination": {
      "current": 1,
      "total": 5
    }
  }
}
```

---

# 9. 🧠 QUERY ENGINE

---

## Responsibilities

- Build WP_Query
- Apply filters
- Apply sorting

---

## Sorting Mapping

```id="7v6c1h"
price_low → meta_key=_price ASC
price_high → meta_key=_price DESC
alphabet → title
```

---

# 10. ❤️ WISHLIST SYSTEM

---

## DB Table

```id="6srdtb"
wp_pw_wishlist
```

---

## Columns

- id
- user_id
- product_id
- created_at

---

## Behavior

- Add → insert
- Remove → delete
- Check → query

---

## Guest Mode

- localStorage
- Sync on login

---

# 11. ⚡ MULTI-WIDGET ISOLATION

---

## MUST FOLLOW

- No global variables
- No shared state
- Unique ID per widget
- Scoped DOM updates

---

# 12. ⚙️ PERFORMANCE RULES

---

- REST API only
- Debounce search
- Cache queries
- Lazy load images

---

# 13. 🔐 SECURITY

---

- Sanitize input
- Escape output
- Use nonces
- Validate REST permissions

---

# 14. 🧹 UNINSTALL

---

- Drop wishlist table
- Clean data

---

# 15. 📱 MOBILE

---

- Responsive layout
- Touch-friendly filters

---

# 16. 🚫 FAILURE CONDITIONS

Plugin is WRONG if:

- Page reload happens
- Widgets conflict
- Wishlist not synced
- Filters not working via AJAX

---

# 17. 🏁 FINAL DEFINITION

This plugin is:

✅ A WooCommerce frontend interaction system
✅ A modular Elementor addon
✅ A scalable architecture

NOT:

❌ A simple product grid

---

# ✅ FINAL NOTE

This document now defines:

- WHAT the plugin does (behavior)
- HOW it behaves (UX rules)
- HOW to build it (architecture)
- HOW to structure it (code rules)

---

Good—this is now moving into **product-grade UX specification**. I’ll integrate your new requirements directly into your documentation so it stays **consistent, executable, and unambiguous**.

I’ll **only extend and refine**, not rewrite everything again.

---

# 📘 WooCommerce Elementor Addon

## **Functional + Technical Specification (Updated with UX Layer & Commerce Logic)**

---

# 🔄 NEW SECTION ADDED: UX & COMMERCE BEHAVIOR LAYER

This section extends previous specification and is **mandatory implementation layer**.

---

# 18. 🍞 TOAST NOTIFICATION SYSTEM (GLOBAL UX FEEDBACK)

---

## 18.1 Purpose

Provide **instant visual feedback** for user actions:

- Add to cart
- Wishlist actions
- Errors/failures

---

## 18.2 Behavior Rules

### 1. Add to Cart Toast

| Action                     | Result                     |
| -------------------------- | -------------------------- |
| Product added successfully | ✅ “Product added to cart” |
| Failed request             | ❌ “Failed to add product” |

---

### 2. Wishlist Toast

| Action                | Result                      |
| --------------------- | --------------------------- |
| Added to wishlist     | ❤️ “Added to wishlist”      |
| Removed from wishlist | 💔 “Removed from wishlist”  |
| Failed                | ❌ “Wishlist action failed” |

---

## 18.3 Technical Rules

- Global singleton (only one container)
- Auto-dismiss: 2.5 seconds
- Non-blocking UI
- Must not depend on any external library

---

## 18.4 Trigger Points

- After successful API response
- After failed API response
- After global wishlist sync event

---

# 19. 👁️ QUICK VIEW SYSTEM

---

## 19.1 Purpose

Allow users to preview product details **without leaving the page**

---

## 19.2 Visibility Rules

| Product Type                         | Quick View |
| ------------------------------------ | ---------- |
| Simple product (1 variant)           | ✅ SHOW    |
| Variable product (multiple variants) | ❌ HIDE    |

---

## 19.3 Behavior

### When clicked:

- Open modal (AJAX loaded)
- Display:
  - Product image
  - Title
  - Price
  - Add to cart button

---

## 19.4 Modal Rules

- Must be global (not per widget)
- Close on:
  - Overlay click
  - Close button

- No page reload

---

---

# 20. 🛒 ADD TO CART LOGIC (CRITICAL COMMERCE RULE)

---

## 20.1 Product Type Handling

### Case 1: Simple Product (Single Variant)

👉 Behavior:

- Show **“Add to Cart” button**
- Clicking:
  - Sends AJAX request
  - Adds product to cart
  - Shows toast

---

### Case 2: Variable Product (Multiple Variants)

👉 Behavior:

- Replace button with:

```
View Details
```

- Clicking:
  - Redirect to single product page

---

## 20.2 Button Logic (STRICT)

| Product Type | Button       | Action   |
| ------------ | ------------ | -------- |
| Simple       | Add to Cart  | AJAX     |
| Variable     | View Details | Redirect |

---

---

# 21. 🔍 QUICK VIEW VS VIEW DETAILS RELATION

---

## Rules:

- If product has multiple variants:
  - ❌ DO NOT show Quick View
  - ✅ Only show “View Details”

- If product has single variant:
  - ✅ Show Quick View
  - ✅ Show Add to Cart

---

---

# 22. 🧩 PRODUCT CARD FINAL STRUCTURE (UPDATED)

---

## 22.1 Grid Card (FINAL)

```html
<div class="pw-product">
  <div class="pw-thumb">
    <img src="..." />

    <button class="pw-wishlist"></button>

    <!-- Conditional -->
    <button class="pw-quickview">Quick View</button>
  </div>

  <div class="pw-content">
    <h3>Product</h3>
    <span>$20</span>

    <!-- Conditional -->
    <button class="pw-add-cart">Add to Cart</button>

    <!-- OR -->
    <a class="pw-view-details">View Details</a>
  </div>
</div>
```

---

# 23. 🧠 API EXTENSION REQUIRED

---

## Product API MUST return:

```json
{
  "id": 1,
  "title": "Product",
  "price": "20",
  "image": "...",
  "permalink": "...",
  "wishlist": true,
  "type": "simple | variable"
}
```

---

## Backend Logic

```php
$product = wc_get_product($post->ID);

$type = $product->is_type('variable') ? 'variable' : 'simple';
```

---

---

# 24. ⚡ FRONTEND LOGIC EXTENSION

---

## Rendering Rules

```js
if (p.type === "simple") {
  showAddToCart();
  showQuickView();
} else {
  showViewDetails();
  hideQuickView();
}
```

---

---

# 25. 🛒 AJAX ADD TO CART ENDPOINT

---

## Endpoint

```
/wp-json/pw/v1/cart/add
```

---

## Request

```json
{
  "product_id": 1,
  "quantity": 1
}
```

---

## Response

```json
{
  "success": true,
  "message": "Added to cart"
}
```

---

---

# 26. ⚠️ FAILURE HANDLING (MANDATORY)

---

Every async action MUST:

```js
try {
  // API call
} catch {
  PWToast.show("Something went wrong", "error");
}
```

---

---

# 27. 🔄 GLOBAL EVENT SYSTEM (UPDATED)

---

## Required Events

```js
pw_wishlist_updated;
pw_cart_updated;
pw_product_loaded;
```

---

## Purpose

- Sync UI across widgets
- Update buttons globally
- Trigger toasts once

---

---

# 28. 📱 UX REQUIREMENTS (UPDATED)

---

- Toast must be visible on mobile
- Modal must be responsive
- Buttons must be thumb-friendly

---

---

# 29. 🚫 FAILURE CONDITIONS (UPDATED)

Plugin is INVALID if:

- No toast feedback
- Add to cart not AJAX
- Variable product wrongly allows direct add
- Quick view shows for variable product
- Wishlist not synced globally

---

---

# 30. 🧠 FINAL PRODUCT DEFINITION (UPDATED)

This plugin is now:

✅ WooCommerce frontend engine
✅ Interactive product system
✅ AJAX commerce layer
✅ UX-optimized shopping interface

---

# 🔥 ADDITIONAL RECOMMENDATIONS (YOU ASKED “ANY MORE?”)

These are **high-value upgrades** 👇

---

## 1. 🔢 Cart Counter Sync

- Update cart count instantly after add

---

## 2. ⏳ Skeleton Loader

Replace:

```
Loading...
```

with animated placeholders

---

## 3. 🔄 Load More Button

Alternative to pagination

---

## 4. 🎯 URL Sync (Advanced)

```
?category=shoes&sort=price_low
```

---

## 5. ❤️ Wishlist Page (Future)

Dedicated page showing saved items

---

## 6. ⚡ Caching Layer

- Transients for queries
- Reduce DB load

---

# ✅ FINAL RESULT

Now this documentation covers:

- Behavior ✔
- Architecture ✔
- Implementation ✔
- UI ✔
- UX ✔
- Commerce logic ✔

---
