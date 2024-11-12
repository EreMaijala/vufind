<?php

/**
 * HTTP service interface.
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
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */

namespace VuFind\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP service interface.
 *
 * @category VuFind
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 * @todo     Merge with PSR-18 HTTP Client Service when implemented
 */
interface HttpServiceInterface
{
    /**
     * Default regular expression matching a request to localhost.
     *
     * @var string
     */
    public const LOCAL_ADDRESS_RE = '@^(localhost|127(\.\d+){3}|\[::1\])@';

    /**
     * Return a new HTTP client.
     *
     * @param ?string $url     Target URL (required for proper proxy setup for non-local addresses)
     * @param ?float  $timeout Request timeout in seconds (overrides configuration)
     * @param array   $options Additional options (similar to Http section in config.ini)
     *
     * @return \Psr\Http\Client\ClientInterface
     */
    public function createClient(
        ?string $url = null,
        ?float $timeout = null,
        array $options = []
    ): ClientInterface;

    /**
     * Make a GET request
     *
     * @param string $url           URL
     * @param array  $headers       Optional headers
     * @param array  $clientOptions Optional HTTP client options
     *
     * @return ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface
     * @throws \Psr\Http\Client\NetworkExceptionInterface
     * @throws \Psr\Http\Client\RequestExceptionInterface
     */
    public function get(string $url, array $headers = [], array $clientOptions = []): ResponseInterface;

    /**
     * Make a POST request
     *
     * @param string $url           URL
     * @param string $body          Request body
     * @param array  $headers       Optional headers
     * @param string $contentType   Optional content type (overrides any content-type set in $headers)
     * @param array  $clientOptions Optional HTTP client options
     *
     * @return ResponseInterface
     * @throws \Psr\Http\Client\ClientExceptionInterface
     * @throws \Psr\Http\Client\NetworkExceptionInterface
     * @throws \Psr\Http\Client\RequestExceptionInterface
     */
    public function post(
        string $url,
        string $body,
        array $headers = [],
        string $contentType = 'application/x-www-form-urlencoded;charset=UTF-8',
        array $clientOptions = []
    ): ResponseInterface;

    /**
     * Check if the response status code indicates success
     *
     * @param ResponseInterface $response Response
     *
     * @return bool
     */
    public function isSuccess(ResponseInterface $response): bool;

    /**
     * Check if the response status code indicates an error
     *
     * @param ResponseInterface $response Response
     *
     * @return bool
     */
    public function isError(ResponseInterface $response): bool;

    /**
     * Check if the response status code indicates a client error
     *
     * @param ResponseInterface $response Response
     *
     * @return bool
     */
    public function isClientError(ResponseInterface $response): bool;

    /**
     * Check if the response status code indicates a server error
     *
     * @param ResponseInterface $response Response
     *
     * @return bool
     */
    public function isServerError(ResponseInterface $response): bool;
}
