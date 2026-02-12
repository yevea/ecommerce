<?php
namespace FacturaScripts\Plugins\ecommerce\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class EcommerceCartItem extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $session_id;

    /** @var int */
    public $product_id;

    /** @var int */
    public $quantity;

    /** @var string */
    public $creation_date;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'ecommerce_cart_items';
    }

    public function clear(): void
    {
        parent::clear();
        $this->quantity = 1;
    }

    protected function saveInsert(): bool
    {
        $this->creation_date = Tools::dateTime();
        return parent::saveInsert();
    }
}
