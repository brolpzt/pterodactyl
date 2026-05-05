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
    userCpf?: string | null;
    /** Pre-defined amount in dollars. null = custom amount (user enters in modal) */
    initialAmount: number | null;
}

interface PixPayload {
    transactionId?: string | null;
    taxId?: string | null;
    pixCode?: string | null;
    qrImageDataUrl?: string | null;
}

const onlyDigits = (value: string): string => value.replace(/\D+/g, '');

const formatCpf = (value: string): string => {
    const digits = onlyDigits(value).slice(0, 11);
    return digits
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
};

const PaymentModal = ({ visible, onDismissed, onSuccess, availableMethods = [], userCpf = null, initialAmount }: PaymentModalProps) => {
    const { formatPrice, currency, exchangeRates } = useCurrency();
    const { addError, clearFlashes } = useFlash();
    const [customAmount, setCustomAmount] = useState('');
    const [selectedMethod, setSelectedMethod] = useState<PaymentMethod | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [pixPayload, setPixPayload] = useState<PixPayload | null>(null);
    const [payerCpf, setPayerCpf] = useState('');

    useEffect(() => {
        if (visible) {
            setCustomAmount('');
            setSelectedMethod(null);
            setPixPayload(null);
            setPayerCpf(userCpf ? formatCpf(userCpf) : '');
        }
    }, [visible, userCpf]);

    const isCustom = initialAmount === null;
    const parsedCustom = parseFloat(customAmount);
    const selectedRate = currency.code === 'USD'
        ? 1
        : ((exchangeRates?.[currency.code as keyof typeof exchangeRates] as number | undefined) ?? 0);
    const customAmountUsd = currency.code === 'USD'
        ? parsedCustom
        : (selectedRate > 0 ? parsedCustom / selectedRate : NaN);
    const isValidCustom = !isNaN(customAmountUsd) && customAmountUsd > 0;
    const displayAmount = isCustom ? (isValidCustom ? customAmountUsd : null) : initialAmount;
    const displayCustomInSelectedCurrency = !isNaN(parsedCustom) && parsedCustom > 0
        ? `${currency.symbol}${parsedCustom.toFixed(2)}`
        : null;
    const enabledMethods = PAYMENT_METHODS.filter((method) => availableMethods.includes(method.id));
    const cpfDigits = onlyDigits(payerCpf);
    const requiresCpf = selectedMethod === 'pix';
    const isCpfValid = cpfDigits.length === 11;
    const canSubmit = enabledMethods.length > 0
        && displayAmount != null
        && displayAmount > 0
        && selectedMethod != null
        && !isSubmitting
        && (!requiresCpf || isCpfValid);

    const handleSubmit = () => {
        if (!canSubmit || !selectedMethod) return;
        clearFlashes('billing');
        setIsSubmitting(true);
        createDeposit(displayAmount!, selectedMethod, selectedMethod === 'pix' ? cpfDigits : undefined)
            .then((result) => {
                if (result.checkout_url) {
                    window.location.assign(result.checkout_url);
                    return;
                }

                if (result.gateway === 'pix' && result.pix_code) {
                    setPixPayload({
                        transactionId: result.transaction_id,
                        taxId: result.tax_id,
                        pixCode: result.pix_code,
                        qrImageDataUrl: result.qr_code_image?.image_data_url ?? null,
                    });
                    setIsSubmitting(false);
                    onSuccess?.();
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
        setPixPayload(null);
        setPayerCpf('');
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
                        <span css={tw`text-neutral-400 font-semibold`}>{currency.symbol}</span>
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
                {isCustom && displayCustomInSelectedCurrency && (
                    <p css={tw`text-xs text-neutral-500 mt-2`}>
                        Valor selecionado: {displayCustomInSelectedCurrency}
                    </p>
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
                            disabled={!!pixPayload}
                            css={[
                                tw`p-4 rounded border transition-all duration-200 flex items-center gap-3`,
                                selectedMethod === method.id
                                    ? tw`border-primary-500 bg-primary-500/10`
                                    : tw`border-neutral-600 bg-neutral-700 hover:border-neutral-500`,
                                pixPayload ? tw`opacity-60 cursor-not-allowed` : null,
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
                    <p css={tw`text-xs text-yellow-400 mt-3`}>
                        Nenhum metodo de pagamento disponivel no momento.
                    </p>
                )}
            </div>

            {selectedMethod === 'pix' && (
                <div css={tw`mb-6 space-y-6`}>
                    <Label htmlFor={'payerCpf'} css={tw`mb-2 block`}>
                        CPF do pagador
                    </Label>
                    <Input
                        id={'payerCpf'}
                        type={'text'}
                        value={payerCpf}
                        onChange={(e) => setPayerCpf(formatCpf(e.currentTarget.value))}
                        placeholder={'000.000.000-00'}
                        maxLength={14}
                        disabled={!!pixPayload}
                    />
                    <p
                        css={[
                            tw`text-xs mt-2`,
                            isCpfValid || payerCpf.length === 0 ? tw`text-neutral-500` : tw`text-red-400`,
                        ]}
                    >
                        {isCpfValid || payerCpf.length === 0
                            ? 'Este CPF sera usado para gerar a cobranca PIX.'
                            : 'Informe um CPF valido com 11 digitos.'}
                    </p>

                    {pixPayload && (
                        <div css={tw`rounded-lg border border-neutral-600 bg-neutral-800 p-4 space-y-3`}>
                            <p css={tw`text-sm text-neutral-200`}>
                                PIX gerado com sucesso. Efetue o pagamento usando o QR Code ou o codigo copia e cola abaixo.
                            </p>
                            {pixPayload.qrImageDataUrl && (
                                <div css={tw`flex justify-center`}>
                                    <img src={pixPayload.qrImageDataUrl} alt={'QR Code PIX'} css={tw`w-56 h-56 rounded bg-white p-2`} />
                                </div>
                            )}
                            {pixPayload.pixCode && (
                                <>
                                    <Label htmlFor={'pixCode'} css={tw`mb-1 block`}>
                                        Codigo PIX copia e cola
                                    </Label>
                                    <Input id={'pixCode'} type={'text'} value={pixPayload.pixCode} readOnly />
                                </>
                            )}
                            {pixPayload.taxId && <p css={tw`text-xs text-neutral-400`}>TAX_ID: {pixPayload.taxId}</p>}
                        </div>
                    )}
                </div>
            )}

            <div css={tw`flex flex-wrap justify-end gap-3 pt-4 border-t border-neutral-600`}>
                <Button isSecondary onClick={handleDismissed} disabled={isSubmitting} css={tw`w-full sm:w-auto`}>
                    Cancelar
                </Button>
                <Button
                    color={'primary'}
                    onClick={handleSubmit}
                    disabled={!canSubmit || !!pixPayload}
                    isLoading={isSubmitting}
                    css={tw`w-full sm:w-auto`}
                >
                    {canSubmit ? `Pagar ${isCustom ? displayCustomInSelectedCurrency : formatPrice(displayAmount!)}` : 'Pagar'}
                </Button>
            </div>
        </Modal>
    );
};

export default PaymentModal;
