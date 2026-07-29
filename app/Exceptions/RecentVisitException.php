<?php

namespace App\Exceptions;

use App\Models\Visit;
use RuntimeException;

class RecentVisitException extends RuntimeException
{
    public function __construct(public readonly Visit $recentVisit)
    {
        parent::__construct('Ya existe una atención muy reciente. Confirma el duplicado e indica el motivo.');
    }
}
