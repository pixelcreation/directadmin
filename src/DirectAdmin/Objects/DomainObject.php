<?php

/*
 * DirectAdmin API Client
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omines\DirectAdmin\Objects;

/**
 * Encapsulates a domain-bound object.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
abstract class DomainObject extends BaseObject
{
    private Domain $domain;

    /**
     * @param string $name   Canonical name for the object
     * @param Domain $domain Domain to which the object belongs
     */
    protected function __construct(string $name, Domain $domain)
    {
        parent::__construct($name, $domain->getContext());
        $this->domain = $domain;
    }

    /**
     * Invokes a POST command on a domain object.
     *
     * @param string $command    Command to invoke
     * @param string $action     Action to execute
     * @param array  $parameters Additional options for the command
     * @param bool   $clearCache Whether to clear the domain cache
     *
     * @return array Response from the API
     */
    protected function invokePost(string $command, string $action, array $parameters = [], bool $clearCache = true): array
    {
        return $this->domain->invokePost($command, $action, $parameters, $clearCache);
    }

    public function getDomain(): Domain
    {
        return $this->domain;
    }

    public function getDomainName(): string
    {
        return $this->domain->getDomainName();
    }

    /**
     * Converts an associative array of descriptors to objects of the specified type.
     *
     *
     */
    public static function toDomainObjectArray(array $items, string $class, Domain $domain): array
    {
        array_walk($items, function (&$value, $name) use ($class, $domain) {
            $value = new $class($name, $domain, $value);
        });
        return $items;
    }
}
