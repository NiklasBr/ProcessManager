<?php

/**
 * Elements.at
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) elements.at New Media Solutions GmbH (https://www.elements.at)
 *  @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Elements\Bundle\ProcessManagerBundle\Executor;

use Elements\Bundle\ProcessManagerBundle\Executor\Action\AbstractAction;
use Elements\Bundle\ProcessManagerBundle\Model\Configuration;
use Elements\Bundle\ProcessManagerBundle\Model\MonitoringItem;
use Pimcore\Tool\Console;

abstract class AbstractExecutor implements \JsonSerializable
{
    protected $name = '';

    protected $extJsClass = '';

    protected $values = [];

    protected $loggers = [];

    protected $executorConfig = [];

    protected $actions = [];

    protected $isShellCommand = false;
    /**
     * @var Configuration
     */
    protected $config;

    public function __construct($config = [])
    {
        $this->config = $config;
    }

    /**
     * @return boolean
     */
    public function getIsShellCommand()
    {
        return $this->isShellCommand;
    }

    /**
     * @param bool $isShellCommand
     *
     * @return $this
     */
    public function setIsShellCommand($isShellCommand)
    {
        $this->isShellCommand = $isShellCommand;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        if (!$this->name) {
            $this->name = lcfirst(array_pop(explode('\\', get_class($this))));
        }

        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Configuration
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param Configuration $config
     *
     * @return $this
     */
    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @return string
     */
    public function getExtJsClass()
    {
        return $this->extJsClass;
    }

    /**
     * @param string $extJsClass
     *
     * @return $this
     */
    public function setExtJsClass($extJsClass)
    {
        $this->extJsClass = $extJsClass;

        return $this;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param array $values
     *
     * @return $this
     */
    public function setValues($values)
    {
        $this->values = $values;

        return $this;
    }

    public function getExtJsSettings()
    {
        $executorConfig = [
            'extJsClass' => $this->getExtJsClass(),
            'name' => $this->getName(),
            'class' => $this->getConfig()->getExecutorClass(),
        ];
        $data['executorConfig'] = $executorConfig;

        $data['values'] = $this->getValues();
        $data['values']['id'] = $this->getConfig()->getId();
        $data['loggers'] = $this->getLoggers();
        $data['actions'] = $this->getActions();

        foreach ((array)$data['actions'] as $i => $actionData) {
            $className = $actionData['class'];
            $x = new $className();
            $data['actions'][$i]['extJsClass'] = $x->getExtJsClass();
            $data['actions'][$i]['config'] = $x->getConfig();
        }

        foreach ((array)$data['loggers'] as $i => $loggerData) {
            $className = $loggerData['class'];
            $x = new $className();
            $data['loggers'][$i]['extJsClass'] = $x->getExtJsClass();
            $data['loggers'][$i]['config'] = $x->getConfig();
        }

        return $data;
    }

    /**
     * @return array
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * @param array $actions
     *
     * @return $this
     */
    public function setActions($actions)
    {
        $this->actions = $actions;

        return $this;
    }

    /**
     *
     * Tests
     *
     * @param MonitoringItem $monitoringItem
     *
     * @return string
     *
     */
    public function getShellCommand(MonitoringItem $monitoringItem)
    {
        return Console::getPhpCli() . ' ' . realpath(PIMCORE_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'console') . ' process-manager:execute-shell-cmd --monitoring-item-id=' . $monitoringItem->getId();
    }

    /**
     * returns the command which should be executed
     *
     * the CallbackSettings are only passed at execution time
     *
     * @param string[] $callbackSettings
     * @param null | MonitoringItem $monitoringItem
     *
     * @return mixed
     */
    abstract public function getCommand($callbackSettings = [], $monitoringItem = null);

    public function jsonSerialize()
    {
        $values = array_merge(['class' => get_class($this)], get_object_vars($this));

        return $values;
    }

    /**
     * @return array
     */
    public function getLoggers()
    {
        return $this->loggers;
    }

    /**
     * @param array $loggers
     *
     * @return $this
     */
    public function setLoggers($loggers)
    {
        $this->loggers = $loggers;

        return $this;
    }

    public function getStorageValue()
    {
        $actions = (array)$this->getActions();
        foreach($actions as $i => $data){
            if(is_object($data) && method_exists($data,'getStorageData')){
                $actions[$i] = $data->getStorageData();
            }
        }
        $data = [
            'values' => (array)$this->getValues(),
            'actions' => $actions,
            'loggers' => (array)$this->getLoggers()
        ];

        return json_encode($data);
    }

    protected function setData($values)
    {
        foreach ($values as $key => $value) {
            $setter = 'set' . ucfirst($key);
            if (method_exists($this, $setter)) {
                $this->$setter($value);
            }
        }

        return $this;
    }

    /**
     * @param Configuration $configuration
     *
     * @return Configuration
     */
    public function setDataFromResource(Configuration $configuration)
    {
        $settings = $configuration->getExecutorSettings();
        if (is_string($settings)) {
            $this->setData(json_decode($settings, true));
        }
        $this->setConfig($configuration);

        return $configuration;
    }
}
