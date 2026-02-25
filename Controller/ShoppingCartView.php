<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Template\Controller;

/**
 * @deprecated Redirects to Presupuesto controller.
 */
class ShoppingCartView extends Controller
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function run(): void
    {
        parent::run();
        $this->redirect('Presupuesto');
    }
}
