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
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Shopware\Components\Logger;
use Shopware\Models\Order\Order;
use Shopware\Models\Shop\Shop;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OrderStatusSubscriber implements EventSubscriber
{
    const STATUS_TYPE_ORDER = 1;
    const STATUS_TYPE_PAYMENT = 2;

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

        $shop = $order->getShop();
        $config = $this->getConfig($shop);
        $recipientGroups = $config['dpnCustomerGroups'];
        $group = $order->getCustomer()->getGroup();
        $groupId = $group ? $group->getId() : null;

        // Skip further processing in case customer groups are selected which current customer is not a member of
        if ($groupId && count($recipientGroups) > 0 && !in_array($groupId, $recipientGroups, true)) {
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

        $shop = $order->getShop();
        $config = $this->getConfig($shop);

        if (false === $config['dpnEnabled']) {
            return;
        }

        $selectedPaymentStatusIds = $config['dpnPaymentStatus'];
        $selectedOrderStatusIds = $config['dpnOrderStatus'];

        $changedStatus = static::$orders[$orderId];

        if ($changedStatus['order']) {
            $newOrderStatusId = $order->getOrderStatus()->getId();
            $this->sendStatusEmail($order, $newOrderStatusId, $selectedOrderStatusIds);
            if (true === $config['dpnCommentEnabled']) {
                $this->addComment($order, static::STATUS_TYPE_ORDER);
            }
        }

        if ($changedStatus['payment']) {
            $newPaymentStatusId = $order->getPaymentStatus()->getId();
            $this->sendStatusEmail($order, $newPaymentStatusId, $selectedPaymentStatusIds);
            if (true === $config['dpnCommentEnabled']) {
                $this->addComment($order, static::STATUS_TYPE_PAYMENT);
            }
        }
    }

    /**
     * @param Order $order
     * @param int $newStatusId
     * @param array $selectedStatusIds
     */
    protected function sendStatusEmail(Order $order, $newStatusId, array $selectedStatusIds)
    {
        if (!in_array($newStatusId, $selectedStatusIds, true)) {
            return;
        }
        $orderId = $order->getId();
        $mail = Shopware()->Modules()->Order()->createStatusMail($orderId, $newStatusId);
        if ($mail === null) {
            $message = $this->getSnippet('backend/dpn_auto_status_email/translations', 'auto_email_missing_template');
            throw new \RuntimeException(sprintf($message, $newStatusId));
        }
        try {
            Shopware()->Modules()->Order()->sendStatusMail($mail);
        }
        catch (\Exception $e) {
            /** @var Logger $logger */
            $logger = $this->container->get('pluginlogger');
            $logger->error(
                'Status email could not be send',
                [
                    'orderId' => $orderId,
                    'status' => $newStatusId,
                ]
            );
        }
    }

    /**
     * @param Order $order
     * @param int $type
     */
    protected function addComment(Order $order, $type)
    {
        $comment = $order->getInternalComment();
        if (!empty($comment)) {
            $comment .= PHP_EOL;
        }
        try {
            $date = (new \DateTime('now'))->format('d.m.Y, H:i');
        }
        catch (\Exception $e) {
            $date = '-';
        }
        $message = $this->getSnippet('backend/dpn_auto_status_email/translations', 'auto_email_sent_comment');
        switch ($type) {
            case static::STATUS_TYPE_ORDER:
                $statusName = $this->getSnippet('backend/static/order_status', $order->getOrderStatus()->getName());
                break;
            case static::STATUS_TYPE_PAYMENT:
                $statusName = $this->getSnippet('backend/static/payment_status', $order->getPaymentStatus()->getName());
                break;
            default:
                $statusName = '-';
        }
        $comment .=  sprintf($message, $date, $statusName);
        $order->setInternalComment($comment);
        $connection = $this->container->get('dbal_connection');
        /** @var QueryBuilder $qb */
        $qb = $connection->createQueryBuilder();
        $qb
            ->update('s_order')
            ->set('internalcomment', ':comment')
            ->where('id = :id')
            ->setParameter('id', $order->getId())
            ->setParameter('comment', $comment)
            ->execute()
        ;
    }

    /**
     * @return array
     */
    protected function getConfig(Shop $shop)
    {
        $configReader = $this->container->get('shopware.plugin.cached_config_reader');

        return $configReader->getByPluginName('DpnAutoStatusEmail', $shop);
    }

    /**
     * @param string $namespace
     * @param string $snippet
     * @return string
     */
    protected function getSnippet($namespace, $snippet)
    {
        return $this->container
            ->get('snippets')
            ->getNamespace($namespace)
            ->get($snippet)
        ;
    }
}
