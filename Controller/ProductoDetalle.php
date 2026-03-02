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

    /** @var array Map of idvariante => array of image objects (for JS-driven variant image switching) */
    public $variantImages = [];

    /** @var array */
    public $productVariants = [];

    /** @var array Attribute groups: [codatributo => ['nombre' => string, 'values' => [id => valor]]] */
    public $productAttributes = [];

    /** @var object|null First/default variant data for initial display */
    public $defaultVariant = null;

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'product-detail';
        return $pageData;
    }

    public function run(): void
    {
        // Disable automatic rendering so we can load the product before rendering.
        $this->autoRenderView = false;
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
        $where = [new \FacturaScripts\Core\Where('referencia', $referencia)];
        if (!$p->loadWhere($where) || !$p->publico) {
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
        $this->variantImages = [];

        // Try loading from ProductoImagen model if available
        $modelClass = '\FacturaScripts\Core\Model\ProductoImagen';
        if (class_exists($modelClass)) {
            // Build a map: referencia -> idvariante from the Variante table
            $refToIdvariante = [];
            $varianteClass = '\FacturaScripts\Core\Model\Variante';
            if (class_exists($varianteClass)) {
                $varianteModel = new $varianteClass();
                $varWhere = [new \FacturaScripts\Core\Where('idproducto', $p->idproducto)];
                foreach ($varianteModel->all($varWhere, [], 0, 0) as $v) {
                    $refToIdvariante[$v->referencia] = $v->idvariante;
                }
            }

            $imgModel = new $modelClass();
            $where = [new \FacturaScripts\Core\Where('idproducto', $p->idproducto)];
            $images = $imgModel->all($where, ['orden' => 'ASC']);
            foreach ($images as $img) {
                $idvariante = null;
                if (!empty($img->referencia) && isset($refToIdvariante[$img->referencia])) {
                    $idvariante = (int) $refToIdvariante[$img->referencia];
                }
                $imgObj = (object) [
                    'url' => $img->url('download-permanent'),
                    'alt' => $p->descripcion,
                    'description' => $img->observaciones ?? '',
                    'idvariante' => $idvariante,
                ];
                $this->productImages[] = $imgObj;
                if (!empty($idvariante)) {
                    $this->variantImages[$idvariante][] = $imgObj;
                }
            }
        }

        // Fall back to the main imagen field on the Producto model
        if (empty($this->productImages) && !empty($p->imagen)) {
            $this->productImages[] = (object) [
                'url' => $p->imagen,
                'alt' => $p->descripcion,
                'description' => '',
                'idvariante' => null,
            ];
        }
    }

    private function loadProductVariants(Producto $p): void
    {
        $this->productVariants = [];
        $this->productAttributes = [];
        $this->defaultVariant = null;

        $varianteClass = '\FacturaScripts\Core\Model\Variante';
        $attrValClass = '\FacturaScripts\Core\Model\AtributoValor';
        if (!class_exists($varianteClass)) {
            return;
        }

        $variante = new $varianteClass();
        $where = [new \FacturaScripts\Core\Where('idproducto', $p->idproducto)];
        $variants = $variante->all($where, ['referencia' => 'ASC']);

        // Single-variant product: no selector needed
        if (count($variants) <= 1) {
            return;
        }

        $attrValueCache = []; // id => ['codatributo' => ..., 'nombre' => ..., 'valor' => ...]
        $attrGroups = []; // codatributo => ['nombre' => ..., 'values' => [id => valor]]

        foreach ($variants as $v) {
            $attrMap = []; // codatributo => idatributovalor (int)

            foreach ([$v->idatributovalor1, $v->idatributovalor2, $v->idatributovalor3, $v->idatributovalor4] as $idAttrVal) {
                if (empty($idAttrVal)) {
                    continue;
                }

                if (!isset($attrValueCache[$idAttrVal]) && class_exists($attrValClass)) {
                    $attrValModel = new $attrValClass();
                    if ($attrValModel->loadFromCode($idAttrVal)) {
                        $atributo = $attrValModel->getAtributo();
                        $attrValueCache[$idAttrVal] = [
                            'codatributo' => $attrValModel->codatributo,
                            'nombre' => $atributo->nombre,
                            'valor' => $attrValModel->valor,
                        ];
                        if (!isset($attrGroups[$attrValModel->codatributo])) {
                            $attrGroups[$attrValModel->codatributo] = [
                                'nombre' => $atributo->nombre,
                                'values' => [],
                            ];
                        }
                        if (!isset($attrGroups[$attrValModel->codatributo]['values'][$idAttrVal])) {
                            $attrGroups[$attrValModel->codatributo]['values'][$idAttrVal] = $attrValModel->valor;
                        }
                    }
                }

                if (isset($attrValueCache[$idAttrVal])) {
                    $attrMap[$attrValueCache[$idAttrVal]['codatributo']] = $idAttrVal;
                }
            }

            // Determine a human-readable description for the simple dropdown fallback
            $desc = '';
            if (method_exists($v, 'description')) {
                $desc = $v->description(true);
            }
            if (empty($desc)) {
                $desc = $v->referencia;
            }

            $variantObj = (object) [
                'referencia' => $v->referencia,
                'idvariante' => $v->idvariante ?? null,
                'description' => $desc,
                'price' => $v->precio,
                'stock' => $v->stockfis,
                'attributes' => $attrMap,
            ];

            $this->productVariants[] = $variantObj;

            // Use the variant matching the parent product referencia as the default
            if ($v->referencia === $p->referencia && $this->defaultVariant === null) {
                $this->defaultVariant = $variantObj;
            }
        }

        // Fall back to the first variant as the default
        if ($this->defaultVariant === null && !empty($this->productVariants)) {
            $this->defaultVariant = $this->productVariants[0];
        }

        $this->productAttributes = $attrGroups;
    }
}
