<?php

function peso($value): string
{
    return '₱'.number_format((float) $value, 2);
}
function field_value(string $field, $record = [], string $default = ''): string
{
    $value = old($field, data_get($record, $field, $default));

    return e(is_scalar($value) ? (string) $value : $default);
}
