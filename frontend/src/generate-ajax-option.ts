import fs from "fs";
import glob from "fast-glob";

const ACTION_REGEX = /add_action\(\s*['"]wp_ajax_([^'"]+)['"]\s*,/g;

/**
 * Script to generate TypeScript types for WordPress AJAX actions
 * by scanning PHP files for add_action('wp_ajax_{action_name}', ...) calls.
 */
async function run() {
  const files = await glob("**/*.php", { ignore: ["vendor/**"] });

  const actions = new Set<string>();

  for (const file of files) {
    const content = fs.readFileSync(file, "utf8");

    let match;
    while ((match = ACTION_REGEX.exec(content))) {
      actions.add(match[1]);
    }
  }

  const typeOutput = `
// AUTO-GENERATED FILE — DO NOT EDIT - Run "bun run generate:ajax" to regenerate, need to commit this file
export type WpAjaxAction =
${Array.from(actions)
  .map((a) => `  | "${a}"`)
  .join("\n")};
`;

  // Ensure the types directory exists
  if (!fs.existsSync("frontend/src/types")) {
    fs.mkdirSync("frontend/src/types", { recursive: true });
  }

  fs.writeFileSync("frontend/src/types/wp-ajax-actions.d.ts", typeOutput);
  console.info("Generated frontend/src/types/wp-ajax-actions.d.ts");
}

run();
