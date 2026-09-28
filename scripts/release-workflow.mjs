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
    [
      "log",
      `${tag}..HEAD`,
      "--not",
      "--tags",
      "--format=%H%x1f%B%x1f%an%x1f%ae%x1f%aI%x1e",
    ],
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
      branch: { name: process.env.GITHUB_DEFAULT_BRANCH ?? "main" },
      cwd,
      logger,
    },
  );

  return { commits, lastRelease, nextRelease };
}

async function githubApi(path, { method = "GET", body, allowNotFound = false } = {}) {
  const repository = required("GITHUB_REPOSITORY");
  const token = required("GITHUB_TOKEN");
  const response = await fetch(`https://api.github.com/repos/${repository}${path}`, {
    method,
    headers: {
      Accept: "application/vnd.github+json",
      Authorization: `Bearer ${token}`,
      "X-GitHub-Api-Version": "2022-11-28",
      "User-Agent": "campement-release-workflow",
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
      `GitHub API ${method} ${path}: HTTP ${response.status} ${responseText.slice(0, 500)}`,
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
      const encodedPath = action.file_path.split("/").map(encodeURIComponent).join("/");
      const file = await githubApi(
        `/contents/${encodedPath}?${query}`,
        { allowNotFound: true },
      );
      if (!file) {
        return false;
      }
      return (
        Buffer.from(file.content.replace(/\s/g, ""), "base64").toString("utf8") === action.content
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
  const defaultBranch = required("GITHUB_DEFAULT_BRANCH");
  const commitSha = required("GITHUB_SHA");
  const repositoryOwner = required("GITHUB_REPOSITORY_OWNER");
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
    head: `${repositoryOwner}:${releaseBranch}`,
    base: defaultBranch,
    per_page: "1",
  });
  const pullRequests = await githubApi(`/pulls?${query}`);
  const description = [
    `## Préparation de la version v${version}`,
    "",
    "Cette PR est générée automatiquement à partir des commits conventionnels fusionnés depuis le dernier tag.",
    "",
    plan.nextRelease.notes.trim(),
    "",
    "Après validation de la CI et fusion, GitHub créera le tag, la release, les images signées et le déploiement en recette.",
  ].join("\n");
  const pullRequestBody = {
    title,
    body: description,
  };

  if (
    pullRequests.length > 0 &&
    pullRequests[0].title === title &&
    (await releaseBranchMatches(actions, pullRequests[0].head.sha))
  ) {
    console.log(`PR de release déjà à jour : ${pullRequests[0].html_url}`);
    return;
  }

  execFileSync("git", ["config", "user.name", "github-actions[bot]"], { cwd });
  execFileSync("git", ["config", "user.email", "41898282+github-actions[bot]@users.noreply.github.com"], { cwd });
  execFileSync("git", ["switch", "--force-create", releaseBranch, commitSha], { cwd });
  execFileSync("git", ["add", "--", ...releaseAssets()], { cwd });
  execFileSync("git", ["commit", "--message", title], { cwd, stdio: "inherit" });

  const remoteRef = await githubApi(
    `/git/ref/heads/${encodeURIComponent(releaseBranch)}`,
    { allowNotFound: true },
  );
  const pushArguments = ["push"];
  if (remoteRef) {
    pushArguments.push(`--force-with-lease=refs/heads/${releaseBranch}:${remoteRef.object.sha}`);
  }
  pushArguments.push("origin", `HEAD:refs/heads/${releaseBranch}`);
  execFileSync("git", pushArguments, { cwd, stdio: "inherit" });

  let pullRequest;
  if (pullRequests.length > 0) {
    pullRequest = await githubApi(`/pulls/${pullRequests[0].number}`, {
      method: "PATCH",
      body: pullRequestBody,
    });
  } else {
    pullRequest = await githubApi("/pulls", {
      method: "POST",
      body: {
        head: releaseBranch,
        base: defaultBranch,
        ...pullRequestBody,
      },
    });
  }

  console.log(`PR de release prête : ${pullRequest.html_url}`);
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
  const commitSha = required("GITHUB_SHA");
  const commitMessage = required("GITHUB_COMMIT_MESSAGE");
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
  const existingRelease = await githubApi(`/releases/tags/${encodedTag}`, {
    allowNotFound: true,
  });

  if (existingRelease) {
    const tagReference = await githubApi(`/git/ref/tags/${encodedTag}`);
    let tagCommitSha = tagReference.object.sha;
    if (tagReference.object.type === "tag") {
      const annotatedTag = await githubApi(`/git/tags/${tagCommitSha}`);
      tagCommitSha = annotatedTag.object.sha;
    }
    if (tagCommitSha !== commitSha) {
      fail(`La release ${tag} existe déjà sur un autre commit.`);
    }
    await githubApi(`/releases/${existingRelease.id}`, {
      method: "PATCH",
      body: { name: tag, body: description, draft: false, prerelease: false },
    });
    console.log(`Release ${tag} déjà présente et vérifiée.`);
    return;
  }

  const release = await githubApi("/releases", {
    method: "POST",
    body: {
      name: tag,
      tag_name: tag,
      target_commitish: commitSha,
      body: description,
      draft: false,
      prerelease: false,
    },
  });
  console.log(`Release créée : ${release.html_url ?? tag}`);
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
