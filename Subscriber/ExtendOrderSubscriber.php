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

class ExtendOrderSubscriber implements SubscriberInterface
{
    /**
     * @var string
     */
    protected $pluginDirectory;

    /**
     * @param string $pluginDirectory
     */
    public function __construct($pluginDirectory)
    {
        $this->pluginDirectory = $pluginDirectory;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Order' => 'onBackendOrderPostDispatch'
        ];
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     */
    public function onBackendOrderPostDispatch(\Enlight_Controller_ActionEventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Article $controller */
        $controller = $args->getSubject();
        $controller->View()->addTemplateDir($this->pluginDirectory . '/Resources/views/');

        if ($controller->Request()->getActionName() === 'load') {
            $controller->View()->extendsTemplate('backend/dpn_auto_status_email/order/controller/list.js');
            $controller->View()->extendsTemplate('backend/dpn_auto_status_email/order/model/mail.js');
        }
    }
}