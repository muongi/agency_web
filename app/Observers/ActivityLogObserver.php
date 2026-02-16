<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Request;

class ActivityLogObserver
{
    /**
     * Handle the activity "creating" event.
     */
    public function creating(ActivityLog $activity): void
    {
        $this->addContext($activity);
    }

    /**
     * Add business unit, IP address, and user agent to activity log.
     */
    protected function addContext(ActivityLog $activity): void
    {
        if (auth()->check()) {
            $businessUnit = auth()->user()->currentBusinessUnit;
            
            if ($businessUnit) {
                $activity->business_unit_id = $businessUnit->id;
            }
        }
        
        $activity->ip_address = Request::ip();
        $activity->user_agent = Request::userAgent();
    }
}

