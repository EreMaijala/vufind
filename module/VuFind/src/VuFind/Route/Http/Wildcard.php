<?php

/**
 * Resettable Wildcard route
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Route
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Route\Http;

use Laminas\Router\Exception\InvalidArgumentException;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function is_array;
use function sprintf;

/**
 * Resettable Wildcard route
 *
 * @category VuFind
 * @package  Route
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Wildcard extends \Laminas\Router\Http\Wildcard
{
    /**
     * Re-initialize the route
     *
     * @param iterable $options Route options
     *
     * @return static
     */
    public function reset($options = []): static
    {
        // Like parent's factory method apart from the `new`:
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects an array or Traversable set of options',
                __METHOD__
            ));
        }

        if (! isset($options['key_value_delimiter'])) {
            $options['key_value_delimiter'] = '/';
        }

        if (! isset($options['param_delimiter'])) {
            $options['param_delimiter'] = '/';
        }

        if (! isset($options['defaults'])) {
            $options['defaults'] = [];
        }

        parent::__construct($options['key_value_delimiter'], $options['param_delimiter'], $options['defaults']);

        return $this;
    }
}
