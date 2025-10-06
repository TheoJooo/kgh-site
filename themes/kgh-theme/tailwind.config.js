/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./**/*.php", "./assets/js/**/*.js"],
  theme: {
    extend: {
      colors: {
        kgh: {
          red:  "#7A2E1A",   // Red Clay
          redclayLight: "#F0E9E7",
          grey: "#373737",   // Sesame Grey
          sesameDark: "#292929", 
          sesameDarker: "#131313",
          sesameLight: "#EBEBEB",
          sesameLightActive: "#C1C1C1",
          sesameLight: "#F7F4F3",
          bg: "#F5F3F0",   // Porcelain White
          porcelainWhite: "#FDFAFA",
          porecelainDark: "#BEBCBC",
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
