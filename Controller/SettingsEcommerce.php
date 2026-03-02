<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Controller\EditSettings;
use FacturaScripts\Core\Model\Settings;

class SettingsEcommerce extends EditSettings
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'settings-ecommerce';
        $data['icon'] = 'fa-solid fa-store';
        return $data;
    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        if ($viewName === 'SettingsEcommerce'
            && $view->model instanceof Settings
            && empty($view->model->name)) {
            $view->model->name = 'ecommerce';
        }
    }
}
