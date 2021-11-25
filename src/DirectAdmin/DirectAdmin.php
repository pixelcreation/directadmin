<?php

/*
 * DirectAdmin API Client
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omines\DirectAdmin;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use JsonException;
use Omines\DirectAdmin\Context\AdminContext;
use Omines\DirectAdmin\Context\ResellerContext;
use Omines\DirectAdmin\Context\UserContext;
use Omines\DirectAdmin\Utility\Conversion;

/**
 * DirectAdmin API main class, encapsulating a specific account connection to a single server.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class DirectAdmin
{
    public const ACCOUNT_TYPE_ADMIN    = 'admin';
    public const ACCOUNT_TYPE_RESELLER = 'reseller';
    public const ACCOUNT_TYPE_USER     = 'user';

    /** @var string */
    private string $authenticatedUser;

    /** @var string */
    private string $username;

    /** @var string */
    private string $baseUrl;

    /** @var Client */
    private Client $connection;

    /**
     * Connects to DirectAdmin with an admin account.
     *
     * @param string $url      The base URL of the DirectAdmin server
     * @param string $username The username of the account
     * @param string $password The password of the account
     * @param bool   $validate Whether to ensure the account exists and is of the correct type
     */
    public static function connectAdmin(string $url, string $username, string $password, bool $validate = false): AdminContext
    {
        return new AdminContext(new self($url, $username, $password), $validate);
    }

    /**
     * Connects to DirectAdmin with a reseller account.
     *
     * @param string $url      The base URL of the DirectAdmin server
     * @param string $username The username of the account
     * @param string $password The password of the account
     * @param bool   $validate Whether to ensure the account exists and is of the correct type
     */
    public static function connectReseller(string $url, string $username, string $password, bool $validate = false): ResellerContext
    {
        return new ResellerContext(new self($url, $username, $password), $validate);
    }

    /**
     * Connects to DirectAdmin with a user account.
     *
     * @param string $url      The base URL of the DirectAdmin server
     * @param string $username The username of the account
     * @param string $password The password of the account
     * @param bool   $validate Whether to ensure the account exists and is of the correct type
     */
    public static function connectUser(string $url, string $username, string $password, bool $validate = false): UserContext
    {
        return new UserContext(new self($url, $username, $password), $validate);
    }

    /**
     * Creates a connection wrapper to DirectAdmin as the specified account.
     *
     * @param string $url      The base URL of the DirectAdmin server
     * @param string $username The username of the account
     * @param string $password The password of the account
     */
    protected function __construct(string $url, string $username, private string $password)
    {
        $accounts                = explode('|', $username);
        $this->authenticatedUser = current($accounts);
        $this->username          = end($accounts);
        $this->baseUrl           = rtrim($url, '/') . '/';
        $this->connection        = new Client([
            'base_uri' => $this->baseUrl,
            'auth'     => [$username, $password],
        ]);
    }

    /**
     * Returns the username behind the current connection.
     *
     * @return string Currently logged-in user's username
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Invokes the DirectAdmin API with specific options.
     *
     * @param string $method  HTTP method to use (i.e. GET or POST)
     * @param string $command DirectAdmin API command to invoke
     * @param array  $options Guzzle options to use for the call
     *
     * @return array The unvalidated response
     * @throws DirectAdminException|GuzzleException|JsonException If anything went wrong on the network level
     */
    public function invokeApi(string $method, string $command, array $options = []): array
    {
        $result = $this->rawRequest($method, '/CMD_API_' . $command, $options);
        if (!empty($result['error'])) {
            throw new DirectAdminException("$method to $command failed: $result[details] ($result[text])");
        }
        return Conversion::sanitizeArray($result);
    }

    /**
     * Returns a clone of the connection logged in as a managed user or reseller.
     *
     *
     */
    public function loginAs(string $username): DirectAdmin
    {
        // DirectAdmin format is to just pipe the accounts together under the master password
        return new self($this->baseUrl, $this->authenticatedUser . "|$username", $this->password);
    }

    /**
     * Sends a raw request to DirectAdmin.
     *
     *
     * @throws GuzzleException|JsonException
     */
    public function rawRequest(string $method, string $uri, array $options): array
    {
        try {
            $response = $this->connection->request($method, $uri, $options);
            if ($response->getHeader('Content-Type')[0] == 'text/html') {
                throw new DirectAdminException(sprintf('DirectAdmin API returned text/html to %s %s containing "%s"', $method, $uri, strip_tags($response->getBody()->getContents())));
            }
            if ($response->getHeader('Content-Type')[0] == 'application/json; charset=utf-8') {
                return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            }
            $body = $response->getBody()->getContents();
            return Conversion::responseToArray($body);
        } catch (TransferException $exception) {
            // Rethrow anything that causes a network issue
            throw new DirectAdminException(sprintf('%s request to %s failed', $method, $uri), 0, $exception);
        }
    }
}
