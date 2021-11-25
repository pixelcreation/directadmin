<?php

/*
 * DirectAdmin API Client
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omines\DirectAdmin\Objects;

use Omines\DirectAdmin\Context\UserContext;
use Omines\DirectAdmin\Objects\Users\User;

/**
 * Database.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class Database extends BaseObject
{
    public const CACHE_ACCESS_HOSTS = 'access_hosts';

    private string $databaseName;

    /**
     * Database constructor.
     *
     * @param string      $name    Name of the database
     * @param User        $owner   Database owner
     * @param UserContext $context Context within which the object is valid
     */
    public function __construct(string $name, private User $owner, UserContext $context)
    {
        parent::__construct($name, $context);
        $this->databaseName = $this->owner->getUsername() . '_' . $this->getName();
    }

    /**
     * Creates a new database under the specified user.
     *
     * @param User        $user     Owner of the database
     * @param string      $name     Database name, without <user>_ prefix
     * @param string      $username Username to access the database with, without <user>_ prefix
     * @param string|null $password Password, or null if database user already exists
     *
     * @return Database Newly created database
     */
    public static function create(User $user, string $name, string $username, ?string $password): Database
    {
        $options = [
            'action' => 'create',
            'name'   => $name,
        ];
        if (!empty($password)) {
            $options += ['user' => $username, 'passwd' => $password, 'passwd2' => $password];
        } else {
            $options += ['userlist' => $username];
        }
        $user->getContext()->invokeApiPost('DATABASES', $options);
        return new self($name, $user, $user->getContext());
    }

    /**
     * Deletes this database from the user.
     */
    public function delete()
    {
        $this->getContext()->invokeApiPost('DATABASES', [
            'action'  => 'delete',
            'select0' => $this->getDatabaseName(),
        ]);
        $this->getContext()->getContextUser()->clearCache();
    }

    /**
     * @return Database\AccessHost[]
     */
    public function getAccessHosts(): array
    {
        return $this->getCache(self::CACHE_ACCESS_HOSTS, function () {
            $accessHosts = $this->getContext()->invokeApiGet('DATABASES', [
                'action' => 'accesshosts',
                'db'     => $this->getDatabaseName(),
            ]);

            return array_map(fn($name) => new Database\AccessHost($name, $this), $accessHosts);
        });
    }

    public function createAccessHost(string $name): Database\AccessHost
    {
        $accessHost = Database\AccessHost::create($this, $name);
        $this->getContext()->getContextUser()->clearCache();
        return $accessHost;
    }

    /**
     * @return string Name of the database
     */
    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }
}
