<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceCartItem;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceOrder;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceOrderLine;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceProduct;

class ShoppingCartView extends Controller
{
    /** @var array */
    public $cartItems = [];

    /** @var float */
    public $cartTotal = 0;

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'ecommerce';
        $pageData['title'] = 'shopping-cart';
        $pageData['icon'] = 'fa-solid fa-shopping-cart';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $action = $this->request->request->get('action', '');
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
    }

    public function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' €';
    }

    private function updateQuantity(): void
    {
        $cartItemId = (int) $this->request->request->get('cart_item_id', 0);
        $quantity = (int) $this->request->request->get('quantity', 1);

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
        $cartItemId = (int) $this->request->request->get('cart_item_id', 0);

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
        $where = [new \FacturaScripts\Core\Where('session_id', '=', $sessionId)];
        $items = $cartItem->all($where);

        if (empty($items)) {
            Tools::log()->warning('cart-empty');
            return;
        }

        $order = new EcommerceOrder();
        $order->customer_name = trim($this->request->request->get('customer_name', ''));
        $order->customer_email = trim($this->request->request->get('customer_email', ''));
        $order->address = trim($this->request->request->get('address', ''));
        $order->notes = trim($this->request->request->get('notes', ''));
        $order->status = 'pending';

        if (empty($order->customer_name)) {
            Tools::log()->warning('customer-name-required');
            return;
        }

        if (!empty($order->customer_email) && false === filter_var($order->customer_email, FILTER_VALIDATE_EMAIL)) {
            Tools::log()->warning('invalid-email');
            return;
        }

        $total = 0;
        $orderLines = [];

        foreach ($items as $item) {
            $product = new EcommerceProduct();
            if ($product->loadFromCode($item->product_id)) {
                $subtotal = $product->price * $item->quantity;
                $total += $subtotal;

                $line = new EcommerceOrderLine();
                $line->product_id = $product->id;
                $line->product_name = $product->name;
                $line->quantity = $item->quantity;
                $line->price = $product->price;
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

            Tools::log()->notice('order-placed-successfully');
            $this->redirect('EditEcommerceOrder?code=' . $order->id);
        } else {
            Tools::log()->error('order-placement-failed');
        }
    }

    private function loadCartItems(): void
    {
        $sessionId = $this->getSessionId();
        $this->cartItems = [];
        $this->cartTotal = 0;

        $cartItem = new EcommerceCartItem();
        $where = [new \FacturaScripts\Core\Where('session_id', '=', $sessionId)];
        $items = $cartItem->all($where);

        foreach ($items as $item) {
            $product = new EcommerceProduct();
            if ($product->loadFromCode($item->product_id)) {
                $this->cartItems[] = (object) [
                    'id' => $item->id,
                    'product_name' => $product->name,
                    'product_price' => $product->price,
                    'quantity' => $item->quantity,
                ];
                $this->cartTotal += $product->price * $item->quantity;
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
