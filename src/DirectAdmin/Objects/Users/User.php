<?php

/*
 * DirectAdmin API Client
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omines\DirectAdmin\Objects\Users;

use Omines\DirectAdmin\Context\BaseContext;
use Omines\DirectAdmin\Context\ResellerContext;
use Omines\DirectAdmin\Context\UserContext;
use Omines\DirectAdmin\DirectAdmin;
use Omines\DirectAdmin\DirectAdminException;
use Omines\DirectAdmin\Objects\BaseObject;
use Omines\DirectAdmin\Objects\Database;
use Omines\DirectAdmin\Objects\Domain;
use Omines\DirectAdmin\Utility\Conversion;

/**
 * User.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class User extends BaseObject
{
    public const CACHE_CONFIG    = 'config';
    public const CACHE_DATABASES = 'databases';
    public const CACHE_USAGE     = 'usage';

    /** @var Domain[] * */
    private array $domains;

    /**
     * Construct the object.
     *
     * @param string      $name    Username of the account
     * @param UserContext $context The context managing this object
     * @param mixed|null  $config  An optional preloaded configuration
     */
    public function __construct($name, UserContext $context, mixed $config = null)
    {
        parent::__construct($name, $context);
        if (isset($config)) {
            $this->setCache(self::CACHE_CONFIG, $config);
        }
    }

    /**
     * Clear the object's internal cache.
     */
    public function clearCache()
    {
        unset($this->domains);
        parent::clearCache();
    }

    /**
     * Creates a new database under this user.
     *
     * @param string      $name     Database name, without <user>_ prefix
     * @param string      $username Username to access the database with, without <user>_ prefix
     * @param string|null $password Password, or null if database user already exists
     *
     * @return Database Newly created database
     */
    public function createDatabase(string $name, string $username, string $password = null): Database
    {
        $db = Database::create($this->getSelfManagedUser(), $name, $username, $password);
        $this->clearCache();
        return $db;
    }

    /**
     * Creates a new domain under this user.
     *
     * @param string     $domainName     Domain name to create
     * @param float|null $bandwidthLimit Bandwidth limit in MB, or NULL to share with account
     * @param float|null $diskLimit      Disk limit in MB, or NULL to share with account
     * @param bool|null  $ssl            Whether SSL is to be enabled, or NULL to fall back to account default
     * @param bool|null  $php            Whether PHP is to be enabled, or NULL to fall back to account default
     * @param bool|null  $cgi            Whether CGI is to be enabled, or NULL to fall back to account default
     *
     * @return Domain Newly created domain
     */
    public function createDomain(string $domainName,
                                 float  $bandwidthLimit = null,
                                 float  $diskLimit = null,
                                 bool   $ssl = null,
                                 bool   $php = null,
                                 bool   $cgi = null): Domain
    {
        $domain = Domain::create($this->getSelfManagedUser(), $domainName, $bandwidthLimit, $diskLimit, $ssl, $php, $cgi);
        $this->clearCache();
        return $domain;
    }

    /**
     * @return string The username
     */
    public function getUsername(): string
    {
        return $this->getName();
    }

    /**
     * Returns the bandwidth limit of the user.
     *
     * @return float|null Limit in megabytes, or null for unlimited
     */
    public function getBandwidthLimit(): ?float
    {
        return floatval($this->getConfig('bandwidth')) ?: null;
    }

    /**
     * Returns the current period's bandwidth usage in megabytes.
     */
    public function getBandwidthUsage(): float
    {
        return floatval($this->getUsage('bandwidth'));
    }

    /**
     * Returns the database quota of the user.
     *
     * @return int|null Limit, or null for unlimited
     */
    public function getDatabaseLimit(): ?int
    {
        return intval($this->getConfig('mysql')) ?: null;
    }

    /**
     * Returns the current number databases in use.
     */
    public function getDatabaseUsage(): int
    {
        return intval($this->getUsage('mysql'));
    }

    /**
     * Returns the disk quota of the user.
     *
     * @return float|null Limit in megabytes, or null for unlimited
     */
    public function getDiskLimit(): ?float
    {
        return floatval($this->getConfig('quota')) ?: null;
    }

    /**
     * Returns the current disk usage in megabytes.
     */
    public function getDiskUsage(): float
    {
        return floatval($this->getUsage('quota'));
    }

    /**
     * @return Domain|null The default domain for the user, if any
     */
    public function getDefaultDomain(): ?Domain
    {
        if (empty($name = $this->getConfig('domain'))) {
            return null;
        }
        return $this->getDomain($name);
    }

    /**
     * Returns maximum number of domains allowed to this user, or NULL for unlimited.
     */
    public function getDomainLimit(): ?int
    {
        return intval($this->getConfig('vdomains')) ?: null;
    }

    /**
     * Returns number of domains owned by this user.
     */
    public function getDomainUsage(): int
    {
        return intval($this->getUsage('vdomains'));
    }

    /**
     * Returns whether the user is currently suspended.
     */
    public function isSuspended(): bool
    {
        return Conversion::toBool($this->getConfig('suspended'));
    }

    /**
     * @return Domain[]
     */
    public function getDatabases(): array
    {
        return $this->getCache(self::CACHE_DATABASES, function () {
            $databases = [];
            foreach ($this->getSelfManagedContext()->invokeApiGet('DATABASES') as $fullName) {
                [$user, $db] = explode('_', $fullName, 2);
                if ($this->getUsername() != $user) {
                    throw new DirectAdminException('Username incorrect on database ' . $fullName);
                }
                $databases[$db] = new Database($db, $this, $this->getSelfManagedContext());
            }
            return $databases;
        });
    }

    public function getDomain(string $domainName): ?Domain
    {
        if (!isset($this->domains)) {
            $this->getDomains();
        }
        return $this->domains[$domainName] ?? null;
    }

    /**
     * @return Domain[]
     */
    public function getDomains(): array
    {
        if (!isset($this->domains)) {
            if (!$this->isSelfManaged()) {
                $this->domains = $this->impersonate()->getDomains();
            } else {
                $this->domains = BaseObject::toRichObjectArray($this->getContext()->invokeApiGet('ADDITIONAL_DOMAINS'), Domain::class, $this->getContext());
            }
        }
        return $this->domains;
    }

    /**
     * @return string The user type, as one of the ACCOUNT_TYPE_ constants in the DirectAdmin class
     */
    public function getType(): string
    {
        return $this->getConfig('usertype');
    }

    /**
     * @return bool Whether the user can use CGI
     */
    public function hasCGI(): bool
    {
        return Conversion::toBool($this->getConfig('cgi'));
    }

    /**
     * @return bool Whether the user can use PHP
     */
    public function hasPHP(): bool
    {
        return Conversion::toBool($this->getConfig('php'));
    }

    /**
     * @return bool Whether the user can use SSL
     */
    public function hasSSL(): bool
    {
        return Conversion::toBool($this->getConfig('ssl'));
    }

    /**
     * @return UserContext|BaseContext
     */
    public function impersonate(): UserContext|BaseContext
    {
        /** @var ResellerContext $context */
        if (!($context = $this->getContext()) instanceof ResellerContext) {
            throw new DirectAdminException('You need to be at least a reseller to impersonate');
        }
        return $context->impersonateUser($this->getUsername());
    }

    /**
     * Modifies the configuration of the user. For available keys in the array check the documentation on
     * CMD_API_MODIFY_USER in the linked document.
     *
     * @param array $newConfig Associative array of values to be modified
     * @url http://www.directadmin.com/api.html#modify
     */
    public function modifyConfig(array $newConfig)
    {
        $this->getContext()->invokeApiPost('MODIFY_USER', array_merge(
            $this->loadConfig(),
            Conversion::processUnlimitedOptions($newConfig),
            ['action' => 'customize', 'user' => $this->getUsername()]
        ));
        $this->clearCache();
    }

    /**
     * Modifies the user package
     *
     * @param $package
     */
    public function modifyPackage($package)
    {
        $this->getContext()->invokeApiPost('MODIFY_USER',
            ['action' => 'package', 'user' => $this->getUsername(), 'package' => $package]
        );
        $this->clearCache();
    }

    /**
     * Modifies the reseller package
     *
     * @param $package
     */
    public function modifyPackageReseller($package)
    {
        $this->getContext()->invokeApiPost('MODIFY_RESELLER',
            ['action' => 'package', 'user' => $this->getUsername(), 'package' => $package]
        );
        $this->clearCache();
    }

    /**
     * Generate a new one time pass or login URL
     *
     * @param $data
     */
    public function loginKeys($data): array
    {
        return $this->getContext()->invokeApiPost('LOGIN_KEYS',
            array_merge(['action' => 'create'], $data)
        );
    }

    /**
     * Create DNS records
     *
     * @param $data
     */
    public function dnsControl($data): array
    {
        return $this->getContext()->invokeApiPost('DNS_CONTROL',
            array_merge(['action' => 'add'], $data)
        );
    }

    /**
     * Loads the current dns configuration from the server.
     *
     * @param $domain
     *
     * @return string[]
     */
    public function getDnsRecords($domain): array
    {
        return $this->getContext()->invokeApiGet('DNS_CONTROL', [
            'domain'          => $domain,
            'full_mx_records' => 'yes',
            'json'            => 'yes'
        ]);
    }

    /**
     * Create FTP user
     *
     * @param $data
     */
    public function createFtpUser($data): array
    {
        return $this->getContext()->invokeApiPost('FTP',
            array_merge(['action' => 'create'], $data)
        );
    }

    /**
     * Delete FTP user
     *
     * @param $data
     */
    public function deleteFtpUser($data): array
    {
        return $this->getContext()->invokeApiPost('FTP',
            array_merge(['action' => 'delete'], $data)
        );
    }


    /**
     * Install SSL certificate
     *
     * @param $data
     */
    public function installSsl($data): array
    {
        return $this->getContext()->invokeApiPost('SSL',
            array_merge(['action' => 'save'], $data)
        );
    }

    /**
     * @param bool $newValue Whether catch-all email is enabled for this user
     */
    public function setAllowCatchall(bool $newValue)
    {
        $this->modifyConfig(['catchall' => Conversion::onOff($newValue)]);
    }

    /**
     * @param float|null $newValue New value, or NULL for unlimited
     */
    public function setBandwidthLimit(?float $newValue)
    {
        $this->modifyConfig(['bandwidth' => $newValue ?? null]);
    }

    /**
     * @param float|null $newValue New value, or NULL for unlimited
     */
    public function setDiskLimit(?float $newValue)
    {
        $this->modifyConfig(['quota' => $newValue ?? null]);
    }

    /**
     * @param int|null $newValue New value, or NULL for unlimited
     */
    public function setDomainLimit(?int $newValue)
    {
        $this->modifyConfig(['vdomains' => $newValue ?? null]);
    }

    /**
     * Constructs the correct object from the given user config.
     *
     * @param array       $config  The raw config from DirectAdmin
     * @param UserContext $context The context within which the config was retrieved
     *
     * @return Admin|Reseller|User The correct object
     * @throws DirectAdminException If the user type could not be determined
     */
    public static function fromConfig(array $config, UserContext $context): Reseller|Admin|User
    {
        $name = $config['username'];
        return match ($config['usertype']) {
            DirectAdmin::ACCOUNT_TYPE_USER     => new self($name, $context, $config),
            DirectAdmin::ACCOUNT_TYPE_RESELLER => new Reseller($name, $context, $config),
            DirectAdmin::ACCOUNT_TYPE_ADMIN    => new Admin($name, $context, $config),
            default                            => throw new DirectAdminException("Unknown user type '$config[usertype]'"),
        };
    }

    /**
     * Internal function to safe guard config changes and cache them.
     *
     * @param string $item Config item to retrieve
     *
     * @return mixed The value of the config item, or NULL
     */
    private function getConfig(string $item): mixed
    {
        return $this->getCacheItem(self::CACHE_CONFIG, $item, fn() => $this->loadConfig());
    }

    /**
     * Internal function to safe guard usage changes and cache them.
     *
     * @param string $item Usage item to retrieve
     *
     * @return mixed The value of the stats item, or NULL
     */
    private function getUsage(string $item): mixed
    {
        return $this->getCacheItem(self::CACHE_USAGE, $item, fn() => $this->getContext()->invokeApiGet('SHOW_USER_USAGE', ['user' => $this->getUsername()]));
    }

    /**
     * @return UserContext The local user context
     */
    protected function getSelfManagedContext(): UserContext
    {
        return $this->isSelfManaged() ? $this->getContext() : $this->impersonate();
    }

    /**
     * @return User The user acting as himself
     */
    protected function getSelfManagedUser(): User
    {
        return $this->isSelfManaged() ? $this : $this->impersonate()->getContextUser();
    }

    /**
     * @return bool Whether the account is managing itself
     */
    protected function isSelfManaged(): bool
    {
        return $this->getUsername() === $this->getContext()->getUsername();
    }

    /**
     * Loads the current user configuration from the server.
     */
    private function loadConfig(): array
    {
        return $this->getContext()->invokeApiGet('SHOW_USER_CONFIG', ['user' => $this->getUsername()]);
    }
}
