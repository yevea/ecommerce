<?php
namespace FacturaScripts\Plugins\ecommerce\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Plugins\ecommerce\Model\EcommerceProduct;

class ImportEcommerceProduct extends Controller
{
    private const PRODUCTO_CLASS = '\\FacturaScripts\\Dinamic\\Model\\Producto';

    /** @var array */
    public $warehouseProducts = [];

    /** @var string */
    public $query = '';

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'ecommerce';
        $pageData['title'] = 'import-products';
        $pageData['icon'] = 'fa-solid fa-download';
        return $pageData;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $action = $this->request->request->get('action', '');
        if ($action === 'import' && $this->validateFormToken()) {
            $this->importProduct();
        }

        $this->query = $this->request->get('query', '');
        $this->loadWarehouseProducts();
    }

    private function importProduct(): void
    {
        $idproducto = (int) $this->request->request->get('idproducto', 0);
        if ($idproducto <= 0) {
            return;
        }

        $existing = new EcommerceProduct();
        if ($existing->loadWhere([Where::eq('idproducto', $idproducto)])) {
            Tools::log()->warning('product-already-imported');
            return;
        }

        $productoClass = self::PRODUCTO_CLASS;
        if (!class_exists($productoClass)) {
            Tools::log()->error('warehouse-model-not-found');
            return;
        }

        $producto = $productoClass::find($idproducto);
        if (null === $producto) {
            Tools::log()->error('record-not-found');
            return;
        }

        $ecomProduct = new EcommerceProduct();
        $ecomProduct->idproducto = $idproducto;
        $ecomProduct->reference = $producto->referencia;
        $ecomProduct->name = $producto->descripcion ?: $producto->referencia;
        $ecomProduct->description = $producto->observaciones ?? '';
        $ecomProduct->price = (float) $producto->precio;
        $ecomProduct->stock = (int) $producto->stockfis;
        $ecomProduct->active = (bool) $producto->sevende;
        $ecomProduct->visibility = 'public';

        if ($ecomProduct->save()) {
            Tools::log()->notice('product-imported-successfully');
            $this->redirect('EditEcommerceProduct?code=' . $ecomProduct->id);
        } else {
            Tools::log()->error('product-import-failed');
        }
    }

    private function loadWarehouseProducts(): void
    {
        $productoClass = self::PRODUCTO_CLASS;
        if (!class_exists($productoClass)) {
            return;
        }

        $where = [];
        if (!empty($this->query)) {
            $where[] = new \FacturaScripts\Core\Base\DataBase\DataBaseWhere(
                'referencia|descripcion', $this->query, 'LIKE'
            );
        }

        $allProducts = $productoClass::all($where, ['referencia' => 'ASC'], 0, 50);

        $ecomProduct = new EcommerceProduct();
        $imported = $ecomProduct->all([], [], 0, 0);
        $importedMap = [];
        foreach ($imported as $ep) {
            if ($ep->idproducto) {
                $importedMap[$ep->idproducto] = $ep->id;
            }
        }

        $this->warehouseProducts = [];
        foreach ($allProducts as $p) {
            $this->warehouseProducts[] = (object) [
                'idproducto' => $p->idproducto,
                'referencia' => $p->referencia,
                'descripcion' => $p->descripcion,
                'precio' => $p->precio,
                'stockfis' => $p->stockfis,
                'sevende' => $p->sevende,
                'secompra' => $p->secompra,
                'imported' => isset($importedMap[$p->idproducto]),
                'ecommerce_id' => $importedMap[$p->idproducto] ?? null,
            ];
        }
    }

    public function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' €';
    }
}
