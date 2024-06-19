<?php

/**
 * EscapeHtmlAttr view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\Escaper\Escaper;
use Laminas\View\Helper\Escaper\AbstractHelper;

/**
 * EscapeHtmlAttr view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EscapeHtmlAttr extends AbstractHelper
{
    /**
     * Constructor
     *
     * @param Escaper $escaper         Escaper
     * @param bool    $useNormalEscape Whether to use normal escaping instead of escapeHtmlAttr
     */
    public function __construct(
        Escaper $escaper,
        protected bool $useNormalEscape
    ) {
        // Base class has an untyped escaper, so set it here:
        $this->escaper = $escaper;
    }

    /**
     * Escape given value
     *
     * @param string $value Value
     *
     * @return string
     */
    protected function escape($value)
    {
        return $this->useNormalEscape
            ? $this->escaper->escapeHtml($value)
            : $this->escaper->escapeHtmlAttr($value);
    }
}
