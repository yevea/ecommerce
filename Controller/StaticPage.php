<?php
namespace FacturaScripts\Plugins\WoodStore\Controller;

use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Template\Controller;
use FacturaScripts\Plugins\WoodStore\Lib\LanguageTrait;
use FacturaScripts\Plugins\WoodStore\Lib\SlugTrait;

class StaticPage extends Controller
{
    use LanguageTrait;
    use SlugTrait;

    protected $requiresAuth = false;

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'woodstore';
        $pageData['title'] = 'static-page';
        $pageData['icon'] = 'fa-solid fa-file';
        $pageData['showonmenu'] = false;
        return $pageData;
    }

    public function run(): void
    {
        parent::run();
        $this->detectAndSetLanguage();

        $cssPath = FS_FOLDER . '/Plugins/WoodStore/Assets/CSS/woodstore.css';
        if (file_exists($cssPath)) {
            AssetManager::addCss(FS_ROUTE . '/Plugins/WoodStore/Assets/CSS/woodstore.css');
        }

        $this->view($this->controllerName() . '.html.twig');
    }
}
