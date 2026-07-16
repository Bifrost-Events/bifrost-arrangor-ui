<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\Api\ApiPortalEventRepository;
use App\Repository\Api\ApiPortalSeriesRepository;
use App\Repository\Api\ApiPortalSpaceRepository;
use App\Repository\Pdo\PdoPortalDomainRepository;
use App\Repository\Pdo\PdoPortalMembershipRepository;
use App\Repository\Pdo\PdoPortalOrganizationLookupRepository;
use App\Repository\Pdo\PdoPortalSpaceParticipationRepository;
use App\Repository\Pdo\PdoPortalUserRepository;
use App\Service\Policy\PortalEventPolicy;
use App\Service\Policy\PortalEventSpacePolicy;
use App\Service\Policy\PortalOrganizationPolicy;
use App\Service\Policy\PortalSeriesPolicy;
use App\Support\Database;
use PDO;

final class PortalV3Services
{
    public readonly OrganizationContextService $organizationContext;
    public readonly PortalDomainContext $domainContext;
    public readonly EventSpaceService $eventSpaces;
    public readonly SeriesService $series;
    public readonly EventService $events;
    public readonly PortalEventLabelResolver $labels;
    public readonly PortalV3AuthService $auth;
    public readonly PortalOrganizationPolicy $organizationPolicy;
    public readonly PortalEventSpacePolicy $spacePolicy;
    public readonly PortalSeriesPolicy $seriesPolicy;
    public readonly PortalEventPolicy $eventPolicy;
    public readonly PdoPortalSpaceParticipationRepository $spaceParticipation;
    public readonly PdoPortalOrganizationLookupRepository $organizations;

    public function __construct(?PDO $pdo = null)
    {
        $pdo = $pdo ?? Database::pdo();
        $memberships = new PdoPortalMembershipRepository($pdo);
        $orgPolicy = new PortalOrganizationPolicy($memberships);
        $this->organizationPolicy = $orgPolicy;
        $this->organizationContext = new OrganizationContextService($pdo, $memberships);
        $this->domainContext = new PortalDomainContext(new PdoPortalDomainRepository($pdo));
        $this->spaceParticipation = new PdoPortalSpaceParticipationRepository($pdo);
        $this->organizations = new PdoPortalOrganizationLookupRepository($pdo);

        $spacePolicy = new PortalEventSpacePolicy($orgPolicy, $this->spaceParticipation);
        $seriesPolicy = new PortalSeriesPolicy($orgPolicy, $this->spaceParticipation);
        $this->spacePolicy = $spacePolicy;
        $this->seriesPolicy = $seriesPolicy;
        $this->eventPolicy = new PortalEventPolicy($orgPolicy);

        $api = new EventsApiClient();
        $spaceRepo = new ApiPortalSpaceRepository($api);
        $seriesRepo = new ApiPortalSeriesRepository($api);
        $eventRepo = new ApiPortalEventRepository($api);

        $this->eventSpaces = new EventSpaceService(
            $spacePolicy,
            $spaceRepo,
            $this->organizationContext,
            $this->domainContext,
        );
        $this->series = new SeriesService($seriesPolicy, $seriesRepo);
        $this->events = new EventService(
            $this->eventPolicy,
            $seriesPolicy,
            $eventRepo,
            $seriesRepo,
            $this->organizationContext,
        );
        $this->labels = new PortalEventLabelResolver($spaceRepo);

        $this->auth = new PortalV3AuthService(new PdoPortalUserRepository($pdo));
    }
}
