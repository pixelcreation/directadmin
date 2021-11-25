<?php

/*
 * DirectAdmin API Client
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omines\DirectAdmin\Objects\Users;

use Omines\DirectAdmin\Context\AdminContext;
use Omines\DirectAdmin\Context\BaseContext;
use Omines\DirectAdmin\Context\ResellerContext;
use Omines\DirectAdmin\Context\UserContext;
use Omines\DirectAdmin\DirectAdminException;
use Omines\DirectAdmin\Objects\BaseObject;

/**
 * Reseller.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class Reseller extends User
{
    /**
     * {@inheritdoc}
     */
    public function __construct($name, UserContext $context, $config = null)
    {
        parent::__construct($name, $context, $config);
    }

    public function getUser(string $username): ?User
    {
        $users = $this->getUsers();
        return $users[$username] ?? null;
    }

    /**
     * @return User[]
     */
    public function getUsers(): array
    {
        return BaseObject::toObjectArray($this->getContext()->invokeApiGet('SHOW_USERS', ['reseller' => $this->getUsername()]),
            User::class, $this->getContext());
    }

    /**
     * @return ResellerContext|BaseContext
     */
    public function impersonate(): ResellerContext|BaseContext
    {
        /** @var AdminContext $context */
        if (!($context = $this->getContext()) instanceof AdminContext) {
            throw new DirectAdminException('You need to be an admin to impersonate a reseller');
        }
        return $context->impersonateReseller($this->getUsername());
    }
}
