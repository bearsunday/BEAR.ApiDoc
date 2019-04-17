<?php

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;
class FakeVendor_FakeProject_Resource_App_Person_c0kmGJA extends FakeVendor\FakeProject\Resource\App\Person implements Ray\Aop\WeavedInterface
{
    private $isIntercepting = true;
    public $bind;
    public $methodAnnotations = 'a:2:{s:5:"onGet";a:1:{i:0;O:35:"BEAR\\Resource\\Annotation\\JsonSchema":3:{s:3:"key";N;s:6:"schema";s:11:"person.json";s:6:"params";N;}}s:11:"setRenderer";a:1:{i:0;O:16:"Ray\\Di\\Di\\Inject":1:{s:8:"optional";b:1;}}}';
    public $classAnnotations = 'a:0:{}';
    /**
     * @JsonSchema(schema="person.json")
     *
     * @param string $id The unique ID of the person.
     */
    function onGet(string $id = 'koriym')
    {
        if ($this->isIntercepting === false) {
            $this->isIntercepting = true;
            return parent::onGet($id);
        }
        $this->isIntercepting = false;
        // invoke interceptor
        $result = (new \Ray\Aop\ReflectiveMethodInvocation($this, __FUNCTION__, [$id], $this->bindings[__FUNCTION__]))->proceed();
        $this->isIntercepting = true;
        return $result;
    }
}
