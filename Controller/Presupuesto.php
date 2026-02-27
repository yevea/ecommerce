<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Model\Producto;
use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceCartItem;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceOrder;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceOrderLine;

class Presupuesto extends Controller
{
    protected $requiresAuth = false;

    /** @var array */
    public $cartItems = [];

    /** @var float */
    public $cartTotal = 0;

    /** @var bool */
    public $orderSuccess = false;

    /** @var string */
    public $orderCode = '';

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'ecommerce';
        $pageData['title'] = 'presupuesto';
        $pageData['icon'] = 'fa-solid fa-file-invoice';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function run(): void
    {
        parent::run();

        $stripeCallback = $this->request()->query->get('stripe', '');
        if ($stripeCallback === 'success') {
            $this->handleStripeSuccess();
        } elseif ($stripeCallback === 'cancel') {
            $this->handleStripeCancel();
        }

        $action = $this->request()->request->get('action', '');
        switch ($action) {
            case 'update-quantity':
                $this->updateQuantity();
                break;

            case 'remove-item':
                $this->removeItem();
                break;

            case 'place-order':
                $this->placeOrder();
                break;
        }

        $this->loadCartItems();

        $this->view('Presupuesto.html.twig');
    }

    public function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' €';
    }

    private function updateQuantity(): void
    {
        $cartItemId = (int) $this->request()->request->get('cart_item_id', 0);
        $quantity = (int) $this->request()->request->get('quantity', 1);

        $cartItem = new EcommerceCartItem();
        if ($cartItem->loadFromCode($cartItemId)) {
            if ($cartItem->session_id === $this->getSessionId()) {
                $cartItem->quantity = max(1, $quantity);
                $cartItem->save();
            }
        }
    }

    private function removeItem(): void
    {
        $cartItemId = (int) $this->request()->request->get('cart_item_id', 0);

        $cartItem = new EcommerceCartItem();
        if ($cartItem->loadFromCode($cartItemId)) {
            if ($cartItem->session_id === $this->getSessionId()) {
                $cartItem->delete();
            }
        }
    }

    private function placeOrder(): void
    {
        $sessionId = $this->getSessionId();

        $cartItem = new EcommerceCartItem();
        $where = [new \FacturaScripts\Core\Where('session_id', $sessionId)];
        $items = $cartItem->all($where);

        if (empty($items)) {
            Tools::log()->warning('cart-empty');
            return;
        }

        $customerName = trim($this->request()->request->get('customer_name', ''));
        $customerEmail = trim($this->request()->request->get('customer_email', ''));
        $address = trim($this->request()->request->get('address', ''));
        $notes = trim($this->request()->request->get('notes', ''));

        if (empty($customerName)) {
            Tools::log()->warning('customer-name-required');
            return;
        }

        if (!empty($customerEmail) && false === filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            Tools::log()->warning('invalid-email');
            return;
        }

        $secretKey = Tools::settings('ecommerce', 'stripe_secret_key', '');
        if (empty($secretKey)) {
            Tools::log()->error('stripe-not-configured');
            return;
        }

        // Store pending order data in session to retrieve after Stripe callback
        $_SESSION['pending_ecommerce_order'] = [
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'address' => $address,
            'notes' => $notes,
        ];

        $checkoutUrl = $this->createStripeCheckoutSession($items, $secretKey);
        if ($checkoutUrl) {
            header('Location: ' . $checkoutUrl, true, 302);
            exit;
        }

        Tools::log()->error('stripe-session-failed');
    }

    private function handleStripeSuccess(): void
    {
        $stripeSessionId = $this->request()->query->get('stripe_session_id', '');
        if (empty($stripeSessionId)) {
            return;
        }

        $secretKey = Tools::settings('ecommerce', 'stripe_secret_key', '');
        if (empty($secretKey)) {
            return;
        }

        if (!$this->verifyStripePayment($stripeSessionId, $secretKey)) {
            Tools::log()->error('stripe-session-failed');
            return;
        }

        $sessionId = $this->getSessionId();
        $pendingOrder = $_SESSION['pending_ecommerce_order'] ?? null;
        if (empty($pendingOrder)) {
            // Session expired or order was already processed; show a generic success page
            $this->orderSuccess = true;
            Tools::log()->notice('order-placed-successfully');
            return;
        }

        $cartItem = new EcommerceCartItem();
        $where = [new \FacturaScripts\Core\Where('session_id', $sessionId)];
        $items = $cartItem->all($where);

        $order = new EcommerceOrder();
        $order->customer_name = $pendingOrder['customer_name'];
        $order->customer_email = $pendingOrder['customer_email'];
        $order->address = $pendingOrder['address'];
        $order->notes = $pendingOrder['notes'];
        $order->status = 'pending';

        $total = 0;
        $orderLines = [];

        foreach ($items as $item) {
            $product = new Producto();
            $productWhere = [new \FacturaScripts\Core\Where('referencia', $item->product_referencia)];
            if ($product->loadWhere($productWhere)) {
                $subtotal = $product->precio * $item->quantity;
                $total += $subtotal;

                $line = new EcommerceOrderLine();
                $line->product_referencia = $product->referencia;
                $line->product_name = $product->descripcion;
                $line->quantity = $item->quantity;
                $line->price = $product->precio;
                $line->subtotal = $subtotal;
                $orderLines[] = $line;
            }
        }

        $order->total = $total;

        if ($order->save()) {
            foreach ($orderLines as $line) {
                $line->order_id = $order->id;
                $line->save();
            }

            foreach ($items as $item) {
                $item->delete();
            }

            unset($_SESSION['pending_ecommerce_order']);
            $this->orderSuccess = true;
            $this->orderCode = $order->code;
        } else {
            Tools::log()->error('order-placement-failed');
        }
    }

    private function handleStripeCancel(): void
    {
        Tools::log()->notice('order-payment-cancelled');
    }

    private function createStripeCheckoutSession(array $items, string $secretKey): ?string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = preg_replace('/[^a-zA-Z0-9.\-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost');
        $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
        $defaultPort = ($scheme === 'https') ? 443 : 80;
        $hostWithPort = ($port !== $defaultPort) ? $host . ':' . $port : $host;
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $baseUrl = $scheme . '://' . $hostWithPort . $scriptDir;

        $lineItems = [];
        foreach ($items as $item) {
            $product = new Producto();
            $productWhere = [new \FacturaScripts\Core\Where('referencia', $item->product_referencia)];
            if ($product->loadWhere($productWhere)) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => ['name' => $product->descripcion],
                        'unit_amount' => (int) round($product->precio * 100),
                    ],
                    'quantity' => $item->quantity,
                ];
            }
        }

        if (empty($lineItems)) {
            return null;
        }

        $params = [
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $baseUrl . '/Presupuesto?stripe=success&stripe_session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $baseUrl . '/Presupuesto?stripe=cancel',
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
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            if ($curlError) {
                Tools::log()->error($curlError);
            }
            return null;
        }

        $data = json_decode($response, true);
        return $data['url'] ?? null;
    }

    private function verifyStripePayment(string $stripeSessionId, string $secretKey): bool
    {
        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($stripeSessionId));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $secretKey . ':');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            if ($curlError) {
                Tools::log()->error($curlError);
            }
            return false;
        }

        $data = json_decode($response, true);
        return isset($data['payment_status']) && $data['payment_status'] === 'paid';
    }

    private function loadCartItems(): void
    {
        $sessionId = $this->getSessionId();
        $this->cartItems = [];
        $this->cartTotal = 0;

        $cartItem = new EcommerceCartItem();
        $where = [new \FacturaScripts\Core\Where('session_id', $sessionId)];
        $items = $cartItem->all($where);

        foreach ($items as $item) {
            $product = new Producto();
            $where = [new \FacturaScripts\Core\Where('referencia', $item->product_referencia)];
            if ($product->loadWhere($where)) {
                $this->cartItems[] = (object) [
                    'id' => $item->id,
                    'product_name' => $product->descripcion,
                    'product_price' => $product->precio,
                    'quantity' => $item->quantity,
                ];
                $this->cartTotal += $product->precio * $item->quantity;
            }
        }
    }

    private function getSessionId(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return session_id();
    }
}
