<?php

namespace Dybasedev\LunaPrototype\Showcase\Structures;

class FieldGroup extends Field
{
    /**
     * @var Field[] 字段列表
     */
    protected(set) array $fields = [];

    public function field(Field $field): static
    {
        $this->fields[] = $field;
        return $this;
    }

    public function fields(array $fields, bool $append = true): static
    {
        if ($append) {
            $this->fields = array_merge($this->fields, $fields);
        } else {
            $this->fields = $fields;
        }

        return $this;
    }
}