# WoodPages — Kick-Start Prompt

> **Purpose**: Use this prompt to create a brand-new FacturaScripts plugin called
> **WoodPages** in a blank repository.  The plugin serves static informational
> pages (FAQ, About Us, Contact, etc.) that share the **exact same header,
> footer, CSS theme and language-switching** as the WoodStore e-commerce plugin.
>
> Static page content will be added by hand later — the agent only creates the
> skeleton infrastructure.

---

## 1  Plugin Identity

| Field | Value |
|---|---|
| Plugin folder name | `WoodPages` |
| `facturascripts.ini` `name` | `WoodPages` |
| `facturascripts.ini` `description` | `Static informational pages (FAQ, About, Contact …) with shared header/footer` |
| `facturascripts.ini` `version` | `1.0` |
| `facturascripts.ini` `min_version` | `2025.71` |
| PHP namespace | `FacturaScripts\Plugins\WoodPages` |
| `composer.json` name | `facturascripts/woodpages` |

Create the standard FacturaScripts plugin scaffolding:

```
WoodPages/
├── Assets/
│   └── CSS/
│       └── woodpages.css          ← full copy of the grey theme (Section 6)
├── Controller/
│   ├── PageFaq.php                ← one controller per page (Section 4)
│   ├── PageAbout.php
│   └── PageContact.php
├── Lib/
│   └── LanguageTrait.php          ← language detection trait (Section 5)
├── Translation/
│   ├── es_ES.json                 ← translation keys (Section 7)
│   ├── en_EN.json
│   ├── fr_FR.json
│   └── de_DE.json
├── View/
│   ├── Header.html.twig           ← shared header partial (Section 3.1)
│   ├── Footer.html.twig           ← shared footer partial (Section 3.2)
│   ├── Hreflang.html.twig         ← SEO alternate links (Section 3.3)
│   ├── PageFaq.html.twig          ← page templates (Section 4)
│   ├── PageAbout.html.twig
│   └── PageContact.html.twig
├── Init.php                       ← empty init (Section 8)
├── composer.json
├── facturascripts.ini
├── LICENSE
└── README.md
```

---

## 2  Design Principles

1. **No dynamic data** — these pages have zero database interaction; no Models, no Tables, no XMLViews.
2. **Identical look & feel** — the header, footer, hamburger menu, language switcher and grey theme CSS must be **pixel-identical** to the WoodStore plugin so visitors perceive a single site.
3. **Multilingual** — every page must support the same four languages (es, en, fr, de) using the FacturaScripts `trans()` mechanism and a `?lang=` query parameter with cookie persistence.
4. **One controller per page** — each static page has its own controller class and Twig template. Adding a new page means copying an existing controller/template pair and changing the class name + translation keys.
5. **SEO-friendly** — each page emits `<link rel="alternate" hreflang="…">` tags for all four languages.
6. **Static content placeholder** — page templates contain an empty `<div class="container mt-4">` with a Twig comment `{# Add your static content here #}`. The owner will fill these in manually with HTML.

---

## 3  Shared Partials

### 3.1  `View/Header.html.twig`

Create this file with the exact content below. It is a fixed site header with a
logo, a language-switcher dropdown and a hamburger navigation menu. **The
navigation links point to the static pages in this plugin plus the WoodStore
shop.**

