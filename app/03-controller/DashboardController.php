<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\ArrangorView;

final class DashboardController
{
    public function __invoke(): array
    {
        return ArrangorView::render('overview');
    }
}
