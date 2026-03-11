# Making the Store Truly Multilingual

## Current State

The plugin already has solid **UI translation** infrastructure:

- **4 JSON translation files** (`Translation/en_EN.json`, `es_ES.json`, `fr_FR.json`, `de_DE.json`) with ~139 keys each covering all UI labels, buttons, messages, and status terms.
- **100% of UI-facing strings** in Twig templates use `{{ trans('key') }}` — no hardcoded UI text.
- **XML admin views** use translatable column names resolved automatically by FacturaScripts.
- **FacturaScripts Core** handles language selection based on the logged-in user's preference or server defaults.

However, the store is **not truly multilingual** because product and category **content** comes directly from the database in a single language (Spanish). The following sections describe what is missing and the possible approaches to close each gap.

---

## Gap 1 — Product Content (Names and Descriptions)

**Problem:** `producto.descripcion` (product name) and `producto.observaciones` (product description) are single-language fields in the FacturaScripts `productos` table. They are displayed as-is in `StoreFront.html.twig`, `ProductoDetalle.html.twig`, and `Tableros.html.twig`.

### Option A — Translation Table (recommended for dynamic catalogs)

Create a `productos_traducciones` table:

```
idproducto  | langcode | nombre                   | descripcion
----------- | -------- | ------------------------ | ----------------------------------
1           | es_ES    | Mesa de olivo            | Mesa rústica de madera de olivo
1           | en_EN    | Olive Wood Table         | Rustic olive wood table
1           | fr_FR    | Table en bois d'olivier  | Table rustique en bois d'olivier
1           | de_DE    | Olivenholztisch          | Rustikaler Olivenholztisch
```

In the controllers (`StoreFront.php`, `ProductoDetalle.php`), after loading each product, look up the user's current language and replace `$p->descripcion` / `$p->observaciones` with the translated values when available, falling back to the original Spanish.

**Pros:** Works for any number of products, content is managed per-product in the admin panel, fully dynamic.
**Cons:** Requires a new DB table, admin UI for entering translations, and lookup logic in every controller that displays products.

### Option B — Translation Keys in JSON files (suitable for small, fixed catalogs)

For stores with a small, stable catalog, each product can have a translation key prefix. For example, a product with `referencia = OLV-TABLE-01` would use keys `product-OLV-TABLE-01-name` and `product-OLV-TABLE-01-desc` in the JSON files:

```json
{
  "product-OLV-TABLE-01-name": "Olive Wood Table",
  "product-OLV-TABLE-01-desc": "Rustic olive wood table"
}
```

The controller would call `trans('product-' . $p->referencia . '-name')` instead of `$p->descripcion`.

**Pros:** No database changes, leverages existing FacturaScripts translation infrastructure, simple to implement.
**Cons:** Does not scale for large or frequently changing catalogs; every new product requires updating all JSON files.

### Option C — AI-Powered On-the-fly Translation

Use an external translation API (DeepL, Google Translate, LibreTranslate) to translate product text at runtime, with results cached in a simple key-value table.

**Pros:** Zero manual translation effort; works for any catalog size.
**Cons:** Depends on an external service, translation quality varies, adds latency (mitigated by caching), ongoing API costs.

---

## Gap 2 — Category Content (Names, Intro, Outro)

**Problem:** The `familias` table stores `descripcion`, `category_intro`, and `category_outro` as single-language fields. These are displayed directly in `Tableros.html.twig` (lines 64, 72, 181).

### Possible Approaches

The same three options from Gap 1 apply:

- **Translation table:** A `familias_traducciones` table with `codfamilia`, `langcode`, `descripcion`, `category_intro`, `category_outro`.
- **Translation keys:** Add keys like `family-TABLEROS-name`, `family-TABLEROS-intro`, `family-TABLEROS-outro` to the JSON files. The plugin already has some category description keys (`olive-wood-desc`, `wood-planks-desc`, etc.) in the translation files — they just are not currently used in the templates.
- **API translation:** Same as above, but note that `category_intro` and `category_outro` contain HTML, so the translation service must preserve markup.

The translation-key approach is particularly viable here because the number of categories is typically small and stable.

---

## Gap 3 — Public Visitor Language Selection

**Problem:** Public visitors (`$requiresAuth = false`) have no way to choose their language. FacturaScripts Core selects the language from the logged-in user's profile or the server default — anonymous visitors are stuck with whatever the server default is.

### Option A — URL-Based Language Prefix

Add a language parameter to the URL: `/en/StoreFront`, `/fr/Tableros`, `/de/ProductoDetalle/olive-wood-table`.

The `StoreFront` controller (and its subclasses) would read the prefix, store it in the session, and pass it to FacturaScripts' translation layer.

**Pros:** SEO-friendly (search engines index each language separately), bookmarkable, standard practice.
**Cons:** Requires URL routing changes, potentially a `.htaccess`/nginx rewrite rule, and updating all internal links.

### Option B — Query Parameter

Use `?lang=en_EN` on any page URL. The controller reads the parameter and sets the session language.

**Pros:** Simplest to implement, no routing changes needed.
**Cons:** Not as SEO-friendly, URLs look less clean.

### Option C — Language Switcher Widget + Cookie/Session

Add a language dropdown to `Header.html.twig` (e.g., flag icons or language codes). When clicked, it sets a cookie or session variable that the controllers read on subsequent requests to override the default language.

