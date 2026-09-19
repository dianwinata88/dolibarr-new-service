<?php

declare(strict_types=1);

namespace App\BankAccount;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Verbatim port of upstream dolEncrypt() / dolDecrypt()
 * (htdocs/blockedlog/lib/securitycore.lib.php).
 *
 * Upstream encrypts iban_prefix at rest with the instance_unique_id key,
 * producing strings of the form "dolcrypt:AES-256-CTR:<iv>:<ciphertext>".
 * The key here comes from the DOLIBARR_INSTANCE_UNIQUE_ID env var; when it
 * is empty, encryption is a pass-through — exactly like upstream without an
 * instance unique id.
 */
final class DolCrypt
{
    private const CIPHER = 'AES-256-CTR';

    public function __construct(
        #[Autowire('%env(default::DOLIBARR_INSTANCE_UNIQUE_ID)%')]
        private readonly ?string $key = null,
    ) {
    }

    /**
     * Port of dolEncrypt() (obfuscation mode 'dolcrypt').
     */
    public function encrypt(?string $chain): string
    {
        if ($chain === '' || $chain === null) {
            return '';
        }

        if (preg_match('/^(dolobfuscation|dolcrypt)[^:]*:([^:]+):(.+)$/', $chain)) {
            // The $chain is already an encrypted string
            return $chain;
        }

        $key = $this->key ?? '';
        if ($key === '') {
            return $chain;
        }

        if (\function_exists('openssl_encrypt')) {
            $ivlen = openssl_cipher_iv_length(self::CIPHER);
            if ($ivlen === false || $ivlen < 1 || $ivlen > 32) {
                $ivlen = 16;
            }
            // upstream dolGetRandomBytes(): hex string of $ivlen chars
            $ivseed = bin2hex(random_bytes((int) floor($ivlen / 2)));

            // If $key is a string with several keys, we keep only the first one
            $key = (string) preg_replace('/,.*$/', '', $key);

            $newchain = (string) openssl_encrypt($chain, self::CIPHER, $key, 0, $ivseed);

            return 'dolcrypt:' . self::CIPHER . ':' . $ivseed . ':' . $newchain;
        }

        return $chain;
    }

    /**
     * Port of dolDecrypt().
     */
    public function decrypt(?string $chain, string $patterntotest = ''): string
    {
        if ($chain === '' || $chain === null) {
            return '';
        }

        $key = $this->key ?? '';

        $reg = [];
        if (preg_match('/^(dolobfuscation|dolcrypt)[^:]*:([^:]+):(.+)$/', $chain, $reg)) {
            $ciphering = $reg[2];
            if (!\function_exists('openssl_decrypt')) {
                return $chain;
            }
            if ($key === '') {
                return $chain;
            }
            $tmpexplode = explode(':', $reg[3]);
            if (!empty($tmpexplode[1])) {
                $data = $tmpexplode[1];
                $iv = $tmpexplode[0];
            } else {
                $data = $tmpexplode[0];
                $iv = '';
            }

            $newchain = '';
            foreach (explode(',', $key) as $tmpkey) {
                $decrypted = openssl_decrypt($data, $ciphering, $tmpkey, 0, $iv);
                if ($decrypted === false) {
                    continue;
                }
                $newchain = $decrypted;
                if ($patterntotest !== '' && preg_match('/^' . preg_quote($patterntotest, '/') . '/', $newchain)) {
                    break;
                }
                if (mb_check_encoding($newchain, 'UTF-8')) {
                    break;
                }
            }

            if (!mb_check_encoding($newchain, 'UTF-8')) {
                return $chain;
            }

            return $newchain;
        }

        return $chain;
    }
}
