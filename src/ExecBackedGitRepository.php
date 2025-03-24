<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver;

final readonly class ExecBackedGitRepository implements GitRepository {

    public function __construct(
        private string $repoDir
    ) {}

    public function path() : string {
        return $this->repoDir;
    }

    public function status() : string {
        exec("cd $this->repoDir && git status --porcelain", $output);
        return join(PHP_EOL, $output);
    }

    public function add(string $file) : void {
        exec("cd $this->repoDir && git add $file");
    }

    public function remove(string $file) : void {
        exec("cd $this->repoDir && git rm $file");
    }
}