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

use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;

class Init extends InitClass
{
    public function init(): void
    {
        $this->loadExtension(new Extension\Controller\EditProducto());
        $this->loadExtension(new Extension\Controller\EditFamilia());
    }

    public function update(): void
    {
        $this->fixProductImageFileRelations();
    }

    public function uninstall(): void
    {
    }

    /**
     * Fix AttachedFileRelation records for product images that have modelid set
     * but modelcode = null. This allows editFileAction() to correctly validate
     * these records when editing observations in the Archivos tab.
     */
    private function fixProductImageFileRelations(): void
    {
        $fileRelation = new AttachedFileRelation();
        $where = [
            Where::eq('model', 'Producto'),
            Where::isNull('modelcode'),
        ];
        foreach ($fileRelation->all($where, [], 0, 0) as $relation) {
            if ($relation->modelid > 0) {
                $relation->modelcode = (string)$relation->modelid;
                $relation->save();
            }
        }
    }
}
