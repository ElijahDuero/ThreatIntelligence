/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                night: {
                    950: '#060b16',
                    900: '#0a1122',
                    850: '#0c1529',
                    800: '#101b34',
                    700: '#162444',
                },
                line: '#1e2c4d',
                'line-soft': '#17233e',
                gold: {
                    200: '#f7e3ae',
                    300: '#f0cf7c',
                    400: '#e4b84f',
                    500: '#d4a437',
                    600: '#b7872a',
                    700: '#8f6a20',
                },
                cream: '#ece5d1',
                mist: '#8e9bb8',
                'mist-dim': '#5f6d8c',
                jade: {
                    400: '#41d39a',
                    500: '#22b57e',
                },
                crimson: {
                    400: '#d46a5f',
                    500: '#b91c1c',
                },
            },
            fontFamily: {
                serif: ['Cinzel', 'Georgia', 'Cambria', 'serif'],
                sans: ['Instrument Sans', 'Inter', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
                mono: ['JetBrains Mono', 'Fira Code', 'SFMono-Regular', 'Menlo', 'monospace'],
            },
        },
    },
    plugins: [],
};
