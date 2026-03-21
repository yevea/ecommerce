<?php
namespace FacturaScripts\Plugins\woodstore\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditTablonPrecio extends EditController
{
    public function getModelClassName(): string
    {
        return 'TablonPrecio';
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'warehouse';
        $pageData['title'] = 'tablon-precio';
        $pageData['icon'] = 'fa-solid fa-money-bill';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    protected function createViews()
    {
        $this->addEditView('EditTablonPrecio', 'TablonPrecio', 'tablon-precio', 'fa-solid fa-money-bill');
    }
}
