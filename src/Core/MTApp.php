<?php

/**
 * MultiTenant Plugin
 * Copyright (c) PRONIQUE Software (http://pronique.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) PRONIQUE Software (http://pronique.com)
 * @link          http://github.com/pronique/multitenant MultiTenant Plugin Project
 * @since         0.5.1
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace MultiTenant\Core;

use Cake\Core\StaticConfigTrait;
use Cake\Core\Exception\Exception;
use Cake\ORM\TableRegistry;
use Cake\Cache\Cache;
use MultiTenant\Error\MultiTenantException;

//TODO Implement Singleton/Caching to eliminate sql query on every call
class MTApp {

    use StaticConfigTrait {
        config as public _config;
    }

    //PHP variables persist for the lifetime of the script running through the interpreter.
    public static $handler = null;
    protected static $_cachedAccounts = [];

    /**
     * find the current context based on domain/subdomain
     * 
     * @return String 'global', 'tenant', 'custom'
     *
     */
    public static function getContext() {
        if (self::$handler) {
            return self::$handler->getContext();
        }

        //get tenant qualifier
        $qualifier = self::_getTenantQualifier();

        if ($qualifier == self::config('primaryDomain')) {
            return 'global';
        }

        return 'tenant';
    }

    /**
     *
     *
     */
    public static function isPrimary() {
        if (self::$handler) {
            return self::$handler->isPrimary();
        }

        //get tenant qualifier
        $qualifier = self::_getTenantQualifier();

        if ($qualifier == self::config('primaryDomain')) {
            return true;
        }

        return false;
    }

    /**
     * 
     * Can be used throughout Application to resolve current tenant
     * Returns tenant entity
     * 
     * @returns Cake\ORM\Entity
     */
    public static function tenant() {

        //if tentant/_findTenant is called at the primary domain the plugin is being used wrong;
        if (self::isPrimary()) {
            throw new Exception('MTApp::tenant() cannot be called from primaryDomain context');
        }

        $qualifier = self::_getTenantQualifier();

        if ($qualifier) {
            $tenant = Cache::read($qualifier);
            $modelConf = self::config('model');
            $tbl = TableRegistry::get($modelConf['className']);
            $entity = $tbl->newEntity($tenant);
            $entity->set('id', $tenant['id']);
            return $entity;
        }

        throw new MultiTenantException("MTApp::tenant() tenant not defined");
    }

    /**
     * 
     * Can be used throughout Application to set current tenant
     * 
     * @returns void
     */
    public static function setTenant($tenant) {
        if (self::config('strategy') == 'session') {
            $_SESSION[self::config('qualifierKey')] = $tenant['id'];
        }

        $qualifier = self::_getTenantQualifier();
        if ($qualifier) {
            Cache::write($qualifier, $tenant);
        }
    }

    /**
     * 
     * Can be used throughout Application to unset the current tenant and
     * free cache memory
     * 
     * @returns void
     */
    public static function unsetTenant() {
        $qualifier = self::_getTenantQualifier();
        if ($qualifier) {
            Cache::delete($qualifier);
            if (self::config('strategy') == 'session') {
                unset($_SESSION[self::config('qualifierKey')]);
            }
        }
    }

    protected static function _getTenantQualifier() {
        //for domain this is the SERVER_NAME from $_SERVER
        if (self::config('strategy') == 'domain') {
            return env('SERVER_NAME');
        } else if (self::config('strategy') == 'session') {
            if ($_SESSION && array_key_exists(self::config('qualifierKey'), $_SESSION)) {
                return self::_getTenantName($_SESSION[self::config('qualifierKey')]);
            }
            return null;
        }
    }

    protected static function _getTenantName($id) {
        return 'MTApp_tenant_' . $id;
    }

}
