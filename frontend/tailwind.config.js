/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#2F80ED',
          dark: '#1F63C9',
          light: '#E8F1FF',
        },
        surface: '#F7F9FC',
        muted: '#6B7280',
        line: '#E5E7EB',
      },
      fontFamily: {
        jakarta: ['"Plus Jakarta Sans"', 'Inter', 'ui-sans-serif', 'system-ui'],
      },
      boxShadow: {
        card: '0 10px 30px rgba(47, 128, 237, 0.08)',
        soft: '0 8px 24px rgba(0,0,0,0.06)',
      },
      borderRadius: {
        soft: '18px',
        pill: '9999px',
      },
    },
  },
  plugins: [],
}
