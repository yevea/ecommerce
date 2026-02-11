<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListEcommerceCategory extends ListController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'ecommerce';
        $pageData['title'] = 'categories';
        $pageData['icon'] = 'fa-solid fa-tags';
        return $pageData;
    }

    protected function createViews($viewName = 'ListEcommerceCategory')
    {
        $this->addView($viewName, 'EcommerceCategory', 'categories', 'fa-solid fa-tags')
            ->addSearchFields(['name', 'description'])
            ->addFilterCheckbox('active', 'active', 'active', '=', true)
            ->addOrderBy(['name'], 'name')
            ->addOrderBy(['creation_date'], 'creation-date', 2);
    }
}
