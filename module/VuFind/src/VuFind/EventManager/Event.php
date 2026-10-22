<?php

/**
 * Event.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  EventManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\EventManager;

use Laminas\Router\RouteMatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Event.
 *
 * @category VuFind
 * @package  EventManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Event implements EventInterface
{
    /**
     * Bootstrap event.
     *
     * @var string
     */
    public const EVENT_BOOTSTRAP = 'bootstrap';

    /**
     * Dispatch event.
     *
     * @var string
     */
    public const EVENT_DISPATCH = 'dispatch';

    /**
     * Dispatch error event.
     *
     * @var string
     */
    public const EVENT_DISPATCH_ERROR = 'dispatch.error';

    /**
     * Finish event.
     *
     * @var string
     */
    public const EVENT_FINISH = 'finish';

    /**
     * Event name.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * Propagation stopped flag.
     *
     * @var bool
     */
    protected bool $propagationStopped = false;

    /**
     * Request.
     *
     * @var ?ServerRequestInterface
     */
    protected ?ServerRequestInterface $request = null;

    /**
     * Response.
     *
     * @var ?ResponseInterface
     */
    protected ?ResponseInterface $response = null;

    /**
     * Route match.
     *
     * @var ?RouteMatch
     */
    protected ?RouteMatch $routeMatch = null;

    /**
     * Parameters.
     *
     * @var array
     */
    protected array $params = [];

    /**
     * Get event name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set event name.
     *
     * @param string $name Name
     *
     * @return static
     */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get propagation stopped flag.
     *
     * @return bool
     */
    public function getPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Set propagation stopped flag.
     *
     * @param bool $value New value
     *
     * @return static
     */
    public function setPropagationStopped(bool $value): static
    {
        $this->propagationStopped = $value;
        return $this;
    }

    /**
     * Get request.
     *
     * @return ?ServerRequestInterface
     */
    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Set request.
     *
     * @param ?ServerRequestInterface $request Request
     *
     * @return static
     */
    public function setRequest(?ServerRequestInterface $request): static
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Get response.
     *
     * @return ?ResponseInterface
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * Set response.
     *
     * @param ?ResponseInterface $response Response
     *
     * @return static
     */
    public function setResponse(?ResponseInterface $response): static
    {
        $this->response = $response;
        return $this;
    }

    /**
     * Get route match.
     *
     * @return ?RouteMatch
     */
    public function getRouteMatch(): ?RouteMatch
    {
        return $this->routeMatch;
    }

    /**
     * Set route match.
     *
     * @param ?RouteMatch $routeMatch Route match
     *
     * @return static
     */
    public function setRouteMatch(?RouteMatch $routeMatch): static
    {
        $this->routeMatch = $routeMatch;
        return $this;
    }

    /**
     * Get parameters.
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Set parameters.
     *
     * @param array $params New parameters
     *
     * @return static
     */
    public function setParams(array $params): static
    {
        $this->params = $params;
        return $this;
    }
}
