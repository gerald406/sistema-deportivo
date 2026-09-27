import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // Zona publica (landing y resultados): Unbounded para titulos, Sora para texto.
                display: ['Unbounded', ...defaultTheme.fontFamily.sans],
                body: ['Sora', ...defaultTheme.fontFamily.sans],
            },
            // Paleta de marca de la zona publica. El panel admin sigue con
            // indigo/gray de Tailwind (no teniamos paleta de marca propia).
            colors: {
                brand: {
                    primary: '#045694',
                    secondary: '#077bcb',
                    accent: '#FF8A2C',
                    gold: '#FFB800',
                },
            },
        },
    },

    plugins: [forms, typography],
};
