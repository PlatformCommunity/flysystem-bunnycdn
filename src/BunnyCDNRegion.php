<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

class BunnyCDNRegion
{
    public const FALKENSTEIN = 'de';

    public const STOCKHOLM = 'se';

    public const NEW_YORK = 'ny';

    public const LOS_ANGELES = 'la';

    public const SINGAPORE = 'sg';

    public const SYDNEY = 'syd';

    public const UNITED_KINGDOM = 'uk';

    public const BRAZIL = 'br';

    public const JOHANNESBURG = 'jh';

    public const DEFAULT = self::FALKENSTEIN;

    /**
     * @deprecated Use LOS_ANGELES instead.
     */
    public const LOS_ANGELAS = 'la';
}
