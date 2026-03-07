<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Model\Familia;
use FacturaScripts\Core\Where;

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

        $this->view('Tableros.html.twig');
    }

    /**
     * Resolve a slug (from ?cat= parameter) to a codfamilia and set it
     * as the category query parameter so parent::run() filters products.
     */
    private function preResolveSlugToCategory(string $slug): void
    {
        $familia = new Familia();
        foreach ($familia->all([], ['descripcion' => 'ASC'], 0, 0) as $fam) {
            $familySlug = self::generateSlug($fam->descripcion);
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
            $slug = self::generateSlug($cat->descripcion);
            $this->slugMap[$cat->codfamilia] = $slug;
        }
    }

    protected function loadProducts(): void
    {
        parent::loadProducts();

        // Apply dimension filtering for Tablones categories
        if ($this->selectedCategoryType !== 'tablones') {
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

        $varianteClass = '\FacturaScripts\Core\Model\Variante';
        if (!class_exists($varianteClass)) {
            return;
        }

        // Batch-load all variants for the current product set in a single query
        $productIds = array_map(fn($p) => $p->idproducto, $this->products);
        if (empty($productIds)) {
            return;
        }

        $variante = new $varianteClass();
        $allVariants = $variante->all([Where::in('idproducto', $productIds)], [], 0, 0);

        // Group variants by idproducto and check dimension filters
        $matchingProductIds = [];
        foreach ($allVariants as $v) {
            if (isset($matchingProductIds[$v->idproducto])) {
                continue; // already matched
            }
            if ($this->variantMatchesDimensionFilters($v)) {
                $matchingProductIds[$v->idproducto] = true;
            }
        }

        $this->products = array_values(array_filter(
            $this->products,
            fn($p) => isset($matchingProductIds[$p->idproducto])
        ));
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
