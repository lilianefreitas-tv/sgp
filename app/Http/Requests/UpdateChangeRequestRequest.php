<?php

namespace App\Http\Requests;

class UpdateChangeRequestRequest extends StoreChangeRequestRequest
{
    public function authorize(): bool
    {
        $changeRequest = $this->route('changeRequest');

        return $this->user() !== null
            && $changeRequest !== null
            && $this->user()->can('update', $changeRequest);
    }
}
