<?php
namespace FacturaScripts\Plugins\ecommerce\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class EcommerceProduct extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $reference;

    /** @var string */
    public $name;

    /** @var string */
    public $description;

    /** @var float */
    public $price;

    /** @var int */
    public $stock;

    /** @var int */
    public $category_id;

    /** @var bool */
    public $active;

    /** @var string */
    public $image;

    /** @var string */
    public $creation_date;

    /** @var string */
    public $modification_date;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'ecommerce_products';
    }

    public function clear(): void
    {
        parent::clear();
        $this->price = 0;
        $this->stock = 0;
        $this->active = true;
    }

    public function saveInsert(array $values = []): bool
    {
        $this->creation_date = Tools::dateTime();
        $this->modification_date = Tools::dateTime();
        return parent::saveInsert($values);
    }

    public function saveUpdate(array $values = []): bool
    {
        $this->modification_date = Tools::dateTime();
        return parent::saveUpdate($values);
    }
}
