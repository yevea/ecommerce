<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Where;

class EditEcommerceCategory extends EditController
{
    public function getModelClassName(): string
    {
        return 'EcommerceCategory';
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'ecommerce';
        $pageData['title'] = 'category';
        $pageData['icon'] = 'fa-solid fa-tag';
        return $pageData;
    }

    protected function createViews()
    {
        $this->addEditView('EditEcommerceCategory', 'EcommerceCategory', 'category', 'fa-solid fa-tag');
        $this->addEditListView('EditEcommerceCategoryProducts', 'EcommerceProduct', 'products', 'fa-solid fa-box');
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditEcommerceCategoryProducts':
                $category_id = $this->getViewModelValue('EditEcommerceCategory', 'id');
                $where = [Where::eq('category_id', $category_id)];
                $view->loadData('', $where);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
