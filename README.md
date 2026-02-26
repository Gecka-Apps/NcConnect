# NcConnect

[![CI](https://github.com/Gecka-Apps/NcConnect/actions/workflows/ci.yml/badge.svg)](https://github.com/Gecka-Apps/NcConnect/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4)](https://www.php.net)
[![Laravel Socialite](https://img.shields.io/badge/Socialite-Provider-FF2D20)](https://socialiteproviders.com)

NC Connect OAuth2 Provider for Laravel Socialite.

## About

NcConnect is a [Laravel Socialite](https://laravel.com/docs/socialite) provider for [NC Connect](https://connect.gouv.nc), the authentication platform of the Government of New Caledonia. It implements the OpenID Connect protocol over the NC Connect V3 (Keycloak) infrastructure.

## Features

- OAuth2 / OpenID Connect authentication flow
- Nonce validation on id_token (replay attack protection)
- User profile with identity claims (name, email, birthdate, gender, etc.)
- Typed accessors on the User object for NC Connect specific claims
- Token refresh support (V3 tokens expire after 30 minutes)
- Logout with post-redirect URI (RP-Initiated Logout)
- Configurable authentication method (`client_secret_basic` or `client_secret_post`)
- Automatic environment switching (production / development)

## Requirements

- PHP 8.2+
- Laravel 10+
- A registered NC Connect client (contact connect@gouv.nc)

## Installation

```bash
composer require gecka/socialite-ncconnect
```

### Configuration

Add to `config/services.php`:

```php
'ncconnect' => [
    'client_id' => env('NCCONNECT_CLIENT_ID'),
    'client_secret' => env('NCCONNECT_CLIENT_SECRET'),
    'redirect' => env('NCCONNECT_REDIRECT_URI'),
    'force_dev' => env('NCCONNECT_FORCE_DEV'),
    'logout_redirect' => env('NCCONNECT_LOGOUT_REDIRECT'),
    'auth_method' => env('NCCONNECT_AUTH_METHOD', 'client_secret_basic'),
],
```

### Register the provider

**Laravel 11+** — in `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\NcConnect\NcConnectExtendSocialite;

public function boot(): void
{
    Event::listen(SocialiteWasCalled::class, NcConnectExtendSocialite::class.'@handle');
}
```

**Laravel 10** — in `app/Providers/EventServiceProvider.php`:

```php
protected $listen = [
    \SocialiteProviders\Manager\SocialiteWasCalled::class => [
        \SocialiteProviders\NcConnect\NcConnectExtendSocialite::class.'@handle',
    ],
];
```

## Usage

### Authentication

```php
// Redirect to NC Connect
return Socialite::driver('ncconnect')->redirect();

// Handle callback
$user = Socialite::driver('ncconnect')->user();
```

### Refreshing tokens

Access tokens expire after 30 minutes in V3. Use the built-in `refreshToken()` method:

```php
$token = Socialite::driver('ncconnect')->refreshToken($refreshToken);

$token->token;        // new access token
$token->refreshToken; // new refresh token
$token->expiresIn;    // expiry in seconds
```

### Logout

The `generateLogoutURL()` method builds an [RP-Initiated Logout](https://openid.net/specs/openid-connect-rpinitiated-1_0.html) URL.

```php
// Basic logout (uses logout_redirect from config)
$logoutUrl = Socialite::driver('ncconnect')->generateLogoutURL();

// With id_token_hint (recommended — enables seamless logout)
$logoutUrl = Socialite::driver('ncconnect')->generateLogoutURL($idTokenHint);

// With a custom post-logout redirect URI
$logoutUrl = Socialite::driver('ncconnect')->generateLogoutURL($idTokenHint, 'https://example.com/logged-out');

// Without arguments and no logout_redirect config: returns the bare logout endpoint
```

The `id_token_hint` is available on the User object after authentication:

```php
$user = Socialite::driver('ncconnect')->user();
$user->tokenId; // store this for logout
```

## User object

The returned `User` object extends the Socialite base user with typed accessors for NC Connect claims:

| Accessor | Return type | Description |
|---|---|---|
| `$user->id` | `string` | Unique identifier (sub) |
| `$user->email` | `?string` | Email address |
| `$user->isEmailVerified()` | `bool` | Whether the email is verified |
| `$user->getVerifiedLevel()` | `int` | Verification level (0 = unverified, 1 = declarative, 2 = digital) |
| `$user->getPreferredUsername()` | `string` | Display name |
| `$user->getGivenName()` | `string` | All given names |
| `$user->getFirstName()` | `string` | First given name only |
| `$user->getFamilyName()` | `string` | Family name |
| `$user->getBirthdate()` | `string` | Date of birth (YYYY-MM-DD) |
| `$user->getGender()` | `string` | Gender (male/female) |
| `$user->getBirthplace()` | `string` | Place of birth |
| `$user->tokenId` | `?string` | ID token hint (for logout) |
| `$user->token` | `string` | Access token |
| `$user->refreshToken` | `?string` | Refresh token |
| `$user->expiresIn` | `int` | Token expiry in seconds |

All attributes are also accessible via `$user->getRaw()` for the full userinfo response.

## Scopes

Default: `openid`, `identite_pivot`, `profile`, `email`

Available: `openid`, `profile`, `email`, `birth`, `identite_pivot`

## Authentication methods

| Method | Config value | Description |
|---|---|---|
| Client Secret Basic | `client_secret_basic` (default) | Credentials sent as Basic auth header |
| Client Secret Post | `client_secret_post` | Credentials sent in POST body |

## Configuration reference

| Env variable | Description | Default |
|---|---|---|
| `NCCONNECT_CLIENT_ID` | OAuth2 client ID | — |
| `NCCONNECT_CLIENT_SECRET` | OAuth2 client secret | — |
| `NCCONNECT_REDIRECT_URI` | Callback URL after login | — |
| `NCCONNECT_LOGOUT_REDIRECT` | Redirect URL after logout | — |
| `NCCONNECT_FORCE_DEV` | Force dev endpoints in production | — |
| `NCCONNECT_AUTH_METHOD` | Authentication method | `client_secret_basic` |

## Upgrading

Migrating from NC Connect V2 to V3 (Keycloak)? See [UPGRADE.md](UPGRADE.md).

## License

This project is released under the [MIT License](LICENSE).

## Authors

- **Adil Kachbat** <contact@akachbat.com>
- **Laurent Dinclaux** <laurent@gecka.nc> — [Gecka](https://gecka.nc)

---

Built with 🥥 and ☕ by [Gecka](https://gecka.nc) — Kanaky-New Caledonia 🇳🇨
