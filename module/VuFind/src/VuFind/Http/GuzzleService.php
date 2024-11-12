<?php

/**
 * Guzzle service.
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

use GuzzleHttp\ClientInterface as GuzzleHttpClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Guzzle service.
 *
 * @category VuFind
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 * @todo     Merge with PSR-18 HTTP Client Service when implemented
 */
class GuzzleService implements HttpServiceInterface
{
    /**
     * Default regular expression matching a request to localhost.
     *
     * @var string
     */
    public const LOCAL_ADDRESS_RE = '@^(localhost|127(\.\d+){3}|\[::1\])@';

    /**
     * VuFind configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Regular expression matching a request to localhost or hosts
     * that are not proxied.
     *
     * @var string
     */
    protected $localAddressesRegEx = self::LOCAL_ADDRESS_RE;

    /**
     * Mappings from VuFind Legacy HTTP settings to Guzzle
     *
     * @var array
     */
    protected $guzzleHttpSettingsMap = [
        'timeout' => 'timeout',
        'curloptions' => 'curl',
    ];

    /**
     * Constructor.
     *
     * @param array $config VuFind configuration
     *
     * @return void
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        if (isset($config['Proxy']['localAddressesRegEx'])) {
            $this->localAddressesRegEx = $config['Proxy']['localAddressesRegEx'];
        }
    }

    /**
     * Return a new Guzzle client as a PSR-18 client interface.
     *
     * @param ?string $url     Target URL (required for proper proxy setup for non-local addresses)
     * @param ?float  $timeout Request timeout in seconds (overrides configuration)
     * @param array   $options Additional options (similar to Http section in config.ini)
     *
     * @return ClientInterface
     */
    public function createClient(?string $url = null, ?float $timeout = null, array $options = []): ClientInterface
    {
        return (ClientInterface::class)($this->createGuzzleClient($url, $timeout, $options));
    }

    /**
     * Return a new Guzzle client.
     *
     * @param ?string $url     Target URL (required for proper proxy setup for non-local addresses)
     * @param ?float  $timeout Request timeout in seconds (overrides configuration)
     * @param array   $options Additional options (similar to Http section in config.ini)
     *
     * @return GuzzleHttpClientInterface
     */
    public function createGuzzleClient(
        ?string $url = null,
        ?float $timeout = null,
        array $options = []
    ): GuzzleHttpClientInterface {
        return new \GuzzleHttp\Client($this->getGuzzleConfig($url, $timeout, $options));
    }

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
    public function get(string $url, array $headers = [], array $clientOptions = []): ResponseInterface
    {
        $request = new Request('GET', $url, $headers);
        return ($this->createClient($url, options: $clientOptions))->sendRequest($request);
    }

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
    ): ResponseInterface {
        if ($contentType) {
            // Remove any existing content-type header:
            foreach (array_keys($headers) as $key) {
                if (strcasecmp($key, 'content-type') === 0) {
                    unset($headers[$key]);
                }
            }
            $headers['Content-Type'] = $contentType;
        }
        $request = new Request('POST', $url, $headers);
        if ($body) {
            $request = $request->withBody(Utils::streamFor($body));
        }
        return ($this->createClient($url, options: $clientOptions))->sendRequest($request);
    }

    /**
     * Check if the response status code indicates success
     *
     * @param ResponseInterface $response Response
     *
     * @return bool
     */
    public function isSuccess(ResponseInterface $response): bool
    {
        $code = $response->getStatusCode();
        return $code >= 200 && $code < 300;

    }

    /**
     * Check if the response status code indicates an error
     *
     * @param ResponseInterface $response Response
     *
     * @return bool
     */
    public function isError(ResponseInterface $response): bool
    {
        return !$this->isSuccess($response);
    }

    /**
     * Check if the response status code indicates a client error
     *
     * @param ResponseInterface $response Response
     *
     * @return bool
     */
    public function isClientError(ResponseInterface $response): bool
    {
        $code = $response->getStatusCode();
        return $code >= 400 && $code < 500;
    }

    /**
     * Check if the response status code indicates a server error
     *
     * @param ResponseInterface $response Response
     *
     * @return bool
     */
    public function isServerError(ResponseInterface $response): bool
    {
        $code = $response->getStatusCode();
        return $code >= 500 && $code < 600;
    }

    /**
     * Get Guzzle options
     *
     * Maps legacy laminas-http settings to Guzzle's equivalents and sets the proxy configuration.
     *
     * @param ?string $url     Target URL (required for proper proxy setup for non-local addresses)
     * @param ?float  $timeout Request timeout in seconds
     * @param array   $options Additional options
     *
     * @return array
     */
    protected function getGuzzleConfig(?string $url, ?float $timeout, array $options = []): array
    {
        $guzzleConfig = array_merge($this->config['Http'] ?? [], $options);

        // Map known one-to-one configuration settings to Guzzle settings:
        $guzzleConfig = array_combine(
            array_map(
                function ($key) {
                    return $this->guzzleHttpSettingsMap[$key] ?? $key;
                },
                array_keys($guzzleConfig)
            ),
            array_values($guzzleConfig)
        );

        // Override timeout if requested:
        if (null !== $timeout) {
            $guzzleConfig['timeout'] = $timeout;
        }

        // Handle maxredirects:
        if (isset($guzzleConfig['maxredirects'])) {
            $guzzleConfig['allow_redirects'] = [
                'max' => $guzzleConfig['maxredirects'],
                'strict' => $guzzleConfig['strictredirects'] ?? false,
                'referer' => false,
                'protocols' => ['http', 'https'],
                'track_redirects' => false,
            ];
            unset($guzzleConfig['maxredirects']);
            unset($guzzleConfig['strictredirects']);
        }

        // Handle useragent:
        if (isset($guzzleConfig['useragent'])) {
            $guzzleConfig['headers']['User-Agent'] = $guzzleConfig['useragent'];
            unset($guzzleConfig['useragent']);
        }

        // Handle sslcapath, sslcafile and sslverifypeer, but apply them only if 'verify' is not already set:
        if (!isset($guzzleConfig['verify'])) {
            if ($guzzleConfig['sslverifypeer'] ?? true) {
                if ($verify = $guzzleConfig['sslcafile'] ?? $guzzleConfig['sslcapath'] ?? null) {
                    $guzzleConfig['verify'] = $verify;
                }
            } else {
                $guzzleConfig['verify'] = false;
            }
        }
        unset($guzzleConfig['sslverifypeer']);
        unset($guzzleConfig['sslcapath']);
        unset($guzzleConfig['sslcafile']);

        // Handle proxy configuration:
        if (!$this->isLocal($url)) {
            $proxyConfig = $this->config['Proxy'] ?? [];
            if (!empty($proxyConfig['host'])) {
                $guzzleConfig['curl'][CURLOPT_PROXY] = $proxyConfig['host'];
            }
            if (!empty($proxyConfig['port'])) {
                $guzzleConfig['curl'][CURLOPT_PROXYPORT] = $proxyConfig['port'];
            }
            // HTTP is default, so handle only the SOCKS 5 proxy types
            switch ($proxyConfig['type'] ?? '') {
                case 'socks5':
                    $guzzleConfig['curl'][CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
                    break;
                case 'socks5_hostname':
                    $guzzleConfig['curl'][CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5_HOSTNAME;
                    break;
            }
        }
        return $guzzleConfig;
    }

    /**
     * Check if given URL is a local address
     *
     * @param ?string $host Host to check
     *
     * @return bool
     */
    protected function isLocal(?string $host): bool
    {
        return $host && preg_match($this->localAddressesRegEx, $host);
    }
}
