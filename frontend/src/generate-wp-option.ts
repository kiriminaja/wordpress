import fs from "fs";
import glob from "fast-glob";

const ACTION_REGEX = /add_action\(\s*['"]wp_ajax_([^'"]+)['"]\s*,/g;
const INTERNAL_PATH_REGEX = /private const INTERNAL_PATH = \[([\s\S]*?)\];/;

/**
 * Script to generate TypeScript types for WordPress AJAX actions and internal paths
 * by scanning PHP files for add_action('wp_ajax_{action_name}', ...) calls
 * and extracting INTERNAL_PATH constants.
 */
async function run() {
  const files = await glob("**/*.php", { ignore: ["vendor/**"] });

  const actions = new Set<string>();
  const internalPaths = new Set<string>();

  for (const file of files) {
    const content = fs.readFileSync(file, "utf8");

    // Extract AJAX actions
    let match;
    while ((match = ACTION_REGEX.exec(content))) {
      actions.add(match[1]);
    }

    // Extract INTERNAL_PATH constants
    const pathMatch = content.match(INTERNAL_PATH_REGEX);
    if (pathMatch) {
      const pathContent = pathMatch[1];
      const pathRegex = /['"]([^'"]+)['"]/g;
      let pathItem;
      while ((pathItem = pathRegex.exec(pathContent))) {
        internalPaths.add(pathItem[1]);
      }
    }
  }

  const actionsArray = Array.from(actions).sort();
  const pathsArray = Array.from(internalPaths).sort();

  const typeOutput = `
// AUTO-GENERATED FILE — DO NOT EDIT - Run "bun run generate:ajax" to regenerate, need to commit this file
export type WpAjaxAction =
${actionsArray.map((a) => `  | "${a}"`).join("\n")};

export type WpAdminPage =
${pathsArray.map((path) => `  | "${path}"`).join("\n")};

export const WP_ADMIN_PAGES: string[] = [${pathsArray
    .map((path) => `"${path}"`)
    .join(", ")}] as const;
`;

  // Ensure the types directory exists
  if (!fs.existsSync("frontend/src/types")) {
    fs.mkdirSync("frontend/src/types", { recursive: true });
  }

  fs.writeFileSync("frontend/src/types/wp.ts", typeOutput);
  console.info(
    "Generated frontend/src/types/wp.ts with AJAX actions and admin pages"
  );
}

run();
