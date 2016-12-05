<?php
/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace BjyAuthorize;

use BjyAuthorize\Guard\AbstractGuard;
use BjyAuthorize\View\UnauthorizedStrategy;
use Zend\EventManager\EventInterface;
use Zend\ModuleManager\Feature\BootstrapListenerInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;

/**
 * BjyAuthorize Module
 *
 * @author Ben Youngblood <bx.youngblood@gmail.com>
 */
class Module implements BootstrapListenerInterface, ConfigProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function onBootstrap(EventInterface $event)
    {
        /* @var $app \Zend\Mvc\ApplicationInterface */
        /* @var $sm \Zend\ServiceManager\ServiceLocatorInterface */
        /* @var UnauthorizedStrategy $strategy */
        /** @var AbstractGuard[] $guards */

        $app = $event->getTarget();
        $serviceManager = $app->getServiceManager();
        $config = $serviceManager->get('BjyAuthorize\Config');
        $strategy = $serviceManager->get($config['unauthorized_strategy']);
        $guards = $serviceManager->get('BjyAuthorize\Guards');

        foreach ($guards as $guard) {
            $guard->attach($app->getEventManager());
        }

        $strategy->attach($app->getEventManager());
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig()
    {
        return include __DIR__ . '/../../config/module.config.php';
    }
}
