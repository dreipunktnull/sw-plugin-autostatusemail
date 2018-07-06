<?php

namespace DpnAutoStatusEmail\Subscriber;

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
     * @param \Enlight_Event_EventArgs $args
     */
    public function onBackendOrderPostDispatch(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Article $controller */
        $controller = $args->getSubject();
        $controller->View()->addTemplateDir($this->pluginDirectory . '/Resources/views/');

        if ($controller->Request()->getActionName() === 'load') {
            $controller->View()->extendsTemplate('backend/dpn_auto_status_email/order/controller/list.js');
        }
    }

}