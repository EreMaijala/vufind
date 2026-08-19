<?php

/**
 * Hierarchy tree retrieval action.
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
use VuFind\ServiceManager\Factory\Autowire;

use function is_object;

/**
 * Hierarchy tree retrieval action.
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
class GetTreeAction extends AbstractAction
{
    use AjaxResponseTrait;

    /**
     * Constructor.
     *
     * @param RecordLoader $recordLoader Record loader
     */
    #[Autowire]
    public function __construct(
        protected RecordLoader $recordLoader,
    ) {
        parent::__construct();
    }

    /**
     * Return hierarchy tree as JSON.
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

        $id = $this->getQueryParam('id');
        $source = $this->getQueryParam('sourceId', DEFAULT_SEARCH_BACKEND);
        $message = 'Service Unavailable'; // default error message
        if ($id) {
            try {
                $recordDriver = $this->recordLoader->load($id, $source);
                $hierarchyDriver = $recordDriver->tryMethod('getHierarchyDriver');
                if (is_object($hierarchyDriver)) {
                    $html = $hierarchyDriver->render(
                        $recordDriver,
                        $this->getQueryParam('context', 'Record'),
                        'List',
                        $this->getQueryParam('hierarchyId', ''),
                        $request->getQueryParams(),
                    );
                    return $this->getJsonResponse($response, compact('html'), allowCaching: true, jsonWrapper: false);
                }
            } catch (\Exception $e) {
                // Let exceptions fall through to error condition below:
                $message = APPLICATION_ENV === 'development' ? (string)$e : 'Unexpected exception';
            }
        }

        // If we got this far, something went wrong:
        $code = 503;
        return $this->getJsonResponse($response, ['error' => compact('code', 'message')], $code, jsonWrapper: false);
    }
}
