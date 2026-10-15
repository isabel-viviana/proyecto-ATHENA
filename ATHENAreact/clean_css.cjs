const fs = require("fs");
const path = require("path");
const postcss = require("postcss");

const selectorsToRemove = [
    "*",
    "body",
    ":root",
    ".layout",
    ".sidebar",
    ".sidebar__logo",
    ".sidebar__logo i",
    ".sidebar__brand",
    ".sidebar__nav",
    ".sidebar__link",
    ".sidebar__link i",
    ".sidebar__link:hover",
    ".sidebar__link--active",
    ".sidebar__link--notifications",
    ".sidebar__badge",
    ".main-content",
    ".ai-button",
];

const plugin = postcss.plugin("clean-css", () => {
    return (root) => {
        root.walkRules((rule) => {
            // Split selectors (in case of comma-separated)
            const selectors = rule.selectors.filter(
                (sel) => !selectorsToRemove.includes(sel.trim()),
            );

            if (selectors.length === 0) {
                rule.remove(); // Remove entire rule if all selectors are in the remove list
            } else {
                rule.selectors = selectors; // Keep only the allowed selectors
            }
        });

        // Also remove any comments that might have been left over right before the removed rules
        // (Optional, let's just let postcss format it)
    };
});

function walk(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            walk(fullPath);
        } else if (file.endsWith(".css")) {
            const css = fs.readFileSync(fullPath, "utf8");

            postcss([plugin])
                .process(css, { from: fullPath, to: fullPath })
                .then((result) => {
                    fs.writeFileSync(fullPath, result.css, "utf8");
                    console.log(`Cleaned ${file}`);
                })
                .catch((err) => {
                    console.error(`Error processing ${file}:`, err);
                });
        }
    }
}

const targetDir = path.join(__dirname, "src", "pages");
console.log("Cleaning CSS in:", targetDir);
walk(targetDir);