```twig
{# Fixed site header – shared across all WoodPages #}
<header id="woodstore-header">
    <div class="container d-flex align-items-center justify-content-between">
        <a href="/"><img style="width:100px;height:25px;" src="https://yevea.com/001-vectores/logo-yevea-white.svg" alt="madera olivo - logo"><sup>®</sup></a>
        <div class="d-flex align-items-center gap-2">
            {# Language switcher dropdown #}
            {% if fsc.availableLanguages is defined %}
            <div class="dropdown">
                <button class="btn btn-sm dropdown-toggle lang-switcher-btn" type="button"
                        id="lang-switcher" data-bs-toggle="dropdown" aria-expanded="false">
                    {{ fsc.currentLangLabel }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="lang-switcher">
                    {% for code, label in fsc.availableLanguages %}
                    <li>
                        <a class="dropdown-item {% if code == fsc.currentLang %}active{% endif %}"
                           href="{{ fsc.langSwitchUrl(code) }}">
                            {{ label }}
                        </a>
                    </li>
                    {% endfor %}
                </ul>
            </div>
            {% endif %}
            <button type="button" id="hamburger-toggle" class="hamburger-toggle" aria-label="Toggle menu" aria-expanded="false">&#9776;</button>
        </div>
    </div>
    <div id="hamburger-menu" class="hamburger-dropdown">
        <ul>
            <li><a href="{{ asset('StoreFront') }}">{{ trans('nav-home') }}</a></li>
            <li><a href="{{ asset('Tableros') }}">{{ trans('nav-products') }}</a></li>
            <li><a href="{{ asset('PageAbout') }}">{{ trans('nav-about') }}</a></li>
            <li><a href="{{ asset('PageFaq') }}">{{ trans('nav-faq') }}</a></li>
            <li><a href="{{ asset('PageContact') }}">{{ trans('nav-contact') }}</a></li>
        </ul>
    </div>
</header>

<script>
document.getElementById('hamburger-toggle').addEventListener('click', function () {
    var langBtn = document.getElementById('lang-switcher');
    if (langBtn) {
        var bsDropdown = bootstrap.Dropdown.getInstance(langBtn);
        if (bsDropdown) {
            bsDropdown.hide();
        }
    }
    this.classList.toggle('open');
    document.getElementById('hamburger-menu').classList.toggle('show');
    this.setAttribute('aria-expanded', this.classList.contains('open'));
});

var langSwitcher = document.getElementById('lang-switcher');
if (langSwitcher) {
    langSwitcher.addEventListener('show.bs.dropdown', function () {
        var toggle = document.getElementById('hamburger-toggle');
        var menu = document.getElementById('hamburger-menu');
        toggle.classList.remove('open');
        menu.classList.remove('show');
        toggle.setAttribute('aria-expanded', 'false');
    });
}
</script>
```

> **Important**: The navigation links use `{{ asset('StoreFront') }}` and
> `{{ asset('Tableros') }}` which resolve to the WoodStore plugin's controllers.
> This works because both plugins are installed on the same FacturaScripts
> instance.

### 3.2  `View/Footer.html.twig`

Create this file with the exact content below:

```twig
{# Site footer – shared across all WoodPages #}
<footer id="woodstore-footer">
    <div class="container">
        <div class="row g-4">
            {# Contact channels #}
            <div class="col-12 col-md-4">
                <h6 class="footer-heading">{{ trans('footer-contact') }}</h6>
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

            {# Address #}
            <div class="col-12 col-md-4">
                <h6 class="footer-heading">{{ trans('footer-address') }}</h6>
                <address class="footer-address">
                    <i class="fa-solid fa-location-dot"></i>
                    Calle Ejemplo 123<br>
                    00000 Ciudad, Provincia<br>
                    España
                </address>
            </div>

            {# Quick links #}
            <div class="col-12 col-md-4">
                <h6 class="footer-heading">{{ trans('footer-links') }}</h6>
                <ul class="footer-list">
                    <li><a href="{{ asset('Tableros') }}">{{ trans('products') }}</a></li>
                    <li><a href="{{ asset('PageAbout') }}">{{ trans('nav-about') }}</a></li>
                    <li><a href="{{ asset('PageFaq') }}">{{ trans('nav-faq') }}</a></li>
                    <li><a href="{{ asset('PageContact') }}">{{ trans('nav-contact') }}</a></li>
                </ul>
            </div>
        </div>

        <hr class="footer-divider">

        <div class="text-center small footer-copy">
            &copy; {{ "now" | date("Y") }} Yevea. {{ trans('footer-rights') }}
        </div>
    </div>
</footer>
```

### 3.3  `View/Hreflang.html.twig`

```twig
{# hreflang alternate-language links for SEO — included in public-facing templates #}
{% if fsc.availableLanguages is defined %}
{% for code, label in fsc.availableLanguages %}
<link rel="alternate" hreflang="{{ code[:2] }}" href="{{ fsc.langSwitchUrl(code) }}">
{% endfor %}
<link rel="alternate" hreflang="x-default" href="{{ fsc.langSwitchUrl('es_ES') }}">
{% endif %}
```

---

## 4  Controllers and Page Templates

Every static page follows the same pattern. The controller is minimal: no auth
required, loads the CSS, detects language, renders its template. The template
extends FacturaScripts `MenuTemplate`, hides the navbar, includes header +
hreflang + footer, and has an empty content placeholder.

### 4.1  Controller Pattern

Create one PHP controller per page. Each one is nearly identical — only the
class name and `getPageData()` values change.

**Example: `Controller/PageFaq.php`**

