<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Model\Familia;

class Tableros extends StoreFront
{
    /** @var array Dimension filter values for Tablones */
    public $dimensionFilters = [];

    /** @var string|null Current category slug (e.g. "TablerosMesa") */
    public $categorySlug = null;

    /** @var array Map of codfamilia => slug for all categories */
    public $slugMap = [];

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'tableros';
        return $pageData;
    }

    public function run(): void
    {
        // Capture dimension filter parameters before parent::run() processes products
        $this->dimensionFilters = [
            'largo_min' => $this->request()->query->get('largo_min', ''),
            'largo_max' => $this->request()->query->get('largo_max', ''),
            'ancho_min' => $this->request()->query->get('ancho_min', ''),
            'ancho_max' => $this->request()->query->get('ancho_max', ''),
            'espesor_min' => $this->request()->query->get('espesor_min', ''),
            'espesor_max' => $this->request()->query->get('espesor_max', ''),
        ];

        // Pre-resolve slug-based category parameter (?cat=SlugName)
        // into the standard ?category= parameter before parent::run()
        $catSlug = $this->request()->query->get('cat', null);
        if ($catSlug !== null) {
            $this->preResolveSlugToCategory($catSlug);
        }

        // Disable auto-rendering so we can select the template
        $this->autoRenderView = false;
        parent::run();

        // Build slug maps from the loaded categories
        $this->buildSlugMaps();

        // Set the current category slug
        if ($this->selectedCategory !== null) {
            $this->categorySlug = $this->slugMap[$this->selectedCategory] ?? null;
        }

        // Ensure per-category template files exist
        $this->ensureAllCategoryTemplates();

        // Render: use per-category template if a category is selected, else base template
        $template = 'Tableros.html.twig';
        if ($this->categorySlug !== null) {
            $categoryTemplate = 'Tableros/' . $this->categorySlug . '.html.twig';
            $templatePath = $this->getCategoryTemplatePath($this->categorySlug);
            if (file_exists($templatePath)) {
                $this->deployCategoryTemplate($this->categorySlug);
                $template = $categoryTemplate;
            }
        }
        $this->view($template);
    }

    /**
     * Resolve a slug (from ?cat= parameter) to a codfamilia and set it
     * as the category query parameter so parent::run() filters products.
     */
    private function preResolveSlugToCategory(string $slug): void
    {
        $familia = new Familia();
        foreach ($familia->all([], ['descripcion' => 'ASC']) as $fam) {
            $familySlug = parent::generateSlug($fam->descripcion);
            if ($familySlug === $slug) {
                $this->request()->query->set('category', $fam->codfamilia);
                break;
            }
        }
    }

    /**
     * Build maps: codfamilia => slug for URL generation in templates.
     */
    private function buildSlugMaps(): void
    {
        $this->slugMap = [];
        foreach ($this->categories as $cat) {
            $slug = parent::generateSlug($cat->descripcion);
            $this->slugMap[$cat->codfamilia] = $slug;
        }
    }

    /**
     * Ensure a Twig template file exists for every loaded category.
     * Creates the View/Tableros/ directory and per-category files as needed.
     */
    private function ensureAllCategoryTemplates(): void
    {
        foreach ($this->categories as $cat) {
            $slug = $this->slugMap[$cat->codfamilia] ?? parent::generateSlug($cat->descripcion);
            $path = $this->getCategoryTemplatePath($slug);
            if (!file_exists($path)) {
                self::createCategoryTemplate($slug, $cat->descripcion);
            }
        }
    }

    /**
     * Deploy a category template from the plugin View directory to the
     * Dinamic/View directory so the Twig loader picks up the latest content.
     * This ensures that edits to files in Plugins/ecommerce/View/Tableros/
     * are reflected on the rendered page.
     */
    private function deployCategoryTemplate(string $slug): void
    {
        $sourcePath = $this->getCategoryTemplatePath($slug);
        if (!file_exists($sourcePath)) {
            return;
        }

        $dinamicDir = self::ensureDinamicViewDir();
        if ($dinamicDir === null) {
            return;
        }

        $dinamicPath = $dinamicDir . '/' . $slug . '.html.twig';

        if (!file_exists($dinamicPath)) {
            copy($sourcePath, $dinamicPath);
            return;
        }

        // Clear PHP's stat cache to ensure we get fresh file modification times
        clearstatcache(true, $sourcePath);
        clearstatcache(true, $dinamicPath);

        // Copy if the source file is newer or the file contents differ
        if (filemtime($sourcePath) > filemtime($dinamicPath)
            || filesize($sourcePath) !== filesize($dinamicPath)
            || md5_file($sourcePath) !== md5_file($dinamicPath)) {
            copy($sourcePath, $dinamicPath);
        }
    }

    /**
     * Ensure the Dinamic/View/Tableros directory exists and return its path.
     * Returns null if the directory could not be created.
     */
    private static function ensureDinamicViewDir(): ?string
    {
        $dinamicDir = FS_FOLDER . '/Dinamic/View/Tableros';
        if (!is_dir($dinamicDir) && !mkdir($dinamicDir, 0750, true)) {
            return null;
        }
        return $dinamicDir;
    }

    /**
     * Get the filesystem path for a category template.
     */
    private function getCategoryTemplatePath(string $slug): string
    {
        return self::getCategoryTemplateDir() . '/' . $slug . '.html.twig';
    }

    /**
     * Get the directory where category templates are stored.
     */
    public static function getCategoryTemplateDir(): string
    {
        return FS_FOLDER . '/Plugins/ecommerce/View/Tableros';
    }

    /**
     * Create a per-category Twig template file.
     * The template extends Tableros.html.twig and provides override blocks
     * that the user can edit to customize each category's appearance.
     */
    public static function createCategoryTemplate(string $slug, string $categoryName): bool
    {
        if (empty($slug)) {
            return false;
        }

        $dir = self::getCategoryTemplateDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $path = $dir . '/' . $slug . '.html.twig';
        if (file_exists($path)) {
            return false;
        }

        $safeName = htmlspecialchars($categoryName, ENT_QUOTES);

        $content = <<<TWIG
{#
    ---------------------------------------------------------------
    Category page: $safeName
    ---------------------------------------------------------------
    Edit this file to customize the appearance of this category.
    You can override the following blocks:

    category_custom_css  - Add custom CSS styles for this category
    category_header      - Customize the page title area
    category_intro       - Add custom HTML before the product grid
    category_outro       - Add custom HTML after the product grid

    The product grid, navigation bar and dimension filters are
    inherited automatically from the base Tableros template.
    ---------------------------------------------------------------
#}
{% extends "Tableros.html.twig" %}

{# -- Uncomment and edit any block below to customize this page -- #}

{#
{% block category_custom_css %}
<style>
    /* Custom styles for $safeName */
</style>
{% endblock %}
#}

{#
{% block category_intro %}
<div class="mb-4">
    <p>Custom introduction for $safeName.</p>
</div>
{% endblock %}
#}

{#
{% block category_outro %}
<div class="mt-4">
    <p>Additional content for $safeName.</p>
</div>
{% endblock %}
#}
TWIG;

        $result = (bool) file_put_contents($path, $content);

        // Deploy to Dinamic/View so the Twig loader can find it immediately
        if ($result) {
            $dinamicDir = self::ensureDinamicViewDir();
            if ($dinamicDir !== null) {
                copy($path, $dinamicDir . '/' . $slug . '.html.twig');
            }
        }

        return $result;
    }

    protected function loadProducts(): void
    {
        parent::loadProducts();

        // Apply dimension filtering for Tablones categories
        if ($this->selectedCategoryType !== 'tablones') {
            return;
        }

        $varianteClass = '\FacturaScripts\Core\Model\Variante';
        if (!class_exists($varianteClass)) {
            return;
        }

        $hasFilter = false;
        foreach ($this->dimensionFilters as $val) {
            if ($val !== '') {
                $hasFilter = true;
                break;
            }
        }

        if (!$hasFilter) {
            return;
        }

        $filtered = [];
        foreach ($this->products as $product) {
            // Check if any variant of this product matches the dimension filters
            $variante = new $varianteClass();
            $varWhere = [new \FacturaScripts\Core\Where('idproducto', $product->idproducto)];
            $variants = $variante->all($varWhere);

            $matches = false;
            foreach ($variants as $v) {
                if ($this->variantMatchesDimensionFilters($v)) {
                    $matches = true;
                    break;
                }
            }

            if ($matches) {
                $filtered[] = $product;
            }
        }

        $this->products = $filtered;
    }

    private function variantMatchesDimensionFilters(object $variant): bool
    {
        $filters = $this->dimensionFilters;

        if ($filters['largo_min'] !== '' && ($variant->largo === null || $variant->largo < (float) $filters['largo_min'])) {
            return false;
        }
        if ($filters['largo_max'] !== '' && ($variant->largo === null || $variant->largo > (float) $filters['largo_max'])) {
            return false;
        }
        if ($filters['ancho_min'] !== '' && ($variant->ancho === null || $variant->ancho < (float) $filters['ancho_min'])) {
            return false;
        }
        if ($filters['ancho_max'] !== '' && ($variant->ancho === null || $variant->ancho > (float) $filters['ancho_max'])) {
            return false;
        }
        if ($filters['espesor_min'] !== '' && ($variant->espesor === null || $variant->espesor < (float) $filters['espesor_min'])) {
            return false;
        }
        if ($filters['espesor_max'] !== '' && ($variant->espesor === null || $variant->espesor > (float) $filters['espesor_max'])) {
            return false;
        }

        return true;
    }
}
