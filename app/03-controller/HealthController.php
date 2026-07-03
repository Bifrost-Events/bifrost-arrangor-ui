<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BackendApiClient;
use App\Support\Response;

final class HealthController
{
    public function __invoke(): array
    {
        $health = (new BackendApiClient())->health();

        return Response::json([
            'arrangor_ui' => 'ok',
            'backend' => $health['ok'] ? ($health['data'] ?? []) : ['error' => $health['error']],
        ], $health['ok'] ? 200 : 503);
    }
}
