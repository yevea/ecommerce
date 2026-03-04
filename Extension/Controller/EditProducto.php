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

namespace FacturaScripts\Plugins\ecommerce\Extension\Controller;

use Closure;
use FacturaScripts\Core\Model\Familia;
use FacturaScripts\Core\Model\Producto;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;
use FacturaScripts\Dinamic\Model\ProductoImagen;

/**
 * Extension for EditProducto controller to fix observations on product images
 * and auto-set nostock for Tableros family products.
 *
 * Fixes two issues:
 * 1. When images are uploaded via the Imágenes tab, their AttachedFileRelation records
 *    have modelid set but modelcode = null. This causes editFileAction() to fail the
 *    permission check when editing observations in the Archivos tab.
 * 2. There was no way to add observations when uploading a new image in the Imágenes tab.
 */
class EditProducto
{
    protected function execAfterAction(): Closure
    {
        return function ($action) {
            if ($action === 'add-image') {
                $this->fixImageFileRelations();
            }
            if (in_array($action, ['edit', 'insert'])) {
                $this->ensureTablerosNoStock();
            }
        };
    }

    /**
     * For products in a Tableros family, automatically set nostock = true.
     */
    protected function ensureTablerosNoStock(): Closure
    {
        return function () {
            $code = $this->request->query->get('code', '');
            if (empty($code)) {
                $code = $this->request->request->get('code', '');
            }
            if (empty($code)) {
                return;
            }

            $producto = new Producto();
            if (!$producto->loadFromCode($code)) {
                return;
            }

            if (empty($producto->codfamilia)) {
                return;
            }

            $familia = new Familia();
            if ($familia->loadFromCode($producto->codfamilia) && ($familia->tipofamilia ?? '') === 'tableros') {
                if (!$producto->nostock) {
                    $producto->nostock = true;
                    $producto->save();
                }
            }
        };
    }

    protected function fixImageFileRelations(): Closure
    {
        return function () {
            $idproducto = (int)$this->request->input('idproducto');
            if ($idproducto <= 0) {
                return;
            }

            $observations = $this->request->input('observations', '');

            // Find newly created file relations that have no modelcode yet (created by addImageAction)
            $fileRelation = new AttachedFileRelation();
            $imgModel = class_exists(ProductoImagen::class) ? new ProductoImagen() : null;
            $where = [
                Where::eq('model', 'Producto'),
                Where::eq('modelid', $idproducto),
                Where::isNull('modelcode'),
            ];
            foreach ($fileRelation->all($where, [], 0, 0) as $relation) {
                $relation->modelcode = (string)$idproducto;
                $relation->observations = $observations;
                $relation->save();

                // Also save observations directly on the ProductoImagen record
                if (!empty($observations) && $imgModel !== null) {
                    $imgWhere = [Where::eq('idfile', $relation->idfile)];
                    foreach ($imgModel->all($imgWhere, [], 0, 0) as $img) {
                        $img->observaciones = $observations;
                        $img->save();
                    }
                }
            }
        };
    }
}
