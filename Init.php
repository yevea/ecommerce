<?php
/**
 * This file is part of ecommerce plugin for FacturaScripts.
 * Copyright (C) 2024 FacturaScripts Community
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Plugins\ecommerce;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Template\InitClass;

class Init extends InitClass
{
    public function init(): void
    {
    }

    public function update(): void
    {
        $this->migrateCartTable();
        $this->dropOldTables();

        // clear menu page cache so new pages are discovered
        Cache::clear('model-Page-Show-Menu');
    }

    public function uninstall(): void
    {
    }

    /**
     * If the old ecommerce_cart_items table has the product_id column
     * (from v1.x referencing ecommerce_products), drop the table so
     * it gets recreated with the new schema (idproducto → productos).
     * Cart items are ephemeral session data, so no data loss matters.
     */
    private function migrateCartTable(): void
    {
        $db = new DataBase();
        if (false === $db->tableExists('ecommerce_cart_items')) {
            return;
        }

        $columns = $db->getColumns('ecommerce_cart_items');
        if (isset($columns['product_id'])) {
            $db->exec('DROP TABLE ecommerce_cart_items;');
        }
    }

    /**
     * Drop old v1.x tables that are no longer used.
     * Order matters due to foreign key constraints.
     */
    private function dropOldTables(): void
    {
        $db = new DataBase();
        $oldTables = [
            'ecommerce_order_lines',
            'ecommerce_orders',
            'ecommerce_products',
            'ecommerce_categories',
        ];

        foreach ($oldTables as $table) {
            if ($db->tableExists($table)) {
                $db->exec('DROP TABLE `' . $table . '`;');
            }
        }
    }
}
