<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Controller\EditSettings;

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
}
