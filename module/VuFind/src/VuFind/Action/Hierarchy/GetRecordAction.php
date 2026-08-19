<?php

/**
 * Hierarchy tree record retrieval action.
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
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Action\AjaxResponseTrait;
use VuFind\Record\Loader as RecordLoader;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\Record;

/**
 * Hierarchy tree record retrieval action.
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
class GetRecordAction extends AbstractTemplateRenderingAction
{
    use AjaxResponseTrait;

    /**
     * Constructor.
     *
     * @param RecordLoader $recordLoader     Record loader
     * @param Record       $recordViewHelper Record view helper
     * @param array        $config           VuFind configuration
     */
    public function __construct(
        protected RecordLoader $recordLoader,
        #[Autowire(container: 'ViewHelperManager')]
        protected Record $recordViewHelper,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Get a record.
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
        $source = $this->getQueryParam('source', DEFAULT_SEARCH_BACKEND);
        $result = null;
        if ($id) {
            try {
                $record = $this->recordLoader->load($id, $source);
                $result = ($this->recordViewHelper)($record)->getCollectionBriefRecord();
            } catch (\VuFind\Exception\RecordMissing $e) {
                // Fall through
            }
        }
        if (null === $result) {
            $result = $this->getTemplateRenderer()
                ->renderTemplateAsString($request, 'collection/collection-record-error.phtml');
        }

        $response->getBody()->write($result);
        return $response;
    }
}
