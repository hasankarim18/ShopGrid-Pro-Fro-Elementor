You have a few options depending on your setup:

---

## Option 1 — Online Tool (Easiest, no install needed)

Go to **https://javascript-minifier.com** or **https://jscompress.com**

1. Paste your `widget.js` content
2. Click Compress/Minify
3. Download the result
4. Replace your `widget.js` with it

---

## Option 2 — Terser via NPM (Best quality, industry standard)

Install once:

```bash
npm install -g terser
```

Then run in your plugin folder:

```bash
terser assets/js/widget.js --compress --mangle --output assets/js/widget.min.js
```

Then in `AssetsManager.php` change the filename:

```php
wp_enqueue_script(
    'pw-widget-script',
    PW_URL . 'assets/js/widget.min.js',
    ['jquery'],
    PW_VERSION,
    true
);
```

Keep the original `widget.js` for your own editing. Only distribute `widget.min.js`.

---

## Option 3 — UglifyJS via NPM

```bash
npm install -g uglify-js
uglifyjs assets/js/widget.js -c -m -o assets/js/widget.min.js
```

---

## What minification does

- Removes all comments and whitespace
- Renames variables to single letters (`productId` → `a`)
- Removes dead code
- Reduces file size by ~60-70%
- Makes it very hard to read but **not impossible** — nothing in JavaScript is truly unbreakable

---

## Recommendation

Use **Terser** (Option 2). It is the most modern, produces the smallest output, and handles ES6+ syntax like arrow functions and classes which your `widget.js` uses heavily. UglifyJS can struggle with modern JS syntax.
