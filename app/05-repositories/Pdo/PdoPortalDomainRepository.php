<?php



declare(strict_types=1);



namespace App\Repository\Pdo;



use PDO;



final class PdoPortalDomainRepository

{

    public function __construct(

        private readonly PDO $pdo,

    ) {

    }



    /**

     * @return array{application_id: int, application_key: string, application_name: string, hostname: string}|null

     */

    public function findApplicationByHostname(string $hostname): ?array

    {

        $hostname = strtolower(trim($hostname));

        if ($hostname === '') {

            return null;

        }



        $stmt = $this->pdo->prepare(

            'SELECT d.hostname,

                    a.application_id,

                    a.application_key,

                    a.name AS application_name

             FROM app_domains d

             INNER JOIN app_applications a ON a.application_id = d.application_id

             WHERE d.hostname = :hostname

               AND d.deleted_at IS NULL

               AND d.status = \'active\'

               AND a.deleted_at IS NULL

             LIMIT 1'

        );

        $stmt->execute(['hostname' => $hostname]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {

            return null;

        }



        return [

            'application_id' => (int) ($row['application_id'] ?? 0),

            'application_key' => (string) ($row['application_key'] ?? ''),

            'application_name' => (string) ($row['application_name'] ?? ''),

            'hostname' => (string) ($row['hostname'] ?? $hostname),

        ];

    }

}

