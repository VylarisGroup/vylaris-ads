// Nur für die Entwicklung: CSS neu bauen mit
//   npx tailwindcss@3 -c resources/tailwind.config.js -i resources/app.css -o public/assets/app.css --minify
module.exports = {
  content: ['./app/**/*.php', './public/assets/app.js'],
  theme: {
    extend: {
      colors: {
        brand: { 50: '#f3f1fe', 100: '#e7e2fd', 200: '#d0c6fb', 300: '#b1a0f6', 400: '#8f72ef', 500: '#7351e6', 600: '#5b3fd6', 700: '#4b31b4', 800: '#3e2a92', 900: '#342675' },
      },
    },
  },
  plugins: [],
};
