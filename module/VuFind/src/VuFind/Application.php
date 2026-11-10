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
use VuFind\Http\RouteHelper;
use VuFind\Http\ServerUrlHelper;
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
     * Base path of the request.
     *
     * @var ?string
     */
    protected ?string $basePath = null;

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

        // Try to find a route match for the request:
        $basePath = $this->getBasePath();
        /*$router = $this->container->get('HttpRouter');
        $router->*/
        $routeMatch = $this->container->get('HttpRouter')->match($this->request, strlen($basePath));
        $this->request = $this->request
            ->withAttribute('route-match', $routeMatch)
            ->withAttribute('view-model', $this->createViewModel());

        // Trigger dispatch or dispatch error event:
        $event->setRouteMatch($routeMatch);
        $this->eventManager->setEventPrototype($event);
        $this->eventManager->trigger($routeMatch ? Event::EVENT_DISPATCH : Event::EVENT_DISPATCH_ERROR);
        // Update with any changes from event listeners:
        $this->request = $event->getRequest();
        $this->response = $event->getResponse();
        $routeMatch = $event->getRouteMatch();
        $this->request = $this->request->withAttribute('route-match', $routeMatch);

        $this->response = $this->actionDispatcher->dispatch($this->request, $this->response);

        // Trigger finish event:
        $event->setResponse($this->response);
        $this->eventManager->trigger(Event::EVENT_FINISH);
        // Update with any changes from event listeners:
        $this->response = $event->getResponse();

        $this->outputResponse($this->response);
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
     * Get the base path of the request.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        if (null === $this->basePath) {
            $this->basePath = $this->detectBasePath($this->request);
        }
        return $this->basePath;
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

        foreach ($response->getHeaders() as $header => $values) {
            foreach ($values as $value) {
                header("$header: $value");
            }
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

    /**
     * Auto-detect the base path from the request environment.
     *
     * Uses a variety of criteria in order to detect the base URL of the request (i.e., anything additional to the
     * document root).
     *
     * Adapted from Laminas Request class.
     *
     * @return string
     */
    protected function detectBasePath(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        $filename       = $serverParams['SCRIPT_FILENAME'] ?? '';
        $scriptName     = $serverParams['SCRIPT_NAME'] ?? null;
        $phpSelf        = $serverParams['PHP_SELF'] ?? null;
        $origScriptName = $serverParams['ORIG_SCRIPT_NAME'] ?? null;

        if ($scriptName !== null && basename($scriptName) === $filename) {
            $baseUrl = $scriptName;
        } elseif ($phpSelf !== null && basename($phpSelf) === $filename) {
            $baseUrl = $phpSelf;
        } elseif ($origScriptName !== null && basename($origScriptName) === $filename) {
            // 1and1 shared hosting compatibility.
            $baseUrl = $origScriptName;
        } else {
            // Backtrack up the SCRIPT_FILENAME to find the portion matching PHP_SELF:
            $baseUrl  = '/';
            $basename = basename($filename ?? '');
            if ($basename) {
                $path     = $phpSelf ? trim($phpSelf, '/') : '';
                $basePos  = strpos($path, $basename) ?: 0;
                $baseUrl .= substr($path, 0, $basePos) . $basename;
            }
        }

        // If the baseUrl is empty, then simply return an empty string:
        if (!$baseUrl) {
            return '';
        }

        // Does the base URL have anything in common with the request URI?
        $requestUri = $this->detectRequestUri($request);

        // Full base URL matches.
        if (0 === strpos($requestUri, $baseUrl)) {
            return $baseUrl;
        }

        // Directory portion of base path matches.
        $baseDir = str_replace('\\', '/', dirname($baseUrl));
        if (0 === strpos($requestUri, $baseDir)) {
            return $baseDir;
        }

        $truncatedRequestUri = $requestUri;

        if (false !== ($pos = strpos($requestUri, '?'))) {
            $truncatedRequestUri = substr($requestUri, 0, $pos);
        }

        $basename = basename($baseUrl);

        // No match whatsoever
        if (empty($basename) || false === strpos($truncatedRequestUri, $basename)) {
            return '';
        }

        // If using mod_rewrite or ISAPI_Rewrite strip the script filename out of the base path. $pos !== 0 makes sure
        // it is not matching a value from PATH_INFO or QUERY_STRING.
        if (
            strlen($requestUri) >= strlen($baseUrl)
            && (false !== ($pos = strpos($requestUri, $baseUrl)) && $pos !== 0)
        ) {
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }

        return $baseUrl;
    }

    /**
     * Detect the base URI for the request
     *
     * Looks at a variety of criteria in order to attempt to autodetect a base
     * URI, including rewrite URIs, proxy URIs, etc.
     *
     * Adapted from Laminas Request class.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return string
     */
    protected function detectRequestUri(ServerRequestInterface $request): string
    {
        $requestUri = null;
        $serverParams = $request->getServerParams();

        // IIS7 with URL Rewrite: make sure we get the unencoded url
        // (double slash problem).
        $iisUrlRewritten = $serverParams['IIS_WasUrlRewritten'] ?? null;
        $unencodedUrl    = $serverParams['UNENCODED_URL'] ?? '';
        if ('1' === $iisUrlRewritten && '' !== $unencodedUrl) {
            return $unencodedUrl;
        }

        $requestUri = $serverParams['REQUEST_URI'] ?? null;

        // HTTP proxy requests setup request URI with scheme and host [and port]
        // + the URL path, only use URL path.
        if ($requestUri !== null) {
            return preg_replace('#^[^/:]+://[^/]+#', '', $requestUri);
        }

        // IIS 5.0, PHP as CGI.
        $origPathInfo = $serverParams['ORIG_PATH_INFO'] ?? null;
        if ($origPathInfo !== null) {
            $queryString = $serverParams['QUERY_STRING'] ?? null;
            if ($queryString !== '') {
                $origPathInfo .= '?' . $queryString;
            }
            return $origPathInfo;
        }

        return '/';
    }
}
