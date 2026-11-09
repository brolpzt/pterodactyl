import {
    CheckCircleIcon,
    ExclamationIcon,
    InformationCircleIcon,
    ShieldExclamationIcon,
} from '@heroicons/react/outline';
import React from 'react';
import classNames from 'classnames';

interface AlertProps {
    type: 'warning' | 'danger' | 'success' | 'info';
    className?: string;
    children: React.ReactNode;
}

const icons = {
    danger: <ShieldExclamationIcon className={'w-6 h-6 flex-shrink-0 text-red-400 mr-2'} />,
    warning: <ExclamationIcon className={'w-6 h-6 flex-shrink-0 text-yellow-500 mr-2'} />,
    success: <CheckCircleIcon className={'w-6 h-6 flex-shrink-0 text-green-400 mr-2'} />,
    info: <InformationCircleIcon className={'w-6 h-6 flex-shrink-0 text-blue-400 mr-2'} />,
};

export default ({ type, className, children }: AlertProps) => {
    return (
        <div
            className={classNames(
                'flex items-center border-l-8 text-gray-50 rounded-md shadow px-4 py-3',
                {
                    ['border-red-500 bg-red-500/25']: type === 'danger',
                    ['border-yellow-500 bg-yellow-500/25']: type === 'warning',
                    ['border-green-500 bg-green-500/25']: type === 'success',
                    ['border-blue-500 bg-blue-500/25']: type === 'info',
                },
                className
            )}
        >
            {icons[type]}
            {children}
        </div>
    );
};
