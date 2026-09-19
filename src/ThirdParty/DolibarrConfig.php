<?php

declare(strict_types=1);

namespace App\ThirdParty;

/**
 * Env-backed replacement for Dolibarr globals (getDolGlobalString/Int/Bool,
 * isModEnabled, getEntity, $conf->entity, $user, $mysoc).
 *
 * Every Dolibarr constant is read from an env var of the same name, so a
 * deployment can toggle the same switches upstream exposes in llx_const.
 * Extra service-specific knobs are documented in src/ThirdParty/README.md.
 */
final class DolibarrConfig
{
    /** Elements for which getEntity() prepends the shared entity 0. */
    private const ADDZERO_ELEMENTS = ['user', 'usergroup', 'cronjob', 'c_email_templates', 'email_template', 'default_values', 'overwrite_trans'];

    public function getString(string $name, string $default = ''): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if ($value === false || $value === null) {
            return $default;
        }

        return (string) $value;
    }

    public function getInt(string $name, int $default = 0): int
    {
        $value = $this->getString($name);

        return $value === '' ? $default : (int) $value;
    }

    public function getBool(string $name, bool $default = false): bool
    {
        $value = $this->getString($name);
        if ($value === '') {
            return $default;
        }

        return $value !== '0' && $value !== 'false';
    }

    /**
     * Dolibarr module emulation. The societe module is always enabled in this
     * service; other modules are listed in the DOLIBARR_MODULES env var as a
     * comma separated list (e.g. "product,prelevement,multicurrency").
     */
    public function isModEnabled(string $module): bool
    {
        if ($module === 'societe') {
            return true;
        }

        return in_array($module, array_filter(array_map('trim', explode(',', $this->getString('DOLIBARR_MODULES')))), true);
    }

    /** Current entity (multientity company id), mirrors $conf->entity. */
    public function entity(): int
    {
        return $this->getInt('DOLIBARR_ENTITY', 1);
    }

    /**
     * Port of getEntity(): returns the comma separated entity list used inside
     * "entity IN (...)" predicates.
     */
    public function getEntity(string $element, int $shared = 1): string
    {
        // France to English element aliases, verbatim from functions.lib.php
        $element = match ($element) {
            'projet' => 'project',
            'contrat' => 'contract',
            'order_supplier' => 'supplier_order',
            'invoice_supplier' => 'supplier_invoice',
            default => $element,
        };

        $out = '';
        if (in_array($element, self::ADDZERO_ELEMENTS, true)) {
            $out .= '0,';
        }
        $out .= (string) $this->entity();

        return $out;
    }

    /** Port of setEntity(): entity to stamp on newly created objects. */
    public function setEntity(?object $currentObject = null): int
    {
        if (is_object($currentObject) && ($currentObject->id ?? 0) > 0 && (int) ($currentObject->entity ?? 0) > 0) {
            return (int) $currentObject->entity;
        }

        return $this->entity();
    }

    /** Id of the technical API user (fk_user_creat / fk_user_author). */
    public function apiUserId(): int
    {
        return $this->getInt('DOLIBARR_API_USER_ID', 0);
    }

    /** Login used in "Access not allowed for login ..." error messages. */
    public function apiUserLogin(): string
    {
        return $this->getString('DOLIBARR_API_USER_LOGIN', 'api');
    }

    /**
     * Parse MAIN_INFO_SOCIETE_COUNTRY ("rowid:code:label") like upstream
     * company configuration. Returns [rowid, code, label].
     *
     * @return array{0: int, 1: string, 2: string}
     */
    public function mysocCountry(): array
    {
        $raw = $this->getString('MAIN_INFO_SOCIETE_COUNTRY');
        if ($raw !== '' && preg_match('/^(\d+):(\w+):(.*)$/', $raw, $m)) {
            return [(int) $m[1], $m[2], $m[3]];
        }

        return [$this->getInt('MAIN_INFO_SOCIETE_COUNTRY_ID'), $this->getString('MAIN_INFO_SOCIETE_COUNTRY_CODE'), ''];
    }
}
