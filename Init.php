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

use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Template\InitClass;

class Init extends InitClass
{
    public function init(): void
    {
    }

    public function update(): void
    {
        // clear menu page cache so new pages are discovered
        Cache::clear('model-Page-Show-Menu');
    }

    public function uninstall(): void
    {
    }
}
