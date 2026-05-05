<?php

namespace Pterodactyl\Http\Requests\Api\Client\Billing;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Pterodactyl\Services\Billing\PaymentGatewayResolver;
use Pterodactyl\Support\Cpf;

class DepositRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string'],
            'payer_cpf' => ['nullable', 'string', 'max:20'],
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

            if (strtolower((string) $this->input('method')) === 'pix') {
                $cpfRaw = (string) $this->input('payer_cpf', '');
                if (!Cpf::isValid($cpfRaw)) {
                    $validator->errors()->add('payer_cpf', 'CPF invalido. Informe um CPF valido para pagamentos PIX.');
                }
            }
        });
    }
}
