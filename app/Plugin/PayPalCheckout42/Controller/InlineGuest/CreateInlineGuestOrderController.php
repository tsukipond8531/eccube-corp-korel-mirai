<?php

namespace Plugin\PayPalCheckout42\Controller\InlineGuest;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Order;
use Plugin\PayPalCheckout42\Exception\PayPalCheckoutException;
use Plugin\PayPalCheckout42\Service\LoggerService;
use Plugin\PayPalCheckout42\Service\PayPalService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class CreateInlineGuestOrderController
 * @package Plugin\PayPalCheckout42\Controller\InlineGuest
 */
class CreateInlineGuestOrderController extends AbstractController
{
    /**
     * @var PayPalService
     */
    protected $paypal;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * CreateInlineGuestOrderController constructor.
     * @param PayPalService $paypal
     * @param LoggerService $loggerService
     */
    public function __construct(
        PayPalService $paypal,
        LoggerService $loggerService
    ) {
        $this->paypal = $paypal;
        $this->logger = $loggerService;
    }

    /**
     * @Method("POST")
     * @Route("/paypal/prepare-transaction-inline-guest", name="paypal_prepare_transaction_inline_guest")
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (!($request->isXmlHttpRequest() && $this->isTokenValid())) {
            return $this->json([], Response::HTTP_UNAUTHORIZED);
        }

        $this->logger->debug('CreateOrderRequest has been received', [
            'headers' => $request->headers->all(),
            'request' => $request->request->all()
        ]);

        try {
            /** @var Order $order */
            $order = $this->paypal->getShippingOrder();

            $this->paypal->createOrderRequest($order, function ($response) use (&$orderingId, &$statusCode) {
                $orderingId = $response->result->id;
                $statusCode = $response->statusCode;
            });

            /** @var array $data */
            $data = [
                "result" => [
                    "id" => $orderingId
                ]
            ];
            $this->logger->debug('CreateOrderRequest has been completed', [
                'response' => $data
            ]);
            return $this->json($data, $statusCode);
        } catch (PayPalCheckoutException $e) {
            $this->logger->error('CreateOrderRequest has been failed', array_merge([
                'headers' => $request->headers->all(),
                'request' => $request->request->all(),
                'statusCode' => $e->getCode(),
                'message' => $e->getMessage(),
            ], $this->paypal->isDebug() ? [
                'trace' => $e->getTrace()
            ] : []));
            throw new BadRequestHttpException("CreateOrderRequest has been failed", $e, $e->getCode());
        }
    }
}
