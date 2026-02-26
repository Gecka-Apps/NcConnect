<?php

/*
 * Copyright (c) 2018 Adil Kachbat and contributors
 * Copyright (c) 2023-2026 Gecka
 *
 * For the full copyright and license notice, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SocialiteProviders\NcConnect;

use SocialiteProviders\Manager\SocialiteWasCalled;

class NcConnectExtendSocialite
{
    /**
     * Register the provider.
     */
    public function handle(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite('ncconnect', Provider::class);
    }
}
