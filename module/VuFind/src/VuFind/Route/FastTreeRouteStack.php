<?php

/**
 * Route Stack Class
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

namespace VuFind\Route;

use Laminas\Router\Exception\InvalidArgumentException;
use Laminas\Router\Exception\RuntimeException;
use Laminas\Router\Http\RouteMatch;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\RequestInterface as Request;
use Laminas\Uri\Http as HttpUri;
use Traversable;

use function is_array;
use function is_string;
use function sprintf;
use function strlen;

/**
 * Route Stack Class
 *
 * A fast router that supports the required functionality from TreeRouteStack without
 * creating a lot of objects on each execution.
 *
 * @template TRoute of array|object
 *
 * @category VuFind
 * @package  Route
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FastTreeRouteStack extends TreeRouteStack
{
    /**
     * Route class aliases
     *
     * @var array
     */
    protected $routeAliases = [
        'chain'    => Http\Chain::class,
        'Chain'    => Http\Chain::class,
        \Laminas\Router\Http\Chain::class => Http\Chain::class,
        'hostname' => Http\Hostname::class,
        'Hostname' => Http\Hostname::class,
        'hostName' => Http\Hostname::class,
        'HostName' => Http\Hostname::class,
        \Laminas\Router\Http\Hostname::class => Http\Hostname::class,
        'literal'  => Http\Literal::class,
        'Literal'  => Http\Literal::class,
        \Laminas\Router\Http\Literal::class => Http\Literal::class,
        'method'   => Http\Method::class,
        'Method'   => Http\Method::class,
        \Laminas\Router\Http\Method::class => Http\Method::class,
        'part'     => Http\Part::class,
        'Part'     => Http\Part::class,
        \Laminas\Router\Http\Part::class => Http\Part::class,
        'regex'    => Http\Regex::class,
        'Regex'    => Http\Regex::class,
        \Laminas\Router\Http\Regex::class => Http\Regex::class,
        'scheme'   => Http\Scheme::class,
        'Scheme'   => Http\Scheme::class,
        \Laminas\Router\Http\Scheme::class => Http\Scheme::class,
        'segment'  => Http\Segment::class,
        'Segment'  => Http\Segment::class,
        \Laminas\Router\Http\Segment::class => Http\Segment::class,
        'wildcard' => Http\Wildcard::class,
        'Wildcard' => Http\Wildcard::class,
        'wildCard' => Http\Wildcard::class,
        'WildCard' => Http\Wildcard::class,
        \Laminas\Router\Http\Wildcard::class => Http\Wildcard::class,
    ];

    /**
     * Cache of route type implementations
     *
     * @var RouteInterface[]
     */
    protected $routeImpls = [];

    /**
     * Cache of assembled routes
     *
     * @var string[]
     */
    protected $assemblyCache = [];

    /**
     * Add a route
     *
     * @param string          $name     Name
     * @param string|iterable $route    Route
     * @param int             $priority Priority
     *
     * @return $this
     */
    public function addRoute($name, $route, $priority = null)
    {
        $route = $this->routeFromArray($route);
        return parent::addRoute($name, $route, $priority);
    }

    /**
     * Create a route from array specifications.
     *
     * @param string|iterable $specs Route options
     *
     * @return TRoute
     *
     * @throws InvalidArgumentException When route definition is not an array nor traversable.
     * @throws InvalidArgumentException When chain routes are not an array nor traversable.
     * @throws RuntimeException         When a generated routes does not implement the HTTP route interface.
     */
    protected function routeFromArray($specs)
    {
        if (is_string($specs)) {
            if (null === ($route = $this->getPrototype($specs))) {
                throw new RuntimeException(sprintf('Could not find prototype with name %s', $specs));
            }

            return $route;
        } elseif ($specs instanceof Traversable) {
            $specs = ArrayUtils::iteratorToArray($specs);
        } elseif (!is_array($specs)) {
            throw new InvalidArgumentException('Route definition must be an array or Traversable object');
        }

        if (isset($specs['chain_routes'])) {
            if (! is_array($specs['chain_routes'])) {
                throw new InvalidArgumentException('Chain routes must be an array or Traversable object');
            }

            $chainRoutes = array_merge([$specs], $specs['chain_routes']);
            unset($chainRoutes[0]['chain_routes']);

            if (isset($specs['child_routes'])) {
                unset($chainRoutes[0]['child_routes']);
            }

            $options = [
                'routes' => $chainRoutes,
                'route_plugins' => $this->routePluginManager,
                'prototypes' => $this->prototypes,
            ];

            $route = [
                'type' => 'chain',
                'options' => $options,
            ];
        } else {
            $route = $specs;
        }

        if (isset($specs['child_routes'])) {
            // TODO: can we support child routes without instantiating the route objects?
            return [
                'type' => 'part',
                'mainRouteType' => $specs['type'],
                'mainRoute' => $specs['options']['route'] ?? null,
                'route' => parent::routeFromArray($specs),
            ];
        }

        return $route;
    }

    /**
     * Function match(): defined by \Laminas\Router\RouteInterface
     *
     * @param Request $request    Request
     * @param ?int    $pathOffset Path offset
     * @param array   $options    Options
     *
     * @return ?RouteMatch
     */
    public function match(Request $request, $pathOffset = null, array $options = [])
    {
        if (! method_exists($request, 'getUri')) {
            return null;
        }

        if ($this->baseUrl === null && method_exists($request, 'getBaseUrl')) {
            $this->setBaseUrl($request->getBaseUrl());
        }

        $uri           = $request->getUri();
        $baseUrlLength = strlen((string)$this->baseUrl) ?: null;

        if ($pathOffset !== null) {
            $baseUrlLength += $pathOffset;
        }

        if ($this->requestUri === null) {
            $this->setRequestUri($uri);
        }

        if ($baseUrlLength !== null) {
            $pathLength = strlen((string)$uri->getPath()) - $baseUrlLength;
        } else {
            $pathLength = null;
        }

        $relPath = null !== $baseUrlLength ? substr($uri->getPath(), $baseUrlLength) : $uri->getPath();

        foreach ($this->routes as $name => $routeSettings) {
            $type = $this->routeAliases[$routeSettings['type'] ?? ''] ?? null;
            // Shortcut for the segment route match:
            if (
                Http\Segment::class === $type
                && ($path = $routeSettings['options']['route'] ?? null)
            ) {
                $p = strpos($path, '[') ?: PHP_INT_MAX;
                $p2 = strpos($path, ':') ?: PHP_INT_MAX;
                if (strncmp($relPath, $path, min($p, $p2)) !== 0) {
                    continue;
                }
            }
            if (
                Http\Literal::class === $type
                && ($path = $routeSettings['options']['route'] ?? null)
            ) {
                if ($path !== $relPath) {
                    continue;
                }
            }
            if (Http\Part::class === $type) {
                if (($this->routeAliases[$routeSettings['mainRouteType']] ?? null) === Http\Literal::class) {
                    $path = $routeSettings['mainRoute'];
                    $p = strpos($path, '[') ?: PHP_INT_MAX;
                    $p2 = strpos($path, ':') ?: PHP_INT_MAX;
                    if (strncmp($relPath, $path, min($p, $p2)) !== 0) {
                        continue;
                    }
                }
            }
            $route = $this->getRouteImpl((array)$routeSettings);
            if (
                ($match = $route->match($request, $baseUrlLength, $options)) instanceof RouteMatch
                && ($pathLength === null || $match->getLength() === $pathLength)
            ) {
                $match->setMatchedRouteName($name);

                foreach ($this->defaultParams as $paramName => $value) {
                    if ($match->getParam($paramName) === null) {
                        $match->setParam($paramName, $value);
                    }
                }

                return $match;
            }
        }

        return null;
    }

    /**
     * Function assemble(): defined by \Laminas\Router\RouteInterface interface.
     *
     * @param array $params  Params
     * @param array $options Options
     *
     * @return mixed
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     *
     * @see \Laminas\Router\RouteInterface::assemble()
     */
    public function assemble(array $params = [], array $options = [])
    {
        $key = md5(var_export($params, true) . var_export($options, true));
        if ($path = $this->assemblyCache[$key] ?? null) {
            return $path;
        }
        if (!isset($options['name'])) {
            throw new InvalidArgumentException('Missing "name" option');
        }
        $names = explode('/', $options['name'], 2);
        $route = $this->routes->get($names[0]);

        if (!$route) {
            throw new RuntimeException(sprintf('Route with name "%s" not found', $names[0]));
        }

        if (isset($names[1])) {
            if (!isset($route['child_routes'])) {
                throw new RuntimeException(sprintf(
                    'Route with name "%s" does not have child routes',
                    $names[0]
                ));
            }
            $options['name'] = $names[1];
        } else {
            unset($options['name']);
        }

        if (isset($options['only_return_path']) && $options['only_return_path']) {
            return $this->baseUrl . $this->getRouteImpl((array)$route)
                ->assemble(array_merge($this->defaultParams, $params), $options);
        }

        if (! isset($options['uri'])) {
            $uri = new HttpUri();

            if (isset($options['force_canonical']) && $options['force_canonical']) {
                if ($this->requestUri === null) {
                    throw new RuntimeException('Request URI has not been set');
                }

                $uri->setScheme($this->requestUri->getScheme())
                    ->setHost($this->requestUri->getHost())
                    ->setPort($this->requestUri->getPort());
            }

            $options['uri'] = $uri;
        } else {
            $uri = $options['uri'];
        }

        $path = $this->baseUrl . $this->getRouteImpl((array)$route)
            ->assemble(array_merge($this->defaultParams, $params), $options);

        if (isset($options['query'])) {
            $uri->setQuery($options['query']);
        }

        if (isset($options['fragment'])) {
            $uri->setFragment($options['fragment']);
        }

        if (
            (isset($options['force_canonical'])
            && $options['force_canonical'])
            || $uri->getHost() !== null
            || $uri->getScheme() !== null
        ) {
            if (($uri->getHost() === null || $uri->getScheme() === null) && $this->requestUri === null) {
                throw new RuntimeException('Request URI has not been set');
            }

            if ($uri->getHost() === null) {
                $uri->setHost($this->requestUri->getHost());
            }

            if ($uri->getScheme() === null) {
                $uri->setScheme($this->requestUri->getScheme());
            }

            $uri->setPath($path);

            if (! isset($options['normalize_path']) || $options['normalize_path']) {
                $uri->normalize();
            }

            return $uri->toString();
        } elseif (! $uri->isAbsolute() && $uri->isValidRelative()) {
            $uri->setPath($path);

            if (! isset($options['normalize_path']) || $options['normalize_path']) {
                $uri->normalize();
            }

            $this->assemblyCache[$key] = $uri->toString();
            return $uri->toString();
        }

        $this->assemblyCache[$key] = $path;
        return $path;
    }

    /**
     * Get route object for a route specification
     *
     * @param array $route Route specification
     *
     * @return object Route object
     */
    protected function getRouteImpl(array $route): object
    {
        $type = $route['type'];

        if (null === ($class = $this->routeAliases[$type])) {
            throw new \Exception("Unknown route class $type");
        }

        // Part is a special case and has been pre-built for each chain:
        if (Http\Part::class === $class) {
            return $route['route'];
        }

        if (!isset($this->routeImpls[$type])) {
            return $this->routeImpls[$type] = $class::factory($route['options'] ?? []);
        }

        return $this->routeImpls[$type]->reset($route['options'] ?? []);
    }
}
