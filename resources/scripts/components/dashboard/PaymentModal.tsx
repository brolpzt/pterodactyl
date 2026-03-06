import React, { useState, useEffect } from 'react';
import Modal from '@/components/elements/Modal';
import Button from '@/components/elements/Button';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCreditCard, faWallet } from '@fortawesome/free-solid-svg-icons';

export type PaymentMethod = 'stripe' | 'pix';

interface PaymentModalProps {
    visible: boolean;
    onDismissed: () => void;
    /** Pre-defined amount in dollars. null = custom amount (user enters in modal) */
    initialAmount: number | null;
}

const PaymentModal = ({ visible, onDismissed, initialAmount }: PaymentModalProps) => {
    const [customAmount, setCustomAmount] = useState('');
    const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);

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
    const canSubmit = displayAmount != null && displayAmount > 0 && selectedMethod != null;

    const handleSubmit = () => {
        if (!canSubmit) return;
        console.log('Payment:', { amount: displayAmount, method: selectedMethod });
        onDismissed();
    };

    const handleDismissed = () => {
        setCustomAmount('');
        setSelectedMethod(null);
        onDismissed();
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
                    <p css={tw`text-2xl font-mono font-black text-neutral-100 py-2`}>${displayAmount!.toFixed(2)}</p>
                )}
            </div>

            <div css={tw`mb-6`}>
                <Label css={tw`mb-3 block`}>Forma de pagamento</Label>
                <div css={tw`grid grid-cols-2 gap-3`}>
                    <button
                        type="button"
                        onClick={() => setSelectedMethod('stripe')}
                        css={[
                            tw`p-4 rounded border transition-all duration-200 flex items-center gap-3`,
                            selectedMethod === 'stripe'
                                ? tw`border-primary-500 bg-primary-500/10`
                                : tw`border-neutral-600 bg-neutral-700 hover:border-neutral-500`,
                        ]}
                    >
                        <FontAwesomeIcon
                            icon={faCreditCard}
                            css={[tw`text-xl`, selectedMethod === 'stripe' ? tw`text-primary-400` : tw`text-neutral-400`]}
                        />
                        <span css={tw`text-sm font-bold text-neutral-200`}>Stripe</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setSelectedMethod('pix')}
                        css={[
                            tw`p-4 rounded border transition-all duration-200 flex items-center gap-3`,
                            selectedMethod === 'pix'
                                ? tw`border-primary-500 bg-primary-500/10`
                                : tw`border-neutral-600 bg-neutral-700 hover:border-neutral-500`,
                        ]}
                    >
                        <FontAwesomeIcon
                            icon={faWallet}
                            css={[tw`text-xl`, selectedMethod === 'pix' ? tw`text-primary-400` : tw`text-neutral-400`]}
                        />
                        <span css={tw`text-sm font-bold text-neutral-200`}>Pix</span>
                    </button>
                </div>
            </div>

            <div css={tw`flex flex-wrap justify-end gap-3 pt-4 border-t border-neutral-600`}>
                <Button isSecondary onClick={handleDismissed} css={tw`w-full sm:w-auto`}>
                    Cancelar
                </Button>
                <Button color={'primary'} onClick={handleSubmit} disabled={!canSubmit} css={tw`w-full sm:w-auto`}>
                    {canSubmit ? `Pagar $${displayAmount!.toFixed(2)}` : 'Pagar'}
                </Button>
            </div>
        </Modal>
    );
};

export default PaymentModal;
