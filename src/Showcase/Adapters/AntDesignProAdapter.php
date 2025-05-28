<?php

namespace Dybasedev\LunaPrototype\Showcase\Adapters;

use Dybasedev\LunaPrototype\Showcase\Adapter;
use Dybasedev\LunaPrototype\Showcase\Structures\Column;
use Dybasedev\LunaPrototype\Showcase\Structures\Field;
use Dybasedev\LunaPrototype\Showcase\Structures\FieldGroup;

class AntDesignProAdapter extends Adapter
{
    public function column(Column $source): array
    {
        $output = [];

        if ($source->key) {
            $output['key'] = $source->key;
        }

        if ($source->title) {
            $output['title'] = $source->title;
        }

        if ($source->name) {
            $output['dataIndex'] = $source->name;
        }

        if ($source->width) {
            $output['width'] = $source->width;
        }

        if ($source->ellipsis) {
            $output['ellipsis'] = true;
        }

        if ($source->copyable) {
            $output['copyable'] = true;
        }

        if ($source->tooltip) {
            $output['tooltip'] = true;
        }

        if (!$source->searchable) {
            $output['search'] = false;
        }

        if ($source->sortable) {
            $output['sorter'] = true;
        }

        if ($source->type) {
            $output['valueType'] = $source->type;
        }

        if ($source->placeholder) {
            $output['placeholder'] = $source->placeholder;
        }

        return $output;
    }

    public function field(Field|FieldGroup $source): array
    {
        $output = [];

        if ($source->type) {
            $output['valueType'] = $source->type;
        }

        if ($source instanceof FieldGroup) {
            if (!in_array($source->type, ['formList', 'formSet', 'group'])) {
                $output['valueType'] = 'group';
            }

            $output['columns'] = array_map(function ($field) {
                return $this->field($field);
            }, $source->fields);
        }

        if ($source->title) {
            $output['title'] = $source->title;
        }

        if ($source->name) {
            $output['dataIndex'] = $source->name;
        }

        if ($source->width) {
            $output['width'] = $source->width;
        }

        if ($source->tooltip) {
            $output['tooltip'] = true;
        }

        if ($source->placeholder) {
            $output['fieldProps']['placeholder'] = $source->placeholder;
        }

        if ($source->formFieldProperties) {
            if (isset($source->formFieldProperties['initialValue'])) {
                $output['initialValue'] = $source->formFieldProperties['initialValue'];
            }
            $output['formItemProps'] = [
                ...($output['formItemProps'] ?? []),
                ...$source->formFieldProperties
            ];
        }

        if ($source->properties) {
            if (isset($source->properties['colProps'])) {
                $output['colProps'] = $source->properties['colProps'];
            }
            $output['fieldProps'] = $source->properties;
        }

        return $output;
    }
}