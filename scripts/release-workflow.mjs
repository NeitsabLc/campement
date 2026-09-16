#!/usr/bin/env node

import { execFileSync } from "node:child_process";
import { Buffer } from "node:buffer";
import { readFile, writeFile } from "node:fs/promises";
import { existsSync, readFileSync } from "node:fs";
import process from "node:process";
import semver from "semver";
import { analyzeCommits } from "@semantic-release/commit-analyzer";
import { generateNotes } from "@semantic-release/release-notes-generator";

const command = process.argv[2];
const cwd = process.cwd();
const releaseBranch = "release/next";
const changelogTitle = "# Historique des versions";
const logger = {
  log() {},
  success() {},
  warn() {},
  error() {},
};

function fail(message) {
  throw new Error(message);
}

function required(name) {
  const value = process.env[name];
  if (!value) {
    fail(`La variable ${name} est obligatoire.`);
  }
  return value;
}

function git(args) {
  return execFileSync("git", args, { cwd, encoding: "utf8" }).trim();
}

function pluginConfiguration(config, pluginName) {
  const entry = config.plugins.find((plugin) => {
    const name = Array.isArray(plugin) ? plugin[0] : plugin;
    return name === pluginName;
  });
  if (!entry) {
    fail(`Configuration absente pour ${pluginName}.`);
  }
  return Array.isArray(entry) ? entry[1] ?? {} : {};
}

function commitsSince(tag) {
  const output = execFileSync(
    "git",
    ["log", `${tag}..HEAD`, "--format=%H%x1f%B%x1f%an%x1f%ae%x1f%aI%x1e"],
    { cwd, encoding: "utf8" },
  );

  return output
    .split("\x1e")
    .map((record) => record.trim())
    .filter(Boolean)
    .map((record) => {
      const [hash, message, authorName, authorEmail, authoredDate] = record.split("\x1f");
      return {
        hash,
        message: message.trim(),
        author: { name: authorName, email: authorEmail },
        authorDate: authoredDate,
      };
    });
}

function isCiOnlyCommit(commit) {
  const header = commit.message.split(/\r?\n/, 1)[0].trim();
  return /^(?:ci(?:\([^)]+\))?|[a-z][a-z0-9-]*\(ci(?:[./_-][^)]+)?\))!?: /i.test(
    header,
  );
}

async function buildReleasePlan() {
  const config = JSON.parse(await readFile(".releaserc.json", "utf8"));
  const tags = git([
    "tag",
    "--merged",
    "HEAD",
    "--list",
    "v[0-9]*.[0-9]*.[0-9]*",
    "--sort=-v:refname",
  ])
    .split("\n")
    .filter(Boolean);
  const lastTag = tags[0];

  if (!lastTag || !semver.valid(lastTag.slice(1))) {
    fail("Aucun tag sémantique vX.Y.Z valide n’a été trouvé.");
  }

  const commits = commitsSince(lastTag).filter((commit) => !isCiOnlyCommit(commit));
  const releaseType = await analyzeCommits(
    pluginConfiguration(config, "@semantic-release/commit-analyzer"),
    { commits, cwd, logger },
  );

  if (!releaseType) {
    return null;
  }

  const lastVersion = lastTag.slice(1);
  const nextVersion = semver.inc(lastVersion, releaseType);
  if (!nextVersion) {
    fail(`Impossible de calculer la version après ${lastVersion} (${releaseType}).`);
  }

  const lastRelease = {
    version: lastVersion,
    gitTag: lastTag,
    gitHead: git(["rev-list", "-n", "1", lastTag]),
  };
  const nextRelease = {
    type: releaseType,
    version: nextVersion,
    gitTag: `v${nextVersion}`,
    gitHead: git(["rev-parse", "HEAD"]),
    name: `v${nextVersion}`,
  };
  nextRelease.notes = await generateNotes(
    pluginConfiguration(config, "@semantic-release/release-notes-generator"),
    {
      commits,
      lastRelease,
      nextRelease,
      options: { repositoryUrl: config.repositoryUrl },
      branch: { name: process.env.CI_DEFAULT_BRANCH ?? "main" },
      cwd,
      logger,
    },
  );

  return { commits, lastRelease, nextRelease };
}

