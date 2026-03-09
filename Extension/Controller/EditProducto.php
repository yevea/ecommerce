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
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;
use FacturaScripts\Dinamic\Model\ProductoImagen;

/**
 * Extension for EditProducto controller to fix observations on product images,
 * save short descriptions (alt text) and rename uploaded files for SEO,
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
                $this->fixImageFileRelations();
            } elseif ($action === 'edit-image') {
                $this->editImageData();
            }
        };
    }

    /**
     * Update editable fields on an existing product image: variant, observations,
     * short description (alt text) and optionally rename the file.
     */
    protected function editImageData(): Closure
    {
        return function () {
            $idimage = (int) $this->request->input('idimage');
            if ($idimage <= 0) {
                return;
            }

            $imgModel = new ProductoImagen();
            if (false === $imgModel->loadFromCode($idimage)) {
                return;
            }

            // Update variant reference
            $referencia = $this->request->input('referencia', '');
            $imgModel->referencia = empty($referencia) ? null : $referencia;

            // Update observations and short description
            $imgModel->observaciones = $this->request->input('observations', '');
            $imgModel->descripcion_corta = $this->request->input('descripcion_corta', '');
            $imgModel->save();

            // Sync observations to the AttachedFileRelation
            $fileRelation = new AttachedFileRelation();
            $where = [
                Where::eq('model', 'Producto'),
                Where::eq('idfile', $imgModel->idfile),
            ];
            foreach ($fileRelation->all($where, [], 0, 1) as $relation) {
                $relation->observations = $imgModel->observaciones;
                $relation->save();
            }

            // Rename the physical file if a new name was provided
            $nombreArchivo = trim($this->request->input('nombre_archivo', ''));
            if (!empty($nombreArchivo)) {
                // Strip any extension the user may have typed
                $nombreArchivo = pathinfo($nombreArchivo, PATHINFO_FILENAME);
                if (!empty($nombreArchivo)) {
                    $this->renameAttachedFile($imgModel->idfile, $nombreArchivo);
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
            $descripcionCorta = $this->request->input('descripcion_corta', '');
            $nombreArchivo = trim($this->request->input('nombre_archivo', ''));

            // Find newly created file relations that have no modelcode yet (created by addImageAction)
            $fileRelation = new AttachedFileRelation();
            $imgModel = class_exists(ProductoImagen::class) ? new ProductoImagen() : null;
            $where = [
                Where::eq('model', 'Producto'),
                Where::eq('modelid', $idproducto),
                Where::isNull('modelcode'),
            ];
            $counter = 0;
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

                // Rename the uploaded file if a new name was provided
                if (!empty($nombreArchivo)) {
                    $this->renameAttachedFile($relation->idfile, $nombreArchivo, $counter);
                    $counter++;
                }
            }
        };
    }

    /**
     * Rename an attached file to a keyword-rich SEO-friendly name.
     * Uses a direct DB update to avoid triggering onChange('path') in AttachedFile
     * which would try to re-process the file and corrupt the path.
     */
    protected function renameAttachedFile(): Closure
    {
        return function (int $idfile, string $newName, int $counter = 0) {
            $attachedFile = new AttachedFile();
            if (!$attachedFile->loadFromCode($idfile)) {
                return;
            }

            // Sanitize the new name: lowercase, replace spaces with hyphens, remove special chars
            $newName = strtolower(trim($newName));
            $newName = str_replace(' ', '-', $newName);
            $newName = preg_replace('/[^a-z0-9\-]/', '', $newName);
            $newName = preg_replace('/-+/', '-', $newName);
            $newName = trim($newName, '-');

            if (empty($newName)) {
                return;
            }

            // Get the original extension from the current path
            $currentPath = $attachedFile->path;
            $extension = pathinfo($currentPath, PATHINFO_EXTENSION);
            $directory = pathinfo($currentPath, PATHINFO_DIRNAME);

            // Add counter suffix for multiple files
            $baseName = $counter > 0 ? $newName . '-' . ($counter + 1) : $newName;
            $targetFilename = $baseName . '.' . $extension;
            $targetPath = $directory . '/' . $targetFilename;

            // Check if target path is already taken
            $fullTargetPath = FS_FOLDER . '/' . $targetPath;
            if (file_exists($fullTargetPath)) {
                // Try with incrementing suffix
                for ($i = 2; $i <= 100; $i++) {
                    $targetFilename = $baseName . '-' . $i . '.' . $extension;
                    $targetPath = $directory . '/' . $targetFilename;
                    $fullTargetPath = FS_FOLDER . '/' . $targetPath;
                    if (!file_exists($fullTargetPath)) {
                        break;
                    }
                }
                // If still exists after 100 attempts, skip rename
                if (file_exists($fullTargetPath)) {
                    Tools::log()->warning('Could not rename file to ' . $baseName . ': all name variants taken');
                    return;
                }
            }

            // Rename the physical file
            $fullCurrentPath = FS_FOLDER . '/' . $currentPath;
            if (!file_exists($fullCurrentPath)) {
                return;
            }

            if (rename($fullCurrentPath, $fullTargetPath)) {
                // Use direct DB update to avoid triggering onChange('path') in AttachedFile,
                // which would try to re-process/move the file and corrupt the path.
                AttachedFile::table()
                    ->whereEq('idfile', $idfile)
                    ->update(['path' => $targetPath, 'filename' => $targetFilename]);
            }
        };
    }
}
