<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Template\Controller;
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

        $action = $this->request->request->get('action', '');
        if ($action === 'add-to-cart') {
            $this->addToCart();
        }

        $this->loadCategories();
        $this->loadProducts();
        $this->loadCartItemCount();
    }

    public function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' €';
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

    private function loadCategories(): void
    {
        $category = new EcommerceCategory();
        $where = [new \FacturaScripts\Core\Where('active', '=', true)];
        $this->categories = $category->all($where, ['name' => 'ASC']);
    }

    private function loadProducts(): void
    {
        $product = new EcommerceProduct();
        $where = [new \FacturaScripts\Core\Where('active', '=', true)];

        $categoryId = $this->request->query->get('category', null);
        if ($categoryId !== null) {
            $this->selectedCategory = (int) $categoryId;
            $where[] = new \FacturaScripts\Core\Where('category_id', '=', $this->selectedCategory);
        }

        $this->products = $product->all($where, ['name' => 'ASC']);
    }

    private function loadCartItemCount(): void
    {
        $cartItem = new EcommerceCartItem();
        $where = [new \FacturaScripts\Core\Where('session_id', '=', $this->getSessionId())];
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
