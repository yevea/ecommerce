<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListEcommerceProduct extends ListController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'ecommerce';
        $pageData['title'] = 'products';
        $pageData['icon'] = 'fa-solid fa-box';
        return $pageData;
    }

    protected function createViews($viewName = 'ListEcommerceProduct')
    {
        $this->addView($viewName, 'EcommerceProduct', 'products', 'fa-solid fa-box')
            ->addSearchFields(['name', 'reference', 'description'])
            ->addFilterCheckbox('active', 'active', 'active', '=', true)
            ->addFilterSelect('category_id', 'category', 'category_id')
            ->addOrderBy(['name'], 'name')
            ->addOrderBy(['price'], 'price')
            ->addOrderBy(['stock'], 'stock')
            ->addOrderBy(['creation_date'], 'creation-date', 2);
    }
}
