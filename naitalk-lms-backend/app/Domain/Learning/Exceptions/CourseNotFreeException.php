<?php

namespace App\Domain\Learning\Exceptions;

class CourseNotFreeException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'This course is not free. Paid courses are unlocked through checkout '.
            '(App\Domain\Commerce\Services\CheckoutService) and membership_only courses through '.
            'an active membership (App\Domain\Membership\Services\MembershipGateService) — '.
            'not through EnrolmentService::enroll() with source "free".'
        );
    }
}
