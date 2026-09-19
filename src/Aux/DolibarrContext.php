<?php

declare(strict_types=1);

namespace App\Aux;

/**
 * Env-backed replacement for the Dolibarr globals the sales-rep / auxiliary
 * slice relies on: getDolGlobalString/Int/Bool, isModEnabled, getEntity,
 * setEntity, $conf->entity, $user (mapped to the technical API user).
 *
 * Dolibarr constants are read from env vars of the same name so a deployment
 * can toggle the same switches upstream exposes through llx_const.
 */
final class DolibarrContext
{
    /** Elements for which getEntity() prepends the shared entity 0. */
    private const ADDZERO_ELEMENTS = [
        'user', 'usergroup', 'cronjob', 'c_email_templates',
        'email_template', 'default_values', 'overwrite_trans',
    ];

    public function getString(string $name, string $default = ''): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if ($value === false) {
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
     * comma-separated list (e.g. "product,multicompany").
     */
    public function isModEnabled(string $module): bool
    {
        if ($module === 'societe') {
            return true;
        }

        $modules = array_filter(array_map('trim', explode(',', $this->getString('DOLIBARR_MODULES'))));

        return in_array($module, $modules, true);
    }

    /** Current entity (mirrors $conf->entity). */
    public function entity(): int
    {
        return $this->getInt('DOLIBARR_ENTITY', 1);
    }

    /**
     * Port of getEntity() (functions.lib.php): returns the comma-separated
     * entity list used inside "entity IN (...)" predicates.
     *
     * Without the multicompany module this is the addzero list (user,
     * usergroup, ... -> "0,<current>") or just <current> for everything else.
     * With multicompany on, shared elements are visible on "0,<current>"
     * (the slice's "entity in (0, current)" semantics); $shared=0 restricts
     * to the current entity only.
     */
    public function getEntity(string $element, int $shared = 1): string
    {
        // France-to-English element aliases, verbatim from functions.lib.php
        $element = match ($element) {
            'projet' => 'project',
            'contrat' => 'contract',
            'order_supplier' => 'supplier_order',
            'invoice_supplier' => 'supplier_invoice',
            default => $element,
        };

        if ($this->isModEnabled('multicompany')) {
            return $shared ? $this->sharedEntities() : (string) $this->entity();
        }

        $addzero = self::ADDZERO_ELEMENTS;
        if ($this->getBool('HOLIDAY_ALLOW_ZERO_IN_DIC')) {
            $addzero[] = 'c_holiday_types';
        }
        $out = '';
        if (in_array($element, $addzero, true)) {
            $out .= '0,';
        }

        return $out . $this->entity();
    }

    /** "0,<current>" — the list used for entity IN (0, current) filters. */
    public function sharedEntities(): string
    {
        return '0,' . $this->entity();
    }

    /**
     * Port of setEntity(): entity to stamp on newly created objects —
     * the object's own entity when it has one, else the current entity.
     */
    public function setEntity(?object $currentObject = null): int
    {
        if (is_object($currentObject) && ($currentObject->id ?? 0) > 0 && (int) ($currentObject->entity ?? 0) > 0) {
            return (int) $currentObject->entity;
        }

        return $this->entity();
    }

    /** Id of the technical API user (fk_user_creat / fk_user / fk_user_author). */
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
     * Port of DolibarrApi::_checkAccessToResource() for third parties: an
     * internal API user sees everything; when DOLIBARR_API_SOCID is set
     * (external user emulation) the socid must be in that list.
     */
    public function checkAccessToThirdparty(int $socid): bool
    {
        $socids = $this->getString('DOLIBARR_API_SOCID');
        if ($socids === '') {
            return true;
        }

        return in_array($socid, array_map('intval', explode(',', $socids)), true);
    }
}
