<?php

/*
 * Copyright (c) 2018 Adil Kachbat and contributors
 * Copyright (c) 2023-2026 Gecka
 *
 * For the full copyright and license notice, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SocialiteProviders\NcConnect;

use SocialiteProviders\Manager\OAuth2\User as BaseUser;

class User extends BaseUser
{
    /**
     * The user's id token hint (used for logout).
     */
    public ?string $tokenId = null;

    public function setTokenId(?string $tokenId): static
    {
        $this->tokenId = $tokenId;

        return $this;
    }

    public function getPreferredUsername(): string
    {
        return $this->attributes['preferred_username'] ?? '';
    }

    public function getGivenName(): string
    {
        return $this->attributes['given_name'] ?? '';
    }

    public function getFirstName(): string
    {
        return $this->attributes['first_name'] ?? '';
    }

    public function getFamilyName(): string
    {
        return $this->attributes['family_name'] ?? '';
    }

    public function isEmailVerified(): bool
    {
        return (bool) ($this->attributes['email_verified'] ?? false);
    }

    /**
     * Identity verification level.
     *
     * 0 = unverified, 1 = declarative, 2 = digitally verified.
     */
    public function getVerifiedLevel(): int
    {
        return (int) ($this->attributes['verified'] ?? 0);
    }

    public function getBirthdate(): string
    {
        return $this->attributes['birthdate'] ?? '';
    }

    public function getGender(): string
    {
        return $this->attributes['gender'] ?? '';
    }

    public function getBirthplace(): string
    {
        return $this->attributes['birthplace'] ?? '';
    }
}
