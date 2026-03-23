<?php
namespace FacturaScripts\Plugins\WoodStore\Controller;

use FacturaScripts\Core\Controller\EditSettings;
use FacturaScripts\Core\Model\Settings;

class SettingsWoodstore extends EditSettings
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'settings-woodstore';
        $data['icon'] = 'fa-solid fa-store';
        return $data;
    }

    protected function execPreviousAction($action)
    {
        if ($action === 'insert'
            && $this->active === 'SettingsWoodstore'
            && isset($this->views[$this->active])
            && $this->views[$this->active]->model instanceof Settings
            && empty($this->views[$this->active]->model->name)) {
            $this->views[$this->active]->model->name = 'woodstore';
        }

        return parent::execPreviousAction($action);
    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        if ($viewName === 'SettingsWoodstore'
            && $view->model instanceof Settings
            && empty($view->model->name)) {
            $view->model->name = 'woodstore';
        }
    }
}
