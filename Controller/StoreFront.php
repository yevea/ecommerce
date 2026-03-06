<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Model\Familia;
use FacturaScripts\Core\Model\Producto;
use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceCartItem;

class StoreFront extends Controller
{
    private const TRANSLITERATIONS = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ñ' => 'n', 'ü' => 'u',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
        'Ñ' => 'N', 'Ü' => 'U',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o',
        'ç' => 'c', 'ß' => 'ss',
    ];

    protected $requiresAuth = false;

    /** @var bool When false, subclasses manage their own view rendering after parent::run() */
    protected $autoRenderView = true;

    /** @var Familia[] */
    public $categories = [];

    /** @var object[] */
    public $products = [];

    /** @var string|null */
    public $selectedCategory = null;

    /** @var string|null Family type of the selected category */
    public $selectedCategoryType = null;

    /** @var object|null Family data for the selected category (includes dimension limits for tableros) */
    public $selectedCategoryFamily = null;

    /** @var int */
    public $cartItemCount = 0;

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'ecommerce';
        $pageData['title'] = 'storefront';
        $pageData['icon'] = 'fa-solid fa-store';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function run(): void
    {
        parent::run();

        $cssPath = FS_FOLDER . '/Plugins/ecommerce/Assets/CSS/ecommerce.css';
        if (file_exists($cssPath)) {
            AssetManager::addCss(FS_ROUTE . '/Plugins/ecommerce/Assets/CSS/ecommerce.css');
        }

        $action = $this->request()->request->get('action', $this->request()->query->get('action', ''));
        if ($action === 'add-to-cart') {
            $this->addToCart();
        } elseif ($action === 'stripe-checkout') {
            $this->stripeCheckout();
        }

        $this->selectedCategory = $this->request()->query->get('category', null) ?: null;
        $this->loadCategories();
        $this->loadSelectedCategoryType();
        $this->loadProducts();
        $this->loadCartItemCount();

        if ($this->autoRenderView) {
            $this->view($this->controllerName() . '.html.twig');
        }
    }

    public function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' €';
    }

    /**
     * Generate a PascalCase URL slug from a category name.
     * E.g. "Tableros Mesa" → "TablerosMesa", "Artesanía" → "Artesania"
     */
    public static function generateSlug(string $text): string
    {
        $text = strtr($text, self::TRANSLITERATIONS);
        $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
        $text = str_replace(' ', '', ucwords($text));
        return $text;
    }

    /**
     * Generate a lowercase, hyphen-separated SEO-friendly slug from a product name.
     * E.g. "Tablero Mesa Olivo" → "tablero-mesa-olivo", "Artesanía Cuenco" → "artesania-cuenco"
     */
    public static function generateProductSlug(string $text): string
    {
        $text = strtr($text, self::TRANSLITERATIONS);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        $text = trim($text, '-');
        return $text;
    }

    protected function addToCart(): void
    {
        $productReferencia = $this->request()->request->get('product_referencia', '');
        if (empty($productReferencia)) {
            return;
        }

        $isPublic = false;
        $familyType = 'mercancia';
        $product = new Producto();
        $where = [Where::eq('referencia', $productReferencia)];
        if ($product->loadWhere($where)) {
            $isPublic = $product->publico;
            $familyType = $this->getFamilyTypeForProduct($product);
        } else {
            // Product not found by referencia — try looking up via Variante for non-primary variants
            $varianteClass = '\FacturaScripts\Core\Model\Variante';
            if (class_exists($varianteClass)) {
                $variante = new $varianteClass();
                $varWhere = [Where::eq('referencia', $productReferencia)];
                if ($variante->loadWhere($varWhere)) {
                    $parent = new Producto();
                    if ($parent->loadFromCode($variante->idproducto)) {
                        $isPublic = $parent->publico;
                        $familyType = $this->getFamilyTypeForProduct($parent);
                    }
                }
            }
        }

        if (!$isPublic) {
            return;
        }

        $qty = max(1, (int) $this->request()->request->get('quantity', 1));

        // For Artesanía, quantity is always 1
        if ($familyType === 'artesania') {
            $qty = 1;
        }

        // For Tableros, get customer dimensions
        $largoCm = null;
        $anchoCm = null;
        if ($familyType === 'tableros') {
            $largoCm = (float) $this->request()->request->get('largo_cm', 0);
            $anchoCm = (float) $this->request()->request->get('ancho_cm', 0);
            if ($largoCm <= 0 || $anchoCm <= 0) {
                Tools::log()->warning('invalid-dimensions');
                return;
            }
            $qty = 1;
        }

        $sessionId = $this->getSessionId();

        $cartItem = new EcommerceCartItem();
        $where = [
            Where::eq('session_id', $sessionId),
            Where::eq('product_referencia', $productReferencia),
        ];

        // For Tableros, each dimension combination is a separate cart item
        if ($familyType !== 'tableros') {
            $existing = $cartItem->all($where);
            if (!empty($existing)) {
                if ($familyType === 'artesania') {
                    // Artesanía: don't add more, quantity stays at 1
                    return;
                }
                $existing[0]->quantity += $qty;
                $existing[0]->save();
                Tools::log()->notice('product-added-to-cart');
                return;
            }
        }

        $cartItem->session_id = $sessionId;
        $cartItem->product_referencia = $productReferencia;
        $cartItem->quantity = $qty;
        $cartItem->largo_cm = $largoCm;
        $cartItem->ancho_cm = $anchoCm;
        $cartItem->save();

        Tools::log()->notice('product-added-to-cart');
    }

    protected function stripeCheckout(): void
    {
        $productReferencia = $this->request()->request->get('product_referencia', '');
        if (empty($productReferencia)) {
            return;
        }

        $product = new Producto();
        $where = [Where::eq('referencia', $productReferencia)];
        if (!$product->loadWhere($where)) {
            Tools::log()->error('product-not-found');
            return;
        }

        $secretKey = Tools::settings('ecommerce', 'stripe_secret_key', '');
        if (empty($secretKey)) {
            Tools::log()->error('stripe-not-configured');
            return;
        }

        $checkoutUrl = $this->createStripeCheckoutSession($product, $secretKey);
        if ($checkoutUrl) {
            header('Location: ' . $checkoutUrl, true, 302);
            exit;
        }

        Tools::log()->error('stripe-session-failed');
    }

    protected function controllerName(): string
    {
        return basename(str_replace('\\', '/', static::class));
    }

    protected function createStripeCheckoutSession(Producto $product, string $secretKey): ?string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = preg_replace('/[^a-zA-Z0-9.\-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost');
        $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
        $defaultPort = ($scheme === 'https') ? 443 : 80;
        $hostWithPort = ($port !== $defaultPort) ? $host . ':' . $port : $host;
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $baseUrl = $scheme . '://' . $hostWithPort . $scriptDir;
        $ctrl = $this->controllerName();

        $params = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => ['name' => $product->descripcion],
                    'unit_amount' => (int) round($product->precio * 100),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $baseUrl . '/' . $ctrl . '?stripe=success',
            'cancel_url' => $baseUrl . '/' . $ctrl . '?stripe=cancel',
        ];

        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_USERPWD, $secretKey . ':');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['url'] ?? null;
    }

    protected function loadCategories(): void
    {
        // Only show families that contain at least one public product
        $product = new Producto();
        $publicProducts = $product->all([Where::eq('publico', true)], [], 0, 0);

        $familyCodes = [];
        foreach ($publicProducts as $p) {
            if (!empty($p->codfamilia)) {
                $familyCodes[$p->codfamilia] = true;
            }
        }

        if (empty($familyCodes)) {
            $this->categories = [];
            return;
        }

        $familia = new Familia();
        $this->categories = $familia->all(
            [Where::in('codfamilia', array_keys($familyCodes))],
            ['descripcion' => 'ASC'],
            0,
            0
        );
    }

    protected function loadSelectedCategoryType(): void
    {
        $this->selectedCategoryType = null;
        $this->selectedCategoryFamily = null;

        if ($this->selectedCategory === null) {
            return;
        }

        $familia = new Familia();
        if ($familia->loadFromCode($this->selectedCategory)) {
            $tipo = $familia->tipofamilia ?? 'mercancia';
            $this->selectedCategoryType = $tipo;
            $this->selectedCategoryFamily = (object) [
                'codfamilia' => $familia->codfamilia,
                'descripcion' => $familia->descripcion,
                'tipofamilia' => $tipo,
                'largo_min' => (float) ($familia->largo_min ?? 0),
                'largo_max' => (float) ($familia->largo_max ?? 0),
                'ancho_min' => (float) ($familia->ancho_min ?? 0),
                'ancho_max' => (float) ($familia->ancho_max ?? 0),
            ];
        }
    }

    protected function loadProducts(): void
    {
        $product = new Producto();
        $where = [Where::eq('publico', true)];

        if ($this->selectedCategory !== null) {
            $where[] = Where::eq('codfamilia', $this->selectedCategory);
        }

        $nativeProducts = $product->all($where, ['descripcion' => 'ASC'], 0, 0);

        // Build a map of family codes to types for efficient lookup
        $familyTypeMap = [];
        foreach ($this->categories as $cat) {
            $familyTypeMap[$cat->codfamilia] = $cat->tipofamilia ?? 'mercancia';
        }

        $this->products = [];
        $imgModelClass = '\FacturaScripts\Core\Model\ProductoImagen';
        foreach ($nativeProducts as $p) {
            $imageUrl = null;
            if (class_exists($imgModelClass)) {
                $imgWhere = [Where::eq('idproducto', $p->idproducto)];
                $images = (new $imgModelClass())->all($imgWhere, ['orden' => 'ASC'], 0, 1);
                if (!empty($images)) {
                    $imageUrl = $images[0]->url('download-permanent');
                }
            }

            $familyType = $familyTypeMap[$p->codfamilia] ?? 'mercancia';

            // For Artesanía: determine if product is sold (stock <= 0)
            $isSold = false;
            if ($familyType === 'artesania' && $p->stockfis <= 0) {
                $isSold = true;
            }

            $productObj = (object) [
                'referencia' => $p->referencia,
                'slug' => self::generateProductSlug($p->descripcion),
                'name' => $p->descripcion,
                'description' => $p->observaciones ?? '',
                'price' => $p->precio,
                'stock' => $p->stockfis,
                'nostock' => (bool) ($p->nostock ?? false),
                'image' => $imageUrl,
                'familyType' => $familyType,
                'isSold' => $isSold,
                'idproducto' => $p->idproducto,
            ];

            $this->products[] = $productObj;
        }
    }

    protected function loadCartItemCount(): void
    {
        $cartItem = new EcommerceCartItem();
        $where = [Where::eq('session_id', $this->getSessionId())];
        $items = $cartItem->all($where);
        $this->cartItemCount = 0;
        foreach ($items as $item) {
            $this->cartItemCount += $item->quantity;
        }
    }

    protected function getSessionId(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return session_id();
    }

    protected function getFamilyTypeForProduct(Producto $product): string
    {
        if (empty($product->codfamilia)) {
            return 'mercancia';
        }

        $familia = new Familia();
        if ($familia->loadFromCode($product->codfamilia)) {
            return $familia->tipofamilia ?? 'mercancia';
        }

        return 'mercancia';
    }
}