async function gitlabApi(path, { method = "GET", body, allowNotFound = false } = {}) {
  const apiRoot = required("CI_API_V4_URL");
  const projectId = required("CI_PROJECT_ID");
  const token = required("GITLAB_TOKEN");
  const response = await fetch(`${apiRoot}/projects/${encodeURIComponent(projectId)}${path}`, {
    method,
    headers: {
      "PRIVATE-TOKEN": token,
      ...(body ? { "Content-Type": "application/json" } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  if (allowNotFound && response.status === 404) {
    return null;
  }

  const responseText = await response.text();
  if (!response.ok) {
    fail(
      `GitLab API ${method} ${path}: HTTP ${response.status} ${responseText.slice(0, 500)}`,
    );
  }

  return responseText ? JSON.parse(responseText) : null;
}

async function updateReleaseFiles(plan) {
  const version = plan.nextRelease.version;
  execFileSync("./scripts/update-version.sh", [version], { cwd, stdio: "inherit" });

  const changelog = await readFile("CHANGELOG.md", "utf8");
  if (!changelog.startsWith(changelogTitle)) {
    fail("Le titre du CHANGELOG.md est inattendu.");
  }
  if (changelog.includes(`## [${version}](`)) {
    fail(`La version ${version} existe déjà dans CHANGELOG.md sans tag correspondant.`);
  }

  const existingEntries = changelog.slice(changelogTitle.length).trim();
  const updatedChangelog = [
    changelogTitle,
    "",
    plan.nextRelease.notes.trim(),
    ...(existingEntries ? ["", existingEntries] : []),
    "",
  ].join("\n");
  await writeFile("CHANGELOG.md", updatedChangelog, "utf8");
}

async function releaseBranchMatches(actions, ref) {
  if (!ref) {
    return false;
  }

  const matches = await Promise.all(
    actions.map(async (action) => {
      const query = new URLSearchParams({ ref });
      const file = await gitlabApi(
        `/repository/files/${encodeURIComponent(action.file_path)}?${query}`,
        { allowNotFound: true },
      );
      if (!file) {
        return false;
      }
      return (
        Buffer.from(file.content.replace(/\s/g, ""), "base64").toString("utf8") ===
        action.content
      );
    }),
  );
  return matches.every(Boolean);
}

function releaseAssets() {
  const versionTargets = [
    "app/config/services.yaml",
    "app/src/VersionApplication.php",
  ].filter(
    (file) => existsSync(file) && readFileSync(file, "utf8").includes("x-release-version"),
  );

  if (versionTargets.length !== 1) {
    fail("Un unique fichier applicatif portant la version doit être présent.");
  }

  return ["CHANGELOG.md", "version.txt", ...versionTargets];
}

async function prepareMergeRequest() {
  const defaultBranch = required("CI_DEFAULT_BRANCH");
  const commitSha = required("CI_COMMIT_SHA");
  const plan = await buildReleasePlan();

  if (!plan) {
    console.log("Aucun changement ne nécessite de nouvelle version.");
    return;
  }

  await updateReleaseFiles(plan);
  const version = plan.nextRelease.version;
  const title = `chore(release): v${version}`;
  const actions = await Promise.all(
    releaseAssets().map(async (filePath) => ({
      action: "update",
      file_path: filePath,
      content: await readFile(filePath, "utf8"),
    })),
  );

  const query = new URLSearchParams({
    state: "opened",
    scope: "all",
    source_branch: releaseBranch,
    target_branch: defaultBranch,
    per_page: "1",
  });
  const mergeRequests = await gitlabApi(`/merge_requests?${query}`);
  const description = [
    `## Préparation de la version v${version}`,
    "",
    "Cette MR est générée automatiquement à partir des commits conventionnels fusionnés depuis le dernier tag.",
    "",
    plan.nextRelease.notes.trim(),
    "",
    "Après validation de la CI et fusion, GitLab créera le tag, la release, les images signées et le déploiement en recette.",
  ].join("\n");
  const mergeRequestBody = {
    title,
    description,
    squash: true,
    remove_source_branch: true,
  };

  if (
    mergeRequests.length > 0 &&
    mergeRequests[0].title === title &&
    (await releaseBranchMatches(actions, mergeRequests[0].sha))
  ) {
    console.log(`MR de release déjà à jour : ${mergeRequests[0].web_url}`);
    return;
  }

  await gitlabApi("/repository/commits", {
    method: "POST",
    body: {
      branch: releaseBranch,
      start_sha: commitSha,
      force: true,
      commit_message: title,
      actions,
    },
  });

  let mergeRequest;
  if (mergeRequests.length > 0) {
    mergeRequest = await gitlabApi(`/merge_requests/${mergeRequests[0].iid}`, {
      method: "PUT",
      body: mergeRequestBody,
    });
  } else {
    mergeRequest = await gitlabApi("/merge_requests", {
      method: "POST",
      body: {
        source_branch: releaseBranch,
        target_branch: defaultBranch,
        ...mergeRequestBody,
      },
    });
  }

  console.log(`MR de release prête : ${mergeRequest.web_url}`);
}

function changelogSection(version, changelog) {
  const marker = `## [${version}](`;
  const start = changelog.indexOf(marker);
  if (start < 0) {
    fail(`La version ${version} est absente de CHANGELOG.md.`);
  }
  const next = changelog.indexOf("\n## ", start + marker.length);
  return changelog.slice(start, next < 0 ? undefined : next).trim();
}

async function publishRelease() {
  const commitSha = required("CI_COMMIT_SHA");
  const commitMessage = required("CI_COMMIT_MESSAGE");
  const version = (await readFile("version.txt", "utf8")).trim();
  if (!semver.valid(version)) {
    fail("version.txt ne contient pas une version sémantique valide.");
  }

  const expectedTitle = `chore(release): v${version}`;
  const containsExpectedTitle = commitMessage
    .split(/\r?\n/)
    .some((line) => line === expectedTitle || line.startsWith(`${expectedTitle} `));
  if (!containsExpectedTitle) {
    fail(`Le message du commit de fusion doit contenir « ${expectedTitle} ».`);
  }

  const tag = `v${version}`;
  const description = changelogSection(version, await readFile("CHANGELOG.md", "utf8"));
  const encodedTag = encodeURIComponent(tag);
  const existingRelease = await gitlabApi(`/releases/${encodedTag}`, {
    allowNotFound: true,
  });

  if (existingRelease) {
    if (existingRelease.commit?.id !== commitSha) {
      fail(`La release ${tag} existe déjà sur un autre commit.`);
    }
    await gitlabApi(`/releases/${encodedTag}`, {
      method: "PUT",
      body: { name: tag, description },
    });
    console.log(`Release ${tag} déjà présente et vérifiée.`);
    return;
  }

  const release = await gitlabApi("/releases", {
    method: "POST",
    body: {
      name: tag,
      tag_name: tag,
      tag_message: `Release ${tag}`,
      ref: commitSha,
      description,
    },
  });
  console.log(`Release créée : ${release._links?.self ?? tag}`);
}

if (command === "plan") {
  const plan = await buildReleasePlan();
  console.log(plan ? JSON.stringify(plan.nextRelease, null, 2) : "Aucune release nécessaire.");
} else if (command === "prepare-mr") {
  await prepareMergeRequest();
} else if (command === "publish") {
  await publishRelease();
} else {
  fail("Usage : release-workflow.mjs plan|prepare-mr|publish");
}
