<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

class Tableros extends StoreFront
{
    /** @var array Dimension filter values for Tablones */
    public $dimensionFilters = [];

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

        parent::run();
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
