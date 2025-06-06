<?php

/**
 * Database service for event table.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024-2025.
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Doctrine\ORM\EntityManager;
use VuFind\Db\Entity\AuditEventEntityInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\PersistenceManager;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;

use function in_array;

/**
 * Database service for event table.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class AuditEventService extends AbstractDbService implements AuditEventServiceInterface
{
    /**
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager Database entity plugin manager
     * @param PersistenceManager  $persistenceManager  Entity persistence manager
     * @param array               $enabledEventTypes   Event types enabled in configuration
     */
    public function __construct(
        protected EntityManager $entityManager,
        protected EntityPluginManager $entityPluginManager,
        protected PersistenceManager $persistenceManager,
        protected array $enabledEventTypes
    ) {
    }

    /**
     * Create an event entity object.
     *
     * @return AuditEventEntityInterface
     */
    public function createEntity(): AuditEventEntityInterface
    {
        $class = $this->getEntityClass(AuditEventEntityInterface::class);
        return new $class();
    }

    /**
     * Add an event.
     *
     * @param AuditEventType       $type    Event type
     * @param AuditEventSubtype    $subtype Event subtype
     * @param ?UserEntityInterface $user    User
     * @param ?string              $message Event message (freeform)
     * @param ?array               $data    Additional data
     *
     * @return void
     */
    public function addEvent(
        AuditEventType $type,
        AuditEventSubtype $subtype,
        ?UserEntityInterface $user = null,
        ?string $message = null,
        array $data = []
    ): void {
        if (!in_array($type->value, $this->enabledEventTypes)) {
            return;
        }
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $class = $backtrace[1]['class'] ?? '';
        $function = $backtrace[1]['function'] ?? '';
        $data = $this->scrubSecrets($data);
        $data['__method'] = "$class::$function";
        $event = $this->createEntity();
        $event
            ->setType($type)
            ->setSubtype($subtype)
            ->setUser($user->getId() ? $user : null)
            ->setClientIp($_SERVER['REMOTE_ADDR'] ?? null)
            ->setServerIp($_SERVER['SERVER_ADDR'] ?? null)
            ->setServerName($_SERVER['SERVER_NAME'] ?? null)
            ->setMessage($message)
            ->setData(json_encode($data));
        $this->persistEntity($event);
    }

    /**
     * Remove any secrets from details to be logged.
     *
     * @param array $details Details
     *
     * @return @rray
     */
    protected function scrubSecrets(array $details): array
    {
        array_walk_recursive(
            $details,
            function (&$value, $key) {
                if ('csrf' === $key || str_contains($key, 'password')) {
                    $value = '***';
                }
            }
        );
        return $details;
    }
}
