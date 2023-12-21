<?php

namespace SocialiteProviders\NcConnect;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\InvalidStateException;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;

/**
 * @see Provider's documentation: https://docs.google.com/document/d/13zo1E1eVMFUmbV6ECw2YvTiM-uPBL0DBuWh5wLtzCpA/edit?pli=1
 */
class Provider extends AbstractProvider
{
    /**
     * API URLs.
     */
    public const PROD_BASE_URL = 'https://connect.gouv.nc/v2/';

    public const TEST_BASE_URL = 'https://connect-dev.gouv.nc/v2/';

    public const IDENTIFIER = 'NCCONNECT';

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = [
        'openid',
        'identite_pivot',
        'profile',
        'email',
    ];

    /**
     * {@inheritdoc}
     */
    protected $scopeSeparator = ' ';

    /**
     * Return API Base URL.
     *
     * @return string
     */
    protected function getBaseUrl()
    {
        return config('app.env') === 'production' && ! $this->getConfig('force_dev') ? self::PROD_BASE_URL : self::TEST_BASE_URL;
    }

    /**
     * {@inheritdoc}
     */
    public static function additionalConfigKeys()
    {
        return ['logout_redirect', 'force_dev'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        //It is used to prevent replay attacks
        $this->parameters['nonce'] = Str::random(20);

        return $this->buildAuthUrlFromBase($this->getBaseUrl().'/authorize', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return $this->getBaseUrl().'/token';
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenResponse($code)
    {
        $response = $this->getHttpClient()->post($this->getBaseUrl().'/token', [
            RequestOptions::HEADERS     => ['Authorization' => 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret)],
            RequestOptions::FORM_PARAMS => $this->getTokenFields($code),
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    public function user()
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException();
        }

        $response = $this->getAccessTokenResponse($this->getCode());

        $user = $this->mapUserToObject($this->getUserByToken(
            $token = Arr::get($response, 'access_token')
        ));

        //store tokenId session for logout url generation
        $this->request->session()->put('fc_token_id', Arr::get($response, 'id_token'));

        return $user->setTokenId(Arr::get($response, 'id_token'))
                    ->setToken($token)
                    ->setRefreshToken(Arr::get($response, 'refresh_token'))
                    ->setExpiresIn(Arr::get($response, 'expires_in'));
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get($this->getBaseUrl().'/userinfo', [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'                 => $user['sub'],
            'email'              => $user['email'],
            'email_verified'     => $user['email_verified'],
            'verified'           => $user['verified'] ?? 0,
            'preferred_username' => $user['preferred_username'] ?? '',

            'given_name'            => $user['given_name'] ?? '',
            'family_name'           => $user['family_name'] ?? '',
            'birthdate'             => $user['birthdate'] ?? '',
            'gender'                => $user['gender'] ?? '',
            'birthplace'            => $user['birthplace'] ?? '',
            'birthcountry'          => $user['birthcountry'] ?? '',
        ]);
    }

    /**
     *  Generate logout URL for redirection to NcConnect.
     */
    public function generateLogoutURL()
    {
        $params = [
            'post_logout_redirect_uri' => $this->getConfig('logout_redirect'),
            'id_token_hint'            => $this->request->session()->get('fc_token_id'),
        ];

        return $this->getBaseUrl().'/logout?'.http_build_query($params);
    }
}
