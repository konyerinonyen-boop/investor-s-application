<?php

namespace App\Interfaces;


interface PaymantGateWayInterface
{
    public function initialize(array $data);
     public function verify(string $reference);
}
