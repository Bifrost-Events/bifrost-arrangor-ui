<?php

declare(strict_types=1);

/**
 * Arrangørportal navigasjon.
 */
return [
    'overview' => [
        'id' => 'overview',
        'label' => 'Oversikt',
        'path' => '/',
        'title' => 'Oversikt',
        'description' => 'Dashboard for arrangør og sesongstatus.',
    ],
    'sections' => [
        [
            'id' => 'competitions',
            'label' => 'Stevner',
            'items' => [
                [
                    'id' => 'competitions.list',
                    'label' => 'Mine stevner',
                    'path' => '/stevner',
                    'title' => 'Mine stevner',
                    'description' => 'Oversikt over stevner for valgt arrangør og sesong.',
                ],
                [
                    'id' => 'competitions.stevneadmin',
                    'label' => 'Stevneadmin',
                    'path' => '/stevner/stevneadmin',
                    'title' => 'Stevneadmin',
                    'description' => 'Resultater, godkjenning og stevneadministrasjon.',
                ],
            ],
        ],
        [
            'id' => 'participants',
            'label' => 'Deltakere',
            'items' => [
                [
                    'id' => 'participants.list',
                    'label' => 'Deltakerliste',
                    'path' => '/deltakere',
                    'title' => 'Deltakerliste',
                    'description' => 'Skyttere og deltakere knyttet til arrangøren.',
                ],
            ],
        ],
        [
            'id' => 'organization',
            'label' => 'Organisasjon',
            'items' => [
                [
                    'id' => 'organization.profile',
                    'label' => 'Min organisasjon',
                    'path' => '/min-organisasjon',
                    'title' => 'Min organisasjon',
                    'description' => 'Arrangørprofil, kontaktinfo og sesongstatus.',
                ],
                [
                    'id' => 'organization.members',
                    'label' => 'Medlemmer',
                    'path' => '/organisasjon/medlemmer',
                    'title' => 'Medlemmer',
                    'description' => 'Medlemmer og invitasjoner til arrangørorganisasjonen.',
                ],
            ],
        ],
    ],
];
