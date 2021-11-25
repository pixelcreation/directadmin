<?php

/*
 * DirectAdmin API Client
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omines\DirectAdmin\Context;

use Omines\DirectAdmin\DirectAdmin;
use Omines\DirectAdmin\DirectAdminException;
use Omines\DirectAdmin\Objects\Domain;
use Omines\DirectAdmin\Objects\Users\User;

/**
 * Context for user functions.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class UserContext extends BaseContext
{
    private ?User $user = null;

    /**
     * Constructs the object.
     *
     * @param DirectAdmin $connection A prepared connection
     * @param bool        $validate   Whether to check if the connection matches the context
     */
    public function __construct(DirectAdmin $connection, bool $validate = false)
    {
        parent::__construct($connection);
        if ($validate) {
            $classMap = [
                DirectAdmin::ACCOUNT_TYPE_ADMIN    => AdminContext::class,
                DirectAdmin::ACCOUNT_TYPE_RESELLER => ResellerContext::class,
                DirectAdmin::ACCOUNT_TYPE_USER     => self::class,
            ];
            if ($classMap[$this->getType()] != $this::class) {
                /* @codeCoverageIgnoreStart */
                throw new DirectAdminException('Validation mismatch on context construction');
                /* @codeCoverageIgnoreEnd */
            }
        }
    }

    /**
     * Returns the type of the account (user/reseller/admin).
     *
     * @return string One of the DirectAdmin::ACCOUNT_TYPE_ constants describing the type of underlying account
     */
    public function getType(): string
    {
        return $this->getContextUser()->getType();
    }

    /**
     * Returns the actual user object behind the context.
     *
     * @return User|null The user object behind the context
     */
    public function getContextUser(): ?User
    {
        if (!isset($this->user)) {
            $this->user = User::fromConfig($this->invokeApiGet('SHOW_USER_CONFIG'), $this);
        }
        return $this->user;
    }

    /**
     * Returns a domain managed by the current user.
     *
     * @param string $domainName The requested domain name
     *
     * @return null|Domain The domain if found, or NULL if it does not exist
     */
    public function getDomain(string $domainName): ?Domain
    {
        return $this->getContextUser()->getDomain($domainName);
    }

    /**
     * Returns a full list of the domains managed by the current user.
     *
     * @return Domain[]
     */
    public function getDomains(): array
    {
        return $this->getContextUser()->getDomains();
    }

    /**
     * Returns the username of the current context.
     *
     * @return string Username for the current context
     */
    public function getUsername(): string
    {
        return $this->getConnection()->getUsername();
    }
}
