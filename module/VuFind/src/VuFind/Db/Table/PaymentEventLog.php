<?php

/**
 * Table Definition for payment transaction event log
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace VuFind\Db\Table;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\ResultSetInterface;
use VuFind\Db\Row\RowGateway;

/**
 * Table Definition for payment transaction event log
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class PaymentEventLog extends \VuFind\Db\Table\Gateway
{
    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas configuration
     * @param ?RowGateway   $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        ?RowGateway $rowObj = null,
        $table = 'payment_event_log'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Get events for a payment
     *
     * @param int $paymentId Payment ID
     *
     * @return ResultSetInterface
     */
    public function getEvents(int $paymentId): ResultSetInterface
    {
        $callback = function ($select) use ($paymentId) {
            $select->where(['payment_id' => $paymentId]);
            $select->order('id');
        };
        return $this->select($callback);
    }

    /**
     * Add an event for a payment
     *
     * @param int    $paymentId Payment ID
     * @param string $status    Status message
     * @param array  $data      Additional data
     *
     * @return void
     */
    public function addEvent(int $paymentId, string $status, array $data = []): void
    {
        $row = $this->createRow();
        $row->populate(
            [
                'payment_id' => $paymentId,
                'date' => date('Y-m-d H:i:s'),
                'server_ip' => $_SERVER['SERVER_ADDR'] ?? '',
                'server_name' => $_SERVER['SERVER_NAME'] ?? '',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'message' => $status,
                'data' => $data ? json_encode($data) : null,
            ]
        );
        $row->save();
    }
}
