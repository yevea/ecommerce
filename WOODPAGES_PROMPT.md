# WoodPages — Kick-Start Prompt

> **Purpose**: Use this prompt to create a brand-new **plain static HTML**
> website called **WoodPages** in a blank GitHub repository. The site is hosted
> via **cPanel** (uploaded directly or deployed via Git). It serves static
> informational pages (FAQ, About Us, Contact, etc.) that share the **exact same
> header, footer and CSS theme** as the WoodStore e-commerce site.
>
> There is **no PHP framework, no database, no Twig, no FacturaScripts**. Every
> page is a plain `.html` file. Language switching is handled entirely with
> **JavaScript** and a cookie. The owner will write static content directly in
> HTML.

---

## 1  Project Identity & Hosting

| Field | Value |
|---|---|
| Repository name | `WoodPages` |
| Hosting | cPanel shared hosting (Apache) |
| Server-side tech | **None** — plain HTML + CSS + JS only |
| Domain example | `https://pages.yevea.com/` (or subdomain/subdirectory) |
| Languages | Spanish (default), English, French, German |
| Language strategy | One HTML file per page per language, JS redirect on root |

---

## 2  File Structure

```
WoodPages/
├── index.html                  ← language detector/redirector (Section 5)
├── assets/
│   ├── css/
│   │   └── woodpages.css       ← full grey theme (Section 7)
│   └── js/
│       └── woodpages.js        ← shared JS: hamburger, lang-switcher, cookie (Section 6)
├── es/
│   ├── faq.html                ← Spanish FAQ page
│   ├── about.html              ← Spanish About Us page
│   └── contact.html            ← Spanish Contact page
├── en/
│   ├── faq.html                ← English FAQ page
│   ├── about.html              ← English About Us page
│   └── contact.html            ← English Contact page
├── fr/
│   ├── faq.html                ← French FAQ page
│   ├── about.html              ← French About Us page
│   └── contact.html            ← French Contact page
├── de/
│   ├── faq.html                ← German FAQ page
│   ├── about.html              ← German About Us page
│   └── contact.html            ← German Contact page
├── .htaccess                   ← Apache rewrites (Section 10)
├── LICENSE
└── README.md
```

### Language Directory Convention

Each language has its own directory (`es/`, `en/`, `fr/`, `de/`). Inside each
directory the **same set of HTML files** exists, one per page. This means:

- `es/faq.html` — Spanish FAQ
- `en/faq.html` — English FAQ
- `fr/faq.html` — French FAQ
- `de/faq.html` — German FAQ

The root `index.html` detects the language and redirects to `/{lang}/faq.html`
(or whichever page you designate as the landing page).

---

## 3  Design Principles

1. **Zero server-side processing** — every page is a self-contained `.html`
   file. No PHP, no Node.js, no build step. Edit in any text editor, push to
   GitHub, pull on cPanel. Done.
2. **Identical look & feel** — the header, footer, hamburger menu, language
   switcher and grey theme CSS are **pixel-identical** to the WoodStore
   FacturaScripts plugin so visitors perceive a single site.
3. **Multilingual via directories** — each language is a folder (`es/`, `en/`,
   `fr/`, `de/`). Every HTML file in every folder is complete and standalone.
   No JavaScript is needed to render content — JS only handles the language
   cookie and switcher navigation.
4. **Cookie shared with WoodStore** — the cookie name is `woodstore_lang` so
   that when a visitor picks a language on the shop (FacturaScripts), the static
   pages inherit it and vice versa.
5. **SEO-friendly** — every page has `<link rel="alternate" hreflang="…">` tags
   pointing to the same page in all four languages, plus `hreflang="x-default"`
   pointing to the Spanish version.
6. **Static content placeholder** — each page template contains a
   `<div class="container mt-4">` with a comment
   `<!-- YOUR CONTENT HERE -->`. The owner fills these in by hand.

---

## 4  Complete Page Template

Every HTML page follows this exact skeleton. The agent must create one copy per
language per page, with only the **language-specific strings** changed.

