<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

class Productos extends StoreFront
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'products';
        return $pageData;
    }
}
