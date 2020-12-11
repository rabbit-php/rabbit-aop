<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\AdviceAfter;
use Go\Aop\Intercept\Joinpoint;

/**
 * "After" interceptor
 *
 * @api
 */
final class AfterReturnInterceptor extends AbstractInterceptor implements AdviceAfter
{
    /**
     * @inheritdoc
     */
    public function invoke(Joinpoint $joinpoint)
    {
        try {
            return $joinpoint->result = $joinpoint->proceed();
        } finally {
            ($this->adviceMethod)($joinpoint);
            return $joinpoint->result;
        }
    }
}
