/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        bg: '#F5F7FB',
        surface: '#FFFFFF',
        'surface-2': '#FCFDFF',
        border: '#E5EAF2',
        text: '#0F172A',
        'text-2': '#475569',
        muted: '#94A3B8',
        primary: {
          DEFAULT: '#F97316',
          hover: '#EA580C',
          soft: '#FFEDD5',
        },
        amber: '#F59E0B',
        'amber-soft': '#FFF6E6',
        'info-soft': '#FFEDD5',
        'info-text': '#C2410C',
        line: '#E5EAF2',
      },
      fontFamily: {
        jakarta: ['"Plus Jakarta Sans"', 'Inter', 'ui-sans-serif', 'system-ui'],
      },
      boxShadow: {
        card: '0 10px 30px rgba(15, 23, 42, 0.10)',
        soft: '0 6px 16px rgba(15, 23, 42, 0.08)',
      },
      borderRadius: {
        soft: '22px',
        pill: '9999px',
      },
      borderColor: {
        DEFAULT: '#E5EAF2',
      },
    },
  },
  plugins: [],
}
