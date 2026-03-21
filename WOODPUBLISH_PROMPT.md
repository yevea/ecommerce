# WoodPublish — Kick-Start Prompt

> **Use this prompt to bootstrap the WoodPublish FacturaScripts plugin in a new blank repository.**
> It contains every specification, convention, and code pattern needed to recreate the slab-publishing PWA as a standalone plugin separated from the ecommerce storefront plugin at `yevea/ecommerce`.

---

## 1. Project Overview

**WoodPublish** is a FacturaScripts plugin that provides a **Progressive Web App (PWA)** for sawmill suppliers to photograph, price, and publish wood slabs/planks directly into a FacturaScripts product catalogue.

### Target Users
- **Sawmill operators** in the field with mobile phones (primary)
- **Warehouse staff** adding inventory from tablets/desktops (secondary)

### Core Workflow
1. User opens the PWA on their phone (or installs it to home screen)
2. Takes a photo of a wood slab with the phone camera
3. Selects wood type (e.g. "Olivo", "Nogal") and slab type (e.g. "Tabla", "Bloque") from dropdowns
4. Enters dimensions: length (cm), width (cm), thickness (cm)
5. Sees auto-calculated price (price/m² × area from pricing table)
6. Taps "Publish" → creates a FacturaScripts `Producto` with the photo as `ProductoImagen`
7. If offline, the submission is queued in IndexedDB and auto-synced when connectivity returns

### Key Design Decisions
- **Offline-first**: Full offline support via Service Worker + IndexedDB queue
- **Deferred authentication**: Form is shown to all visitors; login modal appears only on submit
- **Standalone PWA**: No FacturaScripts navbar — looks like a native mobile app
- **No dependency on any other plugin** (reads/writes core FS models only)

---

## 2. Plugin Metadata

### facturascripts.ini
```ini
name = 'WoodPublish'
description = 'PWA for sawmill suppliers to photograph and publish wood slabs to the FacturaScripts product catalogue'
version = 0.1
min_version = 2025.71
```

### composer.json
```json
{
    "name": "facturascripts/woodpublish",
    "description": "PWA for publishing wood slabs to FacturaScripts product catalogue",
    "type": "facturascripts-plugin",
    "license": "LGPL-3.0-or-later",
    "require": {
        "php": ">=8.1"
    },
    "autoload": {
        "psr-4": {
            "FacturaScripts\\Plugins\\WoodPublish\\": ""
        }
    }
}
```

### Init.php
```php
<?php
namespace FacturaScripts\Plugins\WoodPublish;

use FacturaScripts\Core\Template\InitClass;

class Init extends InitClass
{
    public function init(): void
    {
        // No extensions needed — WoodPublish is self-contained
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

## 3. File Structure

```
WoodPublish/
├── Assets/
│   ├── CSS/
│   │   └── woodpublish.css           # Standalone PWA styles (no Bootstrap dependency)
│   ├── JS/
│   │   └── AddTablon.js              # PWA client logic (581 lines, see §8)
│   ├── icons/
│   │   ├── icon-192.png              # PWA home screen icon (192×192 PNG)
│   │   └── icon-512.png              # PWA splash screen icon (512×512 PNG)
│   └── service-worker.js             # Service Worker (see §7)
├── Controller/
│   ├── AddTablon.php                 # Main PWA controller (see §5)
│   ├── ListTablonPrecio.php          # Admin: price list (see §6)
│   └── EditTablonPrecio.php          # Admin: price edit (see §6)
├── Model/
│   └── TablonPrecio.php              # Pricing model (see §4)
├── Table/
│   └── tablon_precios.xml            # DB schema (see §4)
├── Extension/
│   └── Table/
│       └── productos.xml             # Adds largo, ancho, espesor columns to productos table
├── View/
│   └── AddTablon.html.twig           # PWA page template (see §9)
├── XMLView/
│   ├── ListTablonPrecio.xml          # Price list view definition
│   └── EditTablonPrecio.xml          # Price edit form definition
├── Translation/
│   ├── es_ES.json                    # Spanish (primary)
│   ├── en_EN.json                    # English
│   ├── fr_FR.json                    # French
│   └── de_DE.json                    # German
├── Init.php
├── facturascripts.ini
├── composer.json
├── LICENSE                           # LGPL v3
└── README.md
```

---

## 4. Data Model — TablonPrecio

### Database Table: `tablon_precios`

**Table/tablon_precios.xml:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<table>
    <column>
        <name>id</name>
        <type>serial</type>
        <null>NO</null>
    </column>
    <column>
        <name>tipo_madera</name>
        <type>character varying(100)</type>
        <null>NO</null>
    </column>
    <column>
        <name>tipo_tablon</name>
        <type>character varying(100)</type>
        <null>NO</null>
    </column>
    <column>
        <name>espesor</name>
        <type>double precision</type>
        <null>NO</null>
    </column>
    <column>
        <name>precio_m2</name>
        <type>double precision</type>
        <null>NO</null>
        <default>0</default>
    </column>
    <constraint>
        <name>pk_tablon_precios</name>
        <type>PRIMARY KEY (id)</type>
    </constraint>
</table>
```

### Product Table Extension: `Extension/Table/productos.xml`

This adds dimension columns to the core FacturaScripts `productos` table so each published slab stores its physical measurements:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<table>
    <column>
        <name>largo</name>
        <type>double precision</type>
        <null>YES</null>
    </column>
    <column>
        <name>ancho</name>
        <type>double precision</type>
        <null>YES</null>
    </column>
    <column>
        <name>espesor</name>
        <type>double precision</type>
        <null>YES</null>
    </column>
</table>
```

### Model Class: `Model/TablonPrecio.php`

```php
<?php
namespace FacturaScripts\Plugins\WoodPublish\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;

class TablonPrecio extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $tipo_madera;

    /** @var string */
    public $tipo_tablon;

    /** @var float */
    public $espesor;

    /** @var float */
    public $precio_m2;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'tipo_madera';
    }

    public static function tableName(): string
    {
        return 'tablon_precios';
    }

    public function clear(): void
    {
        parent::clear();
        $this->precio_m2 = 0;
    }

    public function test(): bool
    {
        $this->tipo_madera = trim($this->tipo_madera ?? '');
        $this->tipo_tablon = trim($this->tipo_tablon ?? '');

        if (empty($this->tipo_madera)) {
            return false;
        }
        if (empty($this->tipo_tablon)) {
            return false;
        }
        if ($this->espesor <= 0) {
            return false;
        }
        if ($this->precio_m2 <= 0) {
            return false;
        }

        return parent::test();
    }
}
```

---

## 5. Main Controller — AddTablon.php

This is the heart of the PWA. It serves the app shell, manifest, service worker, icons, handles authentication, form submission, and image upload.

### Key Architecture Patterns

1. **`$requiresAuth = false`** — Page is public; authentication is handled in-app
2. **Action routing via `?action=` parameter** (both GET and POST):
   - `sw` → Serve service worker JS with `Service-Worker-Allowed: /` header
   - `manifest` → Serve dynamic PWA manifest JSON
   - `icon` → Serve PNG icons (only sizes 192 and 512 allowed)
   - `login` → Handle authentication (supports AJAX via `X-Requested-With` header)
   - `logout` → Invalidate session and clear cookies
   - `add-tablon` → Create product (requires auth)
   - `get-options` → Return price table as JSON (for dynamic dropdowns)
   - *(default)* → Load price data and render `AddTablon.html.twig`
3. **Cookie pre-validation** — `clearStaleCookies()` runs before `parent::run()` to prevent FS core's "login-cookie-fail" warning when stale PWA cookies are present
4. **JSON responses** — All AJAX actions respond with `{"result": "ok"|"error"|"login-required", "message": "..."}`

### Complete Controller Code

```php
<?php
namespace FacturaScripts\Plugins\WoodPublish\Controller;

