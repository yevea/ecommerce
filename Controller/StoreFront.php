<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
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
        return number_format($amount, 2, ',', '.') . ' €';
    }

    private function loadStoreFrontData(): void
    {
        $action = $this->request->request->get('action', '');
        if ($action === 'add-to-cart') {
            $this->addToCart();
        }

        $this->loadCategories();
        $this->loadProducts();
        $this->loadCartItemCount();
    }

    private function addToCart(): void
    {
        $productId = (int) $this->request->request->get('product_id', 0);
        if ($productId <= 0) {
            return;
        }

        $sessionId = $this->getSessionId();

        $cartItem = new EcommerceCartItem();
        $where = [
            Where::eq('session_id', $sessionId),
            Where::eq('product_id', $productId),
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

        Tools::log()->notice('product-added-to-cart');
    }

    private function loadCategories(): void
    {
        $category = new EcommerceCategory();
        $where = [Where::eq('active', true)];
        $this->categories = $category->all($where, ['name' => 'ASC']);
    }

    private function loadProducts(): void
    {
        $product = new EcommerceProduct();
        $where = [
            Where::eq('active', true),
            Where::eq('visibility', 'public'),
        ];

        $categoryId = $this->request->query->get('category', null);
        if ($categoryId !== null) {
            $this->selectedCategory = (int) $categoryId;
            $where[] = Where::eq('category_id', $this->selectedCategory);
        }

        $this->products = $product->all($where, ['name' => 'ASC']);
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
