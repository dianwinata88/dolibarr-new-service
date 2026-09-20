<?php

declare(strict_types=1);

namespace App\ThirdParty;

use Doctrine\DBAL\Connection;

/**
 * Port of forgeSQLFromUniversalSearchCriteria() + dolCheckFilters() +
 * dolForgeSQLCriteriaCallback() (core/lib/functions.lib.php).
 *
 * Converts the Dolibarr Universal Search Filter syntax
 * e.g. "(t.nom:like:'A%') and (t.date_creation:>:'2016-01-01')" into a SQL
 * WHERE fragment. Errors return "Filter error - ..."/"1 = 2" strings like
 * upstream so callers can forward them verbatim.
 */
final class UniversalSearchFilter
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return string SQL fragment starting with " AND (" or an error string,
     *         '' when $filter is empty
     */
    public function forge(string $filter, string &$errorstr = '', bool $noand = false, bool $nopar = false, bool $noerror = false): string
    {
        if ($filter === '') {
            return '';
        }
        if (!preg_match('/^\(.*\)$/', $filter)) {
            $filter = '(' . $filter . ')';
        }

        $regexstring = '/\(([a-zA-Z0-9_\.]+:[<>!=insotlke]+:[^\(\)]+)\)/i';
        $firstandlastparenthesis = 0;

        if (!$this->checkFilters($filter, $errorstr, $firstandlastparenthesis)) {
            return $noerror ? '1 = 2' : 'Filter syntax error - ' . $errorstr;
        }

        // Syntax test: replace every "(field:op:value)" with "()" and drop
        // allowed words; anything left means the syntax was wrong.
        $t = (string) preg_replace_callback($regexstring, fn ($m) => $this->dummyCallback($m), $filter);
        $t = str_ireplace(['and', 'or', ' '], '', $t);

        if (preg_match('/[^\(\)]/', $t)) {
            $errorstr = 'Bad syntax of the search string: ' . $filter;

            return $noerror ? '1 = 2' : 'Filter error - Bad syntax of the search string';
        }

        $ret = ($noand ? '' : ' AND ') . ($nopar ? '' : '(');
        $ret .= (string) preg_replace_callback($regexstring, fn ($m) => $this->criteriaCallback($m), $filter);
        $ret .= ($nopar ? '' : ')');

        $ret = str_replace('__NOW__', "'" . $this->db->quote(date('Y-m-d H:i:s')) . "'", $ret);
        // __USER_ID__ has no user context in the API service: use the
        // configured API user id like upstream would use $user->id.
        $ret = str_replace('__USER_ID__', '0', $ret);

        return $ret;
    }

    /**
     * Port of dolCheckFilters(): verify the balance of parenthesis.
     */
    public function checkFilters(string $sqlfilters, string &$error = '', int &$parenthesislevel = 0): bool
    {
        $nb = strlen($sqlfilters);
        $counter = 0;
        $parenthesislevel = 0;
        $error = '';

        for ($i = 0; $i < $nb; $i++) {
            $char = $sqlfilters[$i];

            if ($char === '(') {
                if ($i === $parenthesislevel && $parenthesislevel === $counter) {
                    $parenthesislevel++;
                }
                $counter++;
            } elseif ($char === ')') {
                $nbcharremaining = ($nb - $i - 1);
                if ($nbcharremaining >= $counter) {
                    $parenthesislevel = min($parenthesislevel, $counter - 1);
                }
                if ($parenthesislevel > $counter && $nbcharremaining >= $counter) {
                    $parenthesislevel = $counter;
                }
                $counter--;
            }

            if ($counter < 0) {
                $error = 'Wrong balance of parenthesis in sqlfilters=' . $sqlfilters;
                $parenthesislevel = 0;

                return false;
            }
        }

        if ($counter > 0) {
            $error = 'Wrong balance of parenthesis in sqlfilters=' . $sqlfilters;
            $parenthesislevel = 0;

            return false;
        }

        return true;
    }

    /** @param string[] $matches */
    private function dummyCallback(array $matches): string
    {
        if (empty($matches[1])) {
            return '';
        }
        $tmp = explode(':', $matches[1]);
        if (count($tmp) < 3) {
            return '';
        }

        return '()';
    }

    /** @param string[] $matches */
    private function criteriaCallback(array $matches): string
    {
        if (empty($matches[1])) {
            return '';
        }
        $tmp = explode(':', $matches[1], 3);
        if (count($tmp) < 3) {
            return '';
        }

        $forbiddenfields = ['pass', 'pass_crypted', 'api_key'];

        $operand = (string) preg_replace('/[^a-z0-9\._]/i', '', trim($tmp[0]));

        $operandwithoutprefix = (string) preg_replace('/^[a-z0-9_]+\./i', '', $operand);
        if (in_array(strtolower($operandwithoutprefix), $forbiddenfields, true)) {
            return '1=1';
        }

        $operator = strtoupper((string) preg_replace('/[^a-z<>!=]/i', '', trim($tmp[1])));

        $realOperator = [
            'NOTLIKE' => 'NOT LIKE',
            'ISNOT' => 'IS NOT',
            'NOTIN' => 'NOT IN',
            '!=' => '<>',
        ];
        if (array_key_exists($operator, $realOperator)) {
            $operator = $realOperator[$operator];
        }

        $tmpescaped = $tmp[2];

        if ($operator === 'IN' || $operator === 'NOT IN') {
            $tmpescaped2 = '(';
            $tmpelemarray = explode(',', $tmpescaped);
            foreach ($tmpelemarray as $tmpkey => $tmpelem) {
                $tmpelem = trim($tmpelem);
                if (preg_match('/^\'(.*)\'$/', $tmpelem, $reg)) {
                    $tmpelemarray[$tmpkey] = "'" . $this->escape($this->sanitizeSql($reg[1])) . "'";
                } elseif (preg_match('/^[0-9]+$/', (string) $tmpelem)) {
                    $tmpelemarray[$tmpkey] = (int) $tmpelem;
                } elseif (is_numeric((string) $tmpelem)) {
                    $tmpelemarray[$tmpkey] = (float) $tmpelem;
                } else {
                    $tmpelemarray[$tmpkey] = preg_replace('/[^a-z0-9_]/i', '', $tmpelem);
                }
            }
            $tmpescaped2 .= implode(',', $tmpelemarray);
            $tmpescaped2 .= ')';
            $tmpescaped = $tmpescaped2;
        } elseif ($operator === 'LIKE' || $operator === 'NOT LIKE') {
            if (preg_match('/^\'([^\']*)\'$/', $tmpescaped, $regbis)) {
                $tmpescaped = $regbis[1];
            }
            $tmpescaped = "'" . $this->escape($tmpescaped) . "'";
        } elseif (preg_match('/^\'(.*)\'$/', $tmpescaped, $regbis)) {
            $tmpescaped = "'" . $this->escape($regbis[1]) . "'";
        } else {
            if (strtoupper($tmpescaped) === 'NULL') {
                $tmpescaped = 'NULL';
            } elseif (preg_match('/^[0-9]+$/', (string) $tmpescaped)) {
                $tmpescaped = (int) $tmpescaped;
            } elseif (is_numeric((string) $tmpescaped)) {
                $tmpescaped = (float) $tmpescaped;
            } else {
                $tmpescaped = preg_replace('/[^a-z0-9_]/i', '', $tmpescaped);
            }
        }

        return '(' . $this->escapeIdentifier($operand) . ' ' . $operator . ' ' . $tmpescaped . ')';
    }

    /** Equivalent of $db->escape() (only escapes quotes for SQL literals). */
    private function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    /** Equivalent of $db->escape() applied to a field operand. */
    private function escapeIdentifier(string $value): string
    {
        return $this->escape($value);
    }

    /**
     * Port of $db->sanitize($str, 2, 1, 1, 1): keep alphanum and a small
     * punctuation whitelist inside IN-list elements.
     */
    private function sanitizeSql(string $str): string
    {
        return (string) preg_replace('/[^a-z0-9_\-\'\. ,]/i', '', $str);
    }
}