use FacturaScripts\Core\Model\Familia;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;
use FacturaScripts\Dinamic\Model\ProductoImagen;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\WoodPublish\Model\TablonPrecio;

class AddTablon extends Controller
{
    protected $requiresAuth = false;

    /** @var string */
    public $result = '';

    /** @var string */
    public $resultMessage = '';

    /** @var array */
    public $priceTable = [];

    /** @var array */
    public $woodTypes = [];

    /** @var array */
    public $slabTypes = [];

    /** @var bool */
    public $isAuthenticated = false;

    /** @var string */
    public $loginError = '';

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'warehouse';
        $pageData['title'] = 'add-tablon';
        $pageData['icon'] = 'fa-solid fa-plus-circle';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function run(): void
    {
        // Clear stale session cookies before core auth to prevent
        // "cookie de sesión no válida" warning on PWA page reload
        $this->clearStaleCookies();

        parent::run();

        $user = Session::get('user');
        if ($user !== null && $user->enabled) {
            $this->isAuthenticated = $user->admin || $user->can('AddTablon');
        }

        $action = $this->request()->request->get('action', $this->request()->query->get('action', ''));

        // Serve service worker (no auth needed — required for PWA registration)
        if ($action === 'sw') {
            $this->serveServiceWorker();
            return;
        }

        // Serve manifest with correct URLs for the current installation
        if ($action === 'manifest') {
            $this->serveManifest();
            return;
        }

        // Serve PWA icons through the controller to avoid direct file access issues
        if ($action === 'icon') {
            $this->serveIcon();
            return;
        }

        // Handle login (supports both AJAX and regular POST)
        if ($action === 'login') {
            $this->handleLogin();
            return;
        }

        // Handle logout
        if ($action === 'logout') {
            $this->handleLogout();
            return;
        }

        // Actions that require authentication
        if ($action === 'add-tablon') {
            if (!$this->isAuthenticated) {
                $this->result = 'login-required';
                $this->resultMessage = Tools::lang()->trans('login-required');
                $this->jsonResponse();
                return;
            }
            $this->addTablon();
            $this->jsonResponse();
            return;
        }

        if ($action === 'get-options') {
            $this->loadPriceData();
            $this->jsonResponse([
                'priceTable' => $this->priceTable,
                'woodTypes' => $this->woodTypes,
                'slabTypes' => $this->slabTypes,
            ]);
            return;
        }

        // Always load price data so the form is usable before login
        $this->loadPriceData();
        $this->view('AddTablon.html.twig');
    }

    // ── Dynamic Asset Serving ───────────────────────────────────────────

