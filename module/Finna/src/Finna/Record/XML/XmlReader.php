<?php

/**
 * XML reader
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Record
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace Finna\Record\XML;

use Sabre\Xml\Service as XmlService;

/**
 * XML reader
 *
 * @category VuFind
 * @package  Record
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class XmlReader
{
    /**
     * Parsed XML
     *
     * @var array
     */
    protected array $parsed = [];

    /**
     * Default namespace URI for path parts without namespace
     *
     * @var ?string
     */
    protected ?string $defaultNamespace = null;

    /**
     * Parse an XML string.
     *
     * @param string $xml XML
     *
     * @return static
     */
    public function parse(string $xml): static
    {
        $this->parsed = (new XmlService())->parse($xml);
        return $this;
    }

    /**
     * Set default namespace for path queries.
     *
     * @param ?string $namespace Namespace URI, or null for no default
     *
     * @return static
     */
    public function setDefaultNamespace(?string $namespace): static
    {
        $this->defaultNamespace = $namespace;
        return $this;
    }

    /**
     * Get all nodes by path.
     *
     * @param string|array $path Path (array or a slash-delimited string) with each node either in Clark notation, or
     * just node name with $this->defaultNamespace defined
     *
     * @return array
     */
    public function all(string|array $path): array
    {
        return $this->allByPath($path);
    }

    /**
     * Get all node values by path.
     *
     * @param string|array $path Path (array or a slash-delimited string) with each node either in Clark notation, or
     * just node name with $this->defaultNamespace defined
     *
     * @return string[]
     */
    public function allValues(string|array $path): array
    {
        return $this->getValues($this->allByPath($path));
    }

    /**
     * Get first node by path.
     *
     * @param string|array $path Path (array or a slash-delimited string) with each node either in Clark notation, or
     * just node name with $this->defaultNamespace defined
     *
     * @return array
     */
    public function first(string|array $path): mixed
    {
        return $this->firstByPath($path);
    }

    /**
     * Get all nodes by path starting from the given single node.
     *
     * @param array        $node Node to start from
     * @param string|array $path Path (array or a slash-delimited string) with each node either in Clark notation, or
     * just node name with $this->defaultNamespace defined
     *
     * @return array
     */
    public function allFrom(array $node, string|array $path): array
    {
        if (!is_array($node['value'] ?? null)) {
            return [];
        }
        return $this->allByPath($path, $node['value']);
    }

    /**
     * Get all node values by path starting from the given single node.
     *
     * @param array        $node Node to start from
     * @param string|array $path Path (array or a slash-delimited string) with each node either in Clark notation, or
     * just node name with $this->defaultNamespace defined
     *
     * @return string[]
     */
    public function allValuesFrom(array $node, string|array $path): array
    {
        return $this->getValues($this->allFrom($node, $path));
    }

    /**
     * Get first node by path starting from the given single node.
     *
     * @param array        $node Node to start from
     * @param string|array $path Path (array or a slash-delimited string) with each node either in Clark notation, or
     * just node name with $this->defaultNamespace defined
     *
     * @return array
     */
    public function firstFrom(array $node, string|array $path): mixed
    {
        return $this->allFrom($node, $path)[0] ?? null;
    }

    /**
     * Get first node value as string by path starting from the given single node.
     *
     * @param array        $node Node to start from
     * @param string|array $path Path (array or a slash-delimited string) with each node either in Clark notation, or
     * just node name with $this->defaultNamespace defined
     *
     * @return string
     */
    public function firstValueFrom(array $node, string|array $path): string
    {
        $first = $this->firstFrom($node, $path);
        return is_string($first['value'] ?? null) ? $first['value'] : '';
    }

    /**
     * Get attribute from a node
     *
     * @param array  $node      Node
     * @param string $attribute Attribute either in Clark notation, or just name with $this->defaultNamespace defined
     */
    public function attr(array $node, string $attr): ?string
    {
        return $node['attributes'][$this->clarkify($attr)] ?? null;
    }

    /**
     * Get the string value of a node
     *
     * @param array $node Node
     */
    public function value(array $node): string
    {
        return (string)$node['value'];
    }

    /**
     * Recursively traverse all branches by path and return any values found.
     *
     * @param string|array $path Path (array or a slash-delimited string) with each node either in Clark notation
     *                           just node name with $this->defaultNamespace defined
     * @param ?array       $root Node to start from
     *
     * @return array
     */
    protected function allByPath(string|array $path, ?array $root = null): array
    {
        $currentNodes = $root ?? $this->parsed;
        $remainingPath = is_array($path) ? $path : explode('/', $path);
        $pathPart = array_shift($remainingPath);

        // Verify that the path part has namespace:
        $pathPart = $this->clarkify($pathPart);

        // Try to find nodes first with namespace and fall back to search without namespace:
        foreach ([false, true] as $fallback) {
            if ($fallback) {
                $clark = XmlService::parseClarkNotation($pathPart);
                $pathPart = '{}' . $clark[1];
            }
            $result = null;
            foreach ($currentNodes as $node) {
                if ($pathPart === $node['name']) {
                    if ($remainingPath) {
                        if (is_array($node['value'])) {
                            $result = [
                                ...($result ?? []),
                                ...$this->allByPath($remainingPath, $node['value']),
                            ];
                        }
                    } else {
                        $result[] = $node;
                    }
                }
            }
            if (null !== $result) {
                return $result;
            }
        }

        return [];

        $remainingPath = is_array($path) ? $path : explode('/', $path);
        $current = $root ?? $this->parsed;
        $result = [];
        while ($remainingPath) {
            $part = array_shift($remainingPath);
            if (!str_starts_with($part, '{')) {
                if (null === $this->defaultNamespace) {
                    throw new \InvalidArgumentException(
                        "'$part' must use Clark notation, or default namespace must be defined"
                    );
                }
                $part = '{' . $this->defaultNamespace . '}' . $part;
            }
            if ($part === $current['name']) {
                $current = $current['value'];
            } else {
                // Fallback: try with default namespace:
                if ($this->toDefaultNamespace($part) !== $current['name']) {
                    return [];
                }
                $current = $current['value'];
            }
            foreach ($current as $item) {
                if ($res = $this->allByPath($remainingPath, $item)) {
                    $result = [
                        ...$result,
                        ...$res,
                    ];
                }
            }
        }
        return is_array($current) ? $current : [$current];
    }

    /**
     * Return first value found by traversing the path.
     *
     * @param string|array $path Path (array or a slash-delimited string) with each node either in Clark notation
     *                           just node name with $this->defaultNamespace defined
     * @param ?array       $root Node to start from
     *
     * @return mixed
     */
    protected function firstByPath(string|array $path, ?array $root = null): mixed
    {
        $result = $this->allByPath($path, $root);
        return $result[0] ?? null;
    }

    /**
     * Get values from an array of nodes
     *
     * @param array $nodes Nodes
     *
     * @return array
     */
    protected function getValues(array $nodes): array
    {
        return array_map(
            function ($node) {
                return (string)$node['value'];
            },
            $nodes
        );
    }

    /**
     * Ensure a node or attribute name is in Clark notation
     *
     * @param string $name Name
     *
     * @return string
     */
    protected function clarkify(string $name): string
    {
        // Assume correct notation if it starts with a curly bracket:
        if (str_starts_with($name, '{')) {
            return $name;
        }
        if (null === $this->defaultNamespace) {
            throw new \InvalidArgumentException(
                "'$name' must use Clark notation, or default namespace must be defined"
            );
        }
        return '{' . $this->defaultNamespace . '}' . $name;
    }

    /**
     * Convert any Clark notation name to default namespace
     *
     * @param string $name Name
     *
     * @return string
     */
    protected function toDefaultNamespace(string $name): string
    {
        $clark = XmlService::parseClarkNotation($name);
        return '{}' . $clark[1];
    }
}
