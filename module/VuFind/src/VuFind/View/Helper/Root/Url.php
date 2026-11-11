<?php

/**
 * Url view helper.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use InvalidArgumentException;
use Laminas\Router\RouteMatchInterface;
use Laminas\Router\RouteStackInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use VuFind\Application;
use VuFind\ServiceManager\Factory\Autowire;

use function func_num_args;

/**
 * Url view helper.
 *
 * Partially adapted from Laminas View v2 Url helper.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Url
{
    /**
     * Constructor.
     *
     * @param ?ServerRequestInterface $request Request object for GET parameters
     * @param RouteStackInterface $router Router,
     * @param ?RouteMatchInterface $routeMatch Route match returned by the router
     */
    public function __construct(
        protected Application $application,
        protected RouteStackInterface $router,
        protected ?RouteMatchInterface $routeMatch
    ) {
    }

    /**
     * Generates a url given the name of a route.
     *
     * @param string $name               Name of the route
     * @param array  $params             Parameters for the link
     * @param array  $options            Options for the route
     * @param bool   $reuseMatchedParams Whether to reuse matched parameters
     *
     * @see \Laminas\Router\RouteInterface::assemble()
     *
     * @throws RuntimeException If no RouteStackInterface or no RouteMatch was provided, or if RouteMatch is missing the
     * route name
     *
     * @return static|string This if no params were passed, or URL for the link href attribute
     */
    public function __invoke(
        ?string $name = null,
        array $params = [],
        array $options = [],
        bool $reuseMatchedParams = false
    ): static|string {
        // If argument list is empty, return object for method access:
        if (func_num_args() === 0) {
            return $this;
        }

        if (null === $this->router) {
            throw new RuntimeException('No RouteStackInterface instance provided');
        }

        if (3 === func_num_args() && is_bool($options)) {
            $reuseMatchedParams = $options;
            $options            = [];
        }

        if ($name === null) {
            if ($this->routeMatch === null) {
                throw new RuntimeException('No RouteMatch instance provided');
            }

            $name = $this->routeMatch->getMatchedRouteName();

            if ($name === '') {
                throw new RuntimeException('RouteMatch does not contain a matched route name');
            }
        }

        if ($reuseMatchedParams && $this->routeMatch !== null) {
            $routeMatchParams = $this->routeMatch->getParams();
            $params = array_merge($routeMatchParams, $params);
        }

        $options['name'] = $name;

        return $this->application->getBasePath() . $this->router->assemble($params, $options)->toString();
    }

    /**
     * Set the router to use for assembling.
     *
     * @param RouteStackInterface $router Router
     *
     * @return static
     */
    public function setRouter(RouteStackInterface $router): static
    {
        $this->router = $router;
        return $this;
    }

    /**
     * Set route match returned by the router.
     *
     * @param RouteMatchInterface $routeMatch Route match
     *
     * @return static
     */
    public function setRouteMatch(RouteMatchInterface $routeMatch): static
    {
        $this->routeMatch = $routeMatch;
        return $this;
    }

    /**
     * Get URL with current GET parameters and add one.
     *
     * @param array $params             Key-paired parameters
     * @param bool  $reuseMatchedParams Whether to reuse matched parameters
     *
     * @return string
     */
    public function addQueryParameters(array $params, bool $reuseMatchedParams = true): string
    {
        $requestQuery = $this->application->getRequest()?->getQueryParams() ?? [];
        $options = [
            'query' => array_merge($requestQuery, $params),
            'normalize_path' => false, // fix for VUFIND-1392
        ];
        // If we don't have a route match, direct any url's to default route:
        $routeName = $this->routeMatch ? null : 'default';
        return ($this)($routeName, [], $options, $reuseMatchedParams);
    }
}
