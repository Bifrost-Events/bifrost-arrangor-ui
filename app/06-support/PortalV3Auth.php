<?php

declare(strict_types=1);

namespace App\Support;

use App\Service\OrganizationContextService;

final class PortalV3Auth
{
    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        return PortalV3Session::getAuth();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function personId(): ?int
    {
        $user = self::user();
        $id = (int) ($user['person_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}|null
     */
    public static function requireLogin(): ?array
    {
        if (!self::check()) {
            return Response::redirect(PortalPaths::login());
        }

        return null;
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}|null
     */
    public static function requirePortalAccess(OrganizationContextService $orgContext): ?array
    {
        if ($redirect = self::requireLogin()) {
            return $redirect;
        }

        $orgs = $orgContext->administrableOrganizations(self::personId() ?? 0);
        if ($orgs === []) {
            PortalV3Session::setFlash(
                'info',
                'Du har ingen organisasjon ennå. Opprett en organisasjon for å komme i gang som arrangør.'
            );

            return Response::redirect(PortalPaths::komIGang());
        }

        return null;
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}|null
     */
    public static function requireOrganizationContext(OrganizationContextService $orgContext): ?array
    {
        if ($redirect = self::requirePortalAccess($orgContext)) {
            return $redirect;
        }

        if ($orgContext->activeOrganizationId() === null) {
            // Space-first: uten aktiv org, send til cup-liste i stedet for org-velger.
            return Response::redirect(PortalPaths::cups());
        }

        return null;
    }
}
