<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver;

interface GitRepositoryFactory {

    public function gitRepository(string $repoDir) : GitRepository;

}