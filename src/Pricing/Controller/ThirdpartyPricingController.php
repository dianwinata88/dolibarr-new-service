<?php

declare(strict_types=1);

namespace App\Pricing\Controller;

use App\Entity\Societe;
use App\Entity\SocietePrices;
use App\Entity\SocieteRemise;
use App\Pricing\DolibarrContext;
use App\Pricing\Repository\SocietePricesRepository;
use App\Pricing\Repository\SocieteRemiseExceptRepository;
use App\Pricing\Repository\SocieteRemiseRepository;
use App\Pricing\Service\DiscountManager;
use App\Pricing\Service\ThirdpartyPricingManager;
use App\Pricing\Util\Price2Num;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Customer pricing & discounts endpoints — port of the pricing-related
 * methods of htdocs/societe/class/api_thirdparties.class.php:
 *
 *   PUT    /api/thirdparties/{id}/setpricelevel/{priceLevel}
 *   GET    /api/thirdparties/{id}/fixedamountdiscounts
 *   POST   /api/thirdparties/{id}/fixedamountdiscounts
 *   POST   /api/thirdparties/{id}/splitdiscount/{discountid}
 *
 * plus read/write coverage for llx_societe_remise and llx_societe_prices
 * that upstream only exposes through the UI (comm/remise.php, remx.php).
 *
 * Error payloads use Restler's shape: {"error": {"code": N, "message": "…"}}.
 * Upstream permission checks (societe lire/creer, _checkAccessToResource) are
 * not replicable here — any caller with a valid API key is authorized.
 */
