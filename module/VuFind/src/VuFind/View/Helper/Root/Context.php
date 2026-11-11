<?php

/**
 * Context manager (useful for using render() instead of partial() for better
 * performance -- this allows us to set and roll back variables in the global
 * scope instead of relying on the overhead of building a whole new scope).
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Renderer\RendererInterface;
use Laminas\View\Renderer\Template;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Context manager (useful for using render() instead of partial() for better
 * performance -- this allows us to set and roll back variables in the global
 * scope instead of relying on the overhead of building a whole new scope).
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Context
{
    /**
     * Current template.
     *
     * @var ?Template
     */
    protected ?Template $template = null;

    /**
     * Constructor.
     *
     * @param RendererInterface $view View renderer
     */
    #[Autowire]
    public function __construct(
        protected RendererInterface $view
    ) {
    }

    /**
     * Set current template.
     *
     * @param ?Template $template Template
     *
     * @return static
     */
    public function setTemplate(?Template $template): static
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Render a template using a temporary context; restore the view to its original state when done. This offers the
     * template full access to the global scope, modified by $context, then puts the global scope back the way it was.
     *
     * @param string $template Template to render
     * @param array  $context  Array of context variables to set temporarily
     *
     * @return string          Rendered template output
     */
    public function renderInContext($template, $context)
    {
        // Use a clone to avoid modifying global rendering state:
        $view = clone $this->view;

        if (!$this->template) {
            return $view->render($template, $context);
        }

        return $view->render($template, $context + iterator_to_array($this->template));
    }

    /**
     * Return this helper instance.
     *
     * @param ?Template $template Template
     *
     * @return static
     */
    public function __invoke(?Template $template = null): static
    {
        if (null !== $template) {
            $result = clone $this;
            $result->setTemplate($template);
            return $result;
        }
        return $this;
    }
}
