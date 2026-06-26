<?php

namespace App\Traits;

trait ConvertsToSnakeCase
{
    public function convertFromClassName(string $className, string $suffix = ''): string
    {
        $name = $suffix !== '' ? preg_replace('/' . preg_quote($suffix, '/') . '$/', '', $className) : $className;

        $name = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $name);
        $name = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $name);

        return strtolower(trim($name, '_'));
    }
}
