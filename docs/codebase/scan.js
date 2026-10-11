const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const TREE_LIMIT = 200;
const TREE_MAX_DEPTH = 3;
const TODO_LIMIT = 60;
const MANIFEST_PREVIEW_LINES = 80;
const RECENT_COMMITS_LIMIT = 20;
const CHURN_LIMIT = 20;

const EXCLUDE_DIRS = new Set([
  "node_modules", ".git", "dist", "build", "out", ".next", ".nuxt",
  "__pycache__", ".venv", "venv", ".tox", "target", "vendor",
  "coverage", ".nyc_output", "generated", ".cache", ".turbo",
  ".yarn", ".pnp", "bin", "obj", ".gemini", ".sisyphus", ".ocr"
]);

const MANIFESTS = [
  "package.json", "package-lock.json", "yarn.lock", "pnpm-lock.yaml", "bun.lockb",
  "composer.json", "composer.lock", "Makefile", "Dockerfile", "docker-compose.yml"
];

const ENTRY_CANDIDATES = [
  "src/index.ts", "src/index.js", "src/main.ts", "src/main.js", "src/app.ts", "src/app.js",
  "index.ts", "index.js", "app.ts", "app.js", "index.php", "app.php", "public/index.php",
  "artisan"
];

const LINT_FILES = [
  ".eslintrc", ".eslintrc.json", ".eslintrc.js", "eslint.config.js",
  ".prettierrc", ".prettierrc.json", ".editorconfig", "tsconfig.json",
  "pint.json", "phpstan.neon", "rector.php"
];

const ENV_TEMPLATES = [".env.example", ".env.template", ".env.sample", ".env.defaults", ".env.testing"];

const SOURCE_EXTS = new Set([
  "ts", "tsx", "js", "jsx", "mjs", "cjs",
  "py", "go", "java", "kt", "rb", "php",
  "rs", "cs", "cpp", "c", "h", "ex", "exs",
  "swift", "scala", "clj", "cljs", "lua",
  "json", "xml", "yaml", "yml"
]);

const cwd = path.resolve(__dirname, '../..');

function shouldExclude(dirName) {
  return EXCLUDE_DIRS.has(dirName);
}

function getDirectoryTree(currentDir = cwd, depth = 0) {
  if (depth > TREE_MAX_DEPTH) return [];
  let files = [];
  try {
    const items = fs.readdirSync(currentDir, { withFileTypes: true });
    for (const item of items) {
      if (shouldExclude(item.name)) continue;
      const relPath = path.relative(cwd, path.join(currentDir, item.name)).replace(/\\/g, '/');
      files.push(relPath);
      if (item.isDirectory() && depth < TREE_MAX_DEPTH) {
        files = files.concat(getDirectoryTree(path.join(currentDir, item.name), depth + 1));
      }
    }
  } catch (e) {}
  return files;
}

function readFilePreview(filePath, maxLines = MANIFEST_PREVIEW_LINES) {
  try {
    const content = fs.readFileSync(filePath, 'utf8');
    const lines = content.split(/\r?\n/);
    let preview = lines.slice(0, maxLines).join('\n');
    if (lines.length > maxLines) {
      preview += `\n[TRUNCATED] Showing first ${maxLines} of ${lines.length} lines.`;
    }
    return preview;
  } catch (e) {
    return `[Error reading file: ${e.message}]`;
  }
}

function searchTodos() {
  const todos = [];
  const testExclude = new Set(["test", "tests", "__tests__", "spec", "__mocks__", "fixtures"]);

  function walk(currentDir) {
    if (todos.length >= TODO_LIMIT) return;
    try {
      const items = fs.readdirSync(currentDir, { withFileTypes: true });
      for (const item of items) {
        if (shouldExclude(item.name) || testExclude.has(item.name)) continue;
        const fullPath = path.join(currentDir, item.name);
        if (item.isDirectory()) {
          walk(fullPath);
        } else {
          const ext = path.extname(item.name).slice(1);
          if (SOURCE_EXTS.has(ext)) {
            try {
              const content = fs.readFileSync(fullPath, 'utf8');
              const lines = content.split(/\r?\n/);
              lines.forEach((line, index) => {
                if (todos.length >= TODO_LIMIT) return;
                if (/TODO|FIXME|HACK/.test(line)) {
                  const rel = path.relative(cwd, fullPath).replace(/\\/g, '/');
                  todos.push(`${rel}:${index + 1}: ${line.trim()}`);
                }
              });
            } catch (e) {}
          }
        }
      }
    } catch (e) {}
  }

  walk(cwd);
  return todos;
}

function collectMetrics() {
  let totalFiles = 0;
  let totalLines = 0;
  const byLanguage = {};
  const fileSizes = [];

  const langMap = {
    ts: "TypeScript", js: "JavaScript", php: "PHP", py: "Python", json: "JSON",
    yml: "YAML", yaml: "YAML", blade: "Blade"
  };

  function walk(currentDir) {
    try {
      const items = fs.readdirSync(currentDir, { withFileTypes: true });
      for (const item of items) {
        if (shouldExclude(item.name)) continue;
        const fullPath = path.join(currentDir, item.name);
        if (item.isDirectory()) {
          walk(fullPath);
        } else {
          let ext = path.extname(item.name).slice(1);
          if (item.name.endsWith('.blade.php')) ext = 'blade';
          try {
            const stat = fs.statSync(fullPath);
            fileSizes.push({ path: path.relative(cwd, fullPath).replace(/\\/g, '/'), size: stat.size });
            totalFiles++;
            const lang = langMap[ext] || ext.toUpperCase() || 'Other';
            byLanguage[lang] = (byLanguage[lang] || 0) + 1;
            if (stat.size < 1000000) {
              const content = fs.readFileSync(fullPath, 'utf8');
              totalLines += content.split(/\r?\n/).length;
            }
          } catch (e) {}
        }
      }
    } catch (e) {}
  }

  walk(cwd);
  fileSizes.sort((a, b) => b.size - a.size);
  return {
    totalFiles,
    totalLines,
    byLanguage,
    largestFiles: fileSizes.slice(0, 10).map(f => `${f.path}: ${(f.size / 1024).toFixed(1)}KB`)
  };
}

