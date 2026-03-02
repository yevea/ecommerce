<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListEcommerceProductMeasurement extends ListController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'ecommerce';
        $pageData['title'] = 'measurement-calculator';
        $pageData['icon'] = 'fa-solid fa-ruler-combined';
        return $pageData;
    }

    protected function createViews($viewName = 'ListEcommerceProductMeasurement')
    {
        $this->addView($viewName, 'EcommerceProductMeasurement', 'measurement-calculator', 'fa-solid fa-ruler-combined')
            ->addSearchFields(['product_referencia', 'custom_label'])
            ->addFilterCheckbox('measurement_enabled', 'measurement-enabled', 'measurement_enabled')
            ->addFilterSelect('pricing_mode', 'pricing-mode', 'pricing_mode', [
                ['code' => 'per_measurement', 'description' => 'per-measurement'],
                ['code' => 'quantity_based', 'description' => 'quantity-based'],
            ])
            ->addOrderBy(['product_referencia'], 'reference', 1);
    }
}