### 4.1  Full Example — `es/faq.html`

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preguntas Frecuentes — Yevea</title>

    <!-- hreflang SEO tags -->
    <link rel="alternate" hreflang="es" href="/es/faq.html">
    <link rel="alternate" hreflang="en" href="/en/faq.html">
    <link rel="alternate" hreflang="fr" href="/fr/faq.html">
    <link rel="alternate" hreflang="de" href="/de/faq.html">
    <link rel="alternate" hreflang="x-default" href="/es/faq.html">

    <!-- Bootstrap 5 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet" crossorigin="anonymous">
    <!-- Font Awesome 6 (CDN) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          rel="stylesheet" crossorigin="anonymous">
    <!-- WoodPages grey theme -->
    <link href="/assets/css/woodpages.css" rel="stylesheet">
</head>
<body>

<!-- ═══════════════════════════════════════════════════════════
     HEADER — identical on every page, only lang label changes
     ═══════════════════════════════════════════════════════════ -->
<header id="woodstore-header">
    <div class="container d-flex align-items-center justify-content-between">
        <a href="/"><img style="width:100px;height:25px;"
            src="https://yevea.com/001-vectores/logo-yevea-white.svg"
            alt="madera olivo - logo"><sup>®</sup></a>
        <div class="d-flex align-items-center gap-2">
            <!-- Language switcher dropdown -->
            <div class="dropdown">
                <button class="btn btn-sm dropdown-toggle lang-switcher-btn" type="button"
                        id="lang-switcher" data-bs-toggle="dropdown" aria-expanded="false">
                    español
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="lang-switcher">
                    <li><a class="dropdown-item active" href="/es/faq.html">español</a></li>
                    <li><a class="dropdown-item" href="/en/faq.html">English</a></li>
                    <li><a class="dropdown-item" href="/fr/faq.html">français</a></li>
                    <li><a class="dropdown-item" href="/de/faq.html">Deutsch</a></li>
                </ul>
            </div>
            <button type="button" id="hamburger-toggle" class="hamburger-toggle"
                    aria-label="Toggle menu" aria-expanded="false">&#9776;</button>
        </div>
    </div>
    <div id="hamburger-menu" class="hamburger-dropdown">
        <ul>
            <li><a href="/es/about.html">Sobre Nosotros</a></li>
            <li><a href="/es/faq.html">Preguntas Frecuentes</a></li>
            <li><a href="/es/contact.html">Contacto</a></li>
        </ul>
    </div>
</header>

<!-- ═══════════════════════════════════════════════════════════
     PAGE CONTENT — owner edits this section by hand
     ═══════════════════════════════════════════════════════════ -->
<div class="container mt-4">
    <h1 class="mb-4">Preguntas Frecuentes</h1>
    <!-- YOUR CONTENT HERE -->
</div>

<!-- ═══════════════════════════════════════════════════════════
     FOOTER — identical on every page, only text changes per lang
     ═══════════════════════════════════════════════════════════ -->
<footer id="woodstore-footer">
    <div class="container">
        <div class="row g-4">
            <!-- Contact channels -->
            <div class="col-12 col-md-4">
                <h6 class="footer-heading">Contacto</h6>
                <ul class="footer-list">
                    <li>
                        <i class="fa-solid fa-phone"></i>
                        <a href="tel:+34900000000">+34 900 000 000</a>
                    </li>
                    <li>
                        <i class="fa-brands fa-whatsapp"></i>
                        <a href="https://wa.me/34600000000" target="_blank" rel="noopener">+34 600 000 000</a>
                    </li>
                    <li>
                        <i class="fa-solid fa-envelope"></i>
                        <a href="mailto:info@example.com">info@example.com</a>
                    </li>
                </ul>
            </div>

            <!-- Address -->
            <div class="col-12 col-md-4">
                <h6 class="footer-heading">Nuestra Dirección</h6>
                <address class="footer-address">
                    <i class="fa-solid fa-location-dot"></i>
                    Calle Ejemplo 123<br>
                    00000 Ciudad, Provincia<br>
                    España
                </address>
            </div>

            <!-- Quick links -->
            <div class="col-12 col-md-4">
                <h6 class="footer-heading">Enlaces Rápidos</h6>
                <ul class="footer-list">
                    <li><a href="/es/about.html">Sobre Nosotros</a></li>
                    <li><a href="/es/faq.html">Preguntas Frecuentes</a></li>
                    <li><a href="/es/contact.html">Contacto</a></li>
                </ul>
            </div>
        </div>

        <hr class="footer-divider">

        <div class="text-center small footer-copy">
            &copy; 2025 Yevea. Todos los derechos reservados.
        </div>
    </div>
