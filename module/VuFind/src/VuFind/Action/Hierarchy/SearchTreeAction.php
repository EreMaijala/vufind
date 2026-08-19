<?php

/**
 * Hierarchy tree search action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Action
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Hierarchy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractAction;
use VuFind\Action\AjaxResponseTrait;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Search\Results\PluginManager as SearchResultsPluginManager;
use VuFind\ServiceManager\Factory\Autowire;

use function array_slice;
use function count;

/**
 * Hierarchy tree search action.
 *
 * @category VuFind
 * @package  Action
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SearchTreeAction extends AbstractAction
{
    use AjaxResponseTrait;

    /**
     * Constructor.
     *
     * @param RecordLoader               $recordLoader               Record loader
     * @param SearchResultsPluginManager $searchResultsPluginManager Search results plugin manager
     * @param array                      $config                     VuFind configuration
     */
    public function __construct(
        protected RecordLoader $recordLoader,
        protected SearchResultsPluginManager $searchResultsPluginManager,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Search the hierarchy tree.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $this->disableSessionWrites();  // avoid session write timing bug

        $limit = (int)($this->config['Hierarchy']['treeSearchLimit'] ?? 100);
        $resultIDs = [];
        $hierarchyID = $this->getQueryParam('hierarchyID');
        $source = $this->getQueryParam('hierarchySource', DEFAULT_SEARCH_BACKEND);
        $lookfor = $this->getQueryParam('lookfor', '');
        $searchType = $this->getQueryParam('type', 'AllFields');

        $results = $this->searchResultsPluginManager->get($source);
        $results->getParams()->setBasicSearch($lookfor, $searchType);
        $results->getParams()->addFilter('hierarchy_top_id:' . $hierarchyID);
        $facets = $results->getFullFieldFacets(['id'], false, null === $limit ? -1 : $limit + 1);

        $callback = function ($data) {
            return $data['value'];
        };
        $resultIDs = isset($facets['id']['data']['list'])
            ? array_map($callback, $facets['id']['data']['list']) : [];

        $limitReached = ($limit > 0 && count($resultIDs) > $limit);

        $returnArray = [
            'limitReached' => $limitReached,
            'results' => array_slice($resultIDs, 0, $limit),
        ];
        return $this->getJsonResponse($response, $returnArray, allowCaching: true, jsonWrapper: false);
    }
}
