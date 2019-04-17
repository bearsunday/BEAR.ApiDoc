<?php

use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;
class FakeVendor_FakeProject_Resource_App_User_M2STCIA extends FakeVendor\FakeProject\Resource\App\User implements Ray\Aop\WeavedInterface
{
    private $isIntercepting = true;
    public $bind;
    public $methodAnnotations = 'a:2:{s:5:"onGet";a:4:{i:0;O:35:"BEAR\\Resource\\Annotation\\JsonSchema":3:{s:3:"key";N;s:6:"schema";s:9:"user.json";s:6:"params";N;}i:1;O:29:"BEAR\\Resource\\Annotation\\Link":5:{s:3:"rel";s:7:"profile";s:4:"href";s:8:"/profile";s:6:"method";s:3:"get";s:5:"title";s:0:"";s:5:"crawl";s:0:"";}i:2;O:29:"BEAR\\Resource\\Annotation\\Link":5:{s:3:"rel";s:7:"setting";s:4:"href";s:8:"/setting";s:6:"method";s:3:"get";s:5:"title";s:0:"";s:5:"crawl";s:0:"";}i:3;O:30:"BEAR\\Resource\\Annotation\\Embed":2:{s:3:"rel";s:4:"blog";s:3:"src";s:10:"/blog/{id}";}}s:11:"setRenderer";a:1:{i:0;O:16:"Ray\\Di\\Di\\Inject":1:{s:8:"optional";b:1;}}}';
    public $classAnnotations = 'a:0:{}';
    /**
     * @JsonSchema(schema="user.json")
     *
     * @param string $id      User ID
     * @param string $options User Options
     *
     * @Link(rel="profile", href="/profile", method="get")
     * @Link(rel="setting", href="/setting", method="get")
     * @Embed(rel="blog", src="/blog/{id}")
     */
    function onGet(string $id, string $options = 'guest')
    {
        if ($this->isIntercepting === false) {
            $this->isIntercepting = true;
            return parent::onGet($id, $options);
        }
        $this->isIntercepting = false;
        // invoke interceptor
        $result = (new \Ray\Aop\ReflectiveMethodInvocation($this, __FUNCTION__, [$id, $options], $this->bindings[__FUNCTION__]))->proceed();
        $this->isIntercepting = true;
        return $result;
    }
}
