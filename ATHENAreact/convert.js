import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function toPascalCase(str) {
    return str
        .replace(/[^a-zA-Z0-9]+(.)/g, (m, chr) => chr.toUpperCase())
        .replace(/^[a-z]/, (chr) => chr.toUpperCase());
}

function processStyle(styleStr) {
    const parts = styleStr.split(";").filter(Boolean);
    const styles = [];
    for (const part of parts) {
        const [key, value] = part.split(":").map((s) => s.trim());
        if (key && value) {
            const camelKey = key.replace(/-([a-z])/g, (g) =>
                g[1].toUpperCase(),
            );
            styles.push(`${camelKey}: '${value}'`);
        }
    }
    return `{{ ${styles.join(", ")} }}`;
}

function convertHtmlToJsx(html) {
    let content = html;

    // 1. Extract content (main or body)
    const mainMatch = content.match(/<main[^>]*>([\s\S]*?)<\/main>/i);
    if (mainMatch) {
        content = mainMatch[1];
    } else {
        const bodyMatch = content.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
        if (bodyMatch) {
            content = bodyMatch[1];
            // Remove global layout tags if fallback to body
            content = content.replace(/<aside[\s\S]*?<\/aside>/gi, "");
            content = content.replace(/<nav[\s\S]*?<\/nav>/gi, "");
            content = content.replace(/<header[\s\S]*?<\/header>/gi, "");
            content = content.replace(/<footer[\s\S]*?<\/footer>/gi, "");
        }
    }

    // 2. Syntax replacements
    content = content.replace(/\bclass="/g, 'className="');
    content = content.replace(/\bfor="/g, 'htmlFor="'); // Just in case

    // 3. Self closing tags
    content = content.replace(
        /<img([^>]*?)(?:\s*\/)?>/gi,
        (match, p1) => `<img${p1} />`,
    );
    content = content.replace(
        /<input([^>]*?)(?:\s*\/)?>/gi,
        (match, p1) => `<input${p1} />`,
    );
    content = content.replace(/<br(?:\s*\/)?>/gi, "<br />");
    content = content.replace(/<hr(?:\s*\/)?>/gi, "<hr />");

    // 4. Inline styles
    content = content.replace(
        /style="([^"]*)"/g,
        (match, p1) => `style=${processStyle(p1)}`,
    );

    // 5. HTML Comments to JSX Comments
    content = content.replace(/<!--([\s\S]*?)-->/g, "{/* $1 */}");

    return content.trim();
}

function walk(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            walk(fullPath);
        } else if (file.endsWith(".html")) {
            const html = fs.readFileSync(fullPath, "utf8");
            const jsxContent = convertHtmlToJsx(html);

            const componentName = toPascalCase(path.basename(file, ".html"));

            const finalJsx = `const ${componentName} = () => {\n  return (\n    <>\n      ${jsxContent.split("\n").join("\n      ")}\n    </>\n  );\n};\n\nexport default ${componentName};\n`;

            const newPath = fullPath.replace(/\.html$/, ".jsx");
            fs.writeFileSync(newPath, finalJsx);
            console.log("Converted:", file, "->", path.basename(newPath));

            // Remove original html file
            fs.unlinkSync(fullPath);
        }
    }
}

const targetDir = path.join(__dirname, "src", "pages");
console.log("Targeting:", targetDir);
walk(targetDir);
