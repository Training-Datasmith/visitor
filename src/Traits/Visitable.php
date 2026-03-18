<?php

declare(strict_types=1);

namespace Shetabit\Visitor\Traits;

use Illuminate\Database\Eloquent\Model;
use Shetabit\Visitor\Models\Visit;

trait Visitable
{
    /**
     * Get all of the model visit logs.
     *
     * @return mixed
     */
    public function visitLogs()
    {
        return $this->morphMany(Visit::class, 'visitable');
    }

    /**
     * Create a visit log.
     *
     *
     * @return mixed
     */
    public function createVisitLog(?Model $visitor)
    {
        return app('shetabit-visitor')->setVisitor($visitor)->visit($this);
    }
}
