# Scorched Earth Git Conflict Resolver

> **DO NOT** use this repository until you have reviewed the entire README and **all** relevant code. There are no warranties or support available if you use this repository and something goes wrong!

A set of PHP scripts intended to resolve Git conflicts across massive amounts of files when using the strategies `ours` or `theirs` is not sufficient. Specifically, it resolves conflicts by "restoring" the conflicted file to a specific target. This is a brute-force, scorched-earth approach to Git conflict resolution.

These scripts perform potentially destructive operations on a Git repository, including staging changes for the repository. It is absolutely critical that you review this library in its entirety before using. Chances are this library is not appropriate for your use-case, and you should use a better strategy for resolving merge conflicts.

## Requirements

- PHP 8.3
- ext-mbstring
- Composer
- Git
- A Git conflict too big to be resolved manually
- A use case where a conflicting file can be entirely rewritten with a "clean" version

## Installation

Are you sure you really need to use this script? _Really_ sure? _Really, really_ sure?

Ok, I guess.

Use [Composer](https://getcomposer.org/).

```shell
composer require cspray/scorched-earth-git-conflict-resolver
```

## Usage

### Step 1: Confirm that this library is the correct solution

Are you looking to resolve conflicts in a file by restoring the _entire_ file, and not simply a chunk _in_ the file? Then this repository is for you! Otherwise, it is not. Chances are, this repo is not appropriate for you.

### Step 2: Start a merge/rebase/cherry-pick that produces conflicts

This library expects the Git repository it'll be operating on, to already be in the middle of a merge, rebase, or cherry-pick that has resulted in merge conflicts. Ultimately, this script relies on the output of `git status --porcelain` to determine which files to operate on and what to do with them. If your repository does not have any output from this command, or results in no conflicts, no operations will be carried out.

### Step 3: Clone the repository target branch to a new directory

This library relies on a second clone of the Git repository that has been checked out to the branch that conflicting files will be resolved from. It is not strictly necessary that this directory be a Git repository; however, it must match the exact file structure in the Git repository from Step 2.

### Step 4: Perform a dry run conflict resolution

It is HIGHLY recommended to perform a dry run operation and review it for potential problems. Executing the command without the dry run flag will cause destructive operations to occur. Please do a dry run!

From the root directory of this script run:

```shell
./vendor/bin/scorch-earth /path/to/git/repo /path/to/clone --dry-run
```

If successful, you should see output that looks similar to the following:

```text
Found 1 conflicting file.

COPY /path/to/clone/conflicting-file TO /path/to/git/repo/conflicting-file
GIT ADD /path/to/git/repo/conflicting-file

If these operations DO appear valid, execute this command without the --dry-run flag to perform actual operations
on the filesystem. NOTE: Performing this command without this flag will perform destructive operations!

If these operations DO NOT appear valid, resolve any errant conflicts manually. It is important that resolution of
this conflict results in the offending file no longer appearing in the output of `git status`.
```

### Step 5: Perform a real conflict resolution

If the dry run looks correct, it is time to perform a real resolution. This will result in files being rewritten or removed. Please ensure the output of the dry run is accurate before continuing with this step!

From the root directory of this script run:

```shell
./vendor/bin/scorch-earth /path/to/git/repo /path/to/clone
```

If successful, you should see output that looks similar to the following:

```text
Found 1 conflicting file.

COPY /path/to/clone/conflicting-file TO /path/to/git/repo/conflicting-file
GIT ADD /path/to/git/repo/conflicting-file

Please review your repository and continue/abort your rebase, cherry-pick, or merge.
```

### Step 6: Review your repository

If your Git conflicts are on the scale and use-case that you need this library, you've just had hundreds of files rewritten or deleted in your repository. You should see a `git status` without any remaining conflicts. You should definitely review and/or test your codebase to ensure the modifications made are accurate. 

### Step 7: Continue or Abort merge/rebase/cherry-pick

Based on the result of Step 6, you should continue or abort your Git operation, as appropriate. Good luck!