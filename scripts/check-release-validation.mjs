import { pathToFileURL } from 'node:url';

const validationEvents = new Set(['pull_request', 'workflow_dispatch']);

export function latestValidationRun(runs, sha) {
  return runs
    .filter((run) => run.head_sha === sha && validationEvents.has(run.event))
    .sort((left, right) => right.id - left.id || right.run_attempt - left.run_attempt)[0];
}

export async function checkReleaseValidation({
  repository,
  sha,
  token,
  apiUrl = 'https://api.github.com',
  fetchImpl = fetch,
}) {
  if (!/^[\w.-]+\/[\w.-]+$/.test(repository ?? '') || !/^[a-f0-9]{40}$/.test(sha ?? ''))
    throw new Error('A repository and exact commit SHA are required.');
  if (!token) throw new Error('A GitHub token with actions:read permission is required.');

  const runs = [];
  for (let page = 1; ; page++) {
    const url = new URL(
      `${apiUrl.replace(/\/$/, '')}/repos/${repository}/actions/workflows/test.yml/runs`,
    );
    url.search = new URLSearchParams({ head_sha: sha, per_page: '100', page: String(page) });
    const response = await fetchImpl(url, {
      headers: {
        Accept: 'application/vnd.github+json',
        Authorization: `Bearer ${token}`,
        'X-GitHub-Api-Version': '2022-11-28',
      },
    });
    if (!response.ok) throw new Error(`Cannot read Tests workflow runs (HTTP ${response.status}).`);
    const body = await response.json();
    if (!Array.isArray(body.workflow_runs)) throw new Error('Invalid Tests workflow response.');
    runs.push(...body.workflow_runs);
    if (body.workflow_runs.length < 100) break;
  }

  const run = latestValidationRun(runs, sha);
  if (run?.status !== 'completed' || run.conclusion !== 'success')
    throw new Error(
      `Release blocked: ${sha} needs a successful completed Tests workflow run from pull_request or workflow_dispatch. Run Tests manually for this exact commit, then rerun Publish.`,
    );
  return run;
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    const run = await checkReleaseValidation({
      repository: process.env.GITHUB_REPOSITORY,
      sha: process.env.RELEASE_SHA,
      token: process.env.GITHUB_TOKEN,
      apiUrl: process.env.GITHUB_API_URL,
    });
    console.log(`Release validation confirmed: ${run.html_url}`);
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}
