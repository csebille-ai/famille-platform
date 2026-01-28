import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    safelist: [
        // Category badge colors
        'bg-teal-50', 'text-teal-800', 'border-teal-200',
        'bg-violet-50', 'text-violet-800', 'border-violet-200',
        'bg-blue-50', 'text-blue-800', 'border-blue-200',
        'bg-sky-50', 'text-sky-800', 'border-sky-200',
        'bg-rose-50', 'text-rose-800', 'border-rose-200',
        'bg-slate-50', 'text-slate-800', 'border-slate-200',
        'bg-amber-50', 'text-amber-800', 'border-amber-200',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
