<?php
namespace FacturaScripts\Plugins\woodstore\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Where;

class EditWoodstoreOrder extends EditController
{
    public function getModelClassName(): string
    {
        return 'WoodstoreOrder';
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'woodstore';
        $pageData['title'] = 'order';
        $pageData['icon'] = 'fa-solid fa-shopping-bag';
        return $pageData;
    }

    protected function createViews()
    {
        $this->addEditView('EditWoodstoreOrder', 'WoodstoreOrder', 'order', 'fa-solid fa-shopping-bag');
        $this->addEditListView('EditWoodstoreOrderLine', 'WoodstoreOrderLine', 'order-lines', 'fa-solid fa-list');
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditWoodstoreOrderLine':
                $order_id = $this->getViewModelValue('EditWoodstoreOrder', 'id');
                $where = [Where::eq('order_id', $order_id)];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
