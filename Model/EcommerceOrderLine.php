<?php
namespace FacturaScripts\Plugins\ecommerce\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;

class EcommerceOrderLine extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var int */
    public $order_id;

    /** @var int */
    public $product_id;

    /** @var string */
    public $product_name;

    /** @var int */
    public $quantity;

    /** @var float */
    public $price;

    /** @var float */
    public $subtotal;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'ecommerce_order_lines';
    }

    public function clear(): void
    {
        parent::clear();
        $this->quantity = 1;
        $this->price = 0;
        $this->subtotal = 0;
    }
}
