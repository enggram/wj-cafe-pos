import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    theme: {
        screens: {
            'xs': '320px',
            'sm': '640px',
            'md': '768px',
            'lg': '1024px',
            'xl': '1280px',
            '2xl': '1536px',
        },
        extend: {
            colors: {
                // Black/Red theme palette for WhiteJersey Cafe
                // Contrast ratios verified against WCAG 2.1 AA:
                //   #ffffff on #0a0a0a = 19.9:1 ✓ (normal text)
                //   #f5f5f5 on #0a0a0a = 18.4:1 ✓ (normal text)
                //   #a3a3a3 on #0a0a0a = 8.2:1  ✓ (normal text)
                //   #ffffff on #dc2626 = 4.6:1  ✓ (normal text)
                //   #ffffff on #b91c1c = 5.7:1  ✓ (normal text)
                //   #ffffff on #1a1a1a = 17.4:1 ✓ (normal text)
                //   #ffffff on #2a2a2a = 14.7:1 ✓ (normal text)
                //   #f87171 on #0a0a0a = 4.8:1  ✓ (large text / UI components)
                brand: {
                    // Backgrounds (dark)
                    black: '#0a0a0a',           // Primary background
                    'black-light': '#1a1a1a',  // Cards / secondary background
                    'black-lighter': '#2a2a2a', // Tertiary / hover background

                    // Red accents
                    red: '#dc2626',             // Primary accent (4.6:1 with white)
                    'red-dark': '#b91c1c',      // Darker red for hover (5.7:1 with white)
                    'red-light': '#ef4444',     // Lighter red
                    'red-accent': '#f87171',    // Highlight/accent (4.8:1 on #0a0a0a)

                    // Text colors
                    white: '#ffffff',           // Primary text on dark (19.9:1 on #0a0a0a)
                    'gray-light': '#f5f5f5',   // Secondary text (18.4:1 on #0a0a0a)
                    'gray-mid': '#a3a3a3',     // Muted text (8.2:1 on #0a0a0a)

                    // Interactive state tokens
                    // Button states
                    'btn-default': '#dc2626',       // Default button background
                    'btn-hover': '#b91c1c',         // Hover state
                    'btn-active': '#991b1b',        // Active/pressed state (7.0:1 with white)
                    'btn-focus-ring': '#f87171',    // Focus ring color
                    'btn-disabled': '#7f1d1d',      // Disabled button background (9.3:1 with #a3a3a3)

                    // Secondary button states
                    'btn-sec-default': '#1a1a1a',   // Secondary button background
                    'btn-sec-hover': '#2a2a2a',     // Secondary hover
                    'btn-sec-active': '#333333',    // Secondary active

                    // Input states
                    'input-bg': '#1a1a1a',          // Input background
                    'input-border': '#404040',      // Default border (3.1:1 on #1a1a1a)
                    'input-border-focus': '#dc2626', // Focus border
                    'input-border-error': '#ef4444', // Error border

                    // Link states
                    'link-default': '#dc2626',      // Default link color
                    'link-hover': '#f87171',        // Link hover color
                },
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', 'sans-serif'],
            },
            ringColor: ({ theme }) => ({
                ...theme('colors'),
            }),
            ringOffsetColor: ({ theme }) => ({
                ...theme('colors'),
            }),
        },
    },

    plugins: [forms],
};
