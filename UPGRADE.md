# Upgrading to 2.0 (NC Connect V3 / Keycloak)

NC Connect V3 migrates the authentication platform from a custom OAuth2 server to Keycloak. This package version 2.0 adds full support for the new infrastructure.

> All partners must complete their migration before the end of 2026.

## Breaking changes

### Endpoints

All endpoints moved from `/v2/` to Keycloak's OpenID Connect paths:

| Endpoint | V2 | V3 |
|---|---|---|
| Authorization | `/v2/authorize` | `/v3/realms/nc-connect/protocol/openid-connect/auth` |
| Token | `/v2/token` | `/v3/realms/nc-connect/protocol/openid-connect/token` |
| UserInfo | `/v2/userinfo` | `/v3/realms/nc-connect/protocol/openid-connect/userinfo` |
| Logout | `/v2/logout` | `/v3/realms/nc-connect/protocol/openid-connect/logout` |

> This is handled automatically by the package — no action required.

### Token expiry

Access tokens now expire after **30 minutes** (previously 2 years in V2). A `refresh_token` is provided to renew them.

### Claims

- **Added:** `first_name` — first given name only (unlike `given_name` which contains all given names)
- **Removed:** `birthcountry` — use `birthplace` instead
- All other claims remain the same

### Logout

V3 uses OpenID Connect RP-Initiated Logout. The `generateLogoutURL()` method accepts an optional `id_token_hint` parameter for seamless logout:

```php
$user = Socialite::driver('ncconnect')->user();
// Store $user->tokenId in your session/database for later use

// At logout time:
$logoutUrl = Socialite::driver('ncconnect')->generateLogoutURL($idTokenHint);
```

### Authentication methods

V3 supports: `client_secret_basic` (default), `client_secret_post`, `client_secret_jwt`, `private_key_jwt`.

This package supports `client_secret_basic` and `client_secret_post`. See [README.md](README.md#authentication-methods).

## Upgrade steps

### 1. Update the package

```bash
composer require gecka/socialite-ncconnect:^2.0
```

### 2. Update your code

- If you used `birthcountry`, replace it with `birthplace`
- The new `first_name` claim is now available on the user object
- Store `$user->tokenId` after authentication and pass it to `generateLogoutURL()` at logout time
- User claims are now available via typed accessors (e.g. `$user->getVerifiedLevel()`, `$user->isEmailVerified()`). Direct attribute access still works.

### 3. Handle shorter token lifetime

Access tokens now expire in 30 minutes. If your application stores tokens for later use, implement refresh token logic:

```php
$user = Socialite::driver('ncconnect')->user();

$user->token;        // access token (expires in 30 min)
$user->refreshToken; // use this to obtain a new access token
$user->expiresIn;    // expiry in seconds

// Later, refresh the token:
$token = Socialite::driver('ncconnect')->refreshToken($user->refreshToken);
```

### 4. Test on the development platform

OpenID configuration discovery:

- **V2 (deprecated):** `https://connect-dev.gouv.nc/.well-known/openid-configuration`
- **V3:** `https://connect-dev.gouv.nc/v3/realms/nc-connect/.well-known/openid-configuration`

Set `NCCONNECT_FORCE_DEV=true` in your `.env` to use the dev endpoints regardless of environment.