    private function serveManifest(): void
    {
        $route = Tools::config('route', '/');
        $manifest = [
            'name' => Tools::lang()->trans('add-tablon'),
            'short_name' => 'WoodPublish',
            'id' => $route . 'AddTablon',
            'description' => 'PWA for publishing wood slabs to the product catalogue',
            'start_url' => $route . 'AddTablon',
            'scope' => $route,
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => '#2c3e50',
            'background_color' => '#f0f2f5',
            'icons' => [
                [
                    'src' => $route . 'AddTablon?action=icon&size=192',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $route . 'AddTablon?action=icon&size=512',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $route . 'AddTablon?action=icon&size=512',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        header('Content-Type: application/manifest+json');
        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function serveServiceWorker(): void
    {
        $swPath = FS_FOLDER . '/Plugins/WoodPublish/Assets/service-worker.js';
        if (!file_exists($swPath)) {
            http_response_code(404);
            exit;
        }

        $route = Tools::config('route', '/');
        header('Content-Type: application/javascript');
        header('Service-Worker-Allowed: /');
        // Inject the base path so the SW can build correct URLs
        echo 'var BASE = ' . json_encode($route, JSON_UNESCAPED_SLASHES) . ";\n";
        readfile($swPath);
        exit;
    }

    private function serveIcon(): void
    {
        $size = $this->request()->query->get('size', '192');
        $allowed = ['192', '512'];
        if (!in_array($size, $allowed, true)) {
            http_response_code(404);
            exit;
        }

        $iconPath = FS_FOLDER . '/Plugins/WoodPublish/Assets/icons/icon-' . $size . '.png';
        if (!file_exists($iconPath)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Content-Length: ' . filesize($iconPath));
        readfile($iconPath);
        exit;
    }

    // ── Authentication ──────────────────────────────────────────────────

    private function handleLogin(): void
    {
        $nick = trim($this->request()->request->get('fsNick', ''));
        $password = $this->request()->request->get('fsPassword', '');
        $isAjax = $this->request()->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if (empty($nick) || empty($password)) {
            $this->handleLoginError(Tools::lang()->trans('login-error'), $isAjax);
            return;
        }

        $user = new User();
        if (!$user->loadFromCode($nick) || !$user->enabled || !$user->verifyPassword($password)) {
            $this->handleLoginError(Tools::lang()->trans('login-error'), $isAjax);
            return;
        }

        // Check page permission
        if (!$user->admin && !$user->can('AddTablon')) {
            $this->handleLoginError(Tools::lang()->trans('tablon-access-denied'), $isAjax);
            return;
        }

        // Generate new logkey and set cookies
        $ip = $this->request()->getClientIp() ?? '';
        $browser = $this->request()->headers->get('User-Agent', '');
        $user->newLogkey($ip, $browser);
        $user->save();

        $expire = time() + (int)Tools::config('cookies_expire', 31536000);
        $cookiePath = Tools::config('route', '/');
        $secure = $this->request()->isSecure();

        setcookie('fsNick', $user->nick, [
            'expires' => $expire,
            'path' => $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        setcookie('fsLogkey', $user->logkey, [
            'expires' => $expire,
            'path' => $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if ($isAjax) {
            $this->result = 'ok';
            $this->resultMessage = '';
            $this->jsonResponse();
            return;
        }

        header('Location: ' . $cookiePath . 'AddTablon');
        exit;
    }

    private function handleLoginError(string $message, bool $isAjax): void
    {
        if ($isAjax) {
            $this->result = 'error';
            $this->resultMessage = $message;
            $this->jsonResponse();
            return;
        }
        $this->loginError = $message;
        $this->loadPriceData();
        $this->view('AddTablon.html.twig');
    }

    private function handleLogout(): void
    {
        $cookiePath = Tools::config('route', '/');

        // Invalidate the logkey in the database
        $user = Session::get('user');
        if ($user !== null) {
            $user->logkey = Tools::randomString(99);
            $user->save();
        }

        setcookie('fsNick', '', ['expires' => time() - 3600, 'path' => $cookiePath, 'samesite' => 'Lax']);
        setcookie('fsLogkey', '', ['expires' => time() - 3600, 'path' => $cookiePath, 'samesite' => 'Lax']);

        header('Location: ' . $cookiePath . 'AddTablon');
        exit;
    }

    /**
     * Validate session cookies before core auth runs.
     * If cookies are present but the logkey no longer matches (e.g. user logged in
     * from another device), clear them so the core does not log a
     * "cookie de sesión no válida" warning on every PWA page reload.
     */
    private function clearStaleCookies(): void
    {
        $nick = $_COOKIE['fsNick'] ?? '';
        $logkey = $_COOKIE['fsLogkey'] ?? '';

        if (empty($nick)) {
            return;
        }

        // If fsLogkey is missing, the session is already broken — clear without DB lookup
        if (empty($logkey)) {
            $cookiePath = Tools::config('route', '/');
            setcookie('fsNick', '', ['expires' => time() - 3600, 'path' => $cookiePath, 'samesite' => 'Lax']);
            unset($_COOKIE['fsNick']);
            return;
        }

        $user = new User();
        if (!$user->loadFromCode($nick) || !$user->enabled || $user->logkey !== $logkey) {
            $cookiePath = Tools::config('route', '/');
            setcookie('fsNick', '', ['expires' => time() - 3600, 'path' => $cookiePath, 'samesite' => 'Lax']);
            setcookie('fsLogkey', '', ['expires' => time() - 3600, 'path' => $cookiePath, 'samesite' => 'Lax']);
            unset($_COOKIE['fsNick'], $_COOKIE['fsLogkey']);
        }
    }

    // ── Price Data ──────────────────────────────────────────────────────

    private function loadPriceData(): void
    {
        $model = new TablonPrecio();
        $rows = $model->all([], ['tipo_madera' => 'ASC', 'tipo_tablon' => 'ASC', 'espesor' => 'ASC'], 0, 0);

        $this->priceTable = [];
        $woodSet = [];
        $slabSet = [];

        foreach ($rows as $row) {
            $this->priceTable[] = [
                'tipo_madera' => $row->tipo_madera,
                'tipo_tablon' => $row->tipo_tablon,
                'espesor' => (float)$row->espesor,
                'precio_m2' => (float)$row->precio_m2,
            ];
            $woodSet[$row->tipo_madera] = true;
            $slabSet[$row->tipo_tablon] = true;
        }

        $this->woodTypes = array_keys($woodSet);
        $this->slabTypes = array_keys($slabSet);
    }

    // ── Product Creation ────────────────────────────────────────────────

    private function addTablon(): void
    {
        $req = $this->request()->request;
        $tipoMadera = trim($req->get('tipo_madera', ''));
        $tipoTablon = trim($req->get('tipo_tablon', ''));
        $largo = (float)$req->get('largo', 0);
        $ancho = (float)$req->get('ancho', 0);
        $espesor = (float)$req->get('espesor', 0);

        if (empty($tipoMadera) || empty($tipoTablon) || $largo <= 0 || $ancho <= 0 || $espesor <= 0) {
            $this->result = 'error';
            $this->resultMessage = Tools::lang()->trans('invalid-dimensions');
            return;
        }

        // Look up price from price table
        $precioM2 = $this->lookupPrice($tipoMadera, $tipoTablon, $espesor);
        if ($precioM2 === null) {
            $this->result = 'error';
            $this->resultMessage = Tools::lang()->trans('tablon-price-not-found');
            return;
        }

        // Calculate total price: price/m² × area in m²
        $areaM2 = ($largo / 100) * ($ancho / 100);
        $precio = round($precioM2 * $areaM2, 2);

        // Find the tablones family
        $codfamilia = $this->getTablonesFamily();
        if (empty($codfamilia)) {
            $this->result = 'error';
            $this->resultMessage = Tools::lang()->trans('tablon-family-not-found');
            return;
        }

        // Create the product
        $productoClass = '\\FacturaScripts\\Dinamic\\Model\\Producto';
        $producto = new $productoClass();
        $producto->descripcion = "Tablón $tipoMadera - $tipoTablon {$largo}×{$ancho}×{$espesor} cm";
        $producto->codfamilia = $codfamilia;
        $producto->precio = $precio;
        $producto->publico = true;
        $producto->largo = $largo;
        $producto->ancho = $ancho;
        $producto->espesor = $espesor;

        if (!$producto->save()) {
            $this->result = 'error';
            $this->resultMessage = Tools::lang()->trans('tablon-save-failed');
            return;
        }

        // Update variant with price and stock
        $varianteClass = '\\FacturaScripts\\Dinamic\\Model\\Variante';
        $variante = new $varianteClass();
        $varWhere = [Where::eq('idproducto', $producto->idproducto)];
        foreach ($variante->all($varWhere, [], 0, 0) as $v) {
            $v->precio = $precio;
            $v->stockfis = 1;
            $v->save();
        }
        $producto->stockfis = 1;
        $producto->save();

        // Handle image upload
        $this->saveProductImage($producto);

        $this->result = 'ok';
        $this->resultMessage = Tools::lang()->trans('tablon-added-successfully');
    }

    private function lookupPrice(string $tipoMadera, string $tipoTablon, float $espesor): ?float
    {
        // Try exact match first
        $model = new TablonPrecio();
        $where = [
            Where::eq('tipo_madera', $tipoMadera),
            Where::eq('tipo_tablon', $tipoTablon),
            Where::eq('espesor', $espesor),
        ];
        $rows = $model->all($where, [], 0, 1);
        if (!empty($rows)) {
            return (float)$rows[0]->precio_m2;
        }

        // Find the closest thickness match for the given wood/slab combination
        $where = [
            Where::eq('tipo_madera', $tipoMadera),
            Where::eq('tipo_tablon', $tipoTablon),
        ];
        $rows = $model->all($where, ['espesor' => 'ASC'], 0, 0);
        if (empty($rows)) {
            return null;
        }

        $closest = $rows[0];
        $minDiff = abs($closest->espesor - $espesor);
        foreach ($rows as $row) {
            $diff = abs($row->espesor - $espesor);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $row;
            }
        }
        return (float)$closest->precio_m2;
    }

    private function getTablonesFamily(): string
    {
        $familia = new Familia();
        $where = [Where::eq('tipofamilia', 'tablones')];
        $rows = $familia->all($where, [], 0, 1);
        if (!empty($rows)) {
            return $rows[0]->codfamilia;
        }
        return '';
    }

    // ── Image Upload ────────────────────────────────────────────────────

    private function saveProductImage($producto): bool
    {
        $uploadedFile = $_FILES['image'] ?? null;
        if (!$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Validate image type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($uploadedFile['tmp_name']);
        if (!in_array($mimeType, $allowedTypes, true)) {
            return false;
        }

        // Generate a safe filename
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $safeRef = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $producto->referencia ?? '')));
        $filename = 'tablon-' . ($safeRef ?: $producto->idproducto) . '.' . $extension;

        // Save file to MyFiles directory
        $myFilesDir = FS_FOLDER . '/MyFiles';
        if (!is_dir($myFilesDir)) {
            mkdir($myFilesDir, 0755, true);
        }

        $targetPath = $myFilesDir . '/' . $filename;

        // Handle filename collisions
        if (file_exists($targetPath)) {
            for ($i = 2; $i <= 100; $i++) {
                $targetPath = $myFilesDir . '/' . pathinfo($filename, PATHINFO_FILENAME) . '-' . $i . '.' . $extension;
                if (!file_exists($targetPath)) {
                    $filename = pathinfo($filename, PATHINFO_FILENAME) . '-' . $i . '.' . $extension;
                    break;
                }
            }
        }

        if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
            return false;
        }

        // Create AttachedFile record
        $attachedFile = new AttachedFile();
        $attachedFile->path = $filename;
        if (!$attachedFile->save()) {
            @unlink($targetPath);
            return false;
        }

        // Create AttachedFileRelation
        $fileRelation = new AttachedFileRelation();
        $fileRelation->idfile = $attachedFile->idfile;
        $fileRelation->model = 'Producto';
        $fileRelation->modelid = $producto->idproducto;
        $fileRelation->modelcode = (string)$producto->idproducto;
        $fileRelation->save();

        // Create ProductoImagen record
        $productoImagen = new ProductoImagen();
        $productoImagen->idfile = $attachedFile->idfile;
        $productoImagen->idproducto = $producto->idproducto;
        $productoImagen->orden = 1;
        $productoImagen->descripcion_corta = $producto->descripcion;
        $productoImagen->save();

        return true;
    }

    // ── JSON Response ───────────────────────────────────────────────────

    private function jsonResponse(array $extra = []): void
    {
        $data = array_merge([
            'result' => $this->result,
            'message' => $this->resultMessage,
        ], $extra);

        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
```

---

## 6. Admin Controllers

### ListTablonPrecio.php
```php
<?php
namespace FacturaScripts\Plugins\WoodPublish\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListTablonPrecio extends ListController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'warehouse';
        $pageData['title'] = 'tablon-precios';
        $pageData['icon'] = 'fa-solid fa-money-bill';
        return $pageData;
    }

    protected function createViews($viewName = 'ListTablonPrecio')
    {
        $this->addView($viewName, 'TablonPrecio', 'tablon-precios', 'fa-solid fa-money-bill')
            ->addSearchFields(['tipo_madera', 'tipo_tablon'])
            ->addOrderBy(['tipo_madera', 'tipo_tablon', 'espesor'], 'tipo-madera')
            ->addOrderBy(['precio_m2'], 'precio-m2')
            ->addOrderBy(['espesor'], 'espesor');
    }
}
```

### EditTablonPrecio.php
```php
<?php
namespace FacturaScripts\Plugins\WoodPublish\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditTablonPrecio extends EditController
{
    public function getModelClassName(): string
    {
        return 'TablonPrecio';
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'warehouse';
        $pageData['title'] = 'tablon-precio';
        $pageData['icon'] = 'fa-solid fa-money-bill';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    protected function createViews()
    {
        $this->addEditView('EditTablonPrecio', 'TablonPrecio', 'tablon-precio', 'fa-solid fa-money-bill');
    }
}
```

### XMLView/ListTablonPrecio.xml
```xml
<?xml version="1.0" encoding="UTF-8"?>
<view>
    <columns>
        <column name="id" order="100">
            <widget type="text" fieldname="id" />
        </column>
        <column name="tipo-madera" order="110">
            <widget type="text" fieldname="tipo_madera" />
        </column>
        <column name="tipo-tablon" order="120">
            <widget type="text" fieldname="tipo_tablon" />
        </column>
        <column name="espesor" order="130">
            <widget type="number" fieldname="espesor" decimal="1" />
        </column>
        <column name="precio-m2" order="140">
            <widget type="money" fieldname="precio_m2" />
        </column>
    </columns>
</view>
```

### XMLView/EditTablonPrecio.xml
```xml
<?xml version="1.0" encoding="UTF-8"?>
<view>
    <columns>
        <group name="data" numcolumns="12">
            <column name="id" numcolumns="2">
                <widget type="number" fieldname="id" readonly="true" />
            </column>
            <column name="tipo-madera" numcolumns="3">
                <widget type="text" fieldname="tipo_madera" required="true" />
            </column>
            <column name="tipo-tablon" numcolumns="3">
                <widget type="text" fieldname="tipo_tablon" required="true" />
            </column>
            <column name="espesor" numcolumns="2">
                <widget type="number" fieldname="espesor" decimal="1" required="true" />
            </column>
            <column name="precio-m2" numcolumns="2">
                <widget type="money" fieldname="precio_m2" required="true" />
            </column>
        </group>
    </columns>
</view>
```

---

## 7. Service Worker — `Assets/service-worker.js`

The `BASE` variable is injected by the PHP controller at serve time (not present in the static file).

```javascript
/**
 * Service Worker for the WoodPublish PWA.
 * Caches the complete app shell so the page loads fully offline.
 * POST submissions are handled client-side via IndexedDB queue.
 *
 * The variable BASE is injected by the PHP controller (AddTablon?action=sw)
 * so that all paths resolve correctly regardless of the FacturaScripts
 * installation directory (e.g. "/" or "/facturascripts/").
 */
var CACHE_NAME = 'woodpublish-pwa-v1';
var APP_SHELL = BASE + 'AddTablon';
var SHELL_URLS = [
    APP_SHELL,
    BASE + 'Plugins/WoodPublish/Assets/JS/AddTablon.js',
    BASE + 'AddTablon?action=icon&size=192',
    BASE + 'AddTablon?action=icon&size=512',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/webfonts/fa-solid-900.woff2'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(SHELL_URLS);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (names) {
            return Promise.all(
                names.filter(function (n) { return n !== CACHE_NAME; })
                    .map(function (n) { return caches.delete(n); })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    // Let POSTs go to network — offline queue is handled client-side
    if (event.request.method !== 'GET') {
        return;
    }

    // Navigation requests (HTML pages): network-first, fall back to cached app shell
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).then(function (response) {
                if (response.ok) {
                    var clone = response.clone();
                    caches.open(CACHE_NAME).then(function (cache) {
                        cache.put(event.request, clone);
                    });
                }
                return response;
            }).catch(function () {
                return caches.match(event.request).then(function (cached) {
                    return cached || caches.match(APP_SHELL);
                });
            })
        );
        return;
    }

    // Static assets (JS, CSS, fonts, images): cache-first, fall back to network
    event.respondWith(
        caches.match(event.request).then(function (cached) {
            if (cached) {
                return cached;
            }
            return fetch(event.request).then(function (response) {
                if (response.ok) {
                    var clone = response.clone();
                    caches.open(CACHE_NAME).then(function (cache) {
                        cache.put(event.request, clone);
                    });
                }
                return response;
            });
        })
    );
});
```

### Important: Increment `CACHE_NAME` version when changing any cached resource.

---

## 8. Client-Side JavaScript — `Assets/JS/AddTablon.js`

This is the main PWA client-side logic (581 lines). It handles:

1. **PWA Install Prompt** — Captures `beforeinstallprompt`, shows install banner, dismiss button, fallback manual instructions after 4s (iOS: Share > Add to Home Screen, Android: browser menu)
2. **IndexedDB Offline Queue** — Database `tablonPWA`, store `pendingSlabs`, auto-increment keys
3. **Photo Preview** — FileReader converts to base64 dataURL for offline storage
4. **Price Calculation** — Real-time price lookup from price table injected by Twig
5. **Form Submission** — AJAX POST with FormData, converts base64 back to Blob for file upload
6. **Offline Detection** — Shows/hides offline banner, auto-syncs on reconnect
7. **Deferred Login Modal** — Shows login form when server returns `login-required`, retries submission after successful login
8. **Sync System** — Sequential processing of queued items with progress feedback

### Complete Code

```javascript
/**
 * AddTablon PWA - Client-side logic for the WoodPublish PWA page.
 * Handles PWA install prompt, photo preview, price calculation,
 * AJAX form submission, IndexedDB offline queue, automatic sync
 * when back online, and deferred login via modal.
 */
(function () {
    'use strict';

    // ── DOM references ──────────────────────────────────────────────────
    var form = document.getElementById('tablonForm');
    var imageInput = document.getElementById('imageInput');
    var photoArea = document.getElementById('photoArea');
    var photoPreview = document.getElementById('photoPreview');
    var tipoMadera = document.getElementById('tipo_madera');
    var tipoTablon = document.getElementById('tipo_tablon');
    var largoInput = document.getElementById('largo');
    var anchoInput = document.getElementById('ancho');
    var espesorInput = document.getElementById('espesor');
    var priceAmount = document.getElementById('priceAmount');
    var priceDetail = document.getElementById('priceDetail');
    var btnPublish = document.getElementById('btnPublish');
    var alertBox = document.getElementById('alertBox');
    var offlineBanner = document.getElementById('offlineBanner');
    var pendingBadge = document.getElementById('pendingBadge');
    var btnSync = document.getElementById('btnSync');
    var installBanner = document.getElementById('installBanner');
    var btnInstall = document.getElementById('btnInstall');
    var btnDismissInstall = document.getElementById('btnDismissInstall');

    // Login modal DOM references
    var loginModal = document.getElementById('loginModal');
    var loginForm = document.getElementById('loginForm');
    var loginModalError = document.getElementById('loginModalError');
    var btnLogin = document.getElementById('btnLogin');

    // Price table is injected by the Twig template as a global variable
    var prices = window.priceTable || [];

    // Current photo as base64 dataURL (kept in memory for offline queue)
    var currentPhotoDataURL = '';

    // Pending entry waiting for login before submission
    var pendingLoginEntry = null;

    // ── PWA install prompt ──────────────────────────────────────────────
    var deferredInstallPrompt = null;
    var installTip = document.getElementById('installTip');
    var installTipText = document.getElementById('installTipText');

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredInstallPrompt = e;
        if (installBanner) {
            installBanner.style.display = 'flex';
        }
        // Hide manual tip if the native prompt is available
        if (installTip) {
            installTip.style.display = 'none';
        }
    });

    if (btnInstall) {
        btnInstall.addEventListener('click', function () {
            if (!deferredInstallPrompt) return;
            deferredInstallPrompt.prompt();
            deferredInstallPrompt.userChoice.then(function () {
                deferredInstallPrompt = null;
                if (installBanner) {
                    installBanner.style.display = 'none';
                }
            });
        });
    }

    if (btnDismissInstall) {
        btnDismissInstall.addEventListener('click', function () {
            if (installBanner) {
                installBanner.style.display = 'none';
            }
            deferredInstallPrompt = null;
        });
    }

    window.addEventListener('appinstalled', function () {
        deferredInstallPrompt = null;
        if (installBanner) {
            installBanner.style.display = 'none';
        }
        if (installTip) {
            installTip.style.display = 'none';
        }
    });

    // Show manual install instructions if beforeinstallprompt does not fire
    if (installTip && installTipText) {
        setTimeout(function () {
            if (deferredInstallPrompt) return; // Native prompt available, no need
            if (window.matchMedia('(display-mode: standalone)').matches) return; // Already installed
            if (navigator.standalone) return; // iOS standalone mode

            // Build install instructions using DOM elements (no innerHTML)
            while (installTipText.firstChild) {
                installTipText.removeChild(installTipText.firstChild);
            }

            var isIOS = /iP(hone|ad|od)/i.test(navigator.userAgent);
            var bold1 = document.createElement('b');
            bold1.textContent = 'Instalar:';
            installTipText.appendChild(bold1);
            installTipText.appendChild(document.createTextNode(' '));

            if (isIOS) {
                installTipText.appendChild(document.createTextNode('Pulsa '));
                var shareIcon = document.createElement('i');
                shareIcon.className = 'fa-solid fa-arrow-up-from-bracket';
                installTipText.appendChild(shareIcon);
                installTipText.appendChild(document.createTextNode(' Compartir y luego '));
            } else {
                installTipText.appendChild(document.createTextNode('Abre el menú del navegador '));
                var menuIcon = document.createElement('i');
                menuIcon.className = 'fa-solid fa-ellipsis-vertical';
                installTipText.appendChild(menuIcon);
                installTipText.appendChild(document.createTextNode(' y selecciona '));
            }

            var bold2 = document.createElement('b');
            bold2.textContent = 'Añadir a pantalla de inicio';
            installTipText.appendChild(bold2);

            installTip.style.display = 'flex';
        }, 4000);
    }

    // ── IndexedDB helpers ───────────────────────────────────────────────
    var DB_NAME = 'tablonPWA';
    var DB_VERSION = 1;
    var STORE_NAME = 'pendingSlabs';

    function openDB(callback) {
        var request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = function (e) {
            var db = e.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
            }
        };
        request.onsuccess = function (e) { callback(null, e.target.result); };
        request.onerror = function (e) { callback(e.target.error, null); };
    }

    function savePending(entry, callback) {
        openDB(function (err, db) {
            if (err) { callback(err); return; }
            var tx = db.transaction(STORE_NAME, 'readwrite');
            tx.objectStore(STORE_NAME).add(entry);
            tx.oncomplete = function () { callback(null); };
            tx.onerror = function (e) { callback(e.target.error); };
        });
    }

    function getAllPending(callback) {
        openDB(function (err, db) {
            if (err) { callback(err, []); return; }
            var tx = db.transaction(STORE_NAME, 'readonly');
            var req = tx.objectStore(STORE_NAME).getAll();
            req.onsuccess = function () { callback(null, req.result || []); };
            req.onerror = function (e) { callback(e.target.error, []); };
        });
    }

    function deletePending(id, callback) {
        openDB(function (err, db) {
            if (err) { callback(err); return; }
            var tx = db.transaction(STORE_NAME, 'readwrite');
            tx.objectStore(STORE_NAME).delete(id);
            tx.oncomplete = function () { callback(null); };
            tx.onerror = function (e) { callback(e.target.error); };
        });
    }

    // ── Photo preview handler ───────────────────────────────────────────
    imageInput.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;

        var reader = new FileReader();
        reader.onload = function (e) {
            currentPhotoDataURL = e.target.result;
            photoPreview.src = currentPhotoDataURL;
            photoArea.classList.add('has-photo');
        };
        reader.readAsDataURL(file);
        recalculate();
    });

    // ── Price calculation ───────────────────────────────────────────────
    [tipoMadera, tipoTablon, largoInput, anchoInput, espesorInput].forEach(function (el) {
        el.addEventListener('change', recalculate);
        el.addEventListener('input', recalculate);
    });

    function recalculate() {
        var wood = tipoMadera.value;
        var slab = tipoTablon.value;
        var largo = parseFloat(largoInput.value) || 0;
        var ancho = parseFloat(anchoInput.value) || 0;
        var espesor = parseFloat(espesorInput.value) || 0;

        if (!wood || !slab || largo <= 0 || ancho <= 0 || espesor <= 0) {
            priceAmount.textContent = '— €';
            priceDetail.textContent = '';
            btnPublish.disabled = true;
            return;
        }

        var precioM2 = lookupPrice(wood, slab, espesor);
        if (precioM2 === null) {
            priceAmount.textContent = '— €';
            priceDetail.textContent = window.TABLON_I18N.noPriceDefined;
            btnPublish.disabled = true;
            return;
        }

        var areaM2 = (largo / 100) * (ancho / 100);
        var total = precioM2 * areaM2;

        priceAmount.textContent = total.toFixed(2) + ' €';
        priceDetail.textContent = precioM2.toFixed(2) + ' €/m² × ' + areaM2.toFixed(4) + ' m²';
        btnPublish.disabled = false;
    }

    function lookupPrice(wood, slab, espesor) {
        // Exact match
        for (var i = 0; i < prices.length; i++) {
            if (prices[i].tipo_madera === wood &&
                prices[i].tipo_tablon === slab &&
                prices[i].espesor === espesor) {
                return prices[i].precio_m2;
            }
        }

        // Closest thickness match for the wood/slab combination
        var closest = null;
        var minDiff = Infinity;
        for (var j = 0; j < prices.length; j++) {
            if (prices[j].tipo_madera === wood && prices[j].tipo_tablon === slab) {
                var diff = Math.abs(prices[j].espesor - espesor);
                if (diff < minDiff) {
                    minDiff = diff;
                    closest = prices[j];
                }
            }
        }
        return closest ? closest.precio_m2 : null;
    }

    // ── Form submission ─────────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideAlert();

        var entry = collectFormData();
        if (!entry) return;

        btnPublish.disabled = true;
        btnPublish.classList.add('loading');

        if (!navigator.onLine) {
            // Offline → save to queue
            saveToQueue(entry);
            return;
        }

        // Online → try to submit
        submitEntry(entry, function (ok, message, resultCode) {
            btnPublish.classList.remove('loading');
            if (ok) {
                showAlert('success', message);
                resetForm();
            } else if (resultCode === 'login-required') {
                // Not authenticated → show login modal, then retry
                pendingLoginEntry = entry;
                showLoginModal();
                btnPublish.disabled = false;
            } else {
                // Network error during submit → save to queue
                if (message === '__network_error__') {
                    saveToQueue(entry);
                } else {
                    showAlert('error', message);
                    btnPublish.disabled = false;
                }
            }
        });
    });

    function collectFormData() {
        return {
            tipo_madera: tipoMadera.value,
            tipo_tablon: tipoTablon.value,
            largo: largoInput.value,
            ancho: anchoInput.value,
            espesor: espesorInput.value,
            imageDataURL: currentPhotoDataURL || '',
            timestamp: new Date().toISOString()
        };
    }

    function submitEntry(entry, callback) {
        var formData = new FormData();
        formData.append('action', 'add-tablon');
        formData.append('tipo_madera', entry.tipo_madera);
        formData.append('tipo_tablon', entry.tipo_tablon);
        formData.append('largo', entry.largo);
        formData.append('ancho', entry.ancho);
        formData.append('espesor', entry.espesor);

        // Convert base64 dataURL back to a Blob for file upload
        if (entry.imageDataURL) {
            var blob = dataURLtoBlob(entry.imageDataURL);
            if (blob) {
                formData.append('image', blob, 'tablon-photo.jpg');
            }
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.pathname, true);
        xhr.timeout = 30000;
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    callback(resp.result === 'ok', resp.message || 'OK', resp.result);
                } catch (ex) {
                    callback(false, window.TABLON_I18N.serverError, 'error');
                }
            } else {
                callback(false, '__network_error__', 'error');
            }
        };
        xhr.ontimeout = function () { callback(false, '__network_error__', 'error'); };
        xhr.onerror = function () { callback(false, '__network_error__', 'error'); };
        xhr.send(formData);
    }

    function saveToQueue(entry) {
        savePending(entry, function (err) {
            btnPublish.classList.remove('loading');
            if (err) {
                showAlert('error', window.TABLON_I18N.serverError);
                btnPublish.disabled = false;
            } else {
                showAlert('success', window.TABLON_I18N.savedOffline);
                resetForm();
                refreshPendingCount();
            }
        });
    }

    // ── Login modal ─────────────────────────────────────────────────────
    function showLoginModal() {
        if (!loginModal) return;
        loginModalError.style.display = 'none';
        loginModalError.textContent = '';
        loginModal.classList.add('active');
        var nickInput = document.getElementById('modalNick');
        if (nickInput) nickInput.focus();
    }

    function hideLoginModal() {
        if (!loginModal) return;
        loginModal.classList.remove('active');
        pendingLoginEntry = null;
    }

    // Close modal when clicking the overlay background
    if (loginModal) {
        loginModal.addEventListener('click', function (e) {
            if (e.target === loginModal) {
                hideLoginModal();
            }
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            loginModalError.style.display = 'none';

            var nick = document.getElementById('modalNick').value.trim();
            var password = document.getElementById('modalPassword').value;

            if (!nick || !password) return;

            btnLogin.disabled = true;
            btnLogin.classList.add('loading');

            var formData = new FormData();
            formData.append('action', 'login');
            formData.append('fsNick', nick);
            formData.append('fsPassword', password);

            function resetLoginBtn() {
                btnLogin.disabled = false;
                btnLogin.classList.remove('loading');
            }

            function showLoginError(msg) {
                resetLoginBtn();
                loginModalError.textContent = msg;
                loginModalError.style.display = 'block';
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.pathname, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 15000;
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;

                if (xhr.status === 200) {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.result === 'ok') {
                            resetLoginBtn();
                            hideLoginModal();
                            loginForm.reset();
                            if (pendingLoginEntry) {
                                var entry = pendingLoginEntry;
                                pendingLoginEntry = null;
                                btnPublish.disabled = true;
                                btnPublish.classList.add('loading');
                                submitEntry(entry, function (ok, message) {
                                    btnPublish.classList.remove('loading');
                                    if (ok) {
                                        showAlert('success', message);
                                        resetForm();
                                    } else {
                                        showAlert('error', message);
                                        btnPublish.disabled = false;
                                    }
                                });
                            }
                        } else {
                            showLoginError(resp.message || 'Error');
                        }
                    } catch (ex) {
                        showLoginError(window.TABLON_I18N.serverError);
                    }
                } else {
                    showLoginError(window.TABLON_I18N.serverError);
                }
            };
            xhr.ontimeout = function () { showLoginError(window.TABLON_I18N.serverError); };
            xhr.onerror = function () { showLoginError(window.TABLON_I18N.serverError); };
            xhr.send(formData);
        });
    }

    // ── Sync pending items ──────────────────────────────────────────────
    function syncPending() {
        if (!navigator.onLine) return;

        getAllPending(function (err, items) {
            if (err || items.length === 0) return;

            showAlert('success', window.TABLON_I18N.syncing.replace('{n}', items.length));
            if (btnSync) { btnSync.disabled = true; }

            var idx = 0;
            var ok = 0;
            var fail = 0;

            function next() {
                if (idx >= items.length) {
                    finishSync(ok, fail);
                    return;
                }
                var item = items[idx];
                idx++;
                submitEntry(item, function (success) {
                    if (success) {
                        ok++;
                        deletePending(item.id, function () { next(); });
                    } else {
                        fail++;
                        next();
                    }
                });
            }
            next();
        });
    }

    function finishSync(ok, fail) {
        if (btnSync) { btnSync.disabled = false; }
        refreshPendingCount();
        if (fail === 0 && ok > 0) {
            showAlert('success', window.TABLON_I18N.syncDone.replace('{n}', ok));
        } else if (fail > 0) {
            showAlert('error', window.TABLON_I18N.syncPartial.replace('{ok}', ok).replace('{fail}', fail));
        }
    }

    // ── Pending count badge ─────────────────────────────────────────────
    function refreshPendingCount() {
        getAllPending(function (err, items) {
            var count = (err || !items) ? 0 : items.length;
            if (pendingBadge) {
                pendingBadge.textContent = count;
                pendingBadge.style.display = count > 0 ? 'inline-flex' : 'none';
            }
            if (btnSync) {
                btnSync.style.display = count > 0 ? 'inline-flex' : 'none';
            }
        });
    }

    // ── Online / offline detection ──────────────────────────────────────
    function updateOnlineStatus() {
        if (offlineBanner) {
            offlineBanner.style.display = navigator.onLine ? 'none' : 'flex';
        }
    }

    window.addEventListener('online', function () {
        updateOnlineStatus();
        syncPending();
    });
    window.addEventListener('offline', updateOnlineStatus);

    if (btnSync) {
        btnSync.addEventListener('click', function () {
            syncPending();
        });
    }

    // ── Helpers ─────────────────────────────────────────────────────────
    function dataURLtoBlob(dataURL) {
        try {
            var parts = dataURL.split(',');
            var mime = parts[0].match(/:(.*?);/)[1];
            var b64 = atob(parts[1]);
            var arr = new Uint8Array(b64.length);
            for (var i = 0; i < b64.length; i++) {
                arr[i] = b64.charCodeAt(i);
            }
            return new Blob([arr], { type: mime });
        } catch (e) {
            return null;
        }
    }

    function resetForm() {
        form.reset();
        photoArea.classList.remove('has-photo');
        photoPreview.src = '';
        currentPhotoDataURL = '';
        priceAmount.textContent = '— €';
        priceDetail.textContent = '';
        btnPublish.disabled = true;
    }

    function showAlert(type, message) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function hideAlert() {
        alertBox.style.display = 'none';
        alertBox.className = 'alert';
    }

    // ── Init ────────────────────────────────────────────────────────────
    updateOnlineStatus();
    refreshPendingCount();
})();
```

---

## 9. View Template — `View/AddTablon.html.twig`

This is a **standalone HTML page** (no FacturaScripts navbar, no `{% extends %}`) that serves as the PWA app shell. It includes all CSS inline for offline reliability.

### Key Template Features

1. **Inline CSS** — All styles embedded in `<style>` tag (no external CSS file dependency for the PWA shell)
2. **Apple PWA meta tags** — `apple-mobile-web-app-capable`, `apple-mobile-web-app-status-bar-style`, `apple-mobile-web-app-title`
3. **Manifest link** — `<link rel="manifest" href="{{ asset('AddTablon') }}?action=manifest">`
4. **Apple touch icon** — `<link rel="apple-touch-icon" href="{{ asset('AddTablon') }}?action=icon&size=192">`
5. **Font Awesome** — Loaded from cdnjs CDN
6. **Twig variables injected as JS globals**: `priceTable` (array), `TABLON_I18N` (i18n strings object)
7. **Service worker registration** — Inline script at bottom, scope computed dynamically

### Template Structure

```
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- PWA meta tags -->
    <!-- Manifest link -->
    <!-- Apple touch icon -->
    <!-- Inline CSS (~290 lines) -->
    <!-- Font Awesome CDN -->
