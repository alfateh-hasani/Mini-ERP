<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sar($amount): string
{
    return 'SAR ' . number_format((float) $amount, 2, '.', ',');
}

function csv_safe($value): string
{
    $value = (string) $value;
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}
