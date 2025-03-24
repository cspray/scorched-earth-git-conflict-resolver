<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver;

interface ScorchedEarthFile {

    public function dirtyPath() : string;

    public function cleanPath() : ?string;

    public function conflictType() : ConflictType;

}
