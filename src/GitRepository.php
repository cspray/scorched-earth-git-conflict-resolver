<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver;

interface GitRepository {

    public function path() : string;

    public function status() : string;

    public function add(string $file) : void;

    public function remove(string $file) : void;

}