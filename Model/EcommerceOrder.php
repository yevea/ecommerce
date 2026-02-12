<?php
namespace FacturaScripts\Plugins\ecommerce\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class EcommerceOrder extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $code;

    /** @var string */
    public $customer_name;

    /** @var string */
    public $customer_email;

    /** @var string */
    public $address;

    /** @var string */
    public $status;

    /** @var float */
    public $total;

    /** @var string */
    public $notes;

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
        return 'ecommerce_orders';
    }

    public function clear(): void
    {
        parent::clear();
        $this->status = 'pending';
        $this->total = 0;
    }

    protected function saveInsert(): bool
    {
        if (empty($this->code)) {
            $this->code = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
        }
        $this->creation_date = Tools::dateTime();
        $this->modification_date = Tools::dateTime();
        return parent::saveInsert();
    }

    protected function saveUpdate(): bool
    {
        $this->modification_date = Tools::dateTime();
        return parent::saveUpdate();
    }
}
