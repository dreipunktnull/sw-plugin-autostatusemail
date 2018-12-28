<?php

namespace DpnAutoStatusEmail\Subscriber;

/**
 * Copyright notice
 *
 * (c) Björn Fromme <fromme@dreipunktnull.com>, dreipunktnull
 *
 * All rights reserved
 */

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Shopware\Models\Order\Order;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OrderStatusSubscriber implements EventSubscriber
{
    /**
     * @var array
     */
    protected static $orders = [];

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            Events::preUpdate,
            Events::postUpdate,
        ];
    }

    /**
     * @param PreUpdateEventArgs $eventArgs
     */
    public function preUpdate(PreUpdateEventArgs $eventArgs)
    {
        $order = $eventArgs->getEntity();

        if (!$order instanceof Order) {
            return;
        }

        $orderId = $order->getId();

        if (isset(static::$orders[$orderId])) {
            return;
        }

        if (!$eventArgs->hasChangedField('orderStatus') && !$eventArgs->hasChangedField('paymentStatus')) {
            return;
        }

        static::$orders[$orderId] = [
            'order' => $eventArgs->hasChangedField('orderStatus'),
            'payment' => $eventArgs->hasChangedField('paymentStatus'),
        ];
    }

    /**
     * @param LifecycleEventArgs $eventArgs
     */
    public function postUpdate(LifecycleEventArgs $eventArgs)
    {
        $order = $eventArgs->getEntity();

        if (!$order instanceof Order) {
            return;
        }

        $orderId = $order->getId();

        if (!isset(static::$orders[$orderId])) {
            return;
        }

        $config = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName('DpnAutoStatusEmail');
        $selectedPaymentStatusIds = $config['dpnPaymentStatus'];
        $selectedOrderStatusIds = $config['dpnOrderStatus'];

        $changedStatus = static::$orders[$orderId];

        if ($changedStatus['order']) {
            $newOrderStatusId = $order->getOrderStatus()->getId();
            $this->sendStatusEmail($orderId, $newOrderStatusId, $selectedOrderStatusIds);
        }

        if ($changedStatus['payment']) {
            $newPaymentStatusId = $order->getPaymentStatus()->getId();
            $this->sendStatusEmail($orderId, $newPaymentStatusId, $selectedPaymentStatusIds);
        }
    }

    /**
     * @param int $orderId
     * @param int $newStatusId
     * @param array $selectedStatusIds
     */
    protected function sendStatusEmail($orderId, $newStatusId, array $selectedStatusIds)
    {
        if (in_array($newStatusId, $selectedStatusIds, true)) {
            $mail = Shopware()->Modules()->Order()->createStatusMail($orderId, $newStatusId);
            Shopware()->Modules()->Order()->sendStatusMail($mail);
        }
    }
}