const colors = require('tailwindcss/colors');

/** Paleta alinhada ao hostgamer.net */
const neutral = {
    50: '#f7f9ff',
    100: '#e8ecf7',
    200: '#c5cce0',
    300: '#8a97bb',
    400: '#7d7d8e',
    500: '#5a5a6c',
    600: '#2d2d3a',
    700: '#111420',
    800: '#111420',
    900: '#090911',
};

const blue = {
    50: '#f7f9ff',
    100: '#e8efff',
    200: '#c5d4ff',
    300: '#8fabff',
    400: '#4f7bff',
    500: '#2258ff',
    600: '#1a46cc',
    700: '#14214b',
    800: '#0f1840',
    900: '#090d28',
};

module.exports = {
    content: [
        './resources/scripts/**/*.{js,ts,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Manrope"', 'Arial', 'Helvetica', 'sans-serif'],
                header: ['"Oxanium"', 'Arial', 'Helvetica', 'sans-serif'],
                mono: ['ui-monospace', '"Cascadia Code"', '"Courier New"', 'Courier', 'monospace'],
            },
            colors: {
                black: '#090911',
                primary: blue,
                blue,
                gray: neutral,
                neutral,
                cyan: blue,
            },
            fontSize: {
                '2xs': '0.625rem',
            },
            transitionDuration: {
                250: '250ms',
            },
            borderColor: theme => ({
                default: theme('colors.neutral.600', 'currentColor'),
            }),
        },
    },
    plugins: [
        require('@tailwindcss/line-clamp'),
        require('@tailwindcss/forms')({
            strategy: 'class',
        }),
    ],
};
