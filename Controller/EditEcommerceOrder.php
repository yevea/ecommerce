<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

class EditEcommerceOrder extends EditController
{
    public function getModelClassName(): string
    {
        return 'EcommerceOrder';
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'ecommerce';
        $pageData['title'] = 'order';
        $pageData['icon'] = 'fa-solid fa-shopping-bag';
        return $pageData;
    }

    protected function createViews()
    {
        $this->addEditView('EditEcommerceOrder', 'EcommerceOrder', 'order', 'fa-solid fa-shopping-bag');
        $this->addEditListView('EditEcommerceOrderLine', 'EcommerceOrderLine', 'order-lines', 'fa-solid fa-list');
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditEcommerceOrderLine':
                $order_id = $this->getViewModelValue('EditEcommerceOrder', 'id');
                $where = [new \FacturaScripts\Core\Where('order_id', '=', $order_id)];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