</head>
<body>
    <!-- Header bar (sticky, dark) -->
    <!--   Pending badge, Sync button, Logout button -->

    <!-- Install banner (native prompt) -->
    <!-- Install tip (manual fallback after 4s) -->
    <!-- Offline banner -->

    <div class="container">
        <!-- Alert box -->
        <form id="tablonForm" enctype="multipart/form-data">
            <!-- Card: Photo (camera input) -->
            <!-- Card: Wood type + Slab type (dropdowns) -->
            <!-- Card: Dimensions (largo, ancho, espesor inputs) -->
            <!-- Card: Calculated price display -->
            <!-- Publish button -->
        </form>
    </div>

    <!-- Login modal overlay -->

    <!-- Script: inject priceTable and TABLON_I18N -->
    <!-- Script: load AddTablon.js -->
    <!-- Script: register service worker -->
</body>
</html>
```

### Complete Template

Create the template exactly as shown in the ecommerce source, but update the JS asset path:

```twig
<script src="{{ asset('Plugins/WoodPublish/Assets/JS/AddTablon.js') }}"></script>
```

And in the service worker registration:

```javascript
if ('serviceWorker' in navigator) {
    var swUrl = '{{ asset("AddTablon") }}' + '?action=sw';
    var scope = swUrl.substring(0, swUrl.lastIndexOf('/') + 1) || '/';
    navigator.serviceWorker.register(swUrl, {scope: scope})
        .catch(function() {});
}
```

> **Important**: The scope is dynamically computed from the asset URL. This correctly handles both root (`/`) and subdirectory (`/facturascripts/`) FacturaScripts installations.

---

## 10. Translations

Four language files, each containing only WoodPublish-specific keys. Create `Translation/{lang}.json` for each:

### es_ES.json (Spanish — Primary)
```json
{
    "add-tablon": "Añadir Tablón",
    "tablon-precio": "Precio de Tablón",
    "tablon-precios": "Precios de Tablones",
    "tipo-madera": "Tipo de Madera",
    "tipo-tablon": "Tipo de Tablón",
    "precio-m2": "Precio por m²",
    "largo": "Largo (cm)",
    "ancho": "Ancho (cm)",
    "espesor": "Espesor (cm)",
    "dimensions": "Dimensiones",
    "calculated-price": "Precio calculado",
    "invalid-dimensions": "Las dimensiones ingresadas no son válidas.",
    "tablon-photo": "Foto",
    "tablon-tap-photo": "Toque para tomar una foto",
    "tablon-type": "Tipo",
    "tablon-select": "Seleccionar",
    "tablon-publish": "Publicar Tablón",
    "tablon-added-successfully": "¡Tablón añadido y publicado correctamente!",
    "tablon-save-failed": "Error al guardar el tablón.",
    "tablon-price-not-found": "No se encontró un precio para esta combinación.",
    "tablon-family-not-found": "No se encontró la familia de tablones.",
    "tablon-offline": "Sin conexión — los tablones se guardarán localmente",
    "tablon-saved-offline": "Tablón guardado localmente. Se publicará automáticamente cuando vuelva la conexión.",
    "tablon-syncing": "Sincronizando {n} tablón(es) pendiente(s)…",
    "tablon-sync-done": "{n} tablón(es) sincronizado(s) correctamente.",
    "tablon-sync-partial": "{ok} sincronizado(s), {fail} fallido(s). Se reintentará.",
    "tablon-sync-now": "Sincronizar",
    "tablon-pending": "Tablones pendientes de sincronizar",
    "tablon-app-install": "Añadir Tablones App",
    "tablon-install-prompt": "Instalar esta app para acceso rápido",
    "tablon-install-btn": "Instalar",
    "tablon-login-title": "Iniciar sesión para añadir tablones",
    "tablon-logout": "Cerrar sesión",
    "tablon-access-denied": "No tiene permiso para acceder a esta página.",
    "user": "Usuario",
    "password": "Contraseña",
    "login": "Iniciar Sesión",
    "login-error": "Usuario o contraseña incorrectos.",
    "login-required": "Debe iniciar sesión para realizar esta acción."
}
```

### en_EN.json (English)
```json
{
    "add-tablon": "Add Slab",
    "tablon-precio": "Slab Price",
    "tablon-precios": "Slab Prices",
    "tipo-madera": "Wood Type",
    "tipo-tablon": "Slab Type",
    "precio-m2": "Price per m²",
    "largo": "Length (cm)",
    "ancho": "Width (cm)",
    "espesor": "Thickness (cm)",
    "dimensions": "Dimensions",
    "calculated-price": "Calculated price",
    "invalid-dimensions": "The entered dimensions are not valid.",
    "tablon-photo": "Photo",
    "tablon-tap-photo": "Tap to take a photo",
    "tablon-type": "Type",
    "tablon-select": "Select",
    "tablon-publish": "Publish Slab",
    "tablon-added-successfully": "Slab added and published successfully!",
    "tablon-save-failed": "Failed to save the slab.",
    "tablon-price-not-found": "No price found for this combination.",
    "tablon-family-not-found": "Tablones family not found.",
    "tablon-offline": "Offline — slabs will be saved locally",
    "tablon-saved-offline": "Slab saved locally. It will be published automatically when back online.",
    "tablon-syncing": "Syncing {n} pending slab(s)…",
    "tablon-sync-done": "{n} slab(s) synced successfully.",
    "tablon-sync-partial": "{ok} synced, {fail} failed. Will retry.",
    "tablon-sync-now": "Sync",
    "tablon-pending": "Slabs pending sync",
    "tablon-app-install": "Add Slabs App",
    "tablon-install-prompt": "Install this app for quick access",
    "tablon-install-btn": "Install",
    "tablon-login-title": "Log in to add slabs",
    "tablon-logout": "Log out",
    "tablon-access-denied": "You do not have permission to access this page.",
    "user": "User",
    "password": "Password",
    "login": "Log In",
    "login-error": "Incorrect user or password.",
    "login-required": "You must log in to perform this action."
}
```

### fr_FR.json (French)
```json
{
    "add-tablon": "Ajouter une Planche",
    "tablon-precio": "Prix de Planche",
    "tablon-precios": "Prix des Planches",
    "tipo-madera": "Type de Bois",
    "tipo-tablon": "Type de Planche",
    "precio-m2": "Prix au m²",
    "largo": "Longueur (cm)",
    "ancho": "Largeur (cm)",
    "espesor": "Épaisseur (cm)",
    "dimensions": "Dimensions",
    "calculated-price": "Prix calculé",
    "invalid-dimensions": "Les dimensions saisies ne sont pas valides.",
    "tablon-photo": "Photo",
    "tablon-tap-photo": "Appuyez pour prendre une photo",
    "tablon-type": "Type",
    "tablon-select": "Sélectionner",
    "tablon-publish": "Publier la Planche",
    "tablon-added-successfully": "Planche ajoutée et publiée avec succès !",
    "tablon-save-failed": "Erreur lors de l'enregistrement de la planche.",
    "tablon-price-not-found": "Aucun prix trouvé pour cette combinaison.",
    "tablon-family-not-found": "Famille de planches introuvable.",
    "tablon-offline": "Hors ligne — les planches seront enregistrées localement",
    "tablon-saved-offline": "Planche enregistrée localement. Elle sera publiée automatiquement au retour de la connexion.",
    "tablon-syncing": "Synchronisation de {n} planche(s) en attente…",
    "tablon-sync-done": "{n} planche(s) synchronisée(s) avec succès.",
    "tablon-sync-partial": "{ok} synchronisée(s), {fail} échouée(s). Nouvelle tentative prévue.",
    "tablon-sync-now": "Synchroniser",
    "tablon-pending": "Planches en attente de synchronisation",
    "tablon-app-install": "Ajouter Planches App",
    "tablon-install-prompt": "Installer cette app pour un accès rapide",
    "tablon-install-btn": "Installer",
    "tablon-login-title": "Connectez-vous pour ajouter des planches",
    "tablon-logout": "Déconnexion",
    "tablon-access-denied": "Vous n'avez pas la permission d'accéder à cette page.",
    "user": "Utilisateur",
    "password": "Mot de passe",
    "login": "Se Connecter",
    "login-error": "Utilisateur ou mot de passe incorrect.",
    "login-required": "Vous devez vous connecter pour effectuer cette action."
}
```

### de_DE.json (German)
```json
{
    "add-tablon": "Bohle Hinzufügen",
    "tablon-precio": "Bohlenpreis",
    "tablon-precios": "Bohlenpreise",
    "tipo-madera": "Holzart",
    "tipo-tablon": "Bohlentyp",
    "precio-m2": "Preis pro m²",
    "largo": "Länge (cm)",
    "ancho": "Breite (cm)",
    "espesor": "Dicke (cm)",
    "dimensions": "Maße",
    "calculated-price": "Berechneter Preis",
    "invalid-dimensions": "Die eingegebenen Maße sind ungültig.",
    "tablon-photo": "Foto",
    "tablon-tap-photo": "Tippen, um ein Foto aufzunehmen",
    "tablon-type": "Typ",
    "tablon-select": "Auswählen",
    "tablon-publish": "Bohle Veröffentlichen",
    "tablon-added-successfully": "Bohle erfolgreich hinzugefügt und veröffentlicht!",
    "tablon-save-failed": "Fehler beim Speichern der Bohle.",
    "tablon-price-not-found": "Kein Preis für diese Kombination gefunden.",
    "tablon-family-not-found": "Bohlen-Familie nicht gefunden.",
    "tablon-offline": "Offline — Bohlen werden lokal gespeichert",
    "tablon-saved-offline": "Bohle lokal gespeichert. Sie wird automatisch veröffentlicht, sobald die Verbindung wiederhergestellt ist.",
    "tablon-syncing": "Synchronisierung von {n} ausstehende(n) Bohle(n)…",
    "tablon-sync-done": "{n} Bohle(n) erfolgreich synchronisiert.",
    "tablon-sync-partial": "{ok} synchronisiert, {fail} fehlgeschlagen. Wird erneut versucht.",
    "tablon-sync-now": "Synchronisieren",
    "tablon-pending": "Bohlen warten auf Synchronisierung",
    "tablon-app-install": "Bohlen App hinzufügen",
    "tablon-install-prompt": "Diese App für schnellen Zugriff installieren",
    "tablon-install-btn": "Installieren",
    "tablon-login-title": "Anmelden, um Bohlen hinzuzufügen",
    "tablon-logout": "Abmelden",
    "tablon-access-denied": "Sie haben keine Berechtigung, auf diese Seite zuzugreifen.",
    "user": "Benutzer",
    "password": "Passwort",
    "login": "Anmelden",
    "login-error": "Falscher Benutzername oder Passwort.",
    "login-required": "Sie müssen sich anmelden, um diese Aktion auszuführen."
}
```

---

## 11. PWA Icons

Create two PNG icons in `Assets/icons/`:

- **icon-192.png** — 192×192 pixels. Design: a stylised wood/tree icon with the company branding. This is displayed on the phone home screen.
- **icon-512.png** — 512×512 pixels. Same design, higher resolution. Used for splash screens and install prompts.

> **Critical**: Chrome requires PNG icons to be present and valid. SVG-only manifests will fail the installability check.

### Icon Design Guidelines
- Simple, recognisable at small sizes
- Works on both light and dark backgrounds
- Represents wood/timber industry (tree, plank, grain pattern)
- Company logo can be overlaid or adapted

---

## 12. Technical Notes & Gotchas

### FacturaScripts Plugin Conventions
- **Namespace**: `FacturaScripts\Plugins\{PluginName}\` — PSR-4, folder name = plugin name
- **Controllers** extend `Controller` (for custom pages) or `ListController`/`EditController` (for admin CRUD)
- **Models** extend `ModelClass`, use `ModelTrait`, must declare `primaryColumn()`, `tableName()`
- **Table schemas** are XML files in `Table/` — FS auto-creates tables on plugin install
- **Extension schemas** in `Extension/Table/` add columns to core tables
- **Views** are Twig templates in `View/`. Admin views are XML in `XMLView/`
- **Translations** are JSON files in `Translation/`. Keys are lowercase-hyphenated
- **`FS_FOLDER`** — Absolute path to FacturaScripts installation root
- **`Tools::config('route', '/')`** — URL base path (e.g. `/` or `/facturascripts/`)
- **`$this->view('Template.html.twig')`** — Render a Twig template
- **`$this->request()`** — Symfony Request object
- **`Session::get('user')`** — Currently authenticated User or null
- **`$user->can('PageName')`** — Check if user has permission for a controller page

### PWA Serving Strategy
- **Manifest** must be served dynamically (not as static file) because URLs depend on `Tools::config('route')`
- **Service worker** is served through the controller with `Service-Worker-Allowed: /` header to fix scope mismatch (static SW at `Assets/` has wrong scope)
- **Icons** are served through the controller to avoid direct file access issues with the `Plugins/` directory
- **BASE variable** is injected at serve time into the service worker JS

### Cookie Authentication Details
- FS core uses `fsNick` and `fsLogkey` cookies for session management
- `User::verifyLogkey()` is a simple string comparison (`$user->logkey === $value`)
- `$user->newLogkey($ip, $browser)` generates a new session key
- Cookie path must match `Tools::config('route', '/')` for FS to recognise them
- `SameSite=Lax` is required for cross-page navigation to work

### Product Creation Details
- Products are created via `\FacturaScripts\Dinamic\Model\Producto` (Dinamic namespace for extensibility)
- Each product auto-creates a default `Variante` — update its price and stock after save
- Product dimensions stored in custom columns (`largo`, `ancho`, `espesor`) added by `Extension/Table/productos.xml`
- Product images use three records: `AttachedFile` → `AttachedFileRelation` → `ProductoImagen`
- Files are stored in `FS_FOLDER/MyFiles/` directory
- The product's `codfamilia` must point to a family with `tipofamilia = 'tablones'`

### Prerequisite: Family Configuration
The admin must create at least one `Familia` (product family) with `tipofamilia = 'tablones'` in FacturaScripts for the PWA to know where to categorise new products. The `tipofamilia` column is added by either this plugin's extensions or the ecommerce plugin. If both plugins are installed, FS deduplicates the column definition.

---

## 13. First Steps After Repository Creation

1. **Create the directory structure** as specified in §3
2. **Copy the complete code** from §4–§10 into the corresponding files
3. **Create placeholder PNG icons** in `Assets/icons/` (192×192 and 512×512)
4. **Copy the AddTablon.html.twig template** from the ecommerce plugin, updating:
   - JS asset path: `Plugins/WoodPublish/Assets/JS/AddTablon.js`
   - Remove any references to `ecommerce` in asset paths
5. **Create the LICENSE file** (LGPL v3)
6. **Create README.md** with installation instructions
7. **Test**: Install the plugin in a FacturaScripts instance, create a `tablones` family, add some `TablonPrecio` records, then visit `/AddTablon` on a mobile device

---

## 14. Future Enhancements (Not in Scope for Initial Build)

- **Custom branding**: Replace hard-coded PWA name/colors with admin-configurable settings
- **Bulk upload**: Allow multiple photos per session
- **Photo cropping/editing**: Client-side image editing before upload
- **Barcode scanning**: Scan slab identification barcodes
- **Push notifications**: Notify admin when new slabs are published
- **Analytics dashboard**: Track publishing activity per user/day
