<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver;

use Generator;

final readonly class GitStatusParser {

    private string $dirtyRepoDirectory;

    public function __construct(
        private GitRepository $git,
        private string        $cleanRepoDirectory
    ) {
        $this->dirtyRepoDirectory = $this->git->path();
    }

    public function parse() : Generator {
        $status = $this->git->status();
        foreach (explode(PHP_EOL, $status) as $line) {
            $status = mb_substr($line, 0, 2);
            switch ($status) {
                case 'UU':
                    yield $this->createScorchedEarthFile(ConflictType::BothModified, mb_substr($line, 3));
                    break;
                case 'DU':
                    yield $this->createScorchedEarthFile(ConflictType::DeletedByUs, mb_substr($line, 3));
                    break;
                case 'UD':
                    yield $this->createScorchedEarthFile(ConflictType::DeletedByThem, mb_substr($line, 3));
                    break;
                case 'DD':
                    yield $this->createScorchedEarthFile(ConflictType::BothDeleted, mb_substr($line, 3));
                    break;
            }
        }
    }

    private function createScorchedEarthFile(ConflictType $conflictType, string $gitStatusPath) : ScorchedEarthFile {
        $dirtyPath = $this->dirtyRepoDirectory . '/' . $gitStatusPath;
        $cleanPath = $conflictType->isDeleted() ? null : $this->cleanRepoDirectory . '/' . $gitStatusPath;
        return new readonly class($conflictType, $dirtyPath, $cleanPath) implements ScorchedEarthFile {

            public function __construct(
                private ConflictType $conflictType,
                private string $dirtyPath,
                private ?string $cleanPath,
            ) {}

            public function dirtyPath() : string {
                return $this->dirtyPath;
            }

            public function cleanPath() : ?string {
                return $this->cleanPath;
            }

            public function conflictType() : ConflictType {
                return $this->conflictType;
            }
        };

    }

}