</footer>

<!-- Bootstrap JS (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
<!-- WoodPages shared JS -->
<script src="/assets/js/woodpages.js"></script>
</body>
</html>
```

### 4.2  What Changes Between Languages

When creating the same page for another language (e.g. `en/faq.html`), change
**only** these elements:

| Element | `es/faq.html` | `en/faq.html` |
|---|---|---|
| `<html lang="…">` | `es` | `en` |
| `<title>` | `Preguntas Frecuentes — Yevea` | `FAQ — Yevea` |
| Lang-switcher button text | `español` | `English` |
| Lang-switcher `active` class | on `español` link | on `English` link |
| Lang-switcher `href` values | `/es/faq.html` etc. | `/en/faq.html` etc. |
| Hamburger menu link `href` | `/es/about.html` etc. | `/en/about.html` etc. |
| Hamburger menu link **text** | Spanish labels | English labels |
| `<h1>` | `Preguntas Frecuentes` | `FAQ` |
| Footer heading text | Spanish | English |
| Footer quick-link `href` | `/es/…` | `/en/…` |
| Footer quick-link **text** | Spanish labels | English labels |
| Footer copyright text | Spanish | English |

All other HTML (structure, CSS classes, IDs, scripts) remains **identical**.

### 4.3  Language-Specific Strings Reference

Use these exact strings in each language version:

**Navigation & Page Titles:**

| Key | es | en | fr | de |
|---|---|---|---|---|
| About link | Sobre Nosotros | About Us | À Propos | Über Uns |
| FAQ link | Preguntas Frecuentes | FAQ | FAQ | FAQ |
| Contact link | Contacto | Contact | Contact | Kontakt |

**Footer:**

| Key | es | en | fr | de |
|---|---|---|---|---|
| Contact heading | Contacto | Contact Us | Nous Contacter | Kontakt |
| Address heading | Nuestra Dirección | Our Address | Notre Adresse | Unsere Adresse |
| Quick links heading | Enlaces Rápidos | Quick Links | Liens Rapides | Schnellzugriff |
| Copyright | Todos los derechos reservados. | All rights reserved. | Tous droits réservés. | Alle Rechte vorbehalten. |

**Language switcher button label:**

| Lang | Label |
|---|---|
| es | español |
| en | English |
| fr | français |
| de | Deutsch |

---

## 5  Language Detection — `index.html`

The root `index.html` is a zero-content page that detects the preferred language
and redirects. It checks (in order):

1. The `woodstore_lang` cookie (shared with WoodStore shop)
2. The browser's `navigator.language`
3. Falls back to `es` (Spanish)

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Redirecting…</title>
    <script>
    (function () {
        // Map of supported lang prefixes to directory names
        var supported = { es: 'es', en: 'en', fr: 'fr', de: 'de' };
        var fallback = 'es';
        var landing = 'faq.html'; // default landing page

        // 1. Check cookie (shared with WoodStore FacturaScripts plugin)
        var lang = null;
        var cookieMatch = document.cookie.match(/(?:^|;\s*)woodstore_lang=([^;]+)/);
        if (cookieMatch) {
            // Cookie stores locale codes like "es_ES", "en_EN" — extract prefix
            var prefix = cookieMatch[1].substring(0, 2).toLowerCase();
            if (supported[prefix]) {
                lang = prefix;
            }
        }

        // 2. Check browser language
        if (!lang && navigator.language) {
            var browserPrefix = navigator.language.substring(0, 2).toLowerCase();
            if (supported[browserPrefix]) {
                lang = browserPrefix;
            }
        }

        // 3. Fallback
        if (!lang) {
            lang = fallback;
        }

        // Set/update the cookie so WoodStore also picks it up
        // Map short code back to locale code for WoodStore compatibility
        var localeMap = { es: 'es_ES', en: 'en_EN', fr: 'fr_FR', de: 'de_DE' };
        var localeCode = localeMap[lang] || 'es_ES';
        document.cookie = 'woodstore_lang=' + localeCode
            + ';path=/;max-age=31536000;SameSite=Lax'
            + (location.protocol === 'https:' ? ';Secure' : '');

        // Redirect
        window.location.replace('/' + lang + '/' + landing);
    })();
    </script>
    <noscript>
        <meta http-equiv="refresh" content="0;url=/es/faq.html">
    </noscript>
</head>
<body>
    <p>Redirecting… <a href="/es/faq.html">Click here</a> if not redirected.</p>
</body>
</html>
```

---

## 6  Shared JavaScript — `assets/js/woodpages.js`

This script runs on every page. It handles:

1. **Hamburger menu** toggle (open/close)
2. **Language dropdown / hamburger coordination** (close one when opening the other)
3. **Cookie update** on every page load (so WoodStore stays in sync)

```javascript
/**
 * WoodPages shared JavaScript.
 * Loaded at the bottom of every page after Bootstrap JS.
 */
(function () {
    'use strict';

    // ── Hamburger menu toggle ──────────────────────────────────
    var hamburgerToggle = document.getElementById('hamburger-toggle');
    var hamburgerMenu = document.getElementById('hamburger-menu');

    if (hamburgerToggle && hamburgerMenu) {
        hamburgerToggle.addEventListener('click', function () {
            // Close the language dropdown if it is open
            var langBtn = document.getElementById('lang-switcher');
            if (langBtn) {
                var bsDropdown = bootstrap.Dropdown.getInstance(langBtn);
                if (bsDropdown) {
                    bsDropdown.hide();
                }
            }
            this.classList.toggle('open');
            hamburgerMenu.classList.toggle('show');
            this.setAttribute('aria-expanded',
                this.classList.contains('open'));
        });
    }

    // When the language dropdown opens, close the hamburger menu
    var langSwitcher = document.getElementById('lang-switcher');
    if (langSwitcher) {
        langSwitcher.addEventListener('show.bs.dropdown', function () {
            if (hamburgerToggle && hamburgerMenu) {
                hamburgerToggle.classList.remove('open');
                hamburgerMenu.classList.remove('show');
                hamburgerToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // ── Cookie sync ────────────────────────────────────────────
    // Detect current language from the URL path (first path segment)
    var pathLang = window.location.pathname.split('/')[1]; // "es", "en", etc.
    var localeMap = { es: 'es_ES', en: 'en_EN', fr: 'fr_FR', de: 'de_DE' };
    if (localeMap[pathLang]) {
        document.cookie = 'woodstore_lang=' + localeMap[pathLang]
            + ';path=/;max-age=31536000;SameSite=Lax'
            + (location.protocol === 'https:' ? ';Secure' : '');
    }
})();
```

---

## 7  CSS Theme — `assets/css/woodpages.css`

Create this file as an **exact copy** of the WoodStore grey theme for the header,
footer, body base and responsive sections. This ensures both sites render
identically.

```css
/*
 * WoodPages grey theme — identical to WoodStore
 * Palette (light → dark):
 *   #f8f9fa  #e9ecef  #dee2e6  #ced4da
 *   #adb5bd  #6c757d  #495057  #343a40  #212529
 *
 * Mobile-first responsive overrides included.
 */

/* ── Fixed Header ──────────────────────────────────────────── */
#woodstore-header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 50px;
    background-color: #000;
    color: #fff;
    z-index: 1030;
    display: flex;
    align-items: center;
}
#woodstore-header .woodstore-header-inner {
    width: 100%;
    padding: 0 1rem;
}
#woodstore-header a {
    text-decoration: none;
}
#woodstore-header a:hover {
    color: #adb5bd;
}

/* ── Hamburger toggle button ────────────────────────────────── */
.hamburger-toggle {
    background: none;
    border: none;
    color: #fff;
    font-size: 1.3rem;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    line-height: 1;
}
.hamburger-toggle:hover {
    color: #dee2e6;
}

/* ── Language switcher ──────────────────────────────────────── */
.lang-switcher-btn {
    background: none;
    border: none;
    color: #dee2e6;
    font-size: 0.8rem;
    padding: 0.15rem 0.5rem;
    letter-spacing: 0.05em;
}
.lang-switcher-btn:focus {
    color: #fff;
}
.lang-switcher-btn a {
    color: #e9ecef;
}

/* ── Hamburger dropdown menu ────────────────────────────────── */
.hamburger-dropdown {
    display: none;
    position: fixed;
    top: 50px;
    right: 0;
    width: 240px;
    background-color: #212529;
    box-shadow: -2px 2px 8px rgba(0, 0, 0, 0.3);
    z-index: 1029;
}
.hamburger-dropdown.show {
    display: block;
}
.hamburger-dropdown ul {
    list-style: none;
    margin: 0;
    padding: 0.5rem 0;
}
.hamburger-dropdown li a {
    display: block;
    padding: 0.6rem 1.25rem;
    color: #dee2e6;
    text-decoration: none;
    font-size: 0.95rem;
    transition: background-color 0.15s;
}
.hamburger-dropdown li a:hover {
    background-color: #000;
    color: #fff;
}
.hamburger-divider {
    border-color: #495057;
    margin: 0.25rem 1rem;
}

/* ── Base ───────────────────────────────────────────────────── */
body {
    background-color: #f8f9fa;
    color: #212529;
    padding-top: 50px;
}

/* ── Links ──────────────────────────────────────────────────── */
a {
    color: #495057;
}
a:hover {
    color: #212529;
}

/* ── Footer ─────────────────────────────────────────────────── */
#woodstore-footer {
    background-color: #000;
    color: #dee2e6;
    padding: 2.5rem 0 1.5rem;
    margin-top: 3rem;
}
#woodstore-footer a {
    color: #dee2e6;
    text-decoration: none;
}
#woodstore-footer a:hover {
    color: #fff;
}
.footer-heading {
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.75rem;
    font-size: 0.85rem;
}
.footer-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.footer-list li {
    padding: 0.3rem 0;
    font-size: 0.9rem;
}
.footer-list li i {
    width: 1.25rem;
    text-align: center;
    margin-right: 0.4rem;
    color: #adb5bd;
}
.footer-address {
    font-style: normal;
    font-size: 0.9rem;
    line-height: 1.6;
}
.footer-address i {
    color: #adb5bd;
    margin-right: 0.4rem;
}
.footer-divider {
    border-color: #495057;
    margin: 1.5rem 0 1rem;
}
.footer-copy {
    color: #6c757d;
}

/* ── Shadow tweaks ──────────────────────────────────────────── */
.shadow-sm {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.06) !important;
}
.shadow {
    box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1) !important;
}

/* ── Mobile-first responsive ────────────────────────────────── */
@media (max-width: 575.98px) {
    .container {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
    h1 {
        font-size: 1.5rem;
    }
}
```

> The CSS keeps the same `#woodstore-header` and `#woodstore-footer` selectors
> as the WoodStore plugin so the visual result is pixel-identical.

---

## 8  Apache Configuration — `.htaccess`

This file ensures clean URLs work and prevents directory listing:

```apache
# WoodPages — Apache configuration for cPanel hosting
Options -Indexes

# Force HTTPS (uncomment if your hosting supports it)
# RewriteEngine On
# RewriteCond %{HTTPS} off
# RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Custom 404 page (optional — create /404.html if desired)
# ErrorDocument 404 /404.html
```

---

## 9  How to Add a New Page

To add a new static page (e.g. "Privacy Policy"):

1. **Create 4 HTML files** — one per language:
   - `es/privacy.html`
   - `en/privacy.html`
   - `fr/privacy.html`
   - `de/privacy.html`
2. **Copy an existing page** (e.g. `es/faq.html`) and change:
   - `<html lang="…">` attribute
   - `<title>` text
   - hreflang `href` values (`faq.html` → `privacy.html`)
   - Language switcher `href` values (`faq.html` → `privacy.html`)
   - Hamburger menu labels and links (if adding to nav)
   - `<h1>` text
   - Footer quick-link labels and links (if adding there)
3. **Update navigation** in all existing pages — add a `<li>` entry in the
   hamburger menu and optionally in the footer quick-links section of all
   existing HTML files in all 4 languages.
4. **Fill in content** — replace `<!-- YOUR CONTENT HERE -->` with your HTML.

---

## 10  Keeping Header/Footer in Sync with WoodStore

Both sites have their own header and footer HTML. When you change one, update
the other. The key elements that must stay in sync:

- **CSS selectors** — `#woodstore-header`, `#woodstore-footer`, `.hamburger-*`,
  `.lang-switcher-btn`, `.footer-*`.
- **Hamburger JS** — identical toggle + language-dropdown coordination logic.
- **Language cookie name** — `woodstore_lang` in both sites.
- **Cookie value format** — locale codes like `es_ES`, `en_EN`, `fr_FR`,
  `de_DE` (matching WoodStore's LanguageTrait).

> **Tip**: When editing contact details (phone, email, address) in the footer,
> remember to update them in **all 16 HTML files** (4 pages × 4 languages) plus
> the WoodStore `Footer.html.twig`.

---

## 11  Verification Checklist

After the agent creates all files, verify:

- [ ] Root `index.html` exists and redirects based on cookie / browser language
- [ ] All 12 page files exist: `{es,en,fr,de}/{faq,about,contact}.html`
- [ ] Every HTML file is valid HTML5 (`<!DOCTYPE html>`, `<html lang="…">`)
- [ ] Every HTML file includes Bootstrap 5 CSS + JS from CDN
- [ ] Every HTML file includes Font Awesome 6 from CDN
- [ ] Every HTML file loads `/assets/css/woodpages.css`
- [ ] Every HTML file loads `/assets/js/woodpages.js` after Bootstrap JS
- [ ] Every HTML file has correct `<link rel="alternate" hreflang="…">` for all 4 languages + x-default
- [ ] Language switcher links point to the **same page** in each language (e.g. `faq.html` → `faq.html`)
- [ ] Language switcher marks the **current language** with the `active` class
- [ ] Hamburger menu links point to pages within the **same language** directory
- [ ] Footer quick-links point to pages within the **same language** directory
- [ ] Footer copyright year is hardcoded as `2025` (update yearly or use JS)
- [ ] CSS file contains both header and footer styles with selectors matching WoodStore
- [ ] JS cookie name is `woodstore_lang` (shared with WoodStore)
- [ ] `.htaccess` exists with `Options -Indexes`
- [ ] Each content area has `<!-- YOUR CONTENT HERE -->` placeholder comment
- [ ] No PHP files, no Twig templates, no `composer.json`, no `facturascripts.ini`

---

## 12  What the Agent Should NOT Do

- Do **not** create any PHP files, server-side scripts, or build tooling.
- Do **not** use any template engine (Twig, Handlebars, etc.) — plain HTML only.
- Do **not** use any JavaScript framework (React, Vue, etc.).
- Do **not** fill in page content — just leave `<!-- YOUR CONTENT HERE -->`.
- Do **not** create a database or any data storage.
- Do **not** duplicate the full WoodStore CSS for buttons, badges, alerts, cards,
  tables, etc. — only include the header, footer, body base, link, shadow and
  responsive sections needed for static pages.
- Do **not** add JavaScript beyond the hamburger/language-switcher toggle and
  cookie sync logic.
- Do **not** inline Bootstrap or Font Awesome — use CDN links.

---

## 13  Deployment to cPanel

1. Push all files to the `WoodPages` GitHub repository.
2. On cPanel, use **Git Version Control** to clone the repo into the document
   root (e.g. `public_html/pages/` or a subdomain directory).
3. Pull updates from GitHub whenever content changes.
4. No build step, no `npm install`, no compilation — the files are ready to
   serve as-is.
