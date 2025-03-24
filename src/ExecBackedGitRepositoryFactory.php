<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver;

class ExecBackedGitRepositoryFactory implements GitRepositoryFactory {

    public function gitRepository(string $repoDir) : GitRepository {
        return new ExecBackedGitRepository($repoDir);
    }
}