```php
<?php
namespace FacturaScripts\Plugins\WoodPages\Controller;

use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Plugins\WoodPages\Lib\LanguageTrait;

class PageFaq extends Controller
{
    use LanguageTrait;

    protected $requiresAuth = false;

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'woodpages';
        $pageData['title'] = 'nav-faq';
        $pageData['icon'] = 'fa-solid fa-circle-question';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function run(): void
    {
        parent::run();
        $this->detectAndSetLanguage();

        $cssPath = FS_FOLDER . '/Plugins/WoodPages/Assets/CSS/woodpages.css';
        if (file_exists($cssPath)) {
            AssetManager::addCss(FS_ROUTE . '/Plugins/WoodPages/Assets/CSS/woodpages.css');
        }

        $this->view($this->controllerName() . '.html.twig');
    }

    protected function controllerName(): string
    {
        return basename(str_replace('\\', '/', static::class));
    }
}
```

**Create these controllers** using the exact same pattern, changing only the class name and `getPageData()`:

| Controller class | `title` key | `icon` |
|---|---|---|
| `PageFaq` | `nav-faq` | `fa-solid fa-circle-question` |
| `PageAbout` | `nav-about` | `fa-solid fa-building` |
| `PageContact` | `nav-contact` | `fa-solid fa-envelope` |

### 4.2  Template Pattern

**Example: `View/PageFaq.html.twig`**

```twig
{% extends "Master/MenuTemplate.html.twig" %}

{% block navbar %}{% endblock %}

{% block body %}
{% include 'Header.html.twig' %}
{% include 'Hreflang.html.twig' %}

<div class="container mt-4">
    <h1 class="mb-4">{{ trans('nav-faq') }}</h1>
    {# Add your static FAQ content here #}
</div>

{% include 'Footer.html.twig' %}
{% endblock %}
```

Create matching templates for each page:

| Template file | `<h1>` uses key |
|---|---|
| `View/PageFaq.html.twig` | `nav-faq` |
| `View/PageAbout.html.twig` | `nav-about` |
| `View/PageContact.html.twig` | `nav-contact` |

---

## 5  Language Detection — `Lib/LanguageTrait.php`

This trait is a **standalone copy** of the WoodStore language detection system,
adapted for this plugin. It supports four languages with cookie persistence.

Create `Lib/LanguageTrait.php` with the exact content below:

```php
<?php
namespace FacturaScripts\Plugins\WoodPages\Lib;

use FacturaScripts\Core\Tools;

/**
 * Provides multilingual support for public-facing controllers.
 *
 * Detects the visitor's language from the ?lang= query parameter or a cookie,
 * persists the choice, and provides helper methods used by templates.
 */
trait LanguageTrait
{
    /** @var string Current language code (e.g. 'es_ES') */
    public $currentLang = 'es_ES';

    /** @var string Display label for the current language (e.g. 'español') */
    public $currentLangLabel = 'español';

    /**
     * Available languages: locale code => display label.
     * Locale codes match the Translation/*.json file names shipped with this plugin.
     */
    public $availableLanguages = [
        'es_ES' => 'español',
        'en_EN' => 'English',
        'fr_FR' => 'français',
        'de_DE' => 'Deutsch',
    ];

    /**
     * Detects the visitor's language and applies it to the FacturaScripts
     * translation engine.  Must be called early in run(), before any trans()
     * call or data loading that depends on translated content.
     *
     * Priority: 1) ?lang= query parameter  2) cookie  3) fallback (es_ES).
     */
    protected function detectAndSetLanguage(): void
    {
        $validLangs = array_keys($this->availableLanguages);
        $langCode = null;

        // 1. Explicit ?lang= query parameter (language switcher click)
        $langParam = $this->request()->query->get('lang', '');
        if (in_array($langParam, $validLangs, true)) {
            $langCode = $langParam;
        }

        // 2. Persisted cookie from a previous visit
        // NOTE: uses the same cookie name as WoodStore so language choice
        // is shared across both plugins seamlessly.
        if ($langCode === null && isset($_COOKIE['woodstore_lang'])) {
            $cookieLang = $_COOKIE['woodstore_lang'];
            if (in_array($cookieLang, $validLangs, true)) {
                $langCode = $cookieLang;
            }
        }

        // 3. Fallback to Spanish
        if ($langCode === null) {
            $langCode = 'es_ES';
        }

        // Persist the choice in a cookie (1 year, functional cookie — no consent needed)
        if (!headers_sent()) {
            setcookie('woodstore_lang', $langCode, [
                'expires' => time() + 365 * 24 * 3600,
                'path' => '/',
                'samesite' => 'Lax',
                'secure' => !empty($_SERVER['HTTPS']),
                'httponly' => false,
            ]);
        }

        // Apply to the FacturaScripts translation engine
        $translator = Tools::lang();
        if (method_exists($translator, 'setLang')) {
            $translator->setLang($langCode);
        } elseif (method_exists($translator, 'setDefaultLang')) {
            $translator->setDefaultLang($langCode);
        }

        $this->currentLang = $langCode;
        $this->currentLangLabel = $this->availableLanguages[$langCode]
            ?? strtoupper(substr($langCode, 0, 2));
    }

    /**
     * Returns a URL to the current page in a different language.
     * Adds/replaces the ?lang= parameter while preserving other query params.
     */
    public function langSwitchUrl(string $langCode): string
    {
        $query = $this->request()->query->all();
        $query['lang'] = $langCode;

        $controller = method_exists($this, 'controllerName')
            ? $this->controllerName()
            : basename(str_replace('\\', '/', static::class));

        return $controller . '?' . http_build_query($query);
    }
}
```

