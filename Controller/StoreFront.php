<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceCategory;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceCartItem;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceProduct;

class StoreFront extends Controller
{
    /** @var EcommerceCategory[] */
    public $categories = [];

    /** @var EcommerceProduct[] */
    public $products = [];

    /** @var int|null */
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
        $productId = (int) $this->request()->request->get('product_id', 0);
        if ($productId <= 0) {
            return;
        }

        $sessionId = $this->getSessionId();

        $cartItem = new EcommerceCartItem();
        $where = [
            new \FacturaScripts\Core\Where('session_id', '=', $sessionId),
            new \FacturaScripts\Core\Where('product_id', '=', $productId),
        ];

        $existing = $cartItem->all($where);
        if (!empty($existing)) {
            $existing[0]->quantity += 1;
            $existing[0]->save();
        } else {
            $cartItem->session_id = $sessionId;
            $cartItem->product_id = $productId;
            $cartItem->quantity = 1;
            $cartItem->save();
        }

        $this->toolBox()->i18nLog()->notice('product-added-to-cart');
    }

    protected function stripeCheckout(): void
    {
        $productId = (int) $this->request()->request->get('product_id', 0);
        if ($productId <= 0) {
            return;
        }

        $product = new EcommerceProduct();
        if (!$product->loadFromCode($productId)) {
            $this->toolBox()->i18nLog()->error('product-not-found');
            return;
        }

        $secretKey = Tools::settings('ecommerce', 'stripe_secret_key', '');
        if (empty($secretKey)) {
            $this->toolBox()->i18nLog()->error('stripe-not-configured');
            return;
        }

        $checkoutUrl = $this->createStripeCheckoutSession($product, $secretKey);
        if ($checkoutUrl) {
            header('Location: ' . $checkoutUrl, true, 302);
            exit;
        }

        $this->toolBox()->i18nLog()->error('stripe-session-failed');
    }

    protected function controllerName(): string
    {
        return basename(str_replace('\\', '/', static::class));
    }

    protected function createStripeCheckoutSession(EcommerceProduct $product, string $secretKey): ?string
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
                    'product_data' => ['name' => $product->name],
                    'unit_amount' => (int) round($product->price * 100),
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
        $category = new EcommerceCategory();
        $where = [new \FacturaScripts\Core\Where('active', '=', true)];
        $this->categories = $category->all($where, ['name' => 'ASC']);
    }

    protected function loadProducts(): void
    {
        $product = new EcommerceProduct();
        $where = [new \FacturaScripts\Core\Where('active', '=', true)];

        $categoryId = $this->request()->query->get('category', null);
        if ($categoryId !== null) {
            $this->selectedCategory = (int) $categoryId;
            $where[] = new \FacturaScripts\Core\Where('category_id', '=', $this->selectedCategory);
        }

        $this->products = $product->all($where, ['name' => 'ASC']);
    }

    protected function loadCartItemCount(): void
    {
        $cartItem = new EcommerceCartItem();
        $where = [new \FacturaScripts\Core\Where('session_id', '=', $this->getSessionId())];
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
