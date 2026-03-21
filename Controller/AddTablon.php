<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Model\Familia;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;
use FacturaScripts\Dinamic\Model\ProductoImagen;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\ecommerce\Model\TablonPrecio;

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
        $pageData['menu'] = 'ecommerce';
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

    private function serveManifest(): void
    {
        $route = Tools::config('route', '/');
        $manifest = [
            'name' => 'Añadir Tablón',
            'short_name' => 'Tablón',
            'id' => $route . 'AddTablon',
            'description' => 'PWA para añadir tablones al catálogo de ecommerce',
            'start_url' => $route . 'AddTablon',
            'scope' => $route,
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => '#2c3e50',
            'background_color' => '#f0f2f5',
            'icons' => [
                [
                    'src' => $route . 'Plugins/ecommerce/Assets/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $route . 'Plugins/ecommerce/Assets/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $route . 'Plugins/ecommerce/Assets/icons/icon-512.png',
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
        $swPath = FS_FOLDER . '/Plugins/ecommerce/Assets/service-worker.js';
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
