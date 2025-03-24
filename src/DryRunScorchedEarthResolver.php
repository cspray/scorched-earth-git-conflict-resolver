<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver;

use Symfony\Component\Console\Output\OutputInterface;

final readonly class DryRunScorchedEarthResolver implements ScorchedEarthResolver {

    public function __construct(
        private OutputInterface $output,
    ) {}

    public function resolve(GitStatusParser $gitStatusParser) : void {
        $conflictingFileCount = 0;
        /** @var array<non-empty-string, non-empty-string> $copyOperations */
        /** @var array<non-empty-string, ConflictType> $copyOperations */
        $copyOperations = [];
        $removeOperations = [];
        foreach ($gitStatusParser->parse() as $conflictingFile) {
            $conflictingFileCount++;
            if ($conflictingFile->conflictType()->isModified()) {
                $copyOperations[$conflictingFile->dirtyPath()] = $conflictingFile->cleanPath();
                continue;
            }

            if ($conflictingFile->conflictType()->isDeleted()) {
                $removeOperations[$conflictingFile->dirtyPath()] = $conflictingFile->conflictType();
            }

        }

        if ($conflictingFileCount === 0) {
            $this->output->writeln('No conflicting files found! No operations would be performed.');
            return;
        }

        $fileOrFiles = $conflictingFileCount === 1 ? 'file' : 'files';
        $this->output->writeln("Found $conflictingFileCount conflicting $fileOrFiles.");
        $this->output->writeln('');

        foreach ($copyOperations as $dirtyPath => $cleanPath) {
            $this->output->writeln("COPY $cleanPath TO $dirtyPath");
            $this->output->writeln("GIT ADD $dirtyPath");
        }
        foreach ($removeOperations as $dirtyPath => $conflictType) {
            $this->output->writeln("($conflictType->name) GIT RM $dirtyPath");
        }

        $this->output->writeln('');
        $this->output->writeln(<<<TEXT
        If these operations DO appear valid, execute this command without the --dry-run flag to perform actual operations
        on the filesystem. NOTE: Performing this command without this flag will perform destructive operations!
        
        If these operations DO NOT appear valid, resolve any errant conflicts manually. It is important that resolution of
        this conflict results in the offending file no longer appearing in the output of `git status`.
        TEXT);
    }
}