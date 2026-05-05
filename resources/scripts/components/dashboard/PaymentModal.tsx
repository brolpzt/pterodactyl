import React, { useState, useEffect } from 'react';
import Modal from '@/components/elements/Modal';
import Button from '@/components/elements/Button';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCreditCard, faWallet } from '@fortawesome/free-solid-svg-icons';
import { createDeposit } from '@/api/account/billing';
import { useCurrency } from '@/context/CurrencyContext';
import { httpErrorToHuman } from '@/api/http';
import useFlash from '@/plugins/useFlash';

export type PaymentMethod = 'stripe' | 'pix';

interface PaymentMethodOption {
    id: PaymentMethod;
    label: string;
    icon: string;
}

const PAYMENT_METHODS: PaymentMethodOption[] = [
    { id: 'stripe', label: 'Stripe', icon: 'stripe' },
    { id: 'pix', label: 'Pix', icon: 'pix' },
];

interface PaymentModalProps {
    visible: boolean;
    onDismissed: () => void;
    onSuccess?: () => void;
    availableMethods?: string[];
    /** Pre-defined amount in dollars. null = custom amount (user enters in modal) */
    initialAmount: number | null;
}

const PaymentModal = ({ visible, onDismissed, onSuccess, availableMethods = [], initialAmount }: PaymentModalProps) => {
    const { formatPrice } = useCurrency();
    const { addError, clearFlashes } = useFlash();
    const [customAmount, setCustomAmount] = useState('');
    const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        if (visible) {
            setCustomAmount('');
            setSelectedMethod(null);
        }
    }, [visible]);

    const isCustom = initialAmount === null;
    const parsedCustom = parseFloat(customAmount);
    const isValidCustom = !isNaN(parsedCustom) && parsedCustom > 0;
    const displayAmount = isCustom ? (isValidCustom ? parsedCustom : null) : initialAmount;
    const enabledMethods = PAYMENT_METHODS.filter((method) => availableMethods.includes(method.id));
    const canSubmit = enabledMethods.length > 0 && displayAmount != null && displayAmount > 0 && selectedMethod != null && !isSubmitting;

    const handleSubmit = () => {
        if (!canSubmit || !selectedMethod) return;
        clearFlashes('billing');
        setIsSubmitting(true);
        createDeposit(displayAmount!, selectedMethod)
            .then((result) => {
                if (result.checkout_url) {
                    window.location.assign(result.checkout_url);
                    return;
                }

                onSuccess?.();
                onDismissed();
            })
            .catch((err) => {
                addError({ key: 'billing', message: httpErrorToHuman(err) });
                setIsSubmitting(false);
            });
    };

    const handleDismissed = () => {
        setCustomAmount('');
        setSelectedMethod(null);
        onDismissed();
    };

    const getIcon = (icon: string) => {
        if (icon === 'stripe') return faCreditCard;
        return faWallet;
    };

    return (
        <Modal visible={visible} onDismissed={handleDismissed}>
            <h2 css={tw`text-2xl font-black text-neutral-100 mb-6`}>Adicionar créditos</h2>

            <div css={tw`mb-6`}>
                <Label htmlFor={'amount'} css={tw`mb-2`}>
                    Valor
                </Label>
                {isCustom ? (
                    <div css={tw`flex items-center gap-2`}>
                        <span css={tw`text-neutral-400 font-semibold`}>$</span>
                        <Input
                            id={'amount'}
                            type={'number'}
                            min={1}
                            step={0.01}
                            placeholder={'0.00'}
                            value={customAmount}
                            onChange={(e) => setCustomAmount(e.target.value)}
                        />
                    </div>
                ) : (
                    <p css={tw`text-2xl font-mono font-black text-neutral-100 py-2`}>{formatPrice(displayAmount!)}</p>
                )}
            </div>

            <div css={tw`mb-6`}>
                <Label css={tw`mb-3 block`}>Forma de pagamento</Label>
                <div css={tw`grid grid-cols-1 sm:grid-cols-3 gap-3`}>
                    {enabledMethods.map((method) => (
                        <button
                            key={method.id}
                            type="button"
                            onClick={() => setSelectedMethod(method.id)}
                            css={[
                                tw`p-4 rounded border transition-all duration-200 flex items-center gap-3`,
                                selectedMethod === method.id
                                    ? tw`border-primary-500 bg-primary-500/10`
                                    : tw`border-neutral-600 bg-neutral-700 hover:border-neutral-500`,
                            ]}
                        >
                            <FontAwesomeIcon
                                icon={getIcon(method.icon)}
                                css={[tw`text-xl`, selectedMethod === method.id ? tw`text-primary-400` : tw`text-neutral-400`]}
                            />
                            <span css={tw`text-sm font-bold text-neutral-200`}>{method.label}</span>
                        </button>
                    ))}
                </div>
                {enabledMethods.length === 0 && (
                    <p css={tw`text-xs text-amber-400 mt-3`}>
                        Nenhum metodo de pagamento disponivel no momento.
                    </p>
                )}
            </div>

            <div css={tw`flex flex-wrap justify-end gap-3 pt-4 border-t border-neutral-600`}>
                <Button isSecondary onClick={handleDismissed} disabled={isSubmitting} css={tw`w-full sm:w-auto`}>
                    Cancelar
                </Button>
                <Button color={'primary'} onClick={handleSubmit} disabled={!canSubmit} isLoading={isSubmitting} css={tw`w-full sm:w-auto`}>
                    {canSubmit ? `Pagar ${formatPrice(displayAmount!)}` : 'Pagar'}
                </Button>
            </div>
        </Modal>
    );
};

export default PaymentModal;
