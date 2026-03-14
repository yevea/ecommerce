<?php
namespace FacturaScripts\Plugins\ecommerce\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;

class TablonPrecio extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string */
    public $tipo_madera;

    /** @var string */
    public $tipo_tablon;

    /** @var float */
    public $espesor;

    /** @var float */
    public $precio_m2;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'tipo_madera';
    }

    public static function tableName(): string
    {
        return 'tablon_precios';
    }

    public function clear(): void
    {
        parent::clear();
        $this->precio_m2 = 0;
    }

    public function test(): bool
    {
        $this->tipo_madera = trim($this->tipo_madera ?? '');
        $this->tipo_tablon = trim($this->tipo_tablon ?? '');

        if (empty($this->tipo_madera)) {
            return false;
        }
        if (empty($this->tipo_tablon)) {
            return false;
        }
        if ($this->espesor <= 0) {
            return false;
        }
        if ($this->precio_m2 <= 0) {
            return false;
        }

        return parent::test();
    }
}
