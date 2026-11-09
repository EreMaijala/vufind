<?php

declare(strict_types=1);

namespace VuFind\View\Helper\Root;

use InvalidArgumentException;
use Laminas\Paginator\Paginator;
use Laminas\View\Helper\Partial;
use Laminas\View\HelperPluginManager;
use RuntimeException;
use VuFind\ServiceManager\Factory\Autowire;

use function array_merge;
use function get_object_vars;

class PaginationControl
{
    /**
     * Constructor.
     *
     * @param Partial $partialHelper Partial view helper
     */
    public function __construct(
        #[Autowire(container: HelperPluginManager::class)]
        protected Partial $partialHelper,
    ) {
    }

    /**
     * Render the provided pages.  This checks if $view->paginator is set and,
     * if so, uses that.  Also, if no scrolling style or partial are specified,
     * the defaults will be used (if set).
     *
     * @param ?Paginator $paginator Paginator
     * @param  string              $scrollingStyle Scrolling style
     * @param  string              $partial        View partial
     * @param  array               $params         Params to pass to the partial
     *
     * @return string
     * @throws RuntimeException If no paginator or no view partial provided.
     * @throws InvalidArgumentException If partial is invalid array.
     */
    public function __invoke(
        Paginator $paginator,
        string $scrollingStyle,
        string $partial,
        array|string|null $params = []
    ) {
        $pages = get_object_vars($paginator->getPages($scrollingStyle));

        $params = array_merge($pages, $params);

        return ($this->partialHelper)($partial, $params);
    }
}
