<?php

/*
 * Copyright (c) 2018 Adil Kachbat and contributors
 * Copyright (c) 2023-2026 Gecka
 *
 * For the full copyright and license notice, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SocialiteProviders\NcConnect;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\InvalidStateException;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use UnexpectedValueException;

class Provider extends AbstractProvider
{
    public const PROD_BASE_URL = 'https://connect.gouv.nc/v3/realms/nc-connect/';

    public const TEST_BASE_URL = 'https://connect-dev.gouv.nc/v3/realms/nc-connect/';

    public const IDENTIFIER = 'NCCONNECT';

    protected $scopes = [
        'openid',
        'identite_pivot',
        'profile',
        'email',
    ];

    protected $scopeSeparator = ' ';

    protected function getBaseUrl(): string
    {
        return app()->environment('production') && ! $this->getConfig('force_dev')
            ? self::PROD_BASE_URL
            : self::TEST_BASE_URL;
    }

    public static function additionalConfigKeys(): array
    {
        return ['logout_redirect', 'force_dev', 'auth_method'];
    }

    protected function getAuthUrl($state): string
    {
        $nonce = Str::random(40);
        $this->parameters['nonce'] = $nonce;
        $this->request->session()->put('ncconnect_nonce', $nonce);

        return $this->buildAuthUrlFromBase(
            $this->getBaseUrl().'protocol/openid-connect/auth',
            $state
        );
    }

    protected function getTokenUrl(): string
    {
        return $this->getBaseUrl().'protocol/openid-connect/token';
    }

    /**
     * Build HTTP request options with the configured auth method.
     */
    protected function buildTokenRequestOptions(array $params): array
    {
        if ($this->getConfig('auth_method') === 'client_secret_post') {
            $params['client_id'] = $this->clientId;
            $params['client_secret'] = $this->clientSecret;

            return [RequestOptions::FORM_PARAMS => $params];
        }

        return [
            RequestOptions::HEADERS => [
                'Authorization' => 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret),
            ],
            RequestOptions::FORM_PARAMS => $params,
        ];
    }

    public function getAccessTokenResponse($code): array
    {
        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUrl,
        ];

        $response = $this->getHttpClient()->post(
            $this->getTokenUrl(),
            $this->buildTokenRequestOptions($params)
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    public function user()
    {
        $user = parent::user();

        $idToken = Arr::get($this->credentialsResponseBody, 'id_token');

        $this->validateNonce($idToken);

        if ($user instanceof User) {
            $user->setTokenId($idToken);
        }

        return $user;
    }

    /**
     * Validate the nonce claim in the id_token against the session value.
     *
     * @throws InvalidStateException
     */
    protected function validateNonce(?string $idToken): void
    {
        $expectedNonce = $this->request->session()->pull('ncconnect_nonce');

        if (! $idToken || ! $expectedNonce) {
            throw new InvalidStateException('Missing nonce or id_token.');
        }

        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw new InvalidStateException('Invalid id_token format.');
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        if (! is_array($payload) || ($payload['nonce'] ?? null) !== $expectedNonce) {
            throw new InvalidStateException('Invalid nonce in id_token.');
        }
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(
            $this->getBaseUrl().'protocol/openid-connect/userinfo',
            [RequestOptions::HEADERS => ['Authorization' => 'Bearer '.$token]]
        );

        return json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        if (empty($user['sub'])) {
            throw new UnexpectedValueException('Missing "sub" claim in userinfo response.');
        }

        return (new User)->setRaw($user)->map([
            'id' => $user['sub'],
            'email' => $user['email'] ?? null,
            'email_verified' => $user['email_verified'] ?? false,
            'verified' => $user['verified'] ?? 0,
            'preferred_username' => $user['preferred_username'] ?? '',
            'given_name' => $user['given_name'] ?? '',
            'first_name' => $user['first_name'] ?? '',
            'family_name' => $user['family_name'] ?? '',
            'birthdate' => $user['birthdate'] ?? '',
            'gender' => $user['gender'] ?? '',
            'birthplace' => $user['birthplace'] ?? '',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getRefreshTokenResponse($refreshToken): array
    {
        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        $response = $this->getHttpClient()->post(
            $this->getTokenUrl(),
            $this->buildTokenRequestOptions($params)
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Generate logout URL for redirection to NcConnect.
     *
     * Without arguments, returns the bare logout endpoint.
     * With an id_token_hint and/or redirect URI, builds a full RP-Initiated Logout URL.
     */
    public function generateLogoutURL(?string $idTokenHint = null, ?string $postLogoutRedirectUri = null): string
    {
        $logoutUrl = $this->getBaseUrl().'protocol/openid-connect/logout';

        $redirectUri = $postLogoutRedirectUri ?? $this->getConfig('logout_redirect');

        if ($redirectUri === null && $idTokenHint === null) {
            return $logoutUrl;
        }

        $params = [];

        if ($redirectUri !== null) {
            $params['client_id'] = $this->clientId;
            $params['post_logout_redirect_uri'] = $redirectUri;
        }

        if ($idTokenHint !== null) {
            $params['id_token_hint'] = $idTokenHint;
        }

        return $logoutUrl.'?'.http_build_query($params);
    }
}
