<?php
namespace FacturaScripts\Plugins\ecommerce\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;

/**
 * Stores the Measurement Price Calculator configuration for a single product.
 *
 * Two pricing modes are supported (equivalent to WooCommerce Measurement Price Calculator):
 *  - per_measurement : Price is charged per unit of measurement. The customer enters
 *                      a measurement value and the total is (price × measurement_value).
 *  - quantity_based  : Each product unit covers a fixed measurement (unit_value). The
 *                      customer enters the total measurement they need and the system
 *                      calculates how many product units to order via ceil(needed / unit_value).
 */
class EcommerceProductMeasurement extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var string FK to productos.referencia */
    public $product_referencia;

    /** @var bool Whether the calculator is active for this product */
    public $measurement_enabled;

    /** @var string length|area|volume|weight|custom */
    public $measurement_type;

    /** @var string per_measurement|quantity_based */
    public $pricing_mode;

    /**
     * @var float
     * quantity_based only: how many measurement units one product unit covers.
     * E.g. 1 box = 2.5 m² → unit_value = 2.5
     */
    public $unit_value;

    /** @var string Abbreviation shown in the UI, e.g. m, cm, ft, m2, ft2, L, kg */
    public $measurement_unit;

    /** @var float Minimum purchasable measurement (0 = no minimum) */
    public $min_value;

    /** @var float Maximum purchasable measurement (0 = no maximum) */
    public $max_value;

    /** @var float Input step / increment (e.g. 0.01, 0.1, 1) */
    public $step_value;

    /** @var string Optional custom label for the measurement input field */
    public $custom_label;

    public static function primaryColumn(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'ecommerce_product_measurements';
    }

    public function clear(): void
    {
        parent::clear();
        $this->measurement_enabled = false;
        $this->measurement_type = 'area';
        $this->pricing_mode = 'per_measurement';
        $this->unit_value = 1.0;
        $this->measurement_unit = 'm2';
        $this->min_value = 0.0;
        $this->max_value = 0.0;
        $this->step_value = 0.01;
        $this->custom_label = '';
    }

    /**
     * Returns a human-readable measurement unit label.
     * Converts internal codes (m2, ft2) to display symbols (m², ft²).
     */
    public function unitLabel(): string
    {
        $map = [
            'm2' => 'm²',
            'ft2' => 'ft²',
            'cm2' => 'cm²',
            'm3' => 'm³',
            'ft3' => 'ft³',
        ];
        return $map[$this->measurement_unit] ?? $this->measurement_unit;
    }
}
