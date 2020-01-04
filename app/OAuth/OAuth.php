<?php

namespace App\OAuth;

use Dingo\Api\Auth\Provider\Authorization;
use Dingo\Api\Routing\Route;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Exceptions\MissingScopeException;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Zend\Diactoros\ResponseFactory;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\StreamFactory;
use Zend\Diactoros\UploadedFileFactory;

class OAuth extends Authorization
{

    /**
     * @var ResourceServer
     */
    protected $server;

    /**
     * @var TokenRepository
     */
    protected $tokens;

    /**
     * @var ClientRepository
     */
    protected $clients;

    /**
     * @var UserProvider
     */
    protected $provider;

    public function __construct(
        ResourceServer $server,
        TokenRepository $repository,
        ClientRepository $clients
    )
    {
        $this->server = $server;
        $this->clients = $clients;
        $this->tokens = $repository;
    }

    public function setUserProvider(UserProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Get the providers authorization method.
     *
     * @return string
     */
    public function getAuthorizationMethod()
    {
        return 'bearer';
    }

    /**
     * Authenticate the request and return the authenticated user instance.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Dingo\Api\Routing\Route $route
     *
     * @return mixed
     * @throws AuthenticationException
     * @throws \Laravel\Passport\Exceptions\MissingScopeException|\Illuminate\Auth\AuthenticationException
     */
    public function authenticate(Request $request, Route $route)
    {
        $psr = (new PsrHttpFactory(
            new ServerRequestFactory,
            new StreamFactory,
            new UploadedFileFactory,
            new ResponseFactory
        ))->createRequest($request);

        try {
            $psr = $this->server->validateAuthenticatedRequest($psr);
        } catch (OAuthServerException $e) {
            throw new AuthenticationException;
        }

        $user = $this->provider->retrieveById(
            $psr->getAttribute('oauth_user_id') ?: null
        );

        $token = $this->tokens->find($psr->getAttribute('oauth_access_token_id'));
        if (!$token || $token->revoked || \Carbon\Carbon::now()->greaterThan($token->expires_at)) {
            throw new AuthenticationException;
        }

        $this->validateScopes($token, $route->getScopes());

        $clientId = $psr->getAttribute('oauth_client_id');

        if ($this->clients->revoked($clientId)) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * Validate token credentials.
     *
     * @param  \Laravel\Passport\Token $token
     * @param  array $scopes
     * @return void
     *
     * @throws \Laravel\Passport\Exceptions\MissingScopeException
     */
    protected function validateScopes($token, $scopes)
    {
        if (in_array('*', $token->scopes)) {
            return;
        }

        foreach ($scopes as $scope) {
            if ($token->cant($scope)) {
                throw new MissingScopeException($scope);
            }
        }
    }
}