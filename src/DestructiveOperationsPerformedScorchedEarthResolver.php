<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver;

use Symfony\Component\Console\Output\OutputInterface;

final readonly class DestructiveOperationsPerformedScorchedEarthResolver implements ScorchedEarthResolver {

    public function __construct(
        private GitRepository $gitRepository,
        private OutputInterface $output
    ) {}

    public function resolve(GitStatusParser $gitStatusParser) : void {
        $conflictingFileCount = 0;
        $copyOperations = [];
        $removeOperations = [];
        foreach ($gitStatusParser->parse() as $conflictingFile) {
            $conflictingFileCount++;

            if ($conflictingFile->conflictType()->isModified()) {
                $dirtyPath = $conflictingFile->dirtyPath();
                $cleanPath = $conflictingFile->cleanPath();
                $copyOperations[$dirtyPath] = $cleanPath;
                continue;
            }

            if ($conflictingFile->conflictType()->isDeleted()) {
                $removeOperations[$conflictingFile->dirtyPath()] = $conflictingFile->conflictType();
            }
        }


        $this->output->writeln("Found $conflictingFileCount conflicting file.");
        $this->output->writeln('');
        foreach ($copyOperations as $dirtyPath => $cleanPath) {
            file_put_contents($dirtyPath, file_get_contents($cleanPath));
            $this->output->writeln("COPY $cleanPath TO $dirtyPath");
            $this->gitRepository->add($dirtyPath);
            $this->output->writeln("GIT ADD $dirtyPath");
        }

        foreach ($removeOperations as $dirtyPath => $conflictType) {
            $this->gitRepository->remove($dirtyPath);
            $this->output->writeln("($conflictType->name) GIT RM $dirtyPath");
        }

        $this->output->writeln('');
        $this->output->writeln('Please review your repository and continue/abort your rebase, cherry-pick, or merge.');
    }
}