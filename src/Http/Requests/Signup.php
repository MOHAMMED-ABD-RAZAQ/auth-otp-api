<?php

namespace Ma\AuthOtpApi\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Ma\AuthOtpApi\Traits\ResponseTrait;

class Signup extends FormRequest
{
    use ResponseTrait;
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
            'phone' => ['required', 'unique:users,phone', 'max:25'],
            'password' => 'required|min:8',
            'email' => 'required|unique:users,email',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return  [
            'phone.unique' => 'auth.signup_account_exists'
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = (new ValidationException($validator))->errors();

        foreach ($errors as $error) foreach ($error as $key => $value) {
            $messsage = $value;
        }

        throw new HttpResponseException($this->responseError(null, $messsage));
    }
}
