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
use Omines\DirectAdmin\DirectAdminException;

/**
 * Basic wrapper around a DirectAdmin object as observed within a specific context.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
abstract class BaseObject
{
    private array $cache = [];

    /**
     * @param string      $name    Canonical name for the object
     * @param UserContext $context Context within which the object is valid
     */
    protected function __construct(private string $name, private UserContext $context)
    {
    }

    /**
     * Clear the object's internal cache.
     */
    public function clearCache()
    {
        $this->cache = [];
    }

    /**
     * Retrieves an item from the internal cache.
     *
     * @param string         $key     Key to retrieve
     * @param callable|mixed $default Either a callback or an explicit default value
     *
     * @return mixed Cached value
     */
    protected function getCache(string $key, mixed $default): mixed
    {
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = is_callable($default) ? $default() : $default;
        }
        return $this->cache[$key];
    }

    /**
     * Retrieves a keyed item from inside a cache item.
     *
     * @param callable|mixed $defaultKey
     * @param mixed|null     $defaultItem
     *
     * @return mixed Cached value
     *
     * @codeCoverageIgnore
     */
    protected function getCacheItem(string $key, string $item, mixed $defaultKey, mixed $defaultItem = null): mixed
    {
        if (empty($cache = $this->getCache($key, $defaultKey))) {
            return $defaultItem;
        }
        if (!is_array($cache)) {
            throw new DirectAdminException("Cache item $key is not an array");
        }
        return $cache[$item] ?? $defaultItem;
    }

    /**
     * Sets a specific cache item, for when a cacheable value was a by-product.
     */
    protected function setCache(string $key, mixed $value)
    {
        $this->cache[$key] = $value;
    }

    public function getContext(): UserContext
    {
        return $this->context;
    }

    /**
     * Protected as a derived class may want to offer the name under a different name.
     */
    protected function getName(): string
    {
        return $this->name;
    }

    /**
     * Converts an array of string items to an associative array of objects of the specified type.
     *
     *
     */
    public static function toObjectArray(array $items, string $class, UserContext $context): array
    {
        return array_combine($items, array_map(fn($item) => new $class($item, $context), $items));
    }

    /**
     * Converts an associative array of descriptors to objects of the specified type.
     *
     *
     */
    public static function toRichObjectArray(array $items, string $class, UserContext $context): array
    {
        array_walk($items, function (&$value, $name) use ($class, $context) {
            $value = new $class($name, $context, $value);
        });
        return $items;
    }
}
