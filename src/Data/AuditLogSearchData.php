<?php

namespace App\Data;

use App\Entity\User;

class AuditLogSearchData
{
    public ?string $action = null;

    public ?string $entityType = null;

    public ?User $user = null;

    public ?\DateTimeInterface $dateFrom = null;

    public ?\DateTimeInterface $dateTo = null;
}