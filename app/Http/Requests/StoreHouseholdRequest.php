<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && in_array($user->role, ['admin', 'barangay_staff']);
    }

    public function rules(): array
    {
        return [
            'household_head'          => 'required|string|max:255',
            'barangay_id'             => 'required|exists:barangays,id',
            'sex'                     => 'required|in:Male,Female',
            'age'                     => 'required|integer|min:0|max:130',
            'gender'                  => 'required|in:Male,Female,Other',
            'house_type'              => 'nullable|string|max:100',
            'sitio_purok_zone'        => 'nullable|string|max:255',
            'ip_non_ip'               => 'nullable|in:IP,Non-IP',
            'hh_id'                   => 'nullable|string|max:100',
            'latitude'                => 'required|numeric|between:12.50,13.20',
            'longitude'               => 'required|numeric|between:120.50,121.20',
            'preparedness_kit'        => 'nullable|boolean',
            'educational_attainment'  => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.between'  => 'Latitude must be within the Sablayan area (12.50 – 13.20).',
            'longitude.between' => 'Longitude must be within the Sablayan area (120.50 – 121.20).',
        ];
    }
}
