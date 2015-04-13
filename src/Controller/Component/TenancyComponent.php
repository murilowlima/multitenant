<?php

namespace MultiTenant\Controller\Component;

use Cake\Controller\Component;
use Cake\Event\Event;
use Cake\Core\Exception\Exception;
use MultiTenant\Core\MTApp;
use MultiTenant\Error\MultiTenantException;
use Cake\ORM\TableRegistry;

/**
 * CakePHP TenancyComponent
 * @author Murilo Lima https://github.com/murilowlima/
 */
class TenancyComponent extends Component {

    public $components = array();
    public static $sessionKey = 'Auth.User.Tenant';
    private $_cache = null;
    protected $_defaultConfig = [
        'redirect' => null,
        'model' => null
    ];

    public function beforeFilter(Event $event) {
        MTApp::$handler = $this;
        $context = $this->getContext();
        if ($context == 'global') {
            return;
        }
        try {
            $tenant = $this->getTenant();
            if ($tenant == null) {
                return $this->redirect();
            }
        } catch (MultiTenantException $ex) {
            return $this->redirect();
        }
    }

    public function getTenant() {
        if ($this->_cache !== null) {
            return $this->_cache;
        }
        $tenant = $this->request->session()->read(self::$sessionKey);
        $tbl = TableRegistry::get($this->config('model'));
        $entity = $tbl->newEntity($tenant);
        $entity->set('id', $tenant['id']);
        return $entity;
    }

    public function setTenant($tenant) {
        $this->_cache = $tenant;
        $this->request->session()->write(self::$sessionKey, $tenant);
    }

    public function unsetTenant() {
        return;
    }

    public function redirect() {
        $controller = $this->_registry->getController();
        return $controller->redirect($this->config('redirect'));
    }

    public function isTenancyContext() {
        $controller = $this->_registry->getController();
        $className = get_class($controller);

        if (!method_exists($className, 'isGlobalContext')) {
            throw new Exception('Controller must implement an isGlobalContext() method.');
        }

        return !$controller->isGlobalContext();
    }

    public function isPrimary() {
        return !$this->isTenancyContext();
    }

    public function getContext() {
        $controller = $this->_registry->getController();
        if (!$this->isTenancyContext($controller)) {
            return 'global';
        }

        return 'tenant';
    }
}
