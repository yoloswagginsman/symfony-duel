<?php

namespace App\Contract\Dto;

/**
 * @template T of object
 */
interface ToModelConvertibleInterface
{
    /**
     * @return T
     */
    public function toModel(): object;
}