#[AsController]
#[Route('/api/thirdparties/{id}', requirements: ['id' => '\d+'])]
final class ThirdpartyPricingController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DolibarrContext $context,
        private readonly DiscountManager $discounts,
        private readonly ThirdpartyPricingManager $pricing,
        private readonly SocieteRemiseExceptRepository $remiseExceptRepo,
        private readonly SocieteRemiseRepository $remiseRepo,
        private readonly SocietePricesRepository $pricesRepo,
    ) {
    }

    /**
     * Port of api_thirdparties::setThirdpartyPriceLevel()
     * — PUT {id}/setpricelevel/{priceLevel}.
     */
    #[Route('/setpricelevel/{priceLevel}', methods: ['PUT'], requirements: ['priceLevel' => '\d+'])]
    public function setPriceLevel(int $id, int $priceLevel): JsonResponse
    {
        if (!$this->context->multipricesEnabled()) {
            return $this->error(
                Response::HTTP_NOT_IMPLEMENTED,
                'Multiprices features activation needed for this request',
            );
        }

        $limit = $this->context->multipricesLimit();
        if ($priceLevel < 1 || $priceLevel > $limit) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Price level must be between 1 and ' . $limit);
        }

        $company = $this->fetchThirdparty($id);
        if ($company === null) {
            // Upstream maps fetch()==0 to a 500 "Error fetching" response.
            return $this->error(Response::HTTP_INTERNAL_SERVER_ERROR, 'Error fetching thirdparty ' . $id);
        }

        $result = $this->pricing->setPriceLevel($company, $priceLevel, $this->context->userId());
        if ($result <= 0) {
            return $this->error(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Error setting new price level for thirdparty ' . $id,
            );
        }

        // Upstream returns the full cleaned thirdparty object; only the
        // pricing-relevant projection is available from this slice.
        return new JsonResponse([
            'id' => $company->getRowid(),
            'name' => $company->getNom(),
            'price_level' => $company->getPriceLevel(),
            'remise_percent' => $this->remiseRepo->latestRemiseClient($id),
        ]);
    }

    /**
     * GET {id}/pricelevels — llx_societe_prices history written by
     * setPriceLevel(). No upstream REST equivalent (read side of the table).
     */
    #[Route('/pricelevels', methods: ['GET'])]
    public function getPriceLevelHistory(int $id): JsonResponse
    {
        if ($this->fetchThirdparty($id) === null) {
            return $this->error(Response::HTTP_NOT_FOUND, 'Thirdparty not found');
        }

        return new JsonResponse(array_map(
            static fn (SocietePrices $p): array => [
                'rowid' => $p->getRowid(),
                'fk_soc' => $p->getFkSoc(),
                'price_level' => $p->getPriceLevel(),
                'fk_user_author' => $p->getFkUserAuthor(),
                'datec' => $p->getDatec()?->format('Y-m-d H:i:s'),
                'tms' => $p->getTms()?->format('Y-m-d H:i:s'),
            ],
            $this->pricesRepo->history($id),
        ));
    }

    /**
     * Port of api_thirdparties::getFixedAmountDiscounts()
     * — GET {id}/fixedamountdiscounts?mode=&filter=&sortfield=&sortorder=.
     */
    #[Route('/fixedamountdiscounts', methods: ['GET'])]
    public function getFixedAmountDiscounts(Request $request, int $id): JsonResponse
    {
        if ($id <= 0) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Thirdparty ID is mandatory');
        }
        if ($this->fetchThirdparty($id) === null) {
            return $this->error(Response::HTTP_NOT_FOUND, 'Thirdparty not found');
        }

        $mode = (string) $request->query->get('mode', 'customer');
        if ($mode !== 'customer' && $mode !== 'supplier') {
            // Upstream leaves the SQL empty for unknown modes and the query
            // fails with a 503.
            return $this->error(Response::HTTP_SERVICE_UNAVAILABLE, 'Invalid mode');
        }
        $filter = (string) $request->query->get('filter', 'none');
        $sortfield = (string) $request->query->get('sortfield', 'f.type');
        $sortorder = (string) $request->query->get('sortorder', 'ASC');

        return new JsonResponse(
            $this->remiseExceptRepo->findFixedAmountDiscounts($id, $mode, $filter, $sortfield, $sortorder),
        );
    }

    /**
     * Port of api_thirdparties::createFixedAmountDiscount()
     * — POST {id}/fixedamountdiscounts.
     */
    #[Route('/fixedamountdiscounts', methods: ['POST'])]
    public function createFixedAmountDiscount(Request $request, int $id): JsonResponse
    {
        $data = $this->requestData($request);

        if ($id <= 0) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Thirdparty ID is mandatory');
        }
        if (!isset($data['amount'])) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Missing required field: amount');
        }
        if (!isset($data['description'])) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Missing required field: description');
        }

        $company = $this->fetchThirdparty($id);
        if ($company === null) {
            return $this->error(Response::HTTP_NOT_FOUND, 'Error creating discount, thirdparty not found');
        }

        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Invalid amount_ht: must be a positive number');
        }
        $amount = (float) $data['amount'];

        if (isset($data['tva_tx']) && (!is_numeric($data['tva_tx']) || $data['tva_tx'] < 0)) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Invalid tva_tx: must be a positive number or zero');
        }
        $tvaTx = isset($data['tva_tx']) ? (float) $data['tva_tx'] : 0.0;

        $priceBaseType = 'HT';
        if (isset($data['price_base_type'])) {
            $priceBaseType = strtoupper((string) $data['price_base_type']);
            if ($priceBaseType !== 'HT' && $priceBaseType !== 'TTC') {
                return $this->error(Response::HTTP_BAD_REQUEST, 'Invalid price_base_type: must be "HT" or "TTC"');
            }
        }

        $discountType = 0;
        if (isset($data['discount_type'])) {
            $discountType = (int) $data['discount_type'];
            if ($discountType !== 0 && $discountType !== 1) {
                return $this->error(
                    Response::HTTP_BAD_REQUEST,
                    'Invalid discount_type: must be 0 (customer) or 1 (supplier)',
                );
            }
        }

        $description = (string) $data['description'];
        if (trim($description) === '') {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Description cannot be empty');
        }

        // Upstream only forwards the VAT rate to set_remise_except() when a
        // vat_src_code is provided — without one the rate is an empty string.
        $vatrate = '';
        if (isset($data['vat_src_code']) && $data['vat_src_code'] !== '') {
            $vatrate = $tvaTx . ' (' . $data['vat_src_code'] . ')';
        }

        $result = $this->em->wrapInTransaction(
            fn (): int => $this->discounts->setRemiseExcept(
                $company,
                $amount,
                $this->context->userId(),
                $description,
                $vatrate,
                $discountType,
                $priceBaseType,
            ),
        );

        if ($result > 0) {
            return new JsonResponse($result, Response::HTTP_CREATED);
        }

        return $this->error(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'Error creating discount: ' . ($this->discounts->error ?? 'unknown error'),
        );
    }

    /**
     * Port of api_thirdparties::splitdiscount()
     * — POST {id}/splitdiscount/{discountid}.
     *
     * amount_ttc_1 / amount_ttc_2 are accepted as query params or JSON body
     * fields (Restler binds either).
     */
    #[Route('/splitdiscount/{discountid}', methods: ['POST'], requirements: ['discountid' => '\d+'])]
    public function splitDiscount(Request $request, int $id, int $discountid): JsonResponse
    {
        $data = $this->requestData($request);
        $amountTtc1 = $data['amount_ttc_1'] ?? null;
        $amountTtc2 = $data['amount_ttc_2'] ?? null;

        if ($id <= 0) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Thirdparty ID is mandatory');
        }
        if ($discountid <= 0) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Discount ID is mandatory');
        }
        if (empty($amountTtc1) || empty($amountTtc2)) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Amount are mandatory');
        }
        if ($this->fetchThirdparty($id) === null) {
            return $this->error(Response::HTTP_NOT_FOUND, 'Thirdparty not found');
        }

        $discount = $this->discounts->fetch($discountid);
        if ($discount === null) {
            return $this->error(Response::HTTP_NOT_FOUND, 'Discount not found');
        }
        if ($discount->getFkSoc() !== $id) {
            return $this->error(Response::HTTP_METHOD_NOT_ALLOWED, 'Discount not owned by this thirdpartie');
        }
        if (Price2Num::num((float) $amountTtc1 + (float) $amountTtc2) !== $discount->getAmountTtc()) {
            return $this->error(
                Response::HTTP_METHOD_NOT_ALLOWED,
                'Sum of the 2 discounts is different that the original discount',
            );
        }
        if ($discount->getFkFactureLine()) {
            return $this->error(Response::HTTP_CONFLICT, 'Discount is already used');
        }

        try {
            $ids = $this->discounts->split(
                $discount,
                (float) $amountTtc1,
                (float) $amountTtc2,
                $this->context->userId(),
            );
        } catch (\Throwable) {
            $ids = -1;
        }

        if (!is_array($ids)) {
            return $this->error(Response::HTTP_INTERNAL_SERVER_ERROR, 'Operation fail');
        }

        return new JsonResponse($this->remiseExceptRepo->findDiscountRows($ids, $id));
    }

    /**
     * DELETE {id}/fixedamountdiscounts/{discountid} — remx.php "delete an
     * unused discount" action. DiscountAbsolute::delete() also removes the
     * whole fk_facture_source / fk_invoice_supplier_source family when all
     * members are unused.
     */
    #[Route('/fixedamountdiscounts/{discountid}', methods: ['DELETE'], requirements: ['discountid' => '\d+'])]
    public function deleteFixedAmountDiscount(int $id, int $discountid): JsonResponse
    {
        if ($this->fetchThirdparty($id) === null) {
            return $this->error(Response::HTTP_NOT_FOUND, 'Thirdparty not found');
        }

        $discount = $this->discounts->fetch($discountid);
        if ($discount === null) {
            return $this->error(Response::HTTP_NOT_FOUND, 'Discount not found');
        }
        if ($discount->getFkSoc() !== $id) {
            return $this->error(Response::HTTP_METHOD_NOT_ALLOWED, 'Discount not owned by this thirdpartie');
        }

        $result = $this->em->wrapInTransaction(fn (): int => $this->discounts->delete($discount));
        if ($result === -2) {
            return $this->error(Response::HTTP_CONFLICT, (string) $this->discounts->error);
        }
        if ($result <= 0) {
            return $this->error(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                (string) ($this->discounts->error ?? 'Error deleting discount'),
            );
        }

        return new JsonResponse([
            'success' => [
                'code' => 200,
                'message' => 'Discount deleted',
            ],
        ]);
    }

    /**
     * GET {id}/fixedamountdiscounts/available — port of
     * DiscountAbsolute::getAvailableDiscounts() scoped to this thirdparty.
     * Same data upstream folds into absolute_discount on the thirdparty.
     */
    #[Route('/availablediscounts', methods: ['GET'])]
    public function getAvailableDiscounts(Request $request, int $id): JsonResponse
    {
        if ($this->fetchThirdparty($id) === null) {
            return $this->error(Response::HTTP_NOT_FOUND, 'Thirdparty not found');
        }

        $discountType = (int) $request->query->get('discount_type', '0');
        $userId = $request->query->get('user');
        $maxvalue = (float) $request->query->get('maxvalue', '0');
        // Mirror the two filter clauses used by upstream callers.
        $filter = match ((string) $request->query->get('filter', '')) {
            'not_from_invoice' => 'fk_facture_source IS NULL',
            'deposits' => "fk_facture_source IS NULL OR (description LIKE '(DEPOSIT)%'"
                . " AND description NOT LIKE '(EXCESS RECEIVED)%')",
            default => '',
        };

        return new JsonResponse($this->discounts->getAvailableDiscounts(
            $id,
            $userId !== null ? (int) $userId : null,
            $filter,
            $maxvalue,
            $discountType,
        ));
    }

    /**
     * GET {id}/relativediscounts — llx_societe_remise history plus the
     * current remise_percent (latest history row, like Societe::fetch()).
     */
    #[Route('/relativediscounts', methods: ['GET'])]
    public function getRelativeDiscounts(int $id): JsonResponse
    {
        $company = $this->fetchThirdparty($id);
        if ($company === null) {
            return $this->error(Response::HTTP_NOT_FOUND, 'Thirdparty not found');
        }

        return new JsonResponse([
            'remise_percent' => $this->remiseRepo->latestRemiseClient($id),
            'history' => array_map(
                static fn (SocieteRemise $r): array => [
                    'rowid' => $r->getRowid(),
                    'fk_soc' => $r->getFkSoc(),
                    'entity' => $r->getEntity(),
                    'remise_client' => $r->getRemiseClient(),
                    'note' => $r->getNote(),
                    'fk_user_author' => $r->getFkUserAuthor(),
                    'datec' => $r->getDatec()?->format('Y-m-d H:i:s'),
                    'tms' => $r->getTms()?->format('Y-m-d H:i:s'),
                ],
                $this->remiseRepo->history($id),
            ),
        ]);
    }

    /**
     * POST {id}/relativediscounts — port of Societe::set_remise_client():
     * updates llx_societe.remise_client and appends an llx_societe_remise
     * history row. Body: {remise: float, note: string} (remise_client is
     * accepted as an alias for the upstream column name).
     */
    #[Route('/relativediscounts', methods: ['POST'])]
    public function setRelativeDiscount(Request $request, int $id): JsonResponse
    {
        $data = $this->requestData($request);
        $remise = $data['remise'] ?? $data['remise_client'] ?? null;
        $note = (string) ($data['note'] ?? '');

        if ($this->fetchThirdparty($id) === null) {
            return $this->error(Response::HTTP_NOT_FOUND, 'Thirdparty not found');
        }
        if ($remise === null) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Missing required field: remise');
        }
        if (!is_numeric($remise)) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Invalid remise: must be a number');
        }

        $result = $this->em->wrapInTransaction(
            fn (): int => $this->pricing->setRemiseClient(
                $this->fetchThirdparty($id),
                (float) $remise,
                $note,
                $this->context->userId(),
            ),
        );

        if ($result === -2) {
            return $this->error(Response::HTTP_BAD_REQUEST, (string) $this->pricing->error);
        }
        if ($result <= 0) {
            return $this->error(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                (string) ($this->pricing->error ?? 'Error setting discount'),
            );
        }

        return new JsonResponse(
            ['remise_percent' => $this->remiseRepo->latestRemiseClient($id)],
            Response::HTTP_CREATED,
        );
    }

    /**
     * Societe::fetch() equivalent for this slice: the row must exist and be
     * in the current entity (upstream: s.entity IN (getEntity('societe'))).
     */
    private function fetchThirdparty(int $id): ?Societe
    {
        $company = $this->em->find(Societe::class, $id);
        if ($company === null || $company->getEntity() !== $this->context->entity()) {
            return null;
        }

        return $company;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(Request $request): array
    {
        $data = [];
        $content = $request->getContent();
        if ($content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        return array_merge($request->query->all(), $data);
    }

    private function error(int $code, string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $code);
    }
}
