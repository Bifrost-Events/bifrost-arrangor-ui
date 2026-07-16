<?php

declare(strict_types=1);

namespace App\Support;

use App\Service\OrganizationContextService;
use App\Service\PortalEventLabelResolver;

final class V3Container
{
    private static ?self $instance = null;

    private function __construct(
        public readonly \PDO $pdo,
        public readonly OrganizationContextService $organizationContext,
        public readonly PortalEventLabelResolver $labels,
    ) {
    }

    public static function get(): self
    {
        if (self::$instance === null) {
            $pdo = Database::pdo();
            self::$instance = new self(
                $pdo,
                new OrganizationContextService($pdo),
                new PortalEventLabelResolver($pdo),
            );
        }

        return self::$instance;
    }
}
