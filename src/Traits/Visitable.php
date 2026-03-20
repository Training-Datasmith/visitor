<?php

declare (strict_types=1);
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
    public function visit_logs()
    {
        return $this->morph_many(Visit::class, 'visitable');
    }
    /**
     * Create a visit log.
     *
     *
     * @return mixed
     */
    public function create_visit_log(?Model $visitor)
    {
        return app('shetabit-visitor')->set_visitor($visitor)->visit($this);
    }
}