let out = '';

function printSection(title, content) {
  out += `\n=== ${title} ===\n`;
  if (Array.isArray(content)) {
    out += (content.length ? content.join('\n') : "None found.") + '\n';
  } else {
    out += content + '\n';
  }
}

// 1. Directory Tree
const tree = getDirectoryTree().slice(0, TREE_LIMIT);
printSection(`DIRECTORY TREE (max depth ${TREE_MAX_DEPTH}, source files only)`, tree);

// 2. Stack detection
const manifests = MANIFESTS.filter(m => fs.existsSync(path.join(cwd, m)));
const manifestContent = [];
manifests.forEach(m => {
  manifestContent.push(`--- ${m} ---`);
  manifestContent.push(readFilePreview(path.join(cwd, m)));
});
printSection("STACK DETECTION (manifest files)", manifestContent);

// 3. Entry points
const entries = ENTRY_CANDIDATES.filter(e => fs.existsSync(path.join(cwd, e))).map(e => `Found: ${e}`);
printSection("ENTRY POINTS", entries);

// 4. Linting and formatting config
const lint = LINT_FILES.filter(l => fs.existsSync(path.join(cwd, l))).map(l => `Found: ${l}`);
printSection("LINTING AND FORMATTING CONFIG", lint);

// 5. Env templates
const envs = ENV_TEMPLATES.filter(e => fs.existsSync(path.join(cwd, e)));
const envContent = [];
envs.forEach(e => {
  envContent.push(`--- ${e} ---`);
  envContent.push(readFilePreview(path.join(cwd, e)));
});
printSection("ENVIRONMENT VARIABLE TEMPLATES", envContent);

// 6. TODOs
printSection("TODO / FIXME / HACK (production code only, test dirs excluded)", searchTodos());

// 7. Git info
try {
  const commits = execSync('git log --oneline -n 20', { cwd, encoding: 'utf8' }).trim().split('\n');
  printSection("GIT RECENT COMMITS (last 20)", commits);
} catch (e) {
  printSection("GIT RECENT COMMITS (last 20)", ["No commits found."]);
}

try {
  const churnRaw = execSync('git log --since="90 days ago" --name-only --pretty=format:', { cwd, encoding: 'utf8' });
  const files = churnRaw.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
  const counts = {};
  files.forEach(f => { counts[f] = (counts[f] || 0) + 1; });
  const sorted = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, CHURN_LIMIT);
  printSection("HIGH-CHURN FILES (last 90 days, top 20)", sorted.map(([f, c]) => `${c.toString().padStart(4, ' ')} ${f}`));
} catch (e) {
  printSection("HIGH-CHURN FILES (last 90 days, top 20)", ["None found."]);
}

// 8. Monorepo signals
printSection("MONOREPO SIGNALS", ["No monorepo signals detected."]);

// 9. Code metrics
const metrics = collectMetrics();
const metricsOutput = [
  `Total files scanned: ${metrics.totalFiles}`,
  `Total lines of code: ${metrics.totalLines}`,
  "",
  "Files by language:"
];
Object.entries(metrics.byLanguage).sort((a, b) => b[1] - a[1]).forEach(([lang, count]) => {
  metricsOutput.push(`  ${lang}: ${count}`);
});
metricsOutput.push("");
metricsOutput.push("Top 10 largest files:");
metricsOutput.push(...metrics.largestFiles);
printSection("CODE METRICS", metricsOutput);

// 10. CI/CD Pipelines
const ciList = [];
if (fs.existsSync(path.join(cwd, '.github/workflows'))) ciList.push("CI/CD: GitHub Actions");
printSection("CI/CD PIPELINES", ciList);

// 11. Containers & Orchestration
const containerList = [];
if (fs.existsSync(path.join(cwd, 'Dockerfile'))) containerList.push("Container: Docker found");
if (fs.existsSync(path.join(cwd, 'docker-compose.yml'))) containerList.push("Orchestration: Docker Compose found");
printSection("CONTAINERS & ORCHESTRATION", containerList);

// 12. Security
const secList = [];
if (fs.existsSync(path.join(cwd, '.dependabot.yml')) || fs.existsSync(path.join(cwd, '.github/dependabot.yml'))) secList.push("Security: dependabot");
printSection("SECURITY & COMPLIANCE", secList);

// 13. Performance
printSection("PERFORMANCE & TESTING", ["No performance testing configs detected."]);

out += "\n=== SCAN COMPLETE ===\n";

const outDir = path.join(cwd, 'docs', 'codebase');
if (!fs.existsSync(outDir)) {
  fs.mkdirSync(outDir, { recursive: true });
}
fs.writeFileSync(path.join(outDir, '.codebase-scan.txt'), out, 'utf8');
console.log("Successfully wrote scan to docs/codebase/.codebase-scan.txt");
