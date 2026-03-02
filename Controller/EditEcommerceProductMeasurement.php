<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditEcommerceProductMeasurement extends EditController
{
    public function getModelClassName(): string
    {
        return 'EcommerceProductMeasurement';
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'ecommerce';
        $pageData['title'] = 'measurement-calculator';
        $pageData['icon'] = 'fa-solid fa-ruler-combined';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    protected function createViews()
    {
        $this->addEditView(
            'EditEcommerceProductMeasurement',
            'EcommerceProductMeasurement',
            'measurement-calculator',
            'fa-solid fa-ruler-combined'
        );
    }
}
