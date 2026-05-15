/**
 * WooCommerce Elementor Addon — Frontend Engine
 * Each .pw-widget element becomes an independent ProductWidget instance.
 */

(function () {
  "use strict";

  // ─────────────────────────────────────────────────────────────────────────
  // CONFIG
  // ─────────────────────────────────────────────────────────────────────────
  const API = window.PW_CONFIG?.api_base || "/wp-json/pw/v1";
  const NONCE = window.PW_CONFIG?.nonce || "";
  const IS_LOGGED = parseInt(window.PW_CONFIG?.is_logged || "0", 10) === 1;
  const CART_URL = window.PW_CONFIG?.cart_url || "/cart";

  // ─────────────────────────────────────────────────────────────────────────
  // UTILITIES
  // ─────────────────────────────────────────────────────────────────────────
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

  function debounce(fn, delay) {
    let timer;
    return function (...args) {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  }

  // ─────────────────────────────────────────────────────────────────────────
  // TOAST SYSTEM (global singleton)
  // ─────────────────────────────────────────────────────────────────────────
  const PWToast = (() => {
    let container;

    function init() {
      if (container) return;
      container = document.createElement("div");
      container.id = "pw-toast-container";
      container.setAttribute("aria-live", "polite");
      document.body.appendChild(container);
    }

    function show(message, type = "success", duration = 2500) {
      init();
      const toast = document.createElement("div");
      toast.className = `pw-toast pw-toast--${type}`;

      const icons = { success: "✓", error: "✕", info: "ℹ", heart: "♥" };
      const icon = icons[type] || icons.info;

      toast.innerHTML = `<span class="pw-toast__icon">${icon}</span><span class="pw-toast__msg">${message}</span>`;
      container.appendChild(toast);

      // Animate in
      requestAnimationFrame(() => toast.classList.add("pw-toast--visible"));

      setTimeout(() => {
        toast.classList.remove("pw-toast--visible");
        toast.addEventListener("transitionend", () => toast.remove(), {
          once: true,
        });
      }, duration);
    }

    return { show };
  })();

  // ─────────────────────────────────────────────────────────────────────────
  // WISHLIST STORE (handles guests + logged-in users)
  // ─────────────────────────────────────────────────────────────────────────
  const PWWishlist = (() => {
    const LS_KEY = "pw_guest_wishlist";

    function getGuest() {
      try {
        return JSON.parse(localStorage.getItem(LS_KEY) || "[]").map(Number);
      } catch {
        return [];
      }
    }

    function saveGuest(ids) {
      localStorage.setItem(LS_KEY, JSON.stringify(ids));
    }

    function isWishlisted(productId) {
      if (!IS_LOGGED) {
        return getGuest().includes(Number(productId));
      }
      return false; // DB is source of truth when logged in
    }

    async function toggle(productId) {
      productId = Number(productId);

      if (!IS_LOGGED) {
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

    // Sync guest wishlist to DB after login
    async function syncGuestOnLogin() {
      if (!IS_LOGGED) return;
      const guestIds = getGuest();
      if (!guestIds.length) return;
      try {
        await apiFetch("/wishlist/sync", {
          method: "POST",
          body: JSON.stringify({ product_ids: guestIds }),
        });
        localStorage.removeItem(LS_KEY);
      } catch (e) {
        console.warn("PW: wishlist sync failed", e);
      }
    }

    return { isWishlisted, toggle, syncGuestOnLogin, getGuest };
  })();

  // ─────────────────────────────────────────────────────────────────────────
  // QUICK VIEW MODAL (global singleton)
  // ─────────────────────────────────────────────────────────────────────────
  const PWQuickView = (() => {
    let modal, overlay, inner;

    function init() {
      if (modal) return;
      modal = document.createElement("div");
      modal.id = "pw-quickview-modal";
      modal.innerHTML = `
        <div class="pw-qv-overlay"></div>
        <div class="pw-qv-box">
          <button class="pw-qv-close" aria-label="Close">×</button>
          <div class="pw-qv-inner"></div>
        </div>`;
      document.body.appendChild(modal);

      overlay = modal.querySelector(".pw-qv-overlay");
      inner = modal.querySelector(".pw-qv-inner");

      modal.querySelector(".pw-qv-close").addEventListener("click", close);
      overlay.addEventListener("click", close);

      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") close();
      });
    }

    function open(product) {
      init();
      inner.innerHTML = `
        <div class="pw-qv-product">
          <div class="pw-qv-image">
            <img src="${product.image}" alt="${escHtml(product.title)}" loading="lazy" />
          </div>
          <div class="pw-qv-details">
            <h2 class="pw-qv-title">${escHtml(product.title)}</h2>
            <div class="pw-qv-price">${product.price}</div>
            ${product.rating_count > 0 ? `<div class="pw-qv-rating">${renderStars(product.rating)} <span>(${product.rating_count})</span></div>` : ""}
            <button class="pw-qv-cart pw-btn-primary" data-product-id="${product.id}">
              Add to Cart
            </button>
          </div>
        </div>`;

      modal
        .querySelector(".pw-qv-cart")
        ?.addEventListener("click", async (e) => {
          const btn = e.currentTarget;
          btn.disabled = true;
          btn.textContent = "Adding…";
          try {
            const res = await apiFetch("/cart/add", {
              method: "POST",
              body: JSON.stringify({ product_id: product.id, quantity: 1 }),
            });
            if (res.success) {
              PWToast.show(res.message || "Added to cart!", "success");
              document.dispatchEvent(
                new CustomEvent("pw_cart_updated", {
                  detail: { cart_count: res.cart_count },
                }),
              );
              btn.textContent = "Added ✓";
            } else {
              PWToast.show(res.message || "Failed to add to cart.", "error");
              btn.disabled = false;
              btn.textContent = "Add to Cart";
            }
          } catch {
            PWToast.show("Something went wrong.", "error");
            btn.disabled = false;
            btn.textContent = "Add to Cart";
          }
        });

      modal.classList.add("pw-qv--open");
      document.body.style.overflow = "hidden";
    }

    function close() {
      if (!modal) return;
      modal.classList.remove("pw-qv--open");
      document.body.style.overflow = "";
    }

    return { open, close };
  })();

  // ─────────────────────────────────────────────────────────────────────────
  // HELPERS
  // ─────────────────────────────────────────────────────────────────────────
  function escHtml(str) {
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  }

  function renderStars(rating) {
    const r = parseFloat(rating) || 0;
    let html = "";
    for (let i = 1; i <= 5; i++) {
      html += `<span class="${i <= Math.round(r) ? "pw-star pw-star--filled" : "pw-star"}">★</span>`;
    }
    return html;
  }

  // ─────────────────────────────────────────────────────────────────────────
  // CART COUNTER SYNC
  // ─────────────────────────────────────────────────────────────────────────
  document.addEventListener("pw_cart_updated", (e) => {
    const count = e.detail?.cart_count;
    if (count === undefined) return;
    document
      .querySelectorAll(
        ".cart-count, .cart-contents-count, [data-pw-cart-count]",
      )
      .forEach((el) => {
        el.textContent = count;
      });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // PRODUCT WIDGET CLASS
  // ─────────────────────────────────────────────────────────────────────────
  class ProductWidget {
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

    // ── Build widget shell ─────────────────────────────────────────────────
    _render() {
      const s = this.settings;
      const cols = s.columns || 3;

      this.el.innerHTML = `
        ${
          s.filters || s.search
            ? `<div class="pw-toolbar">
          ${s.search ? `<div class="pw-search-wrap"><input class="pw-search" type="search" placeholder="Search products…" autocomplete="off" /></div>` : ""}
          ${
            s.filters
              ? `
          <div class="pw-filters">
            <select class="pw-sort">
              <option value="default">Default Sorting</option>
              <option value="price_low">Price: Low → High</option>
              <option value="price_high">Price: High → Low</option>
              <option value="alphabet">A → Z</option>
            </select>
            <select class="pw-cat">
              <option value="">All Categories</option>
            </select>
          </div>`
              : ""
          }
        </div>`
            : ""
        }

        <div class="pw-products pw-products--${s.layout || "grid"}" style="--pw-cols:${cols}">
          ${this._skeleton(cols * 2)}
        </div>

        ${s.pagination ? `<div class="pw-pagination"></div>` : ""}
      `;

      this._bindUI();
      this._loadCategories();
    }

    _skeleton(count) {
      return Array.from({ length: count })
        .map(
          () => `<div class="pw-product-card pw-product-card--skeleton">
          <div class="pw-skeleton-thumb"></div>
          <div class="pw-skeleton-line pw-skeleton-line--title"></div>
          <div class="pw-skeleton-line pw-skeleton-line--price"></div>
          <div class="pw-skeleton-line pw-skeleton-line--btn"></div>
        </div>`,
        )
        .join("");
    }

    // ── Bind events ────────────────────────────────────────────────────────
    _bindUI() {
      const searchInput = this.el.querySelector(".pw-search");
      const sortSelect = this.el.querySelector(".pw-sort");
      const catSelect = this.el.querySelector(".pw-cat");

      if (searchInput) {
        const debouncedSearch = debounce((val) => {
          this.state.search = val;
          this.state.page = 1;
          this._load();
        }, 300);

        searchInput.addEventListener("input", (e) => {
          debouncedSearch(e.target.value.trim());
        });
      }

      if (sortSelect) {
        // Set default
        sortSelect.value = this.state.sort;
        sortSelect.addEventListener("change", (e) => {
          this.state.sort = e.target.value;
          this.state.page = 1;
          this._load();
        });
      }

      if (catSelect) {
        catSelect.addEventListener("change", (e) => {
          this.state.category = e.target.value;
          this.state.page = 1;
          this._load();
        });
      }
    }

    _bindGlobalEvents() {
      // When wishlist changes globally, update all cards in this widget
      document.addEventListener("pw_wishlist_updated", (e) => {
        const { product_id, wishlisted } = e.detail || {};
        if (!product_id) return;
        this.el
          .querySelectorAll(`.pw-wishlist-btn[data-product-id="${product_id}"]`)
          .forEach((btn) => {
            btn.classList.toggle("pw-wishlist-btn--active", wishlisted);
            btn.setAttribute(
              "aria-label",
              wishlisted ? "Remove from wishlist" : "Add to wishlist",
            );
          });
      });
    }

    // ── Categories dropdown ────────────────────────────────────────────────
    async _loadCategories() {
      const catSelect = this.el.querySelector(".pw-cat");
      if (!catSelect) return;

      try {
        const res = await fetch(
          `${window.location.origin}/wp-json/wc/v3/products/categories?per_page=50&hide_empty=true`,
          { headers: { "X-WP-Nonce": NONCE } },
        );
        const cats = await res.json();
        if (Array.isArray(cats)) {
          cats.forEach((cat) => {
            const opt = document.createElement("option");
            opt.value = cat.slug;
            opt.textContent = cat.name;
            if (cat.slug === this.state.category) opt.selected = true;
            catSelect.appendChild(opt);
          });
        }
      } catch {
        // Fail silently — categories are non-critical
      }
    }

    // ── API Load ───────────────────────────────────────────────────────────
    async _load() {
      if (this.state.loading) {
        if (this.abortController) this.abortController.abort();
      }

      this.state.loading = true;
      this.abortController = new AbortController();

      const grid = this.el.querySelector(".pw-products");
      if (grid) {
        grid.classList.add("pw-products--loading");
      }

      const params = new URLSearchParams({
        page: this.state.page,
        per_page: this.settings.per_page || 12,
        search: this.state.search,
        category: this.state.category,
        sort: this.state.sort,
      });

      try {
        const res = await apiFetch(`/products?${params}`, {
          signal: this.abortController.signal,
        });

        if (res.success) {
          this._renderProducts(res.data.products);
          if (this.settings.pagination) {
            this._renderPagination(res.data.pagination);
          }
        } else {
          this._renderError("Failed to load products.");
        }
      } catch (err) {
        if (err.name !== "AbortError") {
          this._renderError("Connection error. Please try again.");
        }
      } finally {
        this.state.loading = false;
        if (grid) grid.classList.remove("pw-products--loading");
      }
    }

    // ── Render Products ────────────────────────────────────────────────────
    _renderProducts(products) {
      const grid = this.el.querySelector(".pw-products");
      if (!grid) return;

      if (!products || products.length === 0) {
        grid.innerHTML = `<div class="pw-no-products">
          <div class="pw-no-products__icon">🛍️</div>
          <p>No products found.</p>
        </div>`;
        return;
      }

      const isGrid = this.settings.layout !== "list";
      const guestWishlist = IS_LOGGED ? [] : PWWishlist.getGuest();

      grid.innerHTML = products
        .map((p) => {
          const wishlisted = IS_LOGGED
            ? p.wishlist
            : guestWishlist.includes(Number(p.id));

          if (isGrid) {
            return this._gridCard(p, wishlisted);
          } else {
            return this._listCard(p, wishlisted);
          }
        })
        .join("");

      this._bindProductEvents(grid);
    }

    _gridCard(p, wishlisted) {
      const showWishlist = this.settings.wishlist;
      const isSimple = p.type === "simple";
      const isExternal = p.type === "external";
      // grouped + variable both go to the product page
      const needsPage = p.type === "variable" || p.type === "grouped";

      return `
        <div class="pw-product-card pw-product-card--grid" data-id="${p.id}">
          <div class="pw-product-thumb">
            <a href="${p.permalink}" class="pw-product-image-link">
              <img src="${p.image}" alt="${escHtml(p.title)}" loading="lazy" />
            </a>

            ${
              showWishlist
                ? `
            <button class="pw-wishlist-btn ${wishlisted ? "pw-wishlist-btn--active" : ""}"
              data-product-id="${p.id}"
              aria-label="${wishlisted ? "Remove from wishlist" : "Add to wishlist"}">
              <svg class="wishlist-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
               <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
              </svg>
            </button>

            ${
              isSimple
                ? `
            <button class="pw-quickview-btn"
              data-product='${JSON.stringify(p)}'
              aria-label="Quick view ${escHtml(p.title)}">
              Quick View
            </button>`
                : ""
            }
            `
                : ""
            }

            ${!p.in_stock ? `<span class="pw-badge pw-badge--out">Out of Stock</span>` : ""}
          </div>

          <div class="pw-product-info">
            <h3 class="pw-product-title">
              <a href="${p.permalink}">${escHtml(p.title)}</a>
            </h3>
            ${p.rating_count > 0 ? `<div class="pw-rating">${renderStars(p.rating)}</div>` : ""}
            <div class="pw-product-price">${p.price}</div>

            ${
              isSimple
                ? `<button class="pw-add-to-cart pw-btn-primary"
                  data-product-id="${p.id}"
                  ${!p.in_stock ? "disabled" : ""}>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                  Add to Cart
                </button>`
                : isExternal
                  ? `<a class="pw-buy-now pw-btn-primary" href="${escHtml(p.buy_url)}" target="_blank" rel="noopener noreferrer">
                  ${escHtml(p.button_text || "Buy Now")}
                </a>`
                  : `<a class="pw-view-details pw-btn-secondary" href="${p.permalink}">
                  View Details
                </a>`
            }
          </div>
        </div>`;
    }

    _listCard(p, wishlisted) {
      const showWishlist = this.settings.wishlist;
      const isSimple = p.type === "simple";
      const isExternal = p.type === "external";

      return `
        <div class="pw-product-card pw-product-card--list" data-id="${p.id}">
          <a href="${p.permalink}" class="pw-product-image-link">
            <img src="${p.image}" alt="${escHtml(p.title)}" loading="lazy" />
          </a>
          <div class="pw-product-info">
            <h3 class="pw-product-title">
              <a href="${p.permalink}">${escHtml(p.title)}</a>
            </h3>
            ${p.rating_count > 0 ? `<div class="pw-rating">${renderStars(p.rating)}</div>` : ""}
            <div class="pw-product-price">${p.price}</div>
            <div class="pw-product-actions">
              ${
                isSimple
                  ? `<button class="pw-add-to-cart pw-btn-primary"
                    data-product-id="${p.id}"
                    ${!p.in_stock ? "disabled" : ""}>
                    Add to Cart
                  </button>`
                  : isExternal
                    ? `<a class="pw-buy-now pw-btn-primary" href="${escHtml(p.buy_url)}" target="_blank" rel="noopener noreferrer">
                    ${escHtml(p.button_text || "Buy Now")}
                  </a>`
                    : `<a class="pw-view-details pw-btn-secondary" href="${p.permalink}">View Details</a>`
              }
              ${
                showWishlist
                  ? `
              <button class="pw-wishlist-btn ${wishlisted ? "pw-wishlist-btn--active" : ""}"
                data-product-id="${p.id}"
                aria-label="${wishlisted ? "Remove from wishlist" : "Add to wishlist"}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
              </button>`
                  : ""
              }
            </div>
          </div>
        </div>`;
    }

    // ── Bind product-level events ──────────────────────────────────────────
    _bindProductEvents(container) {
      // Add to Cart
      container.querySelectorAll(".pw-add-to-cart").forEach((btn) => {
        btn.addEventListener("click", async (e) => {
          const productId = parseInt(e.currentTarget.dataset.productId, 10);
          const originalHTML = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = `<span class="pw-spinner"></span> Adding…`;

          try {
            const res = await apiFetch("/cart/add", {
              method: "POST",
              body: JSON.stringify({ product_id: productId, quantity: 1 }),
            });

            if (res.success) {
              PWToast.show(res.message || "Product added to cart!", "success");
              btn.innerHTML = "Added ✓";
              document.dispatchEvent(
                new CustomEvent("pw_cart_updated", {
                  detail: { cart_count: res.cart_count },
                }),
              );
              setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
              }, 2000);
            } else {
              PWToast.show(res.message || "Failed to add product.", "error");
              btn.innerHTML = originalHTML;
              btn.disabled = false;
            }
          } catch {
            PWToast.show("Something went wrong.", "error");
            btn.innerHTML = originalHTML;
            btn.disabled = false;
          }
        });
      });

      // Wishlist
      container.querySelectorAll(".pw-wishlist-btn").forEach((btn) => {
        btn.addEventListener("click", async (e) => {
          const productId = parseInt(e.currentTarget.dataset.productId, 10);
          btn.disabled = true;

          try {
            const res = await PWWishlist.toggle(productId);
            const wishlisted = res.wishlisted;

            // Dispatch global event for cross-widget sync
            document.dispatchEvent(
              new CustomEvent("pw_wishlist_updated", {
                detail: { product_id: productId, wishlisted },
              }),
            );

            const msg = wishlisted
              ? "Added to wishlist ♥"
              : "Removed from wishlist";
            PWToast.show(msg, wishlisted ? "heart" : "info");
          } catch {
            PWToast.show("Wishlist action failed.", "error");
          } finally {
            btn.disabled = false;
          }
        });
      });

      // Quick View
      container.querySelectorAll(".pw-quickview-btn").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          try {
            const product = JSON.parse(e.currentTarget.dataset.product);
            PWQuickView.open(product);
          } catch {
            PWToast.show("Could not open quick view.", "error");
          }
        });
      });
    }

    // ── Pagination ─────────────────────────────────────────────────────────
    _renderPagination(pagination) {
      const container = this.el.querySelector(".pw-pagination");
      if (!container) return;

      const { current, total } = pagination;

      if (total <= 1) {
        container.innerHTML = "";
        return;
      }

      let html = `<nav class="pw-pages" aria-label="Product pages">`;

      // Prev
      html += `<button class="pw-page-btn pw-page-btn--prev" data-page="${current - 1}" ${current <= 1 ? "disabled" : ""} aria-label="Previous page">‹</button>`;

      // Page numbers
      for (let i = 1; i <= total; i++) {
        if (total > 7 && i > 2 && i < total - 1 && Math.abs(i - current) > 1) {
          if (i === 3 || i === total - 2)
            html += `<span class="pw-page-ellipsis">…</span>`;
          continue;
        }
        html += `<button class="pw-page-btn ${i === current ? "pw-page-btn--active" : ""}" data-page="${i}" aria-label="Page ${i}" ${i === current ? 'aria-current="page"' : ""}>${i}</button>`;
      }

      // Next
      html += `<button class="pw-page-btn pw-page-btn--next" data-page="${current + 1}" ${current >= total ? "disabled" : ""} aria-label="Next page">›</button>`;

      html += `</nav>`;
      container.innerHTML = html;

      container
        .querySelectorAll(".pw-page-btn:not([disabled])")
        .forEach((btn) => {
          btn.addEventListener("click", (e) => {
            const page = parseInt(e.currentTarget.dataset.page, 10);
            this.state.page = page;
            this._load();
            // Scroll widget into view
            this.el.scrollIntoView({ behavior: "smooth", block: "start" });
          });
        });
    }

    _renderError(msg) {
      const grid = this.el.querySelector(".pw-products");
      if (grid) {
        grid.innerHTML = `<div class="pw-error"><p>⚠️ ${msg}</p></div>`;
      }
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // INIT: Discover all widgets on page
  // ─────────────────────────────────────────────────────────────────────────
  function initWidgets() {
    document.querySelectorAll(".pw-widget[data-settings]").forEach((el) => {
      if (!el.dataset.pwInit) {
        el.dataset.pwInit = "1";
        new ProductWidget(el);
      }
    });
  }

  // Standard page load
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initWidgets);
  } else {
    initWidgets();
  }

  // Elementor editor live preview re-init
  if (window.elementorFrontend) {
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
    window.elementorFrontend.hooks.addAction(
      "frontend/element_ready/pw_product_list.default",
      ($scope) => {
        const el = $scope[0]?.querySelector(".pw-widget");
        if (el && !el.dataset.pwInit) {
          el.dataset.pwInit = "1";
          new ProductWidget(el);
        }
      },
    );
  }

  // Sync guest wishlist after login
  PWWishlist.syncGuestOnLogin();

  // Expose for advanced users
  window.PWToast = PWToast;
  window.PWWishlist = PWWishlist;
  window.PWQuickView = PWQuickView;
})();
