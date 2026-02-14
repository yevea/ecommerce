<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\PedidoCliente;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceCartItem;

class ShoppingCartView extends Controller
{
    /** @var array */
    public $cartItems = [];

    /** @var float */
    public $cartTotal = 0;

    /** @var bool */
    public $orderPlaced = false;

    /** @var string */
    public $orderCode = '';

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

        if (false === $this->orderPlaced) {
            $this->loadCartItems();
        }
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
        $customerName = trim($this->request->request->get('nombrecliente', ''));
        $cifnif = trim($this->request->request->get('cifnif', ''));
        $email = trim($this->request->request->get('email', ''));

        if (empty($customerName)) {
            Tools::log()->warning('customer-name-required');
            return;
        }

        if (empty($cifnif)) {
            Tools::log()->warning('cifnif-required');
            return;
        }

        // find existing customer by cifnif or create a new one
        $cliente = new Cliente();
        $where = [new DataBaseWhere('cifnif', $cifnif)];
        if (false === $cliente->loadWhere($where)) {
            $cliente->cifnif = $cifnif;
            $cliente->nombre = $customerName;
            $cliente->razonsocial = $customerName;
            $cliente->email = $email;
            if (false === $cliente->save()) {
                Tools::log()->error('order-placement-failed');
                return;
            }
        }

        // create a native FS PedidoCliente
        $pedido = new PedidoCliente();
        $pedido->setSubject($cliente);
        $pedido->observaciones = $notes;

        if (false === $pedido->save()) {
            Tools::log()->error('order-placement-failed');
            return;
        }

        // add lines from cart
        $lineFailed = false;
        foreach ($items as $item) {
            $producto = new Producto();
            if ($producto->loadFromCode($item->idproducto)) {
                $newLine = $pedido->getNewProductLine($producto->referencia);
                $newLine->cantidad = $item->quantity;
                if (false === $newLine->save()) {
                    $lineFailed = true;
                    break;
                }
            }
        }

        if ($lineFailed) {
            $pedido->delete();
            Tools::log()->error('order-placement-failed');
            return;
        }

        // clear cart
        foreach ($items as $item) {
            $item->delete();
        }

        Tools::log()->notice('order-placed-successfully');
        $this->orderPlaced = true;
        $this->orderCode = $pedido->codigo;
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
