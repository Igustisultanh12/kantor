import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                nova: ['Figtree', ...defaultTheme.fontFamily.sans],
                'nova-mono': ['"Geist Mono Variable"', '"Geist Mono"', ...defaultTheme.fontFamily.mono],
            },
            // Token Nova (resources/css/nova.css). Hanya bernilai di dalam .nova.
            colors: {
                nv: {
                    canvas: 'var(--nv-bg-canvas)',
                    surface: 'var(--nv-bg-surface)',
                    subtle: 'var(--nv-bg-subtle)',
                    border: 'var(--nv-border)',
                    'border-input': 'var(--nv-border-input)',
                    fg: 'var(--nv-fg)',
                    muted: 'var(--nv-fg-muted)',
                    'fg-subtle': 'var(--nv-fg-subtle)',
                    accent: 'var(--nv-accent)',
                    'accent-solid': 'var(--nv-accent-solid)',
                    'accent-soft': 'var(--nv-accent-soft)',
                    'on-soft': 'var(--nv-accent-on-soft)',
                    success: 'var(--nv-success)',
                    'success-fg': 'var(--nv-success-fg)',
                    warning: 'var(--nv-warning)',
                    'warning-fg': 'var(--nv-warning-fg)',
                    danger: 'var(--nv-danger)',
                },
            },
            borderRadius: { 'nv-md': '10px', 'nv-lg': '14px', 'nv-xl': '20px' },
        },
    },

    plugins: [forms],
};
