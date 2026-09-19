<?php

declare(strict_types=1);

namespace App\Pricing\Service;

use App\Entity\Societe;
use App\Entity\SocietePrices;
use App\Entity\SocieteRemise;
use App\Pricing\DolibarrContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ports of the Societe methods that maintain customer pricing state:
 * setPriceLevel() (llx_societe.price_level + llx_societe_prices log) and
 * set_remise_client() (llx_societe.remise_client + llx_societe_remise
 * history).
 *
 * Both upstream methods run inside a transaction — callers here are
 * expected to wrap them in EntityManagerInterface::wrapInTransaction().
 */
final class ThirdpartyPricingManager
{
    /**
     * Last error string, mirroring upstream ->error usage. Null on success.
     */
    public ?string $error = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DolibarrContext $context,
    ) {
    }

    /**
     * Societe::setPriceLevel().
     *
     * @return int a negative value on failure, a positive value on success
     */
    public function setPriceLevel(Societe $company, int $priceLevel, ?int $userId): int
    {
        $this->error = null;

        if (!$company->getRowid()) {
            return -1;
        }

        $company->setPriceLevel($priceLevel);
        $this->em->persist($company);

        $log = new SocietePrices();
        $log->setDatec(new \DateTime());
        $log->setFkSoc($company->getRowid());
        $log->setPriceLevel($priceLevel);
        $log->setFkUserAuthor($userId);
        $this->em->persist($log);

        $this->em->flush();

        return 1;
    }

    /**
     * Societe::set_remise_client().
     *
     * @return int a negative value on failure (-2 when the note/reason is empty), a positive value on success
     */
    public function setRemiseClient(Societe $company, float|string $remise, string $note, ?int $userId): int
    {
        $this->error = null;

        $note = trim($note);
        if ($note === '') {
            $this->error = 'ErrorFieldRequired NoteReason';

            return -2;
        }

        if (!$company->getRowid()) {
            return -1;
        }

        $company->setRemiseClient((float) $remise);
        $this->em->persist($company);

        $history = new SocieteRemise();
        $history->setEntity($this->context->entity());
        $history->setDatec(new \DateTime());
        $history->setFkSoc($company->getRowid());
        $history->setRemiseClient((float) $remise);
        $history->setNote($note);
        $history->setFkUserAuthor($userId);
        $this->em->persist($history);

        $this->em->flush();

        return 1;
    }
}
