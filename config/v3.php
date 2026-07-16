<?php

declare(strict_types=1);

/**
 * V3-arrangørportalen er standard. Sett ORGANIZER_PORTAL_V3_ENABLED=false for å deaktivere.
 */
return [
    'enabled' => !in_array(
        strtolower((string) ($_ENV['ORGANIZER_PORTAL_V3_ENABLED'] ?? 'true')),
        ['false', '0', 'no', 'off'],
        true,
    ),
    // Brukerrettede URL-er er funksjonsbaserte (PortalPaths). Ingen /portal-v3-prefix.
    'route_prefix' => '',
    'auth_bypass' => ($_ENV['PORTAL_V3_AUTH_BYPASS'] ?? 'false') === 'true'
        && ($_ENV['APP_ENV'] ?? 'production') === 'development',
];