```twig
{# Example language switcher in Header.html.twig #}
<div class="dropdown">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
        🌐 {{ currentLang }}
    </button>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="?lang=es_ES">🇪🇸 Español</a></li>
        <li><a class="dropdown-item" href="?lang=en_EN">🇬🇧 English</a></li>
        <li><a class="dropdown-item" href="?lang=fr_FR">🇫🇷 Français</a></li>
        <li><a class="dropdown-item" href="?lang=de_DE">🇩🇪 Deutsch</a></li>
    </ul>
</div>
```

**Pros:** User-friendly, persistent across page loads, no URL changes needed.
**Cons:** Search engines see only one language unless combined with URL-based approach.

### Recommended Combination

Combine **Option A + Option C**: URL prefixes for SEO, plus a visible switcher in the header that redirects to the same page with the new language prefix.

---

## Gap 4 — SEO: Multilingual Slugs and hreflang Tags

**Problem:** `SlugTrait.php` generates slugs only from the Spanish `descripcion` field. Search engines cannot discover alternate-language versions of the same page.

### Slug Translation

Store language-specific slugs (either in the translation table from Gap 1 or as additional JSON keys):

```
es: mesa-de-olivo
en: olive-wood-table
fr: table-en-bois-d-olivier
de: olivenholztisch
```

`ProductoDetalle` would resolve the product by any of its language slugs and redirect to the canonical URL in the user's language.

### hreflang Tags

Add `<link rel="alternate" hreflang="..." href="...">` tags in the `<head>` of every public page:

```html
<link rel="alternate" hreflang="es" href="https://example.com/es/ProductoDetalle/mesa-de-olivo" />
<link rel="alternate" hreflang="en" href="https://example.com/en/ProductoDetalle/olive-wood-table" />
<link rel="alternate" hreflang="fr" href="https://example.com/fr/ProductoDetalle/table-en-bois-d-olivier" />
<link rel="alternate" hreflang="de" href="https://example.com/de/ProductoDetalle/olivenholztisch" />
<link rel="alternate" hreflang="x-default" href="https://example.com/ProductoDetalle/mesa-de-olivo" />
```

This tells Google and other search engines that the pages are translations of each other.

---

## Gap 5 — Variant Descriptions and Attribute Names

**Problem:** Variant descriptions are built from `AtributoValor` records (e.g., "Acabado Mate", "Color Natural") which are stored in a single language in the FacturaScripts `atributos_valores` table.

### Possible Approaches

- **Translation table** for `atributos_valores`: `idvalor`, `langcode`, `descripcion`.
- **Translation keys**: `attr-acabado-mate` → "Matte Finish" (EN), "Finition Mate" (FR), etc.
- Attribute names are typically a small, stable set — translation keys in JSON files are practical here.

---

## Gap 6 — Image Alt Text and Metadata

**Problem:** `ProductoImagen.descripcion_corta` and `ProductoImagen.observaciones` are single-language fields used for image `alt` attributes and carousel captions.

### Possible Approaches

- Add language-specific columns to `ProductoImagen` (e.g., `descripcion_corta_en`, `descripcion_corta_fr`) — simple but rigid.
- Use a translation table for image metadata — more flexible.
- Generate alt text from the product's translated name as a fallback (which the template already does: `img.alt` falls back to `product.name`).

---

## Gap 7 — Email and Notification Translation

**Problem:** Order confirmation emails and admin notifications may contain text in a single language.

### Possible Approach

Store the customer's preferred language (from the session/cookie at checkout time) in `ecommerce_orders.langcode`. When sending emails, use that language to select the appropriate template and translation keys.

---

## Gap 8 — Number and Currency Formatting

**Current state:** `fsc.formatMoney()` is used for price display, which respects FacturaScripts' locale settings. This is largely handled by the core and is **not a significant gap** — it mostly works already if the server locale matches the user's expectations.

For full correctness, ensure `formatMoney()` uses the visitor's locale (not just the server locale) when displaying prices on the public storefront.

---

## Implementation Priority

For the most impact with the least effort, the recommended order is:

| Priority | Gap | Effort | Impact |
|----------|-----|--------|--------|
| 1 | **Language switcher** (Gap 3, Option C) | Low | Visitors can choose their language for all UI strings |
| 2 | **Product content via translation keys** (Gap 1, Option B) | Low-Medium | Product names/descriptions appear in the correct language |
| 3 | **Category content via translation keys** (Gap 2) | Low | Category pages are fully translated |
| 4 | **hreflang tags** (Gap 4) | Low | Immediate SEO benefit |
| 5 | **Multilingual slugs** (Gap 4) | Medium | Language-specific SEO URLs |
| 6 | **Variant/attribute translation** (Gap 5) | Medium | Complete product detail translation |
| 7 | **Image metadata** (Gap 6) | Low | Accessibility and SEO |
| 8 | **Email translation** (Gap 7) | Medium | Better customer experience |

For a store with a large or frequently changing catalog, replace priority 2 with Gap 1 Option A (translation table) for long-term scalability.

---

## Summary

The plugin's UI translation layer (`trans()` + JSON files) is complete and well-implemented. The fundamental missing piece is **content translation** — product names, descriptions, category text, and variant attributes are stored in a single language in the database and displayed as-is to all visitors.

Making the store "truly multilingual" requires:

1. A mechanism for **visitors to select their language** (language switcher + session/cookie).
2. A strategy for **translating database content** (translation table or translation keys, depending on catalog size).
3. **SEO support** via hreflang tags and optionally multilingual URL slugs.

The existing translation infrastructure (JSON files, `trans()` function) provides a strong foundation — the remaining work is bridging the gap between UI translations and content translations.
