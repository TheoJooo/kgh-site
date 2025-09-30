/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./**/*.php", "./assets/js/**/*.js"],
  theme: {
    extend: {
      colors: {
        kgh: {
          red:  "#7A2E1A",   // Red Clay
          grey: "#5C5C5C",   // Sesame Grey
          bg:   "#F5F3F0",   // Porcelain White
          blue: "#284B63",   // (ton Blue si tu l’utilises)
        },
      },
      fontFamily: {
        serif: ["Merriweather", "serif"],
        sans:  ["Noto Sans", "ui-sans-serif", "system-ui"],
        kr:    ["Noto Serif KR", "serif"],
      },
      maxWidth: { kgh: "1120px" },
    },
  },
  plugins: [
    require('@tailwindcss/line-clamp'),
  ],
}
