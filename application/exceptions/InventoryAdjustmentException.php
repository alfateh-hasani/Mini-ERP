<?php
namespace App\Exceptions;

class InventoryAdjustmentException extends ValidationException
{
    private $fieldErrors;

    public function __construct(array $fieldErrors)
    {
        parent::__construct('Invalid inventory adjustment.');
        $this->fieldErrors = $fieldErrors;
    }

    public function fieldErrors(): array { return $this->fieldErrors; }
}
