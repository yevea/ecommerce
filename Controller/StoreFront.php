<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Model\Familia;
use FacturaScripts\Core\Model\Producto;
use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceCartItem;

class StoreFront extends Controller
{
    protected $requiresAuth = false;

    /** @var Familia[] */
    public $categories = [];

    /** @var object[] */
    public $products = [];

    /** @var string|null */
    public $selectedCategory = null;

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

        $action = $this->request()->request->get('action', $this->request()->query->get('action', ''));
        if ($action === 'add-to-cart') {
            $this->addToCart();
        } elseif ($action === 'stripe-checkout') {
            $this->stripeCheckout();
        }

        $this->selectedCategory = $this->request()->query->get('category', null) ?: null;
        $this->loadCategories();
        $this->loadProducts();
        $this->loadCartItemCount();

        $this->view($this->controllerName() . '.html.twig');
    }

    public function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' €';
    }

    protected function addToCart(): void
    {
        $productReferencia = $this->request()->request->get('product_referencia', '');
        if (empty($productReferencia)) {
            return;
        }

        $product = new Producto();
        if (!$product->loadFromCode($productReferencia) || !$product->publico) {
            return;
        }

        $sessionId = $this->getSessionId();

        $cartItem = new EcommerceCartItem();
        $where = [
            new \FacturaScripts\Core\Where('session_id', $sessionId),
            new \FacturaScripts\Core\Where('product_referencia', $productReferencia),
        ];

        $existing = $cartItem->all($where);
        if (!empty($existing)) {
            $existing[0]->quantity += 1;
            $existing[0]->save();
        } else {
            $cartItem->session_id = $sessionId;
            $cartItem->product_referencia = $productReferencia;
            $cartItem->quantity = 1;
            $cartItem->save();
        }

        Tools::log()->notice('product-added-to-cart');
    }

    protected function stripeCheckout(): void
    {
        $productReferencia = $this->request()->request->get('product_referencia', '');
        if (empty($productReferencia)) {
            return;
        }

        $product = new Producto();
        if (!$product->loadFromCode($productReferencia)) {
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
        $familia = new Familia();
        $where = [new \FacturaScripts\Core\Where('publica', true)];
        $this->categories = $familia->all($where, ['descripcion' => 'ASC']);
    }

    protected function loadProducts(): void
    {
        $product = new Producto();
        $where = [new \FacturaScripts\Core\Where('publico', true)];

        if ($this->selectedCategory !== null) {
            $where[] = new \FacturaScripts\Core\Where('codfamilia', $this->selectedCategory);
        }

        $nativeProducts = $product->all($where, ['descripcion' => 'ASC']);

        $this->products = [];
        foreach ($nativeProducts as $p) {
            $this->products[] = (object) [
                'referencia' => $p->referencia,
                'name' => $p->descripcion,
                'description' => $p->observaciones ?? '',
                'price' => $p->precio,
                'stock' => $p->stockfis,
                'image' => property_exists($p, 'imagen') ? $p->imagen : null,
            ];
        }
    }

    protected function loadCartItemCount(): void
    {
        $cartItem = new EcommerceCartItem();
        $where = [new \FacturaScripts\Core\Where('session_id', $this->getSessionId())];
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
}
