<?php
namespace FacturaScripts\Plugins\woodstore\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

class ListTablonPrecio extends ListController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'warehouse';
        $pageData['title'] = 'tablon-precios';
        $pageData['icon'] = 'fa-solid fa-money-bill';
        return $pageData;
    }

    protected function createViews($viewName = 'ListTablonPrecio')
    {
        $this->addView($viewName, 'TablonPrecio', 'tablon-precios', 'fa-solid fa-money-bill')
            ->addSearchFields(['tipo_madera', 'tipo_tablon'])
            ->addOrderBy(['tipo_madera', 'tipo_tablon', 'espesor'], 'tipo-madera')
            ->addOrderBy(['precio_m2'], 'precio-m2')
            ->addOrderBy(['espesor'], 'espesor');
    }
}
