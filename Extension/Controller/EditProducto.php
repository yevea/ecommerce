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
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;
use FacturaScripts\Dinamic\Model\ProductoImagen;

/**
 * Extension for EditProducto controller to fix observations on product images,
 * save short descriptions (alt text),
 * and hide dimension fields (largo, ancho, espesor) for non-tablones families.
 */
class EditProducto
{
    protected function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName !== 'EditVariante') {
                return;
            }

            $codfamilia = $this->getViewModelValue('EditProducto', 'codfamilia');
            if (empty($codfamilia)) {
                foreach (['largo', 'ancho', 'espesor'] as $col) {
                    $view->disableColumn($col);
                }
                return;
            }

            $familia = new Familia();
            if (!$familia->loadFromCode($codfamilia) || ($familia->tipofamilia ?? '') !== 'tablones') {
                foreach (['largo', 'ancho', 'espesor'] as $col) {
                    $view->disableColumn($col);
                }
            }
        };
    }

    protected function execAfterAction(): Closure
    {
        return function ($action) {
            if ($action === 'add-image') {
                $idproducto = (int)$this->request->input('idproducto');
                if ($idproducto <= 0) {
                    return;
                }

                $observations = $this->request->input('observations', '');
                $descripcionCorta = $this->request->input('descripcion_corta', '');

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

                    // Save observations and descripcion_corta on the ProductoImagen record
                    if ($imgModel !== null) {
                        $imgWhere = [Where::eq('idfile', $relation->idfile)];
                        foreach ($imgModel->all($imgWhere, [], 0, 0) as $img) {
                            if (!empty($observations)) {
                                $img->observaciones = $observations;
                            }
                            if (!empty($descripcionCorta)) {
                                $img->descripcion_corta = $descripcionCorta;
                            }
                            $img->save();
                        }
                    }
                }
            } elseif ($action === 'edit-image') {
                $idimage = (int)$this->request->input('idimage');
                if ($idimage <= 0) {
                    return;
                }

                $imgModel = new ProductoImagen();
                if (!$imgModel->loadFromCode($idimage)) {
                    return;
                }

                $referencia = $this->request->input('referencia', '');
                $imgModel->referencia = empty($referencia) ? null : $referencia;
                $imgModel->observaciones = $this->request->input('observaciones', '');
                $imgModel->descripcion_corta = $this->request->input('descripcion_corta', '');
                $imgModel->save();
            }
        };
    }
}
