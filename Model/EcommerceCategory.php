<?php
namespace FacturaScripts\Plugins\ecommerce\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class EcommerceCategory extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $name;

    /** @var string */
    public $description;

    /** @var bool */
    public $active;

    /** @var string */
    public $creation_date;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'ecommerce_categories';
    }

    public function clear(): void
    {
        parent::clear();
        $this->active = true;
    }

    public function saveInsert(array $values = []): bool
    {
        $this->creation_date = Tools::dateTime();
        return parent::saveInsert($values);
    }
}
