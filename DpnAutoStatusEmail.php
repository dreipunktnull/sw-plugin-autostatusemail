<?php

namespace DpnAutoStatusEmail;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Models\Order\Order;

class DpnAutoStatusEmail extends Plugin
{
    /**
     * @var array
     */
    protected static $orders = [];

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch_Backend_Order' => 'onBackendOrderPre',
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Order' => 'onBackendOrderPost',
        ];
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     */
    public function onBackendOrderPre(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Order $controller */
        $controller = $args->getSubject();
        $request = $controller->Request();
        $orderId = $request->get('id');

        if (empty($orderId)) {
            return;
        }

        if (isset(static::$orders[$orderId])) {
            return;
        }

        /** @var Order $order */
        $order = Shopware()->Models()->getRepository(Order::class)->find($orderId);

        static::$orders[$orderId] = [
            'orderStatusBefore' => $order->getOrderStatus()->getId(),
            'paymentStatusBefore' => $order->getPaymentStatus()->getId(),
        ];
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     */
    public function onBackendOrderPost(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Order $controller */
        $controller = $args->getSubject();
        $request = $controller->Request();
        $orderId = $request->get('id');

        if ($request->getActionName() !== 'save') {
            return;
        }

        $view = $controller->View();
        $data = $view->getAssign('data');

        $orderStatusId = $data['orderStatus']['id'];
        $paymentStatusId = $data['paymentStatus']['id'];

        if ($this->isHideMailPopup($orderId, $orderStatusId, $paymentStatusId)) {
            $data['mail'] = null;
        }

        $view->assign('data', $data);
    }

    /**
     * @param int $orderId
     * @param int $orderStatusId
     * @param int $paymentStatusId
     * @return bool
     */
    protected function isHideMailPopup($orderId, $orderStatusId, $paymentStatusId)
    {
        $config = $this->container->get('config');
        $selectedPaymentStatusIds = $config->getByNamespace('DpnAutoStatusEmail', 'dpnPaymentStatus');
        $selectedOrderStatusIds = $config->getByNamespace('DpnAutoStatusEmail', 'dpnOrderStatus');

        $isSelectedOrderStatusId = in_array($orderStatusId, $selectedOrderStatusIds, true);
        $isSelectedPaymentStatusId = in_array($paymentStatusId, $selectedPaymentStatusIds, true);

        $isOrderStatusChanged = static::$orders[$orderId]['orderStatusBefore'] !== $orderStatusId;
        $isPaymentStatusChanged = static::$orders[$orderId]['paymentStatusBefore'] !== $paymentStatusId;

        if ($isOrderStatusChanged && $isSelectedOrderStatusId) {
            return true;
        }

        if ($isPaymentStatusChanged && $isSelectedPaymentStatusId) {
            return true;
        }

        return false;
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
    }
}

