<?php

/*
 * DirectAdmin API Client
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omines\DirectAdmin\Objects\Database;

use Omines\DirectAdmin\Objects\BaseObject;
use Omines\DirectAdmin\Objects\Database;
use Stringable;

/**
 * AccessHost.
 */
class AccessHost extends BaseObject implements Stringable
{
    protected Database $database;

    /**
     * @param string $host
     */
    public function __construct($host, Database $database)
    {
        parent::__construct($host, $database->getContext());
        $this->database = $database;
    }

    public static function create(Database $database, string $host): AccessHost
    {
        $database->getContext()->invokeApiPost('DATABASES', [
            'action' => 'accesshosts',
            'create' => 'yes',
            'db'     => $database->getDatabaseName(),
            'host'   => $host,
        ]);
        return new self($host, $database);
    }

    /**
     * Deletes the access host.
     */
    public function delete()
    {
        $this->getContext()->invokeApiPost('DATABASES', [
            'action'  => 'accesshosts',
            'delete'  => 'yes',
            'db'      => $this->database->getDatabaseName(),
            'select0' => $this->getName(),
        ]);
        $this->database->clearCache();
    }

    public function getHost(): string
    {
        return $this->getName();
    }

    public function __toString(): string
    {
        return $this->getHost();
    }
}
