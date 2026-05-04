<?php

namespace App\Services;

use phpCAS;

class CasManager
{
    protected bool $initialized = false;

    public function __construct(protected array $config) {}

    protected function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        if ($this->config['cas_debug']) {
            $logFile = is_string($this->config['cas_debug']) ? $this->config['cas_debug'] : '/tmp/phpCAS.log';
            phpCAS::setDebug($logFile);
        }

        if ($this->config['cas_verbose_errors']) {
            phpCAS::setVerbose(true);
        }

        $version = match ($this->config['cas_version']) {
            '1.0' => CAS_VERSION_1_0,
            '3.0' => CAS_VERSION_3_0,
            default => CAS_VERSION_2_0,
        };

        if ($this->config['cas_enable_saml']) {
            phpCAS::client(
                SAML_VERSION_1_1,
                $this->config['cas_hostname'],
                (int) $this->config['cas_port'],
                $this->config['cas_uri'],
                $this->config['cas_client_service'],
                ! $this->config['cas_control_session']
            );
        } else {
            phpCAS::client(
                $version,
                $this->config['cas_hostname'],
                (int) $this->config['cas_port'],
                $this->config['cas_uri'],
                $this->config['cas_client_service'],
                ! $this->config['cas_control_session']
            );
        }

        if (empty($this->config['cas_validation'])) {
            phpCAS::setNoCasServerValidation();
        } elseif ($this->config['cas_validation'] === 'self') {
            phpCAS::setCasServerCACert($this->config['cas_cert'], $this->config['cas_validate_cn']);
        } else {
            phpCAS::setCasServerCACert($this->config['cas_cert'], $this->config['cas_validate_cn']);
        }

        if (! empty($this->config['cas_masquerade'])) {
            phpCAS::setNoClearTicketsFromUrl();
        }

        $this->initialized = true;
    }

    public function authenticate(): void
    {
        $this->initialize();

        if (! empty($this->config['cas_masquerade'])) {
            return;
        }

        phpCAS::forceAuthentication();
    }

    public function checkAuthentication(): bool
    {
        $this->initialize();

        if (! empty($this->config['cas_masquerade'])) {
            return true;
        }

        return phpCAS::checkAuthentication();
    }

    public function user(): string
    {
        $this->initialize();

        if (! empty($this->config['cas_masquerade'])) {
            return $this->config['cas_masquerade'];
        }

        return phpCAS::getUser();
    }

    public function getAttributes(): array
    {
        $this->initialize();

        if (! empty($this->config['cas_masquerade'])) {
            return [];
        }

        return phpCAS::getAttributes();
    }

    public function logout(array $params = []): void
    {
        $this->initialize();

        if (! empty($this->config['cas_logout_redirect'])) {
            $params['url'] = $this->config['cas_logout_redirect'];
        }

        phpCAS::logout($params);
    }

    public function isAuthenticated(): bool
    {
        return $this->checkAuthentication();
    }
}
