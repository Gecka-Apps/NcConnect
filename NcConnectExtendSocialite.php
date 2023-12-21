<?php

namespace SocialiteProviders\NcConnect;

use SocialiteProviders\Manager\SocialiteWasCalled;

class NcConnectExtendSocialite
{
    /**
     * Register the provider.
     *
     * @param  \SocialiteProviders\Manager\SocialiteWasCalled  $socialiteWasCalled
     */
    public function handle(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite('ncconnect', Provider::class);
    }
}
