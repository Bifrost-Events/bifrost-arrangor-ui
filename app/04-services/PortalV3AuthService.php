<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\Pdo\PdoPortalUserRepository;
use App\Support\PortalV3;
use App\Support\PortalV3Session;
use App\Support\AdminSessionBridge;

final class PortalV3AuthService
{
    public function __construct(
        private readonly PdoPortalUserRepository $users,
    ) {
    }

    /**
     * @return array{ok: bool, user?: array<string, mixed>, error?: string}
     */
    public function login(string $email, string $password): array
    {
        $row = $this->users->findByEmail($email);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Ugyldig e-post eller passord.'];
        }

        if (($row['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => 'Kontoen er ikke aktiv.'];
        }

        $hash = (string) ($row['password_hash'] ?? '');
        $valid = $hash !== '' && password_verify($password, $hash);
        if (!$valid && PortalV3::authBypassEnabled()) {
            $valid = true;
        }

        if (!$valid) {
            return ['ok' => false, 'error' => 'Ugyldig e-post eller passord.'];
        }

        $user = $this->sessionUserFromRow($row);
        PortalV3Session::setAuth($user);
        AdminSessionBridge::syncFromPortalUser($user);
        $apiSession = AdminApiSession::establish($email, $password);
        if (!($apiSession['ok'] ?? false)) {
            PortalV3Session::clearAuth();
            AdminSessionBridge::clear();
            AdminApiSession::clear();

            return [
                'ok' => false,
                'error' => (string) ($apiSession['error'] ?? 'Kunne ikke etablere admin-sesjon.'),
            ];
        }

        return ['ok' => true, 'user' => $user];
    }

    /**
     * Opprett ny brukerkonto + person. Oppretter ikke organisasjon.
     *
     * @param array<string, mixed> $input
     * @return array{ok: bool, user?: array<string, mixed>, error?: string}
     */
    public function register(array $input): array
    {
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirm'] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '') {
            return ['ok' => false, 'error' => 'Fornavn, etternavn og e-post er påkrevd.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Ugyldig e-postadresse.'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'Passordet må være minst 8 tegn.'];
        }
        if ($password !== $passwordConfirm) {
            return ['ok' => false, 'error' => 'Passordene er ikke like.'];
        }
        if ($this->users->findByEmail($email) !== null) {
            return ['ok' => false, 'error' => 'E-postadressen er allerede registrert. Logg inn i stedet.'];
        }

        try {
            $userId = $this->users->createWithPerson([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'password' => $password,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Kunne ikke opprette konto.'];
        }

        $row = $this->users->findByEmail($email);
        if ($row === null || (int) ($row['user_id'] ?? 0) !== $userId) {
            return ['ok' => false, 'error' => 'Konto opprettet, men innlogging feilet. Prøv å logge inn.'];
        }

        $user = $this->sessionUserFromRow($row);
        PortalV3Session::setAuth($user);
        AdminSessionBridge::syncFromPortalUser($user);
        $apiSession = AdminApiSession::establish($email, $password);
        if (!($apiSession['ok'] ?? false)) {
            // Konto finnes; la brukeren logge inn på nytt hvis API-sesjon feilet.
            AdminApiSession::clear();

            return [
                'ok' => false,
                'error' => 'Konto opprettet, men admin-sesjon feilet: '
                    . (string) ($apiSession['error'] ?? 'ukjent feil')
                    . ' Prøv å logge inn.',
            ];
        }

        return ['ok' => true, 'user' => $user];
    }

    public function logout(): void
    {
        PortalV3Session::clearAuth();
        AdminSessionBridge::clear();
        AdminApiSession::clear();
    }

    /** @param array<string, mixed> $row */
    private function sessionUserFromRow(array $row): array
    {
        $name = trim((string) ($row['person_name'] ?? ''));
        if ($name === '') {
            $name = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
        }

        return [
            'user_id' => (int) ($row['user_id'] ?? 0),
            'person_id' => (int) ($row['person_id'] ?? 0),
            'email' => (string) ($row['email'] ?? ''),
            'name' => $name !== '' ? $name : (string) ($row['email'] ?? ''),
        ];
    }

    public function devBypassLogin(): bool
    {
        if (!PortalV3::authBypassEnabled()) {
            return false;
        }
        $row = $this->users->findFirstActive();
        if ($row === null) {
            return false;
        }
        PortalV3Session::setAuth($this->sessionUserFromRow($row));
        AdminSessionBridge::syncFromPortalUser($this->sessionUserFromRow($row));

        return true;
    }
}
