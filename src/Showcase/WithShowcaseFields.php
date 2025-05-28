<?php

namespace Dybasedev\LunaPrototype\Showcase;

trait WithShowcaseFields
{
    /**
     * 返回前端字段描述
     *
     * @param array $context
     * @return array
     */
    abstract public function fields(array $context = []): array;
}