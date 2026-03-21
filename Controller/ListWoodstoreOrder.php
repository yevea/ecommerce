<?php
namespace FacturaScripts\Plugins\WoodStore\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListWoodstoreOrder extends ListController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'woodstore';
        $pageData['title'] = 'orders';
        $pageData['icon'] = 'fa-solid fa-shopping-bag';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    protected function createViews($viewName = 'ListWoodstoreOrder')
    {
        $this->addView($viewName, 'WoodstoreOrder', 'orders', 'fa-solid fa-shopping-bag')
            ->addSearchFields(['code', 'customer_name', 'customer_email'])
            ->addFilterSelect('status', 'status', 'status', [
                ['code' => 'pending', 'description' => 'pending'],
                ['code' => 'processing', 'description' => 'processing'],
                ['code' => 'completed', 'description' => 'completed'],
                ['code' => 'cancelled', 'description' => 'cancelled'],
            ])
            ->addOrderBy(['creation_date'], 'creation-date', 2)
            ->addOrderBy(['total'], 'total')
            ->addOrderBy(['code'], 'code');
    }
}
