<?php

declare(strict_types=1);

namespace App\BankAccount\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\BankAccount\Processor\BankAccountProcessor;
use App\BankAccount\Provider\BankAccountCollectionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Third-party bank account (llx_societe_rib) — API-level port of upstream
 * api_thirdparties.class.php endpoints:
 *
 *   GET    /thirdparties/{id}/bankaccounts
 *   POST   /thirdparties/{id}/bankaccounts
 *   PUT    /thirdparties/{id}/bankaccounts/{bankaccount_id}
 *   DELETE /thirdparties/{id}/bankaccounts/{bankaccount_id}
 *
 * Property names match the keys upstream serializes on CompanyBankAccount
 * (socid, iban, datem, address, owner_name, ...). Dates are unix timestamps
 * (upstream jdate()), like the monolith API.
 */
// Placeholder names are internal: 'socid' is the company id and 'id' the
// bank-account rowid (upstream calls them 'id' / 'bankaccount_id'; renaming
// keeps each uriVariable bound to the same-named DTO property).
#[ApiResource(
    outputFormats: ['json' => ['application/json']],
    operations: [
        new GetCollection(
            uriTemplate: '/thirdparties/{socid}/bankaccounts',
            uriVariables: ['socid'],
            provider: BankAccountCollectionProvider::class,
            normalizationContext: ['groups' => ['rib:list']],
        ),
        new Post(
            uriTemplate: '/thirdparties/{socid}/bankaccounts',
            uriVariables: ['socid'],
            status: 200,
            // upstream applies per-field sanitization over the raw request
            // body (POST uses the 'extrafields' field-name bug), so the
            // processor reads the raw JSON instead of a denormalized input.
            input: false,
            normalizationContext: ['groups' => ['rib:item']],
            processor: BankAccountProcessor::class,
        ),
        new Put(
            uriTemplate: '/thirdparties/{socid}/bankaccounts/{id}',
            uriVariables: ['socid', 'id'],
            read: false,
            input: false,
            normalizationContext: ['groups' => ['rib:item']],
            processor: BankAccountProcessor::class,
        ),
        new Delete(
            uriTemplate: '/thirdparties/{socid}/bankaccounts/{id}',
            uriVariables: ['socid', 'id'],
            read: false,
            processor: BankAccountProcessor::class,
        ),
    ],
)]
final class BankAccountResource
{
    /** Keys of the reduced payload upstream returns on GET collection. */
    public const LIST_KEYS = [
        'socid', 'default_rib', 'frstrecur', 'datec', 'datem',
        'label', 'bank', 'bic', 'iban', 'id', 'rum',
    ];

    #[ApiProperty(identifier: true)]
    #[Groups(['rib:list', 'rib:item'])]
    public ?int $id = null;

    #[Groups(['rib:item'])]
    public ?string $ref = null;

    #[Groups(['rib:list', 'rib:item'])]
    public ?int $socid = null;

    #[Groups(['rib:item'])]
    public ?int $fk_soc = null;

    #[Groups(['rib:item'])]
    public ?int $entity = null;

    #[Groups(['rib:item'])]
    public ?string $type = null;

    #[Groups(['rib:item'])]
    public ?int $status = null;

    #[Groups(['rib:list', 'rib:item'])]
    public ?string $label = null;

    #[Groups(['rib:list', 'rib:item'])]
    public ?string $bank = null;

    #[Groups(['rib:item'])]
    public ?string $code_banque = null;

    #[Groups(['rib:item'])]
    public ?string $code_guichet = null;

    #[Groups(['rib:item'])]
    public ?string $number = null;

    #[Groups(['rib:item'])]
    public ?string $cle_rib = null;

    #[Groups(['rib:list', 'rib:item'])]
    public ?string $bic = null;

    #[Groups(['rib:item'])]
    public ?string $bic_intermediate = null;

    /** Decrypted IBAN (upstream reads iban_prefix then dolDecrypt()s it). */
    #[Groups(['rib:list', 'rib:item'])]
    public ?string $iban = null;

    /** Upstream property exists but stays unset on API objects. */
    #[Groups(['rib:item'])]
    public ?string $iban_prefix = null;

    #[Groups(['rib:item'])]
    public ?string $cci = null;

    /** Maps to column domiciliation upstream. */
    #[Groups(['rib:item'])]
    public ?string $address = null;

    /** Maps to column proprio upstream. */
    #[Groups(['rib:item'])]
    public ?string $owner_name = null;

    #[Groups(['rib:item'])]
    public ?string $proprio = null;

    #[Groups(['rib:item'])]
    public ?string $owner_address = null;

    #[Groups(['rib:list', 'rib:item'])]
    public ?int $default_rib = null;

    #[Groups(['rib:item'])]
    public ?int $state_id = null;

    #[Groups(['rib:item'])]
    public ?int $fk_country = null;

    #[Groups(['rib:item'])]
    public ?int $country_id = null;

    /** Country code joined from llx_c_country upstream. */
    #[Groups(['rib:item'])]
    public ?string $country_code = null;

    #[Groups(['rib:item'])]
    public ?string $currency_code = null;

    #[Groups(['rib:item'])]
    public ?string $model_pdf = null;

    #[Groups(['rib:item'])]
    public ?string $last_main_doc = null;

    #[Groups(['rib:list', 'rib:item'])]
    public ?string $rum = null;

    #[Groups(['rib:item'])]
    public ?int $date_rum = null;

    #[Groups(['rib:list', 'rib:item'])]
    public ?string $frstrecur = null;

    #[Groups(['rib:item'])]
    public ?string $last_four = null;

    #[Groups(['rib:item'])]
    public ?string $card_type = null;

    #[Groups(['rib:item'])]
    public ?string $cvn = null;

    #[Groups(['rib:item'])]
    public ?int $exp_date_month = null;

    #[Groups(['rib:item'])]
    public ?int $exp_date_year = null;

    #[Groups(['rib:item'])]
    public ?int $approved = null;

    #[Groups(['rib:item'])]
    public ?string $email = null;

    #[Groups(['rib:item'])]
    public ?int $ending_date = null;

    #[Groups(['rib:item'])]
    public mixed $max_total_amount_of_all_payments = null;

    #[Groups(['rib:item'])]
    public ?string $preapproval_key = null;

    #[Groups(['rib:item'])]
    public ?int $starting_date = null;

    #[Groups(['rib:item'])]
    public mixed $total_amount_of_all_payments = null;

    #[Groups(['rib:item'])]
    public ?string $stripe_card_ref = null;

    #[Groups(['rib:item'])]
    public ?string $stripe_account = null;

    #[Groups(['rib:item'])]
    public ?string $ext_payment_site = null;

    #[Groups(['rib:item'])]
    public ?string $extraparams = null;

    #[Groups(['rib:item'])]
    public ?int $date_signature = null;

    #[Groups(['rib:item'])]
    public ?string $online_sign_ip = null;

    #[Groups(['rib:item'])]
    public ?string $online_sign_name = null;

    #[Groups(['rib:item'])]
    public ?string $comment = null;

    #[Groups(['rib:item'])]
    public ?string $ipaddress = null;

    #[Groups(['rib:item'])]
    public ?string $import_key = null;

    #[Groups(['rib:list', 'rib:item'])]
    public ?int $datec = null;

    #[Groups(['rib:list', 'rib:item'])]
    public ?int $datem = null;

    /** Inherited Account props upstream always serializes. */
    #[Groups(['rib:item'])]
    public ?int $solde = 0;

    #[Groups(['rib:item'])]
    public ?int $balance = 0;
}
