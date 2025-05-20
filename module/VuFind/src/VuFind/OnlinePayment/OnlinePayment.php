<?php

/**
 * Online payment service
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2025.
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
 * @package  OnlinePayment
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace VuFind\OnlinePayment;

use VuFind\ILS\Connection;
use VuFind\OnlinePayment\Handler\HandlerInterface;
use VuFind\OnlinePayment\Handler\PluginManager as HandlerPluginManager;

/**
 * Online payment service
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class OnlinePayment
{
    /**
     * Constructor.
     *
     * @param HandlerPluginManager $handlerManager Handler plugin manager
     * @param Connection           $ils            ILS Connection
     */
    public function __construct(
        protected HandlerPluginManager $handlerManager,
        protected Connection $ils
    ) {
    }

    /**
     * Get online payment handler
     *
     * @param string $sourceIls Source ILS
     *
     * @return HandlerInterface
     *
     * @throws \Exception
     */
    public function getHandler(string $sourceIls): HandlerInterface
    {
        if (!($handlerName = $this->getHandlerName($sourceIls))) {
            throw new \Exception("Online payment handler not defined for '$sourceIls'");
        }
        if (!$this->handlerManager->has($handlerName)) {
            throw new \Exception("Online payment handler '$handlerName' not found for '$sourceIls'");
        }

        $handler = $this->handlerManager->get($handlerName);
        $handler->init($this->getOnlinePaymentConfig($sourceIls));
        return $handler;
    }

    /**
     * Get online payment handler name.
     *
     * @param string $sourceIls Source ILS
     *
     * @return string
     */
    public function getHandlerName(string $sourceIls): string
    {
        if ($config = $this->getOnlinePaymentConfig($sourceIls)) {
            return $config['handler'] ?? '';
        }
        return '';
    }

    /**
     * Check if online payment is enabled for an ILS.
     *
     * @param string $sourceIls Source ILS
     *
     * @return bool
     */
    public function isEnabled(string $sourceIls): bool
    {
        return $this->getOnlinePaymentConfig($sourceIls) ? true : false;
    }

    /**
     * Get online payment configuration for an ILS.
     *
     * @param string $sourceIls Source ILS
     *
     * @return array
     */
    protected function getOnlinePaymentConfig(string $sourceIls): array
    {
        return $this->ils->getConfig('OnlinePayment', ['__source' => $sourceIls]) ?? [];
    }
}
