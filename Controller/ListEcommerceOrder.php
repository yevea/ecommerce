<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListEcommerceOrder extends ListController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'ecommerce';
        $pageData['title'] = 'orders';
        $pageData['icon'] = 'fa-solid fa-shopping-bag';
        return $pageData;
    }

    protected function createViews($viewName = 'ListEcommerceOrder')
    {
        $this->addView($viewName, 'EcommerceOrder', 'orders', 'fa-solid fa-shopping-bag')
            ->addSearchFields(['code', 'customer_name', 'customer_email'])
            ->addFilterSelect('status', 'status', 'status')
            ->addOrderBy(['creation_date'], 'creation-date', 2)
            ->addOrderBy(['total'], 'total')
            ->addOrderBy(['code'], 'code');
    }
}
