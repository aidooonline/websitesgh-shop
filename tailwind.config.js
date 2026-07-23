/** @type {import('tailwindcss').Config} */
/**
 * Palette and type tokens are copied verbatim from the live websitesgh.com
 * :root block so the shop and the directory read as one brand.
 * Source of truth: websitesgh-v3 theme. Do not invent new hues here.
 */
module.exports = {
  content: ["./**/*.php", "./assets/js/**/*.js"],
  safelist: [
    "is-active", "menu-open", "chat-open",
    "!opacity-100", "opacity-0", "w-5", "w-1.5", "bg-wgh-green", "bg-wgh-line",
  ],
  theme: {
    extend: {
      colors: {
        wgh: {
          bg:         "#FFFFFF",
          ink:        "#14201A",
          ink2:       "#586860",
          ink3:       "#9AA39C",
          line:       "#EDEAE0",
          line2:      "#F5F2E8",
          lineStrong: "#D7D2C4",
          green:      "#0E8C5A",
          greenV:     "#0A6E47",
          greenPale:  "#E9F7F0",
          greenInk:   "#07321F",
          gold:       "#E2A013",
          goldV:      "#B68211",
          goldPale:   "#FBEFD2",
          goldInk:    "#6B4905",
          red:        "#c0392b",
          redPale:    "#fef2f2",
        },
      },
      fontFamily: {
        display: ["Figtree", "system-ui", "-apple-system", "sans-serif"],
        body:    ["Figtree", "system-ui", "-apple-system", "sans-serif"],
        prose:   ["Figtree", "system-ui", "-apple-system", "sans-serif"],
      },
      borderRadius: { sm: "6px", DEFAULT: "10px", lg: "16px", xl: "22px" },
      maxWidth: { shell: "1240px", narrow: "780px", read: "1400px", measure: "820px" },
      spacing: { gutter: "24px", hdr: "68px" },
      boxShadow: {
        card: "0 10px 30px -18px rgba(20,32,26,.28)",
        soft: "0 4px 16px -8px rgba(20,32,26,.20)",
        lift: "0 18px 40px -22px rgba(14,140,90,.35)",
      },
      backgroundImage: {
        "green-fade": "linear-gradient(180deg,#E9F7F0 0%,#FFFFFF 100%)",
        "gold-fade":  "linear-gradient(180deg,#FBEFD2 0%,#FFFFFF 100%)",
      },
      keyframes: {
        riseIn: { "0%": { opacity: 0, transform: "translateY(14px)" }, "100%": { opacity: 1, transform: "translateY(0)" } },
      },
      animation: { riseIn: "riseIn .5s cubic-bezier(.16,1,.3,1) both" },
    },
  },
  plugins: [],
};
