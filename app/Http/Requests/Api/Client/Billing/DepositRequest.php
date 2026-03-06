<?php

namespace Pterodactyl\Http\Requests\Api\Client\Billing;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Pterodactyl\Services\Billing\PaymentGatewayResolver;

class DepositRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string'],
        ];
    }

    /**
     * Validate that the method is available.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $resolver = app(PaymentGatewayResolver::class);
            $available = $resolver->getAvailableMethods();

            if (!in_array($this->input('method'), $available)) {
                $validator->errors()->add('method', 'The selected payment method is not available.');
            }
        });
    }
}
