<?php


namespace uukule\message\driver\wechat_template\contract;


interface Template
{

    /**
     * 获取所有模板列表
     * @param int $offset
     * @param int $count
     * @return array
     */
    public function list():array;

    /**
     * 删除模板
     * @param string $templateId
     * @return bool
     */
    public function delete(string $templateId):bool;

    /**
     * 添加模板
     * 在公众号后台获取 $shortId 并添加到账户。
     * @param string $shortId
     * @return bool
     */
    public function add(string $shortId):bool;

}