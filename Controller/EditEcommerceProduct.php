<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditEcommerceProduct extends EditController
{
    public function getModelClassName(): string
    {
        return 'EcommerceProduct';
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'ecommerce';
        $pageData['title'] = 'product';
        $pageData['icon'] = 'fa-solid fa-box';
        return $pageData;
    }

    protected function createViews()
    {
        $this->addEditView('EditEcommerceProduct', 'EcommerceProduct', 'product', 'fa-solid fa-box');
    }
}
