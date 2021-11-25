<?php

/*
 * DirectAdmin API Client
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omines\DirectAdmin\Objects\Email;

use GuzzleHttp\Psr7\Query;
use Omines\DirectAdmin\Objects\Domain;

/**
 * Encapsulates a full mailbox with POP/IMAP/webmail access.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class Mailbox extends MailObject
{
    public const CACHE_DATA = 'mailbox';

    /**
     * Construct the object.
     *
     * @param string      $prefix The part before the @ in the address
     * @param Domain      $domain The containing domain
     * @param string|null $config URL encoded config string as returned by CMD_API_POP
     */
    public function __construct($prefix, Domain $domain, string $config = null)
    {
        parent::__construct($prefix, $domain);
        if (isset($config)) {
            $this->setCache(self::CACHE_DATA, Query::parse($config));
        }
    }

    /**
     * Creates a new mailbox.
     *
     * @param Domain   $domain    Domain to add the account to
     * @param string   $prefix    Prefix for the account
     * @param string   $password  Password for the account
     * @param int|null $quota     Quota in megabytes, or zero/null for unlimited
     * @param int|null $sendLimit Send limit, or 0 for unlimited, or null for system default
     *
     * @return Mailbox The created mailbox
     */
    public static function create(Domain $domain, string $prefix, string $password, int $quota = null, int $sendLimit = null): Mailbox
    {
        $domain->invokePost('POP', 'create', [
            'user'    => $prefix,
            'passwd'  => $password,
            'passwd2' => $password,
            'quota'   => $quota ?? 0,
            'limit'   => $sendLimit ?? null,
        ]);
        return new self($prefix, $domain);
    }

    /**
     * Deletes the mailbox.
     */
    public function delete()
    {
        $this->invokeDelete('POP', 'user');
    }

    /**
     * Reset the password for this mailbox.
     */
    public function setPassword(string $newPassword)
    {
        $this->invokePost('POP', 'modify', [
            'user'    => $this->getPrefix(),
            'passwd'  => $newPassword,
            'passwd2' => $newPassword,
        ], false);
    }

    /**
     * Returns the disk quota in megabytes.
     */
    public function getDiskLimit(): ?float
    {
        return floatval($this->getData('quota')) ?: null;
    }

    /**
     * Returns the disk usage in megabytes.
     */
    public function getDiskUsage(): float
    {
        return floatval($this->getData('usage'));
    }

    /**
     * Return the amount of mails sent in the current period.
     */
    public function getMailsSent(): int
    {
        return intval($this->getData('sent'));
    }

    /**
     * Return the maximum number of mails that can be sent each day
     */
    public function getMailLimit(): int
    {
        return intval($this->getData('limit'));
    }

    /**
     * Returns if the mailbox is suspended or not
     */
    public function getMailSuspended(): bool
    {
        return (strcasecmp($this->getData('suspended'), "yes") == 0);
    }


    /**
     * Cache wrapper to keep mailbox stats up to date.
     *
     *
     */
    protected function getData(string $key): mixed
    {
        return $this->getCacheItem(self::CACHE_DATA, $key, function () {
            $result = $this->getContext()->invokeApiGet('POP', [
                'domain' => $this->getDomainName(),
                'action' => 'full_list',
            ]);

            return Query::parse($result[$this->getPrefix()]);
        });
    }
}
