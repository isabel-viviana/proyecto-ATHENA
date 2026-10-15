const fs = require("fs");
const path = require("path");

const files = [
    "src/index.css",
    "src/components/MainLayout.jsx",
    "src/components/Sidebar.jsx",
    "src/components/Sidebar.css",
    "src/components/header/Header.css",
    "src/components/navbar/Navbar.css",
    "src/components/fooder/Fooder.css",
    "src/App.jsx",
];

files.forEach((f) => {
    const p = path.join(__dirname, f);
    if (fs.existsSync(p)) {
        try {
            let buf = fs.readFileSync(p);
            // If it starts with FF FE, it's UTF-16 LE BOM
            if (buf.length >= 2 && buf[0] === 0xff && buf[1] === 0xfe) {
                let content = buf.toString("utf16le");
                fs.writeFileSync(p, content, "utf8");
                console.log(`Fixed ${f}`);
            }
        } catch (e) {}
    }
});
