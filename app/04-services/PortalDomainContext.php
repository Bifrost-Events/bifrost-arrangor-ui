<?php



declare(strict_types=1);



namespace App\Service;



use App\Repository\Pdo\PdoPortalDomainRepository;

use App\Support\Config;



/**

 * Mapper request-host til applikasjon via app_domains (domene styrer cup/space-liste).

 */

final class PortalDomainContext

{

    public function __construct(

        private readonly PdoPortalDomainRepository $domains,

    ) {

    }



    /**

     * @return array{application_id: int, application_key: string, application_name: string, hostname: string}|null

     */

    public function resolveFromRequest(): ?array

    {

        foreach ($this->candidateHostnames() as $host) {

            $match = $this->domains->findApplicationByHostname($host);

            if ($match !== null && ($match['application_id'] ?? 0) > 0) {

                return $match;

            }

        }



        return null;

    }



    public function applicationIdFromRequest(): ?int

    {

        $resolved = $this->resolveFromRequest();

        $id = (int) ($resolved['application_id'] ?? 0);



        return $id > 0 ? $id : null;

    }



    /** @return list<string> */

    private function candidateHostnames(): array

    {

        $hosts = [];

        $requestHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));

        $requestHost = preg_replace('/:\d+$/', '', $requestHost) ?? $requestHost;

        if ($requestHost !== '') {

            $hosts[] = $requestHost;

            // arrangor.jaktfeltcup.local → jaktfeltcup.local

            if (str_starts_with($requestHost, 'arrangor.')) {

                $hosts[] = substr($requestHost, strlen('arrangor.'));

            }

        }



        $baseUrl = trim((string) (Config::get('app.base_url') ?? $_ENV['APP_BASE_URL'] ?? ''));

        if ($baseUrl !== '') {

            $parsed = parse_url($baseUrl);

            $baseHost = strtolower((string) ($parsed['host'] ?? ''));

            if ($baseHost !== '') {

                $hosts[] = $baseHost;

                if (str_starts_with($baseHost, 'arrangor.')) {

                    $hosts[] = substr($baseHost, strlen('arrangor.'));

                }

            }

        }



        return array_values(array_unique(array_filter($hosts)));

    }

}

