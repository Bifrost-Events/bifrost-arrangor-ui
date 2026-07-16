<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\ArrangorView;
use App\Support\Auth;
use App\Support\PortalPaths;
use App\Support\PortalV3;
use App\Support\PortalV3Auth;
use App\Support\Response;

final class DashboardController
{
    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function __invoke(): array
    {
        if (PortalV3::isEnabled()) {
            if (PortalV3Auth::check()) {
                return Response::redirect(PortalPaths::oversikt());
            }
            if (!Auth::check()) {
                return Response::redirect(PortalPaths::login());
            }
        }

        return ArrangorView::render('overview');
    }
}
