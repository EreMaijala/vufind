<?php

/**
 * Action configuration manager.
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
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Action;

use Laminas\Router\RouteMatch;
use VuFind\Exception\ConfigException;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;

use function is_string;

/**
 * Action configuration manager.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class ActionConfigManager
{
    /**
     * Route-specific action configuration.
     *
     * The configuration is an array of associative arrays of configuration entries.
     *
     * Valid keys for each configuration entry:
     *  - routes                An array of route names or prefixes the configuration applies to
     *                          (format: category-action)
     *  - accessPermission      Set access permission (string|false|null, see AccessPermissionInterface)
     *  - accessDeniedBehavior  Set behavior when access is denied (string|null, see AccessPermissionInterface)
     *  - backendId             Set search backend identifier (string)
     *  - defaultTab            Set default tab (string|null)
     *  - fallbackDefaultTab    Set fallback default tab (string; empty string to use Site/defaultRecordTab from config)
     *  - poweredBy             Set "Powered by" displayed in page footer
     *
     * @var array
     */
    protected array $actionConfig = [
        // Author:
        [
            'routes' => [
                'author-home',

            ],
            'backendId' => DEFAULT_SEARCH_BACKEND,
        ],
        [
            'routes' => [
                'author-facetlist',
                'author-results',
            ],
            'backendId' => 'SolrAuthor',
        ],
        [
            'routes' => [
                'author-search',
            ],
            'backendId' => 'SolrAuthorFacets',
        ],
        [
            'routes' => [
                'author-facetlist',
            ],
            'backendId' => 'SolrAuthor',
        ],
        // Authority:
        [
            'routes' => [
                'authority-home',
                'authority-search',
            ],
            'backendId' => 'SolrAuth', // Only SolrAuth supported!
        ],
        // Blender:
        [
            'routes' => [
                'blender',
                'legacy-blender-results',
                [
                    'type' => 'prefix',
                    'prefix' => 'blender-',
                ],
            ],
            'backendId' => 'Blender',
        ],
        // Blender2:
        [
            'routes' => [
                'blender2',
                [
                    'type' => 'prefix',
                    'prefix' => 'blender2-',
                ],
            ],
            'backendId' => 'Blender2',
        ],
        // BrowZine:
        [
            'routes' => [
                'browzine-home',
                'browzine-search',
            ],
            'backendId' => 'BrowZine', // Only BrowZine supported!
        ],
        // Combined search:
        [
            'routes' => [
                'combined',
                [
                    'type' => 'prefix',
                    'prefix' => 'combined-',
                ],
            ],
            'backendId' => 'Combined',
        ],
        // Course reserves:
        [
            'routes' => [
                'search-reserves',
            ],
            'backendId' => 'Solr', // Only Solr supported!
        ],
        // EDS:
        [
            'routes' => [
                'eds',
                [
                    'type' => 'prefix',
                    'prefix' => 'eds-',
                ],
            ],
            'accessPermission' => 'access.EDSModule',
            'backendId' => 'EDS',
        ],
        [
            'routes' => [
                'edsrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'edsrecord-',
                ],
            ],
            'accessPermission' => 'access.EDSModule',
            'backendId' => 'EDS',
            'fallbackDefaultTab' => 'Description',
        ],
        // EIT:
        [
            'routes' => [
                'eitrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'eitrecord-',
                ],
            ],
            'accessPermission' => 'access.EITModule',
            'backendId' => 'EIT',
            'fallbackDefaultTab' => 'Description',
        ],
        // EPF:
        [
            'routes' => [
                'epfrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'epfrecord-',
                ],
            ],
            'accessPermission' => 'access.EPFModule',
            'backendId' => 'EPF',
        ],
        // Record, Collection (Default backend):
        [
            'routes' => [
                'collection',
                [
                    'type' => 'prefix',
                    'prefix' => 'collection-',
                ],
                'missingrecord',
                'missingrecord-home',
                'record',
                [
                    'type' => 'prefix',
                    'prefix' => 'record-',
                ],
            ],
            'backendId' => DEFAULT_SEARCH_BACKEND,
            'fallbackDefaultTab' => '',
        ],
        // Primo:
        [
            'routes' => [
                'primorecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'primorecord-',
                ],
            ],
            'accessPermission' => 'access.PrimoModule',
            'backendId' => 'Primo',
            'fallbackDefaultTab' => 'Description',
        ],
        // ProquestFSG:
        [
            'routes' => [
                'proquestfsgrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'proquestfsgrecord-',
                ],
            ],
            'backendId' => 'ProQuestFSG',
        ],
        // Search including home page (Default backend):
        [
            'routes' => [
                'home',
                'search-advanced',
                'search-home',
                'search-results',
            ],
            'backendId' => DEFAULT_SEARCH_BACKEND,
        ],
        [
            'routes' => [
                'search-collectionfacetlist',
            ],
            'backendId' => 'SolrCollection',
        ],
        [
            'routes' => [
                'search-reserves',
            ],
            'backendId' => 'SolrReserves',
        ],
        // Search2, Search2Collection:
        [
            'routes' => [
                'search2collection',
                [
                    'type' => 'prefix',
                    'prefix' => 'search2collection-',
                ],
                'search2record',
                [
                    'type' => 'prefix',
                    'prefix' => 'search2record-',
                ],
            ],
            'backendId' => 'Search2',
            'fallbackDefaultTab' => 'Description',
        ],
        // Summon:
        [
            'routes' => [
                'summonrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'summonrecord-',
                ],
            ],
            'backendId' => 'Summon',
            'fallbackDefaultTab' => 'Description',
            'poweredBy' => 'Powered by Summon™ from Serials Solutions, a division of ProQuest.',
        ],
        // Tags:
        [
            'routes' => [
                'tag-home',
            ],
            'backendId' => 'Tags',
        ],
        // WorldCat2 and legacy WorldCat routes:
        [
            'routes' => [
                // Legacy WorldCat routes:
                'worldcatrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'worldcatrecord-',
                ],
                // Current WorldCat2 routes:
                'worldcat2record',
                [
                    'type' => 'prefix',
                    'prefix' => 'worldcat2record-',
                ],
            ],
            'backendId' => 'WorldCat2',
        ],
    ];

    /**
     * Constructor.
     *
     * @param GlobalsContainer $globalsContainer Global data container
     * @param array            $config           VuFind configuration
     */
    public function __construct(
        protected GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
    }

    /**
     * Apply route-based configuration to the action.
     *
     * @param ActionInterface $action     Action
     * @param ?RouteMatch     $routeMatch Route match
     * @param ?string         $routeName  Route name to use (alternative to one determined from RouteMatch)
     *
     * @return void
     */
    public function applyRouteBasedConfig(
        ActionInterface $action,
        ?RouteMatch $routeMatch = null,
        ?string $routeName = null,
    ): void {
        if ((!$routeMatch && !$routeName) || !($action instanceof ActionConfigInterface)) {
            return;
        }

        if (!$routeName) {
            // Try to use lowercase controller-action or just action if available, with route name as a fallback:
            if ($actionName = $routeMatch->getParam('action')) {
                if ($controllerName = $routeMatch->getParam('controller')) {
                    $routeName = $controllerName . '-' . $actionName;
                } else {
                    $routeName = $actionName;
                }
                $routeName = strtolower($routeName);
            } else {
                $routeName = $routeMatch->getMatchedRouteName();
            }
        }
        foreach ($this->actionConfig as $currentConfig) {
            if ($this->routeNameMatchesConfig($routeName, $currentConfig)) {
                // Apply configuration:
                foreach ($currentConfig as $key => $value) {
                    switch ($key) {
                        case 'routes':
                            break;
                        case 'accessPermission':
                        case 'accessDeniedBehavior':
                            if (!($action instanceof AccessPermissionInterface)) {
                                throw new ConfigException(
                                    $action::class . ' (route ' . $routeName . ')'
                                    . " does not implement AccessPermissionInterface for $key configuration"
                                );
                            }
                            if ('accessDeniedBehavior' === $key) {
                                $action->setAccessDeniedBehavior($value);
                            } else {
                                $action->setAccessPermission($value);
                            }
                            break;
                        case 'backendId':
                            if (!($action instanceof BackendIdInterface)) {
                                throw new ConfigException(
                                    $action::class . ' (route ' . $routeName . ')'
                                    . " does not implement BackendIdInterface for $key configuration"
                                );
                            }
                            $action->setBackendId($value);
                            break;
                        case 'defaultTab':
                        case 'fallbackDefaultTab':
                            if (!($action instanceof DefaultTabInterface)) {
                                throw new ConfigException(
                                    $action::class . ' (route ' . $routeName . ')'
                                    . " does not implement DefaultTabInterface for $key configuration"
                                );
                            }
                            if ('fallbackDefaultTab' === $key) {
                                if ('' === $value) {
                                    // Load default tab setting:
                                    if (!($value = $this->config['Site']['defaultRecordTab'] ?? null)) {
                                        break;
                                    }
                                }
                                $action->setFallbackDefaultTab($value);
                            } else {
                                $action->setDefaultTab($value);
                            }
                            break;
                        case 'poweredBy':
                            $this->globalsContainer['poweredBy'] = $value;
                            break;
                        default:
                            throw new ConfigException(
                                $action::class . ' (route ' . $routeName . "): Invalid configuration key $key"
                            );
                    }
                }
                break;
            }
        }
    }

    /**
     * Check if route name matches the given config.
     *
     * @param string $routeName Route name
     * @param array  $config    Route-based config entry
     *
     * @return bool
     */
    protected function routeNameMatchesConfig(string $routeName, array $config): bool
    {
        foreach ($config['routes'] as $route) {
            if (is_string($route)) {
                if ($routeName === $route) {
                    return true;
                }
            } else {
                switch ($route['type']) {
                    case 'prefix':
                        if (str_starts_with($routeName, $route['prefix'])) {
                            return true;
                        }
                        break;
                    default:
                        throw new ConfigException(('Invalid routes entry: ' . var_export($route, true)));
                }
            }
        }
        return false;
    }
}
