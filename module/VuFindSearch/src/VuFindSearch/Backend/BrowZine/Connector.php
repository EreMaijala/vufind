<?php

/**
 * BrowZine connector.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\BrowZine;

use Closure;
use GuzzleHttp\Psr7\Request;

/**
 * BrowZine connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Connector implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * The base URI for API requests
     *
     * @var string
     */
    protected $base = 'https://api.thirdiron.com/public/v1/';

    /**
     * Constructor
     *
     * Sets up the BrowZine Client
     *
     * @param HttpClient $client HTTP client
     * @param string     $token  API access token
     * @param string     $id     Library ID number
     */
    public function __construct(
        protected Closure $httpClientFactory,
        protected string $token,
        protected string $libraryId
    ) {
    }

    /**
     * Perform a DOI lookup
     *
     * @param string $doi            DOI
     * @param bool   $includeJournal Include journal data in response?
     *
     * @return mixed
     */
    public function lookupDoi($doi, $includeJournal = false)
    {
        // Documentation says URL encoding of DOI is not necessary.
        return $this->request(
            'articles/doi/' . $doi,
            $includeJournal ? ['include' => 'journal'] : []
        );
    }

    /**
     * Perform an ISSN lookup.
     *
     * @param string|array $issns ISSN(s) to look up.
     *
     * @return mixed
     */
    public function lookupIssns($issns)
    {
        return $this->request('search', ['issns' => implode(',', (array)$issns)]);
    }

    /**
     * Perform a search
     *
     * @param string $query Search query
     *
     * @return mixed
     */
    public function search($query)
    {
        return $this->request('search', compact('query'));
    }

    /**
     * Get a full request URL for a relative path
     *
     * @param string $path   URL path for service
     * @param array  $params Get params
     *
     * @return string
     */
    protected function getUri(string $path, array $params)
    {
        return $this->base . 'libraries/' . $this->libraryId . '/' . $path . '?' . http_build_query($params);
    }

    /**
     * Perform an API request and return the response body
     *
     * @param string $path   URL path for service
     * @param array  $params GET parameters
     *
     * @return mixed
     */
    protected function request($path, $params = [])
    {
        $params['access_token'] = $this->token;
        $uri = $this->getUri($path, $params);
        $this->debug('BrowZine request: ' . $uri);
        $client = ($this->httpClientFactory)('GET');
        $response = $client->sendRequest(new Request('GET', $uri));
        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody(), true);
        } else {
            $this->debug('API failure; status: ' . $response->getStatusCode());
        }
        return null;
    }
}
