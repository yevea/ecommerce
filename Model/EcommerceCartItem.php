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

    /** @var string */
    public $product_referencia;

    /** @var int */
    public $quantity;

    /** @var string */
    public $creation_date;

    /** @var float|null Measurement entered by the customer (null = no measurement) */
    public $measurement_value;

    /** @var string|null Unit abbreviation matching the product measurement config */
    public $measurement_unit;

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

    public function saveInsert(array $values = []): bool
    {
        $this->creation_date = Tools::dateTime();
        return parent::saveInsert($values);
    }
}
