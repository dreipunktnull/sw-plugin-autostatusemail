<?php

namespace DpnAutoStatusEmail\Subscriber;

/**
 * Copyright notice
 *
 * (c) Björn Fromme <fromme@dreipunktnull.com>, dreipunktnull
 *
 * All rights reserved
 */

use Enlight\Event\SubscriberInterface;
use Shopware\Components\Plugin\CachedConfigReader;
use Shopware\Models\Order\Order;

class MailPopupSubscriber implements SubscriberInterface
{
    /**
     * @var array
     */
    protected static $orders = [];

    /**
     * @var CachedConfigReader
     */
    protected $configReader;

    /**
     * @param CachedConfigReader $configReader
     */
    public function __construct(CachedConfigReader $configReader) 
    {
        $this->configReader = $configReader;
    }

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
     * @param \Enlight_Controller_ActionEventArgs $args
     */
    public function onBackendOrderPre(\Enlight_Controller_ActionEventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Order $controller */
        $controller = $args->getSubject();
        $request = $controller->Request();
        $orderId = $request->get('id');

        if ($request->getActionName() !== 'save') {
            return;
        }

        if (empty($orderId)) {
            return;
        }

        if (isset(static::$orders[$orderId])) {
            return;
        }

        $order = $this->getOrderById($orderId);

        if (null === $order) {
            return;
        }

        static::$orders[$orderId] = [
            'orderStatusBefore' => $order->getOrderStatus()->getId(),
            'paymentStatusBefore' => $order->getPaymentStatus()->getId(),
        ];
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     */
    public function onBackendOrderPost(\Enlight_Controller_ActionEventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Order $controller */
        $controller = $args->getSubject();
        $request = $controller->Request();
        $orderId = $request->get('id');

        if ($request->getActionName() !== 'save') {
            return;
        }

        $order = $this->getOrderById($orderId);

        if (null === $order) {
            return;
        }

        $view = $controller->View();
        $data = $view->getAssign('data');

        $orderStatusId = $data['orderStatus']['id'];
        $paymentStatusId = $data['paymentStatus']['id'];

        $data['mail']['isAutoSend'] = $this->isHideMailPopup(
            $order,
            $orderStatusId,
            $paymentStatusId
        );

        $view->assign('data', $data);
    }

    /**
     * @param Order $order
     * @param int $orderStatusId
     * @param int $paymentStatusId
     * @return bool
     */
    protected function isHideMailPopup(Order $order, $orderStatusId, $paymentStatusId)
    {
        $shop = $order->getShop();
        $config = $this->configReader->getByPluginName('DpnAutoStatusEmail', $shop);

        if (false === $config['dpnEnabled']) {
            return false;
        }

        $recipientGroups = $config['dpnCustomerGroups'];
        $group = $order->getCustomer()->getGroup();
        $groupId = $group ? $group->getId() : null;

        // Skip further processing in case customer groups are selected which current customer is not a member of
        if ($groupId && count($recipientGroups) > 0 && !in_array($groupId, (array)$recipientGroups, true)) {
            return false;
        }

        $orderId = $order->getId();
        $selectedPaymentStatusIds = $config['dpnPaymentStatus'];
        $selectedOrderStatusIds = $config['dpnOrderStatus'];
        $selectedTrackingCodeStatusIds = $config['dpnTrackingCodeStatus'];

        $isSelectedOrderStatusId = in_array($orderStatusId, (array)$selectedOrderStatusIds, true);
        $isSelectedPaymentStatusId = in_array($paymentStatusId, (array)$selectedPaymentStatusIds, true);
        $isSelectedTrackingCodeStatusId = in_array($orderStatusId, (array)$selectedTrackingCodeStatusIds, true);

        $isOrderStatusChanged = static::$orders[$orderId]['orderStatusBefore'] !== $orderStatusId;
        $isPaymentStatusChanged = static::$orders[$orderId]['paymentStatusBefore'] !== $paymentStatusId;

        if (
            ($isOrderStatusChanged && $isSelectedOrderStatusId) ||
            ($isOrderStatusChanged && $isSelectedTrackingCodeStatusId && !empty($order->getTrackingCode()))
        ) {
            return true;
        }

        if ($isPaymentStatusChanged && $isSelectedPaymentStatusId) {
            return true;
        }

        return false;
    }

    /**
     * @param int $orderId
     * @return Order|null
     */
    protected function getOrderById($orderId)
    {
        return Shopware()->Models()->getRepository(Order::class)->find($orderId);
    }
}