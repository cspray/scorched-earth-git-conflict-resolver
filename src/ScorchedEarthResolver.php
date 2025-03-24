<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver;

interface ScorchedEarthResolver {

    public function resolve(GitStatusParser $gitStatusParser) : void;

}