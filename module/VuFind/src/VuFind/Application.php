<?php

/**
 * VuFind main application.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Bootstrap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Psr7Bridge\Psr7ServerRequest;
use Laminas\Router\Http\HttpRouteInterface;
use Laminas\Router\RouterFactory;
use Laminas\Router\RouteStackInterface;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\ActionDispatcher;
use VuFind\EventManager\Event;
//use VuFind\EventManager\EventManager;
//use VuFind\EventManager\EventManagerInterface;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * VuFind main application.
 *
 * @category VuFind
 * @package  Bootstrap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Application
{
    public const CHUNK_SIZE = 8192;

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
     * Constructor.
     *
     * @param ContainerInterface $container Service container
     * @param EventManagerInterface $eventManager Event manager
     * @param ActionDispatcher $actionDispatcher Action dispatcher
     */
    #[Autowire]
    public function __construct(
        protected ContainerInterface $container,
        protected EventManagerInterface $eventManager,
        protected ActionDispatcher $actionDispatcher,
    ) {
    }

    /**
     * Run the application.
     */
    public function run(): void
    {
        $this->request = $this->createServerRequest();
        $this->response = $this->createResponse();

        // Trigger bootstrap event:
        $event = (new Event())
            ->setRequest($this->request)
            ->setResponse($this->response);
        $this->eventManager->setEventPrototype($event);
        $this->eventManager->trigger(Event::EVENT_BOOTSTRAP);
        // Update with any changes from event listeners:
        $this->request = $event->getRequest();
        $this->response = $event->getResponse();

        $routeMatch = $this->container->get('HttpRouter')->match($this->request);
        $this->request = $this->request
            ->withAttribute('route-match', $routeMatch)
            ->withAttribute('view-model', $this->createViewModel());

        // Trigger dispatch or dispatch error event:
        $event = $event
            ->setRouteMatch($routeMatch);
        $this->eventManager->setEventPrototype($event);
        $this->eventManager->trigger($routeMatch ? Event::EVENT_DISPATCH : Event::EVENT_DISPATCH_ERROR);
        // Update with any changes from event listeners:
        $this->request = $event->getRequest();
        $this->response = $event->getResponse();
        $routeMatch = $event->getRouteMatch();
        $this->request = $this->request->withAttribute('route-match', $routeMatch);

        $this->response = $this->actionDispatcher->dispatch($this->request, $this->response);

        // Trigger finish event:
        $this->eventManager->trigger(Event::EVENT_FINISH);
        // Update with any changes from event listeners:
        $response = $event->getResponse();

        $this->outputResponse($response);
    }

    /**
     * Get the request.
     *
     * @return ?ServerRequestInterface
     */
    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Get the response.
     *
     * @return ?ResponseInterface
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * Create request from globals.
     *
     * @return ServerRequestInterface
     */
    protected function createServerRequest(): ServerRequestInterface
    {
        return ServerRequest::fromGlobals();
    }

    /**
     * Create a default response.
     *
     * @return ResponseInterface
     */
    protected function createResponse(): ResponseInterface
    {
        return new Response();
    }

    /**
     * Create a view model.
     *
     * @return ModelInterface
     */
    protected function createViewModel(): ModelInterface
    {
        return new ViewModel();
    }

    /**
     * Output a response.
     *
     * @param ResponseInterface $response Response
     *
     * @return void
     */
    protected function outputResponse(ResponseInterface $response): void
    {
        $this->outputHeaders($response);
        $this->outputBody($response);
    }

    /**
     * Output response headers.
     *
     * @param ResponseInterface $response Response
     *
     * @return void
     */
    protected function outputHeaders(ResponseInterface $response): void
    {
        // Don't do anything if headers have already been sent:
        if (headers_sent()) {
            return;
        }

        $status = 'HTTP/' . $response->getProtocolVersion() . ' ' . $response->getStatusCode();
        if ($reason = $response->getReasonPhrase()) {
            $status .= " $reason";
        }

        header((string) $status);

        foreach ($response->getHeaders() as $header => $value) {
            header("$header: $value");
        }
    }

    /**
     * Output response body.
     *
     * @param ResponseInterface $response Response
     *
     * @return void
     */
    protected function outputBody(ResponseInterface $response): void
    {
        $body = $response->getBody();
        $body->rewind();
        while (!$body->eof()) {
            echo $body->read(self::CHUNK_SIZE);
        }
    }
}
