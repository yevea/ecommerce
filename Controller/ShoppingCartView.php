<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\PedidoCliente;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceCartItem;

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
        $this->loadShoppingCartData();
    }

    public function publicCore(&$response)
    {
        parent::publicCore($response);
        $this->setTemplate('ShoppingCartView');
        $this->loadShoppingCartData();
    }

    public function formatMoney(float $amount): string
    {
        return Tools::money($amount);
    }

    private function loadShoppingCartData(): void
    {
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
        $where = [Where::eq('session_id', $sessionId)];
        $items = $cartItem->all($where);

        if (empty($items)) {
            Tools::log()->warning('cart-empty');
            return;
        }

        $notes = trim($this->request->request->get('notes', ''));

        // create a native FS PedidoCliente
        $pedido = new PedidoCliente();
        $pedido->codalmacen = Tools::settings('default', 'codalmacen');
        $pedido->codserie = Tools::settings('default', 'codserie');
        $pedido->idempresa = (int) Tools::settings('default', 'idempresa', 1);
        $pedido->observaciones = $notes;

        if (false === $pedido->save()) {
            Tools::log()->error('order-placement-failed');
            return;
        }

        // add lines from cart
        foreach ($items as $item) {
            $producto = new Producto();
            if ($producto->loadFromCode($item->idproducto)) {
                $newLine = $pedido->getNewProductLine($producto->referencia);
                $newLine->cantidad = $item->quantity;
                if (false === $newLine->save()) {
                    Tools::log()->error('order-placement-failed');
                    return;
                }
            }
        }

        // clear cart
        foreach ($items as $item) {
            $item->delete();
        }

        Tools::log()->notice('order-placed-successfully');
        $this->redirect('EditPedidoCliente?code=' . $pedido->idpedido);
    }

    private function loadCartItems(): void
    {
        $sessionId = $this->getSessionId();
        $this->cartItems = [];
        $this->cartTotal = 0;

        $cartItem = new EcommerceCartItem();
        $where = [Where::eq('session_id', $sessionId)];
        $items = $cartItem->all($where);

        foreach ($items as $item) {
            $producto = new Producto();
            if ($producto->loadFromCode($item->idproducto)) {
                $this->cartItems[] = (object) [
                    'id' => $item->id,
                    'referencia' => $producto->referencia,
                    'descripcion' => $producto->descripcion,
                    'precio' => $producto->precio,
                    'quantity' => $item->quantity,
                ];
                $this->cartTotal += $producto->precio * $item->quantity;
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
