<?php
/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace BjyAuthorize\Guard;

use BjyAuthorize\Exception\UnAuthorizedException;
use BjyAuthorize\Provider\Rule\ProviderInterface as RuleProviderInterface;
use BjyAuthorize\Provider\Resource\ProviderInterface as ResourceProviderInterface;

use Users\Assertion\isAuthorized;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Route Guard listener, allows checking of permissions
 * during {@see \Zend\Mvc\MvcEvent::EVENT_ROUTE}
 *
 * @author Ben Youngblood <bx.youngblood@gmail.com>
 */
class Route implements GuardInterface, RuleProviderInterface, ResourceProviderInterface
{
    /**
     * Marker for invalid route errors
     */
    const ERROR = 'error-unauthorized-route';

    /**
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * @var array[]
     */
    protected $rules = array();

    /**
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();

    /**
     * @param array                   $rules
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(array $rules, ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;

        foreach ($rules as $rule) {
            if (!is_array($rule['roles'])) {
                $rule['roles'] = array($rule['roles']);
            }
            $this->rules['route/' . $rule['route']] = array($rule['roles']);
            if (isset($rule[0])){
                $this->rules['route/' . $rule['route']][] = $rule[0];
            }
            if (isset($rule[1])){
                $this->rules['route/' . $rule['route']][] = $rule[1];
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, array($this, 'onRoute'), -1000);
    }

    /**
     * {@inheritDoc}
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getResources()
    {
        $resources = array();

        foreach (array_keys($this->rules) as $resource) {
            $resources[] = $resource;
        }

        return $resources;
    }

    /**
     * {@inheritDoc}
     */
    public function getRules()
    {
        $rules = array();

        foreach ($this->rules as $resource => $roles) {
            $rule = array($roles[0], $resource, null);
            if(isset($roles[1])){
                $rule[] = $roles[1];
            }
            if(isset($roles[2])){
                $rule[2] = $roles[2];
            }
            $rules[] = $rule;
        }

        return array('allow' => $rules);
    }

    /**
     * Event callback to be triggered on dispatch, causes application error triggering
     * in case of failed authorization check
     *
     * @param MvcEvent $event
     *
     * @return void
     */
    public function onRoute(MvcEvent $event)
    {
        $service    = $this->serviceLocator->get('BjyAuthorize\Service\Authorize');
        $match      = $event->getRouteMatch();
        $routeName  = $match->getMatchedRouteName();


        $rules = $this->getRules();
        // implements privilege in routes (not super pretty :s )
        $privilege = null;
        foreach($rules as $ruleType){
            foreach ($ruleType as $rule){
                if ($rule[1]==='route/'.$routeName){
                    $privilege = $rule[2];
                }
            }
        }
        if ($service->isAllowed('route/' . $routeName, $privilege)) {
            return;
        }

        $event->setError(static::ERROR);
        $event->setParam('route', $routeName);
        $event->setParam('identity', $service->getIdentity());
        $event->setParam('exception', new UnAuthorizedException('You are not authorized to access ' . $routeName));

        /* @var $app \Zend\Mvc\Application */
        $app = $event->getTarget();

        $app->getEventManager()->trigger(MvcEvent::EVENT_DISPATCH_ERROR, $event);
    }
}
