<?php

namespace App\Http\Resources\HITUAM\HITUAM01;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->IID,
            'code' => $this->VDEPT,
            'desc' => $this->VPROJECTDESC,
            'prefix' => $this->VPREFIXPROJECT,
            'pic' => $this->VPIC,
            'portal' => $this->VPORTALNAME,
            'operational' => $this->VOPERATIONAL,
            'std' => $this->VSTRDZATION,
            'portal_access' => $this->VPORTALACCESS,
            'host' => $this->VHOST,
            'url' => $this->VHOST,
            'publish' => $this->VPUBLISH,
            'database' => $this->VDATABASE,
            'order' => $this->NORDERPROJECT,
            'icon' => $this->VICON,
            'is_embedded' => (bool) $this->BIS_EMBED,
            'created_at' => $this->DCREA,
            'updated_at' => $this->DMODI,
        ];
    }
}
