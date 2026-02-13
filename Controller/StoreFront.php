<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Familia;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceCartItem;

class StoreFront extends Controller
{
    const MAX_PRODUCTS = 50;

    /** @var array */
    public $families = [];

    /** @var array */
    public $products = [];

    /** @var string|null */
    public $selectedFamily = null;

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

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->loadStoreFrontData();
    }

    public function publicCore(&$response)
    {
        parent::publicCore($response);
        $this->setTemplate('StoreFront');
        $this->loadStoreFrontData();
    }

    public function formatMoney(float $amount): string
    {
        return Tools::money($amount);
    }

    private function loadStoreFrontData(): void
    {
        $action = $this->request->request->get('action', '');
        if ($action === 'add-to-cart') {
            $this->addToCart();
        }

        $this->loadFamilies();
        $this->loadProducts();
        $this->loadCartItemCount();
    }

    private function addToCart(): void
    {
        $idproducto = (int) $this->request->request->get('idproducto', 0);
        if ($idproducto <= 0) {
            return;
        }

        $sessionId = $this->getSessionId();

        $cartItem = new EcommerceCartItem();
        $where = [
            Where::eq('session_id', $sessionId),
            Where::eq('idproducto', $idproducto),
        ];

        $existing = $cartItem->all($where);
        if (!empty($existing)) {
            $existing[0]->quantity += 1;
            $existing[0]->save();
        } else {
            $cartItem->session_id = $sessionId;
            $cartItem->idproducto = $idproducto;
            $cartItem->quantity = 1;
            $cartItem->save();
        }

        Tools::log()->notice('product-added-to-cart');
    }

    private function loadFamilies(): void
    {
        $familia = new Familia();
        $this->families = $familia->all([], ['descripcion' => 'ASC'], 0, 0);
    }

    private function loadProducts(): void
    {
        $producto = new Producto();
        $where = [
            new DataBaseWhere('publico', true),
            new DataBaseWhere('sevende', true),
            new DataBaseWhere('bloqueado', false),
        ];

        $codfamilia = $this->request->query->get('family', null);
        if (!empty($codfamilia)) {
            $this->selectedFamily = $codfamilia;
            $where[] = new DataBaseWhere('codfamilia', $codfamilia);
        }

        $this->products = $producto->all($where, ['descripcion' => 'ASC'], 0, self::MAX_PRODUCTS);
    }

    private function loadCartItemCount(): void
    {
        $cartItem = new EcommerceCartItem();
        $where = [Where::eq('session_id', $this->getSessionId())];
        $items = $cartItem->all($where);
        $this->cartItemCount = 0;
        foreach ($items as $item) {
            $this->cartItemCount += $item->quantity;
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
