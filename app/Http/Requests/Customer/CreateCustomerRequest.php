<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ApiRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Rules\ValidateAlias;

class CreateCustomerRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required',
            'alias' => [
                'bail',
                'required',
                new ValidateAlias(Customer::class)
            ],
        ];
    }
}