> **Key detail**: The cookie name is `woodstore_lang` (same as WoodStore) so
> that when a visitor picks a language on the shop, the static pages inherit it
> and vice versa.

---

## 6  CSS Theme — `Assets/CSS/woodpages.css`

Create this file as an **exact copy** of the WoodStore grey theme. This ensures
both plugins render identically. The full content:

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

/* ── Primary buttons / outlines ─────────────────────────────── */
.btn-primary {
    background-color: #495057;
    border-color: #495057;
    color: #fff;
}
.btn-primary:hover,
.btn-primary:focus,
.btn-primary:active,
.btn-primary.active {
    background-color: #343a40;
    border-color: #343a40;
    color: #fff;
}
.btn-primary:disabled {
    background-color: #adb5bd;
    border-color: #adb5bd;
    color: #fff;
}

.btn-outline-primary {
    color: #495057;
    border-color: #495057;
}
.btn-outline-primary:hover,
.btn-outline-primary:focus,
.btn-outline-primary:active,
.btn-outline-primary.active {
    background-color: #495057;
    border-color: #495057;
    color: #fff;
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

> The CSS file intentionally keeps the same `#woodstore-header` and
> `#woodstore-footer` selectors so the HTML partials work unchanged.

---

## 7  Translation Files

Each JSON file only needs the keys used by the header, footer, navigation and
page titles. Product/category keys from WoodStore are **not** duplicated here.

### 7.1  `Translation/es_ES.json`

```json
{
    "nav-home": "Inicio",
    "nav-products": "Productos",
    "nav-about": "Sobre Nosotros",
    "nav-faq": "Preguntas Frecuentes",
    "nav-contact": "Contacto",
    "products": "Productos",
    "footer-contact": "Contacto",
    "footer-address": "Nuestra Dirección",
    "footer-links": "Enlaces Rápidos",
    "footer-rights": "Todos los derechos reservados."
}
```

### 7.2  `Translation/en_EN.json`

```json
{
    "nav-home": "Home",
    "nav-products": "Products",
    "nav-about": "About Us",
    "nav-faq": "FAQ",
    "nav-contact": "Contact",
    "products": "Products",
    "footer-contact": "Contact Us",
    "footer-address": "Our Address",
    "footer-links": "Quick Links",
    "footer-rights": "All rights reserved."
}
```

### 7.3  `Translation/fr_FR.json`

```json
{
    "nav-home": "Accueil",
    "nav-products": "Produits",
    "nav-about": "À Propos",
    "nav-faq": "FAQ",
    "nav-contact": "Contact",
    "products": "Produits",
    "footer-contact": "Nous Contacter",
    "footer-address": "Notre Adresse",
    "footer-links": "Liens Rapides",
    "footer-rights": "Tous droits réservés."
}
```

### 7.4  `Translation/de_DE.json`

```json
{
    "nav-home": "Startseite",
    "nav-products": "Produkte",
    "nav-about": "Über Uns",
    "nav-faq": "FAQ",
    "nav-contact": "Kontakt",
    "products": "Produkte",
    "footer-contact": "Kontakt",
    "footer-address": "Unsere Adresse",
    "footer-links": "Schnellzugriff",
    "footer-rights": "Alle Rechte vorbehalten."
}
```

---

## 8  `Init.php`

The plugin has no database tables, no migrations and no extensions. Create a
minimal Init class:

```php
<?php
namespace FacturaScripts\Plugins\WoodPages;

use FacturaScripts\Core\Template\InitClass;

class Init extends InitClass
{
    public function init(): void
    {
    }

    public function update(): void
    {
    }

    public function uninstall(): void
    {
    }
}
```

---

## 9  `composer.json`

```json
{
    "name": "facturascripts/woodpages",
    "description": "Static informational pages (FAQ, About, Contact) with shared header/footer for FacturaScripts",
    "type": "facturascripts-plugin",
    "license": "LGPL-3.0-or-later",
    "require": {
        "php": ">=7.4"
    },
    "autoload": {
        "psr-4": {
            "FacturaScripts\\Plugins\\WoodPages\\": ""
        }
    }
}
```

---

## 10  `facturascripts.ini`

```ini
name = 'WoodPages'
description = 'Static informational pages (FAQ, About, Contact) with shared header/footer'
version = 1.0
min_version = 2025.71
```

---

## 11  How to Add a New Page

To add a new static page (e.g. "Privacy Policy"):

1. **Create controller** `Controller/PagePrivacy.php` — copy `PageFaq.php`,
   change class name to `PagePrivacy`, set `title` to `nav-privacy`.
2. **Create template** `View/PagePrivacy.html.twig` — copy `PageFaq.html.twig`,
   change the `<h1>` key to `nav-privacy`.
3. **Add translation keys** — add `"nav-privacy": "…"` to all four JSON files.
4. **Add navigation links** — add a `<li>` to `Header.html.twig` hamburger
   menu and optionally to `Footer.html.twig` quick links.
5. **Fill in content** — replace the `{# Add your static content here #}`
   comment with your HTML.

---

## 12  Keeping Header/Footer in Sync with WoodStore

Both plugins have their own `Header.html.twig`, `Footer.html.twig` and CSS.
When you change the header or footer in one, update the other. The key elements
that must stay in sync:

- **CSS selectors** — `#woodstore-header`, `#woodstore-footer`, `.hamburger-*`,
  `.lang-switcher-btn`, `.footer-*`.
- **Hamburger JS** — identical toggle + language-dropdown coordination.
- **Language cookie name** — `woodstore_lang` in both plugins.
- **Available languages map** — identical in both `LanguageTrait.php` files.

> **Future improvement**: If the maintenance burden grows, extract the shared
> header/footer/CSS/LanguageTrait into a third "WoodTheme" library plugin that
> both WoodStore and WoodPages depend on.

---

## 13  Verification Checklist

After the agent creates all files, verify:

- [ ] `php -l Controller/PageFaq.php` (and all controllers) — no syntax errors
- [ ] `php -l Controller/PageAbout.php` — no syntax errors
- [ ] `php -l Controller/PageContact.php` — no syntax errors
- [ ] `php -l Lib/LanguageTrait.php` — no syntax errors
- [ ] `php -l Init.php` — no syntax errors
- [ ] All four JSON files are valid JSON (`python3 -m json.tool < Translation/es_ES.json`)
- [ ] Every `trans()` key used in Twig templates exists in all four JSON files
- [ ] `facturascripts.ini` name matches folder name and namespace
- [ ] Controllers extend `FacturaScripts\Core\Template\Controller`
- [ ] Controllers use `protected $requiresAuth = false`
- [ ] Templates extend `Master/MenuTemplate.html.twig`
- [ ] Templates have `{% block navbar %}{% endblock %}` (hides FS admin nav)
- [ ] Each template includes `Header.html.twig`, `Hreflang.html.twig`, `Footer.html.twig`
- [ ] CSS file contains both header and footer styles with matching selectors
- [ ] Language cookie name is `woodstore_lang` (shared with WoodStore)

---

## 14  What the Agent Should NOT Do

- Do **not** create any database tables, models, or XML views.
- Do **not** add product/category translation keys — those belong in WoodStore.
- Do **not** fill in page content — just leave the placeholder comments.
- Do **not** create a settings page — there are no settings.
- Do **not** duplicate the full WoodStore CSS for buttons, badges, alerts, cards,
  etc. — only include the header, footer, body base, link, shadow and responsive
  sections that are needed for static pages.
- Do **not** add JavaScript beyond the hamburger/language-switcher toggle already
  in the header partial.
