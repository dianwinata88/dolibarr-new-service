<?php

declare(strict_types=1);

namespace App\BankAccount;

use App\BankAccount\ApiResource\BankAccountResource;
use App\Entity\Dictionary\Country;
use App\Entity\SocieteRib;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Maps a SocieteRib entity to the wire representation upstream produces with
 * CompanyBankAccount::fetch() + DolibarrApi::_cleanObjectDatas().
 *
 * $loaded mirrors whether upstream had called fetch() on the object being
 * serialized: the GET collection and PUT responses are built from a fetched
 * object (ref + country_code set, iban decrypted); the POST response is the
 * freshly created object (ref and country_code never populated).
 */
final class CompanyBankAccountMapper
{
    public function __construct(
        private readonly DolCrypt $dolCrypt,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function toResource(SocieteRib $rib, bool $loaded): BankAccountResource
    {
        $resource = new BankAccountResource();

        $resource->id = $rib->getRowid();
        $resource->socid = $rib->getFkSoc();
        $resource->entity = $rib->getEntity();
        $resource->type = $rib->getType();
        $resource->status = $rib->getStatus();
        $resource->label = $rib->getLabel();
        $resource->bank = $rib->getBank();
        $resource->code_banque = $rib->getCodeBanque();
        $resource->code_guichet = $rib->getCodeGuichet();
        $resource->number = $rib->getNumber();
        $resource->cle_rib = $rib->getCleRib();
        $resource->bic = $rib->getBic();
        $resource->bic_intermediate = $rib->getBicIntermediate();
        $resource->iban = $this->dolCrypt->decrypt($rib->getIbanPrefix() ?? '');
        $resource->cci = $rib->getCci();
        $resource->address = $rib->getDomiciliation();
        $resource->owner_name = $rib->getProprio();
        $resource->proprio = $rib->getProprio();
        $resource->owner_address = $rib->getOwnerAddress();
        $resource->default_rib = $rib->getDefaultRib();
        $resource->state_id = $rib->getStateId();
        $resource->currency_code = $rib->getCurrencyCode();
        $resource->model_pdf = $rib->getModelPdf();
        $resource->last_main_doc = $rib->getLastMainDoc();
        $resource->rum = $rib->getRum();
        $resource->date_rum = $this->toTimestamp($rib->getDateRum());
        $resource->frstrecur = $rib->getFrstrecur();
        $resource->last_four = $rib->getLastFour();
        $resource->card_type = $rib->getCardType();
        $resource->cvn = $rib->getCvn();
        $resource->exp_date_month = $rib->getExpDateMonth();
        $resource->exp_date_year = $rib->getExpDateYear();
        $resource->approved = $rib->getApproved();
        $resource->email = $rib->getEmail();
        $resource->ending_date = $this->toTimestamp($rib->getEndingDate());
        $resource->max_total_amount_of_all_payments = $rib->getMaxTotalAmountOfAllPayments();
        $resource->preapproval_key = $rib->getPreapprovalKey();
        $resource->starting_date = $this->toTimestamp($rib->getStartingDate());
        $resource->total_amount_of_all_payments = $rib->getTotalAmountOfAllPayments();
        $resource->stripe_card_ref = $rib->getStripeCardRef();
        $resource->stripe_account = $rib->getStripeAccount();
        $resource->ext_payment_site = $rib->getExtPaymentSite();
        $resource->extraparams = $rib->getExtraparams();
        $resource->date_signature = $this->toTimestamp($rib->getDateSignature());
        $resource->online_sign_ip = $rib->getOnlineSignIp();
        $resource->online_sign_name = $rib->getOnlineSignName();
        $resource->comment = $rib->getComment();
        $resource->ipaddress = $rib->getIpaddress();
        $resource->import_key = $rib->getImportKey();
        $resource->datec = $this->toTimestamp($rib->getDatec());
        $resource->datem = $this->toTimestamp($rib->getTms());

        if ($loaded) {
            // properties only populated by upstream fetch()
            $resource->ref = $rib->getFkSoc() . '-' . $rib->getLabel();
            $resource->fk_soc = $rib->getFkSoc();
            $resource->country_id = $rib->getFkCountry();
            $country = $rib->getFkCountry() ? $this->em->find(Country::class, $rib->getFkCountry()) : null;
            $resource->country_code = $country instanceof Country ? $country->getCode() : null;
            // upstream stores the encrypted column in prop 'iban'; iban_prefix stays unset
            $resource->iban_prefix = null;
        }

        return $resource;
    }

    /**
     * Build the reduced-per-key payload of upstream getCompanyBankAccount():
     * only the keys in BankAccountResource::LIST_KEYS.
     *
     * @return array<string, mixed>
     */
    public function toListEntry(SocieteRib $rib): array
    {
        $resource = $this->toResource($rib, true);
        $entry = [];
        foreach (BankAccountResource::LIST_KEYS as $key) {
            $entry[$key] = $resource->$key;
        }

        return $entry;
    }

    private function toTimestamp(?\DateTimeInterface $date): ?int
    {
        return $date?->getTimestamp();
    }
}
