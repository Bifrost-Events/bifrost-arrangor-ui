<?php

declare(strict_types=1);

namespace App\Support;

final class PortalV3Session
{
    private const AUTH_KEY = 'portal_v3_auth';
    private const ORG_KEY = 'portal_v3_org_id';
    private const SPACE_KEY = 'portal_v3_space_id';
    private const SEASON_KEY = 'portal_v3_season_series_id';
    private const WORK_MODE_KEY = 'portal_v3_work_mode';
    private const FLASH_KEY = 'portal_v3_flash';
    private const ONBOARDING_APP_KEY = 'portal_v3_onboarding_application_id';
    private const ONBOARDING_ORG_KEY = 'portal_v3_onboarding_org_id';
    private const ONBOARDING_SERIES_KEY = 'portal_v3_onboarding_series_id';

    public const WORK_MODE_CUP = 'cup';
    public const WORK_MODE_ARRANGER = 'arranger';

    public const ONBOARDING_STEP_ACCOUNT = 'account';
    public const ONBOARDING_STEP_ORGANIZATION = 'organization';
    public const ONBOARDING_STEP_APPLICATION = 'application';
    public const ONBOARDING_STEP_SERIES = 'series';
    public const ONBOARDING_STEP_DETAILS = 'details';
    public const ONBOARDING_STEP_DONE = 'done';

    /** @param array<string, mixed> $user */
    public static function setAuth(array $user): void
    {
        Session::startRequired();
        $_SESSION[self::AUTH_KEY] = $user;
    }

    /** @return array<string, mixed>|null */
    public static function getAuth(): ?array
    {
        Session::startRequired();
        $auth = $_SESSION[self::AUTH_KEY] ?? null;

        return is_array($auth) ? $auth : null;
    }

    public static function clearAuth(): void
    {
        Session::startRequired();
        unset(
            $_SESSION[self::AUTH_KEY],
            $_SESSION[self::ORG_KEY],
            $_SESSION[self::SPACE_KEY],
            $_SESSION[self::SEASON_KEY],
            $_SESSION[self::WORK_MODE_KEY],
            $_SESSION[self::FLASH_KEY],
            $_SESSION[self::ONBOARDING_APP_KEY],
            $_SESSION[self::ONBOARDING_ORG_KEY],
            $_SESSION[self::ONBOARDING_SERIES_KEY],
        );
    }

    public static function setOrganizationId(?int $orgId): void
    {
        Session::startRequired();
        if ($orgId === null || $orgId <= 0) {
            unset($_SESSION[self::ORG_KEY]);
            return;
        }
        $_SESSION[self::ORG_KEY] = $orgId;
    }

    public static function getOrganizationId(): ?int
    {
        Session::startRequired();
        $id = $_SESSION[self::ORG_KEY] ?? null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    public static function setSpaceId(?int $spaceId): void
    {
        Session::startRequired();
        $previous = self::getSpaceId();
        if ($spaceId === null || $spaceId <= 0) {
            unset($_SESSION[self::SPACE_KEY], $_SESSION[self::SEASON_KEY]);
            return;
        }
        $_SESSION[self::SPACE_KEY] = $spaceId;
        // Ny cup → nullstill sesongvalg (kan tilhøre annen cup).
        if ($previous !== null && $previous !== $spaceId) {
            unset($_SESSION[self::SEASON_KEY]);
        }
    }

    public static function getSpaceId(): ?int
    {
        Session::startRequired();
        $id = $_SESSION[self::SPACE_KEY] ?? null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    public static function setSeasonSeriesId(?int $seriesId): void
    {
        Session::startRequired();
        if ($seriesId === null || $seriesId <= 0) {
            unset($_SESSION[self::SEASON_KEY]);
            return;
        }
        $_SESSION[self::SEASON_KEY] = $seriesId;
    }

    public static function getSeasonSeriesId(): ?int
    {
        Session::startRequired();
        $id = $_SESSION[self::SEASON_KEY] ?? null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    /** @param self::WORK_MODE_CUP|self::WORK_MODE_ARRANGER|null $mode */
    public static function setWorkMode(?string $mode): void
    {
        Session::startRequired();
        if ($mode !== self::WORK_MODE_CUP && $mode !== self::WORK_MODE_ARRANGER) {
            unset($_SESSION[self::WORK_MODE_KEY]);

            return;
        }
        $_SESSION[self::WORK_MODE_KEY] = $mode;
    }

    /** @return self::WORK_MODE_CUP|self::WORK_MODE_ARRANGER|null */
    public static function getWorkMode(): ?string
    {
        Session::startRequired();
        $mode = $_SESSION[self::WORK_MODE_KEY] ?? null;
        if ($mode === self::WORK_MODE_CUP || $mode === self::WORK_MODE_ARRANGER) {
            return $mode;
        }

        return null;
    }

    /** @param array<string, string> $errors */
    public static function setFlash(string $type, string $message, array $errors = []): void
    {
        Session::startRequired();
        $_SESSION[self::FLASH_KEY] = ['type' => $type, 'message' => $message, 'errors' => $errors];
    }

    /** @return array{type: string, message: string, errors: array<string, string>}|null */
    public static function pullFlash(): ?array
    {
        Session::startRequired();
        $flash = $_SESSION[self::FLASH_KEY] ?? null;
        unset($_SESSION[self::FLASH_KEY]);
        if (!is_array($flash)) {
            return null;
        }

        return [
            'type' => (string) ($flash['type'] ?? 'info'),
            'message' => (string) ($flash['message'] ?? ''),
            'errors' => is_array($flash['errors'] ?? null) ? $flash['errors'] : [],
        ];
    }

    public static function setOnboardingApplicationId(?int $applicationId): void
    {
        Session::startRequired();
        if ($applicationId === null || $applicationId <= 0) {
            unset($_SESSION[self::ONBOARDING_APP_KEY]);

            return;
        }
        $previous = self::getOnboardingApplicationId();
        $_SESSION[self::ONBOARDING_APP_KEY] = $applicationId;
        if ($previous !== null && $previous !== $applicationId) {
            unset($_SESSION[self::ONBOARDING_SERIES_KEY]);
        }
    }

    public static function getOnboardingApplicationId(): ?int
    {
        Session::startRequired();
        $id = $_SESSION[self::ONBOARDING_APP_KEY] ?? null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    public static function setOnboardingOrgId(?int $orgId): void
    {
        Session::startRequired();
        if ($orgId === null || $orgId <= 0) {
            unset($_SESSION[self::ONBOARDING_ORG_KEY]);

            return;
        }
        $_SESSION[self::ONBOARDING_ORG_KEY] = $orgId;
    }

    public static function getOnboardingOrgId(): ?int
    {
        Session::startRequired();
        $id = $_SESSION[self::ONBOARDING_ORG_KEY] ?? null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    public static function setOnboardingSeriesId(?int $seriesId): void
    {
        Session::startRequired();
        if ($seriesId === null || $seriesId <= 0) {
            unset($_SESSION[self::ONBOARDING_SERIES_KEY]);

            return;
        }
        $_SESSION[self::ONBOARDING_SERIES_KEY] = $seriesId;
    }

    public static function getOnboardingSeriesId(): ?int
    {
        Session::startRequired();
        $id = $_SESSION[self::ONBOARDING_SERIES_KEY] ?? null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
