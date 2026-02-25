<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Model\Producto;
use FacturaScripts\Core\Tools;

class ProductoDetalle extends StoreFront
{
    /** @var object|null */
    public $product = null;

    /** @var array */
    public $productImages = [];

    /** @var array */
    public $productVariants = [];

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'product-detail';
        return $pageData;
    }

    public function run(): void
    {
        parent::run();

        $referencia = $this->request()->query->get('ref', '');
        if (!empty($referencia)) {
            $this->loadProduct($referencia);
        }

        $this->view('ProductoDetalle.html.twig');
    }

    private function loadProduct(string $referencia): void
    {
        $p = new Producto();
        if (!$p->loadFromCode($referencia) || !$p->publico) {
            return;
        }

        $this->product = (object) [
            'referencia' => $p->referencia,
            'name' => $p->descripcion,
            'description' => $p->observaciones ?? '',
            'price' => $p->precio,
            'stock' => $p->stockfis,
            'image' => $p->imagen ?? null,
        ];

        $this->loadProductImages($p);
        $this->loadProductVariants($p);
    }

    private function loadProductImages(Producto $p): void
    {
        $this->productImages = [];

        // Try loading from ProductoImagen model if available
        $modelClass = '\FacturaScripts\Core\Model\ProductoImagen';
        if (class_exists($modelClass)) {
            $imgModel = new $modelClass();
            $where = [new \FacturaScripts\Core\Where('referencia', $p->referencia)];
            $images = $imgModel->all($where, ['orden' => 'ASC']);
            foreach ($images as $img) {
                $this->productImages[] = (object) [
                    'url' => $img->imagen ?? null,
                    'alt' => $p->descripcion,
                ];
            }
        }

        // Fall back to the main imagen field on the Producto model
        if (empty($this->productImages) && !empty($p->imagen)) {
            $this->productImages[] = (object) [
                'url' => $p->imagen,
                'alt' => $p->descripcion,
            ];
        }
    }

    private function loadProductVariants(Producto $p): void
    {
        $this->productVariants = [];

        // Try loading product combinations/variants if model is available
        $modelClass = '\FacturaScripts\Core\Model\ProductoCombinacion';
        if (class_exists($modelClass)) {
            $combModel = new $modelClass();
            $where = [new \FacturaScripts\Core\Where('referencia', $p->referencia)];
            $combinations = $combModel->all($where, ['descripcion' => 'ASC']);
            foreach ($combinations as $comb) {
                $this->productVariants[] = (object) [
                    'referencia' => $comb->referencia,
                    'description' => $comb->descripcion ?? '',
                    'price' => $comb->precio ?? $p->precio,
                    'stock' => $comb->stockfis ?? 0,
                ];
            }
        }
    }
}
